<?php
/* ==========================================================================
   Dashboard data: "today in the office" and "needs action".
   Every list respects what the person may see (restricted files, own tasks,
   money only with the finance permission). Scope "mine" narrows to files the
   person leads, tasks assigned to them and appointments they lead.
   ========================================================================== */
declare(strict_types=1);

function dash_scope(array $me, ?string $want): string {
  $office = can('reports', 'view', $me) || can('tasks', 'view', $me);
  return $want === 'office' && $office ? 'office' : ($want === 'mine' || !$office ? 'mine' : (in_array($me['role'], ['partner', 'office_manager'], true) ? 'office' : 'mine'));
}

/** Today's appointments (and the very next one if today is empty). */
function dash_today(array $me, string $scope): array {
  if (!can('calendar', 'view', $me)) return ['today' => [], 'next' => null];
  $f = $scope === 'mine' ? ['lead' => $me['id']] : [];
  $today = appt_range($me, date('Y-m-d'), date('Y-m-d', strtotime('+1 day')), $f);
  $next = null;
  if (!$today) { $n = appt_range($me, date('Y-m-d', strtotime('+1 day')), date('Y-m-d', strtotime('+31 days')), $f); $next = $n[0] ?? null; }
  return ['today' => $today, 'next' => $next];
}

/** Groups of items that need someone to act. Each: [key, count, items[], link]. */
function needs_action(array $me, string $scope): array {
  $g = []; $today = date('Y-m-d'); $mine = $scope === 'mine';
  if (can('calendar', 'view', $me)) {
    [$vis, $p] = linked_visible_sql($me);
    $w = "x.office_id = :office AND $vis AND x.status = 'scheduled' AND x.starts_at < :t AND x.kind IN ('hearing','deadline')" . ($mine ? ' AND x.lead_id = :me' : '');
    $rows = orows("SELECT x.*, m.ref AS matter_ref, m.title AS matter_title FROM appointments x LEFT JOIN matters m ON m.id = x.matter_id AND m.office_id = x.office_id WHERE $w ORDER BY x.starts_at DESC LIMIT 6", $p + ['t' => "$today 00:00:00"] + ($mine ? ['me' => $me['id']] : []));
    $n = (int)orow("SELECT COUNT(*) n FROM appointments x LEFT JOIN matters m ON m.id = x.matter_id AND m.office_id = x.office_id WHERE $w", $p + ['t' => "$today 00:00:00"] + ($mine ? ['me' => $me['id']] : []))['n'];
    $g[] = ['key' => 'na_no_outcome', 'n' => $n, 'items' => array_map(fn($a) => ['href' => '/calendar/' . $a['id'], 'title' => $a['title'], 'ref' => $a['matter_ref'], 'when' => $a['starts_at'], 'who' => (int)$a['lead_id'], 'late' => true], $rows), 'link' => '/followup'];
    $w2 = "x.office_id = :office AND $vis AND x.status = 'scheduled' AND x.reviewed_by IS NULL AND x.kind IN ('hearing','deadline') AND x.starts_at >= :t" . ($mine ? ' AND x.lead_id = :me' : '');
    $n2 = (int)orow("SELECT COUNT(*) n FROM appointments x LEFT JOIN matters m ON m.id = x.matter_id AND m.office_id = x.office_id WHERE $w2", $p + ['t' => "$today 00:00:00"] + ($mine ? ['me' => $me['id']] : []))['n'];
    $r2 = orows("SELECT x.*, m.ref AS matter_ref FROM appointments x LEFT JOIN matters m ON m.id = x.matter_id AND m.office_id = x.office_id WHERE $w2 ORDER BY x.starts_at LIMIT 4", $p + ['t' => "$today 00:00:00"] + ($mine ? ['me' => $me['id']] : []));
    $g[] = ['key' => 'na_unreviewed', 'n' => $n2, 'items' => array_map(fn($a) => ['href' => '/calendar/' . $a['id'], 'title' => $a['title'], 'ref' => $a['matter_ref'], 'when' => $a['starts_at'], 'who' => (int)$a['lead_id'], 'late' => false], $r2), 'link' => '/calendar?view=list'];
  }
  $dl = orows("SELECT d.*, m.ref, m.title FROM delegations d JOIN matters m ON m.id = d.matter_id AND m.office_id = d.office_id WHERE d.office_id = :office AND d.to_staff = :me AND d.status IN ('pending','accepted') ORDER BY d.id DESC LIMIT 6", ['me' => $me['id']]);
  $g[] = ['key' => 'na_delegations', 'n' => count($dl), 'items' => array_map(fn($d) => ['href' => '/delegations/' . $d['id'], 'title' => $d['title'], 'ref' => $d['ref'], 'when' => null, 'who' => (int)$d['from_staff'], 'late' => false, 'tag' => 'dst_' . $d['status']], $dl), 'link' => '/delegations?box=to_me'];
  [$tasks] = task_list($me, ['scope' => $mine ? 'mine' : 'all', 'state' => 'overdue'], 6);
  [, $tn] = task_list($me, ['scope' => $mine ? 'mine' : 'all', 'state' => 'overdue'], 1);
  $g[] = ['key' => 'na_tasks', 'n' => (int)$tn, 'items' => array_map(fn($x) => ['href' => '/tasks/' . $x['id'], 'title' => $x['title'], 'ref' => $x['matter_ref'], 'when' => $x['due_on'], 'who' => (int)$x['assignee_id'], 'late' => true], $tasks), 'link' => '/tasks?scope=' . ($mine ? 'mine' : 'all') . '&state=overdue'];
  if (can('matters', 'view', $me)) {
    [$vis, $p] = matter_visible_sql($me);
    $w = "m.office_id = :office AND $vis AND m.closed_at IS NULL AND m.next_due IS NOT NULL AND m.next_due < :t" . ($mine ? ' AND m.lead_id = :me' : '');
    $pp = $p + ['t' => $today] + ($mine ? ['me' => $me['id']] : []);
    $rows = orows("SELECT m.* FROM matters m WHERE $w ORDER BY m.next_due LIMIT 6", $pp);
    $g[] = ['key' => 'na_next_late', 'n' => (int)orow("SELECT COUNT(*) n FROM matters m WHERE $w", $pp)['n'], 'items' => array_map(fn($m) => ['href' => '/matters/' . $m['id'], 'title' => $m['next_step'] ?: $m['title'], 'ref' => $m['ref'], 'when' => $m['next_due'], 'who' => (int)$m['lead_id'], 'late' => true], $rows), 'link' => '/matters?sort=next'];
    $rd = report_data($me, report_filters([]) + ($mine ? [] : []));
    $stale = $mine ? array_values(array_filter($rd['stale'], fn($r) => (int)$r['lead_id'] === (int)$me['id'])) : $rd['stale'];
    $g[] = ['key' => 'na_stale', 'n' => count($stale), 'items' => array_map(fn($r) => ['href' => '/matters/' . $r['id'], 'title' => $r['title'], 'ref' => $r['ref'], 'when' => $r['last_at'], 'who' => (int)$r['lead_id'], 'late' => false], array_slice($stale, 0, 6)), 'link' => '/reports#stale'];
  }
  if (can('finance', 'view', $me)) {
    [$vis, $p] = linked_visible_sql($me);
    $w = "x.office_id = :office AND $vis AND x.status = 'issued' AND x.paid < x.total AND x.due_on IS NOT NULL AND x.due_on <= :t";
    $rows = orows("SELECT x.*, m.ref FROM invoices x JOIN matters m ON m.id = x.matter_id AND m.office_id = x.office_id WHERE $w ORDER BY x.due_on LIMIT 6", $p + ['t' => $today]);
    $sum = (int)orow("SELECT COALESCE(SUM(x.total - x.paid),0) s, COUNT(*) n FROM invoices x JOIN matters m ON m.id = x.matter_id AND m.office_id = x.office_id WHERE $w", $p + ['t' => $today])['s'];
    $g[] = ['key' => 'na_due', 'n' => count($rows), 'sum' => $sum, 'items' => array_map(fn($i) => ['href' => '/finance/invoices/' . $i['id'], 'title' => ($i['number'] ?? '') . ' · ' . money((int)$i['total'] - (int)$i['paid']), 'ref' => $i['ref'], 'when' => $i['due_on'], 'who' => null, 'late' => $i['due_on'] < $today], $rows), 'link' => '/finance/invoices?state=overdue'];
  }
  return array_values(array_filter($g, fn($x) => $x['n'] > 0));
}

