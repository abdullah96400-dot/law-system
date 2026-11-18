<?php
/* 0001 — foundation: offices, branches, staff, invitations, role permissions,
   sign-in throttling, and the append-only audit log.
   Every table except `offices` carries office_id. */
return [
  'up' => [
    "CREATE TABLE offices ({ID}, code VARCHAR(20) NOT NULL UNIQUE, name_ar VARCHAR(200) NOT NULL, name_en VARCHAR(200) NOT NULL,
      country CHAR(2) NOT NULL DEFAULT 'OM', status VARCHAR(12) NOT NULL DEFAULT 'active', plan VARCHAR(60) NULL, max_users INT NULL,
      require_2fa_all TINYINT NOT NULL DEFAULT 0, demo TINYINT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL){TAIL}",

    "CREATE TABLE branches ({ID}, office_id INT NOT NULL, name VARCHAR(160) NOT NULL, active TINYINT NOT NULL DEFAULT 1, created_at DATETIME NOT NULL,
      FOREIGN KEY (office_id) REFERENCES offices(id)){TAIL}",
    'CREATE INDEX idx_branches_office ON branches (office_id)',

    "CREATE TABLE staff ({ID}, office_id INT NOT NULL, email VARCHAR(190) NOT NULL UNIQUE, full_name VARCHAR(160) NOT NULL, phone VARCHAR(40) NULL,
      role VARCHAR(20) NOT NULL, branch_id INT NULL, lang VARCHAR(2) NOT NULL DEFAULT 'ar', status VARCHAR(10) NOT NULL DEFAULT 'invited',
      password_hash VARCHAR(255) NULL, password_set_at DATETIME NULL, totp_secret VARCHAR(64) NULL, totp_last INT NULL,
      failed_count INT NOT NULL DEFAULT 0, locked_until DATETIME NULL, session_ver INT NOT NULL DEFAULT 0, last_login DATETIME NULL,
      created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, FOREIGN KEY (office_id) REFERENCES offices(id)){TAIL}",
    'CREATE INDEX idx_staff_office ON staff (office_id)',

    "CREATE TABLE invites ({ID}, office_id INT NOT NULL, staff_id INT NOT NULL, token_hash CHAR(64) NOT NULL UNIQUE, expires_at DATETIME NOT NULL,
      used_at DATETIME NULL, created_by INT NULL, created_at DATETIME NOT NULL, FOREIGN KEY (office_id) REFERENCES offices(id), FOREIGN KEY (staff_id) REFERENCES staff(id)){TAIL}",
    'CREATE INDEX idx_invites_office ON invites (office_id)',

    "CREATE TABLE role_permissions ({ID}, office_id INT NOT NULL, role VARCHAR(20) NOT NULL, module VARCHAR(20) NOT NULL, action VARCHAR(10) NOT NULL,
      UNIQUE (office_id, role, module, action), FOREIGN KEY (office_id) REFERENCES offices(id)){TAIL}",

    "CREATE TABLE auth_failures ({ID}, ip_hash CHAR(64) NOT NULL, at DATETIME NOT NULL){TAIL}",
    'CREATE INDEX idx_auth_failures ON auth_failures (ip_hash, at)',

    "CREATE TABLE audit_log ({ID}, office_id INT NOT NULL, at DATETIME NOT NULL, staff_id INT NULL, actor VARCHAR(190) NULL, action VARCHAR(40) NOT NULL,
      entity VARCHAR(40) NULL, entity_id VARCHAR(40) NULL, detail TEXT NULL, ip VARCHAR(45) NULL, prev_hash CHAR(64) NOT NULL, hash CHAR(64) NOT NULL){TAIL}",
    'CREATE INDEX idx_audit_office ON audit_log (office_id, id)',
    // Append-only, even for the app's own database user
    "mysql:CREATE TRIGGER audit_log_no_update BEFORE UPDATE ON audit_log FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_log is append-only'",
    "mysql:CREATE TRIGGER audit_log_no_delete BEFORE DELETE ON audit_log FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_log is append-only'",
    "sqlite:CREATE TRIGGER audit_log_no_update BEFORE UPDATE ON audit_log BEGIN SELECT RAISE(ABORT, 'audit_log is append-only'); END",
    "sqlite:CREATE TRIGGER audit_log_no_delete BEFORE DELETE ON audit_log BEGIN SELECT RAISE(ABORT, 'audit_log is append-only'); END",
  ],
  // Undo (CLI only, after a backup). Drops this migration's tables — and their data.
  'down' => [
    'DROP TRIGGER IF EXISTS audit_log_no_update',
    'DROP TRIGGER IF EXISTS audit_log_no_delete',
    'DROP TABLE IF EXISTS audit_log',
    'DROP TABLE IF EXISTS auth_failures',
    'DROP TABLE IF EXISTS role_permissions',
    'DROP TABLE IF EXISTS invites',
    'DROP TABLE IF EXISTS staff',
    'DROP TABLE IF EXISTS branches',
    'DROP TABLE IF EXISTS offices',
  ],
];
