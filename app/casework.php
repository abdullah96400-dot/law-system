<?php
/* ==========================================================================
   Case-file work: procedures and judgments, delegations between lawyers,
   the office template library.
   - A procedure or judgment is entered by the office with where it came
     from. Another person checks it before it can be shared with the client.
     Nothing here calculates an appeal or filing deadline: a deadline is an
     appointment of kind "deadline" entered with its own source.
   - A delegation asks a colleague to attend a hearing or do a step. Only the
     person asked can accept, decline or record the result; only the person
     who asked (or the managing partner) can send, reassign or cancel it.
   - Templates are the office's own texts with {{fields}} filled from the
     case file. The filled text is shown for review and saved as a PDF
     document; it is not shared with the client until someone shares it.
   ========================================================================== */
declare(strict_types=1);

const ACTION_KINDS = ['procedure', 'judgment', 'appeal', 'execution', 'memo'];
const DELEG_STATUSES = ['draft', 'pending', 'accepted', 'rejected', 'done', 'cancelled'];
const TEMPLATE_KINDS = ['memo', 'letter', 'delegation', 'fee_agreement', 'other'];
const TEMPLATE_FIELDS = ['office_name', 'today', 'client_name', 'matter_ref', 'matter_title', 'case_type', 'court', 'circuit', 'official_no', 'lead_name',
  'opponents', 'next_hearing', 'hearing_date', 'hearing_time', 'hearing_title', 'delegate_name', 'requested_by', 'instructions'];

/* ---------- procedures and judgments ---------- */
function action_get(int $id, array $me): ?array {
  $a = orow('SELECT * FROM matter_actions WHERE office_id = :office AND id = :id', ['id' => $id]);
  return $a && matter_for((int)$a['matter_id'], $me) ? $a : null;
}
function actions_for(int $matterId): array { return orows('SELECT * FROM matter_actions WHERE office_id = :office AND matter_id = :m ORDER BY action_on DESC, id DESC', ['m' => $matterId]); }

