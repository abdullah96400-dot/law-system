<?php
/* 0010 — fee agreement advance and the office logo (used on due notices,
   statements and invoices). Only adds columns. */
return [
  'up' => [
    "ALTER TABLE fee_agreements ADD COLUMN advance INT NULL",
    "ALTER TABLE offices ADD COLUMN logo VARCHAR(80) NULL",
  ],
  'down' => ['mysql:ALTER TABLE fee_agreements DROP COLUMN advance', 'mysql:ALTER TABLE offices DROP COLUMN logo'],
];
