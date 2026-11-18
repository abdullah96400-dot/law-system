<?php
/* ==========================================================================
   Import from Excel (.xlsx) or CSV, in two steps: the file is checked row by
   row and shown as a preview; nothing is saved until the person confirms.
   - Contacts: one row per person or company.
   - Case files: each row is linked to a client already in the office (by
     email, phone or exact name). The rule "no case file without an approved
     conflict check" still holds: every imported file gets its own check,
     recorded as cleared by the person importing, with a note saying it was
     done before the import. Only people who may both decide conflict
     checks and open case files can import case files.
   ========================================================================== */
declare(strict_types=1);

const IMPORT_MAX_ROWS = 2000;
const IMPORT_COLS = [
  'contacts' => ['kind', 'is_client', 'name_ar', 'name_en', 'aliases', 'id_type', 'id_number', 'phone', 'email', 'pref_lang', 'address', 'notes'],
  'matters'  => ['title', 'client', 'case_type', 'court', 'circuit', 'official_no', 'status', 'lead_email', 'opened_on', 'summary'],
];
const IMPORT_EXAMPLE = [
  'contacts' => ['person', 'yes', 'مثال: سالم بن علي', 'Example: Salim Ali', '', 'civil_id', '12345678', '+968 9000 0000', 'salim@example.com', 'ar', 'Muscat', ''],
  'matters'  => ['Example: rent dispute', 'salim@example.com', 'Civil', 'Primary Court Muscat', '', '', 'Open', 'lawyer@office.om', '2026-01-15', ''],
];

