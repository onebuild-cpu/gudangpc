# GudangPC Worklog — Task Pack v2

MIGRATIONS PENDING: database/migrations/019_offer_status_and_freshness.sql, database/migrations/020_clean_seo_canonical.sql, database/migrations/021_offer_listing_identity_and_affiliate_template.sql, database/migrations/022_create_price_history.sql

---

## A2

TASK: A2 — Stop raw description output (stored XSS risk)

FILES CREATED: none

FILES CHANGED:
- `views/public/products/show.php` — computes `$descriptionHtml = \App\Core\RichText::clean($descriptionValue);` in the top PHP block, prints that value, kept the "No description available." fallback, added the one-line cleaning comment.
- `app/Core/ProductImporter.php` — both `$productData['description']` assignments now wrap the CSV value in `RichText::clean(...)`.

CHECKS:
```
php -l app\Core\ProductImporter.php
No syntax errors detected in app\Core\ProductImporter.php
php -l views\public\products\show.php
No syntax errors detected in views\public\products\show.php
php bin/check_rules.php app\Core\ProductImporter.php views\public\products\show.php
OK: no ternary or null-coalescing syntax found.
```
`RichText::clean()` smoke test:
```
<p>Hi</p>alert(1)<a rel="nofollow noopener">x</a>
```
normal description still returns `<p>Hello <strong>world</strong></p><a href="https://example.com" rel="nofollow noopener">link</a>`.

MANUAL TEST STEPS:
1. In phpMyAdmin set a product description to `<p>Hi</p><script>alert(1)</script><a href="javascript:alert(2)">x</a>`.
2. Open the product page: no alert, no `<script>` tag in page source, the link has no `href`.
3. A product with a normal description still renders paragraphs.

DEVIATIONS: none

NOTICED:
- Only the rendered output and future imports are cleaned. Existing rows keep their raw HTML in the database; the view now cleans it on render.
- `short_description` is stored raw by the importer but is escaped by the views, so it is not an XSS vector.

QUESTIONS: none

---

## A3

TASK: A3 � One definition of "active offer" + freshness

FILES CREATED:
- `database/migrations/019_offer_status_and_freshness.sql`

FILES CHANGED:
- `app/Models/OfferModel.php` � added `activeCondition()`, rewrote `findPublicByProductId()` to use it and select `v.store_rating AS vendor_store_rating`, added `findActiveById()`, `countActive()`, `countStale()`.
- `app/Controllers/AdminProductController.php` � `storeOffer()` inserts `status` = `published` and `last_checked_at` = now.
- `views/dashboard/products/edit.php` � offers table now has Status and Last checked columns (datetime or `-`).
- `app/Core/ProductImporter.php` � skipped identical offers refresh `last_checked_at` and set `in_stock = 1` via new private `touchOffer()` (not in dry-run); `updateOffer()` also sets `in_stock = 1`.

CHECKS:
```
php -l app/Models/OfferModel.php
No syntax errors detected in app/Models/OfferModel.php
php -l app/Controllers/AdminProductController.php
No syntax errors detected in app/Controllers/AdminProductController.php
php -l views/dashboard/products/edit.php
No syntax errors detected in views/dashboard/products/edit.php
php -l app/Core/ProductImporter.php
No syntax errors detected in app/Core/ProductImporter.php
php bin/check_rules.php app/Models/OfferModel.php app/Controllers/AdminProductController.php views/dashboard/products/edit.php app/Core/ProductImporter.php
OK: no ternary or null-coalescing syntax found.
```

MANUAL TEST STEPS:
1. Run `database/migrations/019_offer_status_and_freshness.sql` once in phpMyAdmin.
2. Open a product edit page: the offers table shows Status (`published`) and Last checked for each existing offer, `-` when empty.
3. Add an offer from the form: it appears with `published` and the current time.
4. Import a CSV that repeats an identical offer row: the run reports it under skipped identical offers, and Last checked on that offer updates.
5. Set one offer's `last_checked_at` to 20 days ago: it disappears from the public product page and from `/compare`, while the dashboard still lists it.
6. Set its store status to `draft` or its vendor status to `inactive`: it also disappears publicly.

DEVIATIONS:
- `countActive()` counts the full public definition (offer + store + vendor conditions via joins) because A3's first rule is that one definition is used everywhere; `countStale()` counts published, in-stock offers with `last_checked_at` older than 14 days as the task describes.

NOTICED:
- `PublicCompareController` relies on `findPublicByProductId()` so it inherits the new rule automatically.
- The offers table already had `status` (default `draft`) and `last_checked_at` from migration 003, so 019 only normalises the data and the column default.

QUESTIONS: none

---

## A4

TASK: A4 � Edit offers in the dashboard + protect click history

FILES CREATED: none

FILES CHANGED:
- `public/index.php` � added the `offer/(\d+)/update` route inside the Products block, before the delete route.
- `app/Controllers/AdminProductController.php` � added `ClickModel` dependency and `updateOffer()` (requireAdmin, POST check, CSRF, offer/product ownership check, validated fields, `last_checked_at` = now); `deleteOffer()` now refuses deletion when the offer has click history.
- `app/Models/ClickModel.php` � added `countByOfferId()`.
- `views/dashboard/products/edit.php` � each offer row has an Edit button opening a per-offer Bootstrap modal (`offer-edit-<id>`) with price, affiliate URL, status and in-stock fields; delete button kept.

CHECKS:
```
php -l public/index.php
No syntax errors detected in public/index.php
php -l app/Controllers/AdminProductController.php
No syntax errors detected in app/Controllers/AdminProductController.php
php -l app/Models/ClickModel.php
No syntax errors detected in app/Models/ClickModel.php
php -l views/dashboard/products/edit.php
No syntax errors detected in views/dashboard/products/edit.php
php bin/check_rules.php public/index.php app/Controllers/AdminProductController.php app/Models/ClickModel.php views/dashboard/products/edit.php
OK: no ternary or null-coalescing syntax found.
```

