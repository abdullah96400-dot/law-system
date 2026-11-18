<?php
/* ==========================================================================
   Staff sign-in and accounts.
   - Email + password, then an authenticator-app code. Two-step sign-in is
     required for the managing partner and the office manager (and for
     everyone when the office switches it on).
   - 5 wrong passwords lock the account for 15 minutes; 30 failures from one
     network address in 15 minutes pause sign-in from it. Messages never say
     whether an email is registered.
   - Staff join by invitation link (valid 72 hours, used once) and set their
     own password. Passwords are stored only as hashes and never logged.
   - Raising session_ver signs a person out everywhere (disable, password
     change, 2FA reset, "sign out everywhere").
   ========================================================================== */
declare(strict_types=1);

const LOCK_AFTER = 5;
const LOCK_MINUTES = 15;
const IP_LIMIT = 30;
const INVITE_HOURS = 72;
const PENDING_SECONDS = 600;   // time for the second step; first-time setup on a phone means switching to the authenticator app and back

/* ---------- the signed-in person ---------- */
function current_staff(): ?array {
  if (array_key_exists('CURRENT_STAFF', $GLOBALS)) return $GLOBALS['CURRENT_STAFF'];
  $GLOBALS['CURRENT_STAFF'] = null;
  if (!empty($_SESSION['support_gid'])) return $GLOBALS['CURRENT_STAFF'] = support_session();
  $id = (int)($_SESSION['sid'] ?? 0); if (!$id) return null;
  $s = row('SELECT * FROM staff WHERE id=?', [$id]);
  $idle = 60 * (int)cfg('session_idle_minutes', 60);
  $o = $s ? row('SELECT status FROM offices WHERE id=?', [(int)$s['office_id']]) : null;
  if (!$s || $s['status'] !== 'active' || (int)$s['session_ver'] !== (int)($_SESSION['sv'] ?? -1)
      || time() - (int)($_SESSION['last'] ?? 0) > $idle || !$o || $o['status'] === 'suspended') {
    $_SESSION = []; return null;
  }
  $_SESSION['last'] = time();
  set_office_scope((int)$s['office_id']);
  return $GLOBALS['CURRENT_STAFF'] = $s;
}
function current_staff_reset(): void { unset($GLOBALS['CURRENT_STAFF']); set_office_scope(null); }
function require_staff(): array { $s = current_staff(); if (!$s) redirect('/login'); return $s; }
function needs_2fa(array $s): bool {
  if (in_array($s['role'], ROLES_2FA, true) || !empty($s['totp_secret'])) return true;
  $o = row('SELECT require_2fa_all FROM offices WHERE id=?', [(int)$s['office_id']]);
  return (bool)($o['require_2fa_all'] ?? 0);
}

