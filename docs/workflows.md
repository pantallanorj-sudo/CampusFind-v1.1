# Main user workflows

## Student

1. **Register or log in.** Registration always creates a student account.
2. **Report an item.** Choose *Report lost item* or *Report found item*. Fill in name, category, date, location, description and an optional photo.
   The report starts as `open` and a history row is written.
3. **Browse.** Search by text and filter by type, category and status. Closed reports are not listed.
4. **Claim.** On a found item, choose *Claim this item* and describe details only the owner would know.
   You cannot claim your own report, a lost report, a returned or closed report, or an item with an approved claim, and you cannot have two active claims on one item.
5. **Follow the claim** in *My claims*. Pending claims can be withdrawn. When a claim is approved, bring a valid ID to the Lost & Found Desk.
6. **Manage reports.** Open reports can be edited, closed or (if there is no claim activity) deleted after a confirmation page.
   Reports with claim activity are kept for the record.

## Staff

1. **Log in** (same form). The dashboard shows totals, recent reports and pending claims.
2. **Review a claim** (*Manage claims* then *Review*). Compare the private details with the item, write staff notes (required; the claimant can read them) and approve or reject.
   - Approve: claim `approved`, item `awaiting_handover`, other pending claims rejected automatically.
   - Reject: claim `rejected`; the item returns to `open` if no other claim is pending. It is never closed by a rejection.
3. **Record the handover** after the claimant collects the item. Only now does the item become `returned`.
   If the claimant never comes, use **Revoke approval** (reason required) to reopen the report.
4. **Manage reports:** search, view reporter details and history, close or reopen reports, mark a lost item as recovered.
5. **Manage categories** and read the **Status history**.

## End-to-end example (covered by the integration test)

1. Juan reports a found item. 2. Ana browses and finds it. 3. Ana submits a claim (item: `claim_pending`).
4. Staff open the claim. 5. Staff approve it (claim `approved`, item `awaiting_handover`).
6. Staff record the handover (item `returned`, claim stays `approved`).
7. History shows `open`, `claim_pending`, `awaiting_handover`, `returned` with the people who made each change.
8. Ana and Juan see the updated statuses and dashboard counts.
