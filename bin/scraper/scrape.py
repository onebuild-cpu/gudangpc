"""Scrape Indonesian marketplace product pages into the GudangPC import CSV.

The script opens a list of product URLs in a real Chromium browser, copies the
title, price, seller name, description and image of every page and writes the
result to a CSV and an XLSX file whose columns match bin/import_products.php.

The page HTML is read with BeautifulSoup, so the copied text never contains
HTML tags or duplicated markup.

The script never touches the database. The five identity columns (product_slug,
name, category_slug, brand_slug, vendor_slug) are left empty on purpose: a
human fills them in before running the importer.
"""

from __future__ import annotations

import argparse
import csv
import json
import random
import re
import sys
import time
from dataclasses import dataclass
from datetime import date
from pathlib import Path
from typing import Any, Dict, List, Optional, Tuple
from urllib.parse import urlparse

import pandas as pd
from bs4 import BeautifulSoup
from playwright.sync_api import Error as PlaywrightError
from playwright.sync_api import Page, TimeoutError as PlaywrightTimeoutError
from playwright.sync_api import sync_playwright

SCRIPT_DIR = Path(__file__).resolve().parent
PROFILE_ROOT = SCRIPT_DIR / ".profiles"

OUTPUT_COLUMNS: List[str] = [
    "product_slug",
    "name",
    "category_slug",
    "brand_slug",
    "marketplace",
    "vendor_slug",
    "vendor_name",
    "price_idr",
    "affiliate_url",
    "seller_title",
    "product_rating",
    "sold_count",
    "store_rating",
    "short_description",
    "description",
    "image_url",
    "notes",
]

MARKETPLACE_BY_DOMAIN: Dict[str, str] = {
    "shopee.co.id": "shopee",
    "tokopedia.com": "tokopedia",
    "lazada.co.id": "lazada",
    "tiktok.com": "tiktok",
}

SHORT_LINK_DOMAINS: Dict[str, str] = {
    "shope.ee": "shopee",
    "tokopedia.link": "tokopedia",
}

SUPPORTED_MARKETPLACES: Tuple[str, ...] = ("lazada", "tokopedia", "shopee")

LOGIN_URLS: Dict[str, str] = {
    "shopee": "https://shopee.co.id",
    "tokopedia": "https://www.tokopedia.com",
}

PROFILE_FOLDERS: Dict[str, str] = {
    "shopee": "shopee",
    "tokopedia": "tokopedia_browser",
}

CAPTCHA_MARKERS: Tuple[str, ...] = (
    "security check",
    "drag the puzzle piece",
    "verify to continue",
    "captcha",
)

LOGIN_MARKERS: Tuple[str, ...] = ("masuk diperlukan", "log in", "login")

REGION_MARKERS: Tuple[str, ...] = (
    "not currently available in your country or region",
    "tidak tersedia di negara atau wilayah anda",
)

SELECTORS: Dict[str, Dict[str, Any]] = {
    "lazada": {
        "ready": "h1, [class*='pdp-mod-product-badge-title']",
        "price": ["div[class*='price']"],
        "image": ["img.item-gallery__image-photo", ".pdp-product-media img", "img"],
        "vendor": [
            "[class*='shop-card']",
            "[class*='shop-name']",
            "[class*='seller']",
            "[class*='shop']",
        ],
    },
    "tokopedia": {
        "ready": "h1, [data-testid='product-title']",
        "price": ["div[class*='price']"],
        "image": ["img.item-gallery__image-photo", ".pdp-product-media img", "img"],
        "vendor": [
            "[class*='shop-name']",
            "[class*='prd-shop']",
            "[class*='seller']",
            "[class*='shop']",
        ],
    },
    "shopee": {
        "ready": "h1, [data-sqe='name']",
        "price": ["div[class*='price']"],
        "image": ["img.item-gallery__image-photo", ".pdp-product-media img", "img"],
        "shop_link": "a[href*='/shop/']",
        "vendor": [
            "[class*='shop-name' i]",
            "[class*='seller-name' i]",
            "[class*='store-name' i]",
            "[class*='seller']",
            "[class*='shop']",
        ],
    },
}

CHROMIUM_ARGS: List[str] = ["--disable-http2", "--disable-quic"]

