# GudangPC Product Scraper

## What this tool does

This tool opens a list of marketplace product links (Tokopedia, Lazada, Shopee) in a real
Chromium browser window that you can see, one link after the other. It copies the product title,
the price, the shop name, the shop rating, the product rating and the number of sold items of
each page into a spreadsheet. The long product description is not collected any more. The
spreadsheet has exactly the same columns as the GudangPC import file, and the tool never touches
the database.

You still have to do a little work by hand: five columns (product_slug, name, category_slug,
brand_slug, vendor_slug) are left empty on purpose, because only a human knows that the
"MacBook Pro 14 inch case" of one shop and the "Case-Cover MacBook Pro M4" of another shop are
the same product.

Because the browser window is visible, you can solve a captcha or log in while the tool waits.
The tool waits up to 45 seconds for a page and then pauses 5 more seconds before it reads the
values, so you have time to click in the window.

## Install (only needed once)

Open a terminal (press the Windows key, type "cmd", press Enter) and run these four commands:

```
cd /d C:\xampp\htdocs\gudangpc\bin\scraper
python -m venv .venv
.venv\Scripts\activate
pip install -r requirements.txt
playwright install chromium
```

```
If you run it in LMDE :

# 1. system packages (once)
sudo apt update
sudo apt install -y python3-venv python3-full php-cli php-mysql

# 2. venv + python deps
cd ~/path/to/gudangpc/bin/scraper
python3 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt

# 3. chromium + its system libs
playwright install chromium
sudo .venv/bin/playwright install-deps chromium

# 4. one-time Shopee login (must be run from a desktop session)
python scrape.py --login shopee

# 5. run the scraper
python scrape.py --targets sample_targets.csv --out products

```

The last command downloads the Chromium browser that Playwright drives. It takes a few minutes.
When you are done with the terminal you can leave the virtual environment with `deactivate`.

## Captchas and logins

The browser window is visible, so you can always act in it while the tool waits:

- A captcha appears: click through it in the window. The tool keeps reading the page afterwards.
- Tokopedia or Shopee asks you to log in: log in in the window. The next pages of that shop are
  then loaded with the same login.
- Tokopedia keeps the login in `bin\scraper\.profiles\tokopedia_browser` and Shopee in
  `bin\scraper\.profiles\shopee`, so the next run starts with the login still there.

The old one time login commands still work and use the same folders:

```
python scrape.py --login tokopedia
python scrape.py --login shopee
```

A browser window opens on the shop home page. Log in there, go back to the terminal and press
Enter. The login is saved in the profile folder and is reused on every later run.

## How to run

```
python scrape.py --targets sample_targets.csv --out products
```

This writes two files in the folder where you run the command (the current folder, not a hidden
temp folder). The date of today is added to the name, so a run never overwrites the file of
yesterday:

- `products_2026-11-13.csv` - the file you feed to the importer
- `products_2026-11-13.xlsx` - the same data in Excel, with the header row frozen

The date comes from the computer, so it is the date of the day you run the tool. If you want
another name, give another prefix: `--out myprices` writes `myprices_2026-11-13.csv` and
`myprices_2026-11-13.xlsx`.

The input file may be a `.csv` or an `.xlsx` file. It needs a column named `url` and it may have
a column named `affiliate_url`. If `affiliate_url` is empty, the original link is stored as the
affiliate link. The order of the rows is kept.

To use a spreadsheet instead of the CSV: open `sample_targets.csv` in Excel, choose
File > Save As > Excel Workbook (.xlsx), and then run:

```
python scrape.py --targets sample_targets.xlsx --out products
```

## What the notes column means

| notes | meaning | what to do |
|---|---|---|
| `ok` | the title and the price were found | nothing, just fill the five empty columns |
| `partial` | the page opened but the title or the price is missing | open the link in your own browser and copy the missing value |
| `blocked_captcha` | a security check or captcha appeared | open the link in your own browser and copy the values by hand |
| `blocked_login` | the marketplace wants a login | log in in the browser window that is open, the tool continues |
| `timeout` | the page took more than 45 seconds | try again later, or copy the values by hand |
| `error:...` | something technical went wrong, the reason is after the colon | copy the link into the report for the developer |

