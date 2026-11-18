<?php
/* Open an encrypted backup (from the daily job or /backup/latest) with files_key from config.php.
     php law/tools/backup_decrypt.php --in=law-20261118-020000.jsonl.gz.enc --out=backup.jsonl.gz
   The result is gzip JSON lines: one header line, then {"t": table, "r": row} per row. Keep it as safe as the database. */
require __DIR__ . '/_cli.php';
$in = (string)arg('in', ''); $outPath = (string)arg('out', '');
if ($in === '' || $outPath === '' || !is_file($in)) fail('Use --in=<file.enc> --out=<file.jsonl.gz>');
$key = files_key(); if (!$key) fail('files_key is missing or wrong in config.php');
$out = fopen($outPath, 'wb'); $ok = crypt_decrypt_file($in, $out, $key); fclose($out);
if (!$ok) { @unlink($outPath); fail('The file could not be opened: wrong files_key, or the file is damaged.'); }
out("Opened: $outPath");
