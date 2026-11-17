# GudangPC: Task Pack v2 (for OpenCode)

Save this file as `docs/TASKS_V2.md`. Paste **one task at a time** into a **new** OpenCode session (Plan tab first, then Build tab).
`AGENTS.md` still applies to every task (iron rules, syntax rule, view rules, report format). This file only adds *what* to build.

Every task prompt you paste must start with:

> Read `AGENTS.md`, then `docs/PRODUCT_SPEC.md`, then ONLY the task below from `docs/TASKS_V2.md`. Do nothing outside the task. If anything is unclear or contradicts the code, write `QUESTIONS` and stop.

Between tasks: `git add -A && git commit -m "<task id>"`.

---

## 0. Before you start (PO manual steps, not for the worker)

1. **Revoke the GitHub token now.** `docs/token-github-ibizz.id1235-onebuild-cpu` looks like an access token and is not in `.gitignore`. Revoke it on GitHub, delete the file, add `docs/token-*` to `.gitignore`, and remove it from git history (or at least rotate, since the repo was pushed).
2. Add to `.env` (full origin, no trailing slash): `APP_URL=https://your-real-domain`. Tasks B1+ read it with `\App\Core\Dotenv::get('APP_URL', '')`.
3. Back up the database (`mysqldump`) before running any migration.
4. Pre-check (run in phpMyAdmin and keep the result):
```sql
SELECT 'stores' AS t, status, COUNT(*) FROM stores GROUP BY status
UNION ALL SELECT 'vendors', status, COUNT(*) FROM vendors GROUP BY status
UNION ALL SELECT 'offers', status, COUNT(*) FROM offers GROUP BY status;
```
   Task A3 hides offers whose marketplace is not `published` or whose seller is not `active`. Publish your stores first.
5. Migrations in this pack are numbered **019 to 022**. You run each SQL file yourself, in order, when the task says so.

## 1. Decisions baked into this pack (veto before you start)

| # | Decision |
|---|---|
| D1 | **Compare feature is removed** (routes, UI, controller, view). Parked until per-category specs exist. |
| D2 | **Wishlist stays.** Members still register as `pending` and an admin activates them (unchanged). |
| D3 | **Active offer** = offer `published` AND `in_stock = 1` AND marketplace (`stores`) `published` AND seller (`vendors`) `active` (or no vendor) AND `last_checked_at` within 14 days (or NULL). One definition, in `OfferModel`, used everywhere. |
| D4 | Products with **no active offer**: shown dimmed on category/brand/search pages, hidden on the homepage, left out of `sitemap.xml`, no price in structured data. |
| D5 | **No public pages for stores/vendors** for now. Their SEO fields stay in the dashboard but nothing renders them. |
| D6 | Unique rule `(product_id, vendor_id)` stays. A second listing from the same seller for the same product is **reported**, not merged. |
| D7 | Affiliate links: a per-marketplace **template** builds the link from the raw product URL. If no template exists, the raw URL is used and the import report warns. |
| D8 | UI language stays **English** for now (parked: Indonesian UI). |
| D9 | Canonical URL is **computed at render time** from `APP_URL`; stored canonical values are only honoured if they are absolute (`http...`). |

Vocabulary used in this pack: **Marketplace** = a row in `stores` (Tokopedia, Shopee, Lazada). **Seller** = a row in `vendors` (belongs to one marketplace). Table and class names do not change.

---

## 2. Phase map and order

| Phase | Tasks | Purpose |
|---|---|---|
| 0 | T0.1 | Make `PRODUCT_SPEC.md` match reality |
| A | A1 to A6 | Bugs, security, offer rules, robots and sitemap |
| B | B1 to B4 | SEO output actually appears on pages |
| C | C1 to C7 | Browse experience: prices on cards, pagination, search, homepage, product page |
| D | D1 to D5 | Import engine: freshness, affiliate templates, price history, scraper |
| E | E1, E2 | Dashboard overview, click analytics, product list |

Do them in this order. C3 needs A3. D3 needs D1 and D2. D4 needs A4.

---

# PHASE 0

## T0.1: Rewrite `docs/PRODUCT_SPEC.md` to match the real project

**Files changed:** `docs/PRODUCT_SPEC.md` only. No code.

The spec is outdated (it says "no automatic scraping", has no vendors, no member area, no wishlist). Rewrite it so it is true. It must contain:

- Product: price-comparison + affiliate site for PC parts, Indonesia. Offers come from a local Python scraper that writes a CSV, which staff upload in `/dashboard/products/import`. No marketplace API yet.
- Roles: `root`, `admin` (table `users`, Dashboard) and `member` (table `members`, Member Area `/member`, session role value is `visitor`). Members register at `/register`, status `pending` until an admin activates them.
- Vocabulary: Marketplace = `stores`, Seller = `vendors`.
- Status vocabulary: `draft` / `published` for categories, brands, stores, products, offers. Vendors use `active`. Members use `pending`, `active`, `banned`.
- Active offer definition (decision D3) and decisions D1, D4 to D9 from `docs/TASKS_V2.md`.
- Data model as it is today: `categories`, `brands`, `stores`, `vendors`, `products` (incl. `image_alt`, `search_tags`), `offers` (incl. `vendor_id`, `seller_name`, `seller_rating`, `seller_title`, `product_rating`, `sold_count`), `offer_clicks`, `seo_meta` (incl. `custom_body`, `custom_footer`), `settings`, `members` (incl. `whatsapp`), `wishlists`, `search_cache` (currently unused). Planned: migration 021 adds `offers.source_url`, `offers.external_id`, `stores.affiliate_url_template`; migration 022 adds `price_history`.
- Real URL map: `/`, `/categories`, `/brands`, `/brand/<slug>`, `/<category-slug>`, `/<category-slug>/<product-slug>`, `/search?q=`, `/go/<offer_id>`, `/register`, `/login`, `/member`, `/wishlist/add/<id>`, `/wishlist/remove/<id>`, `/sitemap.xml` (planned).
- Roadmap = the phase map above. "Parked": specs and compare, price alerts, Indonesian UI, public marketplace/seller pages, image mirroring, import review queue.

**Do not** change any other file.

**Manual test:** open the file, confirm it no longer says there is no scraping and that vendors, members and wishlist are described.

---

# PHASE A: Bugs, security, offer rules

## A1: Fix member registration (always fails today)

**Bug:** in `app/Controllers/AuthController.php`, method `register()`, the code does `Validator::sanitize($whatsapp, 20)` on the empty variable instead of the posted value, so the WhatsApp check always fails and **nobody can register**.

