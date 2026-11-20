# GudangPC: Product Specification

Owner: the PO. This document is the source of truth for **what** is being built.
`AGENTS.md` says **how** to code. If the two conflict, stop and ask.
The worker must not create any table, route, or feature that is not listed for the current task.

---

## 1. What this product is

GudangPC is an Indonesian price-comparison and affiliate website for PC parts and tech gear (CPU, GPU, motherboard, RAM, SSD, PSU, case, cooler, monitor, peripherals). The dashboard is English and public pages are Indonesian (decision D8).

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

Not a shop: no cart, checkout, payments, stock management, or first-party reviews. No email is sent by the application. Sellers have public pages at `/seller/<slug>`; marketplaces do not (decision D5).

---

## 2. Vocabulary

| Term | Meaning |
|---|---|
| **Marketplace** | A row in `stores` (Tokopedia, Shopee, Lazada, ...). The word "store" in table and class names means marketplace. |
| **Seller** | A row in `vendors`. Every seller belongs to exactly one marketplace (`vendors.store_id`). |
| **Location** | A row in `locations`; a city or area where sellers are based. |
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
| D5 | Marketplaces still have no public pages. Sellers now have public pages at `/seller/<slug>`. Marketplace and seller SEO records are still not rendered publicly; seller pages build their title and description from the seller's name and description. |
| D6 | The unique rule `(product_id, vendor_id)` on `offers` stays. A second listing from the same seller for the same product is **reported**, not merged. |
| D7 | Affiliate links are built from a per-marketplace **template** (`stores.affiliate_url_template`) applied to the raw product URL. If no template exists, the raw URL is used and the import report warns. |
| D8 | The dashboard language is English; public pages are Indonesian. |
| D9 | The canonical URL is **computed at render time** from `APP_URL`. A stored `seo_meta.canonical_url` is honoured only if it is absolute (starts with `http`). |
| D10 | Google sign-ins verify and clear OAuth state, verify TLS with a 10-second timeout, require a verified email, create new members as `active`, and activate a pending email member with a random password. |

D2 is not a change: the wishlist stays, and members still register as `pending` until a `root` administrator activates them.

---

## 7. Global rules for all modules

- **Money:** Rupiah stored as an unsigned integer in `price_idr`. No decimals, no floats. Display with `number_format($price, 0, ',', '.')` and the prefix `Rp `.
- **Slugs:** lowercase `a-z`, `0-9` and `-`; unique per table; created from the name; not changed automatically on rename.
- **Charset:** `utf8mb4`, collation `utf8mb4_unicode_ci`, engine InnoDB, except the legacy `users` table which is `utf8mb4_general_ci` in `initial_database.sql`.
- **Timestamps:** every table has `created_at` and `updated_at`, except `offer_clicks` (only `clicked_at`), `wishlists` and `search_cache` (only `created_at`), and `price_history` (only `recorded_at`).
- **Affiliate links:** public pages never print an affiliate URL directly. They link to `/go/<offer_id>`. Outbound links use `target="_blank"` and `rel="sponsored nofollow noopener"`. Pages with offers show the disclosure line: "Kami dapat memperoleh komisi dari pembelian yang dilakukan melalui tautan kami." Manual entry accepts only `http://` and `https://` URLs, the same as CSV import. Offers have status `draft` or `published`, editable in the offer edit form.
- **Offer visibility:** only active offers (section 5) are shown publicly.
- **Output safety:** all dynamic output is escaped. Rich descriptions are printed only after `\App\Core\RichText::clean()`.
- **Deleting content:** a category cannot be deleted while products reference it. A marketplace cannot be deleted while offers or sellers reference it. A brand is not blocked; products pointing at it get `brand_id = NULL`. A location cannot be deleted while sellers reference it. Products and offers are hard-deleted from the dashboard after a confirm. An offer that already has click history must not be deleted; set it to Draft or Out of stock instead (task A4).
- **Pagination:** public lists paginate at 20 per page with `?page=N`. The admin product list paginates at 20 per page.
- **Language:** the dashboard is English; public pages are Indonesian. Page titles and meta descriptions on public pages are Indonesian unless an SEO record overrides them.

### Security and configuration

- Errors are displayed only when `APP_ENV` is exactly `local` or `development`; otherwise they are hidden and logged to `storage/logs/php_errors.log`.
- `APP_SALT` must be set to a long random string. If it is missing, empty or `change-me`, `/go/<offer_id>` still redirects and logs the click but stores an empty `ip_hash`.
- The vhost `DocumentRoot` must be the `public/` folder. A root `.htaccess` exists only as a fallback that blocks project internals.
- The CSP has no `report-uri` or `Report-To`; no `/csp-report` endpoint exists.
- The "SEO Defaults" link and settings are root-only.

---

## 8. Data model (as built today)