/* ---------- sign in ---------- */
function ip_blocked(): bool {
  $n = row('SELECT COUNT(*) n FROM auth_failures WHERE ip_hash=? AND at>?', [keyed_hash(ip()), date('Y-m-d H:i:s', time() - LOCK_MINUTES * 60)]);
  return (int)$n['n'] >= IP_LIMIT;
}
/** Returns ['ok' => 'done'|'2fa'|'2fa_setup'] or ['error' => key]. */
function login_attempt(string $email, string $password): array {
  if (ip_blocked()) return ['error' => 'too_many_attempts'];
  $email = norm_email($email);
  $s = $email !== '' ? row('SELECT * FROM staff WHERE email=?', [$email]) : null;
  $hash = $s['password_hash'] ?? null;
  $ok = password_verify($password, $hash ?: '$2y$10$kIe46g1.3zSgN4Eko/vD9OyaZMZ6P2S/xX/Y1swNk9NKM1rfD/ibu');   // same work for unknown emails
  $fail = function (string $why) use ($s) {
    q('INSERT INTO auth_failures (ip_hash, at) VALUES (?,?)', [keyed_hash(ip()), now()]);
    if ($s) law_audit('login_failed', 'staff', $s['id'], ['why' => $why], (int)$s['office_id'], $s);
    return ['error' => 'login_failed'];
  };
  if (!$s || !$hash) return $fail('unknown');
  if ($s['locked_until'] && strtotime($s['locked_until']) > time()) return $fail('locked');
  if (!$ok) {
    $n = (int)$s['failed_count'] + 1;
    if ($n >= LOCK_AFTER) { q('UPDATE staff SET failed_count=0, locked_until=? WHERE id=?', [date('Y-m-d H:i:s', time() + LOCK_MINUTES * 60), $s['id']]); law_audit('account_locked', 'staff', $s['id'], [], (int)$s['office_id'], $s); }
    else q('UPDATE staff SET failed_count=? WHERE id=?', [$n, $s['id']]);
    return $fail('password');
  }
  if ($s['status'] !== 'active') return $fail('status_' . $s['status']);
  $o = row('SELECT * FROM offices WHERE id=?', [(int)$s['office_id']]);
  if (!$o || $o['status'] === 'suspended') { law_audit('login_blocked', 'office', $s['office_id'], ['why' => 'suspended'], (int)$s['office_id'], $s); return ['error' => 'office_suspended']; }
  if (password_needs_rehash($hash, PASSWORD_DEFAULT)) q('UPDATE staff SET password_hash=? WHERE id=?', [password_hash($password, PASSWORD_DEFAULT), $s['id']]);
  if (needs_2fa($s)) {
    session_regenerate_id_safe();
    $_SESSION = ['pending' => ['id' => (int)$s['id'], 'at' => time(), 'tries' => 0], 'lang' => $s['lang']];
    return ['ok' => empty($s['totp_secret']) ? '2fa_setup' : '2fa'];
  }
  finish_login($s);
  return ['ok' => 'done'];
}
function session_regenerate_id_safe(): void { if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true); }
function pending_staff(): ?array {
  $p = $_SESSION['pending'] ?? null;
  if (!$p || time() - (int)$p['at'] > PENDING_SECONDS || (int)$p['tries'] >= 5) { unset($_SESSION['pending']); return null; }
  $s = row("SELECT * FROM staff WHERE id=? AND status='active'", [(int)$p['id']]);
  if ($s && $s['locked_until'] && strtotime($s['locked_until']) > time()) { unset($_SESSION['pending']); return null; }
  return $s ?: null;
}
/** Second step. Returns null on success or an error key. */
function login_2fa(string $code): ?string {
  $s = pending_staff(); if (!$s) return 'session_expired';
  $secret = open_secret($s['totp_secret']); if (!$secret) return 'session_expired';
  $step = totp_check($secret, $code, $s['totp_last'] !== null ? (int)$s['totp_last'] : null);
  if ($step === null) {
    // wrong codes count toward the same lock as wrong passwords, so restarting sign-in cannot be used to keep guessing
    $_SESSION['pending']['tries']++;
    q('INSERT INTO auth_failures (ip_hash, at) VALUES (?,?)', [keyed_hash(ip()), now()]);
    $n = (int)$s['failed_count'] + 1;
    if ($n >= LOCK_AFTER) { q('UPDATE staff SET failed_count=0, locked_until=? WHERE id=?', [date('Y-m-d H:i:s', time() + LOCK_MINUTES * 60), $s['id']]); law_audit('account_locked', 'staff', $s['id'], ['why' => '2fa'], (int)$s['office_id'], $s); unset($_SESSION['pending']); }
    else q('UPDATE staff SET failed_count=? WHERE id=?', [$n, $s['id']]);
    law_audit('2fa_failed', 'staff', $s['id'], [], (int)$s['office_id'], $s);
    return 'code_wrong';
  }
  q('UPDATE staff SET totp_last=?, failed_count=0 WHERE id=?', [$step, $s['id']]);
  finish_login($s);
  return null;
}
/** First-time 2FA enrolment during sign-in. The secret waits in the session until a code proves the app is set up. */
/** The same secret on every attempt until enrolment succeeds (tied to this person and their current password), so an
    authenticator entry added on an earlier, timed-out attempt keeps working. Only the server can derive it. */
