<?php
/* 0003 — case files: office-editable lists, matters, their parties, team and timeline. */
return [
  'up' => [
    "CREATE TABLE lookups ({ID}, office_id INT NOT NULL, kind VARCHAR(12) NOT NULL, name_ar VARCHAR(160) NOT NULL, name_en VARCHAR(160) NOT NULL,
      is_closed TINYINT NOT NULL DEFAULT 0, active TINYINT NOT NULL DEFAULT 1, sort INT NOT NULL DEFAULT 0, FOREIGN KEY (office_id) REFERENCES offices(id)){TAIL}",
    'CREATE INDEX idx_lookups ON lookups (office_id, kind)',

    "CREATE TABLE matters ({ID}, office_id INT NOT NULL, ref VARCHAR(20) NOT NULL, title VARCHAR(200) NOT NULL, client_id INT NOT NULL, conflict_check_id INT NOT NULL,
      case_type_id INT NULL, court_id INT NULL, court_level_id INT NULL, circuit VARCHAR(120) NULL, official_no VARCHAR(80) NULL, status_id INT NOT NULL,
      lead_id INT NOT NULL, branch_id INT NULL, opened_on DATE NOT NULL, referral VARCHAR(200) NULL, summary TEXT NULL,
      next_step VARCHAR(300) NULL, next_due DATE NULL, restricted TINYINT NOT NULL DEFAULT 0, closed_at DATETIME NULL,
      created_by INT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
      UNIQUE (office_id, ref), UNIQUE (office_id, conflict_check_id),
      FOREIGN KEY (office_id) REFERENCES offices(id), FOREIGN KEY (client_id) REFERENCES contacts(id), FOREIGN KEY (conflict_check_id) REFERENCES conflict_checks(id)){TAIL}",
    'CREATE INDEX idx_matters_office ON matters (office_id, status_id)',
    'CREATE INDEX idx_matters_lead ON matters (office_id, lead_id)',

    "CREATE TABLE matter_parties ({ID}, office_id INT NOT NULL, matter_id INT NOT NULL, contact_id INT NOT NULL, role VARCHAR(16) NOT NULL, note VARCHAR(300) NULL,
      portal_visible TINYINT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL, UNIQUE (office_id, matter_id, contact_id, role),
      FOREIGN KEY (office_id) REFERENCES offices(id), FOREIGN KEY (matter_id) REFERENCES matters(id), FOREIGN KEY (contact_id) REFERENCES contacts(id)){TAIL}",
    'CREATE INDEX idx_parties_contact ON matter_parties (office_id, contact_id)',

    "CREATE TABLE matter_team ({ID}, office_id INT NOT NULL, matter_id INT NOT NULL, staff_id INT NOT NULL, created_at DATETIME NOT NULL, UNIQUE (office_id, matter_id, staff_id),
      FOREIGN KEY (office_id) REFERENCES offices(id), FOREIGN KEY (matter_id) REFERENCES matters(id), FOREIGN KEY (staff_id) REFERENCES staff(id)){TAIL}",
    'CREATE INDEX idx_team_staff ON matter_team (office_id, staff_id)',

    "CREATE TABLE matter_events ({ID}, office_id INT NOT NULL, matter_id INT NOT NULL, at DATETIME NOT NULL, staff_id INT NULL, kind VARCHAR(16) NOT NULL,
      body TEXT NULL, data TEXT NULL, FOREIGN KEY (office_id) REFERENCES offices(id), FOREIGN KEY (matter_id) REFERENCES matters(id)){TAIL}",
    'CREATE INDEX idx_events_matter ON matter_events (office_id, matter_id, id)',
  ],
  'down' => [
    'DROP TABLE IF EXISTS matter_events',
    'DROP TABLE IF EXISTS matter_team',
    'DROP TABLE IF EXISTS matter_parties',
    'DROP TABLE IF EXISTS matters',
    'DROP TABLE IF EXISTS lookups',
  ],
];
