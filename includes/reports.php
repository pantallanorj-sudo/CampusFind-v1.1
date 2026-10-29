<?php
/**
 * Item-report business logic: statuses, allowed transitions, CRUD, search, statistics.
 *
 * ITEM STATUSES (the state of the REPORT, separate from the state of any CLAIM):
 *   open               nobody has an active claim
 *   claim_pending      at least one student claim is waiting for staff review
 *   awaiting_handover  staff APPROVED a claim, but the item has not been handed over yet
 *   returned           staff recorded the real handover (final)
 *   closed             report archived (owner/staff closed it, or it went unclaimed)
 */
declare(strict_types=1);

/** Thrown when a business rule stops an action. The message is safe to show to the user. */
final class RuleViolation extends RuntimeException
{
}

/** Run $fn inside a database transaction (re-uses an open one). Everything succeeds or nothing is saved. */
function with_transaction(callable $fn)
{
    $pdo = db();
    if ($pdo->inTransaction()) {
        return $fn($pdo);
    }
    $pdo->beginTransaction();
    try {
        $result = $fn($pdo);
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

// ------------------------------------------------------------------ constants
function report_types(): array
{
    return ['lost' => 'Lost', 'found' => 'Found'];
}

function item_statuses(): array
{
    return [
        'open'              => 'Open',
        'claim_pending'     => 'Claim pending',
        'awaiting_handover' => 'Awaiting handover',
        'returned'          => 'Returned',
        'closed'            => 'Closed',
    ];
}

function item_status_descriptions(): array
{
    return [
        'open'              => 'Nobody has an active claim yet.',
        'claim_pending'     => 'A claim is waiting for staff review.',
        'awaiting_handover' => 'A claim was approved. The item has not been handed over yet.',
        'returned'          => 'Staff recorded that the item was handed over.',
        'closed'            => 'The report was archived without a handover.',
    ];
}

/** Statuses that mean "still unresolved". */
function unresolved_statuses(): array
{
    return ['open', 'claim_pending', 'awaiting_handover'];
}

/**
 * Every allowed status change, per report type. Anything not listed is rejected.
 * Lost reports never take claims. Found reports reach 'returned' only through a recorded handover.
 */
function item_status_transitions(string $type): array
{
    if ($type === 'lost') {
        return [
            'open'     => ['closed', 'returned'],
            'closed'   => ['open'],
            'returned' => [],
        ];
    }
    return [
        'open'              => ['claim_pending', 'closed'],
        'claim_pending'     => ['open', 'awaiting_handover', 'closed'],
        'awaiting_handover' => ['returned', 'open'],
        'closed'            => ['open'],
        'returned'          => [],
    ];
}

// -------------------------------------------------------------- status changes
function record_status_history(int $itemId, ?string $old, string $new, int $userId, ?string $notes): void
{
    $stmt = db()->prepare(
        'INSERT INTO status_history (item_report_id, changed_by, old_status, new_status, notes, created_at)
         VALUES (:item, :user, :old, :new, :notes, :created)'
    );
    $stmt->execute([
        ':item'    => $itemId,
        ':user'    => $userId,
        ':old'     => $old,
        ':new'     => $new,
        ':notes'   => $notes,
        ':created' => now(),
    ]);
}

/**
 * The single place where a report's status changes. It validates the transition,
 * updates the report and writes the history row together in one transaction.
 */
function change_item_status(int $itemId, string $newStatus, int $userId, ?string $notes = null): void
{
    if (!array_key_exists($newStatus, item_statuses())) {
        throw new RuleViolation('That status is not valid.');
    }
    with_transaction(function (PDO $pdo) use ($itemId, $newStatus, $userId, $notes): void {
        $stmt = $pdo->prepare('SELECT report_type, status FROM item_reports WHERE id = :id');
        $stmt->execute([':id' => $itemId]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new RuleViolation('The report was not found.');
        }
        $old = $row['status'];
        if ($old === $newStatus) {
            return;
        }
        $allowed = item_status_transitions($row['report_type'])[$old] ?? [];
        if (!in_array($newStatus, $allowed, true)) {
            throw new RuleViolation(sprintf(
                'A %s report cannot change from "%s" to "%s".',
                $row['report_type'],
                item_statuses()[$old] ?? $old,
                item_statuses()[$newStatus]
            ));
        }
        $upd = $pdo->prepare('UPDATE item_reports SET status = :status, updated_at = :now WHERE id = :id');
        $upd->execute([':status' => $newStatus, ':now' => now(), ':id' => $itemId]);
        record_status_history($itemId, $old, $newStatus, $userId, $notes);
    });
}

/** Status changes staff may choose by hand. Claim approval and handover go through the claim pages. */
function manual_status_options(array $report): array
{
    $options = [];
    $from = $report['status'];
    if ($from === 'open' || ($from === 'claim_pending' && $report['report_type'] === 'found')) {
        $options[] = 'closed';
    }
    if ($from === 'open' && $report['report_type'] === 'lost') {
        $options[] = 'returned';
    }
    if ($from === 'closed') {
        $options[] = 'open';
    }
    return $options;
}

function staff_change_item_status(int $itemId, string $newStatus, int $adminId, string $notes): void
{
    with_transaction(function () use ($itemId, $newStatus, $adminId, $notes): void {
        $report = find_report($itemId);
        if (!$report) {
            throw new RuleViolation('The report was not found.');
        }
        if (!in_array($newStatus, manual_status_options($report), true)) {
            throw new RuleViolation('That status change is not allowed for this report right now.');
        }
        if ($newStatus === 'closed') {
            // Closing a report ends any claims that are still waiting for review.
            reject_pending_claims_for_item($itemId, $adminId, 'The report was closed by staff, so this claim was ended.');
        }
        change_item_status($itemId, $newStatus, $adminId, $notes);
    });
}

function owner_close_report(int $itemId, int $userId): void
{
    with_transaction(function () use ($itemId, $userId): void {
        $report = find_report($itemId);
        if (!$report || (int) $report['user_id'] !== $userId) {
            throw new RuleViolation('The report was not found.');
        }
        if ($report['status'] !== 'open') {
            throw new RuleViolation('Only open reports can be closed. Ask staff for help with reports that have claim activity.');
        }
        change_item_status($itemId, 'closed', $userId, 'Closed by the report owner.');
    });
}

// ------------------------------------------------------------------ read data
/** One report without any reporter contact details (safe for students). */
function find_report(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT ir.*, c.name AS category_name
         FROM item_reports ir JOIN categories c ON c.id = ir.category_id
         WHERE ir.id = :id'
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** One report including the reporter's name and email. Staff pages only. */
function find_report_for_staff(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT ir.*, c.name AS category_name, u.full_name AS owner_name, u.email AS owner_email
         FROM item_reports ir
         JOIN categories c ON c.id = ir.category_id
         JOIN users u ON u.id = ir.user_id
         WHERE ir.id = :id'
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Students see everything except other people's closed reports. */
function report_visible_to(array $report, array $user): bool
{
    if ($user['role'] === 'admin' || (int) $report['user_id'] === (int) $user['id']) {
        return true;
    }
    return $report['status'] !== 'closed';
}

function history_for_report(int $itemId): array
{
    $stmt = db()->prepare(
        'SELECT sh.*, u.full_name AS changed_by_name, u.role AS changed_by_role
         FROM status_history sh JOIN users u ON u.id = sh.changed_by
         WHERE sh.item_report_id = :id
         ORDER BY sh.created_at ASC, sh.id ASC'
    );
    $stmt->execute([':id' => $itemId]);
    return $stmt->fetchAll();
}

function list_categories(): array
{
    return db()->query('SELECT id, name, description FROM categories ORDER BY name COLLATE NOCASE')->fetchAll();
}

function list_categories_with_counts(): array
{
    return db()->query(
        'SELECT c.*, (SELECT COUNT(*) FROM item_reports ir WHERE ir.category_id = c.id) AS report_count
         FROM categories c ORDER BY c.name COLLATE NOCASE'
    )->fetchAll();
}

/** Escape % and _ so they are searched as plain characters. */
function like_pattern(string $text): string
{
    return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $text) . '%';
}

/**
 * Search item reports.
 * Filters: mode (browse | mine | admin), user_id, q, type, category_id, status
 * (status may also be the shortcuts "unresolved" or "resolved" outside browse mode).
 * "browse" never returns closed reports and never selects reporter details.
 */
function search_reports(array $f, int $page, int $perPage): array
{
    $pdo    = db();
    $mode   = $f['mode'] ?? 'browse';
    $where  = ['1 = 1'];
    $params = [];

    $status = (string) ($f['status'] ?? '');
    if ($mode === 'browse') {
        if (in_array($status, ['open', 'claim_pending', 'awaiting_handover', 'returned'], true)) {
            $where[] = 'ir.status = :status';
            $params[':status'] = $status;
        } else {
            $where[] = "ir.status IN ('open', 'claim_pending', 'awaiting_handover')";
        }
    } elseif ($status === 'unresolved') {
        $where[] = "ir.status IN ('open', 'claim_pending', 'awaiting_handover')";
    } elseif ($status === 'resolved') {
        $where[] = "ir.status IN ('returned', 'closed')";
    } elseif ($status !== '' && array_key_exists($status, item_statuses())) {
        $where[] = 'ir.status = :status';
        $params[':status'] = $status;
    }

    if ($mode === 'mine') {
        $where[] = 'ir.user_id = :uid';
        $params[':uid'] = (int) ($f['user_id'] ?? 0);
    }
    if (in_array($f['type'] ?? '', ['lost', 'found'], true)) {
        $where[] = 'ir.report_type = :type';
        $params[':type'] = $f['type'];
    }
    if ((int) ($f['category_id'] ?? 0) > 0) {
        $where[] = 'ir.category_id = :cat';
        $params[':cat'] = (int) $f['category_id'];
    }

    $q = trim((string) ($f['q'] ?? ''));
    if ($q !== '') {
        $like = '(ir.item_name LIKE :q1 ESCAPE \'\\\' OR ir.description LIKE :q2 ESCAPE \'\\\' OR ir.location LIKE :q3 ESCAPE \'\\\'';
        $params[':q1'] = $params[':q2'] = $params[':q3'] = like_pattern($q);
        if ($mode === 'admin') {
            $like .= ' OR u.full_name LIKE :q4 ESCAPE \'\\\'';
            $params[':q4'] = like_pattern($q);
        }
        $where[] = $like . ')';
    }

    $ownerSelect = $mode === 'admin' ? ', u.full_name AS owner_name, u.email AS owner_email' : '';
    $from = 'FROM item_reports ir JOIN categories c ON c.id = ir.category_id'
          . ($mode === 'admin' ? ' JOIN users u ON u.id = ir.user_id' : '');
    $whereSql = implode(' AND ', $where);

    $count = $pdo->prepare("SELECT COUNT(*) $from WHERE $whereSql");
    $count->execute($params);
    $pagination = paginate((int) $count->fetchColumn(), $perPage, $page);

    $sql = "SELECT ir.*, c.name AS category_name,
                   (SELECT COUNT(*) FROM claim_requests cr
                     WHERE cr.item_report_id = ir.id AND cr.status = 'pending') AS pending_claims
                   $ownerSelect
            $from WHERE $whereSql
            ORDER BY ir.created_at DESC, ir.id DESC
            LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $name => $value) {
        $stmt->bindValue($name, $value);
    }
    $stmt->bindValue(':limit', $pagination['per_page'], PDO::PARAM_INT);
    $stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
    $stmt->execute();

    return ['items' => $stmt->fetchAll(), 'pagination' => $pagination];
}

/** Status history table for staff. Filters: q (item name or report number), status (new status). */
function search_status_history(array $f, int $page, int $perPage): array
{
    $pdo    = db();
    $where  = ['1 = 1'];
    $params = [];

    $status = (string) ($f['status'] ?? '');
    if ($status !== '' && array_key_exists($status, item_statuses())) {
        $where[] = 'sh.new_status = :status';
        $params[':status'] = $status;
    }
    $q = trim((string) ($f['q'] ?? ''));
    if ($q !== '') {
        $clause = 'ir.item_name LIKE :q ESCAPE \'\\\'';
        $params[':q'] = like_pattern($q);
        if (ctype_digit($q)) {
            $clause .= ' OR ir.id = :qid';
            $params[':qid'] = (int) $q;
        }
        $where[] = "($clause)";
    }
    $whereSql = implode(' AND ', $where);
    $from = 'FROM status_history sh
             JOIN item_reports ir ON ir.id = sh.item_report_id
             JOIN users u ON u.id = sh.changed_by';

    $count = $pdo->prepare("SELECT COUNT(*) $from WHERE $whereSql");
    $count->execute($params);
    $pagination = paginate((int) $count->fetchColumn(), $perPage, $page);

    $stmt = $pdo->prepare(
        "SELECT sh.*, ir.item_name, ir.report_type, u.full_name AS changed_by_name, u.role AS changed_by_role
         $from WHERE $whereSql
         ORDER BY sh.created_at DESC, sh.id DESC
         LIMIT :limit OFFSET :offset"
    );
    foreach ($params as $name => $value) {
        $stmt->bindValue($name, $value);
    }
    $stmt->bindValue(':limit', $pagination['per_page'], PDO::PARAM_INT);
    $stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
    $stmt->execute();

    return ['rows' => $stmt->fetchAll(), 'pagination' => $pagination];
}

// --------------------------------------------------------- owner permissions
/** Owners may edit only while the report is untouched (open). */
function owner_can_edit(array $report): bool
{
    return $report['status'] === 'open';
}

/** Why a report cannot be deleted, or null when deleting is safe. */
function delete_block_reason(array $report): ?string
{
    if ($report['status'] !== 'open') {
        return 'This report is "' . (item_statuses()[$report['status']] ?? $report['status'])
             . '", so it is kept for the record. Only open reports can be deleted.';
    }
    $stmt = db()->prepare('SELECT COUNT(*) FROM claim_requests WHERE item_report_id = :id');
    $stmt->execute([':id' => $report['id']]);
    if ((int) $stmt->fetchColumn() > 0) {
        return 'This report has claim activity, so it must be kept. You can close it instead.';
    }
    return null;
}

// ------------------------------------------------------------------------ CRUD
/** Create a report owned by $userId with status "open" and its first history row. Returns the new id. */
function create_report(int $userId, string $type, array $data, ?string $imagePath): int
{
    if (!array_key_exists($type, report_types())) {
        throw new RuleViolation('Report type must be lost or found.');
    }
    return with_transaction(function (PDO $pdo) use ($userId, $type, $data, $imagePath): int {
        $ts = now();
        $stmt = $pdo->prepare(
            'INSERT INTO item_reports
               (user_id, category_id, report_type, item_name, description, location, date_occurred, image_path, status, created_at, updated_at)
             VALUES (:user, :cat, :type, :name, :desc, :loc, :date, :img, :status, :created, :updated)'
        );
        $stmt->execute([
            ':user'    => $userId,
            ':cat'     => $data['category_id'],
            ':type'    => $type,
            ':name'    => $data['item_name'],
            ':desc'    => $data['description'],
            ':loc'     => $data['location'],
            ':date'    => $data['date_occurred'],
            ':img'     => $imagePath,
            ':status'  => 'open',
            ':created' => $ts,
            ':updated' => $ts,
        ]);
        $id = (int) $pdo->lastInsertId();
        record_status_history($id, null, 'open', $userId, 'Report created');
        return $id;
    });
}

/** Update an open report that belongs to $userId. Returns the old image path when it was replaced/removed. */
function update_report(int $reportId, int $userId, array $data, ?string $newImagePath, bool $removeImage): ?string
{
    return with_transaction(function (PDO $pdo) use ($reportId, $userId, $data, $newImagePath, $removeImage): ?string {
        $report = find_report($reportId);
        if (!$report || (int) $report['user_id'] !== $userId) {
            throw new RuleViolation('The report was not found.');
        }
        if (!owner_can_edit($report)) {
            throw new RuleViolation('Only open reports can be edited.');
        }
        $image    = $report['image_path'];
        $oldImage = null;
        if ($newImagePath !== null) {
            $oldImage = $image;
            $image    = $newImagePath;
        } elseif ($removeImage) {
            $oldImage = $image;
            $image    = null;
        }
        $stmt = $pdo->prepare(
            'UPDATE item_reports
             SET category_id = :cat, item_name = :name, description = :desc, location = :loc,
                 date_occurred = :date, image_path = :img, updated_at = :now
             WHERE id = :id AND user_id = :user AND status = \'open\''
        );
        $stmt->execute([
            ':cat'  => $data['category_id'],
            ':name' => $data['item_name'],
            ':desc' => $data['description'],
            ':loc'  => $data['location'],
            ':date' => $data['date_occurred'],
            ':img'  => $image,
            ':now'  => now(),
            ':id'   => $reportId,
            ':user' => $userId,
        ]);
        return $oldImage;
    });
}

/** Delete an eligible report. Returns the image path so the caller can remove the file. */
function delete_report(int $reportId, int $userId): ?string
{
    return with_transaction(function (PDO $pdo) use ($reportId, $userId): ?string {
        $report = find_report($reportId);
        if (!$report || (int) $report['user_id'] !== $userId) {
            throw new RuleViolation('The report was not found.');
        }
        $reason = delete_block_reason($report);
        if ($reason !== null) {
            throw new RuleViolation($reason);
        }
        // Only the "Report created" history row can exist for an untouched report.
        $pdo->prepare('DELETE FROM status_history WHERE item_report_id = :id')->execute([':id' => $reportId]);
        $pdo->prepare('DELETE FROM item_reports WHERE id = :id AND user_id = :user')
            ->execute([':id' => $reportId, ':user' => $userId]);
        return $report['image_path'];
    });
}

// ------------------------------------------------------------------ statistics
function student_stats(int $userId): array
{
    $pdo = db();
    $one = static function (string $sql, array $params) use ($pdo): int {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    };
    return [
        'total_reports'  => $one('SELECT COUNT(*) FROM item_reports WHERE user_id = :u', [':u' => $userId]),
        'open_reports'   => $one("SELECT COUNT(*) FROM item_reports WHERE user_id = :u AND status IN ('open','claim_pending','awaiting_handover')", [':u' => $userId]),
        'pending_claims' => $one("SELECT COUNT(*) FROM claim_requests WHERE claimant_id = :u AND status = 'pending'", [':u' => $userId]),
        // Items that were returned and that this student reported OR successfully claimed.
        'resolved'       => $one(
            "SELECT COUNT(DISTINCT ir.id)
             FROM item_reports ir
             LEFT JOIN claim_requests cr
                    ON cr.item_report_id = ir.id AND cr.claimant_id = :u1 AND cr.status = 'approved'
             WHERE ir.status = 'returned' AND (ir.user_id = :u2 OR cr.id IS NOT NULL)",
            [':u1' => $userId, ':u2' => $userId]
        ),
    ];
}

/** Latest changes to the student's own reports and claims. */
function student_recent_activity(int $userId, int $limit = 6): array
{
    $stmt = db()->prepare(
        "SELECT 'report' AS kind, ir.id AS ref_id, ir.id AS item_id, ir.item_name AS title, ir.status AS status, ir.updated_at AS ts
           FROM item_reports ir WHERE ir.user_id = :u1
         UNION ALL
         SELECT 'claim', cr.id, ir.id, ir.item_name, cr.status, cr.updated_at
           FROM claim_requests cr JOIN item_reports ir ON ir.id = cr.item_report_id
          WHERE cr.claimant_id = :u2
         ORDER BY ts DESC LIMIT :limit"
    );
    $stmt->bindValue(':u1', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':u2', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function admin_stats(): array
{
    $pdo = db();
    $one = static fn(string $sql): int => (int) $pdo->query($sql)->fetchColumn();
    return [
        'total_reports'     => $one('SELECT COUNT(*) FROM item_reports'),
        'open_reports'      => $one("SELECT COUNT(*) FROM item_reports WHERE status IN ('open','claim_pending','awaiting_handover')"),
        'pending_claims'    => $one("SELECT COUNT(*) FROM claim_requests WHERE status = 'pending'"),
        'awaiting_handover' => $one(
            "SELECT COUNT(*) FROM claim_requests cr JOIN item_reports ir ON ir.id = cr.item_report_id
             WHERE cr.status = 'approved' AND ir.status = 'awaiting_handover'"
        ),
        'resolved'          => $one("SELECT COUNT(*) FROM item_reports WHERE status IN ('returned','closed')"),
    ];
}
