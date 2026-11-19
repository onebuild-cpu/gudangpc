# GudangPC Worklog — Task Pack v2

MIGRATIONS APPLIED: 019, 020, 021, 022 (verified 2026-11-17)

---

## Phase 0 — Specification

### T0.1 — Rewrite `docs/PRODUCT_SPEC.md` to match the real project

STATUS: DONE (spec rewritten to match current schema, roles, URLs)

---

## Phase A — Bugs, security, offer rules

### A1 — Fix member registration

STATUS: DONE (whatsapp sanitize + rate limit identifier fixed)

### A2 — Stop raw description output

STATUS: DONE

FILES CHANGED:
- `views/public/products/show.php` — `$descriptionHtml = RichText::clean($descriptionValue)` in the top PHP block; only unescaped output on this page.
- `app/Core/ProductImporter.php` — both `$productData['description']` assignments wrapped in `RichText::clean(...)`.

### A3 — One definition of "active offer" + freshness

STATUS: DONE

FILES CREATED:
- `database/migrations/019_offer_status_and_freshness.sql`

FILES CHANGED:
- `app/Models/OfferModel.php` — added `activeCondition()`, rewrote `findPublicByProductId()`, added `findActiveById()`, `countActive()`, `countStale()`.
- `app/Controllers/AdminProductController.php` — `storeOffer()` inserts `status` = `published` and `last_checked_at` = now.
- `views/dashboard/products/edit.php` — offers table has Status and Last checked columns.
- `app/Core/ProductImporter.php` — identical offers refresh `last_checked_at`; `updateOffer()` sets `in_stock = 1`.

### A4 — Edit offers in the dashboard + protect click history

STATUS: DONE

FILES CHANGED:
- `public/index.php` — added route `dashboard/products/(\d+)/offer/(\d+)/update`.
- `app/Controllers/AdminProductController.php` — added `updateOffer()`; `deleteOffer()` refuses when `countByOfferId() > 0`.
- `app/Models/ClickModel.php` — added `countByOfferId()`.
- `views/dashboard/products/edit.php` — per-offer Edit modal; delete confirm modal kept.

### A5 — Harden `/go/<offer_id>`

STATUS: DONE

FILES CHANGED:
- `app/Controllers/PublicProductController.php` — `go()` uses `findActiveById()`; flash "That offer is no longer available." + redirect to product page when inactive; added `isBot()` (case-insensitive bot match); sends `X-Robots-Tag: noindex, nofollow` and `Cache-Control: no-store`; 302 redirect preserved; IP hash preserved.

### A6 — Fix `robots.txt` and add `sitemap.xml`

STATUS: DONE

FILES CREATED:
- `app/Controllers/SitemapController.php`

FILES CHANGED:
- `public/robots.txt` — disallow list updated (`/dashboard`, `/login`, `/logout`, `/register`, `/member`, `/go/`, `/search`, `/wishlist/`); `Sitemap:` placeholder; no `Security:` line.
- `public/index.php` — added route `sitemap\.xml`.
- `app/Models/ProductModel.php` — added `findAllPublishedWithActiveOffers()` (single query with `OfferModel::activeCondition()`).

NOTICED:
- `robots.txt` keeps `Sitemap: https://REPLACE-ME/sitemap.xml` until the PO edits the domain by hand.

---

## Phase B — SEO output

### B1 — SEO helper and layout output

STATUS: DONE

FILES CREATED:
- `app/Core/Seo.php` (with `absoluteUrl`, `plainText`, `cleanJsonLd`, `breadcrumbJsonLd`, `productJsonLd`)

FILES CHANGED:
- `views/layouts/header.php` — defaults for `$seo`, `$canonicalUrl`, `$ogImage`, `$ogType`, `$robotsOverride`, `$jsonLd`; prints robots meta, canonical link, OG, Twitter, `$jsonLd`, cleaned `schema_markup`, `custom_head_tags`, `custom_body`.
- `views/layouts/footer.php` — prints `custom_footer` before `</body>`.

### B2 — Wire SEO into home, category, brand, product pages

STATUS: DONE

