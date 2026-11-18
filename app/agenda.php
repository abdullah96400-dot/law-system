<?php
/* ==========================================================================
   Hearings and appointments, tasks, reminders.
   - Court dates are entered by the office. For each one the system keeps
     where the date came from, who entered it and who reviewed it. Changing
     the date, court or source clears the review. A different person must
     review (unless the office has a single active member).
   - No appeal or filing deadline is ever calculated by the system.
   - Reminders are help, not a guarantee: the lawyer checks official dates.
   - Emails never carry case details: they only say there are new reminders.
   ========================================================================== */
declare(strict_types=1);

const APPT_KINDS = ['hearing', 'meeting', 'internal', 'deadline'];
const APPT_SOURCES = ['court_notice', 'court_portal', 'in_session', 'client', 'opposing', 'other'];
const APPT_STATUSES = ['scheduled', 'done', 'postponed', 'cancelled'];
const TASK_PRIORITIES = ['low', 'normal', 'high'];
const TASK_STATUSES = ['open', 'in_progress', 'done', 'cancelled'];
const TASK_RECUR = ['none', 'weekly', 'monthly'];

/* ---------- shared visibility for items that may belong to a case file ---------- */
/** SQL condition on alias x (with x.matter_id) and LEFT JOIN matters m. */
function linked_visible_sql(array $me): array {
  if ($me['role'] === 'partner') return ['1=1', []];
  if (!can('matters', 'view', $me)) return ['(x.matter_id IS NULL)', []];   // no case files: only items not linked to one
  return ['(x.matter_id IS NULL OR m.restricted = 0 OR m.lead_id = :me_id OR m.id IN (SELECT t.matter_id FROM matter_team t WHERE t.office_id = :office AND t.staff_id = :me_id))', ['me_id' => (int)$me['id']]];
}
/** The same condition for a plain list of case files (alias m). */
function linked_matter_sql(string $vis): string { return $vis === '1=1' ? '1=1' : '(' . str_replace('(x.matter_id IS NULL OR ', '(', $vis) . ')'; }
function linked_ok(?int $matterId, array $me): bool { return !$matterId || matter_for($matterId, $me) !== null; }
function single_member_office(): bool { return (int)orow("SELECT COUNT(*) n FROM staff WHERE office_id = :office AND status = 'active'")['n'] <= 1; }