Existing tables and columns. The install schema is `database/schema.sql`, which includes migrations up to 025; migration files 001 to 025 are not kept in the repository.

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

### `locations`

```
id, name, slug, created_at, updated_at
PRIMARY KEY (id), UNIQUE (slug), UNIQUE (name)
```

`locations` has no `status` column.

### `vendors` (sellers)

```
id, store_id, location_id, name, slug, rating, store_rating, status,
description, image, created_at, updated_at
PRIMARY KEY (id), UNIQUE (slug), KEY (store_id), KEY (location_id)
FOREIGN KEY (store_id) REFERENCES stores (id)
FOREIGN KEY (location_id) REFERENCES locations (id) ON DELETE RESTRICT
```

`status` default is `active`. `rating` and `store_rating` are decimal ratings carried from marketplace data; the importer updates `store_rating` from the scrape. `description` is plain text, up to 1000 characters. `image` `varchar(255)` NULL was added by migration 025 and is written only by `bin/create_vendor_placeholder.php`; the vendor forms, seller page and seller card do not use it yet.

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

The structure-only schema is `database/schema.sql`, a `mysqldump --no-data` export taken after migration 025; migrations 023 to 025 are already included in it. New migrations start at 026. `database/initial_database.sql` does not contain the catalog tables. The old data-bearing dump was removed from the repository because it contained INSERT statements for `search_cache` and `users`.

**Migration 022** is applied. The `price_history` table, its index `idx_price_history_offer_recorded` and its foreign key to `offers` (`ON DELETE CASCADE`) exist in the current dump:

- `price_history`: `id`, `offer_id` (`ON DELETE CASCADE`), `price_idr bigint unsigned`, `recorded_at datetime`;
- index on `(offer_id, recorded_at)`; no `created_at` / `updated_at`.

**Migrations 023, 024 and 025** are applied. Migration 023 creates the `locations` table; migration 024 adds `vendors.location_id` (FK to `locations.id`, `ON DELETE RESTRICT`) and `vendors.description` `varchar(1000)` NULL; migration 025 adds `vendors.image` `varchar(255)` NULL. All three are included in `database/schema.sql`. The old plan for a `vendor_stores` join table was not built and remains parked.

New migrations are applied with `php bin/migrate.php` using the `status`, `up` and `baseline` commands. The runner records applied filenames in the `schema_migrations` table (`filename` varchar(190) primary key, `applied_at` timestamp default current_timestamp). The worker never runs the runner; the PO does.

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
| `/locations` | Store locations: sellers grouped by location |
| `/brand/<slug>` | Brand page |
| `/seller/<slug>` | Active seller page |
| `/<category-slug>` | Category page |
| `/<category-slug>/<product-slug>` | Product detail page |
| `/search?q=` | Search results |
| `/search/<slug>` | Structured search result page |
| `/go/<offer_id>` | Logs the click, then 302 redirects to the offer's affiliate URL |
| `/register` | Member registration form (GET) and `/register/submit` (POST) |
| `/verify-email/<token>` | Email verification link |
| `/login` | Sign-in form (GET) and `/login/submit` (POST); `/logout` (POST) |
| `/auth/google/redirect` | Start Google sign-in |
| `/auth/google/callback` | Google sign-in callback |
| `/auth/facebook/redirect` | Start Facebook sign-in |
| `/auth/facebook/callback` | Facebook sign-in callback |
| `/member` | Member Area |
| `/member/profile` | Own member profile |
| `/wishlist/add/<id>` | Add a product to the wishlist (member only) |
| `/wishlist/remove/<id>` | Remove a product from the wishlist (member only) |
| `/sitemap.xml` | XML sitemap |
| `/about` | About page |
| `/how-it-works` | How it works page |
| `/contact` | Contact page |
| `/privacy` | Privacy policy |
| `/terms` | Terms of service |
| `/disclosure` | Affiliate disclosure |

### Dashboard (login required; admin or root)

| URL | Module |
|---|---|
| `/dashboard` | Overview |
| `/dashboard/profile` | Own staff profile |
| `/dashboard/admins[...]` | Staff accounts (root only) |
| `/dashboard/admins/<id>/verify` | Mark a staff account verified (root only) |
| `/dashboard/admins/<id>/unverify` | Remove staff verification (root only) |
| `/dashboard/members` | Members; `/activate` and `/ban` actions (root only) |
| `/dashboard/categories[...]` | Categories |
| `/dashboard/brands[...]` | Brands |
| `/dashboard/stores[...]` | Marketplaces |
| `/dashboard/vendors[...]` | Sellers |
| `/dashboard/locations[...]` | Locations |
| `/dashboard/products[...]` | Products; the product edit page contains the offer forms, which post to separate offer routes for add, update and delete; the product create page accepts an optional first offer |
| `/dashboard/products/<id>/offer/store` | Add an offer from the product edit form |
| `/dashboard/products/<id>/offer/<offer_id>/update` | Update an offer from the product edit form |
| `/dashboard/products/<id>/offer/<offer_id>/delete` | Delete an offer from the product edit form |
| `/dashboard/products/import` | CSV import form; `/store` handles the upload |
| `/dashboard/settings` | Settings |
| `/dashboard/settings/update` | Save SEO defaults (root only) |
| `/dashboard/clicks` | Click analytics |
| `/dashboard/search` | Search analytics |
| `/dashboard/search/<id>/toggle` | Toggle a search query's sitemap inclusion |

