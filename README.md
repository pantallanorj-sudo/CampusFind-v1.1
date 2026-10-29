# CampusFind

A campus lost-and-found management system for a school or university, built for a
second-year BSIS Web Application Development final project.

Students report lost or found items, browse reports and claim items that are theirs.
Authorized staff verify claims, approve or reject them, and record when an item was actually handed over.
All data is stored in SQLite and accessed with PHP and PDO.

> The school, people, items and credentials in this project are **fictional demo data**.

---

## Features

| Module | What it does |
|---|---|
| Landing page | Branding, how-it-works section, Login and Register buttons. Shows no private data. |
| Authentication | Student registration, login, logout, PHP sessions, `password_hash()` / `password_verify()`, session ID regenerated at login, generic login error. |
| Student dashboard | Counts for reports, open reports, pending claims, returned items (all calculated from the database), quick actions, recent activity. |
| Browse and search | Search by text, filter by lost/found, category and status, pagination, empty states, detail page. |
| Item reports (CRUD) | Create lost/found reports (optional photo), view, edit (while open), delete (with a confirmation page and safety rules), close. |
| Claims | Claim form with private details, My Claims, claim detail, withdraw while pending. Staff review with notes, approve, reject, revoke, record handover. |
| Admin dashboard | Total / open / pending claims / awaiting handover / resolved cards, recent reports, pending claims. |
| Categories | Create, list, edit, delete (only when unused), no duplicate names. |
| Status history | Every status change with who and when, searchable. |

### Approved is not returned

A **claim** and an **item** have separate statuses.

| Claim status | Meaning |
|---|---|
| `pending` | Waiting for staff review |
| `approved` | Staff are satisfied the claimant owns it |
| `rejected` | Staff declined (or it was ended automatically) |
| `withdrawn` | The claimant cancelled it |

| Item status | Meaning |
|---|---|
| `open` | No active claim |
| `claim_pending` | At least one claim is waiting for review |
| `awaiting_handover` | A claim was **approved**, but the item has **not** been handed over yet |
| `returned` | Staff **recorded the handover** (final) |
| `closed` | Archived without a handover |

Approving a claim moves the item to `awaiting_handover`. Only the separate **Record handover**
action moves it to `returned`. When a claim is approved, every other pending claim for that item
is automatically rejected with a note, so there is never more than one approved claim.
Rejecting a claim never closes the report.

## Technology

HTML5, CSS3, vanilla JavaScript, PHP, SQLite and PDO. No frameworks, no Node.js, no build step.
Icons are inline SVG, so the app works offline.

## Prerequisites

- PHP 8.0 or newer with the `pdo_sqlite` extension (developed and tested on PHP 8.3.6).
- Optional: the `mbstring` extension (the code falls back if it is missing).
- A terminal. No database server is needed.

Check your PHP: `php -v` and `php -m | grep -i sqlite` (on Windows: `php -m | findstr /i sqlite`).

## Setup and run

```bash
# 1. Open a terminal in the project folder (the one that contains index.php)

# 2. Create the database, demo accounts and sample data
php database/setup.php            # add --fresh to delete an existing database and start over

# 3. Start the built-in web server
php -S localhost:8000 router.php

# 4. Open http://localhost:8000
```

A ready-made `database/campusfind.sqlite` is included in the ZIP, so step 2 is only needed
to reset the demo data. Use `router.php` as the router script: PHP's built-in server ignores
`.htaccess`, and the router is what blocks direct web access to `config/`, `database/`,
`includes/`, `uploads/`, `tests/` and `docs/`.

### Using XAMPP or another Apache setup

1. Copy the `CampusFind` folder into `htdocs` and make sure the `pdo_sqlite` extension is enabled in `php.ini`.
2. Run `php database/setup.php` once from a terminal (or keep the included database).
3. Open `http://localhost/CampusFind/`. The base URL is detected automatically.
4. Make sure `database/` and `uploads/items/` are writable by the web server.
5. The `.htaccess` file in each private folder denies direct access (Apache 2.4 and 2.2 syntax).
   These Apache rules were **not** tested in this build (only the built-in server was available), so
   open `http://localhost/CampusFind/database/campusfind.sqlite` and confirm you get "403 Forbidden".

## Demo accounts (development only)

All demo accounts use the same password: **`Demo@12345`**

| Role | Email | Name |
|---|---|---|
| Staff (admin) | `admin@campusfind.test` | Rowena Mendoza |
| Student | `maria.santos@student.campusfind.test` | Maria Santos |
| Student | `juan.delacruz@student.campusfind.test` | Juan Dela Cruz |
| Student | `ana.reyes@student.campusfind.test` | Ana Reyes |

The passwords are hashed with `password_hash()` by `database/setup.php` before they are stored.
**Change or remove these accounts before any real deployment.**
Public registration always creates a student. Staff accounts are created only by the setup script
(or by an administrator inserting a row with a hashed password). There is no admin option in the sign-up form.

### Suggested demo script

