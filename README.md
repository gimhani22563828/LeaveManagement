# AKK Leave Management System

Employee leave (නිවාඩු) management system — apply, auto-approve, admin review, calendar, logs. Designed to be deployed to **free InfinityFree hosting** and to be **sellable/re-usable per client**.

---

## 1. FaST Deploy (akksolutions.wuaze.com)

Oya account/domain one hadala thiyenne nam:

1. **InfinityFree** → `MySQL Databases` → create a database. Copy:
   - Host Name (e.g. `sql212.infinityfree.com`)
   - Database Name / Username / Password
2. Open **`db.php`** and check these 4 lines are your values:
   ```php
   $DB_HOST = 'sql212.infinityfree.com';
   $DB_NAME = 'if0_xxxxxxxx_leavesystem';
   $DB_USER = 'if0_xxxxxxxx';
   $DB_PASS = 'your-password';
   ```
3. **Upload** all files to InfinityFree `htdocs/` (the `leave_system_deploy.zip` → extract into `htdocs`). Use FTP (FileZilla) or InfinityFree's online file manager.
4. Open `https://akksolutions.wuaze.com/install.php` → create the **Owner (Admin)** account once.
5. Delete/block setup scripts after install (`.htaccess` already blocks `setup_account_AKK.php`). **Also delete `setup_account_AKK.php` from htdocs** after install — it contains hardcoded test credentials.
6. Log in at `.../login.php` and use the admin dashboard.

> Tables are created automatically on first page load (no manual SQL needed).

---

## 2. Sign-in / Entry points

| Page | Who | Purpose |
|------|-----|---------|
| `install.php` | first run | create Owner admin (once) |
| `login.php` | everyone | sign in |
| `create_account.php` | staff | self-register (admin key needed for admin role) |
| `index.php` | employee | apply for leave |
| `admin.php` | admin | approve/reject, calendar, manage employees, rules |
| `my_leaves.php` | employee | own requests / cancel |
| `my_logs.php` | both | activity login |

> `index.php` auto-approves a leave when all business rules pass. If rules block it and it is marked **Emergency**, it goes to **Pending** for admin review. Otherwise it is rejected.

---

## 3. Business Rules (editable in Admin → Leave Auto-Approval Rules)

Rules are stored in the DB (`app_settings`) and can be changed live from the admin panel — no code edits:

- **max_monthly_leaves** — max auto-approved leaves per month per employee (default `4`, `0` = no limit)
- **min_morning_staff** — minimum morning-shift staff that must stay on duty (default `5`)
- **min_evening_staff** — minimum evening-shift staff that must stay on duty (default `3`)
- **use_dependency_rules** — legacy `#1..#12` mutual-exclusion rules (default **OFF**)

**Important (fix that makes it sellable):** the old engine was hard-wired to staff IDs `#1..#12` of the original AKK team. The engine (`leaveManagementEngine.php`) is now **generic**: it counts actual staff by their `shift_type` (`M` / `E` / `BOTH`) from the `users` table, so it works for ANY company size. The `#1..#12` rules are now optional and locked behind `use_dependency_rules`.

---

## 4. Selling to a new client (each client = own InfinityFree + own DB)

1. Copy the `client_package` zip (same code, `db.php` replaced by a clean template).
2. Rename **`db_template.php` → `db.php`** and fill in the client's own:
   - `$DB_HOST / $DB_NAME / $DB_USER / $DB_PASS`
   - `$ADMIN_REG_KEY` (new key for that client — change it!)
   - `$REGISTER_ACCESS_CODE` (change it!)
3. Upload to the client's `htdocs/`, open `install.php`, create owner admin.
4. In admin, set the client's monthly quota / shift minimums, and leave dependency rules OFF unless they actually have a `#1..#12` fixed roster.

> **Security note:** never ship your own InfinityFree DB password to a client. Each client should have their own database and credentials.

---

## 5. Tech notes

- PHP 8.x, PDO MySQL on web / PDO SQLite on localhost (auto-detected).
- Tailwind + Font Awesome + FullCalendar via CDN (needs internet).
- Logging goes to DB `system_logs` (falls back to `logs/` file).
- `.htaccess` blocks direct access to `db.php`, `db_template.php`, `auth.php`, `logger.php`, `leaveManagementEngine.php`, and the `logs/` folder.

---

## 6. Local testing (optional)

Run with a PHP built-in server (SQLite mode is automatic on `localhost`):
```
php -S 127.0.0.1:8080 -t .
```
Open `http://127.0.0.1:8080/install.php`. (The original `phpdesktop` config is also in `phpdesktop_settings.json`.)