/* ---------- appointments ---------- */
function appt_get_for(int $id, array $me): ?array {
  $a = orow('SELECT * FROM appointments WHERE office_id = :office AND id = :id', ['id' => $id]);
  return $a && linked_ok($a['matter_id'] ? (int)$a['matter_id'] : null, $me) ? $a : null;
}
function parse_remind(string $s): string {
  $d = array_values(array_unique(array_filter(array_map('intval', preg_split('/[,،\s]+/u', digits_only_keep($s))), fn($n) => $n >= 0 && $n <= 30)));
  rsort($d); return implode(',', array_slice($d, 0, 3));
}
function appt_input(array $in, array $me): array {
  $s = fn($k, $max) => ($v = mb_substr(trim((string)($in[$k] ?? '')), 0, $max)) === '' ? null : $v;
  $date = valid_date($in['date'] ?? null); $time = preg_match('/^\d{2}:\d{2}$/', (string)($in['time'] ?? '')) ? $in['time'] : null;
  $d = ['kind' => in_array($in['kind'] ?? '', APPT_KINDS, true) ? $in['kind'] : 'hearing', 'title' => $s('title', 200),
        'starts_at' => $date ? $date . ' ' . ($time ?: '00:00') . ':00' : null, 'ends_at' => null,
        'court_id' => (int)($in['court_id'] ?? 0) ?: null, 'circuit' => $s('circuit', 120), 'location' => $s('location', 200),
        'lead_id' => (int)($in['lead_id'] ?? 0) ?: null, 'source' => in_array($in['source'] ?? '', APPT_SOURCES, true) ? $in['source'] : null,
        'source_note' => $s('source_note', 300), 'remind_days' => parse_remind((string)($in['remind_days'] ?? '1,3')), 'matter_id' => (int)($in['matter_id'] ?? 0) ?: null];
  if (!$d['title']) return [$d, 'appt_title_required'];
  if (!$d['starts_at']) return [$d, 'appt_date_required'];
  if (!$d['source']) return [$d, 'appt_source_required'];
  if ($d['court_id'] && !lookup_get($d['court_id'], 'court')) return [$d, 'matter_bad_list_item'];
  if (!staff_active_in_office($d['lead_id'])) return [$d, 'matter_lead_required'];
  if ($d['matter_id'] && !matter_for($d['matter_id'], $me)) return [$d, 'appt_matter_bad'];
  return [$d, null];
}
function appt_save(array $me, array $in, ?array $old = null): array {
  [$d, $err] = appt_input($in, $me); if ($err) return ['error' => $err, 'data' => $d];
  if ($old === null) {
    $id = o_insert('appointments', $d + ['entered_by' => $me['id'], 'status' => 'scheduled', 'created_at' => now(), 'updated_at' => now()]);
    if ($d['matter_id']) matter_event((int)$d['matter_id'], $me, 'appt_add', null, ['appt' => $id, 'kind' => $d['kind'], 'at' => $d['starts_at']]);
    law_audit('appointment_added', 'appointment', $id, ['kind' => $d['kind'], 'matter' => $d['matter_id']], null, $me);
    return ['id' => $id];
  }
  $changed = []; foreach ($d as $f => $v) if ((string)$old[$f] !== (string)$v) $changed[] = $f;
  if (!$changed) return ['id' => (int)$old['id']];
  $clearReview = (bool)array_intersect($changed, ['starts_at', 'court_id', 'source', 'kind']);
  o_update('appointments', (int)$old['id'], $d + ($clearReview ? ['reviewed_by' => null, 'reviewed_at' => null] : []) + ['updated_at' => now()]);
  if ($d['matter_id']) matter_event((int)$d['matter_id'], $me, 'appt_change', null, ['appt' => (int)$old['id'], 'fields' => $changed, 'at' => $d['starts_at']]);
  law_audit('appointment_changed', 'appointment', $old['id'], ['fields' => $changed, 'review_cleared' => $clearReview && $old['reviewed_by']], null, $me);
  return ['id' => (int)$old['id']];
}
function appt_review(array $me, array $a): ?string {
  if ($a['reviewed_by']) return 'appt_already_reviewed';
  if ((int)$a['entered_by'] === (int)$me['id'] && !single_member_office()) return 'appt_review_other';
  o_update('appointments', (int)$a['id'], ['reviewed_by' => $me['id'], 'reviewed_at' => now(), 'updated_at' => now()]);
  if ($a['matter_id']) matter_event((int)$a['matter_id'], $me, 'appt_review', null, ['appt' => (int)$a['id']]);
  law_audit('appointment_reviewed', 'appointment', $a['id'], [], null, $me);
  return null;
}
/** Record what happened. Postponed with a new date creates the next appointment (to be reviewed). */
function appt_outcome(array $me, array $a, array $in): array {
  $status = in_array($in['status'] ?? '', ['done', 'postponed', 'cancelled'], true) ? $in['status'] : null;
  if (!$status) return ['error' => 'appt_outcome_status'];
  $outcome = mb_substr(trim((string)($in['outcome'] ?? '')), 0, 8000); $next = mb_substr(trim((string)($in['next_step'] ?? '')), 0, 300);
  if ($status !== 'cancelled' && $outcome === '') return ['error' => 'appt_outcome_required'];
  $newDate = valid_date($in['new_date'] ?? null);
  if ($status === 'postponed' && !$newDate && $a['kind'] === 'hearing') return ['error' => 'appt_new_date_required'];
  o_update('appointments', (int)$a['id'], ['status' => $status, 'outcome' => $outcome ?: null, 'next_step' => $next ?: null, 'outcome_by' => $me['id'], 'outcome_at' => now(), 'updated_at' => now()]);
  $newId = null;
  if ($newDate) {
    $time = preg_match('/^\d{2}:\d{2}$/', (string)($in['new_time'] ?? '')) ? $in['new_time'] : substr($a['starts_at'], 11, 5);
    $newId = o_insert('appointments', ['matter_id' => $a['matter_id'], 'kind' => $a['kind'], 'title' => $a['title'], 'starts_at' => "$newDate $time:00", 'court_id' => $a['court_id'],
      'circuit' => $a['circuit'], 'location' => $a['location'], 'lead_id' => $a['lead_id'], 'source' => 'in_session', 'source_note' => t('appt_from_previous', $a['id']),
      'entered_by' => $me['id'], 'remind_days' => $a['remind_days'], 'status' => 'scheduled', 'created_at' => now(), 'updated_at' => now()]);
  }
  if ($a['matter_id']) {
    matter_event((int)$a['matter_id'], $me, 'appt_outcome', $outcome ?: null, ['appt' => (int)$a['id'], 'status' => $status, 'next' => $next ?: null, 'new' => $newId]);
    if ($next !== '' && !empty($in['set_matter_next']) && ($m = matter_for((int)$a['matter_id'], $me))) matter_set_next($me, $m, $next, $newDate);
  }
  law_audit('appointment_outcome', 'appointment', $a['id'], ['status' => $status, 'next_appointment' => $newId], null, $me);
  return ['ok' => true, 'new' => $newId];
}
/** Appointments between two dates the person may see. $f: lead (id), mine (bool), kind. */
function appt_range(array $me, string $from, string $to, array $f = []): array {
  [$vis, $p] = linked_visible_sql($me);
  $w = ['x.office_id = :office', 'x.starts_at >= :from', 'x.starts_at < :to', $vis]; $p += ['from' => "$from 00:00:00", 'to' => "$to 00:00:00"];
  if (!empty($f['lead'])) { $w[] = 'x.lead_id = :lead'; $p['lead'] = (int)$f['lead']; }
  if (!empty($f['kind']) && in_array($f['kind'], APPT_KINDS, true)) { $w[] = 'x.kind = :kind'; $p['kind'] = $f['kind']; }
  if (empty($f['with_cancelled'])) $w[] = "x.status <> 'cancelled'";
  return orows('SELECT x.*, m.ref AS matter_ref, m.title AS matter_title FROM appointments x LEFT JOIN matters m ON m.id = x.matter_id AND m.office_id = x.office_id WHERE ' . implode(' AND ', $w) . ' ORDER BY x.starts_at', $p);
}
function appt_for_matter(int $matterId): array { return orows('SELECT * FROM appointments WHERE office_id = :office AND matter_id = :m ORDER BY starts_at DESC', ['m' => $matterId]); }

