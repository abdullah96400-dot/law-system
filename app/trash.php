<?php
/* ==========================================================================
   Recycle bin.
   - Deleting a case file, hearing, procedure, task, document or internal note
     moves it (with what belongs to it) into the bin: a copy of the rows is
     kept and the rows leave the working tables. Document files stay on disk,
     encrypted, until the item is purged.
   - Money is never put in the bin: a case file with fee agreements, invoices,
     expenses, trust entries or due notices cannot be deleted — close it
     instead. Invoices and payments are voided, never deleted.
   - Restoring checks that what the item belongs to still exists (the case
     file, the client, the conflict check not reused) and that its numbers
     are still free. Every delete and restore is in the audit log.
   - The managing partner and the office manager see the bin and restore.
   ========================================================================== */
declare(strict_types=1);

const TRASH_KINDS = ['matter', 'appointment', 'action', 'task', 'document', 'note'];
/** For each kind: the main table and the tables that go with it [table => column pointing at the main row]. */
const TRASH_SPEC = [
  'matter' => ['matters', ['matter_parties' => 'matter_id', 'matter_team' => 'matter_id', 'matter_events' => 'matter_id', 'appointments' => 'matter_id', 'tasks' => 'matter_id',
    'matter_actions' => 'matter_id', 'delegations' => 'matter_id', 'client_updates' => 'matter_id', 'portal_messages' => 'matter_id', 'doc_requests' => 'matter_id', 'portal_shares' => 'matter_id', 'documents' => 'matter_id']],
  'appointment' => ['appointments', ['delegations' => 'appointment_id']],
  'action' => ['matter_actions', []],
  'task' => ['tasks', []],
  'document' => ['documents', ['document_versions' => 'document_id', 'document_shares' => 'document_id']],
  'note' => ['matter_events', []],
];
const TRASH_MONEY = ['fee_agreements', 'invoices', 'expenses', 'trust_entries', 'due_notices'];

