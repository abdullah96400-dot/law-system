# Al Imtyaz Law — Oman: install and operate (v1, batches 1–What v1 contains: a separate app for law offices at **law.alimtyazgcc.com** with its own database and files folder.
- Staff sign-in (password + authenticator app for partners and office managers), invitations, roles and permissions checked on the server, a tamper-evident audit log, Arabic and English, phone and desktop.
- Clients and parties with duplicate warnings; conflict-of-interest checks with a recorded decision; case files opened only from an approved check.
- Hearings with source and outcome, tasks, templates, reminders and escalation (no appeal-deadline calculation, no court connection).
- Encrypted documents with versions and share links; fees, numbered invoices, payments, expenses and client money; a client portal.
- Batch 8: office dashboard and reports with written definitions and Excel download; branches; import from Excel/CSV with a preview; full office export (encrypted ZIP); demo office; view-only support visits opened by the managing partner; signed provisioning API for the platform; in-app help.

The platform side (oman./team.) gets only: `app/law_link.php`, two views (`law_sales.php`, `admin_law.php`), the `/law` and `/admin/law` routes, two new tables (`law_plans`, `law_accounts`) and one line in the daily jobs. No existing platform table changes.

n.

It does **not** touch the oman./team. platform code paths or its database. The only shared-repository change is `.gitignore`.

## 1. Before you start: backup (safe to skip only on the very first install)
hPanel → Files → Backups: take a backup of files and of the **law** database. Download it and check it opens.

## 2. Create the subdomain and database
1. hPanel → Domains → Subdomains → create `law` for alimtyazgcc.com. Point its folder at the `law` folder of the uploaded code (e.g. `public_html/law`).
2. hPanel → Databases → MySQL → create a **new** database and user for the law app (e.g. `u…_law`). Do not reuse the platform database.
3. File Manager → create a folder **outside** `public_html` for case files, e.g. `/home/u…/law_files`.

## 3. Settings
1. Copy `law/config.sample.php` to `law/config.php`.
2. Run `php law/tools/keygen.php` (SSH) — or ask Claude for values — and paste `app_secret` and `files_key` into config.php.
3. **Write `files_key` down offline** (printed, in a safe). Losing it means stored documents can never be opened.
4. Fill in the database, `site_url` (`https://law.alimtyazgcc.com`), `files_dir`, and the email settings (the same Hostinger mailbox as the platform is fine).
5. hPanel → Security → SSL → issue the free certificate for law.alimtyazgcc.com.

## 4. First office
Tables are created automatically on the first visit. To create an office and get the managing partner's one-time link:
```
php law/tools/create_office.php --code=DEMO-SLL --name-ar="مكتب تجريبي — صلالة" --name-en="Demo Law Office — Salalah" \
    --owner-email=you@example.com --owner-name="Your name" --demo
```
Open the printed link, set a password, sign in and set up the authenticator app. Then invite staff from **Staff**.
(`--demo` shows a purple "demo office" banner. Use it for the made-up demo office only.)

Change an office's status (nothing is ever deleted):
```
php law/tools/office_status.php --code=DEMO-SLL --status=readonly     # or active / suspended
```

## 5. Check it works
- `php law/tests/run.php` → `OK 505 checks`; and `php tests/law_link_test.php` → `OK 23 checks` (platform side) (uses a throwaway SQLite database, touches nothing real).
- Open https://oman.alimtyazgcc.com/law/ → must say **Not found**.
- Open https://law.alimtyazgcc.com/app/bootstrap.php and /config.php → must be refused.
- Sign in, open Audit log → "Check the log is intact" → green.

## 6. Link the platform (optional until you sell)
1. `php law/tools/keygen.php` → copy one 64-character value into **law** `config.php` as `api_secret`.
2. In the **platform** `config.php` add: `'law_api' => ['url' => 'https://law.alimtyazgcc.com', 'secret' => 'THE SAME VALUE'],`
3. team. → **Law offices**: add plans (price, users, storage). A plan shows on https://oman.alimtyazgcc.com/law only after a manager with the settings permission approves its price. Changing a price withdraws the approval.
4. Requests from the public page arrive under **Law offices** as "Requested". **Create the office** sends the managing partner the invitation by email.
5. Subscription end: the platform's daily job makes offices past their "paid until" date **read-only** (they can still read and export). Suspend stops sign-in. Nothing is deleted.

## 7. Support visits
1. `php law/tools/support_pass.php` → paste the printed line into law `config.php`. Keep the passphrase with the support team only.
2. The office's managing partner opens a visit (Settings → Support access, 1–72 hours, with a reason) and sends you the link.
3. A visit is view-only; documents, money, legal notes and restricted files stay hidden; every page is in the office's audit log; the partner can end it at any time.

## 8. Demo office
`php law/tools/demo_office.php --code=DEMO-01` creates an office with made-up data and prints the sign-in details once. The demo path is in `docs/law/demo-path.md`. Suspend it when done: `php law/tools/office_status.php --code=DEMO-01 --status=suspended`.

## 9. Guides
`docs/law/guide-office-manager.md`, `docs/law/guide-staff.md`, `docs/law/guide-support.md` (internal), and the in-app **Help** page.

## Database changes and rollback
- Changes are numbered files in `law/migrations/`. `php law/tools/migrate.php status` lists them.
- They only add tables. They run automatically on the first request after an upload.
- To undo the latest one: take and test a backup first, then `php law/tools/migrate.php down --backup-confirmed`. This deletes that migration's tables **and their data**.
- To roll back the code: re-upload the previous release. Leaving new tables in place is harmless.

## Security notes
- Every office query goes through `law/app/tenant.php`, which refuses any SQL without the office scope.
- Permissions are checked on the server for every request (`law/app/perms.php`).
- Audit log: append-only (database triggers) and hash-chained per office.
- Passwords are hashed; 2FA secrets are encrypted; invitation links are stored only as keyed hashes.
- Provisioning API: HMAC-signed, 5-minute window, each request usable once; it can create, change status and limits, and read counts — never client or case data, and it cannot delete.
- `law/app`, `law/migrations`, `law/storage`, `law/tools`, `law/tests` and `config.php` are blocked from the web.
- Backups: hPanel daily backups cover this database too. An off-site, encrypted copy is still to be decided (see the plan document).