GOTO_TIMEOUT_MS = 45_000
READY_TIMEOUT_MS = 45_000
SETTLE_TIMEOUT_MS = 5_000
SLEEP_MIN_SECONDS = 3.0
SLEEP_MAX_SECONDS = 6.0
PRICE_TEXT_LIMIT = 120
SHOP_TEXT_LIMIT = 80
RATING_TEXT_LIMIT = 60
RATING_KEYWORDS: Tuple[str, ...] = ("rating", "bintang", "star")
SHOP_TEXT_BLOCKLIST: Tuple[str, ...] = (
    "icon",
    "cart",
    "search",
    "menu",
    "log in",
    "login",
    "register",
    "home",
    "download",
    "language",
    "bahasa",
    "english",
    "setting",
    "notification",
    "chat",
    "help",
    "courier",
    "favorite",
    "shopping",
)
SOLD_PATTERN = re.compile(r"(?:terjual|sold)\s*:?\s*(\d[\d.,]*)", re.IGNORECASE)
RATING_NUMBER_PATTERN = re.compile(r"(?<![\d.,])([0-5](?:[.,]\d{1,2})?)(?![\d.,])")
SHOP_LINK_PATTERN = re.compile(r"/shop/[a-z0-9\-_]+", re.IGNORECASE)
SHOP_PROMO_PATTERN = re.compile(r"pengembalian|bebas|dulu|\bcod\b", re.IGNORECASE)
SHOP_NAME_WORDS = 4
BAD_IMAGE_PARTS: Tuple[str, ...] = ("t.co", "twitter")

XLSX_COLUMN_WIDTHS: Dict[str, int] = {
    "product_slug": 26,
    "name": 34,
    "category_slug": 16,
    "brand_slug": 14,
    "marketplace": 14,
    "vendor_slug": 22,
    "vendor_name": 24,
    "price_idr": 12,
    "affiliate_url": 46,
    "seller_title": 46,
    "product_rating": 14,
    "sold_count": 12,
    "store_rating": 13,
    "short_description": 40,
    "description": 40,
    "image_url": 46,
    "notes": 20,
}


@dataclass
class ScrapeResult:
    """One scraped target, ready to be written to the output file."""

    marketplace: str = ""
    vendor_name: str = ""
    price_idr: str = ""
    affiliate_url: str = ""
    seller_title: str = ""
    product_rating: str = ""
    sold_count: str = ""
    store_rating: str = ""
    short_description: str = ""
    description: str = ""
    image_url: str = ""
    notes: str = ""


def url_host(url: str) -> str:
    """Return the lower case host of a URL without a leading www."""
    parsed = urlparse(url.strip())
    host = (parsed.hostname or "").lower()
    if host.startswith("www."):
        host = host[4:]
    return host


def detect_marketplace(url: str) -> str:
    """Return the marketplace slug of a URL, or an empty string when unknown."""
    host = url_host(url)
    if host in SHORT_LINK_DOMAINS:
        return SHORT_LINK_DOMAINS[host]
    for domain, slug in MARKETPLACE_BY_DOMAIN.items():
        if host == domain or host.endswith("." + domain):
            return slug
    return ""


def profile_dir(site: str) -> Path:
    """Return the folder that holds the saved browser profile of a marketplace."""
    return PROFILE_ROOT / site


def short_reason(error: Exception) -> str:
    """Return a short single word reason for an exception."""
    text = str(error).strip()
    if not text:
        text = error.__class__.__name__
    text = re.sub(r"\s+", " ", text)
    text = re.sub(r"[^a-zA-Z0-9_:\-]+", "_", text)
    return text.strip("_").lower()[:80]


def apply_stealth(page: Page) -> None:
    """Apply playwright-stealth to a page, supporting the 2.x and the 1.x API."""
    try:
        from playwright_stealth import Stealth
    except ImportError:
        Stealth = None  # type: ignore[assignment]

    if Stealth is not None:
        try:
            Stealth().use_sync(page)
            return
        except Exception:
            pass

    try:
        from playwright_stealth import stealth_sync

        stealth_sync(page)
    except Exception:
        pass


def normalize_price(text: str) -> str:
    """Return the digits of the first price in a text, e.g. 'Rp 194.000' -> '194000'."""
    if not text:
        return ""
    cleaned = text.replace("Rp", " ").replace("IDR", " ")
    match = re.search(r"\d[\d.,]*", cleaned)
    if not match:
        return ""
    number = match.group(0)
    number = re.sub(r"[,.](\d{2})$", "", number)
    return re.sub(r"\D", "", number)


def find_key_deep(payload: Any, keys: Tuple[str, ...]) -> Any:
    """Return the first value found for any of the given keys in a nested payload."""
    if isinstance(payload, dict):
        for key in keys:
            if key in payload:
                value = payload[key]
                if value is not None and value != "":
                    return value
        for value in payload.values():
            found = find_key_deep(value, keys)
            if found is not None:
                return found
    elif isinstance(payload, list):
        for item in payload:
            found = find_key_deep(item, keys)
            if found is not None:
                return found
    return None


