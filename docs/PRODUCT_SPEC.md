# GudangPC: Product Specification

Owner: the PO. This document is the source of truth for **what** is being built.
`AGENTS.md` says **how** to code. If the two conflict, stop and ask.
The worker must not create any table, route, or feature that is not listed for the current task.

---

## 1. What this product is

GudangPC is an Indonesian price-comparison and affiliate website for PC parts and tech gear (CPU, GPU, motherboard, RAM, SSD, PSU, case, cooler, monitor, peripherals). The UI language is English (decision D8).

A visitor finds a product, sees the **same product's prices across several marketplaces** (Tokopedia, Shopee, Lazada, and any other row in `stores`), and clicks through to buy. Every outbound click goes through `/go/<offer_id>`, and the owner earns affiliate commission.

Catalog content is managed by staff in the dashboard. Prices arrive in two ways:

- staff enter and edit offers by hand in the dashboard;
- a **local Python scraper** (`bin/scraper/scrape.py`) writes a CSV file, which staff upload at `/dashboard/products/import`.

There is **no marketplace API integration yet** and no server-side scraping. The scraper runs on a local machine, not on the web server.

### Success looks like

- A visitor lands on a product page from Google and sees a clear price table with a Buy button per seller.
- Staff can add a product with 3 offers in under 3 minutes from the dashboard.
- The owner can see which products and marketplaces get the most clicks.

### Not what this is

Not a shop: no cart, checkout, payments, stock management, or first-party reviews. No email is sent by the application. There are no public marketplace or seller pages (decision D5).

---

## 2. Vocabulary

| Term | Meaning |
|---|---|
| **Marketplace** | A row in `stores` (Tokopedia, Shopee, Lazada, ...). The word "store" in table and class names means marketplace. |
| **Seller** | A row in `vendors`. Every seller belongs to exactly one marketplace (`vendors.store_id`). |
| **Offer** (listing) | One seller's price for one product on one marketplace. One row in `offers`. |
| **Member** | A public user account, stored in `members`. Not a staff account. |

The physical table and class names (`stores`, `StoreModel`, `vendors`, `VendorModel`) stay as they are. Only the documentation vocabulary changes.

---

## 3. People and roles

| Role | Stored in | Signs in with | Can do |
|---|---|---|---|
| `root` | `users.role = 'root'` | Phone number | Everything. Create and manage admins and members. |
| `admin` | `users.role = 'admin'` | Phone number | Manage the catalog (categories, brands, marketplaces, sellers, products, offers, CSV import). Cannot touch `root` or `admin` accounts. |
| `member` | `members`, session role value `visitor` | Email | Browse the public site, open the Member Area at `/member`, edit own profile, use the wishlist. Cannot open the dashboard. |
| anonymous | none | - | Browse, search, click Buy. No account needed. |

### Registration and activation

- Members register themselves at `/register` with **email**, a password and a **WhatsApp number**. A new row is created with status `pending`.
- A `root` administrator activates the member in Dashboard -> Members (the Members module is restricted to `root` today). Only `active` members can sign in; `banned` members cannot.
- Staff accounts are not self-registered. The first `root` is created with `bin/add_root.php`; `admin` accounts are created from the dashboard by the `root`.
- `/login` accepts either an email address (member) or an E.164 phone number such as `+628123456789` (staff).
- Google sign-ups are auto-activated, email sign-ups stay pending.

Wishlist URLs are public routes, but they only work for a signed-in member (session role `visitor`).

---

## 4. Status vocabulary

| Table | Column | Values |
|---|---|---|
| `categories`, `brands`, `stores`, `products`, `offers` | `status` | `draft`, `published` |
| `vendors` | `status` | `active` (deliberate exception; default `active`) |
| `members` | `status` | `pending`, `active`, `banned` |
| `seo_meta` | `robots_tag` | free text, default `index, follow` |

Public pages show only `published` rows (and only `active` sellers). Members must be `active` to sign in.

Non-members are not stored anywhere; a visitor is anonymous until they register.

---

## 5. Active offer (decision D3)

This is the **single definition** of a trustworthy, publicly visible offer. It lives in one place in `OfferModel` and is used by every page, query and redirect.

An offer is *active* when **all** of the following are true:

1. `offers.status = 'published'`
2. `offers.in_stock = 1`
3. its marketplace (`stores`) has `status = 'published'`
4. its seller is missing, or its seller (`vendors`) has `status = 'active'`
5. `offers.last_checked_at` is NULL, or it is within the last 14 days

