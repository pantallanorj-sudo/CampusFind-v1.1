# CampusFind automated test results

- Run date: 2026-10-25 13:41:14 (Asia/Manila)
- PHP version: 8.3.6 (Linux)
- Command: `php tests/run_tests.php`
- Method: the runner builds a temporary SQLite database, starts PHP's built-in server and drives the real application over HTTP (sessions, CSRF tokens, form posts, file uploads), then checks the pages and the database rows.
- **Result: 291 of 291 checks passed**

## 1. Public pages and security headers

| Result | Check |
|---|---|
| PASS | Landing page is public and loads |
| PASS | Landing page links to Login and Registration |
| PASS | Landing page shows no private report data |
| PASS | Login page loads |
| PASS | Content-Security-Policy header is sent |
| PASS | X-Frame-Options and nosniff headers are sent |
| PASS | Registration page loads and has no role field |
| PASS | Unknown page returns 404 |

## 2. Authentication

| Result | Check |
|---|---|
| PASS | Valid student login redirects to the student dashboard |
| PASS | Session ID is regenerated after login |
| PASS | Student dashboard shows the logged-in student |
| PASS | Valid staff login redirects to the admin dashboard (role-based redirect) |
| PASS | Wrong password is rejected with a generic message |
| PASS | Unknown email gives the SAME generic message |
| PASS | Failed login does not create a logged-in session |
| PASS | Empty password is rejected |
| PASS | Passwords are stored as hashes, never plain text |
| PASS | password_verify() accepts the demo password for every account |
| PASS | Student registration succeeds and logs the student in |
| PASS | Email is saved in lower case and role is student |
| PASS | Registered password is hashed and verifies |
| PASS | Duplicate email (any letter case) is rejected |
| PASS | Only one account exists for that email |
| PASS | Registration ignores a "role=admin" field (always student) |
| PASS | Weak or mismatching passwords are rejected with helpful errors |
| PASS | Empty name and invalid email show field errors |
| PASS | Login ignores an external "next" address (no open redirect) |
| PASS | Login ignores a protocol-relative "next" address |
| PASS | Login returns to a safe page the student asked for |
| PASS | Staff are not sent to student pages by "next" |
| PASS | GET /logout.php does not log anyone out |
| PASS | Logout (POST + CSRF) redirects to the login page |
| PASS | After logout the same browser is treated as anonymous |
| PASS | The OLD session cookie no longer works after logout |
| PASS | Logout without a CSRF token is refused and the user stays logged in |

## 3. Access control (server-side)

| Result | Check |
|---|---|
| PASS | Anonymous users are sent to the login page for every protected page |
| PASS | A student gets "403 Access denied" on every staff page |
| PASS | Staff accounts are kept out of student-only pages |
| PASS | A student cannot call any staff action, even with a valid CSRF token |
| PASS | No category "Hacked category" was created |
| PASS | Anonymous POSTs to actions are sent to login and change nothing |
| PASS | State-changing actions refuse GET requests (405) |
| PASS | A POST without a CSRF token is refused (403) |
| PASS | A POST with a forged CSRF token is refused (403) |

## 4. Item report CRUD