**Read first:** `AuthController.php` (`register`), `views/auth/register.php`, `app/Core/RateLimiter.php` (signature of `consume`).

**Change (only `AuthController.php`):**
1. Read `$_POST['whatsapp']` into a raw variable (default `''`), sanitize it (max 30), then remove spaces and dashes with `preg_replace('/[\s\-]/', '', ...)` before the existing regex check.
2. Keep the existing regex rule and error message.
3. The registration rate limit currently uses one global bucket (`consume('register', 'register')`): one attacker blocks everyone. Use the visitor's `REMOTE_ADDR` (string, default `''`) as the identifier instead of the literal `'register'`; keep the action name `'register'`.

**Do not:** touch login, email verification, or the member model.

**Manual test:**
1. Register with `+628123456789`, valid email, 8+ char password → redirected to login with the "administrator must activate" message.
2. Check the `members` row: status `pending`, `whatsapp` filled.
3. Register again with `0812 3456 789` (spaces) and another email → accepted, stored without spaces.
4. Register with `abc` as WhatsApp → error shown.
5. Dashboard → Members → activate the member → member can log in.

## A2: Stop raw description output (stored XSS risk)

**Problem:** `views/public/products/show.php` prints `$descriptionValue` unescaped. The admin form cleans it, but `ProductImporter` writes CSV `description` straight into the database. Category and brand pages already use `\App\Core\RichText::clean()`.

**Files:** `views/public/products/show.php`, `app/Core/ProductImporter.php` (protected, this task authorizes it).

**Change:**
1. In the view, compute `$descriptionHtml = \App\Core\RichText::clean($descriptionValue);` in the PHP block at the top and print that instead. Add the same one-line comment style used in `views/public/categories/show.php` ("cleaned by RichText::clean(); only unescaped output on this page"). Keep the empty-description fallback text.
2. In `ProductImporter.php`, there are **two** places that do `$productData['description'] = $description;`. In both, wrap the value in `RichText::clean(...)` (same namespace, no `use` needed). Do not refactor the duplicated block.

**Manual test:**
1. In phpMyAdmin set a product description to `<p>Hi</p><script>alert(1)</script><a href="javascript:alert(2)">x</a>`.
2. Open the product page: no alert, no script tag in page source, the link has no `href`.
3. Product with a normal description still renders paragraphs.

## A3: One definition of "active offer" + freshness

**Goal:** public pages must only show offers that are trustworthy (decision D3). Today the public query ignores `offers.status`, marketplace status, seller status and age; admin-created offers are `draft` while imported ones are `published`; importer skips `last_checked_at` for unchanged rows.

**Files created:** `database/migrations/019_offer_status_and_freshness.sql`
**Files changed:** `app/Models/OfferModel.php`, `app/Controllers/AdminProductController.php`, `views/dashboard/products/edit.php`, `app/Core/ProductImporter.php` (protected, authorized).

**Migration 019 (idempotent):**
```sql
UPDATE offers SET status = 'published' WHERE status = 'draft';
UPDATE offers SET last_checked_at = updated_at WHERE last_checked_at IS NULL;
ALTER TABLE offers MODIFY COLUMN status varchar(20) DEFAULT 'published';
```

**`OfferModel`:**
1. Add `public static function activeCondition(): string` returning exactly this SQL fragment (aliases `o`, `s`, `v` are used by every caller):
```
o.status = 'published' AND o.in_stock = 1 AND s.status = 'published' AND (v.id IS NULL OR v.status = 'active') AND (o.last_checked_at IS NULL OR o.last_checked_at >= DATE_SUB(NOW(), INTERVAL 14 DAY))
```
2. Change `findPublicByProductId()` to use it (keep the joins and `ORDER BY o.price_idr ASC`). Also select `v.store_rating AS vendor_store_rating` in that query.
3. Add `public function findActiveById(int $offerId): ?array`: same joins, `WHERE o.id = ? AND ` + active condition, returns the offer row or `null`.
4. Add `public function countActive(): int` and `public function countStale(): int` (published offers, `in_stock = 1`, with `last_checked_at < DATE_SUB(NOW(), INTERVAL 14 DAY)`). These are used later by the dashboard.

**`AdminProductController::storeOffer`:** when inserting an offer also set `'status' => 'published'` and `'last_checked_at' => date('Y-m-d H:i:s')`.

**`views/dashboard/products/edit.php`:** in the offers table add two columns: **Status** and **Last checked** (show the datetime or `-`). Nothing else.

**`ProductImporter.php`:** in the branch that counts `skippedIdenticalOffers`, the offer is not touched, so `last_checked_at` stays old and the offer would expire after 14 days even though the scraper just confirmed it. Add a private method `touchOffer(int $offerId): void` (`UPDATE offers SET last_checked_at = ?, in_stock = 1 WHERE id = ?`) and call it in that branch when not in dry-run. Also set `in_stock = 1` in `updateOffer()` (an offer seen again is in stock).

**PO step after the task:** run migration 019.

**Manual test:**
1. Product with 2 offers → both still visible publicly after migration.
2. Set one offer's `last_checked_at` to 30 days ago in phpMyAdmin → it disappears from the public price table.
3. Set a marketplace to `draft` → its offers disappear; set back to `published`.
4. Import the same CSV twice → second run reports identical rows; `last_checked_at` of those offers updated to now.
5. Add an offer in the dashboard → shows `published` and a last-checked time.

## A4: Edit offers in the dashboard + protect click history

**Problem:** there is no way to edit an offer (only add or delete). Deleting and re-adding is the only way to change a price, and `offer_clicks` has `ON DELETE CASCADE`, so every correction **wipes that offer's click history**.

**Files changed:** `public/index.php` (routes), `app/Controllers/AdminProductController.php`, `app/Models/ClickModel.php`, `views/dashboard/products/edit.php`.

**Route (add inside the Products block, after the existing offer routes):**
`$router->addRoute('dashboard/products/(\d+)/offer/(\d+)/update', 'AdminProductController', 'updateOffer');`

**`AdminProductController::updateOffer(string $productId, string $offerId): void`** (follow the pattern of `storeOffer`: requireAdmin, POST check, CSRF):
- Load the offer; it must belong to the product, otherwise error + redirect.
- Editable fields: `price_idr` (`Validator::integer(..., 1)`), `affiliate_url` (`Validator::sanitize` 5000 + `Validator::url`), `in_stock` (checkbox), `status` (`Validator::inArray` of `draft`, `published`). Store and seller are **not** editable.
- Set `last_checked_at` to now. Flash success/error, redirect to the product edit page.