Public price tables, price badges, the homepage, the sitemap and structured data use only active offers. An offer whose data is older than 14 days is *stale* and must not be shown publicly.

---

## 6. Decisions (D1, D4 to D10)

| # | Decision |
|---|---|
| D1 | **The compare feature is removed**: its route, UI, controller and view. Parked until per-category specs exist. |
| D4 | A product with **no active offer** is shown dimmed on category, brand and search pages, hidden on the homepage, left out of `sitemap.xml`, and has no price in structured data. |
| D5 | **No public pages for marketplaces or sellers** for now. Their SEO fields stay in the dashboard but nothing renders them publicly. |
| D6 | The unique rule `(product_id, vendor_id)` on `offers` stays. A second listing from the same seller for the same product is **reported**, not merged. |
| D7 | Affiliate links are built from a per-marketplace **template** (`stores.affiliate_url_template`) applied to the raw product URL. If no template exists, the raw URL is used and the import report warns. |
| D8 | UI language stays **English**. Indonesian UI is parked. |
| D9 | The canonical URL is **computed at render time** from `APP_URL`. A stored `seo_meta.canonical_url` is honoured only if it is absolute (starts with `http`). |
| D10 | Google sign-ins verify and clear OAuth state, verify TLS with a 10-second timeout, require a verified email, create new members as `active`, and activate a pending email member with a random password. |

D2 is not a change: the wishlist stays, and members still register as `pending` until a `root` administrator activates them.

---

## 7. Global rules for all modules

- **Money:** Rupiah stored as an unsigned integer in `price_idr`. No decimals, no floats. Display with `number_format($price, 0, ',', '.')` and the prefix `Rp `.
- **Slugs:** lowercase `a-z`, `0-9` and `-`; unique per table; created from the name; not changed automatically on rename.
- **Charset:** `utf8mb4`, collation `utf8mb4_unicode_ci`, engine InnoDB, except the legacy `users` table which is `utf8mb4_general_ci` in `initial_database.sql`.
- **Timestamps:** every table has `created_at` and `updated_at`, except `offer_clicks` (only `clicked_at`), `wishlists` and `search_cache` (only `created_at`), and the `price_history` (only `recorded_at`).
- **Affiliate links:** public pages never print an affiliate URL directly. They link to `/go/<offer_id>`. Outbound links use `target="_blank"` and `rel="sponsored nofollow noopener"`. Pages with offers show the disclosure line: "We may earn a commission from purchases made through our links." Manual entry accepts only `http://` and `https://` URLs, the same as CSV import. Offers have status `draft` or `published`, editable in the offer edit form.
- **Offer visibility:** only active offers (section 5) are shown publicly.
- **Output safety:** all dynamic output is escaped. Rich descriptions are printed only after `\App\Core\RichText::clean()`.
- **Deleting content:** a category cannot be deleted while products reference it. A marketplace cannot be deleted while offers or sellers reference it. A brand is not blocked; products pointing at it get `brand_id = NULL`. Products and offers are hard-deleted from the dashboard after a confirm. An offer that already has click history must not be deleted; set it to Draft or Out of stock instead (task A4).
- **Pagination:** public lists paginate at 20 per page with `?page=N` (planned in C4). The admin product list paginates at 20 per page.

---

## 8. Data model (as built today)

Existing tables and columns, as created by `database/initial_database.sql` and migrations `002` to `018`.

### `users` (staff: root and admin)

```
id, phone, username, password, role, avatar, is_verified, last_login_at,
created_at, updated_at
PRIMARY KEY (id), UNIQUE (username)
```

`role` holds `root` or `admin`. The column default is `buyer` in the SQL file; the application only creates `root` and `admin`.

### `rate_limits`

```
id, bucket, action, attempt_count, window_start, created_at, updated_at
PRIMARY KEY (id), UNIQUE (bucket, action), KEY (window_start)
```

### `categories`

```
id, parent_id, name, slug, description, image, image_alt, sort_order,
status, created_at, updated_at
PRIMARY KEY (id), UNIQUE (slug)
```

`parent_id` exists but is not used by the public site yet.

### `brands`

```
id, name, slug, logo, website_url, description, status,
created_by, updated_by, created_at, updated_at
PRIMARY KEY (id), UNIQUE (slug)
```

### `stores` (marketplaces)

```
id, name, slug, description, logo, base_url, status,
created_by, updated_by, created_at, updated_at
PRIMARY KEY (id), UNIQUE (slug)
```