function enrol_secret(array $s): string { return b32_encode(substr(hash_hmac('sha256', 'totp-enrol|' . $s['id'] . '|' . (string)$s['password_hash'], (string)cfg('app_secret'), true), 0, 20)); }
function login_2fa_setup_secret(): ?string { $s = pending_staff(); if (!$s) return null; return $_SESSION['pending']['new_secret'] ??= enrol_secret($s); }
function login_2fa_setup(string $code): ?string {
  $s = pending_staff(); if (!$s) return 'session_expired';
  $secret = $_SESSION['pending']['new_secret'] ?? null; if (!$secret) return 'session_expired';
  $step = totp_check($secret, $code, null);
  if ($step === null) { $_SESSION['pending']['tries']++; return 'code_wrong'; }
  q('UPDATE staff SET totp_secret=?, totp_last=?, updated_at=? WHERE id=?', [seal_secret($secret), $step, now(), $s['id']]);
  law_audit('2fa_enabled', 'staff', $s['id'], [], (int)$s['office_id'], $s);
  finish_login(row('SELECT * FROM staff WHERE id=?', [$s['id']]));
  return null;
}
function finish_login(array $s): void {
  session_regenerate_id_safe();
  $_SESSION = ['sid' => (int)$s['id'], 'sv' => (int)$s['session_ver'], 'last' => time(), 'lang' => $s['lang']];
  q('UPDATE staff SET last_login=?, failed_count=0, locked_until=NULL WHERE id=?', [now(), $s['id']]);   // only a complete sign-in clears failures
  current_staff_reset();
  law_audit('login', 'staff', $s['id'], [], (int)$s['office_id'], $s);
}
function logout(): void {
  if ($s = current_staff()) law_audit('logout', 'staff', $s['id']);
  $_SESSION = []; session_regenerate_id_safe(); current_staff_reset();
}

/* ---------- passwords ---------- */
function password_problem(string $pw, string $confirm, string $email): ?string {
  if ($pw !== $confirm) return 'pw_mismatch';
  if (mb_strlen($pw) < 10) return 'pw_short';
  $local = strtok(norm_email($email), '@');
  if ($local && mb_strlen($local) >= 4 && str_contains(mb_strtolower($pw), $local)) return 'pw_has_email';
  if (count(array_unique(mb_str_split($pw))) < 5) return 'pw_simple';
  return null;
}
function change_password(array $me, string $current, string $new, string $confirm): ?string {
  if (!password_verify($current, (string)$me['password_hash'])) { law_audit('password_change_failed', 'staff', $me['id']); return 'pw_current_wrong'; }
  if ($e = password_problem($new, $confirm, $me['email'])) return $e;
  $sv = (int)$me['session_ver'] + 1;
  q('UPDATE staff SET password_hash=?, password_set_at=?, session_ver=?, updated_at=? WHERE id=?', [password_hash($new, PASSWORD_DEFAULT), now(), $sv, now(), $me['id']]);
  $_SESSION['sv'] = $sv;   // keep this session; every other one ends
  law_audit('password_changed', 'staff', $me['id']);
  current_staff_reset();
  return null;
}