FILES CHANGED:
- `app/Controllers/HomeController.php` — passes `seo` (null), `canonicalUrl` (`Seo::absoluteUrl('/')`).
- `app/Controllers/PublicCategoryController.php` — loads SEO, applies title/description fallbacks, canonical `/<slug>`, `ogImage` from category image.
- `app/Controllers/PublicBrandController.php` — same pattern; canonical `/brand/<slug>`.
- `app/Controllers/PublicProductController.php` — title fallback `<name> - Price Comparison | GudangPC`, description fallback, `ogType` = `product`.

### B3 — Canonical clean-up and product URL redirect

STATUS: DONE

FILES CREATED:
- `database/migrations/020_clean_seo_canonical.sql`

FILES CHANGED:
- Four admin controllers — `collectSeoInput()` no longer auto-fills `canonical_url`; stores what user typed.
- Three public controllers — computed canonical wins unless `seo.canonical_url` starts with `http`.
- `PublicProductController` — 301 redirect when URL's category slug is stale.
- All create/edit SEO views — canonical hint text updated.

### B4 — Breadcrumbs and structured data

STATUS: DONE

FILES CREATED:
- `views/public/_breadcrumb.php`

FILES CHANGED:
- `app/Core/Seo.php` — added `breadcrumbJsonLd()`, `productJsonLd()` (AggregateOffer only; no `aggregateRating` / `review`).
- Three public controllers — pass `breadcrumbs` and `jsonLd`.
- Three product/category/brand `show.php` views — include `_breadcrumb.php` at top.
- `views/layouts/header.php` — custom `seo.schema_markup` suppresses generated Product JSON-LD.

---

## Phase C — Browse experience

### C1 — Navbar clean-up

STATUS: DONE — `views/layouts/navbar.php` Products link now live (`/categories`).

### C2 — Remove the compare feature

STATUS: DONE

FILES DELETED:
- `app/Controllers/PublicCompareController.php`
- `views/public/compare/show.php`

FILES CHANGED:
- `public/index.php` — removed compare route.
- `views/home/index.php`, `views/public/{categories,brands}/show.php`, `views/public/search/index.php` — compare checkbox, `#compare-float`, script block removed.

NOTICED: "Compare Prices" button labels remain (they link to the product page, allowed by task).

### C3 — Product card partial with prices

STATUS: DONE

FILES CREATED:
- `views/public/_product_card.php`

FILES CHANGED:
- `app/Models/ProductModel.php` — added `lowest_price` and `offer_count` subqueries (using `OfferModel::activeCondition()`).
- Four views — cards replaced by `include` of the partial.
- `public/assets/css/frontend.css` — added `.product-card-dim { opacity: .6; }`.

### C4 — Pagination and sorting

STATUS: DONE

FILES CREATED:
- `views/public/_pagination.php`

FILES CHANGED:
- `app/Models/ProductModel.php` — added `$limit`/`$offset`/`$sort` params; added `countByCategorySlug`, `countByBrandSlug`, `countSearch`; added `sortClause()` and `queryPaginated()`.
- Three public controllers — read `page`/`sort` from `$_GET`, compute `totalPages`, 404 when `page > totalPages && page > 1`.
- Three views — sort select + pagination include.

### C5 — Better search

STATUS: DONE

