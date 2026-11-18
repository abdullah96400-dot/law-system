<?php
/* ==========================================================================
   Documents.
   - Files are stored outside the website folder under random names, each
     encrypted (libsodium XChaCha20-Poly1305, or OpenSSL AES-256-GCM where PHP has no sodium) with the key in
     config.php ('files_key'). Without that key the app refuses uploads.
   - Allowed types and size are limited; PDFs with scripts, Office files
     with macros and the antivirus test file are refused; an optional ClamAV
     daemon scans every upload (fails closed).
   - Who can see a document: the case file's rules (restricted files stay
     hidden) plus the documents permissions. Downloads, previews, shares
     and changes are all written to the audit log.
   - Sharing: a link with a random 256-bit token (only its hash is stored),
     limited in time and optionally in downloads, revocable at any time.
   - Nothing is erased: "archive" hides a document; versions are kept.
   - Files are never sent to any outside service or used to train AI.
   ========================================================================== */
declare(strict_types=1);

const DOC_TYPES = ['pdf' => ['application/pdf'], 'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
  'doc' => ['application/msword', 'application/CDFV2', 'application/x-ole-storage', 'application/octet-stream'], 'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
  'xls' => ['application/vnd.ms-excel', 'application/CDFV2', 'application/x-ole-storage', 'application/octet-stream'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'txt' => ['text/plain']];
const DOC_PREVIEW = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];
const SHARE_MAX_DAYS = 30;

function files_key(): ?string { $k = base64_decode((string)cfg('files_key', ''), true); return $k !== false && strlen($k) === 32 ? $k : null; }
function files_dir(): string { return rtrim((string)cfg('files_dir', law_root() . '/storage/files'), '/'); }
function doc_path(int $officeId, string $stored): string { return files_dir() . '/' . $officeId . '/' . substr($stored, 0, 2) . '/' . $stored; }
function max_upload_bytes(): int { return (int)cfg('max_upload_mb', 20) * 1048576; }

function screen_file(string $path, string $ext): ?string {
  $data = (string)@file_get_contents($path, false, null, 0, 32 * 1048576);
  if (str_contains($data, 'EICAR-STANDARD-ANTIVIRUS-TEST-FILE')) return 'doc_refused_test';
  if ($ext === 'pdf' && preg_match('#/(JavaScript|JS|Launch|EmbeddedFile|RichMedia)\b#', $data)) return 'doc_refused_pdf';
  if (in_array($ext, ['docx', 'xlsx'], true)) {
    $z = new ZipArchive(); if ($z->open($path) !== true) return 'doc_refused_corrupt';
    for ($i = 0; $i < $z->numFiles; $i++) { $n = strtolower((string)$z->getNameIndex($i)); if (str_contains($n, 'vbaproject') || str_ends_with($n, '.bin') && str_contains($n, 'activex')) { $z->close(); return 'doc_refused_macro'; } }
    $z->close();
  }
  if (in_array($ext, ['doc', 'xls'], true) && (str_contains($data, "_\0V\0B\0A\0_\0P\0R\0O\0J\0E\0C\0T") || str_contains($data, 'VBA_PROJECT') || str_contains($data, "M\0a\0c\0r\0o\0s"))) return 'doc_refused_macro';
  $sock = (string)cfg('clamav_socket', '');
  if ($sock !== '') {
    $target = str_starts_with($sock, 'tcp://') ? $sock : 'unix://' . $sock;
    $s = @stream_socket_client($target, $en, $es, 5); if (!$s) return 'doc_refused_scanner';
    stream_set_timeout($s, 30); fwrite($s, "zINSTREAM\0"); $f = fopen($path, 'rb');
    while (!feof($f)) { $c = fread($f, 8192); if ($c === '' || $c === false) break; fwrite($s, pack('N', strlen($c)) . $c); }
    fclose($f); fwrite($s, pack('N', 0)); $resp = trim((string)stream_get_contents($s), "\0\r\n "); fclose($s);
    if (!str_ends_with($resp, 'OK')) return str_contains($resp, 'FOUND') ? 'doc_refused_virus' : 'doc_refused_scanner';
  }
  return null;
}
/** Encrypt $src into the office's store. Returns the stored name. */
function store_encrypted(string $src, int $officeId): string {
  $key = files_key(); if (!$key) throw new RuntimeException('files_key missing');
  $name = bin2hex(random_bytes(20)); $dst = doc_path($officeId, $name);
  if (!is_dir(dirname($dst)) && !@mkdir(dirname($dst), 0750, true)) throw new RuntimeException('Cannot create the files folder');
  crypt_encrypt_file($src, $dst . '.part', $key); rename($dst . '.part', $dst); @chmod($dst, 0640);
  return $name;
}
/** Decrypt a stored file to the output (or to $sink). Returns false if the file is missing or was altered. */
function stream_decrypted(int $officeId, string $stored, $sink = null): bool {
  $key = files_key(); $p = doc_path($officeId, $stored); if (!$key || !is_file($p)) return false;
  $out = $sink ?? fopen('php://output', 'wb');
  return crypt_decrypt_file($p, $out, $key);
}