**`ClickModel`:** add `public function countByOfferId(int $offerId): int`.

**`AdminProductController::deleteOffer`:** if `countByOfferId() > 0`, do not delete; set `$_SESSION['error'] = 'This offer has click history. Set it to Draft or Out of stock instead.'` and redirect.

**View:** each offer row gets an **Edit** button that opens a Bootstrap modal (`data-bs-toggle="modal"`, no inline JS) with a form (CSRF hidden input) posting to the new route. Use a unique modal id per offer (`offer-edit-<id>`). Keep the existing delete confirm modal.

**Manual test:**
1. Edit an offer's price → saved; public page shows the new price.
2. Tick "out of stock" → offer disappears publicly.
3. Click the offer's Buy button on the public page (creates a click), then try to delete the offer in the dashboard → refused with the message.
4. Delete an offer with zero clicks → works.
5. Try to edit an offer id that belongs to another product (change the URL by hand) → "Offer not found".

## A5: Harden `/go/<offer_id>`

**Problem:** `PublicProductController::go()` redirects to any offer of a published product, even out-of-stock/draft/expired ones, logs crawler hits as clicks, and is not excluded from robots.

**Files changed:** `app/Controllers/PublicProductController.php`.

**Change `go()`:**
1. Use `OfferModel::findActiveById()`. If it returns `null`:
   - if the offer exists and its product is published → flash `$_SESSION['error'] = 'That offer is no longer available.'` and redirect to the product page (`/<category_slug>/<product_slug>`, load with `ProductModel::findByIdWithRelations`);
   - otherwise render the existing 404.
2. Add `private function isBot(string $userAgent): bool` (case-insensitive substring match against: `bot`, `crawl`, `spider`, `slurp`, `preview`, `facebookexternalhit`, `curl`, `wget`, `python-requests`, `headless`; an empty user agent counts as a bot). If it is a bot: **do not log**, still redirect.
3. Before redirecting send `X-Robots-Tag: noindex, nofollow` and `Cache-Control: no-store`.
4. Keep the 302 redirect and the existing hashing of the IP.

**Manual test:**
1. Click Buy on an active offer → redirect works, one new row in `offer_clicks`.
2. `curl -I <site>/go/<id>` (curl user agent) → redirect header present, **no** new click row, `X-Robots-Tag` present.
3. Mark the offer out of stock, open `/go/<id>` → back on the product page with the error message.
4. `/go/999999` → 404.

## A6: Fix `robots.txt` and add `sitemap.xml`

**Problems:** `public/robots.txt` disallows `/admin/` (the dashboard is `/dashboard`), points `Sitemap:` at another project's domain, references a `security.txt` that does not exist, and does not block `/go/`. There is no sitemap.

**Files created:** `app/Controllers/SitemapController.php`.
**Files changed:** `public/robots.txt`, `public/index.php`.

**`robots.txt`:** keep the per-bot structure, but in every section replace the disallow list with: `/dashboard`, `/login`, `/logout`, `/register`, `/member`, `/go/`, `/search`, `/wishlist/`. Replace the `Sitemap:` line with `Sitemap: https://REPLACE-ME/sitemap.xml` (the PO edits the domain by hand; the worker must not invent a domain). Remove the `Security:` line.

**Route** (before the wildcard routes): `$router->addRoute('sitemap\.xml', 'SitemapController', 'index');`

**`SitemapController::index()`:** send `Content-Type: application/xml; charset=UTF-8`. Base = `\App\Core\Dotenv::get('APP_URL', '')`; if empty, output a 500-style plain message "APP_URL is not set" with `http_response_code(500)`. URLs (all absolute, XML-escaped, with `<lastmod>` from `updated_at` where available):
- `/`, `/categories`, `/brands`
- each published category `/<slug>`
- each published brand `/brand/<slug>`
- each published product `/<category_slug>/<slug>` that has **at least one active offer** (decision D4). Use a single SQL query joining `offers`, `stores`, `vendors` with `OfferModel::activeCondition()`; add the needed method to `ProductModel` (`findAllPublishedWithActiveOffers(): array`).

**Manual test:**
1. Set `APP_URL` in `.env`, open `/sitemap.xml` → valid XML, absolute URLs.
2. A product with no active offer is absent; a draft category is absent.
3. `/robots.txt` shows the new paths and the placeholder sitemap line; replace the domain by hand.

---

# PHASE B: SEO output

Background: SEO fields are saved by the four admin controllers (`product`, `category`, `brand`, `store` in table `seo_meta`) but **nothing prints them**. Only the product page reads `seo_title` and `seo_description`. `views/layouts/header.php` prints only `<title>` and `description`. Category and brand controllers never load SEO. There is no public store page (decision D5).

## B1: SEO helper and layout output

**Files created:** `app/Core/Seo.php` (protected folder, authorized).
**Files changed:** `views/layouts/header.php`, `views/layouts/footer.php`.

**`App\Core\Seo` (all `public static`, with docblocks):**
- `absoluteUrl(string $path): string`: `APP_URL` (from `Dotenv::get('APP_URL', '')`, trailing slash trimmed) + path (ensure one leading slash). Return `''` if `APP_URL` is empty.
- `plainText(string $html, int $max): string`: `strip_tags`, decode entities, collapse whitespace, trim, cut to `$max` characters on a word boundary with `mb_*` functions.
- `cleanJsonLd(string $raw): string`: trim; return `''` if empty; `json_decode` it; if invalid return `''`; otherwise return `json_encode($decoded, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)`.

