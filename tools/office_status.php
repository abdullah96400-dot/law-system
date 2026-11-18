<?php
/* Change an office's status. Nothing is deleted.
     php law/tools/office_status.php --code=SLL-001 --status=active|readonly|suspended */
require __DIR__ . '/_cli.php';
$o = row('SELECT * FROM offices WHERE code=?', [strtoupper((string)arg('code', ''))]); if (!$o) fail('No office with that code.');
$e = set_office_status((int)$o['id'], (string)arg('status', ''), 'Al Imtyaz (CLI)');
if ($e) fail($e);
out("{$o['code']}: {$o['status']} -> " . arg('status'));
