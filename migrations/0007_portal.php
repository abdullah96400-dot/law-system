<?php
/* 0007 — client portal: portal accounts, one-time sign-in codes, approved client
   updates, case messages, and document requests. */
return [
  'up' => [
    "CREATE TABLE portal_users ({ID}, office_id INT NOT NULL, contact_id INT NOT NULL, email VARCHAR(190) NOT NULL, lang VARCHAR(2) NOT NULL DEFAULT 'ar',
      status VARCHAR(10) NOT NULL DEFAULT 'active', notify TINYINT NOT NULL DEFAULT 1, session_ver INT NOT NULL DEFAULT 0, last_login DATETIME NULL,
      invited_by INT NULL, created_at DATETIME NOT NULL, UNIQUE (office_id, email), FOREIGN KEY (office_id) REFERENCES offices(id), FOREIGN KEY (contact_id) REFERENCES contacts(id)){TAIL}",
    "CREATE TABLE portal_codes ({ID}, office_id INT NOT NULL, portal_user_id INT NOT NULL, code_hash CHAR(64) NOT NULL, expires_at DATETIME NOT NULL, attempts INT NOT NULL DEFAULT 0,
      used_at DATETIME NULL, created_at DATETIME NOT NULL, FOREIGN KEY (office_id) REFERENCES offices(id)){TAIL}",
    'CREATE INDEX idx_portal_codes ON portal_codes (office_id, portal_user_id)',
    "CREATE TABLE client_updates ({ID}, office_id INT NOT NULL, matter_id INT NOT NULL, body TEXT NOT NULL, status VARCHAR(10) NOT NULL DEFAULT 'draft',
      created_by INT NOT NULL, approved_by INT NULL, published_at DATETIME NULL, created_at DATETIME NOT NULL, FOREIGN KEY (office_id) REFERENCES offices(id), FOREIGN KEY (matter_id) REFERENCES matters(id)){TAIL}",
    'CREATE INDEX idx_updates_matter ON client_updates (office_id, matter_id)',
    "CREATE TABLE portal_messages ({ID}, office_id INT NOT NULL, matter_id INT NOT NULL, portal_user_id INT NULL, staff_id INT NULL, body TEXT NOT NULL,
      read_by_staff_at DATETIME NULL, read_by_client_at DATETIME NULL, created_at DATETIME NOT NULL, FOREIGN KEY (office_id) REFERENCES offices(id), FOREIGN KEY (matter_id) REFERENCES matters(id)){TAIL}",
    'CREATE INDEX idx_pmsg_matter ON portal_messages (office_id, matter_id)',
    "CREATE TABLE doc_requests ({ID}, office_id INT NOT NULL, matter_id INT NOT NULL, title VARCHAR(200) NOT NULL, status VARCHAR(10) NOT NULL DEFAULT 'open',
      document_id INT NULL, created_by INT NOT NULL, created_at DATETIME NOT NULL, FOREIGN KEY (office_id) REFERENCES offices(id), FOREIGN KEY (matter_id) REFERENCES matters(id)){TAIL}",
    'CREATE INDEX idx_docreq_matter ON doc_requests (office_id, matter_id)',
  ],
  'down' => [
    'DROP TABLE IF EXISTS doc_requests', 'DROP TABLE IF EXISTS portal_messages', 'DROP TABLE IF EXISTS client_updates',
    'DROP TABLE IF EXISTS portal_codes', 'DROP TABLE IF EXISTS portal_users',
  ],
];