MANUAL TEST STEPS:
1. Open a product edit page, click Edit on an offer, change the price and save: the new price shows in the table and on the public page.
2. Untick "In stock" and save: the offer disappears from the public price table.
3. Click the Buy button of an offer on the public page, then try to delete that offer in the dashboard: refused with "This offer has click history. Set it to Draft or Out of stock instead."
4. Delete an offer without clicks: succeeds.
5. Hand-change the URL to an offer id of another product (`.../products/<other_id>/offer/<id>/update`, POST): "Offer not found." and redirect to that product edit page.

DEVIATIONS:
- The prompt says "keep the existing delete confirm modal", but `views/dashboard/products/edit.php` has no delete modal � deletion is a plain form button. Left it unchanged; only the edit modal was added.

NOTICED:
- `OfferModel::findByProductId()` already selects `o.*`, so the edit modal and the new Status / Last checked columns get their data without model changes.

QUESTIONS: none

---

## A5

TASK: A5 � Harden `/go/<offer_id>`

FILES CREATED: none

FILES CHANGED:
- `app/Controllers/PublicProductController.php` � `go()` now resolves the offer through `OfferModel::findActiveById()`; unavailable offers on a published product flash "That offer is no longer available." and redirect to the product page, otherwise render 404. Added `isBot()`; bot (or empty) user agents are redirected without a click row. Sends `X-Robots-Tag: noindex, nofollow` and `Cache-Control: no-store` before redirecting. 302 redirect and IP hashing unchanged.

CHECKS:
```
php -l app/Controllers/PublicProductController.php
No syntax errors detected in app/Controllers/PublicProductController.php
php bin/check_rules.php app/Controllers/PublicProductController.php
OK: no ternary or null-coalescing syntax found.
```

MANUAL TEST STEPS:
1. Click Buy on an active offer: redirect works and a new row appears in `offer_clicks`.
2. `curl -I <site>/go/<id>`: redirect header present, no new click row, `X-Robots-Tag: noindex, nofollow` and `Cache-Control: no-store` present.
3. Set the offer out of stock (or Draft, or its store Draft, or a stale date): open `/go/<id>` ? redirected to the product page with "That offer is no longer available."
4. Open `/go/999999` ? 404 page.

DEVIATIONS: none

NOTICED:
- The `go()` route still passes the id as string; casting is unchanged.
- `header()` calls are sent directly because the shared `redirect()` helper only sends `Location`.

QUESTIONS: none

---

## A6

TASK: A6 � Fix `robots.txt` and add `sitemap.xml`

FILES CREATED:
- `app/Controllers/SitemapController.php`
- `database/migrations/019_offer_status_and_freshness.sql` (already created in A3; still pending)

FILES CHANGED:
- `public/robots.txt` � every per-bot section now disallows `/dashboard`, `/login`, `/logout`, `/register`, `/member`, `/go/`, `/search`, `/wishlist/`; `Sitemap:` is the placeholder `https://REPLACE-ME/sitemap.xml`; `Security:` line removed.
- `public/index.php` � added `sitemap\.xml` route before the wildcard routes.
- `app/Models/ProductModel.php` � added `findAllPublishedWithActiveOffers()` (single query joining `categories`, `offers`, `stores`, `vendors` with `OfferModel::activeCondition()`).

CHECKS:
```
php -l app/Controllers/SitemapController.php
No syntax errors detected in app/Controllers/SitemapController.php
php -l app/Models/ProductModel.php
No syntax errors detected in app/Models/ProductModel.php
php -l public/index.php
No syntax errors detected in public/index.php
php bin/check_rules.php app/Controllers/SitemapController.php app/Models/ProductModel.php public/index.php
OK: no ternary or null-coalescing syntax found.
```

MANUAL TEST STEPS:
1. Set `APP_URL` in `.env` (PO step), open `/sitemap.xml`: valid XML with absolute URLs for `/`, `/categories`, `/brands`, published categories, published brands, and published products that have at least one active offer.
2. A product without active offers is absent; a draft category is absent.
3. Open `/robots.txt`: the eight new disallow paths in every section, the `Sitemap: https://REPLACE-ME/sitemap.xml` placeholder (replace the domain by hand), no `Security:` line.
4. Temporarily empty `APP_URL` ? `/sitemap.xml` returns HTTP 500 with "APP_URL is not set".

DEVIATIONS:
- `<lastmod>` values are converted from `YYYY-MM-DD HH:MM:SS` to `YYYY-MM-DDTHH:MM:SS` so they are valid W3C datetimes.
- `findAllPublishedWithActiveOffers()` also joins `categories` (inner) so every URL has a category slug.

NOTICED:
- `robots.txt` will keep the placeholder until the PO edits the domain by hand, per the task.

QUESTIONS: none

---

## B1

TASK: B1 � SEO helper and layout output

FILES CREATED:
- `app/Core/Seo.php`

FILES CHANGED:
- `views/layouts/header.php` � defaults for `$seo`, `$canonicalUrl`, `$ogImage`, `$ogType`, `$robotsOverride`, `$jsonLd`; prints robots meta, canonical link, Open Graph, Twitter tags, each `$jsonLd` string, cleaned `schema_markup`, `custom_head_tags`, and `custom_body` after `<body>`.
- `views/layouts/footer.php` � prints `custom_footer` just before `</body>`.

CHECKS:
```
php -l app/Core/Seo.php
No syntax errors detected in app/Core/Seo.php
php -l views/layouts/header.php
No syntax errors detected in views/layouts/header.php
php -l views/layouts/footer.php
No syntax errors detected in views/layouts/footer.php
php bin/check_rules.php app/Core/Seo.php views/layouts/header.php views/layouts/footer.php
OK: no ternary or null-coalescing syntax found.
```
Helper smoke test:
```
Hello world &
{"@type":"Brand","name":"X"}
string(0) ""
```