/** Check an uploaded file ($_FILES entry). Returns [tmp path, original name, ext, mime, size] or an error key. */
function upload_check(?array $f): array|string {
  if (!$f || !isset($f['error']) || is_array($f['error'])) return 'doc_file_required';
  if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE || ($f['size'] ?? 0) > max_upload_bytes()) return 'doc_too_big';
  if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) return 'doc_file_required';
  $name = mb_substr(basename(str_replace('\\', '/', (string)$f['name'])), 0, 200);
  $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
  if (!isset(DOC_TYPES[$ext])) return 'doc_type_refused';
  $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) ?: 'application/octet-stream';
  if (!in_array($mime, DOC_TYPES[$ext], true)) return 'doc_type_mismatch';
  if ((int)$f['size'] <= 0) return 'doc_file_required';
  if ($e = screen_file($f['tmp_name'], $ext)) return $e;
  return [$f['tmp_name'], $name, $ext, $mime, (int)$f['size']];
}

function doc_get_for(int $id, array $me): ?array {
  $d = orow('SELECT * FROM documents WHERE office_id = :office AND id = :id', ['id' => $id]);
  if (!$d || !can('documents', 'view', $me)) return null;
  if ($d['matter_id']) return matter_for((int)$d['matter_id'], $me) ? $d : null;
  if ($d['contact_id'] && !can('clients', 'view', $me)) return null;
  return $d;
}
function doc_version(array $d, ?int $v = null): ?array {
  return orow('SELECT * FROM document_versions WHERE office_id = :office AND document_id = :d AND version = :v', ['d' => $d['id'], 'v' => $v ?? (int)$d['current_version']]);
}
function doc_versions(int $docId): array { return orows('SELECT * FROM document_versions WHERE office_id = :office AND document_id = :d ORDER BY version DESC', ['d' => $docId]); }