function action_save(array $me, array $m, array $in, ?array $old = null): array {
  $kind = in_array($in['kind'] ?? '', ACTION_KINDS, true) ? $in['kind'] : null;
  $title = mb_substr(trim((string)($in['title'] ?? '')), 0, 200); $on = valid_date($in['action_on'] ?? null);
  $src = in_array($in['source'] ?? '', APPT_SOURCES, true) ? $in['source'] : null;
  $d = ['kind' => $kind, 'title' => $title, 'details' => mb_substr(trim((string)($in['details'] ?? '')), 0, 8000) ?: null, 'action_on' => $on,
        'court_id' => (int)($in['court_id'] ?? 0) ?: null, 'source' => $src, 'source_note' => mb_substr(trim((string)($in['source_note'] ?? '')), 0, 300) ?: null];
  if (!$kind) return ['error' => 'act_kind_required', 'data' => $d];
  if ($title === '') return ['error' => 'act_title_required', 'data' => $d];
  if (!$on) return ['error' => 'act_date_required', 'data' => $d];
  if (!$src) return ['error' => 'appt_source_required', 'data' => $d];
  if ($d['court_id'] && !lookup_get($d['court_id'], 'court')) return ['error' => 'matter_bad_list_item', 'data' => $d];
  if (!$old) {
    $id = o_insert('matter_actions', $d + ['matter_id' => $m['id'], 'portal_visible' => 0, 'created_by' => $me['id'], 'created_at' => now(), 'updated_at' => now()]);
    matter_event((int)$m['id'], $me, 'action_add', null, ['action' => $id, 'kind' => $kind, 'title' => $title]);
    law_audit('action_added', 'matter_action', $id, ['matter' => $m['id'], 'kind' => $kind], null, $me);
    return ['id' => $id];
  }
  $changed = []; foreach ($d as $f => $v) if ((string)$old[$f] !== (string)$v) $changed[] = $f;
  if (!$changed) return ['id' => (int)$old['id']];
  // what the date, kind or source says changed: the check and the sharing start again
  $reset = array_intersect($changed, ['kind', 'title', 'details', 'action_on', 'court_id', 'source']) ? ['verified_by' => null, 'verified_at' => null, 'portal_visible' => 0] : [];
  o_update('matter_actions', (int)$old['id'], $d + $reset + ['updated_at' => now()]);
  if ($reset && $old['portal_visible']) o_insert('portal_shares', ['matter_id' => $m['id'], 'kind' => 'action', 'item_id' => $old['id'], 'shared' => 0, 'staff_id' => $me['id'], 'at' => now()]);
  matter_event((int)$m['id'], $me, 'action_change', null, ['action' => (int)$old['id'], 'title' => $title, 'fields' => $changed]);
  law_audit('action_changed', 'matter_action', $old['id'], ['fields' => $changed], null, $me);
  return ['id' => (int)$old['id']];
}
function action_verify(array $me, array $a): ?string {
  if ($a['verified_by']) return null;
  if ((int)$a['created_by'] === (int)$me['id'] && !single_member_office()) return 'act_verify_other';
  o_update('matter_actions', (int)$a['id'], ['verified_by' => $me['id'], 'verified_at' => now(), 'updated_at' => now()]);
  matter_event((int)$a['matter_id'], $me, 'action_verify', null, ['action' => (int)$a['id'], 'title' => $a['title']]);
  law_audit('action_verified', 'matter_action', $a['id'], [], null, $me);
  return null;
}
/** Share or stop sharing with the client portal. Only checked items; the person must be allowed to publish updates for this file. */
function action_share(array $me, array $m, array $a, bool $on): ?string {
  if ($on && !$a['verified_by']) return 'act_share_unverified';
  if (!can_approve_update($me, $m)) return 'act_share_forbidden';
  if ((bool)$a['portal_visible'] === $on) return null;
  o_update('matter_actions', (int)$a['id'], ['portal_visible' => $on ? 1 : 0, 'updated_at' => now()]);
  share_mark($me, (int)$m['id'], 'action', (int)$a['id'], $on);
  matter_event((int)$m['id'], $me, 'item_share', null, ['kind' => 'action', 'title' => $a['title'], 'on' => $on]);
  law_audit($on ? 'action_shared' : 'action_unshared', 'matter_action', $a['id'], [], null, $me);
  if ($on) portal_notify_matter($m);
  return null;
}
/** Record a change of what the client can see (any kind of item). */
function share_mark(array $me, int $matterId, string $kind, int $itemId, bool $on): void {
  o_insert('portal_shares', ['matter_id' => $matterId, 'kind' => $kind, 'item_id' => $itemId, 'shared' => $on ? 1 : 0, 'staff_id' => $me['id'] ?? null, 'at' => now()]);
}
/** Who shared what with the client, newest first. */
function share_log(int $matterId, int $limit = 50): array {
  return orows("SELECT * FROM portal_shares WHERE office_id = :office AND matter_id = :m ORDER BY id DESC LIMIT $limit", ['m' => $matterId]);
}

/* ---------- delegations ---------- */
function deleg_get(int $id, array $me): ?array {
  $d = orow('SELECT * FROM delegations WHERE office_id = :office AND id = :id', ['id' => $id]);
  if (!$d) return null;
  $mine = in_array((int)$me['id'], [(int)$d['from_staff'], (int)$d['to_staff'], (int)$d['created_by']], true);
  return ($mine || can('calendar', 'view', $me)) && matter_for((int)$d['matter_id'], $me) ? $d : null;
}
function deleg_for_matter(int $matterId): array { return orows('SELECT * FROM delegations WHERE office_id = :office AND matter_id = :m ORDER BY id DESC', ['m' => $matterId]); }
function deleg_for_appt(int $apptId): array { return orows("SELECT * FROM delegations WHERE office_id = :office AND appointment_id = :a AND status <> 'cancelled' ORDER BY id DESC", ['a' => $apptId]); }
/** Asker side: the person who asked, whoever created it, or the managing partner. */
function deleg_is_asker(array $d, array $me): bool { return $me['role'] === 'partner' || in_array((int)$me['id'], [(int)$d['from_staff'], (int)$d['created_by']], true); }
function deleg_is_delegate(array $d, array $me): bool { return (int)$d['to_staff'] === (int)$me['id']; }