MANUAL TEST STEPS:
1. Open any page (home, dashboard, product): it still renders, no PHP notices.
2. `view-source` shows `<meta name="robots" content="index, follow">` and `og:site_name` = GudangPC on every page.
3. With `APP_URL` set, a page that passes a canonical shows `<link rel="canonical">` and `og:url` with the same absolute value.
4. After B2, page sources show the admin SEO values and images.

DEVIATIONS:
- Per the B1 prompt, the layout now prints both `$jsonLd` and `seo.schema_markup` (B4 will make the custom product schema replace the generated one). B4 is authoritative, so B2/B4 wiring will follow that rule.

NOTICED:
- `views/layouts/header.php` is included by dashboard pages too; they now emit `og:*` tags with their dashboard titles as well.

QUESTIONS: none

---

## B2

TASK: B2 � Wire SEO into home, category, brand, product pages

FILES CREATED: none

FILES CHANGED:
- `app/Controllers/HomeController.php` � passes `seo` (null), `canonicalUrl` (`Seo::absoluteUrl('/')`), `ogImage` (empty).
- `app/Controllers/PublicCategoryController.php` � added `SeoMetaModel`; list page passes its own canonical; detail page loads SEO, applies the title/description fallbacks from the task table, computes canonical `/<slug>` and the category image as absolute `ogImage`.
- `app/Controllers/PublicBrandController.php` � same pattern for `brand`; canonical `/brand/<slug>`; `ogImage` from the brand logo.
- `app/Controllers/PublicProductController.php` � title fallback `<name> - Price Comparison | GudangPC`, description fallback `Compare prices for <name>.`, passes `seo`, canonical `/<category_slug>/<slug>`, `ogImage` from the product image, `ogType` = `product`.

CHECKS:
```
php -l app/Controllers/HomeController.php
No syntax errors detected in app/Controllers/HomeController.php
php -l app/Controllers/PublicCategoryController.php
No syntax errors detected in app/Controllers/PublicCategoryController.php
php -l app/Controllers/PublicBrandController.php
No syntax errors detected in app/Controllers/PublicBrandController.php
php -l app/Controllers/PublicProductController.php
No syntax errors detected in app/Controllers/PublicProductController.php
php bin/check_rules.php app/Controllers/HomeController.php app/Controllers/PublicCategoryController.php app/Controllers/PublicBrandController.php app/Controllers/PublicProductController.php
OK: no ternary or null-coalescing syntax found.
```

MANUAL TEST STEPS:
1. Give a category an SEO title, description and robots `noindex, follow` in the dashboard; open that category: source shows all three.
2. Brand without SEO: fallback title `<Name> Products - Price Comparison | GudangPC` and fallback description appear.
3. Product page source contains an absolute canonical, `og:image` and `og:type` = `product`.
4. Put `{"@context":"https://schema.org","@type":"Brand","name":"X"}` in a product's schema field ? JSON-LD script appears; put garbage ? no script, page still renders (B4 also covers the replacement rule).

DEVIATIONS:
- B2 does not yet implement the "absolute `seo.canonical_url` wins" rule; that is B3 item 3 and will be added there.

NOTICED:
- `/search`, wishlist and other public pages still pass no SEO variables and get layout defaults only.

QUESTIONS: none

---

## B3

TASK: B3 � Canonical clean-up and product URL redirect

FILES CREATED:
- `database/migrations/020_clean_seo_canonical.sql`

FILES CHANGED:
- `app/Controllers/AdminProductController.php`, `AdminCategoryController.php`, `AdminBrandController.php`, `AdminStoreController.php` � `collectSeoInput()` no longer fills `canonical_url` when empty; the stored value is exactly what the user typed. Product method keeps its `$slug` / `$categorySlug` parameters.
- `app/Controllers/PublicCategoryController.php`, `PublicBrandController.php`, `PublicProductController.php` � computed canonical is used unless `seo.canonical_url` starts with `http`, which then wins.
- `app/Controllers/PublicProductController.php` � a published product reached with the wrong category slug now gets a 301 to `/<correct_category_slug>/<slug>` (uses `$GLOBALS['base']`, then `exit`).
- `views/dashboard/products|categories|brands|stores/create|edit.php` � canonical hint now reads "Leave empty to use the page's own URL. Only enter a full URL (https://...) to override."

CHECKS:
```
php -l (15 files: 7 controllers + 8 SEO views)
No syntax errors detected in <each file>
php bin/check_rules.php <same 15 files>
OK: no ternary or null-coalescing syntax found.
```

MANUAL TEST STEPS:
1. Create a product and view source: canonical is absolute and correct (computed, since the field is empty).
2. Change the product's category in the dashboard; open the old URL: 301 to the new `/category/slug` URL, canonical matches the new URL.
3. Enter `https://example.com/x` as a brand canonical: the brand page prints that exact value.
4. Check the canonical hint text in all create/edit SEO tabs.
5. PO step: run `database/migrations/020_clean_seo_canonical.sql` (clears old relative canonical values).

DEVIATIONS:
- AdminStoreController's empty-canonical block only assigned `''` (a no-op); it was removed along with the shared block form.

NOTICED:
- `collectSeoInput()` in the product controller now has unused `$slug` / `$categorySlug` parameters; kept on purpose as the task requires.

QUESTIONS: none

---

## B4

TASK: B4 � Breadcrumbs and structured data

FILES CREATED:
- `views/public/_breadcrumb.php`

FILES CHANGED:
- `app/Core/Seo.php` � added `breadcrumbJsonLd()` and `productJsonLd()` (AggregateOffer only; no `aggregateRating` / `review`).
- `app/Controllers/PublicProductController.php` � passes visible `breadcrumbs` (Home � Category � Product) and `jsonLd` (Product when active offers exist + BreadcrumbList).
- `app/Controllers/PublicCategoryController.php` � passes breadcrumbs (Home � Category) and breadcrumb JSON-LD.
- `app/Controllers/PublicBrandController.php` � passes breadcrumbs (Home � Brands � Brand) and breadcrumb JSON-LD.
- `views/public/products/show.php`, `categories/show.php`, `brands/show.php` � include `views/public/_breadcrumb.php` at the top of the content container.
- `views/layouts/header.php` � custom `seo.schema_markup` now suppresses the generated Product JSON-LD (user value replaces it); breadcrumb JSON-LD still prints.