`[...]` means the standard CRUD pattern from `AGENTS.md`: list, `create`, `store`, `(<id>)/edit`, `(<id>)/update`, `(<id>)/delete`.

Staff can enter the first offer while creating a product via the optional "First offer" block (vendor, price, affiliate URL, in stock). If the block is empty no offer is created. If the product is created but the offer fails, the product stays and staff land on the edit page with an error.

The seller page at `/seller/<slug>` is available only for active vendors. It shows the seller name, marketplace, rating (`store_rating` preferred, else `rating`), location, escaped description with line breaks kept, and a 20-per-page grid (`?page=N`) of published products that have an active offer from that seller. The card price is that seller's lowest active price; the seller count is the total active offers for the product. Unknown or inactive slugs return 404. Seller pages have no `seo_meta` records and no sitemap entry; their title and description come from the seller's name and description. "seller" is a reserved slug.

The homepage has "Toko di <location>" sections after the per-category sections and before "Merek": at most 4 locations (most eligible sellers first, then name), each with at most 4 seller cards (most eligible products first, then name). An eligible seller is active, has a location and has at least one published product with an active offer from that seller; sellers without a location do not appear.

On the product page the seller name in both Compare Prices tables links to `/seller/<slug>` when a vendor exists, and the seller's location is printed under the name when set.

Maintenance scripts in `bin/` create placeholder WebP images for categories (`create_category_placeholder.php [slug]`), stores (`create_store_placeholder.php [slug]`) and vendors (`create_vendor_placeholder.php [slug]`), while `fill_product_images_from_categories.php` copies a category image into imageless products. They write files under `public/uploads/<categories|stores|vendors>/` and set the image column only when it is empty; they never overwrite. They are one-time database updates, not runtime fallbacks, and require PHP GD with WebP support and `public/assets/fonts/Poppins-Bold.ttf`. Usage details are in `docs/CATEGORY_PLACEHOLDER_HOWTO.md`.

`/locations` lists locations that have at least one eligible seller, ordered by seller count descending then location name; each section has a "(N Toko)" heading and cards for all its eligible sellers, with no pagination. The page uses two queries, is linked from the navbar as "Lokasi Toko", is not in the sitemap and has no `seo_meta`; "locations" is a reserved slug.

A read-only smoke test, `php bin/smoke.php <base-url>`, runs GET-only checks against a deployed site for expected status codes, forbidden paths, security headers and Indonesian public text after each deploy.

---

## 10. Roadmap

The PO splits each phase into small tasks. The worker only ever sees the current task.

| Phase | Tasks | Purpose |
|---|---|---|
| 0 | T0.1 | Make this spec match reality |
| A | A1 to A6 (A4 done, A6 done) | Bugs and security: member registration, description output, one active-offer definition with freshness, offer editing with protected click history, hardened `/go/`, `robots.txt` and `sitemap.xml` |
| B | B1 to B4 | SEO output: helper and layout tags, wiring into pages, canonical clean-up and product URL redirect, breadcrumbs and structured data |
| C | C1 to C7 | Browse experience: navbar clean-up, remove compare, product cards with prices, pagination and sorting, better search, homepage sections, product page polish |
| D | D1 to D5 | Import engine: migrations for listing identity and affiliate templates, marketplace template field, importer hardening, price history, scraper update |
| E | E1 (done) and E2 (done) | Dashboard: overview counters and click analytics, product list filters and pagination |

### Parked (do not build until the PO writes a task)

1. **Import review queue:** unmatched scraped rows go to a staging table; staff match each row to a product with a search box; the listing (`store_id` + `external_id`) is remembered so the next scrape matches automatically.
2. **Image mirroring:** download and convert the scraper's `image_url` (signed marketplace URLs expire); decide whether seller images are acceptable.
3. **Specs and compare:** `spec_definitions` per category, specs on products, then compare within one category only.
4. **Price chart and price-drop alerts** for members (needs price history and a way to reach members).
6. **Public marketplace pages.** Seller pages are done at `/seller/<slug>`.
7. **Member onboarding:** decide between admin activation (today), automatic activation, or WhatsApp verification.
9. **Caching** of the homepage queries.

---

## 11. Phase gate

A phase is **done** only when every task report is accepted by the PO, `php bin/check_rules.php` passes on the whole project, and the PO has clicked through every manual test step.