/* ---------- tasks ---------- */
function task_get_for(int $id, array $me): ?array {
  $t = orow('SELECT * FROM tasks WHERE office_id = :office AND id = :id', ['id' => $id]);
  if (!$t || !linked_ok($t['matter_id'] ? (int)$t['matter_id'] : null, $me)) return null;
  if (!can('tasks', 'view', $me) && (int)$t['assignee_id'] !== (int)$me['id']) return null;   // own tasks are always visible
  return $t;
}
function task_input(array $in, array $me): array {
  $s = fn($k, $max) => ($v = mb_substr(trim((string)($in[$k] ?? '')), 0, $max)) === '' ? null : $v;
  $d = ['title' => $s('title', 200), 'details' => $s('details', 8000), 'assignee_id' => (int)($in['assignee_id'] ?? 0) ?: null, 'reviewer_id' => (int)($in['reviewer_id'] ?? 0) ?: null,
        'due_on' => valid_date($in['due_on'] ?? null), 'priority' => in_array($in['priority'] ?? '', TASK_PRIORITIES, true) ? $in['priority'] : 'normal',
        'recur' => in_array($in['recur'] ?? '', TASK_RECUR, true) ? $in['recur'] : 'none', 'matter_id' => (int)($in['matter_id'] ?? 0) ?: null];
  if (!$d['title']) return [$d, 'task_title_required'];
  if (!staff_active_in_office($d['assignee_id'])) return [$d, 'task_assignee_required'];
  if ($d['reviewer_id'] && !staff_active_in_office($d['reviewer_id'])) return [$d, 'task_assignee_required'];
  if ($d['recur'] !== 'none' && !$d['due_on']) return [$d, 'task_recur_needs_due'];
  if ($d['matter_id'] && !matter_for($d['matter_id'], $me)) return [$d, 'appt_matter_bad'];
  return [$d, null];
}
function task_save(array $me, array $in, ?array $old = null): array {
  [$d, $err] = task_input($in, $me); if ($err) return ['error' => $err, 'data' => $d];
  if ($old === null) {
    $id = o_insert('tasks', $d + ['status' => 'open', 'created_by' => $me['id'], 'created_at' => now(), 'updated_at' => now()]);
    if ((int)$d['assignee_id'] !== (int)$me['id']) notify((int)$d['assignee_id'], 'task_assigned', 'n_task_assigned', [$d['title']], '/tasks/' . $id, "task-new:$id");
    if ($d['matter_id']) matter_event((int)$d['matter_id'], $me, 'task_add', null, ['task' => $id, 'title' => $d['title']]);
    law_audit('task_added', 'task', $id, ['matter' => $d['matter_id']], null, $me);
    return ['id' => $id];
  }
  $changed = []; foreach ($d as $f => $v) if ((string)$old[$f] !== (string)$v) $changed[] = $f;
  if ($changed) {
    o_update('tasks', (int)$old['id'], $d + ['updated_at' => now()] + (in_array('due_on', $changed, true) ? ['escalated_at' => null] : []));
    if (in_array('assignee_id', $changed, true) && (int)$d['assignee_id'] !== (int)$me['id']) notify((int)$d['assignee_id'], 'task_assigned', 'n_task_assigned', [$d['title']], '/tasks/' . $old['id'], "task-new:{$old['id']}:{$d['assignee_id']}");
    law_audit('task_changed', 'task', $old['id'], ['fields' => $changed], null, $me);
  }
  return ['id' => (int)$old['id']];
}
function task_complete(array $me, array $t, string $note): ?string {
  if (in_array($t['status'], ['done', 'cancelled'], true)) return 'task_closed';
  o_update('tasks', (int)$t['id'], ['status' => 'done', 'done_at' => now(), 'done_by' => $me['id'], 'done_note' => mb_substr(trim($note), 0, 4000) ?: null, 'updated_at' => now()]);
  if ($t['reviewer_id'] && (int)$t['reviewer_id'] !== (int)$me['id']) notify((int)$t['reviewer_id'], 'task_done', 'n_task_done', [$t['title']], '/tasks/' . $t['id'], "task-done:{$t['id']}");
  if ($t['recur'] !== 'none' && $t['due_on']) {
    $next = date('Y-m-d', strtotime($t['due_on'] . ($t['recur'] === 'weekly' ? ' +7 days' : ' +1 month')));
    o_insert('tasks', ['matter_id' => $t['matter_id'], 'title' => $t['title'], 'details' => $t['details'], 'assignee_id' => $t['assignee_id'], 'reviewer_id' => $t['reviewer_id'],
      'due_on' => $next, 'priority' => $t['priority'], 'recur' => $t['recur'], 'status' => 'open', 'created_by' => $me['id'], 'created_at' => now(), 'updated_at' => now()]);
  }
  if ($t['matter_id']) matter_event((int)$t['matter_id'], $me, 'task_done', null, ['task' => (int)$t['id'], 'title' => $t['title']]);
  law_audit('task_done', 'task', $t['id'], [], null, $me);
  return null;
}
function task_set_status(array $me, array $t, string $status): ?string {
  if (!in_array($status, ['open', 'in_progress', 'cancelled'], true)) return 'not_found';
  o_update('tasks', (int)$t['id'], ['status' => $status, 'done_at' => null, 'done_by' => null, 'updated_at' => now()]);
  law_audit('task_status', 'task', $t['id'], ['to' => $status], null, $me);
  return null;
}
/** $f: scope (mine|all), state (open|done|overdue), matter, page. */
function task_list(array $me, array $f, int $per = 50): array {
  [$vis, $p] = linked_visible_sql($me);
  $w = ['x.office_id = :office', $vis];
  if (($f['scope'] ?? 'mine') === 'mine' || !can('tasks', 'view', $me)) { $w[] = 'x.assignee_id = :me2'; $p['me2'] = (int)$me['id']; }
  $state = $f['state'] ?? 'open';
  if ($state === 'open') $w[] = "x.status IN ('open','in_progress')";
  elseif ($state === 'done') $w[] = "x.status IN ('done','cancelled')";
  elseif ($state === 'overdue') { $w[] = "x.status IN ('open','in_progress') AND x.due_on < :today"; $p['today'] = date('Y-m-d'); }
  if (!empty($f['matter'])) { $w[] = 'x.matter_id = :mt'; $p['mt'] = (int)$f['matter']; }
  $from = 'FROM tasks x LEFT JOIN matters m ON m.id = x.matter_id AND m.office_id = x.office_id WHERE ' . implode(' AND ', $w);
  $total = (int)orow("SELECT COUNT(*) n $from", $p)['n'];
  $page = max(1, (int)($f['page'] ?? 1));
  $rows = orows("SELECT x.*, m.ref AS matter_ref FROM tasks x LEFT JOIN matters m ON m.id = x.matter_id AND m.office_id = x.office_id WHERE " . implode(' AND ', $w)
    . " ORDER BY (x.due_on IS NULL), x.due_on, CASE x.priority WHEN 'high' THEN 0 WHEN 'normal' THEN 1 ELSE 2 END, x.id LIMIT $per OFFSET " . (($page - 1) * $per), $p);
  return [$rows, $total];
}