CHECKS:
```
php -l (9 files)
No syntax errors detected in <each file>
php bin/check_rules.php <same 9 files>
OK: no ternary or null-coalescing syntax found.
```
Helper smoke test (`APP_URL=https://example.test`):
```
{"@context":"https://schema.org","@type":"Product","name":"RTX 4070","description":"Fast GPU \u0026 cool","image":"https://example.test/uploads/rtx.jpg","brand":{"@type":"Brand","name":"NVIDIA"},"mpn":"RTX4070","url":"https://example.test/gpu/rtx-4070","offers":{"@type":"AggregateOffer","priceCurrency":"IDR","lowPrice":8500000,"highPrice":9000000,"offerCount":2,"availability":"https://schema.org/InStock"}}
(empty line = no Product JSON-LD when there are no offers)
{"@context":"https://schema.org","@type":"BreadcrumbList","itemListElement":[{"@type":"ListItem","position":1,"name":"Home","item":"https://example.test/"},{"@type":"ListItem","position":2,"name":"GPU","item":"https://example.test/gpu"},{"@type":"ListItem","position":3,"name":"RTX 4070"}]}
```

MANUAL TEST STEPS:
1. Open a product page with active offers: breadcrumb visible; source has a Product JSON-LD and a BreadcrumbList; validate with a schema validator.
2. A product with no active offers: no Product JSON-LD, breadcrumb only.
3. Fill a custom schema in the product's dashboard SEO tab: it replaces the generated Product block; the breadcrumb script remains.
4. Category and brand pages show their breadcrumbs and one BreadcrumbList.

DEVIATIONS:
- Breadcrumb `item` is omitted for the last crumb (empty path) instead of pointing at the current URL; this is the schema.org-recommended shape.

NOTICED:
- The header filter detects the generated Product schema by the literal `"@type":"Product"` substring; any future generated product schema must keep that encoding (the encoder flags produce it).

QUESTIONS: none

---

## C1

TASK: C1 � Navbar clean-up

FILES CREATED: none

FILES CHANGED:
- `views/layouts/navbar.php` � the Products item is now live and links to `<?= $base ?>/categories` (was a commented-out `href="#"` item). Nothing else changed.

CHECKS:
```
php -l views/layouts/navbar.php
No syntax errors detected in views/layouts/navbar.php
php bin/check_rules.php views/layouts/navbar.php
OK: no ternary or null-coalescing syntax found.
```

MANUAL TEST STEPS:
1. As a guest: Sign In/Login and Register each appear once; Products opens `/categories`.
2. Log in as a member: Member Area link appears; active highlighting still works on Home/Categories/Brands.
3. As admin: Dashboard link and sidebar toggle still work.

DEVIATIONS:
- The only `? :` ternaries near Sign In/Register are inside an existing block comment (dead code); the live links already use computed variables. They were left untouched because they render nothing.
- The live navbar already had separate Categories and Brands links, so Products and Categories now point to the same page (as the task requires).

NOTICED:
- The commented-out old Sign In/Register block (lines with a note that registration is dashboard-only) is still dead code.

QUESTIONS: none

---

## C2

TASK: C2 � Remove the compare feature (decision D1)

FILES CREATED: none

FILES CHANGED:
- `public/index.php` � removed the `compare` route.
- `views/home/index.php`, `views/public/categories/show.php`, `views/public/brands/show.php`, `views/public/search/index.php` � removed the compare checkbox block (wishlist form kept), the `#compare-float` div and the script that read `.compare-checkbox`. Home parallax scripts left intact.

FILES DELETED:
- `app/Controllers/PublicCompareController.php`
- `views/public/compare/show.php`

CHECKS:
```
php -l public/index.php
php -l views/home/index.php
php -l views/public/categories/show.php
php -l views/public/brands/show.php
php -l views/public/search/index.php
No syntax errors detected in <each file>
php bin/check_rules.php <same 5 files>
OK: no ternary or null-coalescing syntax found.
```

MANUAL TEST STEPS:
1. No compare checkbox or floating "Compare Selected" button on home, category, brand, search.
2. `/compare?ids=1,2` returns the 404 page.
3. Wishlist heart forms still work on all four pages; product links still work.

DEVIATIONS:
- The wrapper flex div around the wishlist form was kept (only the checkbox block removed).

NOTICED (remaining occurrences of "compare", case-insensitive):
- `app/Controllers/AdminCategoryController.php:285` � `compare` is still in the reserved-slug list.
- `views/home/index.php:97`, `views/public/{categories,brands}/show.php`, `views/public/search/index.php` � link buttons still labelled "Compare Prices" (they lead to the product page).
- `views/public/products/show.php:124` � price-table heading "Compare Prices" (explicitly allowed).
- `app/Controllers/HomeController.php:42-43`, `PublicCategoryController.php:75`, `PublicBrandController.php:109`, `PublicProductController.php:121` � meta titles/descriptions use the word "Compare".
- `bin/seed.php:119-120` � default OG/Twitter descriptions mention "compare prices".

QUESTIONS: none

---

## C3

TASK: C3 � Product card partial with prices

FILES CREATED:
- `views/public/_product_card.php`

