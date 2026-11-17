# Deployment & Git Setup Instructions (`gudangpc`)

This guide covers preparing your local repository on Windows, cleaning large files from Git history, pushing your code to GitHub, and deploying the application and Python scraper to your live Linux server.

---

## 1. Local Windows Setup & Git Cleanup

Your local Python virtual environment (`.venv`) and backup `.zip` files contain large binaries that exceed GitHub's 100 MB file size limit and will not run on a Linux server. Keep `.venv` locally so your scraper continues working on Windows, while tracking only your code and `requirements.txt` in Git.

### Step 1: Export Scraper Dependencies
Generate a `requirements.txt` file inside `bin/scraper/` so the exact Python packages can be reinstalled on your live server:

```powershell
.\bin\scraper\.venv\Scripts\pip freeze > bin\scraper\requirements.txt
```

### Step 2: Ignore Virtual Environments and Zip Archives
Add `.venv/` and `*.zip` to `.gitignore` so Git stops tracking them in future commits:

```powershell
Add-Content .gitignore "`n.venv/`n*.zip"
```

### Step 3: Scrub Large Files from Git History
Remove `gudangpc.zip` and `bin/scraper/.venv` from your past commit history (this only removes them from Git tracking and leaves your local `.venv` folder intact):

```powershell
git filter-branch --force --index-filter "git rm -rf --cached --ignore-unmatch '*gudangpc.zip' 'bin/scraper/.venv'" --prune-empty --tag-name-filter cat -- --all
```

### Step 4: Clean Up Git Cache
Force Git to remove old cached references and shrink the local repository:

```powershell
git for-each-ref --format="delete %(refname)" refs/original | git update-ref --stdin
git reflog expire --expire=now --all
git gc --prune=now --aggressive
```

### Step 5: Commit and Push to GitHub
Stage `.gitignore` and `requirements.txt`, commit your changes, and force push the cleaned branch:

```powershell
git add .gitignore bin/scraper/requirements.txt
git commit -m "Track scraper requirements and ignore .venv and zip backups"
git push origin main --force
```

---

## 2. Live Server Deployment (Linux)

Once your repository is pushed to GitHub, clone or pull it onto your live server (`/home/homiefo2/gudangpc.com`) and set up a fresh Linux environment for the scraper.

### Step 1: Clone or Pull the Repository
Navigate to your web root and pull the latest changes from GitHub:

```bash
cd /home/homiefo2/gudangpc.com
git pull origin main
```
*(If deploying for the first time, run `git clone https://github.com/onebuild-cpu/gudangpc.git /home/homiefo2/gudangpc.com`.)*

### Step 2: Create a Linux Virtual Environment
Navigate to the scraper directory and create a new virtual environment:

```bash
cd /home/homiefo2/gudangpc.com/bin/scraper
python3 -m venv .venv
source .venv/bin/activate
```

### Step 3: Install Python Packages & Playwright Browsers
Install the dependencies from `requirements.txt` and download Chromium for Linux:

```bash
pip install -r requirements.txt
playwright install chromium
```

*(Optional — VPS/Root Access Only: If you have `sudo` access on your server, run `playwright install-deps chromium` to install required OS-level browser libraries.)*

### Step 4: Run the Scraper
Execute your scraper script using the virtual environment's Python interpreter (useful for both manual runs and cron jobs):

```bash
/home/homiefo2/gudangpc.com/bin/scraper/.venv/bin/python your_scraper_script.py
```

---

## 3. Ongoing Workflow

Whenever you make changes to your website or scraper in the future:

1. **On Windows:**
   - If you install a new Python package, update your requirements file:
     ```powershell
     .\bin\scraper\.venv\Scripts\pip freeze > bin\scraper\requirements.txt
     ```
   - Commit and push your changes:
     ```powershell
     git add .
     git commit -m "Describe your update"
     git push origin main
     ```

2. **On the Live Server:**
   - Pull the latest code:
     ```bash
     cd /home/homiefo2/gudangpc.com && git pull origin main
     ```
   - If `requirements.txt` changed, update the server's virtual environment:
     ```bash
     /home/homiefo2/gudangpc.com/bin/scraper/.venv/bin/pip install -r /home/homiefo2/gudangpc.com/bin/scraper/requirements.txt
     ```