/** "Unfinished work": past hearings and deadlines whose outcome is not recorded (hearings not carried forward). f: lead, from. */
function followup_list(array $me, array $f): array {
  [$vis, $p] = linked_visible_sql($me);
  $w = ["x.office_id = :office", $vis, "x.status = 'scheduled'", "x.kind IN ('hearing','deadline')", 'x.starts_at < :t'];
  $p['t'] = date('Y-m-d') . ' 00:00:00';
  if (!empty($f['lead'])) { $w[] = 'x.lead_id = :lead'; $p['lead'] = (int)$f['lead']; }
  if (!empty($f['from'])) { $w[] = 'x.starts_at >= :fr'; $p['fr'] = $f['from'] . ' 00:00:00'; }
  return orows('SELECT x.id, x.kind, x.title, x.starts_at, x.court_id, x.circuit, x.lead_id, m.id AS matter_id, m.ref AS matter_ref, m.title AS matter_title, m.official_no, c.name_ar, c.name_en
    FROM appointments x LEFT JOIN matters m ON m.id = x.matter_id AND m.office_id = x.office_id LEFT JOIN contacts c ON c.id = m.client_id AND c.office_id = x.office_id
    WHERE ' . implode(' AND ', $w) . ' ORDER BY x.starts_at', $p);
}