/* ---------- invitations ---------- */
/** Create an invitation token for a staff member of the office in scope. Returns the raw token (shown or emailed once). */
function issue_invite(int $staffId, ?int $byId): string {
  $tok = token();
  oq('UPDATE invites SET used_at = :now WHERE office_id = :office AND staff_id = :sid AND used_at IS NULL', ['now' => now(), 'sid' => $staffId]);   // older links stop working
  o_insert('invites', ['staff_id' => $staffId, 'token_hash' => keyed_hash($tok), 'expires_at' => date('Y-m-d H:i:s', time() + INVITE_HOURS * 3600), 'created_by' => $byId, 'created_at' => now()]);
  return $tok;
}
function send_invite_mail(array $staff, string $tok, bool $reset = false): bool {
  $o = current_office(); $link = abs_url('/invite/' . $tok);
  $GLOBALS['LANG_FORCE'] = $staff['lang'] ?: 'ar';
  $subject = t($reset ? 'mail_reset_subject' : 'mail_invite_subject', lang() === 'ar' ? $o['name_ar'] : $o['name_en']);
  $body = t($reset ? 'mail_reset_body' : 'mail_invite_body', $staff['full_name'], lang() === 'ar' ? $o['name_ar'] : $o['name_en'], $link, INVITE_HOURS);
  unset($GLOBALS['LANG_FORCE']);
  return send_mail($staff['email'], $subject, $body);
}
/** Invite a new staff member. Returns ['token' => …, 'id' => …] or ['error' => key]. */
function invite_staff(array $me, string $email, string $name, string $role, string $lang = 'ar'): array {
  if (!can('staff', 'create', $me)) return ['error' => 'forbidden'];
  $email = norm_email($email); $name = trim($name);
  if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '' || mb_strlen($name) > 160) return ['error' => 'invite_fields'];
  if (!in_array($role, ROLES, true) || !can_assign_role($me, $role)) return ['error' => 'forbidden'];
  $o = current_office();
  if ($o['max_users'] !== null) { $n = orow("SELECT COUNT(*) n FROM staff WHERE office_id = :office AND status <> 'disabled'"); if ((int)$n['n'] >= (int)$o['max_users']) return ['error' => 'limit_users']; }
  if (row('SELECT id FROM staff WHERE email=?', [$email])) return ['error' => 'email_unavailable'];
  $id = o_insert('staff', ['email' => $email, 'full_name' => $name, 'role' => $role, 'lang' => in_array($lang, ['ar', 'en'], true) ? $lang : 'ar', 'status' => 'invited', 'created_at' => now(), 'updated_at' => now()]);
  $tok = issue_invite($id, (int)$me['id']);
  send_invite_mail(orow('SELECT * FROM staff WHERE office_id = :office AND id = :id', ['id' => $id]), $tok);
  law_audit('staff_invited', 'staff', $id, ['email' => $email, 'role' => $role], null, $me);
  return ['token' => $tok, 'id' => $id];
}
/** Look up a usable invitation (any office: the token itself identifies it). */
function invite_lookup(string $tok): ?array {
  $tok = preg_replace('/[^A-Za-z0-9_-]/', '', $tok);   // links copied from mail or chat can carry invisible marks
  if ($tok === '' || strlen($tok) > 100) return null;
  $i = row('SELECT i.*, s.email, s.full_name, s.status AS staff_status, s.lang FROM invites i JOIN staff s ON s.id = i.staff_id AND s.office_id = i.office_id WHERE i.token_hash=?', [keyed_hash($tok)]);
  if (!$i || $i['used_at'] || strtotime($i['expires_at']) < time() || $i['staff_status'] === 'disabled') return null;
  return $i;
}
function accept_invite(string $tok, string $pw, string $confirm): ?string {
  $i = invite_lookup($tok); if (!$i) return 'invite_invalid';
  if ($e = password_problem($pw, $confirm, $i['email'])) return $e;
  q('UPDATE invites SET used_at=? WHERE id=?', [now(), $i['id']]);
  q("UPDATE staff SET password_hash=?, password_set_at=?, status='active', failed_count=0, locked_until=NULL, session_ver=session_ver+1, updated_at=? WHERE id=?",
    [password_hash($pw, PASSWORD_DEFAULT), now(), now(), $i['staff_id']]);
  law_audit($i['staff_status'] === 'invited' ? 'invite_accepted' : 'password_reset', 'staff', $i['staff_id'], [], (int)$i['office_id'], ['id' => $i['staff_id'], 'full_name' => $i['full_name'], 'email' => $i['email']]);
  return null;
}