/** New document (staff). Returns ['id'] or ['error']. $actor may be a client portal user (uploaded_by_client). */
function doc_create(array $me, array $in, ?array $file, ?int $clientUploader = null): array {
  if (!files_key()) return ['error' => 'doc_no_key'];
  $title = mb_substr(trim((string)($in['title'] ?? '')), 0, 200);
  $matter = (int)($in['matter_id'] ?? 0) ?: null; $contact = (int)($in['contact_id'] ?? 0) ?: null; $cat = (int)($in['category_id'] ?? 0) ?: null;
  if ($matter && !($clientUploader ? orow('SELECT id FROM matters WHERE office_id = :office AND id = :id', ['id' => $matter]) : matter_for($matter, $me))) return ['error' => 'appt_matter_bad'];
  if ($contact && !contact_get($contact)) return ['error' => 'matter_party_contact'];
  if ($cat && !lookup_get($cat, 'doc_category')) return ['error' => 'matter_bad_list_item'];
  $u = upload_check($file); if (is_string($u)) return ['error' => $u];
  [$tmp, $name, $ext, $mime, $size] = $u;
  if (storage_over($size)) return ['error' => 'limit_storage'];
  if ($title === '') $title = mb_substr(pathinfo($name, PATHINFO_FILENAME), 0, 200);
  $stored = store_encrypted($tmp, office_id());
  $pdo = db(); $pdo->beginTransaction();
  try {
    $id = o_insert('documents', ['matter_id' => $matter, 'contact_id' => $contact, 'title' => $title, 'category_id' => $cat, 'current_version' => 1, 'source' => $clientUploader ? 'client' : 'staff',
      'portal_visible' => 0, 'archived' => 0, 'created_by' => $clientUploader ? null : $me['id'], 'created_at' => now(), 'updated_at' => now()]);
    o_insert('document_versions', ['document_id' => $id, 'version' => 1, 'stored_name' => $stored, 'orig_name' => $name, 'ext' => $ext, 'mime' => $mime, 'size' => $size,
      'sha256' => hash_file('sha256', $tmp), 'enc' => 1, 'uploaded_by' => $clientUploader ? null : $me['id'], 'uploaded_by_client' => $clientUploader, 'uploaded_at' => now()]);
    $pdo->commit();
  } catch (Throwable $e) { $pdo->rollBack(); @unlink(doc_path(office_id(), $stored)); throw $e; }
  if ($matter) matter_event($matter, $clientUploader ? ['id' => null] : $me, 'doc_add', null, ['doc' => $id, 'title' => $title, 'client' => (bool)$clientUploader]);
  law_audit('document_uploaded', 'document', $id, ['matter' => $matter, 'size' => $size, 'type' => $ext, 'by_client' => $clientUploader], null, $clientUploader ? ['id' => null, 'full_name' => 'Client portal #' . $clientUploader, 'email' => ''] : $me);
  return ['id' => $id];
}
function doc_add_version(array $me, array $d, ?array $file, string $note): ?string {
  if (!files_key()) return 'doc_no_key';
  $u = upload_check($file); if (is_string($u)) return $u;
  [$tmp, $name, $ext, $mime, $size] = $u;
  if (storage_over($size)) return 'limit_storage';
  $stored = store_encrypted($tmp, office_id()); $v = (int)$d['current_version'] + 1;
  o_insert('document_versions', ['document_id' => $d['id'], 'version' => $v, 'stored_name' => $stored, 'orig_name' => $name, 'ext' => $ext, 'mime' => $mime, 'size' => $size,
    'sha256' => hash_file('sha256', $tmp), 'enc' => 1, 'note' => mb_substr(trim($note), 0, 300) ?: null, 'uploaded_by' => $me['id'], 'uploaded_at' => now()]);
  o_update('documents', (int)$d['id'], ['current_version' => $v, 'updated_at' => now()]);
  law_audit('document_version_added', 'document', $d['id'], ['version' => $v, 'size' => $size], null, $me);
  return null;
}
function doc_update(array $me, array $d, array $in): ?string {
  $title = mb_substr(trim((string)($in['title'] ?? '')), 0, 200); if ($title === '') return 'doc_title_required';
  $cat = (int)($in['category_id'] ?? 0) ?: null; if ($cat && !lookup_get($cat, 'doc_category')) return 'matter_bad_list_item';
  $data = ['title' => $title, 'category_id' => $cat, 'updated_at' => now()];
  if (array_key_exists('portal_visible', $in) || isset($in['portal_form'])) {
    $pv = !empty($in['portal_visible']) ? 1 : 0;
    if ($pv && !$d['matter_id']) return 'doc_portal_needs_matter';
    if ($pv !== (int)$d['portal_visible'] && !can('documents', 'share', $me)) return 'forbidden';
    $data['portal_visible'] = $pv;
  }
  o_update('documents', (int)$d['id'], $data);
  if (isset($data['portal_visible']) && $data['portal_visible'] !== (int)$d['portal_visible'] && $d['matter_id']) share_mark($me, (int)$d['matter_id'], 'document', (int)$d['id'], (bool)$data['portal_visible']);
  law_audit('document_updated', 'document', $d['id'], ['portal_visible' => $data['portal_visible'] ?? null], null, $me);
  return null;
}
function doc_set_archived(array $me, array $d, bool $a): void {
  o_update('documents', (int)$d['id'], ['archived' => $a ? 1 : 0, 'portal_visible' => $a ? 0 : (int)$d['portal_visible'], 'updated_at' => now()]);
  law_audit($a ? 'document_archived' : 'document_restored', 'document', $d['id'], [], null, $me);
}
/** Send a version to the browser. $inline = preview (only safe image/PDF types). */
function doc_send(array $d, array $v, bool $inline): void {
  $inline = $inline && isset(DOC_PREVIEW[$v['ext']]);
  $fname = preg_replace('/[^\p{L}\p{N} ._-]+/u', '_', $v['orig_name']);
  header('Content-Type: ' . ($inline ? DOC_PREVIEW[$v['ext']] : 'application/octet-stream'));
  header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . "; filename=\"document.{$v['ext']}\"; filename*=UTF-8''" . rawurlencode($fname));
  header('Content-Length: ' . (int)$v['size']);
  header($v['ext'] === 'pdf' ? "Content-Security-Policy: default-src 'none'; object-src 'self'; frame-ancestors 'self'" : "Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
  header('X-Content-Type-Options: nosniff');
  if (!stream_decrypted((int)$d['office_id'], $v['stored_name'])) error_log("LAW document {$d['id']} v{$v['version']} could not be decrypted");
}