| Result | Check |
|---|---|
| PASS | CREATE: a lost-item report is saved and the student is sent to its page |
| PASS | CREATE: owner is the logged-in student, type lost, status open |
| PASS | CREATE: created_at and updated_at are recorded |
| PASS | CREATE: a "Report created" status-history row is written |
| PASS | READ: report details page shows the saved data |
| PASS | READ: it appears in "My reports" |
| PASS | READ: it appears in Browse for another student |
| PASS | READ: other students do NOT see the reporter's name or email |
| PASS | CREATE: invalid input is rejected for all 10 bad cases |
| PASS | CREATE: rejected input saves nothing |
| PASS | CREATE: the form redisplays helpful error messages |
| PASS | CREATE: the form keeps what the student typed |
| PASS | OUTPUT: HTML in user text is escaped (no script injection) |
| PASS | SQL injection in search is harmless (prepared statements) |
| PASS | Search treats % as plain text, not a wildcard |
| PASS | UPDATE: owner can edit an open report |
| PASS | UPDATE: changes are stored in the database |
| PASS | UPDATE: owner, type and created_at are unchanged |
| PASS | UPDATE: data persists after a page refresh |
| PASS | UPDATE: invalid input is rejected |
| PASS | UPDATE: the report type cannot be switched from lost to found |
| PASS | Another student cannot open the edit page (403) |
| PASS | Another student cannot edit the report by POST (403) |
| PASS | Another student cannot delete the report (403) |
| PASS | Another student cannot close the report (403) |
| PASS | Another student cannot open the delete page (403) |
| PASS | Missing report returns 404 on edit and details |
| PASS | Non-numeric ids are handled safely (404) |
| PASS | Edit page is blocked for a report that has a pending claim |
| PASS | Edit POST is refused for a report that has a pending claim |
| PASS | Delete page explains why a report with claims cannot be deleted |
| PASS | DELETE: a report with an active claim cannot be deleted |
| PASS | DELETE: an open report with claim history is protected (use Close instead) |
| PASS | CLOSE: Close button is offered on the owner's open report |
| PASS | CLOSE: owner can close an open report |
| PASS | CLOSE: history records open -> closed by the owner |
| PASS | CLOSE: closed report is hidden from other students (404) |
| PASS | CLOSE: closed report is not listed in Browse |
| PASS | CLOSE: owner can still open it |
| PASS | CLOSE: a closed report can no longer be edited |
| PASS | DELETE: a confirmation page is shown first |
| PASS | DELETE: owner can delete an eligible report |
| PASS | DELETE: its history rows are removed too (no orphans) |
| PASS | DELETE: the deleted report returns 404 |
| PASS | STAFF: a status change needs a meaningful note |
| PASS | STAFF: an illegal status transition is rejected |
| PASS | STAFF: an arbitrary status string is rejected |
| PASS | STAFF: can close a report |
| PASS | STAFF: can reopen a closed report |
| PASS | STAFF: can mark a lost item as recovered (returned) |
| PASS | STAFF: every change is in the history with the staff member as the actor |
| PASS | STAFF: a returned report is final and cannot be changed |
| PASS | STAFF: seed item that was returned also cannot be reopened |

## 5. Browse, search and filters

| Result | Check |
|---|---|
| PASS | Browse page lists active reports |
| PASS | Browse hides closed reports by default |
| PASS | Browse hides returned reports by default |
| PASS | Status filter "Returned" shows returned items |
| PASS | Closed reports can never be listed through the status filter |
| PASS | Search by title works |
| PASS | Search by description works |
| PASS | Filter by type "lost" shows only lost reports |
| PASS | Filter by type "found" shows only found reports |
| PASS | Filter by category works |
| PASS | Filter by status works |
| PASS | An empty state is shown when nothing matches |
| PASS | "Clear filters" link is shown only while filtering |
| PASS | Invalid filter values are ignored safely |
| PASS | Browse cards never show reporter names or emails |
| PASS | Pagination: page 1 shows 9 cards and a pager |
| PASS | Pagination: page 2 shows the remaining 3 cards |
| PASS | Pagination: pages do not repeat items |
| PASS | Pagination: a page number that is too high falls back to the last page |
| PASS | Pagination keeps the search words in its links |
| PASS | My reports: filter by type works |
| PASS | My reports: shows only the student's own reports |

## 6. Photo upload safety

| Result | Check |
|---|---|
| PASS | A valid PNG is accepted (type detected from content) |
| PASS | The stored file name is random and ignores the uploaded name ("my photo.php") |
| PASS | The file exists on disk |
| PASS | Owner can view the photo through image.php |
| PASS | Another logged-in student can view it (public listing) |
| PASS | Anonymous users cannot view it |
| PASS | The uploads folder cannot be opened directly (403) |
| PASS | Details page uses image.php, not a direct file link |
| PASS | A PHP script disguised as evil.png is rejected |
| PASS | A text file is rejected |
| PASS | An image over 2 MB is rejected |
| PASS | Rejected uploads leave no files behind |
| PASS | Owner can remove the photo while editing |
| PASS | Owner can add a new photo while editing |
| PASS | Deleting a report also deletes its photo file |

## 7. Claims: submit, privacy and business rules

| Result | Check |
|---|---|
| PASS | Another student sees a "Claim this item" button on an eligible found report |
| PASS | The reporter does NOT get a claim button on their own report |
| PASS | Claim form is blocked for the reporter of the item |
| PASS | A student cannot claim their own found-item report (server rule) |
| PASS | Claim form loads for an eligible student |
| PASS | A too-short claim is rejected with an error |
| PASS | A valid claim is saved and the claimant is sent to its page |
| PASS | The claim is linked to the report and the claimant, status pending |
| PASS | The report moves to "claim_pending" (a separate status from the claim) |
| PASS | History records open -> claim_pending by the claimant |
| PASS | A duplicate active claim by the same student is blocked |
| PASS | Lost-item reports cannot be claimed |
| PASS | A closed report cannot be claimed (hidden from other students) |
| PASS | Claim POST on a closed report is refused |
| PASS | A closed report is not claimable even through a direct POST |
| PASS | A returned item cannot be claimed |
| PASS | An item awaiting handover (approved claim exists) cannot get new claims |
| PASS | A different student can also claim the same item |
| PASS | Extra claims do not add extra history rows (status unchanged) |
| PASS | Claim details never appear on public or reporter pages |
| PASS | The claimant can read their own claim |
| PASS | Another student cannot open someone else's claim (404) |
| PASS | The reporter cannot open the claim either (404) |
| PASS | Anonymous users cannot open a claim |
| PASS | Staff can read the claim details |
| PASS | My claims lists only the student's own claims |
| PASS | A student cannot approve a claim (403) |
| PASS | Even the item's reporter cannot approve a claim (403) |
| PASS | A student cannot record a handover (403) |