def iter_json_ld_nodes(payload: Any) -> List[Dict[str, Any]]:
    """Return every JSON-LD object of a parsed script payload."""
    nodes: List[Dict[str, Any]] = []
    if isinstance(payload, dict):
        nodes.append(payload)
        graph = payload.get("@graph")
        if isinstance(graph, list):
            for item in graph:
                if isinstance(item, dict):
                    nodes.append(item)
    elif isinstance(payload, list):
        for item in payload:
            nodes.extend(iter_json_ld_nodes(item))
    return nodes


def is_product_node(node: Dict[str, Any]) -> bool:
    """Return True when a JSON-LD object describes a product."""
    node_type = node.get("@type")
    if isinstance(node_type, list):
        values = [str(value).lower() for value in node_type]
    else:
        values = [str(node_type).lower()]
    for value in values:
        if value == "product" or value.endswith("/product"):
            return True
    return False


def json_ld_price(node: Dict[str, Any]) -> str:
    """Return the price of a JSON-LD product node as text."""
    offers = node.get("offers")
    if isinstance(offers, list):
        for offer in offers:
            if isinstance(offer, dict) and offer.get("price"):
                return str(offer["price"])
        return ""
    if isinstance(offers, dict):
        return str(offers.get("price", ""))
    return ""


def json_ld_image(value: Any) -> str:
    """Return the first image URL of a JSON-LD image value."""
    if isinstance(value, str):
        return absolute_url(value)
    if isinstance(value, list):
        for item in value:
            found = json_ld_image(item)
            if found:
                return found
        return ""
    if isinstance(value, dict):
        url = value.get("url")
        if isinstance(url, str):
            return absolute_url(url)
    return ""


def absolute_url(url: str) -> str:
    """Return an absolute URL, adding https:// to a protocol relative URL."""
    value = url.strip()
    if value.startswith("//"):
        return "https:" + value
    return value


def clean_whitespace(text: str) -> str:
    """Collapse every run of whitespace into a single space and strip the result."""
    return re.sub(r"\s+", " ", text).strip()


def soup_text(node: Any) -> str:
    """Return the clean text of a BeautifulSoup node."""
    if node is None:
        return ""
    return clean_whitespace(node.get_text(separator=" ", strip=True))


def soup_meta(soup: BeautifulSoup, name: str) -> str:
    """Return the content of a meta tag of the parsed page."""
    for attribute in ("property", "name"):
        tag = soup.find("meta", attrs={attribute: name})
        if tag is None:
            continue
        content = tag.get("content", "")
        if isinstance(content, str) and content.strip():
            return clean_whitespace(content)
    return ""


def soup_json_ld(soup: BeautifulSoup) -> Dict[str, Any]:
    """Return the first JSON-LD product object of the parsed page, or an empty dict."""
    for script in soup.find_all("script", attrs={"type": "application/ld+json"}):
        try:
            payload = json.loads(script.string or script.get_text())
        except (ValueError, TypeError):
            continue
        for node in iter_json_ld_nodes(payload):
            if is_product_node(node):
                return node
    return {}


def extract_page_cache(page: Page) -> Any:
    """Return the window cache payload of the page, or None."""
    for expression in ("() => window.__NEXT_DATA__ || null", "() => window.__cache || null"):
        try:
            payload = page.evaluate(expression)
        except PlaywrightError:
            payload = None
        if payload:
            return payload
    return None


def normalize_rating(value: str) -> str:
    """Return a rating as a number string, e.g. '4,9' -> '4.9'."""
    text = value.strip().replace(",", ".")
    try:
        number = float(text)
    except ValueError:
        return ""
    return str(number)


def element_keywords(element: Any) -> List[str]:
    """Return the texts of an element that can say that it carries a rating."""
    classes = element.get("class") or []
    if isinstance(classes, str):
        classes = [classes]
    sources = [" ".join(classes)]
    sources.append(element.get_text(separator=" ", strip=True))
    for attribute in ("title", "aria-label", "content", "data-rating", "data-testid", "data-qa-locator", "id"):
        value = element.get(attribute)
        if isinstance(value, str) and value:
            sources.append(value)
    return sources


def element_numbers(element: Any) -> List[str]:
    """Return the visible texts of an element that can hold the rating number."""
    sources = [element.get_text(separator=" ", strip=True)]
    for attribute in ("title", "aria-label", "content", "data-rating"):
        value = element.get(attribute)
        if isinstance(value, str) and value:
            sources.append(value)
    return sources


