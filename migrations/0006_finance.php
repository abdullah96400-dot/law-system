<?php
/* 0006 — fee agreements and instalments, invoices and lines, payments, expenses,
   the client trust (money held for clients) ledger, and invoice settings.
   Money is stored in baisa (1 OMR = 1000 baisa) as whole numbers. */
return [
  'up' => [
    "CREATE TABLE fee_agreements ({ID}, office_id INT NOT NULL, matter_id INT NOT NULL, client_id INT NOT NULL, kind VARCHAR(12) NOT NULL, total INT NULL, hourly_rate INT NULL,
      terms TEXT NULL, signed_on DATE NULL, document_id INT NULL, status VARCHAR(8) NOT NULL DEFAULT 'active', created_by INT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
      FOREIGN KEY (office_id) REFERENCES offices(id), FOREIGN KEY (matter_id) REFERENCES matters(id)){TAIL}",
    'CREATE INDEX idx_agreements_matter ON fee_agreements (office_id, matter_id)',
    "CREATE TABLE fee_installments ({ID}, office_id INT NOT NULL, agreement_id INT NOT NULL, label VARCHAR(160) NOT NULL, due_on DATE NULL, amount INT NOT NULL, invoice_id INT NULL,
      FOREIGN KEY (office_id) REFERENCES offices(id), FOREIGN KEY (agreement_id) REFERENCES fee_agreements(id)){TAIL}",

    "CREATE TABLE invoices ({ID}, office_id INT NOT NULL, number VARCHAR(20) NULL, matter_id INT NOT NULL, client_id INT NOT NULL, agreement_id INT NULL,
      status VARCHAR(8) NOT NULL DEFAULT 'draft', issued_on DATE NULL, due_on DATE NULL, subtotal INT NOT NULL DEFAULT 0, tax_rate_bp INT NOT NULL DEFAULT 0, tax INT NOT NULL DEFAULT 0,
      total INT NOT NULL DEFAULT 0, paid INT NOT NULL DEFAULT 0, notes TEXT NULL, portal_visible TINYINT NOT NULL DEFAULT 0, void_reason VARCHAR(300) NULL,
      created_by INT NULL, issued_by INT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE (office_id, number),
      FOREIGN KEY (office_id) REFERENCES offices(id), FOREIGN KEY (matter_id) REFERENCES matters(id)){TAIL}",
    'CREATE INDEX idx_invoices_matter ON invoices (office_id, matter_id)',
    'CREATE INDEX idx_invoices_status ON invoices (office_id, status, due_on)',
    "CREATE TABLE invoice_lines ({ID}, office_id INT NOT NULL, invoice_id INT NOT NULL, kind VARCHAR(8) NOT NULL, description VARCHAR(300) NOT NULL, qty INT NOT NULL DEFAULT 1,
      unit INT NOT NULL, amount INT NOT NULL, expense_id INT NULL, installment_id INT NULL, FOREIGN KEY (office_id) REFERENCES offices(id), FOREIGN KEY (invoice_id) REFERENCES invoices(id)){TAIL}",
    'CREATE INDEX idx_lines_invoice ON invoice_lines (office_id, invoice_id)',

    "CREATE TABLE payments ({ID}, office_id INT NOT NULL, invoice_id INT NOT NULL, amount INT NOT NULL, method VARCHAR(10) NOT NULL, reference VARCHAR(120) NULL,
      received_on DATE NOT NULL, receipt_no VARCHAR(20) NOT NULL, note VARCHAR(300) NULL, voided_at DATETIME NULL, voided_by INT NULL, void_reason VARCHAR(300) NULL,
      created_by INT NULL, created_at DATETIME NOT NULL, UNIQUE (office_id, receipt_no), FOREIGN KEY (office_id) REFERENCES offices(id), FOREIGN KEY (invoice_id) REFERENCES invoices(id)){TAIL}",
    'CREATE INDEX idx_payments_invoice ON payments (office_id, invoice_id)',

    "CREATE TABLE expenses ({ID}, office_id INT NOT NULL, matter_id INT NOT NULL, description VARCHAR(300) NOT NULL, amount INT NOT NULL, spent_on DATE NOT NULL,
      paid_from VARCHAR(6) NOT NULL DEFAULT 'office', billable TINYINT NOT NULL DEFAULT 1, invoice_line_id INT NULL, trust_entry_id INT NULL, document_id INT NULL,
      created_by INT NULL, created_at DATETIME NOT NULL, FOREIGN KEY (office_id) REFERENCES offices(id), FOREIGN KEY (matter_id) REFERENCES matters(id)){TAIL}",
    'CREATE INDEX idx_expenses_matter ON expenses (office_id, matter_id)',

    "CREATE TABLE trust_entries ({ID}, office_id INT NOT NULL, client_id INT NOT NULL, matter_id INT NULL, kind VARCHAR(4) NOT NULL, amount INT NOT NULL, on_date DATE NOT NULL,
      description VARCHAR(300) NOT NULL, reference VARCHAR(120) NULL, created_by INT NULL, created_at DATETIME NOT NULL,
      FOREIGN KEY (office_id) REFERENCES offices(id), FOREIGN KEY (client_id) REFERENCES contacts(id)){TAIL}",
    'CREATE INDEX idx_trust_client ON trust_entries (office_id, client_id)',

    "ALTER TABLE offices ADD COLUMN tax_rate_bp INT NOT NULL DEFAULT 0",
    "ALTER TABLE offices ADD COLUMN tax_label VARCHAR(60) NULL",
    "ALTER TABLE offices ADD COLUMN tax_no VARCHAR(60) NULL",
    "ALTER TABLE offices ADD COLUMN invoice_footer VARCHAR(600) NULL",
  ],
  'down' => [
    'DROP TABLE IF EXISTS trust_entries', 'DROP TABLE IF EXISTS expenses', 'DROP TABLE IF EXISTS payments', 'DROP TABLE IF EXISTS invoice_lines',
    'DROP TABLE IF EXISTS invoices', 'DROP TABLE IF EXISTS fee_installments', 'DROP TABLE IF EXISTS fee_agreements',
    'mysql:ALTER TABLE offices DROP COLUMN tax_rate_bp', 'mysql:ALTER TABLE offices DROP COLUMN tax_label', 'mysql:ALTER TABLE offices DROP COLUMN tax_no', 'mysql:ALTER TABLE offices DROP COLUMN invoice_footer',
  ],
];