FILES CHANGED:
- `app/Models/ProductModel.php` � `getPublishedWithRelations()`, `findAllByCategorySlug()`, `findAllByBrandSlug()`, `search()` now also select `lowest_price` and `offer_count` via correlated subqueries using `OfferModel::activeCondition()`; WHERE/ORDER BY untouched.
- `views/home/index.php`, `views/public/categories/show.php`, `views/public/brands/show.php`, `views/public/search/index.php` � card markup inside each `foreach` replaced by `include` of the partial (home uses `__DIR__ . '/../public/_product_card.php'`, the others `__DIR__ . '/../_product_card.php'`).
- `public/assets/css/frontend.css` � added `.product-card-dim { opacity: .6; }`.

CHECKS:
```
php -l app/Models/ProductModel.php
php -l views/public/_product_card.php
php -l views/home/index.php
php -l views/public/categories/show.php
php -l views/public/brands/show.php
php -l views/public/search/index.php
No syntax errors detected in <each file>
php bin/check_rules.php <same 6 files>
OK: no ternary or null-coalescing syntax found.
```

MANUAL TEST STEPS:
1. Home, a category page, a brand page and a search result show "From Rp ..." plus "N seller(s)".
2. A product whose offers are all inactive shows "No active offers" and a dimmed card.
3. Images have `loading="lazy"`; wishlist heart still posts with CSRF; the Compare Prices button still opens the product page.

DEVIATIONS: none

NOTICED:
- The partial keeps the "Compare Prices" button label (C2 allowed the product-page heading; this label is a link to the product page).
- Price subqueries run per row; the schema already has the relevant indexes (migration 014) but this is not benchmarked.

QUESTIONS: none

TASK: C4 - Pagination and sorting (category, brand, search)
FILES CREATED:
- `views/public/_pagination.php` - Bootstrap pagination partial using `$page`, `$totalPages`, `$pagePath`, `$pageParams`; hidden when `totalPages <= 1`; Previous/Next disabled at the edges; all URLs and numbers escaped.

FILES CHANGED:
- `app/Models/ProductModel.php` - added `$limit = 20`, `$offset = 0`, `$sort = 'newest'` defaults to `findAllByCategorySlug()`, `findAllByBrandSlug()`, `search()`; added `countByCategorySlug()`, `countByBrandSlug()`, `countSearch()` (published only); added private `sortClause()` (fixed whitelist-to-SQL mapping, NULL prices last) and private `queryPaginated()` (binds LIMIT/OFFSET with `PDO::PARAM_INT`, needed because `ATTR_EMULATE_PREPARES` is false).
- `app/Controllers/PublicCategoryController.php` - reads `page`/`sort` from `$_GET` with `Validator::integer()` / `Validator::inArray()`, computes `total`/`totalPages`, 404 when `page > totalPages && page > 1`, passes paging vars plus `pagePath`/`pageParams`; canonical for page N > 1 is the page URL with `?page=N` and no `sort`.
- `app/Controllers/PublicBrandController.php` - same paging/sort handling; canonical `?page=N` without `sort`.
- `app/Controllers/PublicSearchController.php` - same paging/sort handling; `countSearch()` only when the query is non-empty; passes `pagePath` `/search` and `pageParams` `q` + `sort`.
- `views/public/categories/show.php` - GET sort `<select>` (Newest / Price: low to high / Price: high to low) with a "Sort" submit button above the grid; pagination include below the grid.
- `views/public/brands/show.php` - same sort form and pagination include.
- `views/public/search/index.php` - same sort form with a hidden `q`, plus pagination include.

CHECKS:
```
php -l app/Models/ProductModel.php
php -l app/Controllers/PublicCategoryController.php
php -l app/Controllers/PublicBrandController.php
php -l app/Controllers/PublicSearchController.php
php -l views/public/_pagination.php
php -l views/public/categories/show.php
php -l views/public/brands/show.php
php -l views/public/search/index.php
No syntax errors detected in <each file>
php bin/check_rules.php <same 8 files>
OK: no ternary or null-coalescing syntax found.
```

MANUAL TEST STEPS:
1. Open a category with more than 20 published products: page 1 shows 20 cards and pagination; `?page=2` shows the rest.
2. `?page=99` returns the 404 page.
3. In the sort select choose "Price: low to high" and press Sort: cheapest first, products without active offers last; the same for "Price: high to low".
4. Search (e.g. `?q=asus`) then go to page 2: the `q` is kept and the sort select still reflects the current sort.
5. View source on `?page=2&sort=price_asc`: canonical is `...?page=2` without `sort`.

DEVIATIONS:
- Also whitelisted `sort` inside `ProductModel::sortClause()` (task only required the controller whitelist) so the model is safe for any caller; the controllers still whitelist first.
- `queryPaginated()` binds LIMIT/OFFSET as integers instead of using `query()` string binding, because `config/database.php` disables emulated prepares.

NOTICED:
- Category/brand views still render the "Compare Prices" card button label (C2 leftover).
- `PublicSearchController` still has no SEO override vars; C5 configures `noindex`/canonical.
- Search currently matches the whole query as one contiguous string; C5 replaces this.

QUESTIONS: none