**`header.php` new optional variables** (default each with the existing `isset()` pattern): `$seo` (array or null), `$canonicalUrl` (string), `$ogImage` (string, absolute), `$ogType` (string, default `website`), `$robotsOverride` (string), `$jsonLd` (array of ready JSON strings, default `[]`).
Inside `<head>`, after the description meta, print:
1. `<meta name="robots">`: `$robotsOverride` if not empty, else `$seo['robots_tag']` if set and not empty, else `index, follow`.
2. `<link rel="canonical">` only when `$canonicalUrl` is not empty.
3. Open Graph: `og:title` (`$seo['og_title']` → `$seo['seo_title']` → `$pageTitle`), `og:description` (`og_description` → `seo_description` → `$metaDescription`), `og:type`, `og:url` (= canonical, only if not empty), `og:site_name` = `GudangPC`, `og:image` only if `$seo['og_image']` or `$ogImage` is not empty (the first one that is absolute; if `og_image` is a relative path convert with `Seo::absoluteUrl`).
4. Twitter: `twitter:card` = `summary_large_image` when an image exists, else `summary`; `twitter:title` (`twitter_title` → og title), `twitter:description` (`twitter_description` → og description).
5. For each string in `$jsonLd`: `<script type="application/ld+json" nonce="<?= $nonce ?>">` + string + `</script>`. Then, if `$seo['schema_markup']` is not empty and `Seo::cleanJsonLd()` returns something, print that too (only when `$jsonLd` has no product schema; see B4, for now just print both).
6. `$seo['custom_head_tags']` raw, if not empty.
Right after the opening `<body>` tag print `$seo['custom_body']` raw if not empty. In `footer.php`, just before `</body>` print `$seo['custom_footer']` raw if not empty.

**Escaping note for the worker:** every value above goes through `htmlspecialchars((string) ...)` **except** the JSON-LD strings and the three admin-authored HTML fields (`custom_head_tags`, `custom_body`, `custom_footer`). Add a one-line comment above each of those four unescaped outputs saying why.

**Do not** change controllers in this task.

**Manual test:** with no controller changes, every page still renders and `view-source` shows `robots` and `og:site_name`; no PHP notices.

## B2: Wire SEO into home, category, brand, product pages

**Files changed:** `app/Controllers/HomeController.php`, `PublicCategoryController.php`, `PublicBrandController.php`, `PublicProductController.php` (and the `index` methods of the category and brand lists).

For each controller pass these view variables (the views already include the layout, so nothing else to edit): `seo`, `canonicalUrl`, `ogImage`, and set `pageTitle` / `metaDescription` using this precedence:

| Page | meta type | Title | Description | `canonicalUrl` | `ogImage` |
|---|---|---|---|---|---|
| Home | none | keep current | keep current | `Seo::absoluteUrl('/')` | none |
| Category | `category` | `seo_title` else `<name> - Price Comparison \| GudangPC` | `seo_description` else `Seo::plainText(category description, 155)` else `Compare <name> prices across Indonesian marketplaces.` | `/<slug>` | category image |
| Brand | `brand` | `seo_title` else `<name> Products - Price Comparison \| GudangPC` | `seo_description` else `Seo::plainText(brand description, 155)` else `Compare prices for <name> products across Indonesian marketplaces.` | `/brand/<slug>` | brand logo |
| Product | `product` | `seo_title` else `<name> - Price Comparison \| GudangPC` | `seo_description` else `short_description` else `Compare prices for <name>.` | `/<category_slug>/<slug>` | product image |
| `/categories`, `/brands` | none | keep | keep | their own path | none |

Load SEO with `SeoMetaModel::findByMeta('<type>', <id>)` as the product controller already does. Product pages also pass `ogType` = `product`. Image values are stored as site-relative paths; convert with `Seo::absoluteUrl()`.

The product controller currently only uses `seo_title`/`seo_description`; add `'seo' => $seo` and the new variables to its `view()` call.

**Manual test:**
1. In the dashboard give a category an SEO title, description and robots `noindex, follow` → page source shows them.
2. Leave SEO empty on a brand → fallback title and description appear.
3. Product page source contains canonical (absolute, correct), `og:image`, `og:type` = `product`.
4. Put `{"@context":"https://schema.org","@type":"Brand","name":"X"}` into a product's schema field → appears as a JSON-LD script; put garbage → nothing printed, page still renders.

## B3: Canonical clean-up and product URL redirect

**Problems:** (a) the four admin controllers auto-fill `canonical_url` on save with `$base . '/' . ...`, which is usually a **relative** path and goes stale when a slug or category changes. (b) Product URLs contain the category slug; moving a product to another category makes the old URL a 404.

**Files created:** `database/migrations/020_clean_seo_canonical.sql`
**Files changed:** `AdminProductController.php`, `AdminCategoryController.php`, `AdminBrandController.php`, `AdminStoreController.php`, `PublicProductController.php`, and the SEO tab hint text in the product/category/brand/store `create.php` and `edit.php` views.

**Changes:**
1. In each admin controller's SEO collect method, remove the block that fills `canonicalUrl` when it is empty. Store exactly what the user typed (empty stays empty). The method signature of `collectSeoInput` in the product controller takes `$slug` and `$categorySlug` only for that block; keep the signature, stop using them for canonical (do not remove parameters).
2. Migration 020: `UPDATE seo_meta SET canonical_url = NULL WHERE canonical_url IS NOT NULL AND canonical_url NOT LIKE 'http%';`
3. Rendering rule (used by B2): the page's `$canonicalUrl` is the computed one unless `seo.canonical_url` starts with `http`, in which case that wins. Implement this in each public controller where B2 sets `canonicalUrl`.
4. In the SEO form views, change the canonical field hint to: "Leave empty to use the page's own URL. Only enter a full URL (https://...) to override."
5. `PublicProductController::show()`: when the product exists and is published but `$full['category_slug'] !== $categorySlug`, send a **301** to `/<correct_category_slug>/<slug>` (use `header('Location: ...', true, 301)` with the `$GLOBALS['base']` prefix, then `exit`) instead of the 404.

**PO step:** run migration 020.

**Manual test:**
1. Create a product, view source: canonical is absolute and correct.
2. Change its category in the dashboard; open the **old** URL → lands on the new URL (301), canonical matches the new URL.
3. Enter `https://example.com/x` as canonical on a brand → that value is printed.

## B4: Breadcrumbs and structured data

**Files changed:** `app/Core/Seo.php` (authorized), `PublicProductController.php`, `PublicCategoryController.php`, `PublicBrandController.php`, `views/public/products/show.php`, `views/public/categories/show.php`, `views/public/brands/show.php`.
**Files created:** `views/public/_breadcrumb.php`.

**`Seo` additions:**
- `breadcrumbJsonLd(array $crumbs): string`: `$crumbs` = list of `['label' => string, 'path' => string]` (last item may have an empty path). Returns BreadcrumbList JSON (absolute URLs via `absoluteUrl`; return `''` if `APP_URL` is empty).
- `productJsonLd(array $product, string $brandName, array $offers, string $path, string $imagePath): string`: `Product` with `name`, `description` (`plainText` of short description else description, 300), `image` (absolute, only if present), `brand` (`Brand` name, only if not empty), `mpn` = `model_number` (only if not empty), `url`, and `offers` as one `AggregateOffer` (`priceCurrency` `IDR`, `lowPrice`, `highPrice`, `offerCount`, `availability` `https://schema.org/InStock`). Return `''` if `$offers` is empty. **Never** emit `aggregateRating` or `review` (we have no first-party reviews; marketplace ratings must not be marked up).