/* ---------- managing staff (same office only) ---------- */
function can_assign_role(array $me, string $role): bool {
  if ($me['role'] === 'partner') return true;
  return can('staff', 'edit', $me) && !in_array($role, ['partner', 'office_manager'], true);
}
/** May $me manage $target (change role, disable, reset)? Never themselves. */
function can_manage(array $me, array $target): bool {
  if ((int)$target['office_id'] !== (int)$me['office_id'] || (int)$target['id'] === (int)$me['id']) return false;
  if ($me['role'] === 'partner') return true;
  return can('staff', 'edit', $me) && !in_array($target['role'], ['partner', 'office_manager'], true);
}
function staff_in_office(int $id): ?array { return orow('SELECT * FROM staff WHERE office_id = :office AND id = :id', ['id' => $id]); }
function active_partners(): int { $r = orow("SELECT COUNT(*) n FROM staff WHERE office_id = :office AND role = 'partner' AND status = 'active'"); return (int)$r['n']; }

/** Actions on another staff member. Returns null or an error key. */
function staff_action(array $me, int $targetId, string $action, string $arg = ''): ?string {
  $t = staff_in_office($targetId); if (!$t) return 'not_found';
  if (!can_manage($me, $t)) return 'forbidden';
  switch ($action) {
    case 'role':
      if (!in_array($arg, ROLES, true) || !can_assign_role($me, $arg)) return 'forbidden';
      if ($t['role'] === 'partner' && $arg !== 'partner' && $t['status'] === 'active' && active_partners() <= 1) return 'last_partner';
      if ($arg === $t['role']) return null;
      o_update('staff', $targetId, ['role' => $arg, 'session_ver' => (int)$t['session_ver'] + 1, 'updated_at' => now()]);
      law_audit('staff_role_changed', 'staff', $targetId, ['from' => $t['role'], 'to' => $arg], null, $me);
      return null;
    case 'disable':
      if ($t['role'] === 'partner' && $t['status'] === 'active' && active_partners() <= 1) return 'last_partner';
      o_update('staff', $targetId, ['status' => 'disabled', 'session_ver' => (int)$t['session_ver'] + 1, 'updated_at' => now()]);
      oq('UPDATE invites SET used_at = :now WHERE office_id = :office AND staff_id = :sid AND used_at IS NULL', ['now' => now(), 'sid' => $targetId]);
      law_audit('staff_disabled', 'staff', $targetId, [], null, $me);
      return null;
    case 'enable':
      if ($t['status'] !== 'disabled') return null;
      $o = current_office();
      if ($o['max_users'] !== null) { $n = orow("SELECT COUNT(*) n FROM staff WHERE office_id = :office AND status <> 'disabled'"); if ((int)$n['n'] >= (int)$o['max_users']) return 'limit_users'; }
      o_update('staff', $targetId, ['status' => $t['password_hash'] ? 'active' : 'invited', 'updated_at' => now()]);
      law_audit('staff_enabled', 'staff', $targetId, [], null, $me);
      return null;
    case 'revoke':
      o_update('staff', $targetId, ['session_ver' => (int)$t['session_ver'] + 1, 'updated_at' => now()]);
      law_audit('sessions_revoked', 'staff', $targetId, [], null, $me);
      return null;
    case 'reset_2fa':
      o_update('staff', $targetId, ['totp_secret' => null, 'totp_last' => null, 'session_ver' => (int)$t['session_ver'] + 1, 'updated_at' => now()]);
      law_audit('2fa_reset', 'staff', $targetId, [], null, $me);
      return null;
    case 'reinvite':
      if ($t['status'] === 'disabled') return 'forbidden';
      $tok = issue_invite($targetId, (int)$me['id']);
      send_invite_mail($t, $tok, $t['status'] === 'active');
      law_audit($t['status'] === 'active' ? 'password_reset_sent' : 'invite_resent', 'staff', $targetId, [], null, $me);
      $GLOBALS['LAST_INVITE_TOKEN'] = $tok;
      return null;
  }
  return 'not_found';
}