def element_rating(element: Any) -> str:
    """Return the rating of an element as a number, e.g. '4.9', or an empty string."""
    has_keyword = False
    for source in element_keywords(element):
        lowered = source.lower()
        for keyword in RATING_KEYWORDS:
            if keyword in lowered:
                has_keyword = True
    if not has_keyword:
        return ""

    for source in element_numbers(element):
        if not source or len(source) > RATING_TEXT_LIMIT:
            continue
        match = RATING_NUMBER_PATTERN.search(source)
        if match:
            return normalize_rating(match.group(1))
    return ""


def text_rating(text: str) -> str:
    """Return the rating of a short text that mentions a rating, e.g. 'Rating 4.9'."""
    value = text.strip()
    if not value or len(value) > RATING_TEXT_LIMIT:
        return ""
    lowered = value.lower()
    has_keyword = False
    for keyword in RATING_KEYWORDS:
        if keyword in lowered:
            has_keyword = True
    if not has_keyword:
        return ""
    match = RATING_NUMBER_PATTERN.search(value)
    if not match:
        return ""
    return normalize_rating(match.group(1))


def find_rating_candidates(soup: BeautifulSoup) -> List[Tuple[Any, str]]:
    """Return every element that carries a rating, in document order."""
    candidates: List[Tuple[Any, str]] = []
    seen: set = set()

    for element in soup.select(
        "[class*='rating' i], [class*='star' i], [class*='bintang' i], "
        "[id*='rating' i], [data-testid*='rating' i], [data-qa-locator*='rating' i]"
    ):
        value = element_rating(element)
        if not value:
            continue
        key = id(element)
        if key in seen:
            continue
        seen.add(key)
        candidates.append((element, value))

    for text_node in soup.find_all(string=True):
        value = text_rating(str(text_node))
        if not value:
            continue
        parent = text_node.parent
        if parent is None:
            continue
        key = id(parent)
        if key in seen:
            continue
        seen.add(key)
        candidates.append((parent, value))

    return candidates


def is_in_footer(element: Any) -> bool:
    """Return True when the element sits inside a footer or a navigation block."""
    for parent in element.parents:
        name = getattr(parent, "name", "")
        if name in ("footer", "nav"):
            return True
    return False


def is_shop_name_text(text: str) -> bool:
    """Return True when a text can be the name of a shop."""
    if not text:
        return False
    lowered = text.lower()
    for blocked in SHOP_TEXT_BLOCKLIST:
        if blocked in lowered:
            return False
    if SHOP_PROMO_PATTERN.search(lowered):
        return False
    return True


def shop_name_of(element: Any) -> str:
    """Return the shop name of a shop element, without the rating or the address."""
    if element is None:
        return ""
    for child in element.find_all(True):
        classes = child.get("class") or []
        if isinstance(classes, str):
            classes = [classes]
        class_text = " ".join(classes).lower()
        if "title" not in class_text and "name" not in class_text:
            continue
        text = soup_text(child)
        if not text or len(text) > SHOP_TEXT_LIMIT:
            continue
        if text_rating(text) != "":
            continue
        if not is_shop_name_text(text):
            continue
        return text

    text = soup_text(element)
    if not text or len(text) > SHOP_TEXT_LIMIT:
        return ""
    if text_rating(text) != "":
        return ""
    if not is_shop_name_text(text):
        return ""
    return text


def find_shop_element(soup: BeautifulSoup, selectors: Dict[str, Any]) -> Any:
    """Return the element that holds the shop name, or None."""
    link_selector = ""
    if "shop_link" in selectors:
        link_selector = str(selectors["shop_link"])

    if link_selector:
        element = find_shop_link(soup, link_selector)
        if element is not None:
            return element

    for selector in selectors["vendor"]:
        for element in soup.select(selector):
            if not shop_name_of(element):
                continue
            if is_in_footer(element):
                continue
            return element

    element = find_shop_link(soup, "a[href*='/shop']")
    if element is not None:
        return element

    return None


def find_shop_link(soup: BeautifulSoup, selector: str) -> Any:
    """Return the link that points to the shop page, or None."""
    for element in soup.select(selector):
        href = element.get("href", "")
        if not isinstance(href, str):
            continue
        if not SHOP_LINK_PATTERN.search(href):
            continue
        text = soup_text(element)
        if not text or len(text) > SHOP_TEXT_LIMIT:
            continue
        if len(text.split()) > SHOP_NAME_WORDS:
            continue
        if not is_shop_name_text(text):
            continue
        if is_in_footer(element):
            continue
        return element
    return None


