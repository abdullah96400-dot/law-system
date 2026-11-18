<?php
/* ==========================================================================
   Full office export: the office takes its data with it.
   - One ZIP: a CSV per table (UTF-8 with BOM, opens in Excel), every
     document version decrypted under documents/, and a README.
   - Managing partner only. Needs a password of 10+ characters; the ZIP is
     AES-256 encrypted with it.
   - Secrets are left out (password hashes, two-step keys, link tokens).
   - Written to the audit log, with the counts.
   Ending a subscription never deletes data; this export is how an office
   leaves with everything.
   ========================================================================== */
declare(strict_types=1);

const EXPORT_TABLES = ['offices', 'branches', 'staff', 'role_permissions', 'contacts', 'conflict_checks', 'conflict_check_names', 'conflict_hits', 'lookups',
  'matters', 'matter_parties', 'matter_team', 'matter_events', 'appointments', 'tasks', 'task_templates', 'documents', 'document_versions', 'document_shares',
  'fee_agreements', 'fee_installments', 'invoices', 'invoice_lines', 'payments', 'expenses', 'trust_entries',
  'portal_users', 'client_updates', 'portal_messages', 'doc_requests', 'support_grants', 'audit_log'];
const EXPORT_HIDDEN = ['password_hash', 'totp_secret', 'totp_last', 'token_hash', 'session_ver'];

function export_build(array $me, string $password): array {
  if ($me['role'] !== 'partner') return ['error' => 'forbidden'];
  if (mb_strlen($password) < 10) return ['error' => 'export_pw_short'];
  $o = current_office(); $oid = (int)$o['id'];
  $dir = law_root() . '/storage/tmp'; if (!is_dir($dir)) @mkdir($dir, 0700, true);
  $zipPath = $dir . '/export-' . $oid . '-' . bin2hex(random_bytes(6)) . '.zip';
  $z = new ZipArchive(); if ($z->open($zipPath, ZipArchive::CREATE | ZipArchive::EXCL) !== true) return ['error' => 'export_failed'];
  $z->setPassword($password);
  $add = function (string $name, string $data) use ($z) { $z->addFromString($name, $data); $z->setEncryptionName($name, ZipArchive::EM_AES_256); };
  $counts = [];
  foreach (EXPORT_TABLES as $tb) {
    $rows = $tb === 'offices' ? rows('SELECT * FROM offices WHERE id=?', [$oid]) : rows("SELECT * FROM $tb WHERE office_id=? ORDER BY id", [$oid]);
    $counts[$tb] = count($rows);
    $h = fopen('php://temp', 'w+'); fwrite($h, "\xEF\xBB\xBF");
    $cols = $rows ? array_values(array_diff(array_keys($rows[0]), EXPORT_HIDDEN)) : [];
    if ($cols) fputcsv($h, $cols, ',', '"', '');
    foreach ($rows as $r) fputcsv($h, array_map(fn($c) => $r[$c], $cols), ',', '"', '');
    rewind($h); $add('data/' . $tb . '.csv', (string)stream_get_contents($h)); fclose($h);
  }
  $docs = 0; $missing = [];
  foreach (rows('SELECT v.*, d.title FROM document_versions v JOIN documents d ON d.id = v.document_id AND d.office_id = v.office_id WHERE v.office_id=? ORDER BY v.document_id, v.version', [$oid]) as $v) {
    $safe = preg_replace('/[^\p{L}\p{N}._ -]+/u', '_', (string)$v['orig_name']) ?: 'file';
    $name = sprintf('documents/%d-v%d-%s', $v['document_id'], $v['version'], mb_substr($safe, 0, 120));
    $tmp = fopen('php://temp/maxmemory:8388608', 'w+');
    $ok = $v['enc'] ? stream_decrypted($oid, $v['stored_name'], $tmp) : (is_file($p = doc_path($oid, $v['stored_name'])) && fwrite($tmp, (string)file_get_contents($p)) !== false);
    if (!$ok) { $missing[] = $name; fclose($tmp); continue; }
    rewind($tmp); $add($name, (string)stream_get_contents($tmp)); fclose($tmp); $docs++;
  }
  $readme = export_readme($o, $counts, $docs, $missing);
  $add('README.txt', $readme);
  $z->close();
  law_audit('office_exported', 'office', $oid, ['tables' => count($counts), 'rows' => array_sum($counts), 'files' => $docs, 'missing_files' => count($missing)], null, $me);
  return ['path' => $zipPath, 'name' => 'office-' . $o['code'] . '-' . date('Ymd-His') . '.zip'];
}
function export_readme(array $o, array $counts, int $docs, array $missing): string {
  $l = ["Al Imtyaz Law — full office export / تصدير كامل لبيانات المكتب", str_repeat('=', 60), '',
    'Office / المكتب: ' . $o['name_en'] . ' — ' . $o['name_ar'] . ' (' . $o['code'] . ')', 'Created / تاريخ التصدير: ' . now(), '',
    'data/*.csv  — one file per table, UTF-8, first row = column names. Amounts are in baisa (1 OMR = 1000 baisa).',
    '              ملف لكل جدول، الصف الأول أسماء الأعمدة. المبالغ بالبيسة (الريال = 1000 بيسة).',
    'documents/  — every saved version of every document, decrypted. Name: <document id>-v<version>-<original name>.',
    '              كل نسخة محفوظة من كل مستند بعد فك التشفير.',
    'Left out on purpose / حُذف عمدًا: password hashes, two-step keys, sign-in and share-link tokens.',
    'audit_log.csv keeps prev_hash and hash so the chain can still be checked. / سجل التدقيق يحتفظ بسلسلة البصمات للتحقق.', '',
    'Rows per table / عدد الصفوف:'];
  foreach ($counts as $k => $n) $l[] = sprintf('  %-22s %d', $k, $n);
  $l[] = ''; $l[] = 'Document files / ملفات المستندات: ' . $docs;
  if ($missing) { $l[] = 'Could not read (check the server backup) / تعذّرت قراءتها:'; foreach ($missing as $m) $l[] = '  ' . $m; }
  return implode("\r\n", $l) . "\r\n";
}
function export_send(array $r): never {
  header('Content-Type: application/zip'); header('Content-Disposition: attachment; filename="' . $r['name'] . '"'); header('Content-Length: ' . filesize($r['path']));
  header('Cache-Control: no-store'); readfile($r['path']); @unlink($r['path']); exit;
}