function import_allowed(string $kind, array $me): bool {
  return $kind === 'contacts' ? can('clients', 'create', $me) : (can('matters', 'create', $me) && can('conflicts', 'edit', $me));
}
function import_template(string $kind): array {
  return [t('imp_sheet_' . $kind) => [IMPORT_COLS[$kind], IMPORT_EXAMPLE[$kind]]];
}
/** Read an uploaded .xlsx or .csv into rows of strings. Returns rows or an error key. */
function import_read(?array $f): array|string {
  if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string)$f['tmp_name'])) return 'imp_no_file';
  if ((int)$f['size'] > 5 * 1048576) return 'imp_too_big';
  $ext = strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION));
  if ($ext === 'xlsx') { $rows = xlsx_read((string)$f['tmp_name'], IMPORT_MAX_ROWS + 2); return $rows === null ? 'imp_unreadable' : $rows; }
  if ($ext !== 'csv') return 'imp_bad_type';
  $raw = (string)file_get_contents((string)$f['tmp_name']);
  if (str_starts_with($raw, "\xEF\xBB\xBF")) $raw = substr($raw, 3);
  if (!mb_check_encoding($raw, 'UTF-8')) return 'imp_not_utf8';
  $h = fopen('php://temp', 'w+'); fwrite($h, $raw); rewind($h);
  $first = (string)fgets($h); rewind($h);
  $sep = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
  $rows = []; while (($r = fgetcsv($h, 0, $sep, '"', '')) !== false) { if ($r === [null]) continue; $rows[] = array_map(fn($v) => trim((string)$v), $r); if (count($rows) > IMPORT_MAX_ROWS + 1) break; }
  fclose($h); return $rows;
}
/** Turn sheet rows into keyed records, using the header row. Returns [records, error key|null]. */
function import_records(string $kind, array $rows): array {
  if (!$rows) return [[], 'imp_empty'];
  $head = array_map(fn($v) => strtolower(trim((string)$v)), array_shift($rows));
  $missing = array_diff($kind === 'contacts' ? ['name_ar', 'name_en'] : ['title', 'client', 'lead_email'], $head);
  if ($kind === 'contacts' ? count($missing) === 2 : $missing) return [[], 'imp_bad_header'];
  $out = [];
  foreach ($rows as $r) {
    if (!array_filter($r, fn($v) => $v !== '')) continue;
    $rec = []; foreach ($head as $i => $col) if (in_array($col, IMPORT_COLS[$kind], true)) $rec[$col] = (string)($r[$i] ?? '');
    $out[] = $rec;
  }
  if (count($out) > IMPORT_MAX_ROWS) return [[], 'imp_too_many'];
  return $out ? [$out, null] : [[], 'imp_empty'];
}
function imp_yes(string $v): bool { return in_array(mb_strtolower(trim($v)), ['1', 'yes', 'y', 'true', 'نعم', 'موكل', 'client'], true); }
function imp_lookup(string $kind, string $name): ?int {
  $n = trim($name); if ($n === '') return null;
  foreach (lookups($kind, true) as $l) if (mb_strtolower($l['name_ar']) === mb_strtolower($n) || mb_strtolower((string)$l['name_en']) === mb_strtolower($n)) return (int)$l['id'];
  return null;
}
function imp_client(string $v): ?array {
  $v = trim($v); if ($v === '') return null;
  if (str_contains($v, '@')) $r = orows('SELECT * FROM contacts WHERE office_id = :office AND archived = 0 AND email = :e', ['e' => norm_email($v)]);
  elseif ($pn = phone_norm($v)) $r = orows('SELECT * FROM contacts WHERE office_id = :office AND archived = 0 AND phone_norm = :p', ['p' => $pn]);
  else $r = orows('SELECT * FROM contacts WHERE office_id = :office AND archived = 0 AND (name_ar = :n OR name_en = :n)', ['n' => $v]);
  return count($r) === 1 ? $r[0] : null;
}
/** Check every record. Each result: ['row' => n, 'data' => …, 'error' => key|null, 'warn' => key|null]. */
function import_check(string $kind, array $recs): array {
  $out = []; $seenEmail = [];
  foreach ($recs as $i => $r) {
    $row = $i + 2; $err = null; $warn = null;
    if ($kind === 'contacts') {
      $in = $r; $in['is_client'] = imp_yes($r['is_client'] ?? '') ? 1 : 0;
      $in['kind'] = in_array(mb_strtolower($r['kind'] ?? ''), ['company', 'شركة', 'مؤسسة'], true) ? 'company' : 'person';
      [$d, $err] = contact_input($in);
      if (!$err && $d['email'] && isset($seenEmail[$d['email']])) $err = 'imp_dup_in_file';
      if (!$err) { if ($d['email']) $seenEmail[$d['email']] = 1; if (contact_duplicates($d)) $warn = 'imp_maybe_dup'; }
      $out[] = ['row' => $row, 'data' => $in, 'label' => $d['name_ar'] ?: $d['name_en'], 'error' => $err, 'warn' => $warn];
    } else {
      $client = imp_client($r['client'] ?? '');
      $lead = ($e = norm_email((string)($r['lead_email'] ?? ''))) ? orow("SELECT id FROM staff WHERE office_id = :office AND email = :e AND status = 'active'", ['e' => $e]) : null;
      $status = imp_lookup('status', $r['status'] ?? '') ?? (int)(orow('SELECT id FROM lookups WHERE office_id = :office AND kind = :k AND active = 1 AND is_closed = 0 ORDER BY sort, id LIMIT 1', ['k' => 'status'])['id'] ?? 0);
      $st = lookup_get($status, 'status');
      $d = ['title' => $r['title'] ?? '', 'client_id' => $client['id'] ?? 0, 'lead_id' => $lead['id'] ?? 0, 'status_id' => $status, 'case_type_id' => imp_lookup('case_type', $r['case_type'] ?? ''),
            'court_id' => imp_lookup('court', $r['court'] ?? ''), 'circuit' => $r['circuit'] ?? '', 'official_no' => $r['official_no'] ?? '', 'opened_on' => $r['opened_on'] ?? '', 'summary' => $r['summary'] ?? '', 'is_closed' => (int)($st['is_closed'] ?? 0)];
      if (trim($d['title']) === '') $err = 'matter_title_required';
      elseif (!$client) $err = 'imp_client_not_found';
      elseif (!$lead) $err = 'imp_lead_not_found';
      elseif (!$st) $err = 'matter_status_required';
      elseif (($r['opened_on'] ?? '') !== '' && !valid_date($r['opened_on'])) $err = 'imp_bad_date';
      elseif (($r['case_type'] ?? '') !== '' && !$d['case_type_id'] || ($r['court'] ?? '') !== '' && !$d['court_id']) $warn = 'imp_list_unknown';
      $out[] = ['row' => $row, 'data' => $d, 'label' => trim($d['title']) . ($client ? ' — ' . contact_label($client) : ''), 'error' => $err, 'warn' => $warn];
    }
  }
  return $out;
}
/** Save the checked rows that have no error. Returns the number saved. */
function import_commit(array $me, string $kind, array $checked, string $fileName): int {
  $ok = array_values(array_filter($checked, fn($c) => !$c['error'])); if (!$ok) return 0;
  // Row by row: each case file is opened in its own transaction (matter_open). Rows were checked first, so a failure stops the run and is reported.
  $n = 0;
  try {
    foreach ($ok as $c) {
      $d = $c['data'];
      if ($kind === 'contacts') { $r = contact_save($me, $d, null, true); if (isset($r['id'])) $n++; continue; }
      $client = contact_get((int)$d['client_id']); if (!$client) continue;
      $chk = o_insert('conflict_checks', ['ref' => next_ref('CC', 'CC'), 'subject' => mb_substr(t('imp_check_subject', $d['title']), 0, 300), 'status' => 'cleared',
        'decision_note' => t('imp_check_note', $fileName), 'decided_by' => $me['id'], 'decided_at' => now(), 'created_by' => $me['id'], 'created_at' => now(), 'updated_at' => now()]);
      $nm = contact_label($client); o_insert('conflict_check_names', ['check_id' => $chk, 'role' => 'client', 'name' => mb_substr($nm, 0, 200), 'norm' => mb_substr(name_norm($nm), 0, 255)]);
      check_run($chk);
      $openStatus = $d['is_closed'] ? (int)orow('SELECT id FROM lookups WHERE office_id = :office AND kind = :k AND active = 1 AND is_closed = 0 ORDER BY sort, id LIMIT 1', ['k' => 'status'])['id'] : $d['status_id'];
      $r = matter_open($me, $d + ['check_id' => $chk, 'status_id' => $openStatus]);
      if (!isset($r['id'])) throw new RuntimeException('import row ' . $c['row'] . ': ' . ($r['error'] ?? '?'));
      if ($d['is_closed']) matter_set_status($me, matter_for($r['id'], $me), (int)$d['status_id'], t('imp_closed_note'));
      $n++;
    }
  } catch (Throwable $e) {
    law_audit('import_stopped', 'import', $kind, ['file' => mb_substr($fileName, 0, 120), 'saved' => $n], null, $me);
    throw $e;
  }
  law_audit('import_done', 'import', $kind, ['file' => mb_substr($fileName, 0, 120), 'saved' => $n, 'skipped' => count($checked) - $n], null, $me);
  return $n;
}
