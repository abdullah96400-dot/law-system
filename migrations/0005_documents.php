<?php
/* 0005 — documents, their versions (stored encrypted), and time-limited share links. */
return [
  'up' => [
    "CREATE TABLE documents ({ID}, office_id INT NOT NULL, matter_id INT NULL, contact_id INT NULL, title VARCHAR(200) NOT NULL, category_id INT NULL,
      current_version INT NOT NULL DEFAULT 1, source VARCHAR(8) NOT NULL DEFAULT 'staff', portal_visible TINYINT NOT NULL DEFAULT 0, archived TINYINT NOT NULL DEFAULT 0,
      created_by INT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, FOREIGN KEY (office_id) REFERENCES offices(id)){TAIL}",
    'CREATE INDEX idx_docs_matter ON documents (office_id, matter_id)',
    'CREATE INDEX idx_docs_contact ON documents (office_id, contact_id)',

    "CREATE TABLE document_versions ({ID}, office_id INT NOT NULL, document_id INT NOT NULL, version INT NOT NULL, stored_name CHAR(40) NOT NULL, orig_name VARCHAR(255) NOT NULL,
      ext VARCHAR(8) NOT NULL, mime VARCHAR(100) NOT NULL, size INT NOT NULL, sha256 CHAR(64) NOT NULL, enc TINYINT NOT NULL DEFAULT 1, note VARCHAR(300) NULL,
      uploaded_by INT NULL, uploaded_by_client INT NULL, uploaded_at DATETIME NOT NULL, UNIQUE (office_id, document_id, version),
      FOREIGN KEY (office_id) REFERENCES offices(id), FOREIGN KEY (document_id) REFERENCES documents(id)){TAIL}",

    "CREATE TABLE document_shares ({ID}, office_id INT NOT NULL, document_id INT NOT NULL, token_hash CHAR(64) NOT NULL UNIQUE, label VARCHAR(160) NULL,
      expires_at DATETIME NOT NULL, max_downloads INT NULL, downloads INT NOT NULL DEFAULT 0, revoked_at DATETIME NULL, revoked_by INT NULL,
      created_by INT NOT NULL, created_at DATETIME NOT NULL, FOREIGN KEY (office_id) REFERENCES offices(id), FOREIGN KEY (document_id) REFERENCES documents(id)){TAIL}",
    'CREATE INDEX idx_shares_doc ON document_shares (office_id, document_id)',
  ],
  'down' => [
    'DROP TABLE IF EXISTS document_shares',
    'DROP TABLE IF EXISTS document_versions',
    'DROP TABLE IF EXISTS documents',
  ],
];
