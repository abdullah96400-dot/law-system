<?php
/* ==========================================================================
   Case files ("matters").
   - A case file can only be opened from a conflict check of this office
     whose recorded decision is "no conflict" or "no conflict with
     conditions", and each check opens at most one file.
   - Who sees a file: anyone with "case files: view", unless the file is
     restricted — then only the managing partner, the lead lawyer and the
     team. A file someone may not see answers "not found".
   - The internal summary and notes are legal content: they need the
     "legal memos and notes" permission (an accountant does not see them by
     default). Clients never see either.
   - Every change goes to the file's timeline and to the audit log (field
     names only, never the content).
   ========================================================================== */
declare(strict_types=1);

const PARTY_ROLES = ['client', 'co_client', 'opponent', 'opponent_lawyer', 'agent', 'witness', 'expert', 'other'];
const MATTER_FIELDS = ['title', 'case_type_id', 'court_id', 'court_level_id', 'circuit', 'official_no', 'lead_id', 'opened_on', 'referral', 'restricted', 'summary'];

/** SQL condition (on alias m) limiting matters to those $me may see. */
function matter_visible_sql(array $me): array {
  if ($me['role'] === 'partner') return ['1=1', []];
  return ['(m.restricted = 0 OR m.lead_id = :me_id OR m.id IN (SELECT t.matter_id FROM matter_team t WHERE t.office_id = :office AND t.staff_id = :me_id))', ['me_id' => (int)$me['id']]];
}
function matter_access(array $m, array $me): bool {
  if ($me['role'] === 'partner') return true;
  if (!can('matters', 'view', $me)) return false;
  if (!$m['restricted'] || (int)$m['lead_id'] === (int)$me['id']) return true;
  return (bool)orow('SELECT id FROM matter_team WHERE office_id = :office AND matter_id = :m AND staff_id = :s', ['m' => $m['id'], 's' => $me['id']]);
}
/** A matter the person may see, or null (callers answer 404). */
function matter_for(int $id, array $me): ?array {
  $m = orow('SELECT * FROM matters WHERE office_id = :office AND id = :id', ['id' => $id]);
  return $m && matter_access($m, $me) ? $m : null;
}
function matter_event(int $matterId, array $me, string $kind, ?string $body = null, array $data = []): void {
  o_insert('matter_events', ['matter_id' => $matterId, 'at' => now(), 'staff_id' => $me['id'], 'kind' => $kind, 'body' => $body, 'data' => $data ? json_encode($data, JSON_UNESCAPED_UNICODE) : null]);
}
function active_staff(): array { return orows("SELECT id, full_name, role FROM staff WHERE office_id = :office AND status = 'active' ORDER BY full_name"); }
function staff_active_in_office(?int $id): bool { return $id && (bool)orow("SELECT id FROM staff WHERE office_id = :office AND id = :id AND status = 'active'", ['id' => $id]); }
/** Approved checks that have not opened a case file yet. */
function checks_available(): array {
  return orows("SELECT k.* FROM conflict_checks k WHERE k.office_id = :office AND k.status IN ('cleared','cleared_cond')
    AND NOT EXISTS (SELECT 1 FROM matters m WHERE m.office_id = :office AND m.conflict_check_id = k.id) ORDER BY k.id DESC");
}
function client_options(): array { return orows('SELECT id, name_ar, name_en, kind FROM contacts WHERE office_id = :office AND archived = 0 ORDER BY is_client DESC, COALESCE(name_ar, name_en)'); }

function valid_date(?string $d): ?string { return $d && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) ? $d : null; }

/** Shared validation for open/edit. Returns [clean data, error key|null]. */
function matter_input(array $in, array $me, bool $withSummary): array {
  $s = fn($k, $max) => ($v = mb_substr(trim((string)($in[$k] ?? '')), 0, $max)) === '' ? null : $v;
  $d = ['title' => $s('title', 200), 'circuit' => $s('circuit', 120), 'official_no' => $s('official_no', 80), 'referral' => $s('referral', 200),
        'case_type_id' => (int)($in['case_type_id'] ?? 0) ?: null, 'court_id' => (int)($in['court_id'] ?? 0) ?: null, 'court_level_id' => (int)($in['court_level_id'] ?? 0) ?: null,
        'lead_id' => (int)($in['lead_id'] ?? 0) ?: null, 'opened_on' => valid_date($in['opened_on'] ?? null) ?? date('Y-m-d'), 'restricted' => !empty($in['restricted']) ? 1 : 0,
        'branch_id' => (int)($in['branch_id'] ?? 0) ?: null];
  if ($withSummary) $d['summary'] = $s('summary', 20000);
  if (!$d['title']) return [$d, 'matter_title_required'];
  foreach (['case_type_id' => 'case_type', 'court_id' => 'court', 'court_level_id' => 'court_level'] as $f => $k) if ($d[$f] && !lookup_get($d[$f], $k)) return [$d, 'matter_bad_list_item'];
  if (!staff_active_in_office($d['lead_id'])) return [$d, 'matter_lead_required'];
  if ($d['branch_id'] && !orow('SELECT id FROM branches WHERE office_id = :office AND id = :id', ['id' => $d['branch_id']])) return [$d, 'matter_bad_list_item'];
  return [$d, null];
}

