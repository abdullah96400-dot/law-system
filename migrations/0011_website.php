<?php
/* 0011 — the office's public website and its incoming requests.
   office_sites: one row per office; content is edited as a draft and copied to
   live on publish. office_domains: extra addresses (an office's own domain).
   site_requests / site_request_notes: requests sent from the website.
   Also gives existing offices the default "website" permissions. Only adds. */
return [
  'up' => [
    "CREATE TABLE office_sites ({ID}, office_id INT NOT NULL, draft TEXT NULL, live TEXT NULL, published TINYINT NOT NULL DEFAULT 0, published_at DATETIME NULL,
      published_by INT NULL, updated_by INT NULL, updated_at DATETIME NOT NULL, FOREIGN KEY (office_id) REFERENCES offices(id)){TAIL}",
    'CREATE UNIQUE INDEX uq_sites_office ON office_sites (office_id)',
    "CREATE TABLE office_domains ({ID}, office_id INT NOT NULL, host VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, FOREIGN KEY (office_id) REFERENCES offices(id)){TAIL}",
    'CREATE UNIQUE INDEX uq_domains_host ON office_domains (host)',
    "CREATE TABLE site_requests ({ID}, office_id INT NOT NULL, ref VARCHAR(30) NOT NULL, service_key VARCHAR(16) NOT NULL, service_title VARCHAR(200) NOT NULL,
      name VARCHAR(160) NOT NULL, phone VARCHAR(40) NOT NULL, phone_norm VARCHAR(20) NULL, email VARCHAR(190) NULL, message TEXT NULL, lang VARCHAR(2) NOT NULL DEFAULT 'ar',
      status VARCHAR(10) NOT NULL DEFAULT 'new', assignee_id INT NULL, contact_id INT NULL, check_id INT NULL, matter_id INT NULL, ip_hash VARCHAR(64) NULL,
      created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, closed_at DATETIME NULL, FOREIGN KEY (office_id) REFERENCES offices(id)){TAIL}",
    'CREATE UNIQUE INDEX uq_requests_ref ON site_requests (office_id, ref)',
    'CREATE INDEX idx_requests_status ON site_requests (office_id, status, created_at)',
    "CREATE TABLE site_request_notes ({ID}, office_id INT NOT NULL, request_id INT NOT NULL, staff_id INT NULL, kind VARCHAR(10) NOT NULL DEFAULT 'note', body TEXT NOT NULL,
      created_at DATETIME NOT NULL, FOREIGN KEY (office_id) REFERENCES offices(id), FOREIGN KEY (request_id) REFERENCES site_requests(id)){TAIL}",
    'CREATE INDEX idx_rnotes_req ON site_request_notes (office_id, request_id)',
    "INSERT INTO role_permissions (office_id, role, module, action) SELECT id, 'office_manager', 'website', 'view' FROM offices",
    "INSERT INTO role_permissions (office_id, role, module, action) SELECT id, 'office_manager', 'website', 'edit' FROM offices",
    "INSERT INTO role_permissions (office_id, role, module, action) SELECT id, 'office_manager', 'website', 'create' FROM offices",
    "INSERT INTO role_permissions (office_id, role, module, action) SELECT id, 'office_manager', 'website', 'share' FROM offices",
    "INSERT INTO role_permissions (office_id, role, module, action) SELECT id, 'coordinator', 'website', 'view' FROM offices",
    "INSERT INTO role_permissions (office_id, role, module, action) SELECT id, 'coordinator', 'website', 'edit' FROM offices",
    "INSERT INTO role_permissions (office_id, role, module, action) SELECT id, 'reception', 'website', 'view' FROM offices",
    "INSERT INTO role_permissions (office_id, role, module, action) SELECT id, 'reception', 'website', 'edit' FROM offices",
    "INSERT INTO role_permissions (office_id, role, module, action) SELECT id, 'lawyer', 'website', 'view' FROM offices",
  ],
  'down' => [
    "DELETE FROM role_permissions WHERE module = 'website'",
    'DROP TABLE IF EXISTS site_request_notes', 'DROP TABLE IF EXISTS site_requests', 'DROP TABLE IF EXISTS office_domains', 'DROP TABLE IF EXISTS office_sites',
  ],
];
