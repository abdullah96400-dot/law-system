<?php
/* 0002 — clients and parties (contacts), their searchable name forms, and
   conflict-of-interest checks with their matches and the reviewer's decision. */
return [
  'up' => [
    "CREATE TABLE contacts ({ID}, office_id INT NOT NULL, kind VARCHAR(10) NOT NULL, is_client TINYINT NOT NULL DEFAULT 0,
      name_ar VARCHAR(200) NULL, name_en VARCHAR(200) NULL, aliases VARCHAR(500) NULL,
      id_type VARCHAR(12) NULL, id_number VARCHAR(60) NULL, phone VARCHAR(40) NULL, phone_norm VARCHAR(20) NULL, email VARCHAR(190) NULL,
      pref_lang VARCHAR(2) NOT NULL DEFAULT 'ar', address VARCHAR(300) NULL, notes TEXT NULL, archived TINYINT NOT NULL DEFAULT 0,
      created_by INT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, FOREIGN KEY (office_id) REFERENCES offices(id)){TAIL}",
    'CREATE INDEX idx_contacts_office ON contacts (office_id, archived)',
    'CREATE INDEX idx_contacts_phone ON contacts (office_id, phone_norm)',

    "CREATE TABLE contact_names ({ID}, office_id INT NOT NULL, contact_id INT NOT NULL, norm VARCHAR(255) NOT NULL, source VARCHAR(10) NOT NULL,
      FOREIGN KEY (office_id) REFERENCES offices(id), FOREIGN KEY (contact_id) REFERENCES contacts(id)){TAIL}",
    'CREATE INDEX idx_contact_names ON contact_names (office_id, contact_id)',

    "CREATE TABLE conflict_checks ({ID}, office_id INT NOT NULL, ref VARCHAR(20) NOT NULL, subject VARCHAR(300) NOT NULL, status VARCHAR(12) NOT NULL DEFAULT 'pending',
      hit_count INT NOT NULL DEFAULT 0, decision_note TEXT NULL, decided_by INT NULL, decided_at DATETIME NULL,
      created_by INT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE (office_id, ref), FOREIGN KEY (office_id) REFERENCES offices(id)){TAIL}",
    'CREATE INDEX idx_checks_office ON conflict_checks (office_id, status)',

    "CREATE TABLE conflict_check_names ({ID}, office_id INT NOT NULL, check_id INT NOT NULL, role VARCHAR(10) NOT NULL, name VARCHAR(200) NOT NULL, norm VARCHAR(255) NOT NULL,
      FOREIGN KEY (office_id) REFERENCES offices(id), FOREIGN KEY (check_id) REFERENCES conflict_checks(id)){TAIL}",
    'CREATE INDEX idx_check_names ON conflict_check_names (office_id, check_id)',

    "CREATE TABLE conflict_hits ({ID}, office_id INT NOT NULL, check_id INT NOT NULL, name_id INT NOT NULL, contact_id INT NULL, other_check_id INT NULL,
      matched VARCHAR(200) NOT NULL, label VARCHAR(300) NOT NULL, score INT NOT NULL,
      FOREIGN KEY (office_id) REFERENCES offices(id), FOREIGN KEY (check_id) REFERENCES conflict_checks(id)){TAIL}",
    'CREATE INDEX idx_hits ON conflict_hits (office_id, check_id)',

    "CREATE TABLE counters ({ID}, office_id INT NOT NULL, k VARCHAR(20) NOT NULL, n INT NOT NULL, UNIQUE (office_id, k), FOREIGN KEY (office_id) REFERENCES offices(id)){TAIL}",
  ],
  'down' => [
    'DROP TABLE IF EXISTS counters',
    'DROP TABLE IF EXISTS conflict_hits',
    'DROP TABLE IF EXISTS conflict_check_names',
    'DROP TABLE IF EXISTS conflict_checks',
    'DROP TABLE IF EXISTS contact_names',
    'DROP TABLE IF EXISTS contacts',
  ],
];