/** New delegation from a case file (optionally for one of its appointments). $send: send now or keep as a draft. */
function deleg_create(array $me, array $m, array $in): array {
  $to = (int)($in['to_staff'] ?? 0); $appt = (int)($in['appointment_id'] ?? 0) ?: null;
  $instr = mb_substr(trim((string)($in['instructions'] ?? '')), 0, 4000);
  $d = ['to_staff' => $to, 'appointment_id' => $appt, 'instructions' => $instr];
  if (!staff_active_in_office($to)) return ['error' => 'deleg_to_required', 'data' => $d];
  if ($to === (int)$me['id']) return ['error' => 'deleg_not_self', 'data' => $d];
  if ($appt) {
    $a = orow('SELECT id, matter_id, status FROM appointments WHERE office_id = :office AND id = :id', ['id' => $appt]);
    if (!$a || (int)$a['matter_id'] !== (int)$m['id']) return ['error' => 'deleg_appt_bad', 'data' => $d];
    if ($a['status'] !== 'scheduled') return ['error' => 'deleg_appt_closed', 'data' => $d];
  }
  if ($instr === '') return ['error' => 'deleg_instr_required', 'data' => $d];
  if (!deleg_can_open($to, $m)) return ['error' => 'deleg_no_access', 'data' => $d];
  $id = o_insert('delegations', $d + ['matter_id' => $m['id'], 'from_staff' => $me['id'], 'status' => 'draft', 'created_by' => $me['id'], 'created_at' => now(), 'updated_at' => now()]);
  law_audit('delegation_created', 'delegation', $id, ['matter' => $m['id'], 'to' => $to], null, $me);
  if (!empty($in['send'])) { $dl = orow('SELECT * FROM delegations WHERE office_id = :office AND id = :id', ['id' => $id]); deleg_send($me, $dl); }
  return ['id' => $id];
}
/** A restricted file is opened only by its team: the delegate must already be on it. */
function deleg_can_open(int $staffId, array $m): bool {
  $s = orow("SELECT * FROM staff WHERE office_id = :office AND id = :id AND status = 'active'", ['id' => $staffId]);
  return $s && can('matters', 'view', $s) && matter_access($m, $s);
}
function deleg_set(array $me, array $d, string $status, array $extra, string $event, ?string $body = null): void {
  o_update('delegations', (int)$d['id'], ['status' => $status, 'updated_at' => now()] + $extra);
  matter_event((int)$d['matter_id'], $me, 'deleg_' . $event, $body, ['deleg' => (int)$d['id'], 'to' => (int)($extra['to_staff'] ?? $d['to_staff']), 'status' => $status]);
  law_audit('delegation_' . $event, 'delegation', $d['id'], ['status' => $status] + (isset($extra['to_staff']) ? ['to' => $extra['to_staff']] : []), null, $me);
}
function deleg_send(array $me, array $d): ?string {
  if (!deleg_is_asker($d, $me)) return 'forbidden';
  if ($d['status'] !== 'draft') return 'deleg_bad_state';
  deleg_set($me, $d, 'pending', [], 'sent');
  notify((int)$d['to_staff'], 'delegation', 'n_deleg_request', [staff_name((int)$d['from_staff'])], '/delegations/' . $d['id'], 'deleg:' . $d['id'] . ':' . $d['to_staff']);
  return null;
}
function deleg_respond(array $me, array $d, bool $accept, string $note): ?string {
  if (!deleg_is_delegate($d, $me)) return 'forbidden';
  if ($d['status'] !== 'pending') return 'deleg_bad_state';
  $note = mb_substr(trim($note), 0, 600);
  if (!$accept && $note === '') return 'deleg_reason_required';
  deleg_set($me, $d, $accept ? 'accepted' : 'rejected', ['response_note' => $note ?: null, 'responded_at' => now()], $accept ? 'accepted' : 'rejected', $note ?: null);
  notify((int)$d['from_staff'], 'delegation', $accept ? 'n_deleg_accepted' : 'n_deleg_rejected', [staff_name((int)$d['to_staff'])], '/delegations/' . $d['id'], 'deleg-r:' . $d['id'] . ':' . ($accept ? 'a' : 'r') . ':' . $d['to_staff']);
  return null;
}
function deleg_reassign(array $me, array $d, int $to, string $instr): ?string {
  if (!deleg_is_asker($d, $me)) return 'forbidden';
  if (!in_array($d['status'], ['pending', 'rejected', 'accepted'], true)) return 'deleg_bad_state';
  if (!staff_active_in_office($to)) return 'deleg_to_required';
  if ($to === (int)$d['to_staff'] || $to === (int)$d['from_staff']) return 'deleg_same_person';
  $m = orow('SELECT * FROM matters WHERE office_id = :office AND id = :id', ['id' => $d['matter_id']]);
  if (!deleg_can_open($to, $m)) return 'deleg_no_access';
  $instr = mb_substr(trim($instr), 0, 4000);
  $prev = (int)$d['to_staff'];
  deleg_set($me, $d, 'pending', ['to_staff' => $to, 'response_note' => null, 'responded_at' => null, 'memo_document_id' => null] + ($instr !== '' ? ['instructions' => $instr] : []), 'reassigned', staff_name($prev));
  notify($to, 'delegation', 'n_deleg_request', [staff_name((int)$d['from_staff'])], '/delegations/' . $d['id'], 'deleg:' . $d['id'] . ':' . $to);
  if ($d['status'] === 'accepted') notify($prev, 'delegation', 'n_deleg_withdrawn', [], '/delegations/' . $d['id'], 'deleg-w:' . $d['id'] . ':' . $prev);
  return null;
}
function deleg_complete(array $me, array $d, string $result): ?string {
  if (!deleg_is_delegate($d, $me)) return 'forbidden';
  if ($d['status'] !== 'accepted') return 'deleg_bad_state';
  $result = mb_substr(trim($result), 0, 8000); if ($result === '') return 'deleg_result_required';
  deleg_set($me, $d, 'done', ['result_note' => $result, 'done_at' => now()], 'done', $result);
  notify((int)$d['from_staff'], 'delegation', 'n_deleg_done', [staff_name((int)$d['to_staff'])], '/delegations/' . $d['id'], 'deleg-d:' . $d['id']);
  return null;
}
function deleg_cancel(array $me, array $d): ?string {
  if (!deleg_is_asker($d, $me)) return 'forbidden';
  if (!in_array($d['status'], ['draft', 'pending', 'accepted', 'rejected'], true)) return 'deleg_bad_state';
  deleg_set($me, $d, 'cancelled', [], 'cancelled');
  if (in_array($d['status'], ['pending', 'accepted'], true)) notify((int)$d['to_staff'], 'delegation', 'n_deleg_withdrawn', [], '/delegations/' . $d['id'], 'deleg-w:' . $d['id'] . ':' . $d['to_staff']);
  return null;
}
/** The delegation list: f = staff (either side), status, period (day|week|all), d (anchor date). */
function deleg_list(array $me, array $f): array {
  $vis = matter_visible_sql($me); $w = ['x.office_id = :office', $vis[0]]; $p = $vis[1];
  if (!can('calendar', 'view', $me)) { $w[] = '(x.to_staff = :me1 OR x.from_staff = :me1 OR x.created_by = :me1)'; $p['me1'] = (int)$me['id']; }
  if (!empty($f['staff'])) { $w[] = '(x.to_staff = :st OR x.from_staff = :st)'; $p['st'] = (int)$f['staff']; }
  if (!empty($f['mine'])) { $w[] = 'x.to_staff = :me2'; $p['me2'] = (int)$me['id']; }
  if (!empty($f['status']) && in_array($f['status'], DELEG_STATUSES, true)) { $w[] = 'x.status = :s'; $p['s'] = $f['status']; }
  [$from, $to] = deleg_period($f);
  if ($from) { $w[] = '(COALESCE(a.starts_at, x.created_at) >= :from AND COALESCE(a.starts_at, x.created_at) < :to)'; $p += ['from' => "$from 00:00:00", 'to' => "$to 00:00:00"]; }
  return orows('SELECT x.*, m.ref AS matter_ref, m.title AS matter_title, a.starts_at, a.title AS appt_title, a.court_id AS appt_court, a.circuit AS appt_circuit
    FROM delegations x JOIN matters m ON m.id = x.matter_id AND m.office_id = x.office_id LEFT JOIN appointments a ON a.id = x.appointment_id AND a.office_id = x.office_id
    WHERE ' . implode(' AND ', $w) . ' ORDER BY COALESCE(a.starts_at, x.created_at), x.id', $p);
}
function deleg_period(array $f): array {
  $d = valid_date($f['d'] ?? null) ?? date('Y-m-d');
  return match ($f['period'] ?? 'all') {
    'day' => [$d, date('Y-m-d', strtotime("$d +1 day"))],
    'week' => (function () use ($d) { $dow = (int)date('w', strtotime($d)); $from = date('Y-m-d', strtotime("$d -" . (($dow - country_week_start() + 7) % 7) . ' days')); return [$from, date('Y-m-d', strtotime("$from +7 days"))]; })(),   // weeks start on Saturday
    default => [null, null],
  };
}

/* ---------- templates ---------- */
function tpl_doc_list(?string $kind = null, bool $all = false): array {
  $w = 'office_id = :office' . ($all ? '' : ' AND active = 1'); $p = [];
  if ($kind && in_array($kind, TEMPLATE_KINDS, true)) { $w .= ' AND kind = :k'; $p['k'] = $kind; }
  return orows("SELECT * FROM doc_templates WHERE $w ORDER BY kind, name", $p);
}
function tpl_doc_get(int $id): ?array { return orow('SELECT * FROM doc_templates WHERE office_id = :office AND id = :id', ['id' => $id]); }
function tpl_doc_versions(int $id): array { return orows('SELECT * FROM doc_template_versions WHERE office_id = :office AND template_id = :t ORDER BY version DESC', ['t' => $id]); }
/** Create or change a template. A changed text keeps the earlier one as a version. A .txt file may replace the text. */
function tpl_doc_save(array $me, array $in, ?array $file, ?array $old = null): array {
  $kind = in_array($in['kind'] ?? '', TEMPLATE_KINDS, true) ? $in['kind'] : null; $name = mb_substr(trim((string)($in['name'] ?? '')), 0, 160);
  $body = str_replace("\r\n", "\n", (string)($in['body'] ?? ''));
  if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']) || strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION)) !== 'txt' || $file['size'] > 200000) return ['error' => 'tpl_file_bad', 'data' => $in];
    $txt = (string)file_get_contents($file['tmp_name']); if (!mb_check_encoding($txt, 'UTF-8')) return ['error' => 'tpl_file_bad', 'data' => $in];
    $body = str_replace("\r\n", "\n", preg_replace('/^\xEF\xBB\xBF/', '', $txt));
  }
  $body = mb_substr(trim($body), 0, 30000);
  $d = ['kind' => $kind, 'name' => $name, 'body' => $body];
  if (!$kind) return ['error' => 'tpl_kind_required', 'data' => $d];
  if ($name === '') return ['error' => 'tpl_name_required', 'data' => $d];
  if ($body === '') return ['error' => 'tpl_body_required', 'data' => $d];
  if ($bad = tpl_unknown_fields($body)) return ['error' => 'tpl_unknown_field', 'data' => $d, 'fields' => $bad];
  if (!$old) {
    $id = o_insert('doc_templates', $d + ['version' => 1, 'active' => 1, 'created_by' => $me['id'], 'created_at' => now(), 'updated_by' => $me['id'], 'updated_at' => now()]);
    o_insert('doc_template_versions', ['template_id' => $id, 'version' => 1, 'body' => $body, 'created_by' => $me['id'], 'created_at' => now()]);
    law_audit('template_added', 'doc_template', $id, ['kind' => $kind], null, $me);
    return ['id' => $id];
  }
  $v = (int)$old['version'] + ($old['body'] !== $body ? 1 : 0);
  o_update('doc_templates', (int)$old['id'], $d + ['version' => $v, 'active' => !empty($in['active']) ? 1 : 0, 'updated_by' => $me['id'], 'updated_at' => now()]);
  if ($v !== (int)$old['version']) o_insert('doc_template_versions', ['template_id' => $old['id'], 'version' => $v, 'body' => $body, 'created_by' => $me['id'], 'created_at' => now()]);
  law_audit('template_changed', 'doc_template', $old['id'], ['version' => $v], null, $me);
  return ['id' => (int)$old['id']];
}
function tpl_fields_in(string $body): array { preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/', $body, $mm); return array_values(array_unique($mm[1])); }
function tpl_unknown_fields(string $body): array { return array_values(array_diff(tpl_fields_in($body), TEMPLATE_FIELDS)); }

/** Field values for a case file (and optionally a delegation). Empty when the file has no value. */
function tpl_values(array $m, ?array $d = null): array {
  $c = contact_get((int)$m['client_id']); $o = current_office(); $df = 'd/m/Y';
  $next = orow("SELECT starts_at FROM appointments WHERE office_id = :office AND matter_id = :m AND status = 'scheduled' AND kind = 'hearing' AND starts_at >= :now ORDER BY starts_at LIMIT 1", ['m' => $m['id'], 'now' => now()]);
  $opp = array_map('contact_label', array_filter(matter_parties((int)$m['id']), fn($p) => $p['role'] === 'opponent'));
  $v = ['office_name' => office_name($o), 'today' => date($df), 'client_name' => $c ? contact_label($c) : '', 'matter_ref' => (string)$m['ref'], 'matter_title' => (string)$m['title'],
    'case_type' => $m['case_type_id'] ? lookup_name((int)$m['case_type_id'], 'case_type') : '', 'court' => $m['court_id'] ? lookup_name((int)$m['court_id'], 'court') : '',
    'circuit' => (string)$m['circuit'], 'official_no' => (string)$m['official_no'], 'lead_name' => staff_name((int)$m['lead_id']), 'opponents' => implode(t('list_sep'), $opp),
    'next_hearing' => $next ? date($df . ' H:i', strtotime($next['starts_at'])) : '', 'hearing_date' => '', 'hearing_time' => '', 'hearing_title' => '', 'delegate_name' => '', 'requested_by' => '', 'instructions' => ''];
  if ($d) {
    $v['delegate_name'] = staff_name((int)$d['to_staff']); $v['requested_by'] = staff_name((int)$d['from_staff']); $v['instructions'] = (string)$d['instructions'];
    if ($d['appointment_id'] && ($a = orow('SELECT * FROM appointments WHERE office_id = :office AND id = :id', ['id' => $d['appointment_id']]))) {
      $v['hearing_date'] = date($df, strtotime($a['starts_at'])); $v['hearing_time'] = date('H:i', strtotime($a['starts_at'])); $v['hearing_title'] = $a['title'];
      if ($a['court_id']) $v['court'] = lookup_name((int)$a['court_id'], 'court'); if ($a['circuit']) $v['circuit'] = $a['circuit'];
    }
  }
  return array_map(fn($x) => $x === '—' ? '' : $x, $v);
}
/** Fill the fields. Returns [text, missing field names]. A field with no value stays visible as [field] so the reviewer sees it. */
function tpl_fill(string $body, array $values): array {
  $missing = [];
  $out = preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', function ($mm) use ($values, &$missing) {
    $k = $mm[1]; $v = $values[$k] ?? '';
    if ($v === '') { $missing[$k] = true; return '[' . t('tf_' . $k) . ']'; }
    return $v;
  }, $body);
  return [(string)$out, array_keys($missing)];
}
/** The built-in delegation memo layout, used when the office has no delegation template. Not a legal text: it lists the facts of the request. */
function deleg_default_body(): string { return t('deleg_memo_default'); }