## 8. Claims: staff review, approval, handover

| Result | Check |
|---|---|
| PASS | Approving without staff notes is refused |
| PASS | An unknown decision value is refused |
| PASS | Staff review page warns that approval rejects other pending claims |
| PASS | Staff can approve a pending claim |
| PASS | The reviewer and review time are recorded |
| PASS | APPROVAL DOES NOT MARK THE ITEM AS RETURNED (status is awaiting_handover) |
| PASS | The other pending claim was automatically rejected (one winner only) |
| PASS | Only one approved claim exists for the item |
| PASS | History records claim_pending -> awaiting_handover by staff |
| PASS | No "returned" history exists yet |
| PASS | An already-approved claim cannot be approved again |
| PASS | A rejected claim cannot be approved afterwards |
| PASS | Nobody can start a new claim while a handover is pending |
| PASS | The claimant sees "approved" and that the item is not yet returned |
| PASS | The claimant cannot withdraw an approved claim |
| PASS | Withdraw POST on an approved claim is refused |
| PASS | A handover cannot be recorded for a claim that is not approved |
| PASS | A handover cannot be recorded for a rejected claim |
| PASS | Staff can record the handover separately from approval |
| PASS | The approved claim stays "approved" after handover |
| PASS | History records awaiting_handover -> returned with the handover note |
| PASS | The claimant sees "handed over" |
| PASS | A second handover for the same item is refused |
| PASS | An approval cannot be revoked after the handover |
| PASS | A returned item cannot be claimed again |

## 9. Claims: reject, withdraw, revoke and closing

| Result | Check |
|---|---|
| PASS | Staff can reject a claim and the reviewer is recorded |
| PASS | Rejecting one of two claims keeps the report at "claim_pending" |
| PASS | The claimant can read the staff notes on a rejected claim |
| PASS | Rejecting the last pending claim reopens the report (status open, NOT closed) |
| PASS | History records claim_pending -> open after the last rejection |
| PASS | A rejected claimant can submit a new claim (rejection is not a ban) |
| PASS | Another student cannot withdraw someone else's claim (404) |
| PASS | The claimant sees a Withdraw button on a pending claim |
| PASS | The claimant can withdraw a pending claim |
| PASS | Withdrawing the only pending claim reopens the report |
| PASS | A withdrawn claim cannot be withdrawn again |
| PASS | Staff cannot approve a withdrawn claim |
| PASS | Approving a claim for an already-returned item is prevented |
| PASS | Setup: item is awaiting handover after approval |
| PASS | Revoking an approval needs a reason |
| PASS | Staff can revoke an approval before handover (claim rejected, report open) |
| PASS | The revoke reason is saved on the claim |
| PASS | Staff can close a report that has a pending claim |
| PASS | Closing the report ends its pending claims (no dangling claims) |
| PASS | Owner cannot close a report that has claim activity (claim_pending) |

## 10. Integration: the complete lost-and-found journey

| Result | Check |
|---|---|
| PASS | Step 1-2: student Juan reports a found item |
| PASS | Step 3: another student (Ana) finds it by browsing and searching |
| PASS | Step 3: Ana opens the details page and sees the claim button |
| PASS | Step 4: Ana submits a claim; claim is pending and the item is claim_pending |
| PASS | Step 4: Ana sees the pending claim in "My claims" |
| PASS | Step 4: Juan (reporter) sees the report as "Claim pending" but not the claim text |
| PASS | Step 5: staff see the claim in the pending queue |
| PASS | Step 5: staff dashboard counts it as a pending claim |
| PASS | Step 5: staff open the claim and read the private details |
| PASS | Step 6: staff approve; claim approved, item awaiting_handover (not returned) |
| PASS | Step 7: the staff dashboard shows it under "Awaiting handover" |
| PASS | Step 7: Ana's dashboard does not count it as returned yet |
| PASS | Step 8: the item status is now returned |
| PASS | Step 8: the claim remains approved |
| PASS | Step 9: history lists null>open, open>claim_pending, claim_pending>awaiting_handover, awaiting_handover>returned |
| PASS | Step 9: each history row names the right person (Juan, Ana, staff, staff) |
| PASS | Step 9: staff see the same trail on the Status history page |
| PASS | Step 9: staff see the history on the report page |
| PASS | Step 10: Ana sees her claim as approved and handed over |
| PASS | Step 10: Juan sees his report as Returned |
| PASS | Step 10: Ana's "Returned items" count went up by one |
| PASS | Step 10: Juan's dashboard also counts it as returned |
| PASS | Step 10: staff totals moved from "awaiting handover" to "resolved" |