TASK: C5 - Better search
FILES CHANGED:
- `app/Models/ProductModel.php` - `search()` and `countSearch()` now split the query on whitespace (max 6 tokens), escape `\`, `%` and `_` per token, and require every token to match one of `p.name`, `p.model_number`, `p.search_tags`, `b.name`, `p.short_description` with `ESCAPE '\\'`; groups are ANDed. Both join `brands` for `b.name`; `search()` keeps the C4 sort/LIMIT/OFFSET handling. Added private `searchPatterns()`; empty/blank query returns no rows and count 0.
- `app/Controllers/PublicSearchController.php` - passes `robotsOverride` = `noindex, follow` and `canonicalUrl` = `''`; the rest of the C4 paging/sort wiring is unchanged.
- `views/public/search/index.php` - heading `Search results for: <query>` plus a result count (`N result(s)`); empty query shows an intro and a link to `/categories` instead of the blank grid; no results shows "No products found" plus the categories link. Sort form and pagination kept from C4.

CHECKS:
```
php -l app/Models/ProductModel.php
php -l app/Controllers/PublicSearchController.php
php -l views/public/search/index.php
No syntax errors detected in <each file>
php bin/check_rules.php <same 3 files>
OK: no ternary or null-coalescing syntax found.
```
Extra manual check (temp script, no DB): `searchPatterns("rtx asus")` -> `%rtx%`, `%asus%`; `"50% _x_ a\b c d e f g"` -> `%50\%%`, `%\_x\_%`, `%a\\b%`, `%c%`, `%d%`, `%e%` (token 7+ dropped).

MANUAL TEST STEPS:
1. Search `rtx asus`: products whose name/tags contain both words in any order appear.
2. Search `50%`: only literal "50%" matches, not everything.
3. Search `<script>`: the heading shows it escaped, no alert.
4. Empty search page shows the categories link; a nonsense query shows "No products found" plus that link; results show the count.
5. View source on a search URL: `<meta name="robots" content="noindex, follow">` and no canonical link.

DEVIATIONS:
- Added private `searchPatterns()` instead of duplicating tokenisation in both methods; behaviour is exactly the task's algorithm.
- Kept the C4 sort form and pagination on the search page.

NOTICED:
- `SearchCacheModel` / `search_cache` left untouched as instructed; they remain unused.
- `p.model_number` exists (`database/migrations/003_create_others_table.sql`), so the added column reference is valid.
- The product card/link labels still say "Compare Prices" (C2 leftover).

QUESTIONS: none

TASK: C6 - Homepage: latest products per category (Part 1)
FILES CHANGED:
- `app/Controllers/HomeController.php` - removed the `getPublishedWithRelations()` call; `$categories` = first 8 of `findAllPublished('sort_order ASC')`; `$categorySections` = up to the first 6 categories that return products, each `['category' => row, 'products' => rows]` from `findLatestActiveByCategoryId($id, 8)`; `$brands` = `findPublishedWithProducts(12)`.
- `app/Models/ProductModel.php` - added `findLatestActiveByCategoryId(int $categoryId, int $limit): array`: published products of the category with `EXISTS` active offer (`OfferModel::activeCondition()`), same selected columns incl. `lowest_price`/`offer_count`, `ORDER BY p.id DESC LIMIT ?` bound as `PDO::PARAM_INT`.
- `app/Models/BrandModel.php` - added `findPublishedWithProducts(int $limit): array`: published brands with at least one published product, ordered by `name ASC`, `LIMIT ?` bound as int.
- `views/home/index.php` - hero images now use `<?= $base ?>/uploads/images/...`; the single "Latest Products" block is replaced by one "Latest in <category>" block per `$categorySections` entry (heading, "View all" link to `/<slug>`, `_product_card.php` cards); added a brands strip after the sections (logo at `height="40"` if `logo` is set, else a name badge, linking to `/brand/<slug>`).

CHECKS:
```
php -l app/Controllers/HomeController.php
php -l app/Models/ProductModel.php
php -l app/Models/BrandModel.php
php -l views/home/index.php
No syntax errors detected in <each file>
php bin/check_rules.php <same 4 files>
OK: no ternary or null-coalescing syntax found.
```

MANUAL TEST STEPS:
1. Home loads fast with 100+ products and shows "Latest in ..." for each of the first six categories that have products with active offers; categories without such products show no section.
2. No card in any section shows a product without an active offer ("From Rp ..." > 0 sellers everywhere).
3. "View all" opens the matching category page.
4. The brands strip appears after the sections; logos/names link to the brand page.
5. Hero images load when the app is served from a subfolder (base path respected).

DEVIATIONS:
- Removed the passed `products` variable because the view no longer uses it (Part 1 replaces the single block).
- Part 2 ("Most clicked this week") not implemented: the task says it is only for after Part 1 is accepted.

NOTICED:
- `ProductModel::getPublishedWithRelations()` now has no callers; left in place because removing it was not requested.
- Brand strip logos use a `height="40"` attribute and no new CSS class (inline `style` is discouraged by AGENTS.md).

QUESTIONS: none

TASK: C7 - Product page polish
FILES CHANGED:
- `app/Controllers/PublicProductController.php` - no longer groups offers per store; passes the flat `$offers` list (already price-ascending), computes `inWishlist` (true only when `$_SESSION['user_id']` is set, `$_SESSION['role']` is `visitor`, and `WishlistModel::exists()` is true), loads `related` via `findRelated($categoryId, $productId, 4)`, and passes `inWishlist`/`related`.
- `app/Models/ProductModel.php` - added `findRelated(int $categoryId, int $excludeId, int $limit): array`: published products in the same category excluding the current product, `EXISTS` an active offer (`OfferModel::activeCondition()`), same selected columns incl. `lowest_price`/`offer_count`, `ORDER BY p.id DESC LIMIT ?` bound as int.
- `views/public/products/show.php` - layout reworked: left `col-lg-8` gets the header card, a "Compare prices" card with a flat offers table (Marketplace, Seller, Listing, Seller rating, Product rating, Sold, Price, Buy), the description card, and "Related products"; right `col-lg-4` gets the summary card ("From Rp X", "N sellers", "Updated <relative>", primary Buy button to the cheapest offer, wishlist control, disclosure). Offers table lists the first 10 rows, then a Bootstrap collapse ("Show all N offers") with the rest. "Lowest price" badge on the first row. Prices ascending, Buy links use `/go/<id>` with `target="_blank"` and `rel="sponsored nofollow noopener"`. Seller rating prefers `vendor_store_rating` then `vendor_rating`; sold count printed as `N sold`; relative time computed in the view PHP block from the newest `last_checked_at` (`just now`, `N hours ago`, `N days ago`). No offers shows "No active offers right now." and keeps the wishlist form. Wishlist form posts to `/wishlist/add/<id>` or `/wishlist/remove/<id>` with the CSRF hidden input.
- `public/assets/css/frontend.css` - added `.offer-title` (max-width 220px, nowrap, ellipsis) for the listing title.

CHECKS:
```
php -l app/Models/ProductModel.php
php -l app/Controllers/PublicProductController.php
php -l views/public/products/show.php
No syntax errors detected in <each file>
php bin/check_rules.php <same 3 files>
OK: no ternary or null-coalescing syntax found.
```

MANUAL TEST STEPS:
1. Open a product with several offers: first row has the "Lowest price" badge, prices ascend, Buy opens `/go/<id>` in a new tab.
2. The summary card shows the lowest "From Rp ..." price, seller count, relative "Updated ..." text, and the Buy button points to the same cheapest offer.
3. A product with 12 active offers shows 10 rows plus a "Show all 12 offers" button that expands the rest.
4. Logged-in `visitor` who saved the product sees "Remove from wishlist"; otherwise "Save to wishlist"; both POST with a CSRF token.
5. A product with no active offers shows "No active offers right now." and still shows the wishlist control.
6. A product with siblings in its category shows up to 4 "Related products" cards; a lone product hides the section.

DEVIATIONS:
- Added `.offer-title` to `public/assets/css/frontend.css` (the task asked for truncation "with a CSS class" but did not list the CSS file).
- Listing title prints an empty cell (a `-` is not used) when `seller_title` is null; other optional cells fall back to `-`.
- Sold counts are printed with thousand separators (`1.234 sold`); the `1,2rb` compact style is out of scope as stated.

NOTICED:
- Buyer-facing labels on cards still say "Compare Prices" (C2 leftover); the product page heading now matches the task's "Compare prices" text.
- `WishlistController::remove()` still redirects to `/member`, as the task requires leaving it.
- The product page now runs extra queries for `related` and `inWishlist`; the wishlist check only runs for a logged-in `visitor`.

QUESTIONS: none

TASK: D1 - Migration for listing identity and affiliate templates
FILES CREATED:
- `database/migrations/021_offer_listing_identity_and_affiliate_template.sql` - adds `offers.source_url` (text, AFTER `affiliate_url`) and `offers.external_id` (varchar(100)) with `ADD COLUMN IF NOT EXISTS`; creates `UNIQUE KEY offers_store_external (store_id, external_id)` only when the index is absent (information_schema.statistics check like `014_add_indexes.sql`); adds `stores.affiliate_url_template` (varchar(500)).

FILES CHANGED:
- `docs/WORKLOG.md` - `MIGRATIONS PENDING` now lists 019, 020 and 021.

CHECKS:
```
php bin/check_rules.php database/migrations/021_offer_listing_identity_and_affiliate_template.sql
OK: no ternary or null-coalescing syntax found.
```
`php -l` is not applicable: the task allows SQL only and no PHP file was touched.

MANUAL TEST STEPS:
1. Run the migration once; `DESCRIBE offers;` shows `source_url` and `external_id`; `DESCRIBE stores;` shows `affiliate_url_template`.
2. `SHOW INDEX FROM offers;` shows `offers_store_external`.
3. Run the file a second time; it completes without duplicate-column or duplicate-key errors.
4. Existing public product pages and dashboard store pages still load.

DEVIATIONS: none

NOTICED:
- The unique key `(store_id, external_id)` relies on MySQL/MariaDB allowing repeated NULL `external_id` values; legacy rows are unaffected.
- The migration is not run here (PO runs migrations); the `MIGRATIONS PENDING` list now includes it.

QUESTIONS: none

TASK: D2 - Affiliate template field on marketplaces
FILES CHANGED:
- `app/Controllers/AdminStoreController.php` - `collectInput()` reads `affiliate_url_template` (max 500, `Validator::sanitize`), rejects non-empty values unless they start with `https://` and contain `{url}`, `{url_encoded}` or `{external_id}` with the exact error message; the value is saved in create/update. Added the key to `emptyFormData()` and `formDataForStore()`, and `collectOldInput()` repopulates it after an error.
- `views/dashboard/stores/create.php`, `views/dashboard/stores/edit.php` - new optional "Affiliate URL template" text input (maxlength 500) after Base URL, with the required helper text and value repopulated from `$old`.

