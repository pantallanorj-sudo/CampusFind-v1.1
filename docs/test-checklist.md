# Manual test checklist

The automated suite (`php tests/run_tests.php`, results in `test-results.md`) covers requests, permissions,
business rules and the database. It cannot run JavaScript or judge layout, so use this checklist for those parts.
Items below are **not ticked**: tick them as you test.
During development a headless Chromium spot check already covered: desktop and phone rendering of the main pages, the mobile menu
(open, close with Escape), the Approve confirm dialog (Cancel leaves the claim pending), no console errors and no sideways overflow at
1280, 768 and 375 px. Please still repeat these in your own browser. Demo accounts and the password are in `README.md`.

## Browser-only checks (not covered by automation)

- [ ] Landing page looks right at desktop, tablet (about 768 px) and phone (about 375 px) widths.
- [ ] On a phone width, the staff and student menu opens from the menu button, closes with the overlay and the Escape key.
- [ ] On a phone width, tables turn into stacked cards and nothing scrolls sideways.
- [ ] Delete / close / withdraw / approve / reject / revoke / handover each show a confirmation prompt, and Cancel does nothing.
- [ ] Submit buttons disable and show a "working" label after a click, and Approve / Reject still send the right decision.
- [ ] The photo preview appears when a file is chosen, and an oversized file is refused with a message.
- [ ] The show/hide password button works on the login and register pages.
- [ ] The character counters update while typing.
- [ ] Flash messages can be dismissed with the close button.
- [ ] Tab through a form with the keyboard; the focus outline is visible and the skip link works.
- [ ] Status colours are distinguishable and text is readable (contrast).

## Authentication

- [ ] Valid login (student and staff) goes to the right dashboard.
- [ ] Wrong password and unknown email both show "Incorrect email or password."
- [ ] Registering with an existing email is refused.
- [ ] A student typing an `/admin/...` URL sees "Access denied".
- [ ] Logged out, any protected URL goes to the login page. After logout, the Back button does not reveal private pages.

## Item reports

- [ ] Create lost and found reports (with and without a photo); invalid input shows messages and keeps what was typed.
- [ ] Edit an open report; refresh and the changes remain.
- [ ] Another student cannot edit or delete your report (try the URL).
- [ ] Delete shows a confirmation page; a report with a claim cannot be deleted; closing is offered instead.

## Claims

- [ ] Submit a claim; submit again and see the duplicate refused.
- [ ] Try to claim your own found item, a lost item, a closed item and a returned item.
- [ ] As another student, claim details and staff notes are not visible anywhere.
- [ ] Staff approve: item shows Awaiting handover (not Returned). Record handover: item shows Returned.
- [ ] Staff reject: the report stays open. Revoke an approval: the report reopens.

## Staff tools

- [ ] Categories: add, edit, duplicate refused, delete unused, delete in-use blocked.
- [ ] Status history lists every change with the right person and time; filters work.
- [ ] Dashboard numbers match what the lists show.