def assign_ratings(candidates: List[Tuple[Any, str]], shop_element: Any, order_index: Dict[int, int]) -> Tuple[str, str]:
    """Return the product rating and the store rating of a page."""
    product_rating = ""
    store_rating = ""
    shop_position = -1
    if shop_element is not None:
        shop_position = order_index.get(id(shop_element), -1)

    for element, value in candidates:
        inside_shop = False
        if shop_element is not None:
            inside_shop = element is shop_element or shop_element in element.parents
        if inside_shop:
            if store_rating == "":
                store_rating = value
            continue
        position = order_index.get(id(element), -1)
        if shop_position >= 0 and position < shop_position:
            if product_rating == "":
                product_rating = value
            continue
        if store_rating == "":
            store_rating = value

    if shop_position < 0 and candidates:
        product_rating = candidates[0][1]
        if len(candidates) > 1:
            store_rating = candidates[1][1]

    return product_rating, store_rating


def find_sold_count(soup: BeautifulSoup) -> str:
    """Return the number of sold items of the page, e.g. 'Terjual 8' -> '8'."""
    for text_node in soup.find_all(string=True):
        value = str(text_node).strip()
        if not value or len(value) > RATING_TEXT_LIMIT:
            continue
        match = SOLD_PATTERN.search(value)
        if not match:
            continue
        digits = re.sub(r"\D", "", match.group(1))
        if digits:
            return digits
    return ""


def clean_image_url(url: str) -> str:
    """Return an image URL, or an empty string when it is not a product picture."""
    value = url.strip()
    if not value:
        return ""
    lowered = value.lower()
    for part in BAD_IMAGE_PARTS:
        if part in lowered:
            return ""
    return value


def find_generic_price(soup: BeautifulSoup) -> str:
    """Return the first price of the page, e.g. the text 'Rp 194.000' -> '194000'."""
    for text_node in soup.find_all(string=True):
        value = str(text_node).strip()
        if not value or len(value) > 60:
            continue
        if not value.startswith("Rp"):
            continue
        price = normalize_price(value)
        if price:
            return price
    return ""


def fill_from_cache(fields: Dict[str, str], payload: Any) -> None:
    """Copy the title, price and image of a page cache payload."""
    if not payload:
        return
    if fields["title"] == "":
        title = find_key_deep(payload, ("productName", "product_name", "productTitle"))
        if isinstance(title, str) and title.strip():
            fields["title"] = clean_whitespace(title)
    if fields["price"] == "":
        price = find_key_deep(payload, ("priceFormatted", "formattedPrice", "priceText", "price"))
        if price is not None:
            value = normalize_price(str(price))
            if value:
                fields["price"] = value
    if fields["image"] == "":
        image = find_key_deep(payload, ("imageUrl", "imageFront", "image"))
        if isinstance(image, str) and image.startswith("http"):
            fields["image"] = image


def fill_from_soup(fields: Dict[str, str], soup: BeautifulSoup, node: Dict[str, Any], marketplace: str) -> None:
    """Copy the title, price, image, shop name and the metrics from the parsed page."""
    selectors = SELECTORS[marketplace]

    heading = soup.find("h1")
    if heading is not None:
        fields["title"] = soup_text(heading)
    if fields["title"] == "":
        fields["title"] = soup_meta(soup, "og:title")
    if fields["title"] == "" and node:
        fields["title"] = clean_whitespace(str(node.get("name", "")))

    for selector in selectors["price"]:
        for element in soup.select(selector):
            text = soup_text(element)
            if not text or len(text) > PRICE_TEXT_LIMIT:
                continue
            price = normalize_price(text)
            if price:
                fields["price"] = price
                break
        if fields["price"]:
            break
    if fields["price"] == "" and node:
        fields["price"] = normalize_price(json_ld_price(node))
    if fields["price"] == "":
        fields["price"] = find_generic_price(soup)

    fields["image"] = clean_image_url(soup_meta(soup, "og:image"))
    if fields["image"] == "":
        for selector in selectors["image"]:
            element = soup.select_one(selector)
            if element is None:
                continue
            source = element.get("src", "") or element.get("data-src", "")
            if isinstance(source, str) and source.startswith("http"):
                fields["image"] = clean_image_url(absolute_url(source))
                if fields["image"]:
                    break
    if fields["image"] == "" and node:
        fields["image"] = clean_image_url(json_ld_image(node.get("image")))

    shop_element = find_shop_element(soup, selectors)
    if shop_element is not None:
        fields["vendor"] = shop_name_of(shop_element)

    order_index: Dict[int, int] = {}
    for position, element in enumerate(soup.find_all(True)):
        order_index[id(element)] = position

    candidates = find_rating_candidates(soup)
    product_rating, store_rating = assign_ratings(candidates, shop_element, order_index)
    fields["product_rating"] = product_rating
    fields["store_rating"] = store_rating
    fields["sold_count"] = find_sold_count(soup)


