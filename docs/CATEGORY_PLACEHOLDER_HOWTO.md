# Category and product placeholder images — how to use the bin scripts

## What each script does

| Script | Purpose |
|---|---|
| `bin/create_category_placeholder.php [slug]` | Creates a WebP placeholder image for one category and saves its path in `categories.image`. Defaults to `gaming` if no slug is given. Never overwrites a category that already has an image. |
| `bin/create_store_placeholder.php [slug]` | Creates a WebP logo placeholder for stores and saves it in `stores.logo`. With no slug it fills every store whose logo is empty; with a slug it fills only that store. Never overwrites an existing logo. |
| `bin/create_vendor_placeholder.php [slug]` | Creates a WebP image placeholder for vendors and saves it in `vendors.image`. With no slug it fills every vendor whose image is empty; with a slug it fills only that vendor. Requires migration 025. Never overwrites an existing image. |
| `bin/fill_product_images_from_categories.php` | One-time DB update: for every category that has an image, copies that image path into every product in the category whose `image` is NULL or empty. Never overwrites existing product images. |
| `bin/seed_images.php` | Older, separate script. Generates *product* placeholders (`placeholder-cpu.webp`, `placeholder-gpu.webp`, …) in `public/uploads/products/` for its fixed list of ten slugs and assigns them to products. It does **not** create category images. |

Requirements: PHP with GD + WebP support, `public/assets/fonts/Poppins-Bold.ttf`, and the DB settings in `.env`. On this machine `php` is not on PATH, so use the full path `C:\xampp\php\php.exe`. Run all commands from the project root (`C:\xampp\htdocs\gudangpc`).

## Where images are shown

- **Category image** (`categories.image`): displayed on `/categories` (category tiles) and on `/<category-slug>` (category hero image). If it is empty, `/categories` shows a `bi bi-tag` icon and the category page shows no image.
- **Product image** (`products.image`): displayed by the shared card partial `views/public/_product_card.php` on the homepage, category, brand, search and seller pages. If it is empty, the card shows a plain CSS placeholder with an image icon.
- Dashboard upload: **Dashboard → Categories → Edit** also sets `categories.image` manually (stored under `/uploads/categories/`).

The `fill_product_images_from_categories.php` script exists so you do not have to upload the same placeholder for every product by hand.

## Current state

- `gpu` has a category image; `gaming` has `placeholder-gaming.webp`. Their products have images.
- `laptops`, `smartphones`, `monitors`, `cpu`, `motherboard` and `ram` have **no category image** yet.
- Note: both `monitors` (plural) and `monitor` (singular) exist as categories. The old `seed_images.php` uses `monitor`. Use the exact slug you want to target.
- `cpu`, `motherboard` and `ram` already have *product* placeholders from `seed_images.php`, but those are not *category* images.

## Step 1 — create the missing category placeholders

One at a time:

```powershell
& "C:\xampp\php\php.exe" bin/create_category_placeholder.php laptops
& "C:\xampp\php\php.exe" bin/create_category_placeholder.php smartphones
& "C:\xampp\php\php.exe" bin/create_category_placeholder.php monitors
& "C:\xampp\php\php.exe" bin/create_category_placeholder.php cpu
& "C:\xampp\php\php.exe" bin/create_category_placeholder.php motherboard
& "C:\xampp\php\php.exe" bin/create_category_placeholder.php ram
```

Or all six in one loop:

```powershell
foreach ($slug in @('laptops','smartphones','monitors','cpu','motherboard','ram')) {
    & "C:\xampp\php\php.exe" bin/create_category_placeholder.php $slug
}
```

Expected output per slug:

```
Category laptops: saved /uploads/categories/placeholder-laptops.webp, updated 1 category row(s)
```

If the category already has an image, the script prints `already has an image` and does nothing. If the slug does not exist, it prints `Category not found: <slug>`.

## Step 2 — give imageless products their category's image

```powershell
& "C:\xampp\php\php.exe" bin/fill_product_images_from_categories.php
```

