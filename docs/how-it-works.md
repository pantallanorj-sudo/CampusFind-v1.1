# How HTML, CSS, JavaScript, PHP, SQLite and PDO work together

Think of CampusFind as a restaurant.

| Technology | Role in the restaurant | Where to find it |
|---|---|---|
| **HTML** | The menu: what is on the page (headings, forms, tables) | The markup printed by the `.php` pages |
| **CSS** | The decor: colours, spacing, layout, phone vs desktop | `assets/css/style.css` |
| **JavaScript** | The waiter's small helpers: confirm prompts, mobile menu, photo preview. It never decides anything important. | `assets/js/app.js` |
| **PHP** | The kitchen: checks the order, applies the rules, talks to the storage room | `student/`, `admin/`, `actions/`, `includes/` |
| **SQLite** | The storage room: one file that keeps all the data | `database/campusfind.sqlite` |
| **PDO** | The messenger between PHP and SQLite, using prepared statements so user text can never be run as SQL | `config/database.php` |

## One request, step by step: a student submits a claim

1. The browser opens `student/submit-claim.php?id=7`. PHP checks the person is a logged-in student, loads report 7 from SQLite through PDO,
   and checks the claim is allowed. It prints an HTML form (with a hidden CSRF token). CSS makes it look good.
2. JavaScript shows the character counter and disables the button after the click so it cannot be double-submitted.
3. The browser sends a **POST** to `actions/claims/submit.php`. This file prints no HTML. It:
   - checks the request is a POST with a valid CSRF token and that the user is a student,
   - validates the text on the server (JavaScript is not trusted),
   - calls `submit_claim()` in `includes/claims.php`, which re-checks the rules and, inside one database **transaction**,
     inserts the claim and changes the item status plus its history row,
   - sets a flash message and redirects to the claim page.
4. The claim page reads the new row from SQLite and shows it with a status badge.

Why server-side rules matter: anyone can bypass the page and send a request by hand, so every rule is enforced in PHP (and the
most important ones again as database constraints).

## Where to start reading the code

1. `includes/init.php` is included by every page and sets everything up.
2. `student/browse-items.php` is a good small page: it reads filters, calls `search_reports()`, prints cards.
3. `actions/reports/create.php` shows validation, saving and redirecting.
4. `includes/claims.php` has the claim rules and the approve / handover logic.
