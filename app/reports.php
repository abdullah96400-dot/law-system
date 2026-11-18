<?php
/* ==========================================================================
   Office reports. Every figure has a written definition next to it and only
   counts case files the person may see (restricted files stay out for
   people outside their team). Money figures need the finance permission.
   Filters: period, branch, responsible lawyer, case type.
   ========================================================================== */
declare(strict_types=1);

function report_filters(array $g): array {
  $from = valid_date($g['from'] ?? null) ?? date('Y-m-01'); $to = valid_date($g['to'] ?? null) ?? date('Y-m-d', strtotime('+1 day'));
  return ['from' => $from, 'to' => $to, 'branch' => (int)($g['branch'] ?? 0), 'lead' => (int)($g['lead'] ?? 0), 'type' => (int)($g['type'] ?? 0)];
}
/** WHERE fragment on alias m for visibility + filters. */
function report_scope(array $me, array $f): array {
  [$vis, $p] = matter_visible_sql($me);
  $w = ['m.office_id = :office', $vis];
  if ($f['branch']) { $w[] = 'm.branch_id = :br'; $p['br'] = $f['branch']; }
  if ($f['lead']) { $w[] = 'm.lead_id = :ld'; $p['ld'] = $f['lead']; }
  if ($f['type']) { $w[] = 'm.case_type_id = :ty'; $p['ty'] = $f['type']; }
  return [implode(' AND ', $w), $p];
}
function report_data(array $me, array $f): array {
  [$S, $p] = report_scope($me, $f); $pp = $p + ['f' => $f['from'] . ' 00:00:00', 't' => $f['to'] . ' 00:00:00'];
  $d = [];
  $d['opened'] = (int)orow("SELECT COUNT(*) n FROM matters m WHERE $S AND m.created_at >= :f AND m.created_at < :t", $pp)['n'];
  $d['closed'] = (int)orow("SELECT COUNT(*) n FROM matters m WHERE $S AND m.closed_at >= :f AND m.closed_at < :t", $pp)['n'];
  $d['open_now'] = (int)orow("SELECT COUNT(*) n FROM matters m WHERE $S AND m.closed_at IS NULL", $p)['n'];
  $d['by_status'] = orows("SELECT m.status_id, COUNT(*) n FROM matters m WHERE $S AND m.closed_at IS NULL GROUP BY m.status_id ORDER BY n DESC", $p);
  $d['stale'] = orows("SELECT m.id, m.ref, m.title, m.lead_id, (SELECT MAX(e.at) FROM matter_events e WHERE e.office_id = m.office_id AND e.matter_id = m.id) last_at FROM matters m
    WHERE $S AND m.closed_at IS NULL AND NOT EXISTS (SELECT 1 FROM matter_events e WHERE e.office_id = m.office_id AND e.matter_id = m.id AND e.at >= :cut) ORDER BY last_at LIMIT 50", $p + ['cut' => date('Y-m-d H:i:s', strtotime('-30 days'))]);
  $d['hearings_no_outcome'] = (int)orow("SELECT COUNT(*) n FROM appointments a JOIN matters m ON m.id = a.matter_id AND m.office_id = a.office_id WHERE $S AND a.status = 'scheduled' AND a.starts_at < :now", $p + ['now' => date('Y-m-d') . ' 00:00:00'])['n'];
  $d['tasks_overdue'] = (int)orow("SELECT COUNT(*) n FROM tasks k LEFT JOIN matters m ON m.id = k.matter_id AND m.office_id = k.office_id WHERE k.office_id = :office AND k.status IN ('open','in_progress') AND k.due_on < :today AND (k.matter_id IS NULL OR ($S))", $p + ['today' => date('Y-m-d')])['n'];
  $d['workload'] = orows("SELECT s.id, s.full_name,
      (SELECT COUNT(*) FROM matters m WHERE $S AND m.lead_id = s.id AND m.closed_at IS NULL) files,
      (SELECT COUNT(*) FROM tasks k WHERE k.office_id = s.office_id AND k.assignee_id = s.id AND k.status IN ('open','in_progress')) tasks,
      (SELECT COUNT(*) FROM appointments a WHERE a.office_id = s.office_id AND a.lead_id = s.id AND a.status = 'scheduled' AND a.starts_at >= :now2 AND a.starts_at < :in30) hearings
    FROM staff s WHERE s.office_id = :office AND s.status = 'active' ORDER BY files DESC, s.full_name", $p + ['now2' => date('Y-m-d H:i:s'), 'in30' => date('Y-m-d', strtotime('+30 days'))]);
  if (can('finance', 'view', $me)) {
    $d['money'] = orow("SELECT COALESCE(SUM(CASE WHEN i.issued_on >= :fd AND i.issued_on < :td THEN i.total ELSE 0 END),0) invoiced,
        COALESCE(SUM(CASE WHEN i.due_on < :today THEN i.total - i.paid ELSE 0 END),0) overdue, COALESCE(SUM(i.total - i.paid),0) outstanding
      FROM invoices i JOIN matters m ON m.id = i.matter_id AND m.office_id = i.office_id WHERE $S AND i.status = 'issued'", $p + ['fd' => $f['from'], 'td' => $f['to'], 'today' => date('Y-m-d')]);
    $d['money']['received'] = (int)orow("SELECT COALESCE(SUM(y.amount),0) s FROM payments y JOIN invoices i ON i.id = y.invoice_id AND i.office_id = y.office_id JOIN matters m ON m.id = i.matter_id AND m.office_id = i.office_id WHERE $S AND y.voided_at IS NULL AND y.received_on >= :fd AND y.received_on < :td", $p + ['fd' => $f['from'], 'td' => $f['to']])['s'];
    $d['by_file'] = orows("SELECT m.id, m.ref, m.title,
        (SELECT COALESCE(SUM(i.total),0) FROM invoices i WHERE i.office_id = m.office_id AND i.matter_id = m.id AND i.status = 'issued' AND i.issued_on >= :fd AND i.issued_on < :td) invoiced,
        (SELECT COALESCE(SUM(y.amount),0) FROM payments y JOIN invoices i ON i.id = y.invoice_id AND i.office_id = y.office_id WHERE i.office_id = m.office_id AND i.matter_id = m.id AND y.voided_at IS NULL AND y.received_on >= :fd AND y.received_on < :td) received,
        (SELECT COALESCE(SUM(x.amount),0) FROM expenses x WHERE x.office_id = m.office_id AND x.matter_id = m.id AND x.spent_on >= :fd AND x.spent_on < :td) expenses
      FROM matters m WHERE $S ORDER BY invoiced DESC, received DESC LIMIT 30", $p + ['fd' => $f['from'], 'td' => $f['to']]);
    $d['by_file'] = array_values(array_filter($d['by_file'], fn($r) => $r['invoiced'] || $r['received'] || $r['expenses']));
  }
  $d['portal'] = [
    'accounts' => (int)orow("SELECT COUNT(*) n FROM portal_users WHERE office_id = :office AND status = 'active'")['n'],
    'logins' => (int)orow("SELECT COUNT(*) n FROM audit_log WHERE office_id = :office AND action = 'portal_login' AND at >= :f AND at < :t", ['f' => $pp['f'], 't' => $pp['t']])['n'],
    'messages' => (int)orow("SELECT COUNT(*) n FROM portal_messages WHERE office_id = :office AND portal_user_id IS NOT NULL AND created_at >= :f AND created_at < :t", ['f' => $pp['f'], 't' => $pp['t']])['n'],
    'uploads' => (int)orow("SELECT COUNT(*) n FROM documents WHERE office_id = :office AND source = 'client' AND created_at >= :f AND created_at < :t", ['f' => $pp['f'], 't' => $pp['t']])['n'],
  ];
  return $d;
}
function report_sheets(array $d): array {
  $s = [];
  $s[t('rp_summary')] = [[t('rp_metric'), t('rp_value'), t('rp_definition')],
    [t('rp_opened'), $d['opened'], t('rp_opened_def')], [t('rp_closed'), $d['closed'], t('rp_closed_def')], [t('rp_open_now'), $d['open_now'], t('rp_open_now_def')],
    [t('rp_hearings_no_outcome'), $d['hearings_no_outcome'], t('rp_hearings_no_outcome_def')], [t('rp_tasks_overdue'), $d['tasks_overdue'], t('rp_tasks_overdue_def')],
    [t('rp_portal_accounts'), $d['portal']['accounts'], ''], [t('rp_portal_logins'), $d['portal']['logins'], ''], [t('rp_portal_messages'), $d['portal']['messages'], ''], [t('rp_portal_uploads'), $d['portal']['uploads'], '']];
  if (isset($d['money'])) foreach (['invoiced', 'received', 'outstanding', 'overdue'] as $k) $s[t('rp_summary')][] = [t('kpi_' . $k) . ' (OMR)', (int)$d['money'][$k] / 1000, t('kpi_' . $k . '_def', '')];
  $s[t('rp_by_status')] = array_merge([[t('status'), t('rp_files')]], array_map(fn($r) => [lookup_name((int)$r['status_id'], 'status'), (int)$r['n']], $d['by_status']));
  $s[t('rp_workload')] = array_merge([[t('name'), t('rp_files_lead'), t('rp_open_tasks'), t('rp_hearings_30')]], array_map(fn($r) => [$r['full_name'], (int)$r['files'], (int)$r['tasks'], (int)$r['hearings']], $d['workload']));
  $s[t('rp_stale')] = array_merge([[t('ref'), t('matter_title'), t('matter_lead'), t('rp_last_entry')]], array_map(fn($r) => [$r['ref'], $r['title'], staff_name((int)$r['lead_id']), $r['last_at']], $d['stale']));
  if (isset($d['by_file'])) $s[t('rp_by_file')] = array_merge([[t('ref'), t('matter_title'), t('kpi_invoiced') . ' (OMR)', t('kpi_received') . ' (OMR)', t('kpi_expenses') . ' (OMR)']], array_map(fn($r) => [$r['ref'], $r['title'], $r['invoiced'] / 1000, $r['received'] / 1000, $r['expenses'] / 1000], $d['by_file']));
  return $s;
}

/* ---------- branches ---------- */
function branches(bool $all = false): array { return orows('SELECT * FROM branches WHERE office_id = :office' . ($all ? '' : ' AND active = 1') . ' ORDER BY name'); }
function branch_save(array $me, ?int $id, string $name, bool $active = true): ?string {
  $name = mb_substr(trim($name), 0, 160); if ($name === '') return 'lookup_name_required';
  if ($id) { if (!o_update('branches', $id, ['name' => $name, 'active' => $active ? 1 : 0])) return 'not_found'; }
  else $id = o_insert('branches', ['name' => $name, 'active' => 1, 'created_at' => now()]);
  law_audit('branch_saved', 'branch', $id, [], null, $me);
  return null;
}