1. Log in as **Maria**, report a found item, then log out.
2. Log in as **Juan**, browse, open Maria's item and submit a claim. Check **My claims**.
3. Log in as **staff**, open **Manage claims**, review Juan's claim, add notes and approve it.
   The item shows **Awaiting handover**, not Returned.
4. Still as staff, open the claim and use **Record handover**. The item is now **Returned**.
5. Open **Status history** to see every change, and log in as Juan to see his updated claim.

The seed data already contains a pending claim, an approved claim awaiting handover, a returned item,
a rejected claim and a closed report, so every screen has something to show.

## Folder structure

```
CampusFind/
├── index.php  login.php  register.php  logout.php  image.php  router.php
├── config/        app.php (settings), database.php (PDO connection, foreign keys ON)
├── database/      schema.sql, seed.sql, setup.php, campusfind.sqlite (generated)
├── includes/      init.php, auth.php, csrf.php, functions.php, validation.php,
│                  reports.php, claims.php (business rules),
│                  layouts/ (header, footer, sidebars, flash), components/ (badges, cards, icons, form)
├── student/       dashboard, browse, details, report/edit/delete, my reports, claims
├── admin/         dashboard, reports, claims, categories, status history
├── actions/       auth/  reports/  claims/  categories/   (POST handlers, no HTML)
├── assets/        css/style.css, js/app.js, images/
├── uploads/items/ uploaded photos (not web-accessible; served by image.php after a login check)
├── tests/         run_tests.php, HttpClient.php
└── docs/          database.md, workflows.md, how-it-works.md, test-checklist.md, test-results.md
```

## Database relationships (summary)

```
users 1───* item_reports *───1 categories
users 1───* claim_requests (claimant_id)     users 1───* claim_requests (reviewed_by)
item_reports 1───* claim_requests            item_reports 1───* status_history *───1 users (changed_by)
```

Foreign keys are enforced (`PRAGMA foreign_keys = ON` on every connection). Details and the status
rules are in [`docs/database.md`](docs/database.md).

## Security notes

- PDO prepared statements everywhere; `htmlspecialchars()` on all output (`e()` helper).
- Server-side validation and permission checks on every page and every action. Hiding a button is never the only protection.
- CSRF token on every POST form; state changes are POST-only (GET returns 405).
- Passwords hashed; one generic message for every login failure; session ID regenerated at login; session cookie is `HttpOnly` and `SameSite=Lax`; logout is a CSRF-protected POST that destroys the session.
- Ownership checks: a student cannot edit, delete or close another student's report, or open another student's claim.
- Claim details and staff notes are visible only to the claimant and staff.
- Redirect targets are restricted to `.php` pages inside the project (no open redirect).
- Photo uploads: type detected from file content (JPG, PNG, WebP), 2 MB limit, random file names, stored outside direct web access, served by `image.php` only to logged-in users.
- Strict Content-Security-Policy. The pages use no inline scripts or styles.
- Related changes (status + history + claim updates) run inside database transactions.

## Testing

```bash
php tests/run_tests.php                                 # run all checks
php tests/run_tests.php --report=docs/test-results.md   # also write a Markdown report
```

The runner builds a **temporary** database, starts the built-in server against it and drives the real
application over HTTP: registration, login, CSRF, reports CRUD, uploads, the complete claim workflow,
categories, dashboards, database constraints and a crawler that checks every link and form. Your demo
database is never touched. The last run covered 291 checks and all passed; see
[`docs/test-results.md`](docs/test-results.md). A manual checklist for things automation cannot see
(layout, confirm dialogs, mobile menu) is in [`docs/test-checklist.md`](docs/test-checklist.md).

## Known limitations

- **Browser coverage is a spot check only.** The automated tests exercise HTTP responses and the database; they do not
  run JavaScript or judge layout. As a separate one-off check in headless **Chromium** I rendered the landing page, both
  dashboards, browse, My reports and the claim review screen (desktop 1280 px and phone 375 px), confirmed the mobile menu
  opens and closes, that cancelling the Approve confirm dialog leaves the claim pending, that there are no JavaScript
  console errors or 4xx/5xx responses while visiting the main pages, and that the main pages have no sideways overflow at
  1280, 768 and 375 px. That pass found and fixed two layout bugs. Other browsers, real phones, the photo preview and every
  remaining dialog were not checked; use [`docs/test-checklist.md`](docs/test-checklist.md).
- Apache `.htaccess` protection was not tested (see the XAMPP notes above).
- No password reset, email verification or email/SMS notifications.
- No login rate limiting or lockout, and no idle-session timeout.
- No screen for managing staff accounts; staff are provisioned with `database/setup.php`.
- Uploaded photos are checked but not re-encoded, so image metadata (such as GPS EXIF data) is kept.
- A student whose claim was rejected can submit a new claim for the same item (only active claims are blocked).
- Lost reports are not matched to found reports automatically; students match them by browsing.
- SQLite allows one writer at a time, which is fine for a campus demo but not for heavy traffic.
- HTTPS is not enforced. Use HTTPS in any real deployment.