def extract_fields(soup: BeautifulSoup, page: Page, marketplace: str) -> Dict[str, str]:
    """Best effort extraction of the product fields of one marketplace page."""
    fields: Dict[str, str] = {
        "title": "",
        "price": "",
        "image": "",
        "short_description": "",
        "description": "",
        "vendor": "",
        "product_rating": "",
        "sold_count": "",
        "store_rating": "",
    }

    node = soup_json_ld(soup)
    fill_from_soup(fields, soup, node, marketplace)
    fill_from_cache(fields, extract_page_cache(page))
    return fields


def read_page_text(page: Page) -> str:
    """Return the page title and the visible body text."""
    parts: List[str] = []
    try:
        parts.append(page.title())
    except PlaywrightError:
        pass
    try:
        parts.append(page.inner_text("body", timeout=5000))
    except PlaywrightError:
        pass
    return " ".join(parts)


def is_captcha(page_text: str, url: str) -> bool:
    """Return True when the page shows a captcha or a security check."""
    haystack = (page_text + " " + url).lower()
    for marker in CAPTCHA_MARKERS:
        if marker in haystack:
            return True
    return False


def is_login_wall(page_text: str, title: str) -> bool:
    """Return True when the page shows a login wall instead of the product."""
    haystack = page_text.lower()
    if "masuk diperlukan" in haystack:
        return True
    if title:
        return False
    for marker in LOGIN_MARKERS:
        if marker in haystack:
            return True
    return False


def is_region_block(page_text: str) -> bool:
    """Return True when the marketplace refuses to show the product for this region."""
    haystack = page_text.lower()
    for marker in REGION_MARKERS:
        if marker in haystack:
            return True
    return False


class Scraper:
    """Scrapes marketplace product pages with Playwright."""

    def __init__(self, playwright: Any, headless: bool = False) -> None:
        """Keep the Playwright instance and prepare the browser cache."""
        self._playwright = playwright
        self._headless = headless
        self._browsers: Dict[str, Any] = {}
        self._contexts: Dict[str, Any] = {}

    def close(self) -> None:
        """Close every browser and context that was opened."""
        for context in self._contexts.values():
            try:
                context.close()
            except PlaywrightError:
                pass
        self._contexts.clear()
        for browser in self._browsers.values():
            try:
                browser.close()
            except PlaywrightError:
                pass
        self._browsers.clear()

    def context_for(self, marketplace: str) -> Any:
        """Return the browser context of a marketplace, creating it once."""
        if marketplace in self._contexts:
            return self._contexts[marketplace]

        if marketplace in PROFILE_FOLDERS:
            context = self._playwright.chromium.launch_persistent_context(
                user_data_dir=str(profile_dir(PROFILE_FOLDERS[marketplace])),
                headless=self._headless,
                viewport={"width": 1440, "height": 900},
                locale="id-ID",
                args=CHROMIUM_ARGS,
            )
        else:
            browser = self._playwright.chromium.launch(headless=self._headless, args=CHROMIUM_ARGS)
            self._browsers[marketplace] = browser
            context = browser.new_context(
                viewport={"width": 1440, "height": 900},
                locale="id-ID",
            )

        self._contexts[marketplace] = context
        return context

    def scrape(self, url: str, affiliate_url: str) -> ScrapeResult:
        """Scrape one product URL and return the filled row."""
        result = ScrapeResult()
        result.affiliate_url = affiliate_url if affiliate_url else url

        initial = detect_marketplace(url)
        result.marketplace = initial

        if initial and initial not in SUPPORTED_MARKETPLACES:
            result.notes = "error:unsupported_marketplace"
            return result

        mode = initial
        if not mode:
            mode = "lazada"

        page = None
        try:
            context = self.context_for(mode)
            page = context.new_page()
            if mode in ("tokopedia", "shopee"):
                apply_stealth(page)
            self._scrape_page(page, url, result)
        except Exception as error:
            if not result.notes:
                result.notes = "error:" + short_reason(error)
        finally:
            if page is not None:
                try:
                    page.close()
                except PlaywrightError:
                    pass

        return result

    def _scrape_page(self, page: Page, url: str, result: ScrapeResult) -> None:
        """Open the URL, wait for the page and fill the result with the page data."""
        try:
            page.goto(url, wait_until="domcontentloaded", timeout=GOTO_TIMEOUT_MS)
        except PlaywrightTimeoutError:
            result.notes = "timeout"
        except PlaywrightError as error:
            result.notes = "error:" + short_reason(error)

        final_url = url
        try:
            final_url = page.url
        except PlaywrightError:
            pass

        marketplace = detect_marketplace(final_url)
        result.marketplace = marketplace

        if marketplace not in SELECTORS:
            if not result.notes:
                result.notes = "error:unsupported_marketplace"
            return

        selectors = SELECTORS[marketplace]
        page_text = read_page_text(page)

        if is_region_block(page_text):
            result.notes = "error:region_not_available"
        else:
            try:
                page.wait_for_selector(selectors["ready"], timeout=READY_TIMEOUT_MS)
            except PlaywrightTimeoutError:
                if not result.notes:
                    result.notes = "timeout"
            page_text = read_page_text(page)

        page.wait_for_timeout(SETTLE_TIMEOUT_MS)

        html = page.content()
        soup = BeautifulSoup(html, "lxml")

        fields = extract_fields(soup, page, marketplace)

        if result.notes in ("", "timeout"):
            if is_captcha(page_text, final_url):
                result.notes = "blocked_captcha"
            elif is_login_wall(page_text, fields["title"]):
                result.notes = "blocked_login"

        if result.notes == "timeout" and fields["title"]:
            result.notes = ""

        if not result.notes:
            if fields["title"] and fields["price"]:
                result.notes = "ok"
            else:
                result.notes = "partial"

        result.seller_title = fields["title"]
        result.price_idr = fields["price"]
        result.vendor_name = fields["vendor"]
        result.product_rating = fields["product_rating"]
        result.sold_count = fields["sold_count"]
        result.store_rating = fields["store_rating"]
        result.short_description = fields["short_description"]
        result.description = fields["description"]
        result.image_url = fields["image"]


