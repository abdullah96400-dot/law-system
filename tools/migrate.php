<?php
/* Database migrations.
     php law/tools/migrate.php status
     php law/tools/migrate.php up
     php law/tools/migrate.php down --backup-confirmed     (undoes the latest one; deletes its tables' data) */
require __DIR__ . '/_cli.php';
$cmd = $argv[1] ?? 'status';
if ($cmd === 'status') {
  foreach (mig_status() as $m) out(sprintf('%s %-30s %s%s', $m['version'], $m['name'], $m['applied'] ? 'applied' : 'PENDING', $m['changed'] ? '  (file changed since it was applied!)' : ''));
} elseif ($cmd === 'up') {
  $done = mig_up(); out($done ? 'Applied: ' . implode(', ', $done) : 'Nothing to apply.');
} elseif ($cmd === 'down') {
  if (!arg('backup-confirmed')) fail('This deletes data. Take a database backup, check that it restores, then add --backup-confirmed.');
  $v = mig_down(); out($v ? "Undone: $v" : 'Nothing to undo.');
  foreach (glob(storage_dir() . '/schema_*.ok') ?: [] as $f) @unlink($f);
} else fail('Use: status | up | down --backup-confirmed');
