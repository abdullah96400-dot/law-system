<?php
/* ==========================================================================
   Offices (tenants). Created by Al Imtiyaz (CLI tool now; signed API from
   the platform in a later batch). Status:
   - active    normal use
   - readonly  staff can sign in, read and export; nothing can be changed
               (subscription ended or paused — data is never deleted here)
   - suspended nobody can sign in
   ========================================================================== */
declare(strict_types=1);

const OFFICE_STATUSES = ['active', 'readonly', 'suspended'];

/** Returns ['office_id', 'owner_id', 'token'] or ['error' => key]. */
function create_office(string $code, string $nameAr, string $nameEn, string $ownerEmail, string $ownerName, array $opt = []): array {
  $code = strtoupper(trim($code)); $ownerEmail = norm_email($ownerEmail);
  if (!preg_match('/^[A-Z0-9-]{3,20}$/', $code)) return ['error' => 'office_code'];
  if (trim($nameAr) === '' || trim($nameEn) === '' || trim($ownerName) === '' || !filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) return ['error' => 'invite_fields'];
  if (row('SELECT id FROM offices WHERE code=?', [$code])) return ['error' => 'office_exists'];
  if (row('SELECT id FROM staff WHERE email=?', [$ownerEmail])) return ['error' => 'email_unavailable'];
  $pdo = db(); $pdo->beginTransaction();
  try {
    q('INSERT INTO offices (code, name_ar, name_en, country, status, plan, max_users, demo, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)',
      [$code, trim($nameAr), trim($nameEn), (string)cfg('country', 'OM'), 'active', $opt['plan'] ?? null, $opt['max_users'] ?? null, !empty($opt['demo']) ? 1 : 0, now(), now()]);
    $oid = (int)$pdo->lastInsertId();
    seed_role_permissions($oid);
    $prev = $GLOBALS['OFFICE_SCOPE'] ?? null; set_office_scope($oid);
    foreach (LOOKUP_KINDS as $k) lookups($k);   // the office's starting lists (editable)
    $sid = o_insert('staff', ['email' => $ownerEmail, 'full_name' => trim($ownerName), 'role' => 'partner', 'lang' => $opt['lang'] ?? 'ar', 'status' => 'invited', 'created_at' => now(), 'updated_at' => now()]);
    $tok = issue_invite($sid, null);
    $pdo->commit();
  } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
  law_audit('office_created', 'office', $oid, ['code' => $code, 'demo' => !empty($opt['demo'])], $oid, ['id' => null, 'full_name' => 'Al Imtyaz', 'email' => '']);
  if (!empty($opt['send_mail'])) send_invite_mail(row('SELECT * FROM staff WHERE id=?', [$sid]), $tok);
  set_office_scope($prev);
  return ['office_id' => $oid, 'owner_id' => $sid, 'token' => $tok];
}
function set_office_status(int $officeId, string $status, string $by = 'Al Imtyaz'): ?string {
  if (!in_array($status, OFFICE_STATUSES, true)) return 'bad_status';
  $o = row('SELECT * FROM offices WHERE id=?', [$officeId]); if (!$o) return 'not_found';
  if ($o['status'] === $status) return null;
  q('UPDATE offices SET status=?, updated_at=? WHERE id=?', [$status, now(), $officeId]);
  law_audit('office_status', 'office', $officeId, ['from' => $o['status'], 'to' => $status], $officeId, ['id' => null, 'full_name' => $by, 'email' => '']);
  return null;
}
function office_readonly(): bool { $o = current_office(); return $o && $o['status'] === 'readonly'; }
function office_name(?array $o = null): string { $o = $o ?? current_office(); return $o ? (lang() === 'ar' ? $o['name_ar'] : $o['name_en']) : ''; }