def build_row(result: ScrapeResult) -> Dict[str, str]:
    """Return the full output row. The identity columns stay empty."""
    row: Dict[str, str] = {column: "" for column in OUTPUT_COLUMNS}
    row["marketplace"] = result.marketplace
    row["vendor_name"] = result.vendor_name
    row["price_idr"] = result.price_idr
    row["affiliate_url"] = result.affiliate_url
    row["seller_title"] = result.seller_title
    row["product_rating"] = result.product_rating
    row["sold_count"] = result.sold_count
    row["store_rating"] = result.store_rating
    row["short_description"] = result.short_description
    row["description"] = result.description
    row["image_url"] = result.image_url
    row["notes"] = result.notes
    return row


def clean_cell(value: Any) -> str:
    """Return a spreadsheet cell as a trimmed string."""
    if value is None:
        return ""
    try:
        if pd.isna(value):
            return ""
    except (TypeError, ValueError):
        pass
    return str(value).strip()


def load_targets(path: Path) -> List[Dict[str, str]]:
    """Read the target URLs from a CSV or XLSX file, keeping the input order."""
    if path.suffix.lower() in (".xlsx", ".xls"):
        frame = pd.read_excel(path, dtype=str)
    else:
        frame = pd.read_csv(path, dtype=str)

    frame.columns = [str(column).strip() for column in frame.columns]
    if "url" not in frame.columns:
        raise ValueError("the targets file must contain a 'url' column")

    targets: List[Dict[str, str]] = []
    for record in frame.to_dict("records"):
        url = clean_cell(record.get("url"))
        if not url:
            continue
        targets.append(
            {
                "url": url,
                "affiliate_url": clean_cell(record.get("affiliate_url")),
            }
        )
    return targets


def write_csv(rows: List[Dict[str, str]], path: Path) -> None:
    """Write the rows to a CSV file with every value quoted."""
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", encoding="utf-8", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=OUTPUT_COLUMNS, quoting=csv.QUOTE_ALL)
        writer.writeheader()
        for row in rows:
            writer.writerow(row)


def write_xlsx(rows: List[Dict[str, str]], path: Path) -> bool:
    """Write the rows to an XLSX file. Returns False when openpyxl could not write it."""
    try:
        from openpyxl import Workbook
        from openpyxl.styles import Font
        from openpyxl.utils import get_column_letter
    except ImportError:
        print("Warning: openpyxl is not installed, only the CSV file was written.")
        return False

    try:
        workbook = Workbook()
        sheet = workbook.active
        sheet.title = "products"
        sheet.append(OUTPUT_COLUMNS)
        for cell in sheet[1]:
            cell.font = Font(bold=True)
        for row in rows:
            sheet.append([row[column] for column in OUTPUT_COLUMNS])
        sheet.freeze_panes = "A2"
        for index, column in enumerate(OUTPUT_COLUMNS, start=1):
            width = XLSX_COLUMN_WIDTHS.get(column, 18)
            sheet.column_dimensions[get_column_letter(index)].width = width
        path.parent.mkdir(parents=True, exist_ok=True)
        workbook.save(path)
    except Exception as error:
        print("Warning: could not write {0}: {1}. Only the CSV file was written.".format(path, error))
        return False
    return True