/* ---------- task templates ---------- */
/** Template text: one task per line, "title | days after start" (days optional). */
function tpl_parse(string $text): array {
  $out = [];
  foreach (preg_split('/\r?\n/', $text) as $line) {
    $parts = array_map('trim', explode('|', $line)); if (($parts[0] ?? '') === '') continue;
    $out[] = ['title' => mb_substr($parts[0], 0, 200), 'days' => isset($parts[1]) && is_numeric(digits_only_keep($parts[1])) ? max(0, min(365, (int)digits_only_keep($parts[1]))) : null];
  }
  return array_slice($out, 0, 40);
}
function tpl_save(array $me, ?int $id, string $name, string $text, bool $active = true): ?string {
  $name = mb_substr(trim($name), 0, 160); $items = tpl_parse($text);
  if ($name === '' || !$items) return 'tpl_required';
  if ($id) { if (!o_update('task_templates', $id, ['name' => $name, 'items' => json_encode($items, JSON_UNESCAPED_UNICODE), 'active' => $active ? 1 : 0])) return 'not_found'; }
  else $id = o_insert('task_templates', ['name' => $name, 'items' => json_encode($items, JSON_UNESCAPED_UNICODE), 'active' => 1, 'created_at' => now()]);
  law_audit('template_saved', 'task_template', $id, [], null, $me);
  return null;
}
function tpl_list(bool $all = false): array { return orows('SELECT * FROM task_templates WHERE office_id = :office' . ($all ? '' : ' AND active = 1') . ' ORDER BY name'); }
function tpl_apply(array $me, array $m, int $tplId, ?int $assignee): ?string {
  $tpl = orow('SELECT * FROM task_templates WHERE office_id = :office AND id = :id AND active = 1', ['id' => $tplId]); if (!$tpl) return 'not_found';
  $assignee = staff_active_in_office($assignee) ? $assignee : (int)$m['lead_id'];
  foreach (json_decode($tpl['items'], true) ?: [] as $it) task_save($me, ['title' => $it['title'], 'assignee_id' => $assignee, 'matter_id' => $m['id'], 'due_on' => $it['days'] !== null ? date('Y-m-d', strtotime('+' . (int)$it['days'] . ' days')) : null]);
  law_audit('template_applied', 'matter', $m['id'], ['template' => $tplId], null, $me);
  return null;
}