**Controllers:** product page passes `jsonLd` = non-empty results of `productJsonLd` and `breadcrumbJsonLd` (Home › Category › Product). Category page: breadcrumb (Home › Category). Brand page: breadcrumb (Home › Brands › Brand). Also pass `breadcrumbs` (array for the visible partial).
**Partial `_breadcrumb.php`:** Bootstrap `breadcrumb` nav (`<nav aria-label="breadcrumb">`), items from `$breadcrumbs` (`label`, `url`; last item `active`, no link), all escaped. Include it at the top of the three views with `include`.
**Header rule:** when the product controller provides a product JSON-LD, the header must not also print `seo.schema_markup` for that page unless the user filled it (user value replaces the generated product schema). Adjust the B1 logic accordingly: custom `schema_markup` wins over generated Product schema; breadcrumbs always print.

**Manual test:**
1. Product page shows a breadcrumb and `view-source` has two JSON-LD blocks; paste the page into Google's Rich Results Test or schema.org validator: Product with offers and BreadcrumbList parse without errors.
2. Product with no active offers: no Product JSON-LD, breadcrumb only.
3. Fill a custom schema in the dashboard → it replaces the generated Product block.

---

# PHASE C: Browse experience

## C1: Navbar clean-up

**File:** `views/layouts/navbar.php` only.
- The `Products` link is `href="#"`. Keep the label `Products` and point it to `<?= $base ?>/categories`.
- The navbar contains `? :` ternaries (around the `Sign In` and `Register` links using `$requestUri`). Rewrite them with the "compute a variable first" pattern from `AGENTS.md`. Then run `php bin/check_rules.php views/layouts/navbar.php`.
- Do not change anything else.

**Manual test:** guest sees Sign In/Register once each; logged-in member sees Member Area; Products opens the categories page; active-link highlighting still works.

## C2: Remove the compare feature (decision D1)

**Files changed:** `public/index.php`, `views/home/index.php`, `views/public/categories/show.php`, `views/public/brands/show.php`, `views/public/search/index.php`.
**Files deleted (this task names and authorizes exactly these two):** `app/Controllers/PublicCompareController.php`, `views/public/compare/show.php`.

- Remove the `compare` route line.
- In the four views remove: the `compare-checkbox` block (`form-check` div with the checkbox and label), the `#compare-float` div, and the script block that reads `.compare-checkbox`. Keep the wishlist form and the "view" link. The home page has other inline `<script>` blocks (parallax): leave them.
- Search the whole project for the word `compare` in PHP/views and list anything left under `NOTICED` (the price table heading "Compare Prices" on the product page is fine).

**Manual test:** no checkbox or floating button anywhere; `/compare?ids=1,2` now returns 404; product page and wishlist still work.

## C3: Product card partial with prices

**Problem:** the product card HTML is copy-pasted in four views and shows **no price**: the single most important thing on an affiliate comparison card.

**Files created:** `views/public/_product_card.php`.
**Files changed:** `app/Models/ProductModel.php`, `views/home/index.php`, `views/public/categories/show.php`, `views/public/brands/show.php`, `views/public/search/index.php`.

**`ProductModel`:** in `getPublishedWithRelations`, `findAllByCategorySlug`, `findAllByBrandSlug`, `search`, add two selected columns via correlated subqueries (use `OfferModel::activeCondition()` string concatenated into the SQL; it is a constant fragment, not user input):
```sql
(SELECT MIN(o.price_idr) FROM offers o
   INNER JOIN stores s ON s.id = o.store_id
   LEFT JOIN vendors v ON v.id = o.vendor_id
   WHERE o.product_id = p.id AND <activeCondition>) AS lowest_price,
(SELECT COUNT(*) FROM offers o
   INNER JOIN stores s ON s.id = o.store_id
   LEFT JOIN vendors v ON v.id = o.vendor_id
   WHERE o.product_id = p.id AND <activeCondition>) AS offer_count
```
(`lowest_price` is NULL when there is no active offer.) Do not change the WHERE or ORDER BY of these methods yet.

**Partial `_product_card.php`:** expects `$product`, `$base`. Shows: image (`loading="lazy"`, alt from `$product['name']`) or placeholder icon, brand name, product name, then:
- if `offer_count > 0`: `From Rp <number_format(lowest_price, 0, ',', '.')>` in bold and a small muted line `N seller(s)`;
- else: muted `No active offers` and add class `product-card-dim` to the card root.
Then the link button (to `/<category_slug>/<slug>`) and the existing wishlist heart form (keep CSRF hidden input). Add `.product-card-dim { opacity: .6; }` to `public/assets/css/frontend.css` (this task authorizes that one CSS rule).

**Views:** replace the card markup inside each `foreach` with `<?php include __DIR__ . '/../_product_card.php'; ?>` (home: `__DIR__ . '/../public/_product_card.php'`). It **must be `include`, not `require_once`**, because it runs once per product.

**Manual test:**
1. Home, category, brand and search pages show "From Rp ..." and seller count.
2. A product with only expired offers shows "No active offers" dimmed.
3. Page source: no duplicated inline styles from the old cards; cards look the same as before.

## C4: Pagination and sorting (category, brand, search)

**Files created:** `views/public/_pagination.php`.
**Files changed:** `ProductModel.php`, `PublicCategoryController.php`, `PublicBrandController.php`, `PublicSearchController.php`, the three matching views.

- `ProductModel`: add `$limit`/`$offset` and `$sort` parameters to `findAllByCategorySlug`, `findAllByBrandSlug`, `search`; add `countByCategorySlug(string $slug): int`, `countByBrandSlug(string $slug): int`, `countSearch(string $query): int` (published products only). Callers outside these controllers must keep working: give the new parameters defaults (`limit` 20, `offset` 0, `sort` `'newest'`).
- `sort` whitelist via `Validator::inArray`: `newest` (default, `p.id DESC`), `price_asc`, `price_desc`. For price sorts, products with `lowest_price IS NULL` always go last. Use a fixed mapping from whitelist value to SQL text (never concatenate the raw GET value).
- Controllers read `page` (int, min 1) and `sort` from `$_GET`, pass `page`, `totalPages`, `sort`, `total` to the view. `page > totalPages` (and `page > 1`) → 404.
- Partial `_pagination.php`: Bootstrap pagination; expects `$page`, `$totalPages`, `$pagePath` (e.g. `/gpu`), `$pageParams` (array of other query params to keep, e.g. `q`, `sort`). Hidden when `totalPages <= 1`. All values escaped.
- Views: a small sort `<select>` form (GET, no inline JS: a normal submit button labelled "Sort") above the grid; the pagination below it.
- Canonical for page N > 1 is the page URL with `?page=N` and **without** `sort`; page 1 canonical has no params.

