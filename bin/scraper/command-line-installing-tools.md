============================ WINDOWS 10+ USER ============================

# 1. Go to the new folder
cd /d C:\xampp\htdocs\gudangpc\bin\scraper

# 2. Delete the old virtual environment and profiles (safe to ignore errors if they don't exist)
Remove-Item -Recurse -Force .venv -ErrorAction SilentlyContinue
Remove-Item -Recurse -Force .profiles -ErrorAction SilentlyContinue

# 3. Re-create the environment and install
python -m venv .venv
.venv\Scripts\activate
pip install -r requirements.txt
playwright install chromium

# 4. Run the scraper
python scrape.py --targets sample_targets.csv --out products


============================ LINUX DEBIAN USER ============================

# 1. Install system dependencies for Playwright (requires sudo)
sudo apt update
sudo apt install -y python3-venv python3-pip libnss3 libnspr4 libatk1.0-0 libatk-bridge2.0-0 libcups2 libdrm2 libxkbcommon0 libxcomposite1 libxdamage1 libxfixes3 libxrandr2 libgbm1 libasound2 libpango-1.0-0 libcairo2 libatspi2.0-0

# 2. Go to the new folder
cd ~/path/to/gudangpc/bin/scraper

# 3. Delete the old virtual environment and profiles
rm -rf .venv .profiles

# 4. Re-create the environment and install
python3 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
playwright install chromium

# 5. Run the scraper
python scrape.py --targets sample_targets.csv --out products