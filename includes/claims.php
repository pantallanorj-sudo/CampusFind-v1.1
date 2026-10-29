<?php
/**
 * Claim business logic.
 *
 * CLAIM STATUSES: pending -> approved | rejected | withdrawn.
 * A claim status is NOT the item status. Approving a claim moves the item to
 * "awaiting_handover". Only record_handover() moves it to "returned".
 *
 * Rule when a claim is approved: every OTHER pending claim for the same item is
 * automatically rejected (with a note), so there is never more than one approved claim.
 */
declare(strict_types=1);

function claim_statuses(): array
{
    return [
        'pending'   => 'Pending',
        'approved'  => 'Approved',
        'rejected'  => 'Rejected',
        'withdrawn' => 'Withdrawn',
    ];
}

function find_claim(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT cr.*,
                ir.item_name, ir.report_type, ir.status AS item_status, ir.user_id AS item_owner_id,
                ir.description AS item_description, ir.location AS item_location,
                ir.date_occurred AS item_date_occurred, ir.image_path AS item_image_path,
                cat.name AS category_name,
                claimant.full_name AS claimant_name, claimant.email AS claimant_email,
                reviewer.full_name AS reviewer_name
         FROM claim_requests cr
         JOIN item_reports ir      ON ir.id = cr.item_report_id
         JOIN categories cat       ON cat.id = ir.category_id
         JOIN users claimant       ON claimant.id = cr.claimant_id
         LEFT JOIN users reviewer  ON reviewer.id = cr.reviewed_by
         WHERE cr.id = :id'
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** All claims on one report (staff view). */
function claims_for_report(int $itemId): array
{
    $stmt = db()->prepare(
        'SELECT cr.*, claimant.full_name AS claimant_name
         FROM claim_requests cr JOIN users claimant ON claimant.id = cr.claimant_id
         WHERE cr.item_report_id = :id ORDER BY cr.created_at DESC, cr.id DESC'
    );
    $stmt->execute([':id' => $itemId]);
    return $stmt->fetchAll();
}

/** The student's own active (pending or approved) claim on a report, if any. */
function active_claim_for_user(int $itemId, int $userId): ?array
{
    $stmt = db()->prepare(
        "SELECT * FROM claim_requests
         WHERE item_report_id = :item AND claimant_id = :user AND status IN ('pending', 'approved')"
    );
    $stmt->execute([':item' => $itemId, ':user' => $userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Why this student cannot claim this report right now, or null when they can. */
function claim_block_reason(array $report, int $userId): ?string
{
    if ($report['report_type'] !== 'found') {
        return 'Only found-item reports can be claimed.';
    }
    if ((int) $report['user_id'] === $userId) {
        return 'You cannot claim your own found-item report.';
    }
    if ($report['status'] === 'returned') {
        return 'This item has already been returned to its owner.';
    }
    if ($report['status'] === 'closed') {
        return 'This report is closed and no longer accepts claims.';
    }
    if ($report['status'] === 'awaiting_handover') {
        return 'A claim for this item has already been approved and is waiting for handover.';
    }
    if (active_claim_for_user((int) $report['id'], $userId) !== null) {
        return 'You already have an active claim for this item.';
    }
    return null;
}

/** Submit a claim. Returns the new claim id. */
function submit_claim(int $itemId, int $userId, string $details): int
{
    return with_transaction(function (PDO $pdo) use ($itemId, $userId, $details): int {
        $report = find_report($itemId);
        if (!$report) {
            throw new RuleViolation('The report was not found.');
        }
        $reason = claim_block_reason($report, $userId);
        if ($reason !== null) {
            throw new RuleViolation($reason);
        }
        $ts = now();
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO claim_requests (item_report_id, claimant_id, claim_details, status, created_at, updated_at)
                 VALUES (:item, :user, :details, 'pending', :created, :updated)"
            );
            $stmt->execute([':item' => $itemId, ':user' => $userId, ':details' => $details, ':created' => $ts, ':updated' => $ts]);
        } catch (PDOException $e) {
            // The unique index caught a double-submit that raced past the check above.
            throw new RuleViolation('You already have an active claim for this item.');
        }
        $claimId = (int) $pdo->lastInsertId();
        if ($report['status'] === 'open') {
            change_item_status($itemId, 'claim_pending', $userId, 'A claim was submitted (claim #' . $claimId . ').');
        }
        return $claimId;
    });
}

/** After a claim ends, put the item back to "open" if no other claim is waiting. */
function release_item_if_no_pending_claims(int $itemId, int $userId, string $notes): void
{
    $report = find_report($itemId);
    if (!$report || $report['status'] !== 'claim_pending') {
        return;
    }
    $stmt = db()->prepare("SELECT COUNT(*) FROM claim_requests WHERE item_report_id = :id AND status = 'pending'");
    $stmt->execute([':id' => $itemId]);
    if ((int) $stmt->fetchColumn() === 0) {
        change_item_status($itemId, 'open', $userId, $notes);
    }
}

/** Reject every pending claim of an item (optionally except one). Returns how many were rejected. */
function reject_pending_claims_for_item(int $itemId, int $adminId, string $notes, ?int $exceptClaimId = null): int
{
    $ts = now();
    $stmt = db()->prepare(
        "UPDATE claim_requests
         SET status = 'rejected', reviewed_by = :admin, reviewed_at = :ts, staff_notes = :notes, updated_at = :ts2
         WHERE item_report_id = :item AND status = 'pending' AND id != :except"
    );
    $stmt->execute([
        ':admin'  => $adminId,
        ':ts'     => $ts,
        ':ts2'    => $ts,
        ':notes'  => $notes,
        ':item'   => $itemId,
        ':except' => $exceptClaimId ?? 0,
    ]);
    return $stmt->rowCount();
}

function withdraw_claim(int $claimId, int $userId): void
{
    with_transaction(function (PDO $pdo) use ($claimId, $userId): void {
        $claim = find_claim($claimId);
        if (!$claim || (int) $claim['claimant_id'] !== $userId) {
            throw new RuleViolation('The claim was not found.');
        }
        if ($claim['status'] !== 'pending') {
            throw new RuleViolation('Only pending claims can be withdrawn.');
        }
        $stmt = $pdo->prepare(
            "UPDATE claim_requests SET status = 'withdrawn', updated_at = :now
             WHERE id = :id AND claimant_id = :user AND status = 'pending'"
        );
        $stmt->execute([':now' => now(), ':id' => $claimId, ':user' => $userId]);
        release_item_if_no_pending_claims((int) $claim['item_report_id'], $userId, 'A claim was withdrawn. The report is open for new claims.');
    });
}

function approve_claim(int $claimId, int $adminId, string $notes): void
{
    with_transaction(function (PDO $pdo) use ($claimId, $adminId, $notes): void {
        $claim = find_claim($claimId);
        if (!$claim) {
            throw new RuleViolation('The claim was not found.');
        }
        if ($claim['status'] !== 'pending') {
            throw new RuleViolation('Only pending claims can be approved.');
        }
        if ($claim['report_type'] !== 'found' || $claim['item_status'] !== 'claim_pending') {
            throw new RuleViolation('This item is "' . (item_statuses()[$claim['item_status']] ?? $claim['item_status'])
                . '", so a claim cannot be approved for it.');
        }
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM claim_requests WHERE item_report_id = :id AND status = 'approved'");
        $stmt->execute([':id' => $claim['item_report_id']]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new RuleViolation('Another claim for this item is already approved.');
        }

        $ts = now();
        $upd = $pdo->prepare(
            "UPDATE claim_requests
             SET status = 'approved', reviewed_by = :admin, reviewed_at = :ts, staff_notes = :notes, updated_at = :ts2
             WHERE id = :id AND status = 'pending'"
        );
        $upd->execute([':admin' => $adminId, ':ts' => $ts, ':ts2' => $ts, ':notes' => $notes, ':id' => $claimId]);
        if ($upd->rowCount() !== 1) {
            throw new RuleViolation('The claim was changed by someone else. Reload the page and try again.');
        }

        // Defined rule: only one claim can win, so other pending claims are rejected with a note.
        reject_pending_claims_for_item(
            (int) $claim['item_report_id'], $adminId,
            'Automatically rejected because another claim for this item was approved.', $claimId
        );

        // Approval is NOT a return. The item now waits for the real handover.
        change_item_status((int) $claim['item_report_id'], 'awaiting_handover', $adminId,
            'Claim #' . $claimId . ' approved. Waiting for the claimant to collect the item.');
    });
}

function reject_claim(int $claimId, int $adminId, string $notes): void
{
    with_transaction(function (PDO $pdo) use ($claimId, $adminId, $notes): void {
        $claim = find_claim($claimId);
        if (!$claim) {
            throw new RuleViolation('The claim was not found.');
        }
        if ($claim['status'] !== 'pending') {
            throw new RuleViolation('Only pending claims can be rejected.');
        }
        $ts = now();
        $upd = $pdo->prepare(
            "UPDATE claim_requests
             SET status = 'rejected', reviewed_by = :admin, reviewed_at = :ts, staff_notes = :notes, updated_at = :ts2
             WHERE id = :id AND status = 'pending'"
        );
        $upd->execute([':admin' => $adminId, ':ts' => $ts, ':ts2' => $ts, ':notes' => $notes, ':id' => $claimId]);
        if ($upd->rowCount() !== 1) {
            throw new RuleViolation('The claim was changed by someone else. Reload the page and try again.');
        }
        // Rejecting a claim never closes the report. It only goes back to "open" when no other claim is waiting.
        release_item_if_no_pending_claims((int) $claim['item_report_id'], $adminId,
            'Claim #' . $claimId . ' rejected. The report is open for new claims.');
    });
}

/** Undo an approval before handover (for example the claimant never shows up). */
function revoke_claim(int $claimId, int $adminId, string $notes): void
{
    with_transaction(function (PDO $pdo) use ($claimId, $adminId, $notes): void {
        $claim = find_claim($claimId);
        if (!$claim) {
            throw new RuleViolation('The claim was not found.');
        }
        if ($claim['status'] !== 'approved' || $claim['item_status'] !== 'awaiting_handover') {
            throw new RuleViolation('Only an approved claim that is still waiting for handover can be revoked.');
        }
        $ts = now();
        $pdo->prepare(
            "UPDATE claim_requests
             SET status = 'rejected', reviewed_by = :admin, reviewed_at = :ts, staff_notes = :notes, updated_at = :ts2
             WHERE id = :id AND status = 'approved'"
        )->execute([':admin' => $adminId, ':ts' => $ts, ':ts2' => $ts, ':notes' => 'Approval revoked: ' . $notes, ':id' => $claimId]);
        change_item_status((int) $claim['item_report_id'], 'open', $adminId,
            'Approval of claim #' . $claimId . ' was revoked before handover. ' . $notes);
    });
}

/** Record the REAL handover. This is the only way a found item becomes "returned". */
function record_handover(int $claimId, int $adminId, string $notes): void
{
    with_transaction(function (PDO $pdo) use ($claimId, $adminId, $notes): void {
        $claim = find_claim($claimId);
        if (!$claim) {
            throw new RuleViolation('The claim was not found.');
        }
        if ($claim['status'] !== 'approved' || $claim['item_status'] !== 'awaiting_handover') {
            throw new RuleViolation('A handover can only be recorded for an approved claim whose item is awaiting handover.');
        }
        $message = 'Handed over to ' . $claim['claimant_name'] . ' (claim #' . $claimId . ').';
        if ($notes !== '') {
            $message .= ' ' . $notes;
        }
        change_item_status((int) $claim['item_report_id'], 'returned', $adminId, $message);
        $pdo->prepare('UPDATE claim_requests SET updated_at = :now WHERE id = :id')
            ->execute([':now' => now(), ':id' => $claimId]);
    });
}

/**
 * List claims.
 * Filters: mode (mine | admin), claimant_id, status, q (item name, claimant name or email).
 */
function search_claims(array $f, int $page, int $perPage): array
{
    $pdo    = db();
    $mode   = $f['mode'] ?? 'admin';
    $where  = ['1 = 1'];
    $params = [];

    if ($mode === 'mine') {
        $where[] = 'cr.claimant_id = :uid';
        $params[':uid'] = (int) ($f['claimant_id'] ?? 0);
    }
    $status = (string) ($f['status'] ?? '');
    if ($status !== '' && array_key_exists($status, claim_statuses())) {
        $where[] = 'cr.status = :status';
        $params[':status'] = $status;
    }
    $q = trim((string) ($f['q'] ?? ''));
    if ($q !== '') {
        $clause = 'ir.item_name LIKE :q1 ESCAPE \'\\\'';
        $params[':q1'] = like_pattern($q);
        if ($mode === 'admin') {
            $clause .= ' OR claimant.full_name LIKE :q2 ESCAPE \'\\\' OR claimant.email LIKE :q3 ESCAPE \'\\\'';
            $params[':q2'] = $params[':q3'] = like_pattern($q);
        }
        $where[] = "($clause)";
    }
    $whereSql = implode(' AND ', $where);
    $from = 'FROM claim_requests cr
             JOIN item_reports ir ON ir.id = cr.item_report_id
             JOIN users claimant ON claimant.id = cr.claimant_id';

    $count = $pdo->prepare("SELECT COUNT(*) $from WHERE $whereSql");
    $count->execute($params);
    $pagination = paginate((int) $count->fetchColumn(), $perPage, $page);

    $stmt = $pdo->prepare(
        "SELECT cr.*, ir.item_name, ir.report_type, ir.status AS item_status,
                claimant.full_name AS claimant_name, claimant.email AS claimant_email
         $from WHERE $whereSql
         ORDER BY cr.created_at DESC, cr.id DESC
         LIMIT :limit OFFSET :offset"
    );
    foreach ($params as $name => $value) {
        $stmt->bindValue($name, $value);
    }
    $stmt->bindValue(':limit', $pagination['per_page'], PDO::PARAM_INT);
    $stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
    $stmt->execute();

    return ['claims' => $stmt->fetchAll(), 'pagination' => $pagination];
}
