<?php
/* 0004 — appointments (hearings, meetings, deadlines), tasks, task templates, in-app notifications. */
return [
  'up' => [
    "CREATE TABLE appointments ({ID}, office_id INT NOT NULL, matter_id INT NULL, kind VARCHAR(10) NOT NULL, title VARCHAR(200) NOT NULL,
      starts_at DATETIME NOT NULL, ends_at DATETIME NULL, court_id INT NULL, circuit VARCHAR(120) NULL, location VARCHAR(200) NULL, lead_id INT NOT NULL,
      source VARCHAR(16) NOT NULL, source_note VARCHAR(300) NULL, entered_by INT NOT NULL, reviewed_by INT NULL, reviewed_at DATETIME NULL,
      status VARCHAR(12) NOT NULL DEFAULT 'scheduled', outcome TEXT NULL, outcome_by INT NULL, outcome_at DATETIME NULL, next_step VARCHAR(300) NULL,
      remind_days VARCHAR(20) NOT NULL DEFAULT '1,3', portal_visible TINYINT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
      FOREIGN KEY (office_id) REFERENCES offices(id), FOREIGN KEY (matter_id) REFERENCES matters(id)){TAIL}",
    'CREATE INDEX idx_appt_when ON appointments (office_id, starts_at)',
    'CREATE INDEX idx_appt_matter ON appointments (office_id, matter_id)',

    "CREATE TABLE tasks ({ID}, office_id INT NOT NULL, matter_id INT NULL, appointment_id INT NULL, title VARCHAR(200) NOT NULL, details TEXT NULL,
      assignee_id INT NOT NULL, reviewer_id INT NULL, due_on DATE NULL, priority VARCHAR(6) NOT NULL DEFAULT 'normal', status VARCHAR(12) NOT NULL DEFAULT 'open',
      recur VARCHAR(8) NOT NULL DEFAULT 'none', done_at DATETIME NULL, done_by INT NULL, done_note TEXT NULL, escalated_at DATETIME NULL,
      created_by INT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, FOREIGN KEY (office_id) REFERENCES offices(id)){TAIL}",
    'CREATE INDEX idx_tasks_assignee ON tasks (office_id, assignee_id, status)',
    'CREATE INDEX idx_tasks_matter ON tasks (office_id, matter_id)',

    "CREATE TABLE task_templates ({ID}, office_id INT NOT NULL, name VARCHAR(160) NOT NULL, items TEXT NOT NULL, active TINYINT NOT NULL DEFAULT 1,
      created_at DATETIME NOT NULL, FOREIGN KEY (office_id) REFERENCES offices(id)){TAIL}",

    "CREATE TABLE notifications ({ID}, office_id INT NOT NULL, staff_id INT NOT NULL, kind VARCHAR(20) NOT NULL, title VARCHAR(300) NOT NULL, link VARCHAR(200) NULL,
      dedupe VARCHAR(80) NOT NULL, read_at DATETIME NULL, emailed_at DATETIME NULL, created_at DATETIME NOT NULL, UNIQUE (office_id, staff_id, dedupe),
      FOREIGN KEY (office_id) REFERENCES offices(id)){TAIL}",
    'CREATE INDEX idx_notif_staff ON notifications (office_id, staff_id, read_at)',

    "ALTER TABLE offices ADD COLUMN escalate_days INT NULL",
  ],
  'down' => [
    'DROP TABLE IF EXISTS notifications',
    'DROP TABLE IF EXISTS task_templates',
    'DROP TABLE IF EXISTS tasks',
    'DROP TABLE IF EXISTS appointments',
    'sqlite:SELECT 1',
    'mysql:ALTER TABLE offices DROP COLUMN escalate_days',
  ],
];