/* ---------- PDF from text, saved as a document ---------- */
/** Lay a text out on A4 pages with the office name on top. */
function text_pdf(string $title, string $text, string $subtitle = ''): string {
  require_once __DIR__ . '/pdf.php';
  $o = current_office(); $ar = lang() === 'ar'; $pdf = new ImzPdf(false, $ar); $pdf->addPage(); $W = $pdf->w; $x0 = 56; $x1 = $W - 56; $max = $x1 - $x0;
  $X = fn(float $x) => $ar ? $W - $x : $x; $al = $ar ? 'R' : 'L'; $ar2 = $ar ? 'L' : 'R';
  $head = function () use ($pdf, $X, $al, $ar2, $x0, $x1, $o, $title) {
    $pdf->text($X($x0), 56, office_name($o), 13, true, $al); $pdf->text($X($x1), 56, $pdf->fit($title, 260, 12, true), 12, true, $ar2);
    $pdf->line($x0, 68, $x1, 68, [0.66, 0.54, 0.31], 0.8); return 92;
  };
  $y = $head();
  if ($subtitle !== '') { $pdf->text($X($x0), $y, $pdf->fit($subtitle, $max, 10), 10, false, $al, [0.4, 0.4, 0.45]); $y += 22; }
  foreach (explode("\n", str_replace("\r", '', $text)) as $para) {
    $lines = $para === '' ? [''] : pdf_wrap($pdf, $para, $max, 11);
    foreach ($lines as $ln) {
      if ($y > $pdf->h - 70) { $pdf->addPage(); $y = $head(); }
      if ($ln !== '') $pdf->text($X($x0), $y, $ln, 11, false, $al);
      $y += 17;
    }
  }
  $n = $pdf->pageCount();
  for ($i = 0; $i < $n; $i++) { $pdf->onPage($i); $pdf->text($W / 2, $pdf->h - 36, t('pdf_page', $i + 1, $n) . ' · ' . t('pdf_generated', date('d/m/Y H:i')), 8, false, 'C', [0.45, 0.45, 0.5]); }
  return $pdf->output($title);
}
function pdf_wrap(ImzPdf $pdf, string $s, float $max, float $size): array {
  $out = []; $cur = '';
  foreach (preg_split('/(\s+)/u', $s) as $w) {
    if ($w === '') continue;
    $try = $cur === '' ? $w : "$cur $w";
    if ($pdf->width($try, $size) <= $max) { $cur = $try; continue; }
    if ($cur !== '') $out[] = $cur;
    while ($pdf->width($w, $size) > $max && mb_strlen($w) > 1) { $cut = mb_strlen($w); while ($cut > 1 && $pdf->width(mb_substr($w, 0, $cut), $size) > $max) $cut--; $out[] = mb_substr($w, 0, $cut); $w = mb_substr($w, $cut); }
    $cur = $w;
  }
  if ($cur !== '') $out[] = $cur;
  return $out ?: [''];
}
/** Save generated bytes as a new document of a case file (same encryption and audit as an upload). */
function doc_create_generated(array $me, array $m, string $title, string $bytes, string $ext = 'pdf', string $mime = 'application/pdf'): array {
  if (!files_key()) return ['error' => 'doc_no_key'];
  if (storage_over(strlen($bytes))) return ['error' => 'limit_storage'];
  $tmp = tempnam(sys_get_temp_dir(), 'imz'); file_put_contents($tmp, $bytes);
  try {
    $stored = store_encrypted($tmp, office_id());
    $title = mb_substr($title, 0, 200); $name = preg_replace('/[^\p{L}\p{N}\-_ ]+/u', '', $title) ?: 'document';
    $id = o_insert('documents', ['matter_id' => $m['id'], 'contact_id' => null, 'title' => $title, 'category_id' => null, 'current_version' => 1, 'source' => 'staff',
      'portal_visible' => 0, 'archived' => 0, 'created_by' => $me['id'], 'created_at' => now(), 'updated_at' => now()]);
    o_insert('document_versions', ['document_id' => $id, 'version' => 1, 'stored_name' => $stored, 'orig_name' => mb_substr($name, 0, 190) . '.' . $ext, 'ext' => $ext, 'mime' => $mime, 'size' => strlen($bytes),
      'sha256' => hash('sha256', $bytes), 'enc' => 1, 'uploaded_by' => $me['id'], 'uploaded_by_client' => null, 'uploaded_at' => now()]);
  } finally { @unlink($tmp); }
  matter_event((int)$m['id'], $me, 'doc_add', null, ['doc' => $id, 'title' => $title, 'client' => false]);
  law_audit('document_generated', 'document', $id, ['matter' => $m['id'], 'size' => strlen($bytes)], null, $me);
  return ['id' => $id];
}
/** Memo for an accepted delegation: the office's delegation template, or the built-in layout. Saved to the file's documents. */
function deleg_memo(array $me, array $d): array {
  if (!in_array($d['status'], ['accepted', 'done'], true)) return ['error' => 'deleg_memo_state'];
  $m = orow('SELECT * FROM matters WHERE office_id = :office AND id = :id', ['id' => $d['matter_id']]);
  $tpl = orow("SELECT * FROM doc_templates WHERE office_id = :office AND kind = 'delegation' AND active = 1 ORDER BY id LIMIT 1");
  [$text] = tpl_fill($tpl['body'] ?? deleg_default_body(), tpl_values($m, $d));
  $title = t('deleg_memo') . ' — ' . $m['ref'];
  $r = doc_create_generated($me, $m, $title, text_pdf(t('deleg_memo'), $text, $m['ref'] . ' — ' . $m['title']));
  if (isset($r['id'])) { o_update('delegations', (int)$d['id'], ['memo_document_id' => $r['id'], 'updated_at' => now()]); law_audit('delegation_memo', 'delegation', $d['id'], ['document' => $r['id']], null, $me); }
  return $r;
}
/** The delegation report as a PDF table (daily or weekly). */
function deleg_report_pdf(array $list, string $label): string {
  require_once __DIR__ . '/pdf.php';
  $o = current_office(); $ar = lang() === 'ar'; $pdf = new ImzPdf(true, $ar); $pdf->addPage(); $W = $pdf->w; $x0 = 40; $x1 = $W - 40;
  $X = fn(float $x) => $ar ? $W - $x : $x; $al = $ar ? 'R' : 'L';
  $cols = [[t('deleg_when'), 95], [t('appt_matter'), 150], [t('matter_court'), 140], [t('deleg_from'), 115], [t('deleg_to'), 115], [t('status'), 85]];
  $head = function () use ($pdf, $X, $al, $x0, $x1, $o, $label, $cols) {
    $pdf->text($X($x0), 46, office_name($o) . ' — ' . t('deleg_report'), 13, true, $al); $pdf->text($X($x0), 64, $label, 10, false, $al, [0.4, 0.4, 0.45]);
    $y = 84; $pdf->rect($x0, $y - 13, $x1 - $x0, 19, [0.95, 0.93, 0.89]); $x = $x0 + 4;
    foreach ($cols as [$c, $w]) { $pdf->text($X($x), $y, $c, 9, true, $al); $x += $w; } return $y + 20;
  };
  $y = $head();
  if (!$list) $pdf->text($X($x0 + 4), $y, t('empty'), 10, false, $al);
  foreach ($list as $r) {
    if ($y > $pdf->h - 50) { $pdf->addPage(); $y = $head(); }
    $vals = [$r['starts_at'] ? date('d/m/Y H:i', strtotime($r['starts_at'])) : date('d/m/Y', strtotime($r['created_at'])), $r['matter_ref'] . ' ' . $r['matter_title'],
      $r['appt_court'] ? lookup_name((int)$r['appt_court'], 'court') . ($r['appt_circuit'] ? ' · ' . $r['appt_circuit'] : '') : '—', staff_name((int)$r['from_staff']), staff_name((int)$r['to_staff']), t('dst_' . $r['status'])];
    $x = $x0 + 4; foreach ($cols as $i => [, $w]) { $pdf->text($X($x), $y, $pdf->fit($vals[$i], $w - 8, 9), 9, false, $al); $x += $w; }
    $pdf->line($x0, $y + 6, $x1, $y + 6, [0.88, 0.88, 0.9]); $y += 18;
  }
  $pdf->text($W / 2, $pdf->h - 26, t('pdf_generated', date('d/m/Y H:i')), 8, false, 'C', [0.45, 0.45, 0.5]);
  return $pdf->output(t('deleg_report'));
}