CHECKS:
```
php -l app/Controllers/AdminStoreController.php
php -l views/dashboard/stores/create.php
php -l views/dashboard/stores/edit.php
No syntax errors detected in <each file>
php bin/check_rules.php <same 3 files>
OK: no ternary or null-coalescing syntax found.
```

MANUAL TEST STEPS:
1. Edit a store, enter `https://affiliate-network.example/deeplink?to={url_encoded}&sub=gudangpc`, save; reload the edit page and see the value.
2. Enter `https://example.com/no-placeholder` or `http://example.com/{url}` and save: error "Template must start with https:// and contain {url}, {url_encoded} or {external_id}." and the typed value is kept.
3. Leave it empty and save: no error.

DEVIATIONS:
- Field label is "Affiliate URL template"; the helper text consists of the required example plus "Leave empty to use the raw product URL." inside a `<code>` element.

NOTICED:
- The store form now writes `affiliate_url_template`, so migration 021 must be run before saving a store.
- Template sanitising uses `strip_tags`, so a template containing `<` would lose that fragment; the task's validation rules do not expect such characters.

QUESTIONS: none

TASK: D3 - Importer hardening
FILES CHANGED:
- `app/Core/ProductImporter.php` - real runs wrap the whole row loop in one transaction (`beginTransaction()` / `commit()`, `rollBack()` + rethrow on any `Throwable`; row rejections do not abort). New optional CSV columns `source_url`, `external_id` (external id truncated to 100). Offer detection now counts marketplace, (vendor_slug or vendor_name), price_idr, (affiliate_url or source_url); product-only rows still take the product path and partial offer rows are rejected as `incomplete offer data`. Seller auto-create: when `vendor_slug` is empty, a slug is generated from `<marketplace>-<vendor_name>`, looked up, and inserted as `active` (with `store_rating` when supplied); `created_vendors` counted; known good vendors cached per run. Affiliate resolution: explicit `affiliate_url` wins; otherwise the store template replaces `{url_encoded}` (rawurlencode), `{url}`, `{external_id}`; without a template `source_url` is used and one `no affiliate template for <marketplace>` warning is added. `source_url`/`external_id` are always stored on created offers. Matching by `(store_id, external_id)` first (an early pre-check rejects `listing already linked to another product` before any vendor/product write), then `(product_id, vendor_id)`; a stored external id that differs from the CSV one rejects with `seller already has another listing for this product`; updates fill missing `external_id`/`source_url`. Rows with a `notes` value other than empty/`ok` are rejected with `scraper note: <value>`. Price changes above 50 % add `large price change: <slug> <old> -> <new>` while still updating. Report also returns `created_vendors` and `warnings`.
- `app/Controllers/AdminProductImportController.php` - success flash message includes the sellers created count.
- `views/dashboard/products/import.php` - reads `created_vendors` and `warnings`; added a "Sellers created" counter and a Warnings card (only when non-empty) above the rejection table.