**Manual test:** with more than 20 products in one category: page 1 shows 20, `?page=2` shows the rest, `?page=99` is 404; sort by price ascending puts cheapest first and no-offer products last; search keeps `q` across pages.

## C5: Better search

**Files changed:** `ProductModel.php` (`search`, `countSearch`), `PublicSearchController.php`, `views/public/search/index.php`.

Today the whole query must appear as one contiguous string in name/description/tags: `asus rtx 4060` finds nothing if the product is "RTX 4060 ASUS Dual", and `%`/`_` in the query act as wildcards.
- Split the sanitized query on whitespace; keep at most 6 tokens. For each token add `(p.name LIKE ? OR p.model_number LIKE ? OR p.search_tags LIKE ? OR b.name LIKE ? OR p.short_description LIKE ?)` with `ESCAPE '\\'`; **AND** the groups. Escape `\`, `%`, `_` in each token before wrapping with `%...%`.
- The view prints `Search results for: <query>` with a result count; for an empty query show links to the categories page instead of a blank page; for no results show "No products found" plus the categories link.
- Pass `robotsOverride` = `noindex, follow` and `canonicalUrl` = `''` (search pages are not indexed).
- Do **not** touch `SearchCacheModel` or the `search_cache` table (unused; listed in NOTICED).

**Manual test:** `rtx asus` finds "ASUS RTX 4060 ..."; `50%` does not match everything; `<script>` in the query is shown escaped; page source has `noindex`.

## C6: Homepage: latest products per category

**Files changed:** `HomeController.php`, `ProductModel.php`, `BrandModel.php`, `views/home/index.php`.

1. **Remove** the call to `getPublishedWithRelations()` in `HomeController` (it loads every product and then slices 8).
2. `ProductModel::findLatestActiveByCategoryId(int $categoryId, int $limit): array`: published products of that category with at least one active offer (`EXISTS` subquery with `OfferModel::activeCondition()`), `ORDER BY p.id DESC LIMIT ?` (bind as int), same selected columns as the list queries including `lowest_price`/`offer_count`.
3. `BrandModel::findPublishedWithProducts(int $limit): array`: published brands that have at least one published product, ordered by name.
4. `HomeController::index()`:
   - `$categories` = `findAllPublished('sort_order ASC')` (first 8 for the tile row).
   - `$categorySections` = for the first 6 categories in that list, a section only when `findLatestActiveByCategoryId($id, 8)` returns at least 1 product: `['category' => row, 'products' => rows]`.
   - `$brands` = `findPublishedWithProducts(12)`.
5. View: keep the hero and the category tiles. Replace the single "Latest Products" block with one block per `$categorySections` entry: heading `Latest in <category name>`, a "View all" link to `/<slug>`, the cards from `_product_card.php`. After the sections, a **brands strip** (logo if present, else name; link to `/brand/<slug>`). Fix the three hero `<img src="/uploads/images/...">` to use `<?= $base ?>/uploads/images/...`.
5b. **Part 2 (only if Part 1 is accepted):** "Most clicked this week" row. `ProductModel::findTopClicked(int $days, int $limit): array` joins `offer_clicks` to `offers` to `products` (published, with an active offer), `WHERE clicked_at >= DATE_SUB(NOW(), INTERVAL ? DAY)`, `GROUP BY p.id ORDER BY COUNT(*) DESC`. Show the row between the tiles and the category sections **only if it returns 4 or more products**.

**Manual test:** home shows a "Latest in ..." block for each category that has products with active offers and hides empty categories; no block shows a product without a price; page loads without fetching all products (check the query count if you have a debug bar, or just confirm no slowdown with 100+ products); hero images use the base path.

## C7: Product page polish

**Files changed:** `PublicProductController.php`, `ProductModel.php`, `views/public/products/show.php`.

Today offers are grouped per marketplace in a narrow column, so the cheapest offer is not obvious, and scraped data (seller title, ratings, sold count, last checked) is never shown.

1. **Layout:** left column (`col-lg-8`): product header card, then a **"Compare prices"** card with the offers table, then the description card. Right column (`col-lg-4`): a summary card: `From Rp X`, `N sellers`, `Updated <relative time>`, a primary **Buy** button for the cheapest offer (`/go/<id>`, `target="_blank"`, `rel="sponsored nofollow noopener"`), the wishlist control, and the disclosure line.
2. **Offers table** (flat list, ascending price, no grouping): Marketplace name, Seller name, listing title (`seller_title`, one line, truncated with CSS class), seller rating (`vendor_store_rating` if present, else `vendor_rating`), product rating (`product_rating`), sold count (`1,2rb`-style is out of scope: print `<n> sold`), price, Buy button. A **"Lowest price"** badge on the first row. Show the first 10 rows; if there are more, the rest sit in a Bootstrap `collapse` with a "Show all N offers" button. Relative time helper: compute in the view's PHP block (`just now`, `N hours ago`, `N days ago`) from `last_checked_at`.
3. **No active offers:** show "No active offers right now." in the card; keep the wishlist control.
4. **Wishlist state:** controller passes `inWishlist` (bool): true only for a logged-in member whose session role is `visitor` and `WishlistModel::exists()` is true. The control shows "Remove from wishlist" (POST to `/wishlist/remove/<id>`) or "Save to wishlist" (POST to `/wishlist/add/<id>`), both with CSRF. `WishlistController::remove` currently redirects to `/member`; leave it.
5. **Related products:** `ProductModel::findRelated(int $categoryId, int $excludeId, int $limit): array` (published, same category, at least one active offer, `ORDER BY p.id DESC`). Show up to 4 with `_product_card.php` under the description, heading "Related products", hidden when empty.

**Manual test:** cheapest offer is first with the badge; Buy goes through `/go/<id>`; 12 offers → 10 visible + "Show all 12 offers"; logged-in member sees the correct save/remove state; product with no offers shows the message; related products appear on products that have siblings.

---

# PHASE D: Import engine

Background: the importer works as a first version but: it is **not transactional**, `vendor_slug` must be filled by hand (the scraper deliberately leaves it blank and writes `vendor_name`, which the importer ignores), `image_url` and `notes` columns from the scraper are ignored, the "affiliate_url" is just the raw marketplace URL (no commission link), offers are matched only by `(product, vendor)`, and there is no price history.

## D1: Migration for listing identity and affiliate templates

**Files created:** `database/migrations/021_offer_listing_identity_and_affiliate_template.sql`. SQL only; no PHP.

Use the idempotent `information_schema` check style of `014_add_indexes.sql` (or `ADD COLUMN IF NOT EXISTS` as in 009/017/018):
```sql
ALTER TABLE offers
  ADD COLUMN IF NOT EXISTS source_url text DEFAULT NULL AFTER affiliate_url,
  ADD COLUMN IF NOT EXISTS external_id varchar(100) DEFAULT NULL;