def dated_output_prefix(prefix: str) -> str:
    """Return the output prefix with the date of today added, e.g. 'products_2026-11-13'."""
    return "{0}_{1}".format(prefix, date.today().strftime("%Y-%m-%d"))


def run_scrape(targets: List[Dict[str, str]]) -> List[Dict[str, str]]:
    """Scrape every target and return the output rows in the input order."""
    rows: List[Dict[str, str]] = []
    total = len(targets)
    with sync_playwright() as playwright:
        scraper = Scraper(playwright)
        try:
            for index, target in enumerate(targets, start=1):
                url = target["url"]
                result = scraper.scrape(url, target["affiliate_url"])
                rows.append(build_row(result))
                print("{0}/{1} {2} {3} {4}".format(index, total, result.marketplace, result.notes, url))
                if index < total:
                    time.sleep(random.uniform(SLEEP_MIN_SECONDS, SLEEP_MAX_SECONDS))
        finally:
            scraper.close()
    return rows


def run_login(site: str) -> int:
    """Open a visible browser so the user can log in, then keep the profile."""
    home = LOGIN_URLS[site]
    folder = profile_dir(PROFILE_FOLDERS[site])
    folder.mkdir(parents=True, exist_ok=True)

    print("Opening {0} in a visible browser window.".format(home))
    print("Log in inside that window, then come back here and press Enter to save the profile.")

    with sync_playwright() as playwright:
        context = playwright.chromium.launch_persistent_context(
            user_data_dir=str(folder),
            headless=False,
            viewport={"width": 1440, "height": 900},
            locale="id-ID",
            args=CHROMIUM_ARGS,
        )
        page = context.pages[0] if context.pages else context.new_page()
        try:
            page.goto(home, wait_until="domcontentloaded", timeout=GOTO_TIMEOUT_MS)
        except PlaywrightError as error:
            print("Warning: {0}".format(short_reason(error)), file=sys.stderr)
        try:
            input("Press Enter here after you finished logging in... ")
        except (EOFError, KeyboardInterrupt):
            pass
        context.close()

    print("Profile saved in {0}".format(folder))
    return 0


def parse_args(argv: Optional[List[str]] = None) -> argparse.Namespace:
    """Parse the command line arguments."""
    parser = argparse.ArgumentParser(
        description="Scrape marketplace product pages into the GudangPC import CSV."
    )
    parser.add_argument(
        "--targets",
        help="Path of the targets file (.csv or .xlsx). It must have a 'url' column.",
    )
    parser.add_argument(
        "--out",
        default="products",
        help="Output prefix. The date of today is added to it and the files are written in the "
        "folder where you run the command (default: products).",
    )
    parser.add_argument(
        "--login",
        choices=sorted(LOGIN_URLS),
        help="Open a visible browser to log in and save the profile, then exit.",
    )
    args = parser.parse_args(argv)
    args.out = dated_output_prefix(args.out)
    return args


def main(argv: Optional[List[str]] = None) -> int:
    """Run the scraper and return the exit code of the process."""
    args = parse_args(argv)

    if args.login:
        return run_login(args.login)

    if not args.targets:
        print("Error: --targets is required.", file=sys.stderr)
        return 1

    targets_path = Path(args.targets)
    if not targets_path.is_file():
        print("Error: targets file not found: {0}".format(targets_path), file=sys.stderr)
        return 1

    try:
        targets = load_targets(targets_path)
    except Exception as error:
        print("Error: could not read {0}: {1}".format(targets_path, error), file=sys.stderr)
        return 1

    if not targets:
        print("Error: the targets file has no rows.", file=sys.stderr)
        return 1

    rows = run_scrape(targets)

    prefix = Path(args.out)
    if not prefix.is_absolute():
        prefix = Path.cwd() / prefix

    csv_path = Path(str(prefix) + ".csv")
    xlsx_path = Path(str(prefix) + ".xlsx")

    write_csv(rows, csv_path)
    write_xlsx(rows, xlsx_path)

    print("Wrote {0} and {1}".format(csv_path, xlsx_path))
    return 0


if __name__ == "__main__":
    sys.exit(main())