/** Open a case file. Returns ['id'] or ['error', 'data']. */
function matter_open(array $me, array $in): array {
  [$d, $err] = matter_input($in, $me, can('legal_notes', 'create', $me));
  $check = check_get((int)($in['check_id'] ?? 0));
  if (!$err && !$check) $err = 'matter_check_required';
  if (!$err && !in_array($check['status'], ['cleared', 'cleared_cond'], true)) $err = 'matter_check_not_cleared';
  if (!$err && orow('SELECT id FROM matters WHERE office_id = :office AND conflict_check_id = :k', ['k' => $check['id']])) $err = 'matter_check_used';
  $client = contact_get((int)($in['client_id'] ?? 0));
  if (!$err && (!$client || $client['archived'])) $err = 'matter_client_required';
  $status = lookup_get((int)($in['status_id'] ?? 0), 'status');
  if (!$err && (!$status || !$status['active'] || $status['is_closed'])) $err = 'matter_status_required';
  if ($err) return ['error' => $err, 'data' => $d + $in];
  $pdo = db(); $pdo->beginTransaction();
  try {
    $id = o_insert('matters', $d + ['ref' => next_ref('M', 'M'), 'client_id' => $client['id'], 'conflict_check_id' => $check['id'], 'status_id' => $status['id'],
      'next_step' => null, 'next_due' => null, 'created_by' => $me['id'], 'created_at' => now(), 'updated_at' => now()]);
    if (!$client['is_client']) o_update('contacts', (int)$client['id'], ['is_client' => 1, 'updated_at' => now()]);
    o_insert('matter_parties', ['matter_id' => $id, 'contact_id' => $client['id'], 'role' => 'client', 'created_at' => now()]);
    foreach (array_unique([(int)$d['lead_id'], (int)$me['id']]) as $sid) o_insert('matter_team', ['matter_id' => $id, 'staff_id' => $sid, 'created_at' => now()]);
    matter_event($id, $me, 'created', null, ['check' => $check['ref'], 'status' => $status['id']]);
    $pdo->commit();
  } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
  law_audit('matter_opened', 'matter', $id, ['check' => $check['ref'], 'restricted' => (bool)$d['restricted']], null, $me);
  return ['id' => $id];
}
function matter_update(array $me, array $m, array $in): ?string {
  $withSummary = can('legal_notes', 'edit', $me);
  [$d, $err] = matter_input($in, $me, $withSummary); if ($err) return $err;
  if ($d['restricted'] !== (int)$m['restricted'] && !in_array($me['role'], ['partner', 'office_manager'], true) && (int)$m['lead_id'] !== (int)$me['id']) return 'matter_restrict_forbidden';
  $changed = []; foreach ($d as $f => $v) if ((string)$m[$f] !== (string)$v) $changed[] = $f;
  if (!$changed) return null;
  o_update('matters', (int)$m['id'], $d + ['updated_at' => now()]);
  if (in_array('lead_id', $changed, true) && !orow('SELECT id FROM matter_team WHERE office_id = :office AND matter_id = :m AND staff_id = :s', ['m' => $m['id'], 's' => $d['lead_id']]))
    o_insert('matter_team', ['matter_id' => $m['id'], 'staff_id' => $d['lead_id'], 'created_at' => now()]);
  matter_event((int)$m['id'], $me, 'fields', null, ['fields' => $changed]);
  law_audit('matter_updated', 'matter', $m['id'], ['fields' => $changed], null, $me);
  return null;
}
function matter_set_status(array $me, array $m, int $statusId, string $note): ?string {
  $st = lookup_get($statusId, 'status'); if (!$st || !$st['active']) return 'matter_status_required';
  if ((int)$st['id'] === (int)$m['status_id']) return null;
  o_update('matters', (int)$m['id'], ['status_id' => $st['id'], 'closed_at' => $st['is_closed'] ? now() : null, 'updated_at' => now()]);
  matter_event((int)$m['id'], $me, 'status', mb_substr(trim($note), 0, 2000) ?: null, ['from' => (int)$m['status_id'], 'to' => (int)$st['id']]);
  law_audit('matter_status', 'matter', $m['id'], ['from' => (int)$m['status_id'], 'to' => (int)$st['id']], null, $me);
  return null;
}
function matter_set_next(array $me, array $m, string $step, ?string $due): ?string {
  $step = mb_substr(trim($step), 0, 300); $due = valid_date($due);
  if ($step === '' && $due) return 'matter_next_step_required';
  o_update('matters', (int)$m['id'], ['next_step' => $step ?: null, 'next_due' => $step ? $due : null, 'updated_at' => now()]);
  matter_event((int)$m['id'], $me, 'next', $step ?: null, ['due' => $step ? $due : null]);
  law_audit('matter_next_step', 'matter', $m['id'], [], null, $me);
  return null;
}
function matter_party_add(array $me, array $m, int $contactId, string $role, string $note): ?string {
  $c = contact_get($contactId); if (!$c || $c['archived']) return 'matter_party_contact';
  if (!in_array($role, PARTY_ROLES, true)) return 'matter_party_role';
  if (orow('SELECT id FROM matter_parties WHERE office_id = :office AND matter_id = :m AND contact_id = :c AND role = :r', ['m' => $m['id'], 'c' => $contactId, 'r' => $role])) return 'matter_party_exists';
  $pid = o_insert('matter_parties', ['matter_id' => $m['id'], 'contact_id' => $contactId, 'role' => $role, 'note' => mb_substr(trim($note), 0, 300) ?: null, 'created_at' => now()]);
  matter_event((int)$m['id'], $me, 'party_add', null, ['contact' => $contactId, 'role' => $role]);
  law_audit('matter_party_added', 'matter', $m['id'], ['party' => $pid, 'role' => $role], null, $me);
  return null;
}
function matter_party_remove(array $me, array $m, int $partyId): ?string {
  $p = orow('SELECT * FROM matter_parties WHERE office_id = :office AND id = :id AND matter_id = :m', ['id' => $partyId, 'm' => $m['id']]); if (!$p) return 'not_found';
  if ($p['role'] === 'client' && (int)$p['contact_id'] === (int)$m['client_id']) return 'matter_party_main_client';
  oq('DELETE FROM matter_parties WHERE office_id = :office AND id = :id', ['id' => $partyId]);
  matter_event((int)$m['id'], $me, 'party_remove', null, ['contact' => (int)$p['contact_id'], 'role' => $p['role']]);
  law_audit('matter_party_removed', 'matter', $m['id'], ['party' => $partyId, 'role' => $p['role']], null, $me);
  return null;
}
function matter_team_add(array $me, array $m, int $staffId): ?string {
  if (!staff_active_in_office($staffId)) return 'matter_team_staff';
  if (orow('SELECT id FROM matter_team WHERE office_id = :office AND matter_id = :m AND staff_id = :s', ['m' => $m['id'], 's' => $staffId])) return null;
  o_insert('matter_team', ['matter_id' => $m['id'], 'staff_id' => $staffId, 'created_at' => now()]);
  matter_event((int)$m['id'], $me, 'team_add', null, ['staff' => $staffId]);
  law_audit('matter_team_changed', 'matter', $m['id'], ['added' => $staffId], null, $me);
  return null;
}
function matter_team_remove(array $me, array $m, int $staffId): ?string {
  if ($staffId === (int)$m['lead_id']) return 'matter_team_lead';
  if (!oq('DELETE FROM matter_team WHERE office_id = :office AND matter_id = :m AND staff_id = :s', ['m' => $m['id'], 's' => $staffId])->rowCount()) return 'not_found';
  matter_event((int)$m['id'], $me, 'team_remove', null, ['staff' => $staffId]);
  law_audit('matter_team_changed', 'matter', $m['id'], ['removed' => $staffId], null, $me);
  return null;
}
function matter_note_add(array $me, array $m, string $body): ?string {
  $body = mb_substr(trim($body), 0, 20000); if ($body === '') return 'matter_note_required';
  matter_event((int)$m['id'], $me, 'note', $body);
  o_update('matters', (int)$m['id'], ['updated_at' => now()]);
  law_audit('matter_note_added', 'matter', $m['id'], [], null, $me);   // never the note itself
  return null;
}
function matter_parties(int $id): array {
  return orows('SELECT p.*, c.name_ar, c.name_en, c.kind, c.archived FROM matter_parties p JOIN contacts c ON c.id = p.contact_id AND c.office_id = p.office_id
    WHERE p.office_id = :office AND p.matter_id = :m ORDER BY p.id', ['m' => $id]);
}
function matter_team(int $id): array {
  return orows('SELECT s.id, s.full_name, s.role, s.status FROM matter_team t JOIN staff s ON s.id = t.staff_id AND s.office_id = t.office_id WHERE t.office_id = :office AND t.matter_id = :m ORDER BY s.full_name', ['m' => $id]);
}
/** Timeline, newest first. Notes only for people allowed to read legal notes. */
function matter_events(int $id, array $me): array {
  $notes = can('legal_notes', 'view', $me) ? '' : " AND e.kind <> 'note'";
  return orows("SELECT e.*, s.full_name FROM matter_events e LEFT JOIN staff s ON s.id = e.staff_id AND s.office_id = e.office_id WHERE e.office_id = :office AND e.matter_id = :m$notes ORDER BY e.id DESC LIMIT 300", ['m' => $id]);
}
/** List with filters: q, status, lead, type, state (open|closed|all), page. */
function matter_list(array $me, array $f, int $per = 25): array {
  [$vis, $p] = matter_visible_sql($me);
  $w = ['m.office_id = :office', $vis];
  if (($f['state'] ?? 'open') === 'open') $w[] = 'm.closed_at IS NULL'; elseif (($f['state'] ?? '') === 'closed') $w[] = 'm.closed_at IS NOT NULL';
  if (!empty($f['status'])) { $w[] = 'm.status_id = :st'; $p['st'] = (int)$f['status']; }
  if (!empty($f['lead'])) { $w[] = 'm.lead_id = :lead'; $p['lead'] = (int)$f['lead']; }
  if (!empty($f['type'])) { $w[] = 'm.case_type_id = :ty'; $p['ty'] = (int)$f['type']; }
  if (($q = trim((string)($f['q'] ?? ''))) !== '') { $w[] = '(m.ref LIKE :q OR m.title LIKE :q OR m.official_no LIKE :q OR c.name_ar LIKE :q OR c.name_en LIKE :q)'; $p['q'] = '%' . $q . '%'; }
  $from = 'FROM matters m JOIN contacts c ON c.id = m.client_id AND c.office_id = m.office_id WHERE ' . implode(' AND ', $w);
  $total = (int)orow("SELECT COUNT(*) n $from", $p)['n'];
  $page = max(1, (int)($f['page'] ?? 1));
  $order = ['next' => '(m.next_due IS NULL), m.next_due, m.updated_at DESC', 'hearing' => '(next_hearing IS NULL), next_hearing, m.updated_at DESC', 'new' => 'm.opened_on DESC, m.id DESC',
            'ref' => 'm.ref DESC', 'updated' => 'm.updated_at DESC'][$f['sort'] ?? 'next'] ?? '(m.next_due IS NULL), m.next_due, m.updated_at DESC';
  $p['nowh'] = date('Y-m-d H:i:s');
  $rows = orows("SELECT m.*, c.name_ar AS client_ar, c.name_en AS client_en,
      (SELECT MIN(a.starts_at) FROM appointments a WHERE a.office_id = m.office_id AND a.matter_id = m.id AND a.kind = 'hearing' AND a.status = 'scheduled' AND a.starts_at >= :nowh) AS next_hearing
    $from ORDER BY $order LIMIT $per OFFSET " . (($page - 1) * $per), $p);
  return [$rows, $total];
}
function matters_for_contact(int $contactId, array $me): array {
  [$vis, $p] = matter_visible_sql($me);
  return orows("SELECT DISTINCT m.id, m.ref, m.title, m.status_id, m.closed_at, mp.role FROM matter_parties mp JOIN matters m ON m.id = mp.matter_id AND m.office_id = mp.office_id
    WHERE mp.office_id = :office AND mp.contact_id = :c AND $vis ORDER BY m.id DESC", $p + ['c' => $contactId]);
}
function matter_for_check(int $checkId): ?array { return orow('SELECT id, ref FROM matters WHERE office_id = :office AND conflict_check_id = :k', ['k' => $checkId]); }