function trash_label(string $kind, array $r): string {
  return mb_substr(match ($kind) { 'matter' => $r['ref'] . ' — ' . $r['title'], 'note' => mb_strimwidth((string)$r['body'], 0, 120, '…'), default => (string)$r['title'] }, 0, 300);
}
/** Who may delete each kind (server side). */
function trash_can_delete(string $kind, array $me): bool {
  return match ($kind) { 'matter', 'action' => can('matters', 'delete', $me), 'appointment' => can('calendar', 'delete', $me), 'task' => can('tasks', 'delete', $me),
    'document' => can('documents', 'delete', $me), 'note' => can('legal_notes', 'delete', $me) || $me['role'] === 'partner', default => false };
}
/** Move an item to the bin. The caller has already checked that the person can see it. */
function trash_move(array $me, string $kind, int $id): ?string {
  if (!in_array($kind, TRASH_KINDS, true)) return 'not_found';
  if (!trash_can_delete($kind, $me)) return 'forbidden';
  [$table, $children] = TRASH_SPEC[$kind];
  $row = orow("SELECT * FROM $table WHERE office_id = :office AND id = :id", ['id' => $id]); if (!$row) return 'not_found';
  if ($kind === 'note' && $row['kind'] !== 'note') return 'not_found';
  if ($kind === 'matter') foreach (TRASH_MONEY as $t) if (orow("SELECT id FROM $t WHERE office_id = :office AND matter_id = :m LIMIT 1", ['m' => $id])) return 'trash_has_money';
  if ($kind === 'document' && orow('SELECT id FROM fee_agreements WHERE office_id = :office AND document_id = :d LIMIT 1', ['d' => $id])) return 'trash_doc_in_agreement';
  $data = [$table => [$row]];
  foreach ($children as $t => $col) $data[$t] = orows("SELECT * FROM $t WHERE office_id = :office AND $col = :id", ['id' => $id]);
  if ($kind === 'matter' && $data['documents']) {   // the files of the case's documents
    $dids = implode(',', array_map(fn($d) => (int)$d['id'], $data['documents']));
    $data['document_versions'] = orows("SELECT * FROM document_versions WHERE office_id = :office AND document_id IN ($dids)");
    $data['document_shares'] = orows("SELECT * FROM document_shares WHERE office_id = :office AND document_id IN ($dids)");
  }
  $pdo = db(); $pdo->beginTransaction();
  try {
    $tid = o_insert('trash', ['kind' => $kind, 'item_id' => $id, 'matter_id' => $kind === 'matter' ? $id : ($row['matter_id'] ?? null), 'label' => trash_label($kind, $row),
      'data' => json_encode($data, JSON_UNESCAPED_UNICODE), 'deleted_by' => $me['id'], 'deleted_at' => now()]);
    foreach (array_reverse(array_keys($data)) as $t) foreach ($data[$t] as $r) oq("DELETE FROM $t WHERE office_id = :office AND id = :id", ['id' => $r['id']]);
    $pdo->commit();
  } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
  if ($kind !== 'matter' && !empty($row['matter_id'])) matter_event((int)$row['matter_id'], $me, 'trashed', null, ['kind' => $kind, 'title' => trash_label($kind, $row)]);
  law_audit('trash_deleted', $kind, $id, ['trash' => $tid, 'rows' => array_sum(array_map('count', $data))], null, $me);
  return null;
}
/** Put an item back. Returns null or an error key explaining what blocks it. */
function trash_restore(array $me, int $trashId): ?string {
  if (!trash_can_view($me)) return 'forbidden';
  $t = orow('SELECT * FROM trash WHERE office_id = :office AND id = :id AND restored_at IS NULL', ['id' => $trashId]); if (!$t) return 'not_found';
  $data = json_decode($t['data'], true) ?: []; [$table] = TRASH_SPEC[$t['kind']]; $row = $data[$table][0] ?? null; if (!$row) return 'not_found';
  if (!empty($row['matter_id']) && $t['kind'] !== 'matter' && !orow('SELECT id FROM matters WHERE office_id = :office AND id = :id', ['id' => $row['matter_id']])) return 'trash_parent_gone';
  if ($t['kind'] === 'matter') {
    if (!contact_get((int)$row['client_id'])) return 'trash_client_gone';
    if ($row['conflict_check_id'] && orow('SELECT id FROM matters WHERE office_id = :office AND conflict_check_id = :c', ['c' => $row['conflict_check_id']])) return 'trash_check_reused';
    if (orow('SELECT id FROM matters WHERE office_id = :office AND ref = :r', ['r' => $row['ref']])) return 'trash_ref_taken';
  }
  foreach ($data as $tb => $rows) foreach ($rows as $r) if (orow("SELECT id FROM $tb WHERE office_id = :office AND id = :id", ['id' => $r['id']])) return 'trash_id_taken';
  $pdo = db(); $pdo->beginTransaction();
  try {
    foreach ($data as $tb => $rows) foreach ($rows as $r) {
      $r['office_id'] = office_id(); $cols = array_keys($r);
      q("INSERT INTO $tb (" . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', array_values($r));
    }
    oq('UPDATE trash SET restored_by = :me, restored_at = :now WHERE office_id = :office AND id = :id', ['me' => $me['id'], 'now' => now(), 'id' => $t['id']]);
    $pdo->commit();
  } catch (Throwable $e) { $pdo->rollBack(); error_log('LAW trash restore: ' . $e->getMessage()); return 'trash_restore_failed'; }
  if ($t['matter_id']) matter_event((int)$t['matter_id'], $me, 'restored', null, ['kind' => $t['kind'], 'title' => $t['label']]);
  law_audit('trash_restored', $t['kind'], $t['item_id'], ['trash' => $t['id']], null, $me);
  return null;
}
function trash_list(array $f): array {
  $w = ['office_id = :office']; $p = [];
  $w[] = !empty($f['restored']) ? 'restored_at IS NOT NULL' : 'restored_at IS NULL';
  if (!empty($f['kind']) && in_array($f['kind'], TRASH_KINDS, true)) { $w[] = 'kind = :k'; $p['k'] = $f['kind']; }
  if (!empty($f['who'])) { $w[] = 'deleted_by = :w'; $p['w'] = (int)$f['who']; }
  if (!empty($f['from'])) { $w[] = 'deleted_at >= :f'; $p['f'] = $f['from'] . ' 00:00:00'; }
  return orows('SELECT id, kind, item_id, matter_id, label, deleted_by, deleted_at, restored_by, restored_at FROM trash WHERE ' . implode(' AND ', $w) . ' ORDER BY id DESC LIMIT 300', $p);
}

/* ---------- backups ---------- */
/** Snapshot of the whole database (every table), gzip, encrypted with the files key; keeps the last 14. Run by the daily job. */
function backup_run(string $kind = 'nightly'): array {
  $key = files_key(); $dir = storage_dir() . '/backups';
  if (!$key) { q('INSERT INTO backup_runs (kind, ok, size, note, at) VALUES (?,?,?,?,?)', [$kind, 0, null, 'files_key missing', now()]); return ['ok' => false]; }
  try {
    if (!is_dir($dir) && !@mkdir($dir, 0750, true)) throw new RuntimeException('cannot create backups folder');
    $tables = is_sqlite() ? array_column(rows("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"), 'name') : array_map(fn($r) => array_values($r)[0], rows('SHOW TABLES'));
    $tmp = tempnam(sys_get_temp_dir(), 'bk'); $gz = gzopen($tmp, 'wb6');
    gzwrite($gz, json_encode(['format' => 'alimtyaz-law-backup', 'v' => 1, 'at' => now(), 'tables' => $tables]) . "\n");
    foreach ($tables as $tb) { $st = q("SELECT * FROM $tb"); while ($r = $st->fetch()) gzwrite($gz, json_encode(['t' => $tb, 'r' => $r], JSON_UNESCAPED_UNICODE) . "\n"); }
    gzclose($gz);
    $name = 'law-' . date('Ymd-His') . '.jsonl.gz.enc'; $dst = "$dir/$name";
    crypt_encrypt_file($tmp, $dst, $key); @unlink($tmp); @chmod($dst, 0640);
    $files = glob("$dir/law-*.jsonl.gz.enc") ?: []; sort($files); foreach (array_slice($files, 0, max(0, count($files) - 14)) as $old) @unlink($old);
    q('INSERT INTO backup_runs (kind, ok, size, note, at) VALUES (?,?,?,?,?)', [$kind, 1, filesize($dst), $name, now()]);
    return ['ok' => true, 'file' => $dst];
  } catch (Throwable $e) {
    error_log('LAW backup: ' . $e->getMessage());
    q('INSERT INTO backup_runs (kind, ok, size, note, at) VALUES (?,?,?,?,?)', [$kind, 0, null, mb_substr($e->getMessage(), 0, 300), now()]);
    return ['ok' => false];
  }
}
function backup_due(): bool { $r = row("SELECT at FROM backup_runs WHERE ok = 1 ORDER BY id DESC LIMIT 1"); return !$r || strtotime($r['at']) < time() - 20 * 3600; }
function backup_status(): array {
  return ['last_ok' => row('SELECT * FROM backup_runs WHERE ok = 1 ORDER BY id DESC LIMIT 1'), 'last' => row('SELECT * FROM backup_runs ORDER BY id DESC LIMIT 1'),
    'runs' => rows('SELECT * FROM backup_runs ORDER BY id DESC LIMIT 14'), 'kept' => count(glob(storage_dir() . '/backups/law-*.jsonl.gz.enc') ?: [])];
}
