<?php
/* ==========================================================================
   Numbered migrations (law/migrations/NNNN_name.php).
   - Each file returns ['up' => [statements], 'down' => [statements]].
     {ID} and {TAIL} become the right column/table options for MySQL or SQLite;
     a statement starting with "mysql:" runs on MySQL only (triggers).
   - Applied versions are recorded in schema_migrations with a checksum, so a
     changed file is reported instead of silently re-run.
   - Pending migrations are applied automatically on the first request (all
     of them only add tables/columns). Going back ("down") is CLI only and
     refuses to run without --backup-confirmed. See law/INSTALL.md.
   ========================================================================== */
declare(strict_types=1);

function mig_files(): array {
  $out = [];
  foreach (glob(law_root() . '/migrations/*.php') ?: [] as $f) {
    if (preg_match('/^(\d{4})_([a-z0-9_]+)\.php$/', basename($f), $m)) $out[$m[1]] = ['version' => $m[1], 'name' => $m[2], 'file' => $f];
  }
  ksort($out); return $out;
}
function mig_sql(string $s): ?string {
  $my = !is_sqlite();
  if (str_starts_with($s, 'mysql:')) { if (!$my) return null; $s = substr($s, 6); }
  elseif (str_starts_with($s, 'sqlite:')) { if ($my) return null; $s = substr($s, 7); }
  return strtr($s, [
    '{ID}' => $my ? 'id INT AUTO_INCREMENT PRIMARY KEY' : 'id INTEGER PRIMARY KEY AUTOINCREMENT',
    '{TAIL}' => $my ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '',
  ]);
}
function mig_ensure_table(): void {
  db()->exec(mig_sql('CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(4) NOT NULL PRIMARY KEY, name VARCHAR(80) NOT NULL, checksum CHAR(64) NOT NULL, applied_at DATETIME NOT NULL){TAIL}'));
}
/** [version => row] of applied migrations. */
function mig_applied(): array { mig_ensure_table(); $o = []; foreach (rows('SELECT * FROM schema_migrations ORDER BY version') as $r) $o[$r['version']] = $r; return $o; }
function mig_status(): array {
  $applied = mig_applied(); $out = [];
  foreach (mig_files() as $v => $f) {
    $sum = hash_file('sha256', $f['file']);
    $out[] = ['version' => $v, 'name' => $f['name'], 'applied' => isset($applied[$v]), 'changed' => isset($applied[$v]) && $applied[$v]['checksum'] !== $sum];
  }
  return $out;
}
/** Run one statement so that a half-finished first run on MySQL can simply be run again:
    "already exists" errors are skipped, and audit-log triggers the hosting plan does not allow
    are recorded instead of stopping the install (the hash chain still shows any change to the log). */
function mig_exec(string $sql): void {
  if (!is_sqlite() && str_starts_with($sql, 'CREATE TABLE ')) $sql = 'CREATE TABLE IF NOT EXISTS ' . substr($sql, 13);
  try { db()->exec($sql); }
  catch (PDOException $e) {
    $code = (int)($e->errorInfo[1] ?? 0);
    if (in_array($code, [1050, 1060, 1061, 1359], true)) return;   // table / column / index / trigger already there
    if (!is_sqlite() && str_starts_with($sql, 'CREATE TRIGGER') && in_array($code, [1044, 1142, 1227, 1419], true)) {   // no permission for triggers on this plan
      @file_put_contents(storage_dir() . '/audit_triggers_missing.txt', now() . ' ' . $e->getMessage() . "\n", FILE_APPEND);
      error_log('LAW migrate: audit triggers not created: ' . $e->getMessage());
      return;
    }
    throw $e;
  }
}
/** Apply every pending migration in order. Returns the versions applied. */
function mig_up(): array {
  $applied = mig_applied(); $done = [];
  foreach (mig_files() as $v => $f) {
    if (isset($applied[$v])) continue;
    $m = require $f['file'];
    foreach ($m['up'] as $s) { $sql = mig_sql($s); if ($sql !== null) mig_exec($sql); }
    q('INSERT INTO schema_migrations (version, name, checksum, applied_at) VALUES (?,?,?,?)', [$v, $f['name'], hash_file('sha256', $f['file']), now()]);
    $done[] = $v;
  }
  return $done;
}
/** Undo ONE applied migration (the latest). CLI only, after a verified backup. */
function mig_down(): ?string {
  $applied = mig_applied(); if (!$applied) return null;
  $v = array_key_last($applied); $files = mig_files(); if (!isset($files[$v])) throw new RuntimeException("Migration file $v is missing");
  $m = require $files[$v]['file'];
  foreach ($m['down'] as $s) { $sql = mig_sql($s); if ($sql !== null) db()->exec($sql); }
  q('DELETE FROM schema_migrations WHERE version=?', [$v]);
  return $v;
}
/** Web requests: apply pending migrations once, guarded by a lock file. */
function mig_auto(): void {
  static $ran = false; if ($ran) return; $ran = true;
  $files = mig_files(); if (!$files) return;
  $flag = storage_dir() . '/schema_' . array_key_last($files) . '_' . substr(keyed_hash((string)cfg('db_dsn')), 0, 12) . '.ok';
  if (is_file($flag)) return;
  $lock = fopen(storage_dir() . '/migrate.lock', 'c'); if ($lock) flock($lock, LOCK_EX);
  try { mig_up(); @file_put_contents($flag, now()); }
  finally { if ($lock) { flock($lock, LOCK_UN); fclose($lock); } }
}