CHECKS:
```
php -l app/Core/ProductImporter.php
php -l app/Controllers/AdminProductImportController.php
php -l views/dashboard/products/import.php
No syntax errors detected in <each file>
php bin/check_rules.php <same 3 files>
OK: no ternary or null-coalescing syntax found.
```
Functional check (temp script, in-memory SQLite mirroring the schema + unique index): product-only row created a product; seller auto-created with store_rating; template produced `affiliate_url`; `source_url` stored; external-id mismatch rejected; notes rejection; one warning per marketplace; 100 % price change warned and updated; source_url used as affiliate URL when no template; `seller already has another listing for this product` rejected leaving one offer; forced insert exception rolled back products, vendors and offers. All assertions passed.

MANUAL TEST STEPS:
1. Import a CSV row with `marketplace` and `vendor_name` only (no `vendor_slug`): the seller appears under Vendors and the offer imports.
2. Import a row with `notes` = `blocked_captcha`: rejected with `scraper note: blocked_captcha`.
3. Cause an exception mid-file (for example point a row at a database value the schema rejects): the run aborts and no rows from it are stored (check products/offers counts).
4. Import rows for a marketplace with no template: Warnings card shows `no affiliate template for <marketplace>` once; after saving a template in D2, the stored `affiliate_url` follows the template.
5. Change a price by 60 % and re-import: offer updated, Warnings card shows `large price change: ...`.
6. Import the same `external_id` for a different `product_slug`: rejected with `listing already linked to another product`.

DEVIATIONS:
- Added an early external-id pre-check before seller/product creation so a rejected mismatched listing does not create a seller as a side effect.
- The product-only row path never reads `source_url`/`external_id`; those columns only affect offer rows.
- `bin/import_products.php` was left unchanged because it does not read report keys that changed (new keys are additive).

NOTICED:
- `bin/import_products.php` still prints its old comment that vendors are never created; the CSV help text in `import.php` still says vendors are never created and describes `affiliate_url` as required. Both are now stale (the task did not authorize editing them beyond the listed changes).
- Importing the same new product row twice in one file still behaves as before (the second row is treated as an existing product and may create a new offer).
- A seller auto-created for a row that is later rejected by a non-external rule (for example `invalid price_idr`) is still inserted; only the external-id mismatch is pre-checked.

QUESTIONS: none

TASK: D4 - Price history
FILES CREATED:
- `database/migrations/022_create_price_history.sql` - `CREATE TABLE IF NOT EXISTS price_history` (`id`, `offer_id` FK `ON DELETE CASCADE`, `price_idr bigint unsigned`, `recorded_at datetime`, index `(offer_id, recorded_at)`), then the one-time backfill `INSERT ... SELECT id, price_idr, COALESCE(last_checked_at, updated_at) FROM offers`.
- `app/Models/PriceHistoryModel.php` - `protected string $table = 'price_history'`, `protected bool $timestamps = false`, `record(int $offerId, int $priceIdr): bool` inserting the current timestamp.

FILES CHANGED:
- `app/Core/ProductImporter.php` - builds a `PriceHistoryModel` for real runs only; records a row after an offer is created and after an update when `price_idr` actually changed; dry runs and identical prices record nothing.
- `app/Controllers/AdminProductController.php` - injects `PriceHistoryModel`; `storeOffer()` captures the new offer id and records its price; `updateOffer()` compares the stored price with the submitted price and records only on change.
- `docs/WORKLOG.md` - `MIGRATIONS PENDING` now includes 022.

CHECKS:
```
php -l app/Models/PriceHistoryModel.php
php -l app/Core/ProductImporter.php
php -l app/Controllers/AdminProductController.php
No syntax errors detected in <each file>
php bin/check_rules.php <same 3 files>
OK: no ternary or null-coalescing syntax found.
```
Functional check (temp script, in-memory SQLite with a `price_history` table): first import created one history row; an identical-price re-import added none; a changed-price re-import added one and stored the new price; `PriceHistoryModel::record()` wrote directly. All passed.

MANUAL TEST STEPS:
1. Run migration 022 once; `DESCRIBE price_history;` shows the columns and `SHOW INDEX` lists `(offer_id, recorded_at)`; existing offers have one backfill row each.
2. Import a CSV with a changed price for an existing offer: exactly one new `price_history` row.
3. Edit an offer price on the dashboard: one new row.
4. Save an offer (dashboard or import) without changing the price: no new row.
5. Dry-run an import with a changed price: no rows written.

DEVIATIONS:
- `recorded_at` is set explicitly by the model instead of relying on the column default.
- The backfill has no idempotence guard because the task states the file is run once.

NOTICED:
- Deleting an offer cascades its price history rows; deleting a product cascades offers and therefore their history.
- The dashboard exposes no price-history UI yet (as specified).

QUESTIONS: none
