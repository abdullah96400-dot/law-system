<?php
/* 0009 — the case-file workspace: procedures and judgments, delegations between
   lawyers, the office template library, due notices, share log for the client
   portal, recycle bin, backup runs, court fees on expenses, plan features.
   Only adds tables and columns. */
return [
  'up' => [
    "CREATE TABLE matter_actions ({ID}, office_id INT NOT NULL, matter_id INT NOT NULL, kind VARCHAR(12) NOT NULL, title VARCHAR(200) NOT NULL, details TEXT NULL,
      action_on DATE NOT NULL, court_id INT NULL, source VARCHAR(16) NOT NULL, source_note VARCHAR(300) NULL, verified_by INT NULL, verified_at DATETIME NULL,
      portal_visible TINYINT NOT NULL DEFAULT 0, created_by INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
      FOREIGN KEY (office_id) REFERENCES offices(id), FOREIGN KEY (matter_id) REFERENCES matters(id)){TAIL}",
    'CREATE INDEX idx_actions_matter ON matter_actions (office_id, matter_id, action_on)',

    "CREATE TABLE delegations ({ID}, office_id INT NOT NULL, matter_id INT NOT NULL, appointment_id INT NULL, from_staff INT NOT NULL, to_staff INT NOT NULL,
      instructions TEXT NULL, status VARCHAR(10) NOT NULL DEFAULT 'draft', response_note VARCHAR(600) NULL, result_note TEXT NULL, responded_at DATETIME NULL, done_at DATETIME NULL,
      memo_document_id INT NULL, created_by INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
      FOREIGN KEY (office_id) REFERENCES offices(id), FOREIGN KEY (matter_id) REFERENCES matters(id)){TAIL}",
    'CREATE INDEX idx_deleg_to ON delegations (office_id, to_staff, status)',
    'CREATE INDEX idx_deleg_matter ON delegations (office_id, matter_id)',

    "CREATE TABLE doc_templates ({ID}, office_id INT NOT NULL, kind VARCHAR(12) NOT NULL, name VARCHAR(160) NOT NULL, body TEXT NOT NULL, version INT NOT NULL DEFAULT 1,
      active TINYINT NOT NULL DEFAULT 1, created_by INT NULL, created_at DATETIME NOT NULL, updated_by INT NULL, updated_at DATETIME NOT NULL,
      FOREIGN KEY (office_id) REFERENCES offices(id)){TAIL}",
    "CREATE TABLE doc_template_versions ({ID}, office_id INT NOT NULL, template_id INT NOT NULL, version INT NOT NULL, body TEXT NOT NULL, created_by INT NULL, created_at DATETIME NOT NULL,
      FOREIGN KEY (office_id) REFERENCES offices(id), FOREIGN KEY (template_id) REFERENCES doc_templates(id)){TAIL}",

    "CREATE TABLE due_notices ({ID}, office_id INT NOT NULL, client_id INT NOT NULL, matter_id INT NOT NULL, installment_id INT NULL, invoice_id INT NULL,
      amount INT NOT NULL, due_on DATE NULL, status VARCHAR(8) NOT NULL DEFAULT 'draft', channel VARCHAR(10) NULL, delivery VARCHAR(16) NULL, auto TINYINT NOT NULL DEFAULT 0,
      sent_at DATETIME NULL, sent_by INT NULL, created_by INT NULL, created_at DATETIME NOT NULL,
      FOREIGN KEY (office_id) REFERENCES offices(id), FOREIGN KEY (matter_id) REFERENCES matters(id)){TAIL}",
    'CREATE INDEX idx_notices_matter ON due_notices (office_id, matter_id)',

    "CREATE TABLE portal_shares ({ID}, office_id INT NOT NULL, matter_id INT NOT NULL, kind VARCHAR(12) NOT NULL, item_id INT NOT NULL, shared TINYINT NOT NULL,
      staff_id INT NULL, at DATETIME NOT NULL, FOREIGN KEY (office_id) REFERENCES offices(id)){TAIL}",
    'CREATE INDEX idx_shares_matter ON portal_shares (office_id, matter_id)',

    "CREATE TABLE trash ({ID}, office_id INT NOT NULL, kind VARCHAR(12) NOT NULL, item_id INT NOT NULL, matter_id INT NULL, label VARCHAR(300) NOT NULL, data TEXT NOT NULL,
      deleted_by INT NOT NULL, deleted_at DATETIME NOT NULL, restored_by INT NULL, restored_at DATETIME NULL,
      FOREIGN KEY (office_id) REFERENCES offices(id)){TAIL}",
    'CREATE INDEX idx_trash_office ON trash (office_id, restored_at, deleted_at)',

    "CREATE TABLE backup_runs ({ID}, kind VARCHAR(10) NOT NULL, ok TINYINT NOT NULL, size INT NULL, note VARCHAR(300) NULL, at DATETIME NOT NULL){TAIL}",

    "ALTER TABLE expenses ADD COLUMN category VARCHAR(10) NOT NULL DEFAULT 'expense'",
    "ALTER TABLE offices ADD COLUMN notice_days VARCHAR(20) NULL",
    "ALTER TABLE offices ADD COLUMN features TEXT NULL",
  ],
  'down' => [
    'DROP TABLE IF EXISTS backup_runs', 'DROP TABLE IF EXISTS trash', 'DROP TABLE IF EXISTS portal_shares', 'DROP TABLE IF EXISTS due_notices',
    'DROP TABLE IF EXISTS doc_template_versions', 'DROP TABLE IF EXISTS doc_templates', 'DROP TABLE IF EXISTS delegations', 'DROP TABLE IF EXISTS matter_actions',
    'mysql:ALTER TABLE expenses DROP COLUMN category', 'mysql:ALTER TABLE offices DROP COLUMN notice_days', 'mysql:ALTER TABLE offices DROP COLUMN features',
  ],
];