/* ---------- share links ---------- */
function share_create(array $me, array $d, int $days, ?int $maxDownloads, string $label): array {
  if ($d['archived']) return ['error' => 'doc_archived'];
  $days = max(1, min(SHARE_MAX_DAYS, $days)); $tok = token(32);
  $id = o_insert('document_shares', ['document_id' => $d['id'], 'token_hash' => keyed_hash($tok), 'label' => mb_substr(trim($label), 0, 160) ?: null,
    'expires_at' => date('Y-m-d H:i:s', time() + $days * 86400), 'max_downloads' => $maxDownloads && $maxDownloads > 0 ? min(100, $maxDownloads) : null,
    'created_by' => $me['id'], 'created_at' => now()]);
  law_audit('document_shared', 'document', $d['id'], ['share' => $id, 'days' => $days, 'max_downloads' => $maxDownloads], null, $me);
  return ['token' => $tok, 'id' => $id];
}
function share_revoke(array $me, array $d, int $shareId): bool {
  $n = oq('UPDATE document_shares SET revoked_at = :now, revoked_by = :me WHERE office_id = :office AND id = :id AND document_id = :d AND revoked_at IS NULL', ['now' => now(), 'me' => $me['id'], 'id' => $shareId, 'd' => $d['id']])->rowCount();
  if ($n) law_audit('document_share_revoked', 'document', $d['id'], ['share' => $shareId], null, $me);
  return $n > 0;
}
/** A usable share link (any office: the token identifies it). Sets the office scope. */
function share_lookup(string $tok): ?array {
  if (strlen($tok) < 40 || strlen($tok) > 60) return null;
  $s = row('SELECT * FROM document_shares WHERE token_hash=?', [keyed_hash($tok)]);
  if (!$s || $s['revoked_at'] || strtotime($s['expires_at']) < time() || ($s['max_downloads'] !== null && (int)$s['downloads'] >= (int)$s['max_downloads'])) return null;
  $o = row('SELECT status FROM offices WHERE id=?', [(int)$s['office_id']]); if (!$o || $o['status'] === 'suspended') return null;
  set_office_scope((int)$s['office_id']);
  $d = orow('SELECT * FROM documents WHERE office_id = :office AND id = :id AND archived = 0', ['id' => $s['document_id']]);
  return $d ? $s + ['doc' => $d] : null;
}
function doc_shares(int $docId): array { return orows('SELECT * FROM document_shares WHERE office_id = :office AND document_id = :d ORDER BY id DESC', ['d' => $docId]); }

/** Documents the person may see. $f: q, category, matter, contact, archived, page. */
function doc_list(array $me, array $f, int $per = 30): array {
  [$vis, $p] = linked_visible_sql($me);
  $w = ['x.office_id = :office', $vis, 'x.archived = :arch']; $p['arch'] = !empty($f['archived']) ? 1 : 0;
  if (!can('clients', 'view', $me)) $w[] = '(x.contact_id IS NULL OR x.matter_id IS NOT NULL)';
  if (!empty($f['category'])) { $w[] = 'x.category_id = :cat'; $p['cat'] = (int)$f['category']; }
  if (!empty($f['matter'])) { $w[] = 'x.matter_id = :mt'; $p['mt'] = (int)$f['matter']; }
  if (!empty($f['contact'])) { $w[] = 'x.contact_id = :ct'; $p['ct'] = (int)$f['contact']; }
  if (($q = trim((string)($f['q'] ?? ''))) !== '') { $w[] = 'x.title LIKE :q'; $p['q'] = '%' . $q . '%'; }
  $from = 'FROM documents x LEFT JOIN matters m ON m.id = x.matter_id AND m.office_id = x.office_id LEFT JOIN contacts ct ON ct.id = x.contact_id AND ct.office_id = x.office_id WHERE ' . implode(' AND ', $w);
  $total = (int)orow("SELECT COUNT(*) n $from", $p)['n'];
  $page = max(1, (int)($f['page'] ?? 1));
  return [orows("SELECT x.*, m.ref AS matter_ref, (SELECT v.ext FROM document_versions v WHERE v.office_id = x.office_id AND v.document_id = x.id AND v.version = x.current_version) AS ext, (SELECT v.size FROM document_versions v WHERE v.office_id = x.office_id AND v.document_id = x.id AND v.version = x.current_version) AS size, ct.name_ar AS ct_ar, ct.name_en AS ct_en $from ORDER BY x.updated_at DESC, x.id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $p), $total];
}
/** Bytes stored by the office (all versions). */
function storage_used(): int { return (int)orow('SELECT COALESCE(SUM(size),0) s FROM document_versions WHERE office_id = :office')['s']; }
/** Would adding $bytes go over the storage limit Al Imtyaz set for the plan? No limit = never. */
function storage_over(int $bytes): bool { $o = current_office(); $lim = $o['max_storage_mb'] ?? null; return $lim !== null && storage_used() + $bytes > (int)$lim * 1048576; }
function human_size(int $b): string { return $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : max(1, (int)round($b / 1024)) . ' KB'; }