## 11. Category management

| Result | Check |
|---|---|
| PASS | Staff see the seeded categories with report counts |
| PASS | CREATE: staff can add a category |
| PASS | CREATE: a duplicate name (any letter case) is rejected |
| PASS | CREATE: an empty name is rejected |
| PASS | CREATE: a name over 60 characters is rejected |
| PASS | The new category is offered on the report form |
| PASS | EDIT: the edit form is prefilled |
| PASS | EDIT: staff can rename a category |
| PASS | EDIT: renaming to an existing name is rejected |
| PASS | EDIT: a missing category is handled safely |
| PASS | DELETE: a category still used by reports cannot be deleted |
| PASS | DELETE: the page shows "In use" instead of a delete button for it |
| PASS | DELETE: an unused category can be deleted |
| PASS | A student cannot create categories (403) |

## 12. Dashboards use real database numbers

| Result | Check |
|---|---|
| PASS | Student dashboard numbers match the database (maria.santos@student.campusfind.test) |
| PASS | Student dashboard numbers match the database (juan.delacruz@student.campusfind.test) |
| PASS | Student dashboard numbers match the database (ana.reyes@student.campusfind.test) |
| PASS | Staff dashboard numbers match the database |
| PASS | Staff dashboard lists recent reports and pending claims |
| PASS | Staff navigation has all required links |
| PASS | Student quick actions are present |
| PASS | A brand-new student sees zero counts and an empty-state message |
| PASS | A brand-new student sees empty states on My reports and My claims |
| PASS | All staff list pages and filters load without errors |
| PASS | Status history filter shows only matching changes |
| PASS | Manage reports search shows an empty state when nothing matches |
| PASS | Staff view of a report shows the reporter's contact details |

## 13. Database rules (SQLite constraints)

| Result | Check |
|---|---|
| PASS | PRAGMA foreign_keys is ON for connections |
| PASS | A report with a missing user is rejected by the foreign key |
| PASS | A report with a missing category is rejected by the foreign key |
| PASS | An invalid report status is rejected by a CHECK constraint |
| PASS | An invalid report type is rejected by a CHECK constraint |
| PASS | An invalid user role is rejected by a CHECK constraint |
| PASS | A duplicate user email is rejected by the UNIQUE constraint |
| PASS | A duplicate category name is rejected by the UNIQUE constraint |
| PASS | An invalid claim status is rejected by a CHECK constraint |
| PASS | Two active claims by one student on one report are blocked by a unique index |
| PASS | Two approved claims on one report are blocked by a unique index |
| PASS | A user who owns reports cannot be deleted (foreign key restrict) |
| PASS | A category used by reports cannot be deleted at the database level |
| PASS | A report with claims cannot be deleted at the database level |
| PASS | Indexes exist for searched and foreign-key columns |
| PASS | Timestamps use one consistent format (YYYY-MM-DD HH:MM:SS) |
| PASS | Every claim's status is separate from its item status (both columns exist independently) |
| PASS | Seed data: item reports and claims reference valid users and categories |
| PASS | No item is ever "returned" without a recorded history row |
| PASS | Every report's current status matches its latest history row |

## 14. Links, buttons, forms and page quality (crawler)

| Result | Check |
|---|---|
| PASS | No dead links for the student area (28 pages crawled) |
| PASS | No dead links for the staff area (55 pages crawled) |
| PASS | No dead links for the public area (4 pages crawled) |
| PASS | All CSS, JS, favicon and image URLs used by the pages load (3 checked) |
| PASS | Every POST form includes a CSRF token |
| PASS | Every form submits to a file that exists (13 form targets) |
| PASS | No inline <script>, style="" or onclick="" (the strict Content-Security-Policy would block them) |
| PASS | Every page has a title, an <h1> and lang="en" |
| PASS | Every form field has a visible <label> |
| PASS | JavaScript file is served and does not touch localStorage/sessionStorage |

## Final health checks

| Result | Check |
|---|---|
| PASS | Foreign-key check finds no broken references after all tests |
| PASS | SQLite integrity check passes |
| PASS | The server log contains no PHP warnings, notices or fatal errors |