-- unique per marketplace listing; NULL external_id (legacy rows) is allowed many times
-- add UNIQUE KEY offers_store_external (store_id, external_id) only if it does not exist
ALTER TABLE stores
  ADD COLUMN IF NOT EXISTS affiliate_url_template varchar(500) DEFAULT NULL;
```

**PO step:** run it. **Manual test:** `DESCRIBE offers; DESCRIBE stores;` show the new columns; existing pages still work.

## D2: Affiliate template field on marketplaces

**Files changed:** `AdminStoreController.php`, `views/dashboard/stores/create.php`, `views/dashboard/stores/edit.php`.

- New optional field `affiliate_url_template` (max 500). Validation: empty, or starts with `https://` and contains at least one placeholder from `{url}` (raw product URL) or `{url_encoded}` (URL-encoded raw product URL) or `{external_id}`. Anything else: error "Template must start with https:// and contain {url}, {url_encoded} or {external_id}."
- Add the helper text under the input: "Example: `https://affiliate-network.example/deeplink?to={url_encoded}&sub=gudangpc`. Leave empty to use the raw product URL."
- Wire it into create/update, the `old` repopulation and the edit form following the pattern already used for other store fields.

**Manual test:** save a store with a valid template, reload the edit page → value shown; invalid template → error and the form keeps what you typed.

## D3: Importer hardening

**Files changed:** `app/Core/ProductImporter.php` (protected, authorized), `app/Controllers/AdminProductImportController.php`, `views/dashboard/products/import.php`, and `bin/import_products.php` only if it reads report keys that change (read it first).

Implement all of the following inside `ProductImporter` (read the whole file before editing):

1. **Transaction:** `importFile` / `importRows` for a real run (not dry-run): `beginTransaction()` before the row loop, `commit()` after, and on any exception `rollBack()` then rethrow. Row-level rejections do **not** abort; they stay in the report.
2. **New CSV columns** (all optional): `source_url`, `external_id`. Header check still requires only `product_slug`.
3. **Required offer fields** become: `marketplace`; (`vendor_slug` **or** `vendor_name`); `price_idr`; (`affiliate_url` **or** `source_url`). Adjust the "offer fields filled" logic so a row that has only product columns is still a product-only row, and a partially filled offer row is still rejected as `incomplete offer data`.
4. **Seller auto-create:** if `vendor_slug` is empty and `vendor_name` is not: slug = lowercase `a-z0-9-` of `<marketplace>-<vendor_name>`; find by slug, else insert into `vendors` (`store_id`, `name`, `slug`, `status` = `active`, and `store_rating` when given). Count `created_vendors` in the report. If `vendor_slug` is given but unknown, keep today's rejection `unknown vendor`.
5. **Affiliate URL resolution:** if `affiliate_url` is empty, take the marketplace's `affiliate_url_template` (add a lookup by store id), replace `{url}`, `{url_encoded}` (`rawurlencode`), `{external_id}` using `source_url` / `external_id`. If the store has no template, use `source_url` and add a **warning** `no affiliate template for <marketplace>` (once per marketplace per run). Always store `source_url` on the offer when given.
6. **Matching offers:** if `external_id` is given, look the offer up by `(store_id, external_id)` first; if found and its `product_id` differs from the row's product, reject with `listing already linked to another product`. Otherwise fall back to the existing `(product_id, vendor_id)` lookup. If an offer is found by `(product_id, vendor_id)` and **both** the stored and the CSV `external_id` are non-empty and different, reject with `seller already has another listing for this product`. On update, store `external_id` and `source_url` when the offer had none.
7. **Scraper notes:** if the `notes` column exists and its value is not empty and not `ok` (case-insensitive), reject the row with `scraper note: <value>`.
8. **Price sanity warning:** on an update where the new price differs from the stored price by more than 50 % in either direction, still update, but add a warning `large price change: <slug> <old> -> <new>`.
9. **Report:** add `created_vendors` (int) and `warnings` (list of strings) to the returned array; keep every existing key.

`AdminProductImportController` and `import.php`: show `created_vendors`, and a Warnings card (only when not empty) listing the warnings. Keep the existing rejection table.

**Manual test** (use a copy of a real scraper CSV and `bin/scraper/test_import.csv`):
1. Row with `marketplace`, `vendor_name` only (no `vendor_slug`) imports and creates the seller.
2. Row with `notes` = `blocked_captcha` is rejected with that reason.
3. Break the file on purpose halfway (cause an exception, e.g. a column count the code cannot handle, or ask the worker for a safe way to simulate it) → nothing from that run is in the database.
4. A marketplace without a template shows the warning; after saving a template in D2, the stored `affiliate_url` follows the template.
5. Change a price by 60 % in the CSV → updated, warning listed.
6. Same `external_id` pointing at a different `product_slug` → rejected.

## D4: Price history

**Files created:** `database/migrations/022_create_price_history.sql`, `app/Models/PriceHistoryModel.php`.
**Files changed:** `ProductImporter.php` (authorized), `AdminProductController.php` (`storeOffer`, `updateOffer`).

- Migration: table `price_history` per `docs/PRODUCT_SPEC.md` (`id`, `offer_id` with `ON DELETE CASCADE`, `price_idr bigint unsigned`, `recorded_at datetime`, index on `(offer_id, recorded_at)`), then a one-time backfill: `INSERT INTO price_history (offer_id, price_idr, recorded_at) SELECT id, price_idr, COALESCE(last_checked_at, updated_at) FROM offers;` (the PO runs the file **once**).
- `PriceHistoryModel`: `protected string $table = 'price_history'; protected bool $timestamps = false;` and `record(int $offerId, int $priceIdr): bool`.
- Record a row when an offer is **created** and when its price **changes** (importer create + update branches, not in dry-run; `storeOffer`; `updateOffer` only if the price changed). Do not record identical prices.
- Nothing is displayed yet.