Two error reasons you will probably see: `error:region_not_available` means the marketplace does not
sell that product in the country the computer is in, and `error:page_goto:...` means the page could
not be opened at all (a network problem).

## What the columns mean

The tool fills these columns for you:

| column | what it is | example |
|---|---|---|
| `marketplace` | the shop the link belongs to | `lazada` |
| `vendor_name` | the name of the shop | `I-Shop Jakarta` |
| `price_idr` | the price in rupiah, digits only | `1299000` |
| `affiliate_url` | the link you put in the target file | `https://www.lazada.co.id/...` |
| `seller_title` | the product title exactly as the shop wrote it | `Case Macbook Pro 14 inch ...` |
| `product_rating` | the rating of the product, from 0 to 5 | `4.9` |
| `store_rating` | the rating of the shop, from 0 to 5 | `4.8` |
| `sold_count` | how many items were sold | `8` |
| `image_url` | the link of the product picture | `https://img.lazcdn.com/...` |
| `notes` | what happened on that link | `ok` |

`short_description` and `description` stay empty on purpose. If you ever want to fill them, the
importer writes them into the product when the product is created.

A metric that is not on the page stays empty. The importer ignores an empty metric, so a row with
an empty `sold_count` does not erase the sold count that is already in the database.

## Honest expectations

Lazada is the most reliable of the three and usually returns a complete row. Tokopedia works about
half of the time: it is slow and it often shows a captcha, but you can click through it in the
visible window. Shopee changes its layout often and it shows a captcha to strangers, so log in in
the window when it appears. There is no promise of 100% and blank cells are normal. The tool never
guesses: if it cannot read a value, it leaves the cell empty so that you fill it in.

## Filling the file for the importer

Open the file of today, for example `products_2026-11-13.csv` or `products_2026-11-13.xlsx`, and
fill these five columns for every row you want to import:

| column | example | rule |
|---|---|---|
| `product_slug` | `macbook-pro-14-case-hard` | lowercase letters, digits and dashes only (`a-z`, `0-9`, `-`) |
| `name` | `Case MacBook Pro 14 inch hard` | the product name, max 200 characters |
| `category_slug` | `peripherals` | an existing category slug, see below |
| `brand_slug` | `apple` | an existing brand slug, see below |
| `vendor_slug` | `techzone-id` | an existing vendor of the same marketplace, see below |

The `marketplace` column is filled by the tool and must not be changed: `shopee`, `tokopedia` or
`lazada`. The importer rejects a row when the vendor does not belong to that marketplace.

Category slugs that already exist: `laptops`, `smartphones`, `monitors`, `cpu`, `gpu`,
`motherboard`, `ram`, `ssd`, `psu`, `case`, `cpu-cooler`, `monitor`, `peripherals`.

Brand slugs that already exist: `apple`, `samsung`, `asus`, `amd`, `intel`, `nvidia`, `msi`,
`gigabyte`, `corsair`, `kingston`, `logitech`, `seasonic`, `nzxt`.

Vendor slugs that already exist, with the marketplace they belong to:

| marketplace | vendor slugs |
|---|---|
| shopee | `ibox-official-store`, `random-gadget-store`, `techzone-id`, `enter-komputer` |
| tokopedia | `tokopedia-official`, `nanokomputer`, `gadget-store-id` |
| lazada | `lazada-official`, `digital-store` |
| blibli | `blibli-official`, `audiostream` |
| official | `amd-official` |

When the file is ready, save it as CSV and run the importer from the project root. Use the name
with today's date, otherwise the importer cannot find it:

```
cd /d C:\xampp\htdocs\gudangpc
php bin/import_products.php bin/scraper/products_2026-11-13.csv --dry-run
```

The dry run prints what would happen and writes nothing. When you are happy with the report, run
it without `--dry-run` to write the products and offers into the database, then look at
`/dashboard/products` in the browser.

Rows that the importer rejects are listed in the report with the reason, for example
`row 3: unknown vendor: shopee-id (macbook-pro-14-case)`. Fix the row in the CSV and run the
importer again.