/* ---------- hearing outcome with follow-up ---------- */
/** Record the outcome, then optionally a follow-up task and an attached document. Inputs are checked before anything is saved. */
function appt_outcome_full(array $me, array $a, array $in, ?array $file): array {
  $taskTitle = trim((string)($in['task_title'] ?? '')); $hasFile = $file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
  if ($taskTitle !== '') {
    if (!can('tasks', 'create', $me)) return ['error' => 'forbidden'];
    [, $terr] = task_input(['title' => $taskTitle, 'assignee_id' => $in['task_assignee'] ?? 0, 'due_on' => $in['task_due'] ?? '', 'matter_id' => $a['matter_id']], $me);
    if ($terr) return ['error' => $terr];
  }
  if ($hasFile) {
    if (!$a['matter_id'] || !can('documents', 'create', $me)) return ['error' => 'forbidden'];
    if (!files_key()) return ['error' => 'doc_no_key'];
    $u = upload_check($file); if (is_string($u)) return ['error' => $u];
  }
  $r = appt_outcome($me, $a, $in); if (isset($r['error'])) return $r;
  if ($taskTitle !== '') { $t = task_save($me, ['title' => $taskTitle, 'assignee_id' => $in['task_assignee'] ?? 0, 'due_on' => $in['task_due'] ?? '', 'matter_id' => $a['matter_id'],
    'details' => t('task_from_outcome', $a['title'], date('d/m/Y', strtotime($a['starts_at'])))], null); $r['task'] = $t['id'] ?? null; }
  if ($hasFile) { $dc = doc_create($me, ['title' => trim((string)($in['doc_title'] ?? '')) ?: t('doc_from_outcome', $a['title'], date('d/m/Y', strtotime($a['starts_at']))), 'matter_id' => $a['matter_id']], $file); $r['doc'] = $dc['id'] ?? null; if (isset($dc['error'])) $r['doc_error'] = $dc['error']; }
  return $r;
}
