# GudangPC: Task Prompts for OpenCode (revised)

These are the prompts you paste into OpenCode, one per session, in order.
`AGENTS.md` is loaded automatically by OpenCode. `docs/PRODUCT_SPEC.md` is read because every prompt tells the worker to.

**You are not a programmer. That is fine.** For every task below:
- You paste the prompt into OpenCode (Plan tab → Tab → Build tab).
- You copy-paste the "CHECK COMMANDS" into a terminal and paste the output back to me.
- You follow the "MANUAL TEST STEPS" in your browser like a checklist.
- You paste the worker's report back to me.
I (the PO) read the report, you don't have to understand the code.

---

## A. Plain-English glossary

You will see these words a lot. You do not need to understand them deeply — just recognize them.

| Word | What it means here |
|---|---|
| **PO** | Product Owner. That's me. I write the tasks. |
| **Worker** | The AI (OpenCode) that writes the code. |
| **Root** | You / the owner. There is exactly **one** root account, created once with `bin/add_root.php`. Cannot be created again. |
| **Admin** | Internal staff. Created by Root via the dashboard. Can use the dashboard (categories, brands, stores, products, offers). Cannot touch accounts. |
| **Member** | A public visitor who signed up on the register page. Lives in the `members` table. Can log in and use the Member Area. **Cannot see the dashboard.** |
| **Visitor** | Deprecated word. Do not use it any more. "Member" replaces it. |
| **Syntax rule** | A project rule: no `? :`, no `?:`, no `??`, no `??=`, ever — in any PHP file or view. `php bin/check_rules.php` checks this. |
| **CSRF** | A security check that proves a form was submitted from *our* site, not a malicious one. Every form has a hidden "token" and the server checks it. |
| **Sanitize** | Clean up user input (trim, strip tags, limit length) before using it. |
| **Escape / `htmlspecialchars`** | Make text safe to print in HTML (turns `<` into `&lt;` so it can't break the page or run scripts). |
| **MIME** | The real file type (image/png, image/jpeg, …). We check the file *content*, not just its extension. |
| **Migration** | A small `.sql` file that changes the database structure. You run it once. |
| **Prompt** | A task I write for the Worker. You paste it. |
| **Session** | One OpenCode chat. Start a **new** session for every task. |
| **Build / Plan tabs** | Two modes of the Worker. Plan = read-only planning. Build = writes files. Always use Plan first. |
| **`php -l file.php`** | "PHP lint" — a syntax check. Prints "No syntax errors" if OK. |
| **`php bin/check_rules.php`** | Runs the syntax-rule checker. Prints `OK` if clean, or a list of bad lines. |
| **`git diff`** | Shows what the Worker changed. You copy-paste it to me. |
| **`git commit`** | Save a checkpoint of the current state so you can undo. |
| **`git checkout .`** | Throw away uncommitted changes (undo a bad task). |
| **Dashboard** | The internal admin area at `/dashboard`. |
| **Member Area** | The public signed-in area at `/member`. |

---

## B. The final role model

**Memorize this table. Every prompt depends on it.**

| Role | Table | Created by | Managed by | Can log into | Can do |
|---|---|---|---|---|---|
| **Root** | `users` | `bin/add_root.php` **once** | Nobody (edits self on `/dashboard/profile`) | Dashboard | Everything. Creates/deletes **admins**. Manages **members** (activate/ban). |
| **Admin** | `users` | **Root only** | **Root only** | Dashboard | Categories, Brands, Stores, Products, Offers. **Cannot** see Users or Members links. **Cannot** touch accounts. |
| **Member** | `members` | Self-registration **or** Root | **Root only** | Member Area | Edit own profile. **Cannot** see the dashboard. |

**Rules that follow from this:**

1. There is exactly **one** Root. `bin/add_root.php` already blocks a second one. Do not remove that check.
2. The **Users** dashboard module manages **admins only**. Its list must show `role='admin'` rows and **never** the root row.
3. The **Users** create form has **no role dropdown**. It always creates an admin.
4. The **Users** edit form edits **username** and **password** only. Role is not editable.
5. The **Users** and **Members** sidebar links appear **only** for root (`$_SESSION['role'] === 'root'`).
6. **No public page** creates a `users` row. The only public registration is the member register page.

---

## C. Revised roadmap

| Phase | Name | Status | What it does |
|---|---|---|---|
| **0** | Foundation | **To do** | Syntax-rule cleanup across the whole project, branding, role-name alignment, DB hygiene migration. No new features. |
| **1** | Users module (Manage Admins) | **To do** | Root-only module to create / edit / delete admin accounts. Sets the CRUD pattern for everything after. |
| **2** | Catalog audit & fix | **To do** | Categories, Brands, Stores **already exist** in the code and DB. This phase audits and fixes them against `AGENTS.md`: syntax rule, CSRF, escaping, validation, uploads, delete-safety. No new features. |
| **3** | Products & offers audit & fix | **To do** | Products and Offers **already exist**. Audit and fix the same way. Focus on price validation, offer uniqueness, image upload, delete-safety. |
| **4** | Public site + click tracking | **To do** | New table `offer_clicks`. New public pages: home, category, product, search. `/go/<offer_id>` redirect with click logging. Sitemap, SEO titles. |
| **5** | Specs & compare | **To do** | New tables `spec_definitions`, `product_specs`. Spec entry on products. Filters. Compare page (max 4 products). |
| **6** | Analytics & price history | **To do** | New table `price_history`. Dashboard click analytics. "Last checked" freshness. |
| **7** | Build planner | **To do** | Pick parts into a PC build. Compatibility checks. Total price. |

**Phase gates:** A phase is done only when every task report is accepted, `php bin/check_rules.php` passes on the whole project, and you have clicked through every manual test step.

---

## D. One-time setup (you, not the Worker)

Do this **before** Phase 0.1.

### D.1. Back up the project

Open a terminal in the project folder and run:

```bash
git init
git add -A
git commit -m "baseline before phases"
