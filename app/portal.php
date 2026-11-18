<?php
/* ==========================================================================
   Client portal, at /p/{OFFICE-CODE}/… with the office's name.
   - Clients sign in with a one-time code sent to their email (10 minutes,
     5 tries, stored only as a keyed hash). No passwords.
   - A client sees a case file only when the office explicitly shares it
     with that client (the client must be a client/co-client party of the
     file and the party's "show in portal" switch must be on).
   - Inside a shared file the client sees only what the office chose: the
     status, published updates (approved by the responsible lawyer, the
     managing partner or the office manager), hearings marked for the portal,
     documents marked for the portal, issued invoices marked for the portal,
     document requests, and the message thread. Never internal notes, the
     internal summary, other parties' details or other clients.
   - Emails to clients only say "there is something new in your portal".
   ========================================================================== */
declare(strict_types=1);

const PORTAL_CODE_MINUTES = 10;
const PORTAL_PARTY_ROLES = ['client', 'co_client'];

/* ---------- office and session ---------- */
function portal_office(string $code): ?array {
  $o = row('SELECT * FROM offices WHERE code=?', [strtoupper($code)]);
  if (!$o || $o['status'] === 'suspended') return null;
  set_office_scope((int)$o['id']);
  return $o;
}
function portal_base(): string { return '/p/' . strtolower(current_office()['code']); }
function current_portal_user(): ?array {
  if (array_key_exists('PORTAL_USER', $GLOBALS)) return $GLOBALS['PORTAL_USER'];
  $GLOBALS['PORTAL_USER'] = null;
  $p = $_SESSION['portal'] ?? null; if (!$p || (int)$p['office'] !== office_id()) return null;
  $u = orow("SELECT * FROM portal_users WHERE office_id = :office AND id = :id AND status = 'active'", ['id' => (int)$p['id']]);
  if (!$u || (int)$u['session_ver'] !== (int)$p['sv'] || time() - (int)$p['last'] > 3600) { unset($_SESSION['portal']); return null; }
  $_SESSION['portal']['last'] = time();
  return $GLOBALS['PORTAL_USER'] = $u;
}
function portal_lang(): void { if ($u = current_portal_user()) $GLOBALS['LANG_FORCE'] = $u['lang']; }

/** Step 1: send a code if the email belongs to an active portal account of this office. Same answer either way. */
function portal_send_code(string $email): void {
  if (ip_blocked()) return;
  $email = norm_email($email);
  $u = filter_var($email, FILTER_VALIDATE_EMAIL) ? orow("SELECT * FROM portal_users WHERE office_id = :office AND email = :e AND status = 'active'", ['e' => $email]) : null;
  if (!$u) { q('INSERT INTO auth_failures (ip_hash, at) VALUES (?,?)', [keyed_hash(ip()), now()]); $_SESSION['portal_pending'] = ['office' => office_id(), 'id' => 0, 'at' => time()]; return; }
  $recent = (int)orow('SELECT COUNT(*) n FROM portal_codes WHERE office_id = :office AND portal_user_id = :u AND created_at > :t', ['u' => $u['id'], 't' => date('Y-m-d H:i:s', time() - 900)])['n'];
  $_SESSION['portal_pending'] = ['office' => office_id(), 'id' => (int)$u['id'], 'at' => time()];
  if ($recent >= 5) return;   // at most 5 codes per 15 minutes
  $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
  oq('UPDATE portal_codes SET used_at = :now WHERE office_id = :office AND portal_user_id = :u AND used_at IS NULL', ['now' => now(), 'u' => $u['id']]);
  o_insert('portal_codes', ['portal_user_id' => $u['id'], 'code_hash' => keyed_hash('portal|' . $u['id'] . '|' . $code), 'expires_at' => date('Y-m-d H:i:s', time() + PORTAL_CODE_MINUTES * 60), 'created_at' => now()]);
  $GLOBALS['LANG_FORCE'] = $u['lang'];
  send_mail($u['email'], t('mail_pcode_subject', office_name()), t('mail_pcode_body', office_name(), $code, PORTAL_CODE_MINUTES));
  unset($GLOBALS['LANG_FORCE']);
}
/** Step 2: check the code. Returns null on success or an error key. */
function portal_verify_code(string $code): ?string {
  $p = $_SESSION['portal_pending'] ?? null;
  if (!$p || (int)$p['office'] !== office_id() || time() - (int)$p['at'] > PORTAL_CODE_MINUTES * 60) return 'session_expired';
  if (!$p['id']) { q('INSERT INTO auth_failures (ip_hash, at) VALUES (?,?)', [keyed_hash(ip()), now()]); return 'code_wrong'; }
  $c = orow('SELECT * FROM portal_codes WHERE office_id = :office AND portal_user_id = :u AND used_at IS NULL AND expires_at > :now ORDER BY id DESC LIMIT 1', ['u' => $p['id'], 'now' => now()]);
  if (!$c || (int)$c['attempts'] >= 5) return 'session_expired';
  if (!hash_equals($c['code_hash'], keyed_hash('portal|' . $p['id'] . '|' . digits_only($code)))) {
    oq('UPDATE portal_codes SET attempts = attempts + 1 WHERE office_id = :office AND id = :id', ['id' => $c['id']]);
    q('INSERT INTO auth_failures (ip_hash, at) VALUES (?,?)', [keyed_hash(ip()), now()]);
    return 'code_wrong';
  }
  oq('UPDATE portal_codes SET used_at = :now WHERE office_id = :office AND id = :id', ['now' => now(), 'id' => $c['id']]);
  $u = orow('SELECT * FROM portal_users WHERE office_id = :office AND id = :id', ['id' => $p['id']]);
  session_regenerate_id_safe();
  unset($_SESSION['portal_pending']);
  $_SESSION['portal'] = ['office' => office_id(), 'id' => (int)$u['id'], 'sv' => (int)$u['session_ver'], 'last' => time()];
  oq('UPDATE portal_users SET last_login = :now WHERE office_id = :office AND id = :id', ['now' => now(), 'id' => $u['id']]);
  unset($GLOBALS['PORTAL_USER']);
  law_audit('portal_login', 'portal_user', $u['id'], [], null, ['id' => null, 'full_name' => 'Client portal #' . $u['id'], 'email' => '']);
  return null;
}
function portal_logout(): void { unset($_SESSION['portal'], $GLOBALS['PORTAL_USER']); }

