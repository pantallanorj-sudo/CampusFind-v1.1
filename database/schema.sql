-- CampusFind database schema (SQLite)
-- Run through database/setup.php, which also turns foreign keys ON for the connection.

PRAGMA foreign_keys = ON;

-- ---------------------------------------------------------------- users
CREATE TABLE users (
    id            INTEGER PRIMARY KEY,
    full_name     TEXT NOT NULL,
    email         TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    role          TEXT NOT NULL CHECK (role IN ('student', 'admin')),
    created_at    TEXT NOT NULL
);

-- ----------------------------------------------------------- categories
CREATE TABLE categories (
    id          INTEGER PRIMARY KEY,
    name        TEXT NOT NULL UNIQUE,
    description TEXT,
    created_at  TEXT NOT NULL
);

-- --------------------------------------------------------- item_reports
-- status values: open, claim_pending, awaiting_handover, returned, closed
CREATE TABLE item_reports (
    id            INTEGER PRIMARY KEY,
    user_id       INTEGER NOT NULL,
    category_id   INTEGER NOT NULL,
    report_type   TEXT NOT NULL CHECK (report_type IN ('lost', 'found')),
    item_name     TEXT NOT NULL,
    description   TEXT NOT NULL,
    location      TEXT NOT NULL,
    date_occurred TEXT NOT NULL,
    image_path    TEXT,
    status        TEXT NOT NULL
                  CHECK (status IN ('open', 'claim_pending', 'awaiting_handover', 'returned', 'closed')),
    created_at    TEXT NOT NULL,
    updated_at    TEXT NOT NULL,
    FOREIGN KEY (user_id)     REFERENCES users(id)      ON DELETE RESTRICT,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT
);

-- -------------------------------------------------------- claim_requests
-- A claim has its OWN status. It is separate from the item's status.
CREATE TABLE claim_requests (
    id             INTEGER PRIMARY KEY,
    item_report_id INTEGER NOT NULL,
    claimant_id    INTEGER NOT NULL,
    claim_details  TEXT NOT NULL,
    status         TEXT NOT NULL CHECK (status IN ('pending', 'approved', 'rejected', 'withdrawn')),
    reviewed_by    INTEGER,
    reviewed_at    TEXT,
    staff_notes    TEXT,
    created_at     TEXT NOT NULL,
    updated_at     TEXT NOT NULL,
    FOREIGN KEY (item_report_id) REFERENCES item_reports(id) ON DELETE RESTRICT,
    FOREIGN KEY (claimant_id)    REFERENCES users(id)        ON DELETE RESTRICT,
    FOREIGN KEY (reviewed_by)    REFERENCES users(id)        ON DELETE RESTRICT
);

-- ------------------------------------------------------- status_history
CREATE TABLE status_history (
    id             INTEGER PRIMARY KEY,
    item_report_id INTEGER NOT NULL,
    changed_by     INTEGER NOT NULL,
    old_status     TEXT,
    new_status     TEXT NOT NULL,
    notes          TEXT,
    created_at     TEXT NOT NULL,
    FOREIGN KEY (item_report_id) REFERENCES item_reports(id) ON DELETE RESTRICT,
    FOREIGN KEY (changed_by)     REFERENCES users(id)        ON DELETE RESTRICT
);

-- --------------------------------------------------------------- indexes
CREATE INDEX idx_item_reports_user      ON item_reports (user_id);
CREATE INDEX idx_item_reports_category  ON item_reports (category_id);
CREATE INDEX idx_item_reports_status    ON item_reports (status, report_type);
CREATE INDEX idx_item_reports_created   ON item_reports (created_at);
CREATE INDEX idx_item_reports_name      ON item_reports (item_name);

CREATE INDEX idx_claims_item            ON claim_requests (item_report_id);
CREATE INDEX idx_claims_claimant        ON claim_requests (claimant_id);
CREATE INDEX idx_claims_status          ON claim_requests (status);

CREATE INDEX idx_history_item           ON status_history (item_report_id, created_at);
CREATE INDEX idx_history_changed_by     ON status_history (changed_by);

-- Database-level safety nets for the claim rules (the PHP code checks them too):
--  1) one ACTIVE (pending or approved) claim per student per report
CREATE UNIQUE INDEX uq_claim_one_active_per_user
    ON claim_requests (item_report_id, claimant_id)
    WHERE status IN ('pending', 'approved');
--  2) never more than one approved claim for the same report
CREATE UNIQUE INDEX uq_claim_one_approved_per_item
    ON claim_requests (item_report_id)
    WHERE status = 'approved';
