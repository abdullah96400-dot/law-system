<?php
/* ==========================================================================
   Audit log: append-only, one hash chain per office.
   - Each entry's hash covers the previous entry's hash and its own fields, so
     any edit or removal breaks the chain (audit_verify shows where).
   - Database triggers refuse UPDATE and DELETE on audit_log.
   - Nothing in the user interface can change or delete entries.
   - Passwords, codes and tokens are never written here.
   ========================================================================== */
declare(strict_types=1);

function audit_hash(string $prev, array $r): string {
  return hash('sha256', $prev . '|' . implode('|', [$r['office_id'], $r['at'], (string)$r['staff_id'], $r['action'], (string)$r['entity'], (string)$r['entity_id'], (string)$r['detail'], (string)$r['ip']]));
}
/** Record an action for the office in scope (or $officeId). Never throws: a failure is logged to the server error log. */
function law_audit(string $action, ?string $entity = null, $entityId = null, array $detail = [], ?int $officeId = null, ?array $actor = null): void {
  try {
    $oid = $officeId ?? ($GLOBALS['OFFICE_SCOPE'] ?? null); if (!$oid) return;
    $actor = $actor ?? (function_exists('current_staff') ? current_staff() : null);
    $r = ['office_id' => (int)$oid, 'at' => now(), 'staff_id' => ($actor['id'] ?? null) ?: null, 'action' => $action, 'entity' => $entity,
          'entity_id' => $entityId === null ? null : (string)$entityId,
          'detail' => $detail ? json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null, 'ip' => ip()];
    $pdo = db(); $own = !$pdo->inTransaction(); if ($own) $pdo->beginTransaction();
    try {
      if (!is_sqlite()) q('SELECT id FROM offices WHERE id=? FOR UPDATE', [$r['office_id']]);   // one writer per office chain at a time
      $prev = row('SELECT hash FROM audit_log WHERE office_id=? ORDER BY id DESC LIMIT 1', [$r['office_id']]);
      $p = $prev['hash'] ?? str_repeat('0', 64);
      q('INSERT INTO audit_log (office_id, at, staff_id, actor, action, entity, entity_id, detail, ip, prev_hash, hash) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
        [$r['office_id'], $r['at'], $r['staff_id'], $actor ? (($actor['full_name'] ?? '') ?: ($actor['email'] ?? null)) : null, $action, $entity, $r['entity_id'], $r['detail'], $r['ip'], $p, audit_hash($p, $r)]);
      if ($own) $pdo->commit();
    } catch (Throwable $e) { if ($own && $pdo->inTransaction()) $pdo->rollBack(); throw $e; }
  } catch (Throwable $e) { error_log('LAW audit: ' . $e->getMessage()); }
}
/** Walk one office's chain. Returns [ok, entries checked, first broken id or null]. */
function audit_verify(int $officeId): array {
  $prev = str_repeat('0', 64); $n = 0;
  foreach (rows('SELECT * FROM audit_log WHERE office_id=? ORDER BY id', [$officeId]) as $r) {
    $n++;
    if ($r['prev_hash'] !== $prev || !hash_equals($r['hash'], audit_hash($prev, $r))) return [false, $n, (int)$r['id']];
    $prev = $r['hash'];
  }
  return [true, $n, null];
}