**Manual test:** import a changed price → one new `price_history` row; edit a price in the dashboard → one new row; save an offer without changing the price → no new row.

## D5: Scraper update (`bin/scraper/scrape.py`, README)

**Files changed:** `bin/scraper/scrape.py`, `bin/scraper/README.md`. No new Python packages.

- Add two output columns: `source_url` (the page URL that was scraped) and `external_id`. Extract `external_id` only when the URL matches one of these rules, otherwise leave it empty: Shopee `...-i.<shopid>.<itemid>` → `<shopid>.<itemid>`; Tokopedia: if the last path segment ends with a run of 15 or more digits, that run; Lazada: `-i<digits>` → `<digits>`. (Do not invent other rules.)
- `affiliate_url` stays empty unless the targets file supplies it. **Stop** copying the raw URL into `affiliate_url` (the importer now builds it from the marketplace template and `source_url`).
- Keep `vendor_name`, `notes`, `image_url` and all existing columns. The five human columns in the README stay human, except `vendor_slug`, which is now optional because the importer creates sellers from `vendor_name`.
- Update the README: new columns, the explanation above, and a rule: "Clear the `notes` cell once you fixed a row by hand, otherwise the importer rejects it."

**Manual test:** run the scraper on 3 links; CSV has `source_url` for all rows and `external_id` for the Shopee/Tokopedia ones; `affiliate_url` is empty; importing it works with D3.

---

# PHASE E: Visibility

## E1: Dashboard overview and click analytics

**Files created:** `app/Controllers/AdminClickController.php`, `views/dashboard/clicks/index.php`.
**Files changed:** `public/index.php`, `DashboardController.php` (`index` only), `views/dashboard/index.php`, `views/dashboard/_sidebar.php`, `ClickModel.php`, `ProductModel.php`, `OfferModel.php` (uses the A3 methods).

- Route: `dashboard/clicks` → `AdminClickController::index` (`requireAdmin()`); sidebar link "Click analytics" for admin and root, highlight with `currentPage` = `clicks`.
- `ClickModel`: `countSince(int $days): int`; `topProducts(int $days, int $limit): array` (product name, category slug, product slug, click count); `topMarketplaces(int $days, int $limit): array` (store name, click count); `topReferers(int $days, int $limit): array` (referer host + count; extract the host in PHP with `parse_url`, group in PHP, `(direct)` for empty); `dailyCounts(int $days): array` (date + count).
- Clicks page: `days` filter via whitelist 7 / 30 / 90 (default 7); cards for total clicks; tables for top 10 products, top marketplaces, top referrers, and the daily list. No chart yet.
- Dashboard overview replaces the placeholder text with six counters: published products, draft products, published products **without** an active offer, active offers (`OfferModel::countActive`), stale offers (`countStale`), clicks in the last 7 days; plus a "Top 5 products this week" table.
- `ProductModel`: `countByStatus(string $status): int` and `countPublishedWithoutActiveOffer(): int`.

**Manual test:** click a few Buy buttons, open `/dashboard/clicks` → numbers match `offer_clicks`; bot clicks (A5) are absent; counters on `/dashboard` agree with what you see in the product list; a member (non-admin) cannot open the clicks page.

## E2: Dashboard product list: search, filters, pagination

**Files changed:** `AdminProductController.php` (`index`), `ProductModel.php`, `views/dashboard/products/index.php`.

- `ProductModel::getAdminList(array $filters, int $limit, int $offset): array` and `countAdminList(array $filters): int`. Filters (all optional, from GET): `q` (name, slug or model number, escaped LIKE), `status` (`draft`/`published`, whitelist), `offers` (`with`, `without`: has / lacks an **active** offer, via `OfferModel::activeCondition()`), `category_id` (int).
- Extra columns in the list: offers count (all offers), lowest active price, last checked (max `last_checked_at`), and a **Stale** badge when the product has offers but none is active.
- 20 per page, `?page=N`, reuse `views/public/_pagination.php` or the same markup (keep GET params).
- Keep the existing Edit/Delete buttons and delete modal.

**Manual test:** with 50+ products: pagination works, filters combine, "without active offer" lists exactly the products that show "No active offers" publicly.

---

# Parked (do not build yet; PO will write tasks after the Phase D recheck)

1. **Import review queue:** unmatched scraped rows land in a staging table; staff match each row to a product with a search box; the listing (`store_id` + `external_id`) is remembered so the next scrape matches automatically. This removes the manual "fill five columns" step and is the biggest time saver once volume grows.
2. **Image mirroring:** download and convert the scraper's `image_url` (signed marketplace URLs expire) and decide whether using seller images is acceptable; official brand images are the safer source.
3. **Specs and compare:** `spec_definitions` per category, spec entry on products, then compare within one category only.
4. **Price chart and price-drop alerts** for members (needs D4 data and a way to reach members, e.g. WhatsApp).
5. **Indonesian UI** and Indonesian meta titles (`<html lang="id">`).
6. **Public marketplace/seller pages.**
7. **Member onboarding:** decide between admin activation (today), automatic activation, or WhatsApp verification.
8. **A migration runner** (`bin/migrate.php` + a `migrations` table); today migrations are run by hand and migration 012 depends on constraint names.
9. **Caching** of the homepage queries.

# NOTICED list (known, deliberately not fixed in these tasks)

- `SearchCacheModel` and the `search_cache` table / `products.search_tags` intent parsing: `SearchCacheModel` is never used.
- `app/Models/HomeModel.php` points to a table `home` that does not exist and is unused.
- `AuthController::verifyEmail` and the `verify-email/<token>` route are dead: no email is ever sent.
- `public/test-hero.html`, `bin/test-speed.js`, `bin/test-speed.php`: test files that should not be deployed to production.
- `public/index.php` sets `display_errors=1` at the very top; `FrameworkHeader.php` later switches it off when `APP_ENV=production`. Make sure `.env` really has `APP_ENV=production` on the live server.
- Category deletion does not check child categories (`parent_id`), and `parent_id` is not used anywhere on the public site.
- `vendors.status` uses `active` while other tables use `published`; kept on purpose (see T0.1).
- Reserved words are not blocked as category slugs (a category called `search`, `brands`, `member`, `login` would be shadowed by the fixed routes).
