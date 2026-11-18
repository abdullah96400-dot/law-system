<?php
/* 0008 — operations: support access grants, provisioning-API replay guard,
   plan limits that Al Imtyaz sets on an office. Nothing existing changes. */
return [
  'up' => [
    "CREATE TABLE support_grants ({ID}, office_id INT NOT NULL, token_hash CHAR(64) NOT NULL UNIQUE, reason VARCHAR(300) NOT NULL, expires_at DATETIME NOT NULL,
      created_by INT NOT NULL, revoked_at DATETIME NULL, revoked_by INT NULL, first_used_at DATETIME NULL, last_used_at DATETIME NULL, created_at DATETIME NOT NULL,
      FOREIGN KEY (office_id) REFERENCES offices(id)){TAIL}",
    'CREATE INDEX idx_support_office ON support_grants (office_id)',
    "CREATE TABLE api_nonces ({ID}, nonce CHAR(32) NOT NULL UNIQUE, at DATETIME NOT NULL){TAIL}",
    'CREATE INDEX idx_api_nonces_at ON api_nonces (at)',
    'ALTER TABLE offices ADD COLUMN max_storage_mb INT NULL',
    'ALTER TABLE offices ADD COLUMN paid_until DATE NULL',
    'ALTER TABLE offices ADD COLUMN account_ref VARCHAR(40) NULL',
  ],
  'down' => [
    'DROP TABLE IF EXISTS api_nonces', 'DROP TABLE IF EXISTS support_grants',
    'mysql:ALTER TABLE offices DROP COLUMN max_storage_mb', 'mysql:ALTER TABLE offices DROP COLUMN paid_until', 'mysql:ALTER TABLE offices DROP COLUMN account_ref',
  ],
];