### `vendors` (sellers)

```
id, store_id, name, slug, rating, store_rating, status,
created_at, updated_at
PRIMARY KEY (id), UNIQUE (slug), KEY (store_id)
FOREIGN KEY (store_id) REFERENCES stores (id)
```

`status` default is `active`. `rating` and `store_rating` are decimal ratings carried from marketplace data; the importer updates `store_rating` from the scrape.

### `products`

```
id, category_id, brand_id, name, slug, model_number, short_description,
description, image, image_alt, search_tags, status,
created_by, updated_by, created_at, updated_at
PRIMARY KEY (id), UNIQUE (slug), KEY (category_id), KEY (brand_id)
FOREIGN KEY (category_id) REFERENCES categories (id)
FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE SET NULL
```

`short_description` is plain text: formatting tags are stripped on save and the limit is 500 characters of text. `description` is rich text, keeps formatting, and is printed only after `\App\Core\RichText::clean()`.

### `offers`

```
id, product_id, store_id, vendor_id, price_idr, affiliate_url,
seller_title, seller_name, seller_rating, product_rating, sold_count,
in_stock, last_checked_at, status, created_at, updated_at
PRIMARY KEY (id), UNIQUE (product_id, vendor_id), KEY (store_id)
FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
FOREIGN KEY (store_id) REFERENCES stores (id)
FOREIGN KEY (vendor_id) REFERENCES vendors (id) ON DELETE SET NULL
```

`seller_name` and `seller_rating` are legacy per-offer seller fields kept alongside `vendor_id`. `seller_title`, `product_rating` and `sold_count` are scraped listing data. `offer_clicks` cascades from `offers`.

### `offer_clicks`

```
id, offer_id, clicked_at, ip_hash, user_agent, referer
PRIMARY KEY (id), KEY (offer_id)
FOREIGN KEY (offer_id) REFERENCES offers (id) ON DELETE CASCADE
```

`ip_hash` = `hash('sha256', $ip . <APP_SALT from .env>)`. Raw IPs are never stored. No `updated_at`.

### `seo_meta`

```
id, meta_type, meta_id, seo_title, seo_description, canonical_url, robots_tag,
og_title, og_description, og_image, twitter_title, twitter_description,
schema_markup, custom_head_tags, custom_body, custom_footer,
created_at, updated_at
PRIMARY KEY (id), UNIQUE (meta_type, meta_id)
```

`meta_type` is the module name (`product`, `category`, `brand`, `store`). `custom_body` and `custom_footer` are admin-authored HTML.

### `settings`

```
id, setting_key, setting_value, created_at, updated_at
PRIMARY KEY (id), UNIQUE (setting_key)
```

### `members`

```
id, email, username, password, status, verification_token, avatar, whatsapp,
created_at, updated_at
PRIMARY KEY (id), UNIQUE (email), UNIQUE (username)
```

`status` default is `pending`. `whatsapp` is collected at registration.

### `wishlists`

