# GudangPC: Standing Orders for the AI Worker

You are the **worker**. The human is the **product owner (PO)**. The PO writes every task.
You implement exactly what the current task says. Nothing more, nothing less.

Read this file fully at the start of every session. Then read `docs/PRODUCT_SPEC.md`.

---

## 1. The Iron Rules

1. **Do only what the task prompt says.** No extra files, features, refactors, renames, helpers, dependencies, or "improvements". If you notice something else that should change, list it under `NOTICED` in your report. Do not fix it.
2. **Never guess. Never invent.** Do not invent table names, column names, class names, method names, routes, file paths, config keys, or library APIs. Use only names that appear in (a) the task prompt, (b) `docs/PRODUCT_SPEC.md`, or (c) code you have opened in this session.
3. **Read before you write.** Open every file you will modify. Open the signature of every existing method you will call. If a method is not in the file, it does not exist.
4. **When unsure, STOP and ask.** If the task is ambiguous, contradicts the code, or needs a decision, write a `QUESTIONS` section and stop. A question costs nothing. A wrong guess costs a rewrite.
5. **No new libraries.** No Composer, no npm, no new CDN links, no new JS/CSS frameworks. Only what is already in `public/assets/`.
6. **Protected files. Do not edit unless the task names the file:**
   `app/Core/*`, `config/*`, `public/.htaccess`, `public/assets/css/bootstrap*.css`, `public/assets/js/*.min.js`, `bin/add_root.php`, `bin/change_pwd.php`, `tutorial/*`, `database/initial_database.sql`.
7. **Never run destructive commands** (`rm -rf`, `DROP`, `TRUNCATE`, `git reset --hard`, `git clean`) and never run `/init`. Never edit `.env`.
8. **One task per session.** Do not start the next task.

## 2. The One Syntax Rule

Everything is written in **classic, explicit PHP**.

**Forbidden everywhere (PHP files and view templates):**

| Forbidden | Write this instead |
|---|---|
| `$a = $x ? 'p' : 'q';` | `$a = 'q'; if ($x) { $a = 'p'; }` |
| `$a = $x ?: 'q';` | `$a = 'q'; if (!empty($x)) { $a = $x; }` (keep the original truthiness meaning) |
| `$a = $arr['k'] ?? 'd';` | `$a = 'd'; if (isset($arr['k'])) { $a = $arr['k']; }` |
| `$a ??= 'd';` | `if (!isset($a)) { $a = 'd'; }` |
| nested ternary | `if / elseif / else` |

**In views**, never put a condition inside an attribute. Compute a variable first in a PHP block, then print it:

```php
<?php
$overviewActive = '';
if ($currentPage === 'index') {
    $overviewActive = 'active';
}
?>
<a class="nav-link <?= $overviewActive ?>" href="<?= $base ?>/dashboard">Overview</a>
```

Allowed: `?string` nullable types in signatures. Not used in this project: `?->` and `match`. For branching use `if / elseif / else` or `switch`.

**Formatting (all new code):**
- 4 spaces. No tabs. Always braces, even for one-line `if`.
- Class and function declarations: opening brace on the **next line** (as in `app/Core/Controller.php`). Control structures: brace on the **same line**.
- Single-quoted strings unless interpolating. Short array syntax `[]`.
- Every class and public method has a short English docblock.
- Type-hint parameters and return types. No `declare(strict_types=1)`. Do not add it.
- File names: `PascalCase.php` for classes, `lowercase.php` for views.

**After every task you MUST run, and paste the output of:**

```
php bin/check_rules.php <the files you created or changed>
php -l <each PHP file you created or changed>
```

Both must pass for the files you touched. If `check_rules.php` fails on a file you did not touch, ignore it and mention it under `NOTICED`.

## 3. Architecture (as built, do not change)