/* ---------- what a client may see ---------- */
function portal_matters(array $u): array {
  return orows("SELECT DISTINCT m.* FROM matters m JOIN matter_parties p ON p.matter_id = m.id AND p.office_id = m.office_id
    WHERE m.office_id = :office AND p.contact_id = :c AND p.portal_visible = 1 AND p.role IN ('client','co_client') ORDER BY m.closed_at IS NOT NULL, m.updated_at DESC", ['c' => $u['contact_id']]);
}
function portal_matter(array $u, int $id): ?array {
  return orow("SELECT m.* FROM matters m JOIN matter_parties p ON p.matter_id = m.id AND p.office_id = m.office_id
    WHERE m.office_id = :office AND m.id = :id AND p.contact_id = :c AND p.portal_visible = 1 AND p.role IN ('client','co_client') LIMIT 1", ['id' => $id, 'c' => $u['contact_id']]);
}
function portal_updates(int $matterId): array { return orows("SELECT * FROM client_updates WHERE office_id = :office AND matter_id = :m AND status = 'published' ORDER BY published_at DESC", ['m' => $matterId]); }
function portal_hearings(int $matterId): array { return orows("SELECT id, kind, title, starts_at, court_id, circuit, location, status FROM appointments WHERE office_id = :office AND matter_id = :m AND portal_visible = 1 AND status <> 'cancelled' ORDER BY starts_at DESC", ['m' => $matterId]); }
function portal_documents(int $matterId): array { return orows('SELECT * FROM documents WHERE office_id = :office AND matter_id = :m AND portal_visible = 1 AND archived = 0 ORDER BY id DESC', ['m' => $matterId]); }
function portal_invoices(int $matterId, int $contactId): array { return orows("SELECT * FROM invoices WHERE office_id = :office AND matter_id = :m AND client_id = :c AND portal_visible = 1 AND status = 'issued' ORDER BY id DESC", ['m' => $matterId, 'c' => $contactId]); }
function portal_requests(int $matterId): array { return orows("SELECT * FROM doc_requests WHERE office_id = :office AND matter_id = :m AND status <> 'cancelled' ORDER BY status, id DESC", ['m' => $matterId]); }
function matter_messages(int $matterId): array { return orows('SELECT * FROM portal_messages WHERE office_id = :office AND matter_id = :m ORDER BY id', ['m' => $matterId]); }

function portal_send_message(array $u, array $m, string $body): ?string {
  $body = mb_substr(trim($body), 0, 4000); if ($body === '') return 'matter_note_required';
  $id = o_insert('portal_messages', ['matter_id' => $m['id'], 'portal_user_id' => $u['id'], 'body' => $body, 'created_at' => now()]);
  foreach (appt_people(['lead_id' => $m['lead_id'], 'matter_id' => $m['id']]) as $sid) notify($sid, 'portal_message', 'n_portal_message', [$m['ref']], '/matters/' . $m['id'] . '#portal', "pmsg:$id");
  law_audit('portal_message_sent', 'matter', $m['id'], ['message' => $id], null, ['id' => null, 'full_name' => 'Client portal #' . $u['id'], 'email' => '']);
  return null;
}
function portal_upload(array $u, array $m, int $requestId, ?array $file): ?string {
  $req = $requestId ? orow("SELECT * FROM doc_requests WHERE office_id = :office AND id = :id AND matter_id = :m AND status = 'open'", ['id' => $requestId, 'm' => $m['id']]) : null;
  if ($requestId && !$req) return 'not_found';
  $r = doc_create(['id' => null], ['matter_id' => $m['id'], 'title' => $req ? $req['title'] : t('doc_from_client'), 'category_id' => 0], $file, (int)$u['id']);
  if (isset($r['error'])) return $r['error'];
  o_update('documents', (int)$r['id'], ['portal_visible' => 1, 'contact_id' => $u['contact_id']]);   // the client sees what they sent
  if ($req) o_update('doc_requests', (int)$req['id'], ['status' => 'received', 'document_id' => $r['id']]);
  foreach (appt_people(['lead_id' => $m['lead_id'], 'matter_id' => $m['id']]) as $sid) notify($sid, 'portal_upload', 'n_portal_upload', [$m['ref']], '/documents/' . $r['id'], "pup:{$r['id']}");
  return null;
}

/* ---------- staff side ---------- */
function portal_user_for_contact(int $contactId): ?array { return orow('SELECT * FROM portal_users WHERE office_id = :office AND contact_id = :c', ['c' => $contactId]); }
function portal_invite(array $me, array $c): ?string {
  if (!$c['email']) return 'portal_needs_email';
  if ($c['archived']) return 'not_found';
  $u = portal_user_for_contact((int)$c['id']);
  if ($u) { oq("UPDATE portal_users SET status = 'active', email = :e, session_ver = session_ver + 1 WHERE office_id = :office AND id = :id", ['e' => $c['email'], 'id' => $u['id']]); $uid = (int)$u['id']; }
  else {
    if (orow('SELECT id FROM portal_users WHERE office_id = :office AND email = :e', ['e' => $c['email']])) return 'portal_email_used';
    $uid = o_insert('portal_users', ['contact_id' => $c['id'], 'email' => $c['email'], 'lang' => $c['pref_lang'] ?: 'ar', 'status' => 'active', 'invited_by' => $me['id'], 'created_at' => now()]);
  }
  $GLOBALS['LANG_FORCE'] = $c['pref_lang'] ?: 'ar';
  send_mail($c['email'], t('mail_pinvite_subject', office_name()), t('mail_pinvite_body', contact_label($c), office_name(), abs_url(portal_base() . '/login')));
  unset($GLOBALS['LANG_FORCE']);
  law_audit('portal_invited', 'contact', $c['id'], ['portal_user' => $uid], null, $me);
  return null;
}
function portal_disable(array $me, array $c): ?string {
  $u = portal_user_for_contact((int)$c['id']); if (!$u) return 'not_found';
  oq("UPDATE portal_users SET status = 'disabled', session_ver = session_ver + 1 WHERE office_id = :office AND id = :id", ['id' => $u['id']]);
  law_audit('portal_disabled', 'contact', $c['id'], [], null, $me);
  return null;
}
/** Tell the client's portal accounts on this file that there is something new (no details). */
function portal_notify_matter(array $m): void {
  foreach (orows("SELECT u.* FROM portal_users u JOIN matter_parties p ON p.contact_id = u.contact_id AND p.office_id = u.office_id
      WHERE u.office_id = :office AND p.matter_id = :m AND p.portal_visible = 1 AND p.role IN ('client','co_client') AND u.status = 'active' AND u.notify = 1", ['m' => $m['id']]) as $u) {
    $GLOBALS['LANG_FORCE'] = $u['lang'];
    send_mail($u['email'], t('mail_pnew_subject', office_name()), t('mail_pnew_body', office_name(), abs_url(portal_base() . '/login')));
    unset($GLOBALS['LANG_FORCE']);
  }
}
function can_approve_update(array $me, array $m): bool { return in_array($me['role'], ['partner', 'office_manager'], true) || (int)$m['lead_id'] === (int)$me['id']; }
function update_draft(array $me, array $m, string $body): ?string {
  $body = mb_substr(trim($body), 0, 8000); if ($body === '') return 'matter_note_required';
  $id = o_insert('client_updates', ['matter_id' => $m['id'], 'body' => $body, 'status' => 'draft', 'created_by' => $me['id'], 'created_at' => now()]);
  law_audit('client_update_drafted', 'matter', $m['id'], ['update' => $id], null, $me);
  if (!can_approve_update($me, $m)) notify((int)$m['lead_id'], 'update_to_approve', 'n_update_to_approve', [$m['ref']], '/matters/' . $m['id'] . '#portal', "upd:$id");
  return null;
}
function update_publish(array $me, array $m, int $id): ?string {
  if (!can_approve_update($me, $m)) return 'update_approver_only';
  if (!oq("UPDATE client_updates SET status = 'published', approved_by = :me, published_at = :now WHERE office_id = :office AND id = :id AND matter_id = :m AND status = 'draft'", ['me' => $me['id'], 'now' => now(), 'id' => $id, 'm' => $m['id']])->rowCount()) return 'not_found';
  matter_event((int)$m['id'], $me, 'update_published', null, ['update' => $id]);
  share_mark($me, (int)$m['id'], 'update', $id, true);
  law_audit('client_update_published', 'matter', $m['id'], ['update' => $id], null, $me);
  portal_notify_matter($m);
  return null;
}
function update_discard(array $me, array $m, int $id): ?string {
  if (!oq("DELETE FROM client_updates WHERE office_id = :office AND id = :id AND matter_id = :m AND status = 'draft'", ['id' => $id, 'm' => $m['id']])->rowCount()) return 'not_found';
  law_audit('client_update_discarded', 'matter', $m['id'], ['update' => $id], null, $me);
  return null;
}
function staff_reply(array $me, array $m, string $body): ?string {
  $body = mb_substr(trim($body), 0, 4000); if ($body === '') return 'matter_note_required';
  $id = o_insert('portal_messages', ['matter_id' => $m['id'], 'staff_id' => $me['id'], 'body' => $body, 'created_at' => now()]);
  oq('UPDATE portal_messages SET read_by_staff_at = :now WHERE office_id = :office AND matter_id = :m AND portal_user_id IS NOT NULL AND read_by_staff_at IS NULL', ['now' => now(), 'm' => $m['id']]);
  law_audit('portal_reply_sent', 'matter', $m['id'], ['message' => $id], null, $me);
  portal_notify_matter($m);
  return null;
}
function doc_request_add(array $me, array $m, string $title): ?string {
  $title = mb_substr(trim($title), 0, 200); if ($title === '') return 'doc_title_required';
  o_insert('doc_requests', ['matter_id' => $m['id'], 'title' => $title, 'status' => 'open', 'created_by' => $me['id'], 'created_at' => now()]);
  law_audit('doc_requested', 'matter', $m['id'], [], null, $me);
  portal_notify_matter($m);
  return null;
}
function party_portal_toggle(array $me, array $m, int $partyId, bool $on): ?string {
  $p = orow("SELECT * FROM matter_parties WHERE office_id = :office AND id = :id AND matter_id = :m AND role IN ('client','co_client')", ['id' => $partyId, 'm' => $m['id']]); if (!$p) return 'not_found';
  if ($m['restricted'] && $on && !in_array($me['role'], ['partner', 'office_manager'], true) && (int)$m['lead_id'] !== (int)$me['id']) return 'matter_restrict_forbidden';
  o_update('matter_parties', $partyId, ['portal_visible' => $on ? 1 : 0]);
  matter_event((int)$m['id'], $me, 'portal_share', null, ['contact' => (int)$p['contact_id'], 'on' => $on]);
  if ((bool)$p['portal_visible'] !== $on) share_mark($me, (int)$m['id'], 'file', (int)$p['contact_id'], $on);
  law_audit($on ? 'portal_shared' : 'portal_unshared', 'matter', $m['id'], ['party' => $partyId], null, $me);
  return null;
}

/* ---------- v7 portal: home summary and sections ---------- */
/** Checked procedures and judgments the office shared, for one file. $judgments: only judgments, or everything else. */
function portal_actions(int $matterId, ?bool $judgments = null): array {
  $w = $judgments === null ? '' : ($judgments ? " AND kind = 'judgment'" : " AND kind <> 'judgment'");
  return orows("SELECT id, kind, title, details, action_on FROM matter_actions WHERE office_id = :office AND matter_id = :m AND portal_visible = 1 AND verified_by IS NOT NULL$w ORDER BY action_on DESC, id DESC", ['m' => $matterId]);
}
/** Fee agreements whose statement is shared with this client, in files shared with them. */
function portal_statements(array $u, ?int $matterId = null): array {
  $out = [];
  foreach (portal_matters($u) as $m) {
    if ($matterId && (int)$m['id'] !== $matterId) continue;
    foreach (orows('SELECT * FROM fee_agreements WHERE office_id = :office AND matter_id = :m AND client_id = :c', ['m' => $m['id'], 'c' => $u['contact_id']]) as $a)
      if (statement_shared((int)$a['id'])) $out[] = $a + ['matter_ref' => $m['ref'], 'matter_title' => $m['title']];
  }
  return $out;
}
function portal_statement(array $u, int $agreementId): ?array {
  foreach (portal_statements($u) as $a) if ((int)$a['id'] === $agreementId) return $a;
  return null;
}
/** Notices the office actually sent to this client (any channel), in shared files. */
function portal_notices(array $u): array {
  $ids = array_map(fn($m) => (int)$m['id'], portal_matters($u)); if (!$ids) return [];
  return orows("SELECT * FROM due_notices WHERE office_id = :office AND client_id = :c AND status = 'sent' AND matter_id IN (" . implode(',', $ids) . ') ORDER BY sent_at DESC LIMIT 50', ['c' => $u['contact_id']]);
}
/** Everything the home page shows: files, next hearing, last approved update, amount due now. */
function portal_overview(array $u): array {
  $ms = portal_matters($u); $ids = array_map(fn($m) => (int)$m['id'], $ms);
  $in = $ids ? implode(',', $ids) : '0';
  $next = orow("SELECT id, matter_id, kind, title, starts_at, court_id, circuit FROM appointments WHERE office_id = :office AND matter_id IN ($in) AND portal_visible = 1 AND status = 'scheduled' AND starts_at >= :now ORDER BY starts_at LIMIT 1", ['now' => now()]);
  $upd = orow("SELECT matter_id, body, published_at FROM client_updates WHERE office_id = :office AND matter_id IN ($in) AND status = 'published' ORDER BY published_at DESC LIMIT 1");
  $due = 0; $agIds = [];
  foreach (portal_statements($u) as $a) { $due += agreement_summary($a)['due_now']; $agIds[] = (int)$a['id']; }
  foreach (orows("SELECT * FROM invoices WHERE office_id = :office AND client_id = :c AND portal_visible = 1 AND status = 'issued' AND paid < total AND matter_id IN ($in)", ['c' => $u['contact_id']]) as $i)
    if (!in_array((int)$i['agreement_id'], $agIds, true) && (!$i['due_on'] || $i['due_on'] <= date('Y-m-d'))) $due += (int)$i['total'] - (int)$i['paid'];
  $counts = ['hearings' => (int)orow("SELECT COUNT(*) n FROM appointments WHERE office_id = :office AND matter_id IN ($in) AND portal_visible = 1 AND status = 'scheduled' AND starts_at >= :now", ['now' => now()])['n'],
    'actions' => (int)orow("SELECT COUNT(*) n FROM matter_actions WHERE office_id = :office AND matter_id IN ($in) AND portal_visible = 1 AND verified_by IS NOT NULL AND kind <> 'judgment'")['n'],
    'judgments' => (int)orow("SELECT COUNT(*) n FROM matter_actions WHERE office_id = :office AND matter_id IN ($in) AND portal_visible = 1 AND verified_by IS NOT NULL AND kind = 'judgment'")['n'],
    'documents' => (int)orow("SELECT COUNT(*) n FROM documents WHERE office_id = :office AND matter_id IN ($in) AND portal_visible = 1 AND archived = 0")['n']];
  return ['matters' => $ms, 'next' => $next, 'update' => $upd, 'due' => $due, 'counts' => $counts];
}
function portal_actor(array $u): array { return ['id' => null, 'full_name' => 'Client portal #' . $u['id'], 'email' => '']; }