FILES CHANGED:
- `app/Models/ProductModel.php` — `search()` and `countSearch()` now split query on whitespace (max 6 tokens), escape `\`, `%`, `_`, AND groups; added `searchPatterns()`.
- `app/Controllers/PublicSearchController.php` — passes `robotsOverride` = `noindex, follow`, `canonicalUrl` = `''`.
- `views/public/search/index.php` — "Search results for:" heading + count; empty query shows categories link.

### C6 — Homepage: latest products per category

STATUS: DONE (Part 1)

FILES CHANGED:
- `app/Controllers/HomeController.php` — removed `getPublishedWithRelations()`; `$categorySections` from `findLatestActiveByCategoryId($id, 8)`; `$brands` from `findPublishedWithProducts(12)`.
- `app/Models/ProductModel.php` — added `findLatestActiveByCategoryId()`.
- `app/Models/BrandModel.php` — added `findPublishedWithProducts()`.
- `views/home/index.php` — hero images use `$base`; per-category "Latest in ..." blocks; brands strip.

NOTICED: Part 2 ("Most clicked this week") deferred per task.

### C7 — Product page polish

STATUS: DONE

FILES CHANGED:
- `app/Controllers/PublicProductController.php` — flat offers list, `inWishlist` (visitor only), `related` (4).
- `app/Models/ProductModel.php` — added `findRelated()`.
- `views/public/products/show.php` — reworked: flat offers table with lowest-price badge, "Show all N offers" collapse, relative time, wishlist form, disclosure line.
- `public/assets/css/frontend.css` — added `.offer-title`.

---

## Phase D — Import engine

### D1 — Migration for listing identity and affiliate templates

STATUS: DONE (applied)

FILES CREATED:
- `database/migrations/021_offer_listing_identity_and_affiliate_template.sql`

NOTICED: `offers_store_external` unique key relies on repeated NULL `external_id` values being allowed by MySQL/MariaDB.

### D2 — Affiliate template field on marketplaces

STATUS: DONE

FILES CHANGED:
- `app/Controllers/AdminStoreController.php` — `collectInput()` reads/validates `affiliate_url_template`; added to empty/old/form data.
- `views/dashboard/stores/{create,edit}.php` — template text input with helper text.

### D3 — Importer hardening

STATUS: DONE

FILES CHANGED:
- `app/Core/ProductImporter.php` — real runs wrapped in transaction; optional `source_url`/`external_id`; seller auto-create from `vendor_name`; affiliate template resolution; `(store_id, external_id)` matching first; `notes` rejection; price-change warning; report includes `created_vendors` and `warnings`.
- `app/Controllers/AdminProductImportController.php` — success flash includes sellers created.
- `views/dashboard/products/import.php` — "Sellers created" counter and Warnings card.

NOTICED: `bin/import_products.php` still has stale comment about vendors never being created.

### D4 — Price history

STATUS: DONE (applied)

FILES CREATED:
- `database/migrations/022_create_price_history.sql`
- `app/Models/PriceHistoryModel.php`

FILES CHANGED:
- `app/Core/ProductImporter.php` — records price history on create and price change (not on identical re-import, not in dry-run).
- `app/Controllers/AdminProductController.php` — `storeOffer()` and `updateOffer()` record history on new/changed prices.

NOTICED: No price-history UI yet (per spec).

---

## Phase E — Visibility

### E1 — Dashboard overview and click analytics

STATUS: PENDING (not started)

### E2 — Dashboard product list: search, filters, pagination

STATUS: PENDING (not started)

---

## Audit fixes (2026-11-17)

### GP-036 — `Validator::sanitize` UTF-8 fix

STATUS: PENDING — see ticket GP-036

### GP-038 — translate public views to English

STATUS: PENDING — see ticket GP-038

### GP-019 — `security.php` env detection

STATUS: PENDING — see ticket GP-019

### GP-026 — `PRODUCT_SPEC.md` search_cache docs

STATUS: PENDING — see ticket GP-026

### GP-027 — Gemini API key in header

STATUS: PENDING — see ticket GP-027

### GP-028 — AI search rate limit

STATUS: PENDING — see ticket GP-028

### GP-037 — `Validator::sanitize` docblock + callsite audit

STATUS: PENDING — see ticket GP-037

### GP-041 — verify GP-015 form guards

STATUS: PENDING — see ticket GP-041

---

## Vendor Location feature (done)

Done through tasks 4.1 to 4.5. Applied migrations: `023_locations.sql` (the `locations` table) and `024_vendor_location_description.sql` (`vendors.location_id` FK to `locations.id` ON DELETE RESTRICT and `vendors.description` varchar(1000) NULL). The old plan listed 023 as a `vendor_stores` join table and 024 as locations; the `vendor_stores` join table was NOT built and remains parked.

Feature spec: `docs/PRODUCT_SPEC.md` §2 (Location glossary), §6 D5 (seller pages), §7 (Deleting content and Language), §8 (locations and vendors data model, schema history), §9 (seller route/page, homepage location sections, product page seller link) and §10 Parked item 6.

Tickets GL-001a through GL-011 — see product owner for prompts. The work was done through the 4.x tasks instead.