Custom PHP MVC called "OneBuild". No Composer, no framework. PSR-4 autoloader, namespace `App\` maps to `app/`.

| Folder | Purpose |
|---|---|
| `app/Controllers/` | Extend `App\Core\Controller`. Constructor receives `PDO $pdo`. |
| `app/Models/` | Extend `App\Core\Model`. Set `protected string $table`. |
| `app/Core/` | Router, Model, Controller, Validator, CsrfProtection, RateLimiter, SecurityLogger. |
| `views/` | Raw PHP templates. Folders: `layouts/`, `auth/`, `dashboard/`, `home/`, `errors/`. |
| `public/` | Only web-exposed folder. `index.php` is the front controller. |
| `database/` | SQL files. New schema changes go in `database/migrations/NNN_name.sql`. |
| `storage/` | Logs and sessions. Never commit contents. |

### Routing (copy this exact style)

All routes are registered by hand in `public/index.php`:

```php
$router->addRoute('dashboard/users', 'UserController', 'index');            // GET  list
$router->addRoute('dashboard/users/(\d+)/edit', 'UserController', 'edit');  // GET  form
```

- Pattern has **no leading or trailing slash**. The router wraps it as `~^pattern$~`.
- Controller name is a string **without namespace**. Method is a string.
- Regex groups are passed to the method as **string arguments**: `public function edit(string $id): void`, then `$id = (int) $id;`.
- The router does **not** separate GET and POST. Use separate URLs: `.../store`, `.../update`, `.../delete` for POST, and check `$_SERVER['REQUEST_METHOD'] !== 'POST'` at the top (redirect away if not POST).
- First match wins. Put specific routes before general ones.
- Admin URL shape: `dashboard/<plural-module>`, `dashboard/<module>/create`, `.../store`, `.../(\d+)/edit`, `.../(\d+)/update`, `.../(\d+)/delete`.
- Each module's routes are one commented block in `public/index.php`, placed after the existing dashboard routes.

### Controller pattern

1. First line of every protected method: `$this->requireLogin();` or `$this->requireAdmin();`
2. Every POST method: check method, then `CsrfProtection::requireValidToken();`
3. Sanitize with `Validator::sanitize()`, validate with `Validator::*`.
4. Database work only through a Model. No SQL in controllers.
5. Success or failure messages: set `$_SESSION['success']` or `$_SESSION['error']`, then `$this->redirect(...)`. The navbar prints and clears them.
6. Render with `$this->view('dashboard/<module>/<page>', [...])`. Always pass `pageTitle`, `metaDescription`, and `currentPage` (for the sidebar highlight).
7. Redirect paths are given without base URL, e.g. `$this->redirect('/dashboard/users');`.

### Model pattern

- Use the inherited `insert()`, `update()`, `delete()`, `findById()`, `findAll()`, `query()`, `queryOne()`.
- `insert()` auto-fills `created_at` and `updated_at`. A table without both columns MUST set `protected bool $timestamps = false;`.
- `findAllPublished()` expects a `status` column with the value `'published'`.
- Custom SQL: prepared statements with `?` placeholders only. Never concatenate user input. The table name is `{$this->table}`.
- Never select or output the `password` column in list queries.

## 4. Views

- First lines of every full-page view: set `$base` and `$nonce` from `$GLOBALS` using `isset()`, copy the block from `views/dashboard/index.php`. Then include `layouts/header.php` and `layouts/navbar.php`; end with `layouts/footer.php`.
- All URLs and assets: `<?= $base ?>/...`.
- All dynamic output: `<?= htmlspecialchars((string) $value) ?>`. No exceptions.
- Every `<form method="POST">` must contain `<?= \App\Core\CsrfProtection::getHiddenInput() ?>`.
- The CSP blocks inline handlers (`onclick=""`, `onsubmit=""`) and inline `style=""` is discouraged. Never use inline event attributes. Inline `<script>` and `<style>` blocks MUST have `nonce="<?= $nonce ?>"`. Prefer Bootstrap's built-in `data-bs-*` behaviour (modals, collapse, dropdowns) over custom JS.
- UI framework: Bootstrap 5 + Bootstrap Icons (`bi bi-*`). Reuse existing classes from `public/assets/css/dashboard.css`. Put new shared CSS there only if the task says so.
- UI language: English in the dashboard, Indonesian on public pages.

## 5. Security checklist (every task)

- [ ] `requireLogin()` / `requireAdmin()` at the top of protected methods
- [ ] CSRF token in every form and `requireValidToken()` in every POST handler
- [ ] Input sanitized and validated; `inArray()` for any whitelisted value
- [ ] Prepared statements only
- [ ] All output escaped
- [ ] Uploaded files: check real MIME with `finfo`, size limit, safe extension map, random-ish file name. Copy the approach of `handleAvatarUpload()` in `DashboardController.php`.
- [ ] Never log or print passwords, hashes, or CSRF tokens

## 6. Roles

`root` (super admin, created only by `bin/add_root.php`), `admin` (staff), `visitor` (public member).
Registration is **dashboard-only**. There is no public register page.

## 7. Report format (end of every task, always)

```
TASK: <id and name>
FILES CREATED: <list>
FILES CHANGED: <list, with one line per file saying what changed>
CHECKS: <paste php -l and check_rules.php output>
MANUAL TEST STEPS: <numbered list the PO can follow in the browser>
DEVIATIONS: <anything you did differently from the prompt and why, or "none">
NOTICED: <problems you saw but did NOT fix, or "none">
QUESTIONS: <or "none">
```

## 8. Out of scope (globally, until a task says otherwise)

Scraping or auto-fetching prices, marketplace APIs, payments, shopping cart, public user registration, comments or reviews, multi-language UI, REST API, JavaScript frameworks, email, WhatsApp, caching layers, any rewrite of `app/Core/*`.