/* ---------- notifications ---------- */
/** In-app notification; written in the recipient's language. The same dedupe key is never sent twice. */
function notify(int $staffId, string $kind, string $key, array $args, ?string $link, string $dedupe): void {
  $s = orow('SELECT lang FROM staff WHERE office_id = :office AND id = :id', ['id' => $staffId]); if (!$s) return;
  $GLOBALS['LANG_FORCE'] = $s['lang'] ?: 'ar'; $title = mb_substr(t($key, ...$args), 0, 300); unset($GLOBALS['LANG_FORCE']);
  if (orow('SELECT id FROM notifications WHERE office_id = :office AND staff_id = :s AND dedupe = :d', ['s' => $staffId, 'd' => $dedupe])) return;
  try { o_insert('notifications', ['staff_id' => $staffId, 'kind' => $kind, 'title' => $title, 'link' => $link, 'dedupe' => mb_substr($dedupe, 0, 80), 'created_at' => now()]); }
  catch (PDOException $e) { /* created at the same moment by another run */ }
}
function notif_unread(array $me): int { return (int)orow('SELECT COUNT(*) n FROM notifications WHERE office_id = :office AND staff_id = :s AND read_at IS NULL', ['s' => $me['id']])['n']; }
function notif_list(array $me, int $limit = 100): array { return orows("SELECT * FROM notifications WHERE office_id = :office AND staff_id = :s ORDER BY id DESC LIMIT $limit", ['s' => $me['id']]); }
function notif_read_all(array $me): void { oq('UPDATE notifications SET read_at = :now WHERE office_id = :office AND staff_id = :s AND read_at IS NULL', ['now' => now(), 's' => $me['id']]); }