```
id, member_id, product_id, created_at
PRIMARY KEY (id), UNIQUE (member_id, product_id), KEY (member_id), KEY (product_id)
FOREIGN KEY (member_id) REFERENCES members (id) ON DELETE CASCADE
FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE

No `updated_at`.

### `search_cache`

id, query_text, query_slug, ai_response_json (longtext, JSON-valid),
result_count, is_indexed, created_at, updated_at
PRIMARY KEY (id), UNIQUE (query_text), UNIQUE (query_slug)

Written by SearchCacheModel when AiSearchService parses a query.
AdminSearchController reads it and toggles is_indexed for sitemap inclusion.

### Schema history (migration 021 applied)

**Migration 021** is applied. Listing identity and affiliate templates exist in the current dump:

- `offers.source_url` `text` NULL: the scraped product page URL;
- `offers.external_id` `varchar(100)` NULL: the marketplace listing id;
- unique key `(store_id, external_id)` on `offers` (NULL `external_id` rows may repeat);
- `stores.affiliate_url_template` `varchar(500)` NULL.

`dump_sql_gudangpc.sql` is currently the authoritative full schema; `initial_database.sql` does not contain the catalog tables.

**Migration 022** is applied. The `price_history` table, its index `idx_price_history_offer_recorded` and its foreign key to `offers` (`ON DELETE CASCADE`) exist in the current dump:

- `price_history`: `id`, `offer_id` (`ON DELETE CASCADE`), `price_idr bigint unsigned`, `recorded_at datetime`;
- index on `(offer_id, recorded_at)`; no `created_at` / `updated_at`.

### Not in the database

`spec_definitions`, `product_specs` and any `home` table do not exist. Specs and compare are parked.

ai_response_json is validated by a CHECK (json_valid(...)) constraint.
Any code that writes to search_cache must produce valid JSON.

---

## 9. URL map

### Public (no login)

| URL | Serves |
|---|---|
| `/` | Homepage |
| `/categories` | Category list |
| `/brands` | Brand list |
| `/brand/<slug>` | Brand page |
| `/<category-slug>` | Category page |
| `/<category-slug>/<product-slug>` | Product detail page |
| `/search?q=` | Search results |
| `/go/<offer_id>` | Logs the click, then 302 redirects to the offer's affiliate URL |
| `/register` | Member registration form (GET) and `/register/submit` (POST) |
| `/login` | Sign-in form (GET) and `/login/submit` (POST); `/logout` (POST) |
| `/member` | Member Area |
| `/member/profile` | Own member profile |
| `/wishlist/add/<id>` | Add a product to the wishlist (member only) |
| `/wishlist/remove/<id>` | Remove a product from the wishlist (member only) |
| `/sitemap.xml` | XML sitemap |

### Dashboard (login required; admin or root)

| URL | Module |
|---|---|
| `/dashboard` | Overview |
| `/dashboard/profile` | Own staff profile |
| `/dashboard/admins[...]` | Staff accounts (root only) |
| `/dashboard/members` | Members; `/activate` and `/ban` actions (root only) |
| `/dashboard/categories[...]` | Categories |
| `/dashboard/brands[...]` | Brands |
| `/dashboard/stores[...]` | Marketplaces |
| `/dashboard/vendors[...]` | Sellers |
| `/dashboard/products[...]` | Products; offers can be added, edited and deleted inside the product edit page; the product create page accepts an optional first offer |
| `/dashboard/products/import` | CSV import form; `/store` handles the upload |
| `/dashboard/settings` | Settings |
| `/dashboard/clicks` | Click analytics |

`[...]` means the standard CRUD pattern from `AGENTS.md`: list, `create`, `store`, `(<id>)/edit`, `(<id>)/update`, `(<id>)/delete`.

Staff can enter the first offer while creating a product via the optional "First offer" block (vendor, price, affiliate URL, in stock). If the block is empty no offer is created. If the product is created but the offer fails, the product stays and staff land on the edit page with an error.

---

## 10. Roadmap

The PO splits each phase into small tasks. The worker only ever sees the current task.

| Phase | Tasks | Purpose |
|---|---|---|
| 0 | T0.1 | Make this spec match reality |
| A | A1 to A6 | Bugs and security: member registration, description output, one active-offer definition with freshness, offer editing with protected click history, hardened `/go/`, `robots.txt` and `sitemap.xml` |
| B | B1 to B4 | SEO output: helper and layout tags, wiring into pages, canonical clean-up and product URL redirect, breadcrumbs and structured data |
| C | C1 to C7 | Browse experience: navbar clean-up, remove compare, product cards with prices, pagination and sorting, better search, homepage sections, product page polish |
| D | D1 to D5 | Import engine: migrations for listing identity and affiliate templates, marketplace template field, importer hardening, price history, scraper update |
| E | E1 and E2 | Dashboard: overview counters and click analytics, product list filters and pagination |

### Parked (do not build until the PO writes a task)

1. **Import review queue:** unmatched scraped rows go to a staging table; staff match each row to a product with a search box; the listing (`store_id` + `external_id`) is remembered so the next scrape matches automatically.
2. **Image mirroring:** download and convert the scraper's `image_url` (signed marketplace URLs expire); decide whether seller images are acceptable.
3. **Specs and compare:** `spec_definitions` per category, specs on products, then compare within one category only.
4. **Price chart and price-drop alerts** for members (needs price history and a way to reach members).
5. **Indonesian UI** and Indonesian meta titles (`<html lang="id">`).
6. **Public marketplace and seller pages.**
7. **Member onboarding:** decide between admin activation (today), automatic activation, or WhatsApp verification.
8. **A migration runner** (`bin/migrate.php` plus a `migrations` table); today migrations are run by hand and migration 012 depends on constraint names.
9. **Caching** of the homepage queries.

---

## 11. Phase gate

A phase is **done** only when every task report is accepted by the PO, `php bin/check_rules.php` passes on the whole project, and the PO has clicked through every manual test step.