Expected output (counts depend on your data):

```
Category gpu: updated 0 product(s) with /uploads/categories/category_1794461625_bc90044e.webp
Category gaming: updated 0 product(s) with /uploads/categories/placeholder-gaming.webp
Category laptops: updated 2 product(s) with /uploads/categories/placeholder-laptops.webp
...
Total products updated: 2
```

Re-running is safe: once every product has an image, the script reports `0` updates.

## Step 3 — check the result in the browser

1. Open `/categories`: each category tile should show its placeholder instead of the tag icon.
2. Open `/laptops` (or another slug): the category hero image should appear.
3. Open a product from that category: its card should show the same placeholder instead of the empty-image icon.

## Notes

- These are **one-time** database updates. A product created later without an image will not inherit automatically; re-run `fill_product_images_from_categories.php` after adding products or category images.
- `create_category_placeholder.php` accepts only lowercase letters, digits and dashes as the slug.
- Generated files live in `public/uploads/categories/` with names like `placeholder-<slug>.webp`. Do not delete `.htaccess` in that folder.
- To use a custom image instead, upload it in the dashboard category edit form; existing category images are never overwritten by the script.

## Stores (marketplaces)

Stores use `stores.logo` (not `image`). The logo is shown in **Dashboard → Stores** (list thumbnail and edit preview); there are no public store pages. All six current stores (`shopee`, `tokopedia`, `traveloka`, `lazada`, `blibli`, `official`) have an empty logo.

Fill every store that has no logo:

```powershell
& "C:\xampp\php\php.exe" bin/create_store_placeholder.php
```

Fill just one store:

```powershell
& "C:\xampp\php\php.exe" bin/create_store_placeholder.php shopee
```

Expected output (with no argument):

```
Store shopee: saved /uploads/stores/placeholder-shopee.webp, updated 1 store row(s)
Store tokopedia: saved /uploads/stores/placeholder-tokopedia.webp, updated 1 store row(s)
...
Total stores updated: 6
```

Notes:

- Generated files live in `public/uploads/stores/` as `placeholder-<slug>.webp`; the database stores the path `/uploads/stores/<file>`.
- Stores that already have a logo are skipped and reported as `already has a logo`.
- Re-running with no argument is safe: once all logos are set, it prints `No stores need a placeholder logo.`
- This is a one-time DB update, like the product/category script. There is no runtime fallback for store logos yet.
- The script needs the same GD/WebP extension and `Poppins-Bold.ttf` font as the category script.

## Vendors (sellers)

Vendors now have a nullable `vendors.image` column, added by `database/migrations/025_vendors_image.sql`. **Apply that migration first**, otherwise the script will fail with an unknown-column error. The column is not yet used by the vendor forms or the public seller card, so the placeholder will be stored but not displayed until a follow-up task wires the views.

Fill every vendor that has no image:

```powershell
& "C:\xampp\php\php.exe" bin/create_vendor_placeholder.php
```

Fill just one vendor:

```powershell
& "C:\xampp\php\php.exe" bin/create_vendor_placeholder.php ec-store-jakarta
```

Expected output (with no argument):

```
Vendor ibox-official-store: saved /uploads/vendors/placeholder-ibox-official-store.webp, updated 1 vendor row(s)
Vendor random-gadget-store: saved /uploads/vendors/placeholder-random-gadget-store.webp, updated 1 vendor row(s)
...
Total vendors updated: 14
```

Notes:

- Generated files live in `public/uploads/vendors/` as `placeholder-<slug>.webp`; the database stores the path `/uploads/vendors/<file>`. The script creates that folder if needed.
- Vendors that already have an image are skipped and reported as `already has an image`.
- Re-running with no argument is safe: once all images are set, it prints `No vendors need a placeholder image.`
- This is a one-time DB update, like the category and store scripts. There is no runtime fallback for vendor images yet.
- Migration 025 was not applied by the worker; apply it yourself before running the script.
