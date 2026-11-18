<?php
/* ==========================================================================
   Support access. Al Imtyaz staff never see an office's data unless the
   managing partner opens a visit:
   - The partner creates a grant (1–72 hours, with a reason) and sends the
     one-time link to Al Imtyaz support. The link alone is not enough: the
     support person also enters the support passphrase held by Al Imtyaz
     (config support_pass_hash).
   - A visit is view-only. It cannot open documents, money, legal notes or
     restricted case files, and it cannot save anything.
   - Every page opened during a visit is written to the office's audit log.
   - The partner can end a visit at any time; the next page load is refused.
   ========================================================================== */
declare(strict_types=1);

const SUPPORT_MAX_HOURS = 72;

function support_grant_create(array $me, int $hours, string $reason): array {
  if ($me['role'] !== 'partner') return ['error' => 'forbidden'];
  $reason = mb_substr(trim($reason), 0, 300); if ($reason === '') return ['error' => 'support_reason_required'];
  $hours = max(1, min(SUPPORT_MAX_HOURS, $hours));
  $tok = token(32);
  $id = o_insert('support_grants', ['token_hash' => keyed_hash($tok), 'reason' => $reason, 'expires_at' => date('Y-m-d H:i:s', time() + $hours * 3600), 'created_by' => $me['id'], 'created_at' => now()]);
  law_audit('support_granted', 'support_grant', $id, ['hours' => $hours], null, $me);
  return ['id' => $id, 'link' => abs_url('/support/' . $tok)];
}
function support_grant_revoke(array $me, int $id): bool {
  if ($me['role'] !== 'partner') return false;
  $g = orow('SELECT * FROM support_grants WHERE office_id = :office AND id = :id AND revoked_at IS NULL', ['id' => $id]); if (!$g) return false;
  o_update('support_grants', $id, ['revoked_at' => now(), 'revoked_by' => $me['id']]);
  law_audit('support_revoked', 'support_grant', $id, [], null, $me);
  return true;
}
function support_grants(): array { return orows('SELECT * FROM support_grants WHERE office_id = :office ORDER BY id DESC LIMIT 20'); }
function support_grant_live(array $g): bool { return !$g['revoked_at'] && $g['expires_at'] > now(); }

/** Grant for a link token, across offices (the token is the lookup key). */
function support_lookup(string $tok): ?array {
  $g = row('SELECT * FROM support_grants WHERE token_hash=?', [keyed_hash($tok)]);
  return $g && support_grant_live($g) ? $g : null;
}
/** Start a visit. Returns null or an error key. */
function support_enter(string $tok, string $pass): ?string {
  if (ip_blocked()) return 'too_many_attempts';
  $hash = (string)cfg('support_pass_hash', '');
  $g = support_lookup($tok);
  if (!$g) return 'support_link_invalid';
  if ($hash === '' || !password_verify($pass, $hash)) { q('INSERT INTO auth_failures (ip_hash, at) VALUES (?,?)', [keyed_hash(ip()), now()]); law_audit('support_denied', 'support_grant', $g['id'], [], (int)$g['office_id'], support_actor($g)); return 'support_pass_wrong'; }
  session_regenerate_id(true);
  $_SESSION = ['support_gid' => (int)$g['id'], 'last' => time(), 'csrf' => bin2hex(random_bytes(16))];
  q('UPDATE support_grants SET first_used_at = COALESCE(first_used_at, ?), last_used_at = ? WHERE id = ?', [now(), now(), $g['id']]);
  law_audit('support_started', 'support_grant', $g['id'], [], (int)$g['office_id'], support_actor($g));
  return null;
}
function support_actor(array $g): array { return ['id' => null, 'full_name' => 'Al Imtyaz support (visit #' . $g['id'] . ')', 'email' => '']; }
/** The signed-in support visitor as a staff-shaped row, or null when the visit has ended. */
function support_session(): ?array {
  $g = row('SELECT * FROM support_grants WHERE id=?', [(int)$_SESSION['support_gid']]);
  $idle = 60 * (int)cfg('session_idle_minutes', 60);
  $o = $g ? row('SELECT status FROM offices WHERE id=?', [(int)$g['office_id']]) : null;
  if (!$g || !support_grant_live($g) || time() - (int)($_SESSION['last'] ?? 0) > $idle || !$o || $o['status'] === 'suspended') { $_SESSION = []; return null; }
  $_SESSION['last'] = time();
  set_office_scope((int)$g['office_id']);
  return ['id' => 0, 'office_id' => (int)$g['office_id'], 'role' => 'support', 'full_name' => support_actor($g)['full_name'], 'email' => '', 'lang' => $_SESSION['lang'] ?? 'ar',
          'status' => 'active', 'totp_secret' => 'support', 'session_ver' => 0, 'branch_id' => null, 'grant' => $g];
}
function is_support(?array $me): bool { return ($me['role'] ?? '') === 'support'; }