/** People to tell about an appointment: its lead and, for a case file, the file team. */
function appt_people(array $a): array {
  $ids = [(int)$a['lead_id']];
  if ($a['matter_id']) foreach (orows('SELECT staff_id FROM matter_team WHERE office_id = :office AND matter_id = :m', ['m' => $a['matter_id']]) as $r) $ids[] = (int)$r['staff_id'];
  $act = []; foreach (array_unique($ids) as $i) if (staff_active_in_office($i)) $act[] = $i;
  return $act;
}
function office_managers(): array { return array_map('intval', array_column(orows("SELECT id FROM staff WHERE office_id = :office AND status = 'active' AND role IN ('partner','office_manager')"), 'id')); }

/** Daily reminders for the office in scope. Returns counts. */
function reminders_run(?string $today = null): array {
  $today = $today ?? date('Y-m-d'); $n = ['notifications' => 0, 'emails' => 0];
  $before = (int)orow('SELECT COUNT(*) n FROM notifications WHERE office_id = :office')['n'];
  $horizon = date('Y-m-d', strtotime("$today +31 days"));
  foreach (orows("SELECT * FROM appointments WHERE office_id = :office AND status = 'scheduled' AND starts_at >= :a AND starts_at < :b", ['a' => "$today 00:00:00", 'b' => "$horizon 00:00:00"]) as $a) {
    $days = (int)round((strtotime(substr($a['starts_at'], 0, 10)) - strtotime($today)) / 86400);
    $when = date('d/m/Y H:i', strtotime($a['starts_at']));
    $want = array_map('intval', array_filter(explode(',', (string)$a['remind_days']), 'strlen')); $want[] = 0;
    if (in_array($days, $want, true)) foreach (appt_people($a) as $sid) notify($sid, 'appt_soon', $days === 0 ? 'n_appt_today' : 'n_appt_soon', [$a['title'], $when, $days], '/calendar/' . $a['id'], "appt:{$a['id']}:$days:" . substr($a['starts_at'], 0, 10));
    if ($days <= 7 && !$a['reviewed_by'] && in_array($a['kind'], ['hearing', 'deadline'], true)) foreach (array_unique(array_merge([(int)$a['lead_id']], office_managers())) as $sid)
      notify($sid, 'appt_unreviewed', 'n_appt_unreviewed', [$a['title'], $when], '/calendar/' . $a['id'], "appt-unrev:{$a['id']}:" . substr($a['starts_at'], 0, 10));
  }
  foreach (orows("SELECT * FROM appointments WHERE office_id = :office AND status = 'scheduled' AND starts_at < :t", ['t' => "$today 00:00:00"]) as $a)
    if (staff_active_in_office((int)$a['lead_id'])) notify((int)$a['lead_id'], 'appt_outcome', 'n_appt_outcome', [$a['title'], date('d/m/Y', strtotime($a['starts_at']))], '/calendar/' . $a['id'], "appt-out:{$a['id']}");
  $tomorrow = date('Y-m-d', strtotime("$today +1 day"));
  foreach (orows("SELECT * FROM tasks WHERE office_id = :office AND status IN ('open','in_progress') AND due_on IS NOT NULL AND due_on <= :tm", ['tm' => $tomorrow]) as $t) {
    if (!staff_active_in_office((int)$t['assignee_id'])) continue;
    if ($t['due_on'] >= $today) notify((int)$t['assignee_id'], 'task_due', 'n_task_due', [$t['title'], date('d/m/Y', strtotime($t['due_on']))], '/tasks/' . $t['id'], "task-due:{$t['id']}:{$t['due_on']}");
    else notify((int)$t['assignee_id'], 'task_overdue', 'n_task_overdue', [$t['title'], date('d/m/Y', strtotime($t['due_on']))], '/tasks/' . $t['id'], "task-over:{$t['id']}:{$t['due_on']}");
  }
  $esc = row('SELECT escalate_days FROM offices WHERE id=?', [office_id()])['escalate_days'] ?? null;   // read fresh: a long-running job may have the office cached
  if ($esc !== null && (int)$esc > 0) {
    $limit = date('Y-m-d', strtotime("$today -" . (int)$esc . ' days'));
    foreach (orows("SELECT * FROM tasks WHERE office_id = :office AND status IN ('open','in_progress') AND due_on IS NOT NULL AND due_on <= :l AND escalated_at IS NULL", ['l' => $limit]) as $t) {
      foreach (office_managers() as $sid) if ($sid !== (int)$t['assignee_id']) notify($sid, 'task_escalated', 'n_task_escalated', [$t['title'], staff_name((int)$t['assignee_id'])], '/tasks/' . $t['id'], "task-esc:{$t['id']}");
      o_update('tasks', (int)$t['id'], ['escalated_at' => now()]);
    }
  }
  $n['notifications'] = (int)orow('SELECT COUNT(*) n FROM notifications WHERE office_id = :office')['n'] - $before;
  // one short email per person, with no details
  foreach (orows("SELECT staff_id, COUNT(*) c FROM notifications WHERE office_id = :office AND emailed_at IS NULL AND read_at IS NULL GROUP BY staff_id") as $r) {
    $s = orow("SELECT * FROM staff WHERE office_id = :office AND id = :id AND status = 'active'", ['id' => $r['staff_id']]); if (!$s) continue;
    $GLOBALS['LANG_FORCE'] = $s['lang'] ?: 'ar';
    $ok = send_mail($s['email'], t('mail_reminders_subject'), t('mail_reminders_body', $s['full_name'], (int)$r['c'], abs_url('/notifications')));
    unset($GLOBALS['LANG_FORCE']);
    if ($ok) { oq('UPDATE notifications SET emailed_at = :now WHERE office_id = :office AND staff_id = :s AND emailed_at IS NULL', ['now' => now(), 's' => $s['id']]); $n['emails']++; }
  }
  return $n;
}
/** Run for every office that can sign in. */
function reminders_all(?string $today = null): array {
  $out = []; $prev = $GLOBALS['OFFICE_SCOPE'] ?? null;
  foreach (rows("SELECT id, code FROM offices WHERE status <> 'suspended'") as $o) { set_office_scope((int)$o['id']); $out[$o['code']] = reminders_run($today) + ['notices' => function_exists('notices_auto') ? notices_auto($today) : 0]; }
  set_office_scope($prev);
  return $out;
}
