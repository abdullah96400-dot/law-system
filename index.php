<?php
/* ==========================================================================
   AL IMTYAZ LAW — Oman. Entry point for law.alimtyazgcc.com only.
   This folder sits inside the platform's files, so the same address on
   another host (oman., team., the company site) answers "Not found".
   ========================================================================== */
declare(strict_types=1);

$host = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
$lawHost = str_starts_with($host, 'law.');   // any other address is an office's own website domain, or "Not found"

$path = '/' . trim(rawurldecode((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH)), '/');
if (isset($_GET['r'])) $path = '/' . trim((string)$_GET['r'], '/');   // fallback when URL rewriting is off

/* static files: only from law/assets */
if (preg_match('#^/assets/([a-z0-9._-]+)$#i', $path, $m)) {
  $file = __DIR__ . '/assets/' . $m[1];
  $types = ['css' => 'text/css', 'js' => 'application/javascript', 'png' => 'image/png', 'svg' => 'image/svg+xml', 'ico' => 'image/x-icon'];
  $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
  if (is_file($file) && isset($types[$ext])) {
    header('Content-Type: ' . $types[$ext]); header('X-Content-Type-Options: nosniff'); header('Cache-Control: public, max-age=86400');
    readfile($file); exit;
  }
  http_response_code(404); exit('Not found');
}

require __DIR__ . '/app/bootstrap.php';
$GLOBALS['ROUTE'] = $path;
try { mig_auto(); } catch (Throwable $e) {
  error_log('LAW migrate: ' . $e->getMessage()); http_response_code(500);
  $sk = (string)cfg('setup_key', '');   // during installation, the person holding the setup key sees the reason
  if (strlen($sk) >= 32 && hash_equals($sk, preg_replace('/[^0-9a-zA-Z]/', '', (string)($_GET['key'] ?? $_POST['key'] ?? '')))) exit('Setup error: ' . htmlspecialchars($e->getMessage()));
  // a safe hint for everyone: names the cause, never the details
  $m = $e->getMessage(); $hint = 'See the server error log.';
  if (str_contains($m, '[1045]') || stripos($m, 'Access denied') !== false) $hint = 'The database refused the username or password. Check db_user and db_pass in config.php (lines 8 and 9). | قاعدة البيانات رفضت اسم المستخدم أو كلمة المرور: راجع السطرين 8 و9 في config.php.';
  elseif (str_contains($m, '[1049]') || stripos($m, 'Unknown database') !== false) $hint = 'The database name is wrong. Check dbname in db_dsn in config.php (line 7). | اسم قاعدة البيانات غير صحيح: راجع السطر 7 في config.php.';
  elseif (str_contains($m, '[2002]') || stripos($m, 'Connection refused') !== false) $hint = 'Cannot reach the database server. Keep host=localhost in config.php (line 7). | تعذّر الوصول إلى خادم قاعدة البيانات: اجعل host=localhost في السطر 7.';
  elseif (stripos($m, 'could not find driver') !== false) $hint = 'PHP has no MySQL driver (pdo_mysql). Enable it in hPanel → PHP configuration. | فعّل إضافة pdo_mysql من إعدادات PHP في hPanel.';
  elseif (stripos($m, 'Permission denied') !== false || stripos($m, 'failed to open') !== false) $hint = 'PHP cannot write to the storage folder. Set the storage folder permission to 755. | لا يستطيع PHP الكتابة في مجلد storage: اجعل صلاحيته 755.';
  else $hint = 'Database tables could not be created. Open this page with ?key=YOUR_SETUP_KEY to see the reason. | تعذّر إنشاء الجداول: افتح الصفحة مع مفتاح الإعداد لرؤية السبب.';
  exit('<meta charset="utf-8"><div style="font:16px system-ui;margin:40px;line-height:1.8">Setup error. ' . htmlspecialchars($hint) . '</div>');
}

if (!$lawHost) {   // an office's own domain serves only its public website
  if (is_post()) check_csrf();
  $so = site_office_by_host($host); if (!$so) { http_response_code(404); exit('Not found'); }
  site_dispatch($so, '', $path);
}
if (str_starts_with($path, '/api/v1/')) api_handle($path);   // signed server-to-server calls from the Al Imtyaz platform; no session, no CSRF

if (is_post()) check_csrf();
$me = current_staff();

/* support visits are view-only: only signing out and language change are allowed */
if (is_post() && is_support($me) && !in_array($path, ['/logout', '/lang', '/theme'], true)) deny('support_readonly');

/* read-only offices: sign-in, language, own password and signing out still work */
if (is_post() && $me && office_readonly() && !in_array($path, ['/logout', '/lang', '/theme', '/account/password', '/account/signout-others'], true)) deny('office_readonly');

/* ---------- public ---------- */
if ($path === '/setup') {   // first install without SSH: checks the server and creates the first office / demo office. Off unless setup_key is set.
  $key = (string)cfg('setup_key', ''); $given = preg_replace('/[^0-9a-zA-Z]/', '', (string)($_POST['key'] ?? $_GET['key'] ?? ''));   // copied links can carry invisible marks
  // closes by itself once the first real office's partner has signed in, even if setup_key is still in config.php
  // ('setup_keep_open' => true in config.php keeps it open, e.g. to reset a partner's sign-in)
  $closed = !cfg('setup_keep_open', false) && row("SELECT s.id FROM staff s JOIN offices o ON o.id = s.office_id WHERE o.demo = 0 AND s.role = 'partner' AND s.last_login IS NOT NULL LIMIT 1");
  if (strlen($key) < 32 || $closed) { http_response_code(404); render('error', ['title' => t('error'), 'msg' => t('not_found')]); exit; }
  if (!hash_equals($key, $given)) {   // ask for the key on the page itself: links copied from chats can lose or change it
    if ($given !== '') { usleep(400000); http_response_code(403); }
    render('setup_key', ['title' => 'Setup', 'wrong' => $given !== '']); exit;
  }
  $realOffices = (int)row('SELECT COUNT(*) n FROM offices WHERE demo = 0')['n']; $demoOffices = (int)row('SELECT COUNT(*) n FROM offices WHERE demo = 1')['n'];
  $out = null; $err = null;
  if (is_post()) {
    $do = (string)($_POST['do'] ?? '');
    if ($do === 'office' && !$realOffices) {
      $r = create_office(post('code'), post('name_ar'), post('name_en'), post('email'), post('name'), ['send_mail' => true, 'lang' => 'ar']);
      if (isset($r['error'])) $err = t($r['error']); else { $out = ['link' => abs_url('/invite/' . $r['token'])]; law_audit('setup_office', 'office', $r['office_id'], [], (int)$r['office_id'], ['id' => null, 'full_name' => 'Setup', 'email' => '']); }
    } elseif ($do === 'reinvite' && $realOffices) {   // the first partner never got in (link lost or expired): a fresh link, older ones stop working
      $st = row("SELECT s.* FROM staff s JOIN offices o ON o.id = s.office_id WHERE o.demo = 0 AND s.role = 'partner' AND s.status = 'invited' AND s.id = ?", [(int)($_POST['staff'] ?? 0)]);
      if ($st) { $prev = $GLOBALS['OFFICE_SCOPE'] ?? null; set_office_scope((int)$st['office_id']); $tok = issue_invite((int)$st['id'], null); send_invite_mail($st, $tok); set_office_scope($prev);
        law_audit('setup_reinvite', 'staff', (int)$st['id'], [], (int)$st['office_id'], ['id' => null, 'full_name' => 'Setup', 'email' => '']); $out = ['link' => abs_url('/invite/' . $tok)]; }
    } elseif ($do === 'twofa_reset' && $realOffices) {   // a partner locked out of two-step sign-in (phone lost, setup failed): back to email and password
      $st = row("SELECT s.* FROM staff s JOIN offices o ON o.id = s.office_id WHERE o.demo = 0 AND s.role = 'partner' AND s.id = ?", [(int)($_POST['staff'] ?? 0)]);
      if ($st) { q('UPDATE staff SET totp_secret=NULL, totp_last=NULL, failed_count=0, locked_until=NULL, updated_at=? WHERE id=?', [now(), $st['id']]);
        law_audit('2fa_reset_setup', 'staff', (int)$st['id'], [], (int)$st['office_id'], ['id' => null, 'full_name' => 'Setup', 'email' => '']); $err = '2FA reset for ' . $st['email'] . '. Sign in with email and password.'; }
    } elseif ($do === 'demo' && !$demoOffices) {
      if (!files_key()) $err = 'files_key';
      else { $r = demo_create('DEMO-01'); set_office_scope(null); if (isset($r['error'])) $err = t($r['error']); else $out = ['logins' => $r['logins'], 'portal' => $r['portal']]; }
    }
    $realOffices = (int)row('SELECT COUNT(*) n FROM offices WHERE demo = 0')['n']; $demoOffices = (int)row('SELECT COUNT(*) n FROM offices WHERE demo = 1')['n'];
  }
  $GLOBALS['LAYOUT'] = 'layout';
  ob_start();
  try { render('setup', ['title' => 'Setup', 'key' => $given, 'twofa' => $realOffices ? rows("SELECT s.id, s.full_name, s.email FROM staff s JOIN offices o ON o.id = s.office_id WHERE o.demo = 0 AND s.role = 'partner' AND (s.totp_secret IS NOT NULL OR s.locked_until IS NOT NULL) ORDER BY s.id") : [], 'waiting' => $realOffices ? rows("SELECT s.id, s.full_name, s.email, o.code FROM staff s JOIN offices o ON o.id = s.office_id WHERE o.demo = 0 AND s.role = 'partner' AND s.status = 'invited' ORDER BY s.id") : [], 'real' => $realOffices, 'demo' => $demoOffices, 'out' => $out, 'err' => $err]); ob_end_flush(); }
  catch (Throwable $e) {   // the key holder sees why the page failed
    ob_end_clean(); error_log('LAW setup: ' . $e->getMessage()); http_response_code(500);
    echo '<meta charset="utf-8"><div style="font:16px system-ui;margin:40px;line-height:1.8"><b>Setup page error:</b> ' . htmlspecialchars($e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')') . '</div>';
  }
  exit;
}
if ($path === '/theme' && is_post()) {   // light or dark; kept in a cookie on this device only
  $th = post('theme') === 'dark' ? 'dark' : 'light';
  setcookie('theme', $th, ['expires' => time() + 31536000, 'path' => '/', 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
  $back = post('back', '/'); redirect(preg_match('#^/[a-z0-9/_.-]*$#i', $back) ? $back : '/');
}
if ($path === '/lang' && is_post()) {
  $l = post('lang'); if (in_array($l, ['ar', 'en'], true)) { $_SESSION['lang'] = $l; if ($me) { q('UPDATE staff SET lang=? WHERE id=?', [$l, $me['id']]); } }
  $back = post('back', '/'); redirect(preg_match('#^/[a-z0-9/_-]*$#i', $back) ? $back : '/');
}
if ($path === '/login') {
  if ($me) redirect('/');
  $err = null;
  if (is_post()) {
    $r = login_attempt(post('email'), (string)($_POST['password'] ?? ''));
    if (isset($r['ok'])) redirect($r['ok'] === 'done' ? '/' : ($r['ok'] === '2fa' ? '/login/2fa' : '/login/2fa-setup'));
    $err = $r['error'];
  }
  render('login', ['title' => t('sign_in'), 'err' => $err, 'email' => post('email')]); exit;
}
if ($path === '/login/2fa') {
  $p = pending_staff(); if (!$p) { flash(t('session_expired'), 'err'); redirect('/login'); }
  $err = null;
  if (is_post()) { $err = login_2fa(post('code')); if (!$err) redirect('/'); if ($err === 'session_expired') { flash(t('session_expired'), 'err'); redirect('/login'); } }
  render('twofa', ['title' => t('2fa'), 'err' => $err]); exit;
}
if ($path === '/login/2fa-setup') {
  $p = pending_staff(); if (!$p) { flash(t('session_expired'), 'err'); redirect('/login'); }
  $err = null;
  if (is_post()) { $err = login_2fa_setup(post('code')); if (!$err) redirect('/'); if ($err === 'session_expired') { flash(t('session_expired'), 'err'); redirect('/login'); } }
  $secret = login_2fa_setup_secret();
  render('twofa_setup', ['title' => t('2fa_setup'), 'err' => $err, 'secret' => $secret, 'uri' => totp_uri($secret, $p['email'])]); exit;
}
if ($path === '/logout' && is_post()) { logout(); redirect('/login'); }
if (preg_match('#^/support/([A-Za-z0-9_-]{40,60})$#', $path, $m)) {   // Al Imtyaz support visit, opened by the office's managing partner
  $g = support_lookup($m[1]);
  if (!$g) { http_response_code(404); render('error', ['title' => t('error'), 'msg' => t('support_link_invalid')]); exit; }
  $err = null;
  if (is_post()) { $err = support_enter($m[1], (string)($_POST['pass'] ?? '')); if (!$err) redirect('/'); }
  render('support_enter', ['title' => t('support_visit'), 'err' => $err, 'g' => $g, 'office' => row('SELECT * FROM offices WHERE id=?', [(int)$g['office_id']])]); exit;
}
if (preg_match('#^/s/([A-Za-z0-9_-]{40,60})(/download)?$#', $path, $m)) {   // share link: no sign-in, the token is the permission
  $sh = share_lookup($m[1]);
  if (!$sh) { http_response_code(404); render('error', ['title' => t('error'), 'msg' => t('share_invalid')]); exit; }
  $v = doc_version($sh['doc']);
  if (!empty($m[2])) {
    oq('UPDATE document_shares SET downloads = downloads + 1 WHERE office_id = :office AND id = :id', ['id' => $sh['id']]);
    law_audit('document_share_downloaded', 'document', $sh['doc']['id'], ['share' => (int)$sh['id']], null, ['id' => null, 'full_name' => 'Share link', 'email' => '']);
    doc_send($sh['doc'], $v, false); exit;
  }
  render('share', ['title' => t('share_page_title'), 'sh' => $sh, 'v' => $v, 'office' => current_office(), 'tok' => $m[1]]); exit;
}
if ($path === '/cron/daily') {   // hPanel cron: wget -q -O /dev/null "https://law.alimtyazgcc.com/cron/daily?key=…"
  $key = (string)cfg('cron_key', '');
  if ($key === '' || !hash_equals($key, (string)($_GET['key'] ?? ''))) not_found();
  header('Content-Type: text/plain; charset=utf-8'); $r = reminders_all(); sr_purge(); if (backup_due()) backup_run();
  echo 'ok ' . count($r) . " offices\n"; exit;
}
if ($path === '/login/forgot') {   // self-service reset: the same answer whether or not the address exists
  if ($me) redirect('/');
  $sent = false;
  if (is_post()) { password_reset_request(post('email')); $sent = true; }
  render('forgot', ['title' => t('forgot_title'), 'sent' => $sent]); exit;
}
if ($path === '/backup/latest') {   // owner only: the newest encrypted backup, to keep a copy off this server (needs backup_key in config.php)
  $key = (string)cfg('backup_key', '');
  if (strlen($key) < 32 || !hash_equals($key, preg_replace('/[^0-9a-zA-Z]/', '', (string)($_GET['key'] ?? '')))) not_found();
  $files = glob(storage_dir() . '/backups/law-*.jsonl.gz.enc') ?: []; sort($files); $f = end($files);
  if (!$f) { http_response_code(404); header('Content-Type: text/plain'); exit('No backup yet. Run the daily job first.'); }
  law_audit('backup_downloaded', null, null, ['file' => basename($f)], null, ['id' => null, 'full_name' => 'Owner (backup_key)', 'email' => '']);
  header('Content-Type: application/octet-stream'); header('Content-Disposition: attachment; filename="' . basename($f) . '"'); header('Content-Length: ' . filesize($f)); header('Cache-Control: no-store');
  readfile($f); exit;
}
if (preg_match('#^/invite/([^/]{20,400})$#', $path, $m) && strlen($m[1] = preg_replace('/[^A-Za-z0-9_-]/', '', $m[1])) >= 20) {   // links copied from mail or chat can carry invisible marks
  $inv = invite_lookup($m[1]);
  if (!$inv) { http_response_code(404); render('error', ['title' => t('error'), 'msg' => t('invite_invalid')]); exit; }
  $_SESSION['lang'] ??= $inv['lang'];
  $err = null;
  if (is_post()) {
    $err = accept_invite($m[1], (string)($_POST['password'] ?? ''), (string)($_POST['confirm'] ?? ''));
    if (!$err) { logout(); flash(t('pw_set_now_sign_in')); redirect('/login'); }
  }
  render('invite', ['title' => t('set_password'), 'err' => $err, 'inv' => $inv, 'office' => row('SELECT * FROM offices WHERE id=?', [(int)$inv['office_id']])]); exit;
}

/* ---------- the office's public website: law.…/o/{code}/… ---------- */
if (preg_match('#^/o/([a-z0-9-]{3,20})(/.*)?$#i', $path, $om)) {
  $so = site_office_by_code($om[1]); if (!$so) not_found();
  site_dispatch($so, '/o/' . strtolower($so['code']), ($om[2] ?? '') === '' ? '/' : $om[2]);
}

/* ---------- client portal ---------- */
if (preg_match('#^/p/([a-z0-9-]{3,20})(/.*)?$#i', $path, $pm)) {
  $o = portal_office($pm[1]); if (!$o || !feature_on('portal', $o)) not_found();
  $GLOBALS['LAYOUT'] = 'portal_layout'; $sub = $pm[2] ?? '/'; if ($sub === '') $sub = '/';
  $base = portal_base(); $pu = current_portal_user(); portal_lang();
  if (is_post() && $o['status'] === 'readonly' && !in_array($sub, ['/login', '/code', '/logout', '/settings'], true)) { http_response_code(403); render('error', ['title' => t('error'), 'msg' => t('portal_readonly')]); exit; }
  if ($sub === '/login') {
    if ($pu) redirect($base . '/');
    if (is_post()) { portal_send_code(post('email')); redirect($base . '/code'); }
    render('portal_login', ['title' => t('portal_sign_in'), 'o' => $o, 'base' => $base]); exit;
  }
  if ($sub === '/code') {
    $err = null;
    if (is_post()) { $err = portal_verify_code(post('code')); if (!$err) redirect($base . '/'); if ($err === 'session_expired') { flash(t('session_expired'), 'err'); redirect($base . '/login'); } }
    render('portal_code', ['title' => t('portal_sign_in'), 'o' => $o, 'base' => $base, 'err' => $err]); exit;
  }
  if ($sub === '/logout' && is_post()) { portal_logout(); redirect($base . '/login'); }
  if ($sub === '/logo.jpg') { $f = office_logo_file($o); if (!$f) not_found(); header('Content-Type: image/jpeg'); header('Cache-Control: public, max-age=3600'); readfile($f); exit; }
  if (!$pu) redirect($base . '/login');
  if ($sub === '/') { render('portal_home', ['title' => t('portal_home'), 'o' => $o, 'base' => $base, 'u' => $pu, 'ov' => portal_overview($pu)]); exit; }
  if ($sub === '/settings') {
    if (is_post()) { $l = post('lang') === 'en' ? 'en' : 'ar'; oq('UPDATE portal_users SET lang = :l, notify = :n WHERE office_id = :office AND id = :id', ['l' => $l, 'n' => !empty($_POST['notify']) ? 1 : 0, 'id' => $pu['id']]); flash(t('saved')); redirect($base . '/settings'); }
    render('portal_settings', ['title' => t('portal_settings'), 'o' => $o, 'base' => $base, 'u' => $pu]); exit;
  }
  if (preg_match('#^/m/(\d+)(?:/(message|upload))?$#', $sub, $mm)) {
    $m = portal_matter($pu, (int)$mm[1]); if (!$m) not_found();
    $err = null;
    if (!empty($mm[2])) {
      if (!is_post()) not_found();
      $err = $mm[2] === 'message' ? portal_send_message($pu, $m, (string)($_POST['body'] ?? '')) : portal_upload($pu, $m, (int)post('request_id'), $_FILES['file'] ?? null);
      if ($err === 'not_found') not_found();
      if (!$err) { flash(t($mm[2] === 'message' ? 'portal_message_sent' : 'portal_upload_done')); redirect($base . '/m/' . $m['id']); }
      http_response_code(422);
    } else {
      oq('UPDATE portal_messages SET read_by_client_at = :now WHERE office_id = :office AND matter_id = :m AND staff_id IS NOT NULL AND read_by_client_at IS NULL', ['now' => now(), 'm' => $m['id']]);
      law_audit('portal_matter_viewed', 'matter', $m['id'], [], null, ['id' => null, 'full_name' => 'Client portal #' . $pu['id'], 'email' => '']);
    }
    render('portal_matter', ['title' => $m['title'], 'o' => $o, 'base' => $base, 'u' => $pu, 'm' => $m, 'err' => $err]); exit;
  }
  if (preg_match('#^/d/(\d+)$#', $sub, $mm)) {
    $d = orow('SELECT * FROM documents WHERE office_id = :office AND id = :id AND portal_visible = 1 AND archived = 0', ['id' => (int)$mm[1]]);
    if (!$d || !$d['matter_id'] || !portal_matter($pu, (int)$d['matter_id'])) not_found();
    law_audit('portal_document_downloaded', 'document', $d['id'], [], null, ['id' => null, 'full_name' => 'Client portal #' . $pu['id'], 'email' => '']);
    doc_send($d, doc_version($d), false); exit;
  }
  if (preg_match('#^/i/(\d+)$#', $sub, $mm)) {
    $i = orow("SELECT * FROM invoices WHERE office_id = :office AND id = :id AND portal_visible = 1 AND status = 'issued' AND client_id = :c", ['id' => (int)$mm[1], 'c' => $pu['contact_id']]);
    if (!$i || !portal_matter($pu, (int)$i['matter_id'])) not_found();
    law_audit('portal_invoice_viewed', 'invoice', $i['id'], [], null, ['id' => null, 'full_name' => 'Client portal #' . $pu['id'], 'email' => '']);
    pdf_send(invoice_pdf($i), $i['number'] . '.pdf');
  }
  if (in_array($sub, ['/hearings', '/actions', '/judgments', '/documents', '/payments'], true)) {
    law_audit('portal_section_viewed', null, null, ['section' => ltrim($sub, '/')], null, portal_actor($pu));
    render('portal_section', ['title' => t('ps_' . ltrim($sub, '/')), 'o' => $o, 'base' => $base, 'u' => $pu, 'sec' => ltrim($sub, '/')]); exit;
  }
  if (preg_match('#^/s/(\d+)(/pdf)?$#', $sub, $mm)) {
    $a = portal_statement($pu, (int)$mm[1]); if (!$a) not_found();
    $s = statement_data(null, 'agreement', (int)$a['id']) + ['portal' => true];
    law_audit('portal_statement_viewed', 'fee_agreement', $a['id'], ['pdf' => !empty($mm[2])], null, portal_actor($pu));
    if (!empty($mm[2])) pdf_send(statement_pdf($s), 'statement-' . $s['m']['ref'] . '.pdf');
    render('portal_statement', ['title' => t('statement'), 'o' => $o, 'base' => $base, 'u' => $pu, 's' => $s]); exit;
  }
  not_found();
}

/* ---------- signed in ---------- */
$me = require_staff();
if (is_support($me) && (str_starts_with($path, '/account') || str_starts_with($path, '/documents'))) deny();
if (is_support($me) && !is_post()) law_audit('support_viewed', 'page', mb_substr($path, 0, 40), $_GET ? ['query' => mb_substr(http_build_query($_GET), 0, 200)] : [], null, $me);
/* modules switched off by the office's plan answer "not found"; their data stays */
foreach (['/finance' => 'finance', '/reports' => 'reports', '/import' => 'import', '/settings/branches' => 'branches'] as $pfx => $feat)
  if (($path === $pfx || str_starts_with($path, $pfx . '/') || str_starts_with($path, $pfx . '.')) && !feature_on($feat)) not_found();

if ($path === '/') { render('home', ['title' => t('home'), 'me' => $me]); exit; }
if ($path === '/more') { render('more', ['title' => t('nav_more'), 'me' => $me]); exit; }

if ($path === '/account') { render('account', ['title' => t('account'), 'me' => $me, 'err' => null]); exit; }
if ($path === '/account/password' && is_post()) {
  $err = change_password($me, (string)($_POST['current'] ?? ''), (string)($_POST['password'] ?? ''), (string)($_POST['confirm'] ?? ''));
  if (!$err) { flash(t('pw_changed')); redirect('/account'); }
  render('account', ['title' => t('account'), 'me' => $me, 'err' => $err]); exit;
}
if ($path === '/account/2fa-off' && is_post()) {
  $e = twofa_off($me, (string)($_POST['current'] ?? ''));
  if ($e) { flash(t($e), 'err'); redirect('/account'); }
  flash(t('twofa_turned_off')); redirect('/account');
}
if ($path === '/account/signout-others' && is_post()) {
  $sv = (int)$me['session_ver'] + 1; q('UPDATE staff SET session_ver=? WHERE id=?', [$sv, $me['id']]); $_SESSION['sv'] = $sv;
  law_audit('signed_out_elsewhere', 'staff', $me['id']); flash(t('signed_out_elsewhere')); redirect('/account');
}

if ($path === '/staff') {
  require_perm('staff', 'view');
  $list = orows('SELECT id, email, full_name, role, status, branch_id, last_login, totp_secret IS NOT NULL AS has_2fa FROM staff WHERE office_id = :office ORDER BY status, full_name');
  render('staff', ['title' => t('staff'), 'me' => $me, 'list' => $list, 'err' => null, 'new_link' => null]); exit;
}
if ($path === '/staff/invite' && is_post()) {
  require_perm('staff', 'create');
  $r = invite_staff($me, post('email'), post('full_name'), post('role'), post('lang', 'ar'));
  if (isset($r['error'])) {
    $list = orows('SELECT id, email, full_name, role, status, branch_id, last_login, totp_secret IS NOT NULL AS has_2fa FROM staff WHERE office_id = :office ORDER BY status, full_name');
    render('staff', ['title' => t('staff'), 'me' => $me, 'list' => $list, 'err' => $r['error'], 'new_link' => null]); exit;
  }
  flash(t('invite_sent', post('email'))); redirect('/staff');
}
if (preg_match('#^/staff/(\d+)/(role|disable|enable|revoke|reset_2fa|reinvite)$#', $path, $m) && is_post()) {
  require_perm('staff', 'edit');
  $err = staff_action($me, (int)$m[1], $m[2], post('role'));
  if ($err === 'not_found') not_found();
  if ($err === 'forbidden') deny();
  if ($err) flash(t($err), 'err'); else flash(t('saved'));
  redirect('/staff');
}

if ($path === '/roles') {
  require_perm('settings', 'view');
  $mx = []; foreach (ROLES as $r) $mx[$r] = role_matrix($r);
  render('roles', ['title' => t('roles'), 'me' => $me, 'mx' => $mx, 'editable' => editable_roles($me)]); exit;
}
if (preg_match('#^/roles/([a-z_]+)$#', $path, $m) && is_post()) {
  require_perm('settings', 'edit');
  if (!in_array($m[1], editable_roles($me), true)) deny();
  $grid = is_array($_POST['p'] ?? null) ? $_POST['p'] : [];
  save_role_matrix($me, $m[1], $grid);
  flash(t('saved')); redirect('/roles#role-' . $m[1]);
}

/* ---------- reports, branches, import, export, support, help ---------- */
if ($path === '/reports') {
  require_perm('reports', 'view');
  $f = report_filters($_GET); $d = report_data($me, $f);
  if (isset($_GET['export'])) {
    if (!can('reports', 'download')) deny();
    law_audit('report_exported', 'report', 'office', ['from' => $f['from'], 'to' => $f['to']], null, $me);
    xlsx_send('report-' . $f['from'] . '-' . $f['to'] . '.xlsx', report_sheets($d));
  }
  render('reports', ['title' => t('mod_reports'), 'f' => $f, 'd' => $d]); exit;
}
if ($path === '/settings/branches') {
  require_perm('settings', 'view');
  if (is_post()) {
    require_perm('settings', 'edit');
    $id = (int)($_POST['id'] ?? 0) ?: null;
    $err = branch_save($me, $id, post('name'), $id ? !empty($_POST['active']) : true);
    if ($err === 'not_found') not_found();
    flash($err ? t($err) : t('saved'), $err ? 'err' : 'ok'); redirect('/settings/branches');
  }
  render('branches', ['title' => t('branches'), 'list' => branches(true)]); exit;
}
if (preg_match('#^/staff/(\d+)/branch$#', $path, $mm) && is_post()) {
  require_perm('staff', 'edit');
  $s2 = staff_in_office((int)$mm[1]); if (!$s2) not_found();
  if (!can_manage($me, $s2)) deny();
  $b = (int)($_POST['branch_id'] ?? 0) ?: null;
  if ($b && !orow('SELECT id FROM branches WHERE office_id = :office AND id = :id', ['id' => $b])) not_found();
  o_update('staff', (int)$s2['id'], ['branch_id' => $b, 'updated_at' => now()]);
  law_audit('staff_branch', 'staff', $s2['id'], ['branch' => $b], null, $me);
  flash(t('saved')); redirect('/staff');
}
if ($path === '/import') {
  if (!import_allowed('contacts', $me) && !import_allowed('matters', $me)) deny();
  render('import', ['title' => t('imp_title'), 'kind' => null, 'checked' => null, 'err' => null]); exit;
}
if (preg_match('#^/import/(contacts|matters)/template$#', $path, $mm)) {
  if (!import_allowed($mm[1], $me)) deny();
  xlsx_send('import-' . $mm[1] . '.xlsx', import_template($mm[1]));
}
if (preg_match('#^/import/(contacts|matters)(/confirm)?$#', $path, $mm) && is_post()) {
  $kind = $mm[1]; if (!import_allowed($kind, $me)) deny();
  if (!empty($mm[2])) {
    $job = $_SESSION['import'] ?? null; unset($_SESSION['import']);
    if (!$job || $job['kind'] !== $kind || $job['office'] !== office_id() || $job['by'] !== (int)$me['id']) { flash(t('imp_expired'), 'err'); redirect('/import'); }
    $checked = import_check($kind, $job['recs']);   // checked again: the office's data may have changed since the preview
    try { $n = import_commit($me, $kind, $checked, $job['file']); flash(t('imp_saved', $n)); }
    catch (Throwable $e) { error_log('LAW import: ' . $e->getMessage()); flash(t('imp_failed'), 'err'); }
    redirect($kind === 'contacts' ? '/clients' : '/matters');
  }
  $rows = import_read($_FILES['file'] ?? null); $err = is_string($rows) ? $rows : null; $checked = null;
  if (!$err) { [$recs, $err] = import_records($kind, $rows); if (!$err) { $checked = import_check($kind, $recs); $_SESSION['import'] = ['kind' => $kind, 'office' => office_id(), 'by' => (int)$me['id'], 'recs' => $recs, 'file' => mb_substr((string)($_FILES['file']['name'] ?? ''), 0, 120)]; } }
  render('import', ['title' => t('imp_title'), 'kind' => $kind, 'checked' => $checked, 'err' => $err]); exit;
}
if ($path === '/settings/export') {
  if ($me['role'] !== 'partner') deny();
  $err = null;
  if (is_post()) { $r = export_build($me, (string)($_POST['password'] ?? '')); if (isset($r['path'])) export_send($r); $err = $r['error']; }
  render('export', ['title' => t('export_title'), 'err' => $err]); exit;
}
if ($path === '/settings/support' || preg_match('#^/settings/support/(\d+)/revoke$#', $path, $mm)) {
  if ($me['role'] !== 'partner') deny();
  $link = null; $err = null;
  if (!empty($mm[1]) && is_post()) { if (!support_grant_revoke($me, (int)$mm[1])) not_found(); flash(t('support_revoked')); redirect('/settings/support'); }
  if (is_post()) { $r = support_grant_create($me, (int)($_POST['hours'] ?? 24), post('reason')); if (isset($r['error'])) $err = $r['error']; else $link = $r['link']; }
  render('support', ['title' => t('support_title'), 'list' => support_grants(), 'link' => $link, 'err' => $err]); exit;
}
if ($path === '/help') { render('help', ['title' => t('help')]); exit; }
if ($path === '/search') {   // one search over what the person may open: case files, clients and parties, document titles
  $q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100); $res = ['matters' => [0, []], 'clients' => [0, []], 'documents' => [0, []]];
  if (mb_strlen($q) >= 2) {
    if (can('matters', 'view')) { [$l, $n] = matter_list($me, ['q' => $q, 'state' => 'all'], 10); $res['matters'] = [$n, $l]; }
    if (can('clients', 'view')) { [$l, $n] = contact_list(['q' => $q], 10); $res['clients'] = [$n, $l]; }
    if (can('documents', 'view')) { [$l, $n] = doc_list($me, ['q' => $q], 10); $res['documents'] = [$n, $l]; }
  }
  render('search', ['title' => t('search_title'), 'q' => $q, 'res' => $res]); exit;
}

/* ---------- case files ---------- */
if ($path === '/matters') {
  require_perm('matters', 'view');
  $f = ['q' => (string)($_GET['q'] ?? ''), 'status' => (int)($_GET['status'] ?? 0), 'lead' => (int)($_GET['lead'] ?? 0), 'type' => (int)($_GET['type'] ?? 0),
        'state' => in_array($_GET['state'] ?? 'open', ['open', 'closed', 'all'], true) ? ($_GET['state'] ?? 'open') : 'open', 'page' => max(1, (int)($_GET['page'] ?? 1)),
        'sort' => in_array($_GET['sort'] ?? '', ['next', 'hearing', 'new', 'ref', 'updated'], true) ? $_GET['sort'] : 'next'];
  [$list, $total] = matter_list($me, $f);
  render('matters', ['title' => t('mod_matters'), 'list' => $list, 'total' => $total, 'f' => $f, 'pages' => max(1, (int)ceil($total / 25))]); exit;
}
if ($path === '/matters/new') {
  require_perm('matters', 'create');
  $err = null; $d = ['check_id' => (int)($_GET['check'] ?? 0), 'lead_id' => $me['id'], 'opened_on' => date('Y-m-d')];
  $fromReq = (int)($_GET['req'] ?? $_POST['from_request'] ?? 0);   // opened from a website request: prefilled, and linked back after saving
  $sreq = $fromReq && can('website', 'create', $me) ? sr_get($fromReq, $me) : null;
  if ($sreq && !is_post()) { $d['check_id'] = (int)$sreq['check_id']; $d['client_id'] = (int)$sreq['contact_id']; $d['title'] = $sreq['service_title']; $d['summary'] = (string)$sreq['message'];
    if (($_GET['as'] ?? '') === 'consult') foreach (lookups('status') as $st) if (!$st['is_closed'] && ($st['name_ar'] === 'استشارة أولية' || $st['name_en'] === 'Initial consultation')) { $d['status_id'] = (int)$st['id']; break; } }
  if (is_post()) {
    $r = matter_open($me, $_POST);
    if (isset($r['id'])) { if ($sreq) sr_link_matter($me, (int)$sreq['id'], (int)$r['id'], (int)post('check_id')); flash(t('matter_opened')); redirect('/matters/' . $r['id']); }
    $err = $r['error']; $d = $r['data'];
  }
  render('matter_form', ['title' => t('matter_new'), 'm' => null, 'd' => $d, 'err' => $err, 'fromRequest' => $sreq['id'] ?? null]); exit;
}
if (preg_match('#^/matters/(\d+)(?:/(edit|status|next|parties|team|notes)|/(parties|team)/(\d+)/remove)?$#', $path, $mm)) {
  require_perm('matters', 'view');
  $m = matter_for((int)$mm[1], $me); if (!$m) not_found();
  $act = $mm[2] ?? ''; if ($act === '' && !empty($mm[3])) $act = $mm[3] . '_remove';
  if ($act === '') {
    law_audit('matter_viewed', 'matter', $m['id'], [], null, $me);
    render('matter', ['title' => $m['ref'], 'm' => $m, 'err' => null]); exit;
  }
  if ($act === 'edit') {
    require_perm('matters', 'edit'); $err = null; $d = $m;
    if (is_post()) { $err = matter_update($me, $m, $_POST); if (!$err) { flash(t('saved')); redirect('/matters/' . $m['id']); } $d = $_POST + $m; }
    render('matter_form', ['title' => t('matter_edit'), 'm' => $m, 'd' => $d, 'err' => $err]); exit;
  }
  if (!is_post()) not_found();
  if ($act === 'notes') { if (!can('legal_notes', 'create')) deny(); $err = matter_note_add($me, $m, (string)($_POST['body'] ?? '')); }
  else {
    require_perm('matters', 'edit');
    $err = match ($act) {
      'status' => matter_set_status($me, $m, (int)post('status_id'), post('note')),
      'next' => matter_set_next($me, $m, post('next_step'), post('next_due')),
      'parties' => matter_party_add($me, $m, (int)post('contact_id'), post('role'), post('note')),
      'team' => matter_team_add($me, $m, (int)post('staff_id')),
      'parties_remove' => matter_party_remove($me, $m, (int)$mm[4]),
      'team_remove' => matter_team_remove($me, $m, (int)$mm[4]),
    };
  }
  if ($err === 'not_found') not_found();
  if ($err) { http_response_code(422); render('matter', ['title' => $m['ref'], 'm' => matter_for((int)$m['id'], $me), 'err' => $err]); exit; }
  flash(t('saved')); redirect('/matters/' . $m['id'] . '#' . ($act === 'notes' ? 'timeline' : preg_replace('/_remove$/', '', $act)));
}

/* ---------- calendar ---------- */
if ($path === '/calendar') {
  require_perm('calendar', 'view');
  $view = in_array($_GET['view'] ?? 'week', ['day', 'week', 'month', 'list'], true) ? ($_GET['view'] ?? 'week') : 'week';
  $d = valid_date($_GET['d'] ?? null) ?? date('Y-m-d');
  if ($view === 'day') { $from = $d; $to = date('Y-m-d', strtotime("$d +1 day")); }
  elseif ($view === 'week') { $dow = (int)date('w', strtotime($d)); $from = date('Y-m-d', strtotime("$d -" . (($dow - country_week_start() + 7) % 7) . ' days')); $to = date('Y-m-d', strtotime("$from +7 days")); }   // weeks start on Saturday
  elseif ($view === 'month') { $from = date('Y-m-01', strtotime($d)); $to = date('Y-m-d', strtotime("$from +1 month")); }
  else { $from = $d; $to = date('Y-m-d', strtotime("$d +60 days")); }
  $f = ['lead' => (int)($_GET['lead'] ?? 0), 'kind' => (string)($_GET['kind'] ?? '')];
  render('calendar', ['title' => t('mod_calendar'), 'view' => $view, 'd' => $d, 'from' => $from, 'to' => $to, 'f' => $f, 'list' => appt_range($me, $from, $to, $f)]); exit;
}
if ($path === '/calendar/new' || preg_match('#^/calendar/(\d+)/edit$#', $path, $mm)) {
  $old = isset($mm[1]) ? appt_get_for((int)$mm[1], $me) : null; if (isset($mm[1]) && !$old) not_found();
  require_perm('calendar', $old ? 'edit' : 'create');
  $err = null; $d = $old ? $old + ['date' => substr($old['starts_at'], 0, 10), 'time' => substr($old['starts_at'], 11, 5)] : ['matter_id' => (int)($_GET['matter'] ?? 0), 'kind' => in_array($_GET['kind'] ?? '', APPT_KINDS, true) ? $_GET['kind'] : 'hearing', 'lead_id' => $me['id'], 'remind_days' => '1,3', 'date' => valid_date($_GET['d'] ?? null) ?? ''];
  if (!$old && !empty($_GET['title'])) $d['title'] = mb_substr((string)$_GET['title'], 0, 200);
  if (!$old && $d['matter_id'] && ($m0 = matter_for((int)$d['matter_id'], $me))) $d += ['title' => $m0['title'], 'court_id' => $m0['court_id'], 'circuit' => $m0['circuit'], 'lead_id' => $m0['lead_id']];
  if (is_post()) { $r = appt_save($me, $_POST, $old); if (isset($r['id'])) { flash(t('saved')); redirect('/calendar/' . $r['id']); } $err = $r['error']; $d = $_POST + $d; }
  render('appt_form', ['title' => t($old ? 'appt_edit' : 'appt_new'), 'a' => $old, 'd' => $d, 'err' => $err]); exit;
}
if (preg_match('#^/calendar/(\d+)(?:/(review|outcome))?$#', $path, $mm)) {
  require_perm('calendar', 'view');
  $a = appt_get_for((int)$mm[1], $me); if (!$a) not_found();
  $err = null;
  if (($mm[2] ?? '') !== '') {
    if (!is_post()) not_found();
    require_perm('calendar', 'edit');
    if ($mm[2] === 'review') $err = appt_review($me, $a);
    else { $r = appt_outcome_full($me, $a, $_POST, $_FILES['file'] ?? null); $err = $r['error'] ?? null; if (!$err && !empty($r['doc_error'])) flash(t($r['doc_error']), 'err'); if (!$err && $r['new']) { flash(t('appt_outcome_saved_new')); redirect('/calendar/' . $r['new']); } }
    if (!$err) { flash(t('saved')); redirect('/calendar/' . $a['id']); }
    http_response_code(422); $a = appt_get_for((int)$a['id'], $me);
  }
  render('appt', ['title' => $a['title'], 'a' => $a, 'err' => $err]); exit;
}

/* ---------- tasks ---------- */
if ($path === '/tasks') {
  $me = require_staff();
  $f = ['scope' => ($_GET['scope'] ?? 'mine') === 'all' && can('tasks', 'view') ? 'all' : 'mine', 'state' => in_array($_GET['state'] ?? 'open', ['open', 'done', 'overdue'], true) ? ($_GET['state'] ?? 'open') : 'open', 'page' => max(1, (int)($_GET['page'] ?? 1))];
  [$list, $total] = task_list($me, $f);
  render('tasks', ['title' => t('mod_tasks'), 'list' => $list, 'total' => $total, 'f' => $f]); exit;
}
if ($path === '/tasks/new' || preg_match('#^/tasks/(\d+)/edit$#', $path, $mm)) {
  $old = isset($mm[1]) ? task_get_for((int)$mm[1], $me) : null; if (isset($mm[1]) && !$old) not_found();
  require_perm('tasks', $old ? 'edit' : 'create');
  $err = null; $d = $old ?? ['matter_id' => (int)($_GET['matter'] ?? 0), 'assignee_id' => $me['id'], 'priority' => 'normal', 'recur' => 'none'];
  if (is_post()) { $r = task_save($me, $_POST, $old); if (isset($r['id'])) { flash(t('saved')); redirect('/tasks/' . $r['id']); } $err = $r['error']; $d = $_POST + $d; }
  render('task_form', ['title' => t($old ? 'task_edit' : 'task_new'), 't' => $old, 'd' => $d, 'err' => $err]); exit;
}
if (preg_match('#^/tasks/(\d+)(?:/(done|status))?$#', $path, $mm)) {
  $t = task_get_for((int)$mm[1], $me); if (!$t) not_found();
  $err = null;
  if (($mm[2] ?? '') !== '') {
    if (!is_post()) not_found();
    if ((int)$t['assignee_id'] !== (int)$me['id'] && !can('tasks', 'edit')) deny();
    $err = $mm[2] === 'done' ? task_complete($me, $t, (string)($_POST['note'] ?? '')) : task_set_status($me, $t, post('status'));
    if (!$err) { flash(t('saved')); redirect('/tasks/' . $t['id']); }
    http_response_code(422); $t = task_get_for((int)$t['id'], $me);
  }
  render('task', ['title' => $t['title'], 't' => $t, 'err' => $err]); exit;
}
if (preg_match('#^/matters/(\d+)/template$#', $path, $mm) && is_post()) {
  require_perm('tasks', 'create');
  $m = matter_for((int)$mm[1], $me); if (!$m) not_found();
  $err = tpl_apply($me, $m, (int)post('template_id'), (int)post('assignee_id') ?: null);
  if ($err === 'not_found') not_found();
  flash(t('tpl_applied')); redirect('/matters/' . $m['id'] . '#tasks');
}

/* ---------- documents ---------- */
if ($path === '/documents') {
  require_perm('documents', 'view');
  $f = ['q' => (string)($_GET['q'] ?? ''), 'category' => (int)($_GET['category'] ?? 0), 'archived' => !empty($_GET['archived']), 'page' => max(1, (int)($_GET['page'] ?? 1))];
  [$list, $total] = doc_list($me, $f);
  render('documents', ['title' => t('mod_documents'), 'list' => $list, 'total' => $total, 'f' => $f]); exit;
}
if ($path === '/documents/upload') {
  require_perm('documents', 'create');
  $err = null; $d = ['matter_id' => (int)($_GET['matter'] ?? 0), 'contact_id' => (int)($_GET['contact'] ?? 0)];
  if (is_post()) { $r = doc_create($me, $_POST, $_FILES['file'] ?? null); if (isset($r['id'])) { flash(t('doc_uploaded')); redirect('/documents/' . $r['id']); } $err = $r['error']; $d = $_POST + $d; }
  render('doc_upload', ['title' => t('doc_upload'), 'd' => $d, 'err' => $err]); exit;
}
if (preg_match('#^/documents/(\d+)(?:/(download|preview|version|edit|share|archive|restore)|/shares/(\d+)/revoke)?$#', $path, $mm)) {
  $d = doc_get_for((int)$mm[1], $me); if (!$d) not_found();
  $act = $mm[2] ?? ''; if ($act === '' && !empty($mm[3])) $act = 'revoke';
  if ($act === '') { law_audit('document_viewed', 'document', $d['id'], [], null, $me); render('document', ['title' => $d['title'], 'd' => $d, 'err' => null, 'link' => null]); exit; }
  if ($act === 'download' || $act === 'preview') {
    require_perm('documents', 'download');
    $v = doc_version($d, isset($_GET['v']) ? (int)$_GET['v'] : null); if (!$v) not_found();
    law_audit($act === 'download' ? 'document_downloaded' : 'document_previewed', 'document', $d['id'], ['version' => (int)$v['version']], null, $me);
    doc_send($d, $v, $act === 'preview'); exit;
  }
  if (!is_post()) not_found();
  $err = null; $link = null;
  switch ($act) {
    case 'version': require_perm('documents', 'edit'); $err = doc_add_version($me, $d, $_FILES['file'] ?? null, post('note')); break;
    case 'edit': require_perm('documents', 'edit'); $err = doc_update($me, $d, $_POST); if ($err === 'forbidden') deny(); break;
    case 'share': require_perm('documents', 'share'); $r = share_create($me, $d, (int)post('days', '7'), (int)post('max_downloads') ?: null, post('label')); $err = $r['error'] ?? null; $link = isset($r['token']) ? abs_url('/s/' . $r['token']) : null; break;
    case 'revoke': require_perm('documents', 'share'); if (!share_revoke($me, $d, (int)$mm[3])) not_found(); break;
    case 'archive': case 'restore': require_perm('documents', 'delete'); doc_set_archived($me, $d, $act === 'archive'); break;
  }
  if ($err || $link) { if ($err) http_response_code(422); render('document', ['title' => $d['title'], 'd' => doc_get_for((int)$d['id'], $me), 'err' => $err, 'link' => $link]); exit; }
  flash(t('saved')); redirect('/documents/' . $d['id']);
}

/* ---------- fees and money ---------- */
if ($path === '/finance') {
  require_perm('finance', 'view');
  $from = valid_date($_GET['from'] ?? null) ?? date('Y-m-01'); $to = valid_date($_GET['to'] ?? null) ?? date('Y-m-d', strtotime(date('Y-m-01') . ' +1 month'));
  [$overdue] = invoice_list($me, ['state' => 'overdue'], 20);
  render('finance', ['title' => t('mod_finance'), 'tot' => finance_totals($me, $from, $to), 'from' => $from, 'to' => $to, 'overdue' => $overdue]); exit;
}
if ($path === '/finance/invoices' || $path === '/finance/invoices.xlsx') {
  require_perm('finance', 'view');
  $f = ['state' => (string)($_GET['state'] ?? ''), 'matter' => (int)($_GET['matter'] ?? 0), 'client' => (int)($_GET['client'] ?? 0), 'from' => valid_date($_GET['from'] ?? null), 'to' => valid_date($_GET['to'] ?? null), 'page' => max(1, (int)($_GET['page'] ?? 1))];
  if ($path === '/finance/invoices.xlsx') {
    require_perm('finance', 'download');
    [$rows] = invoice_list($me, $f + ['page' => 1], 10000);
    law_audit('finance_exported', null, null, ['what' => 'invoices', 'rows' => count($rows)], null, $me);
    $out = [[t('inv_number'), t('inv_issued_on'), t('inv_due_on'), t('ct_client'), t('appt_matter'), t('status'), t('inv_total') . ' (OMR)', t('inv_paid') . ' (OMR)', t('inv_balance') . ' (OMR)']];
    foreach ($rows as $r) $out[] = [$r['number'] ?? t('inv_draft'), $r['issued_on'], $r['due_on'], lang() === 'ar' ? ($r['name_ar'] ?: $r['name_en']) : ($r['name_en'] ?: $r['name_ar']), $r['matter_ref'], t('is_' . invoice_state($r)), $r['total'] / 1000, $r['paid'] / 1000, ($r['total'] - $r['paid']) / 1000];
    xlsx_send('invoices-' . date('Ymd') . '.xlsx', [t('invoices') => $out]);
  }
  [$list, $sum] = invoice_list($me, $f);
  render('invoices', ['title' => t('invoices'), 'list' => $list, 'sum' => $sum, 'f' => $f]); exit;
}
if (preg_match('#^/matters/(\d+)/(invoice|agreement|expense)$#', $path, $mm)) {
  if (!feature_on('finance')) not_found();
  $m = finance_matter((int)$mm[1], $me); if (!$m) { require_perm('finance', 'view'); not_found(); }
  require_perm('finance', 'create');
  if ($mm[2] === 'invoice' && is_post()) { $r = invoice_create($me, $m, (int)post('installment_id') ?: null); redirect('/finance/invoices/' . $r['id']); }
  $err = null;
  if ($mm[2] === 'agreement') {
    if (is_post()) { $r = agreement_save($me, $m, $_POST); if (isset($r['id'])) { flash(t('saved')); redirect('/finance/agreements/' . $r['id']); } $err = $r['error']; }
    render('agreement_form', ['title' => t('fee_new'), 'm' => $m, 'a' => null, 'd' => $_POST, 'err' => $err]); exit;
  }
  if ($mm[2] === 'expense') {
    if (is_post()) { $r = expense_add($me, $m, $_POST); if (isset($r['id'])) { flash(t('saved')); redirect('/matters/' . $m['id'] . '#finance'); } $err = $r['error']; }
    render('expense_form', ['title' => t('exp_new'), 'm' => $m, 'd' => $_POST, 'err' => $err]); exit;
  }
  not_found();
}
if (preg_match('#^/finance/agreements/(\d+)/(?:installments/(\d+)/(pay|notice)|statement(?:/(share|unshare))?)$#', $path, $mm)) {
  require_perm('finance', 'view');
  $a = agreement_get_for((int)$mm[1], $me); if (!$a) not_found();
  if (!empty($mm[3])) {
    if (!is_post()) not_found(); if (office_readonly()) deny();
    if ($mm[3] === 'pay') {
      require_perm('finance', 'create'); require_perm('finance', 'edit');
      $r = installment_pay($me, $a, (int)$mm[2], $_POST);
      if (isset($r['id'])) { flash(t('pay_saved_receipt')); redirect('/finance/agreements/' . $a['id'] . '#schedule'); }
      if ($r['error'] === 'not_found') not_found(); flash(t($r['error']), 'err'); redirect('/finance/agreements/' . $a['id'] . '#schedule');
    }
    require_perm('finance', 'create');
    $r = notice_create($me, $a, (int)$mm[2], null);
    if (isset($r['id'])) redirect('/finance/notices/' . $r['id']);
    if ($r['error'] === 'not_found') not_found(); flash(t($r['error']), 'err'); redirect('/finance/agreements/' . $a['id'] . '#schedule');
  }
  if (!empty($mm[4])) {
    if (!is_post()) not_found(); if (office_readonly()) deny();
    $err = statement_share($me, $a, $mm[4] === 'share'); if ($err === 'act_share_forbidden') deny();
    flash(t($mm[4] === 'share' ? 'st_shared' : 'saved')); redirect('/finance/agreements/' . $a['id'] . '/statement');
  }
  $s = statement_data($me, 'agreement', (int)$a['id']);
  if (!empty($_GET['pdf'])) { require_perm('finance', 'download'); law_audit('statement_pdf', 'fee_agreement', $a['id'], [], null, $me); pdf_send(statement_pdf($s), 'statement-' . $s['m']['ref'] . '.pdf'); }
  render('statement', ['title' => t('statement'), 's' => $s]); exit;
}
if (preg_match('#^/finance/clients/(\d+)/statement$#', $path, $mm)) {
  require_perm('finance', 'view');
  $s = statement_data($me, 'client', (int)$mm[1]); if (!$s) not_found();
  if (!empty($_GET['pdf'])) { require_perm('finance', 'download'); law_audit('statement_pdf', 'contact', (int)$mm[1], [], null, $me); pdf_send(statement_pdf($s), 'statement-client-' . (int)$mm[1] . '.pdf'); }
  render('statement', ['title' => t('statement'), 's' => $s]); exit;
}
if (preg_match('#^/finance/invoices/(\d+)/notice$#', $path, $mm) && is_post()) {
  require_perm('finance', 'create'); if (office_readonly()) deny();
  $r = notice_create($me, null, null, (int)$mm[1]);
  if (isset($r['id'])) redirect('/finance/notices/' . $r['id']);
  flash(t($r['error']), 'err'); redirect('/finance/invoices/' . (int)$mm[1]);
}
if ($path === '/finance/notices') {
  require_perm('finance', 'view');
  $st = in_array($_GET['status'] ?? 'draft', ['draft', 'sent', 'all'], true) ? ($_GET['status'] ?? 'draft') : 'draft';
  render('notices', ['title' => t('notices'), 'list' => notice_list($me, $st), 'st' => $st]); exit;
}
if (preg_match('#^/finance/notices/(\d+)(?:/(send|discard|pdf))?$#', $path, $mm)) {
  require_perm('finance', 'view');
  $n = notice_get((int)$mm[1], $me); if (!$n) not_found();
  $act = $mm[2] ?? '';
  if ($act === 'pdf') { require_perm('finance', 'download'); law_audit('notice_pdf', 'due_notice', $n['id'], [], null, $me); pdf_send(notice_pdf($n), 'notice-' . $n['id'] . '.pdf'); }
  if ($act !== '') {
    if (!is_post()) not_found(); require_perm('finance', 'create'); if (office_readonly()) deny();
    $err = $act === 'send' ? notice_send($me, $n, post('channel'), post('note')) : notice_discard($me, $n);
    flash($err ? t($err) : t($act === 'send' ? 'notice_sent_ok' : 'notice_discarded'), $err ? 'err' : 'ok'); redirect('/finance/notices/' . $n['id']);
  }
  render('notice', ['title' => t('notice_title'), 'n' => $n]); exit;
}
if ($path === '/finance/reports') {
  require_perm('finance', 'view');
  $r = in_array($_GET['view'] ?? 'contracts', ['contracts', 'aging', 'monthly', 'pl'], true) ? ($_GET['view'] ?? 'contracts') : 'contracts';
  $year = max(2000, min(2100, (int)($_GET['year'] ?? date('Y'))));
  render('fin_reports', ['title' => t('fin_reports'), 'r' => $r, 'year' => $year, 'data' => fin_report($me, $r === 'pl' ? 'monthly' : $r, $year)]); exit;
}
if ($path === '/office/logo.jpg') {
  $me = require_staff(); $f = office_logo_file(); if (!$f) not_found();
  header('Content-Type: image/jpeg'); header('Cache-Control: private, max-age=3600'); readfile($f); exit;
}
if ($path === '/settings/logo' && is_post()) {
  require_perm('settings', 'edit'); if (office_readonly()) deny();
  $err = office_logo_save($me, $_FILES['logo'] ?? null);
  flash($err ? t($err) : t('saved'), $err ? 'err' : 'ok'); redirect('/settings#office-logo');
}
if (preg_match('#^/finance/agreements/(\d+)(?:/(edit|installment))?$#', $path, $mm)) {
  require_perm('finance', 'view');
  $a = agreement_get_for((int)$mm[1], $me); if (!$a) not_found();
  $m = matter_for((int)$a['matter_id'], $me); $err = null;
  if (($mm[2] ?? '') === 'edit') { require_perm('finance', 'edit'); if (is_post()) { $r = agreement_save($me, $m, $_POST, $a); if (isset($r['id'])) { flash(t('saved')); redirect('/finance/agreements/' . $a['id']); } $err = $r['error']; }
    render('agreement_form', ['title' => t('fee_edit'), 'm' => $m, 'a' => $a, 'd' => is_post() ? $_POST : $a, 'err' => $err]); exit; }
  if (($mm[2] ?? '') === 'installment' && is_post()) { require_perm('finance', 'edit'); $err = installment_add($me, $a, post('label'), post('due_on'), post('amount')); if (!$err) { flash(t('saved')); redirect('/finance/agreements/' . $a['id']); } }
  render('agreement', ['title' => t('fee_agreement'), 'a' => $a, 'm' => $m, 'err' => $err]); exit;
}
if (preg_match('#^/finance/invoices/(\d+)(?:/(pdf|line|lines/(\d+)/remove|expenses|draft|issue|void|delete|payment|payments/(\d+)/void|payments/(\d+)/receipt|portal))?$#', $path, $mm)) {
  require_perm('finance', 'view');
  $i = invoice_get_for((int)$mm[1], $me); if (!$i) not_found();
  $act = $mm[2] ?? '';
  if ($act === 'pdf') { require_perm('finance', 'download'); law_audit('invoice_pdf', 'invoice', $i['id'], [], null, $me); pdf_send(invoice_pdf($i), ($i['number'] ?: 'draft-' . $i['id']) . '.pdf'); }
  if (!empty($mm[5])) { require_perm('finance', 'download'); $p = orow('SELECT * FROM payments WHERE office_id = :office AND id = :id AND invoice_id = :i', ['id' => (int)$mm[5], 'i' => $i['id']]); if (!$p) not_found(); pdf_send(invoice_pdf($i, $p), $p['receipt_no'] . '.pdf'); }
  if ($act === '') { render('invoice', ['title' => $i['number'] ?: t('inv_draft'), 'i' => $i, 'err' => null]); exit; }
  if (!is_post()) not_found();
  $err = null;
  if ($act === 'payment' || !empty($mm[4])) { require_perm('finance', 'create'); $err = !empty($mm[4]) ? payment_void($me, $i, (int)$mm[4], post('reason')) : (payment_add($me, $i, $_POST)['error'] ?? null); }
  elseif ($act === 'delete') { require_perm('finance', 'delete'); $err = invoice_delete_draft($me, $i); if (!$err) { flash(t('inv_draft_deleted')); redirect('/matters/' . $i['matter_id'] . '#finance'); } }
  else {
    require_perm('finance', 'edit');
    $err = match (true) {
      $act === 'line' => invoice_add_line($me, $i, post('kind'), post('description'), post('qty', '1'), post('unit')),
      !empty($mm[3]) => invoice_remove_line($me, $i, (int)$mm[3]),
      $act === 'expenses' => (invoice_add_expenses($me, $i) >= 0 ? null : null),
      $act === 'draft' => invoice_update_draft($me, $i, $_POST),
      $act === 'issue' => invoice_issue($me, $i),
      $act === 'void' => invoice_void($me, $i, post('reason')),
      $act === 'portal' => invoice_set_portal($me, $i, !empty($_POST['on'])),
      default => 'not_found',
    };
  }
  if ($err === 'not_found') not_found();
  if ($err) { http_response_code(422); render('invoice', ['title' => $i['number'] ?: t('inv_draft'), 'i' => invoice_get_for((int)$i['id'], $me), 'err' => $err]); exit; }
  flash(t('saved')); redirect('/finance/invoices/' . $i['id']);
}
if ($path === '/finance/trust') {
  require_perm('finance', 'view');
  $rows = orows("SELECT c.id, c.name_ar, c.name_en, SUM(CASE WHEN t.kind = 'in' THEN t.amount ELSE -t.amount END) bal, MAX(t.on_date) last FROM trust_entries t JOIN contacts c ON c.id = t.client_id AND c.office_id = t.office_id WHERE t.office_id = :office GROUP BY c.id, c.name_ar, c.name_en ORDER BY bal DESC");
  render('trust', ['title' => t('trust'), 'rows' => $rows, 'client' => null, 'err' => null]); exit;
}
if (preg_match('#^/finance/trust/(\d+)$#', $path, $mm)) {
  require_perm('finance', 'view');
  $c = contact_get((int)$mm[1]); if (!$c) not_found(); $err = null;
  if (is_post()) { require_perm('finance', 'create'); $r = trust_add($me, (int)$c['id'], (int)post('matter_id') ?: null, post('kind'), post('amount'), post('on_date'), post('description'), post('reference')); if (isset($r['id'])) { flash(t('saved')); redirect('/finance/trust/' . $c['id']); } $err = $r['error']; }
  render('trust', ['title' => t('trust'), 'rows' => [], 'client' => $c, 'err' => $err]); exit;
}

/* ---------- notifications ---------- */
if ($path === '/notifications') {
  $list = notif_list($me);
  render('notifications', ['title' => t('notifications'), 'list' => $list]);
  notif_read_all($me); exit;
}

/* ---------- office settings ---------- */
/* ---------- the office website: management (draft, preview, publish) ---------- */
if ($path === '/site' || str_starts_with($path, '/site/')) {
  $me = require_staff(); if (!feature_on('website')) not_found();
  if (str_starts_with($path, '/site/preview')) {   // staff see the draft exactly as visitors would
    if (!can('website', 'share', $me) && !can('website', 'view', $me)) deny();
    $sub = substr($path, strlen('/site/preview')); site_dispatch(current_office(), '/site/preview', $sub === '' ? '/' : $sub, true);
  }
  if (!site_can_manage($me)) deny();
  if (is_post() && office_readonly()) deny('office_readonly');
  if ($path === '/site') {
    render('site_admin', ['title' => t('site_admin'), 'c' => site_content('draft'), 'row' => site_row(), 'domains' => site_domains(), 'err' => null]); exit;
  }
  if ($path === '/site/general' && is_post()) {
    $e = site_update_general($me, $_POST); if ($e) { flash(t($e), 'err'); redirect('/site#general'); }
    flash(t('site_draft_saved')); redirect('/site#general');
  }
  if (preg_match('#^/site/(lawyers|services)/(new|[a-f0-9]{6,16})$#', $path, $sm)) {
    $c = site_content('draft'); $key = $sm[2] === 'new' ? null : $sm[2]; $it = $key ? site_item($c, $sm[1], $key) : ['show' => 1]; if ($key && !$it) not_found();
    $err = null;
    if (is_post()) { [$k, $err] = site_item_save($me, $sm[1], $key, $_POST); if (!$err) { flash(t('site_draft_saved')); redirect('/site#' . $sm[1]); } $it = $_POST + ['key' => $key]; }
    render('site_item', ['title' => t($sm[1] === 'lawyers' ? 'site_lawyer' : 'site_service'), 'kind' => $sm[1], 'it' => $it, 'key' => $key, 'err' => $err]); exit;
  }
  if (preg_match('#^/site/(lawyers|services)/([a-f0-9]{6,16})/(up|down|remove)$#', $path, $sm) && is_post()) { site_item_move($me, $sm[1], $sm[2], $sm[3]); flash(t('site_draft_saved')); redirect('/site#' . $sm[1]); }
  if ($path === '/site/publish' && is_post()) { $e = site_publish($me); flash(t($e ?: 'site_published_ok'), $e ? 'err' : 'ok'); redirect('/site'); }
  if ($path === '/site/unpublish' && is_post()) { site_unpublish($me); flash(t('site_unpublished_ok')); redirect('/site'); }
  if ($path === '/site/discard' && is_post()) { oq('UPDATE office_sites SET draft = live WHERE office_id = :office AND live IS NOT NULL', []); law_audit('site_draft_discarded', 'office_site', office_id(), [], null, $me); flash(t('site_draft_discarded')); redirect('/site'); }
  if ($path === '/site/domains' && is_post()) {
    if ($me['role'] !== 'partner') deny();
    $e = site_domain_add($me, post('host')); flash(t($e ?: 'site_domain_added'), $e ? 'err' : 'ok'); redirect('/site#domains');
  }
  if (preg_match('#^/site/domains/(\d+)/remove$#', $path, $sm) && is_post()) { if ($me['role'] !== 'partner') deny(); site_domain_remove($me, (int)$sm[1]); flash(t('saved')); redirect('/site#domains'); }
  not_found();
}

/* ---------- requests from the website ---------- */
if ($path === '/requests') {
  $me = require_perm('website', 'view'); if (!feature_on('website')) not_found();
  $f = ['status' => (string)($_GET['status'] ?? ''), 'service' => (string)($_GET['service'] ?? ''), 'assignee' => (string)($_GET['assignee'] ?? ''), 'page' => (int)($_GET['page'] ?? 1)];
  [$list, $total] = sr_list($me, $f);
  render('requests', ['title' => t('sr_title'), 'list' => $list, 'total' => $total, 'f' => $f, 'counts' => sr_counts($me), 'services' => site_content('live')['services'] ?: site_content('draft')['services'], 'staff' => active_staff()]); exit;
}
if (preg_match('#^/requests/(\d+)(?:/(status|assign|note|contact|check))?$#', $path, $rm)) {
  $me = require_perm('website', 'view'); if (!feature_on('website')) not_found();
  $r = sr_get((int)$rm[1], $me); if (!$r) not_found();
  $act = $rm[2] ?? '';
  if ($act !== '') {
    if (!is_post()) not_found();
    if (office_readonly()) deny('office_readonly');
    if ($act === 'status') { $e = sr_set_status($me, $r, post('status')); }
    elseif ($act === 'assign') { $e = sr_assign($me, $r, (int)post('assignee')); }
    elseif ($act === 'note') { $e = sr_note($me, $r, post('body')); }
    elseif ($act === 'check') { $res = sr_start_check($me, $r); $e = $res['error'] ?? null; if (!$e) { flash(t('sr_check_started')); redirect('/conflicts/' . $res['id']); } }
    else {
      $res = sr_link_contact($me, $r, (int)post('contact_id'), post('mode') === 'new', post('confirm') === '1');
      if (isset($res['dups'])) { render('request', ['title' => $r['ref'], 'r' => $r, 'notes' => sr_notes((int)$r['id']), 'staff' => active_staff(), 'dups' => $res['dups']]); exit; }
      $e = $res['error'] ?? null;
    }
    if ($e) { flash(t($e), 'err'); } else flash(t('saved'));
    redirect('/requests/' . $r['id']);
  }
  render('request', ['title' => $r['ref'], 'r' => $r, 'notes' => sr_notes((int)$r['id']), 'staff' => active_staff(), 'dups' => null]); exit;
}

if ($path === '/settings') {
  if (!can('staff', 'view') && !can('settings', 'view') && !can('audit', 'view')) deny();
  render('settings', ['title' => t('office_settings'), 'o' => current_office(), 'tpls' => tpl_list(true)]); exit;
}
if ($path === '/settings/finance' && is_post()) {
  require_perm('settings', 'edit'); require_perm('finance', 'edit');
  $rate = trim(post('tax_rate')) === '' ? 0 : (int)round((float)str_replace('٫', '.', digits_only_keep(post('tax_rate'))) * 100);
  if ($rate < 0 || $rate > 5000) { flash(t('tax_rate_bad'), 'err'); redirect('/settings#finance'); }
  $nd = implode(',', array_slice(array_values(array_unique(array_filter(array_map('intval', preg_split('/[,،\s]+/u', digits_only_keep(post('notice_days')))), fn($x) => $x >= 0 && $x <= 60))), 0, 3)) ?: null;
  q('UPDATE offices SET tax_rate_bp=?, tax_label=?, tax_no=?, invoice_footer=?, notice_days=?, updated_at=? WHERE id=?', [$rate, mb_substr(post('tax_label'), 0, 60) ?: null, mb_substr(post('tax_no'), 0, 60) ?: null, mb_substr(post('invoice_footer'), 0, 600) ?: null, $nd, now(), office_id()]);
  law_audit('finance_settings', 'office', office_id(), ['tax_rate_bp' => $rate], null, $me);
  flash(t('saved')); redirect('/settings#finance');
}
if ($path === '/settings/office' && is_post()) {
  require_perm('settings', 'edit');
  $esc = post('escalate_days') === '' ? null : max(1, min(60, (int)post('escalate_days')));
  $all2fa = !empty($_POST['require_2fa_all']) ? 1 : 0;
  q('UPDATE offices SET escalate_days=?, require_2fa_all=?, updated_at=? WHERE id=?', [$esc, $all2fa, now(), office_id()]);
  law_audit('office_settings', 'office', office_id(), ['escalate_days' => $esc, 'require_2fa_all' => $all2fa], null, $me);
  flash(t('saved')); redirect('/settings');
}
if (preg_match('#^/settings/templates/(new|\d+)$#', $path, $mm) && is_post()) {
  require_perm('settings', 'edit');
  $err = tpl_save($me, $mm[1] === 'new' ? null : (int)$mm[1], post('name'), (string)($_POST['items'] ?? ''), !empty($_POST['active']) || $mm[1] === 'new');
  if ($err === 'not_found') not_found();
  if ($err) flash(t($err), 'err'); else flash(t('saved'));
  redirect('/settings#templates');
}

/* ---------- portal (staff side) ---------- */
if (preg_match('#^/clients/(\d+)/portal/(invite|disable)$#', $path, $mm) && is_post()) {
  require_perm('clients', 'edit');
  $c = contact_get((int)$mm[1]); if (!$c) not_found();
  $err = $mm[2] === 'invite' ? portal_invite($me, $c) : portal_disable($me, $c);
  if ($err === 'not_found') not_found();
  flash($err ? t($err) : t($mm[2] === 'invite' ? 'portal_invite_sent' : 'saved'), $err ? 'err' : 'ok'); redirect('/clients/' . $c['id'] . '#portal');
}
if (preg_match('#^/matters/(\d+)/portal-preview$#', $path, $mm)) {
  require_perm('matters', 'view'); if (is_support($me)) deny();
  $m = matter_for((int)$mm[1], $me); if (!$m) not_found();
  $cps = array_values(array_filter(matter_parties((int)$m['id']), fn($p) => in_array($p['role'], ['client', 'co_client'], true)));
  $cid = (int)($_GET['contact'] ?? $m['client_id']); $cp = null; foreach ($cps as $p) if ((int)$p['contact_id'] === $cid) $cp = $p; if (!$cp) not_found();
  law_audit('portal_previewed', 'matter', $m['id'], ['contact' => $cid], null, $me);
  render('portal_preview', ['title' => t('pv_title'), 'm' => $m, 'cp' => $cp, 'cps' => $cps]); exit;
}
if (preg_match('#^/matters/(\d+)/(?:portal/(\d+)/(on|off)|updates(?:/(\d+)/(publish|discard))?|reply|requests)$#', $path, $mm) && is_post()) {
  require_perm('matters', 'view');
  $m = matter_for((int)$mm[1], $me); if (!$m) not_found();
  if (str_ends_with($path, '/requests')) { require_perm('documents', 'create'); $err = doc_request_add($me, $m, post('title')); }
  else {
    require_perm('matters', 'edit');
    if (!empty($mm[2])) $err = party_portal_toggle($me, $m, (int)$mm[2], $mm[3] === 'on');
    elseif (!empty($mm[4])) $err = $mm[5] === 'publish' ? update_publish($me, $m, (int)$mm[4]) : update_discard($me, $m, (int)$mm[4]);
    elseif (str_ends_with($path, '/updates')) $err = update_draft($me, $m, (string)($_POST['body'] ?? ''));
    else $err = staff_reply($me, $m, (string)($_POST['body'] ?? ''));
  }
  if ($err === 'not_found') not_found();
  if ($err === 'update_approver_only' || $err === 'matter_restrict_forbidden') deny($err);
  flash($err ? t($err) : t('saved'), $err ? 'err' : 'ok'); redirect('/matters/' . $m['id'] . '#portal');
}
if (preg_match('#^/calendar/(\d+)/portal$#', $path, $mm) && is_post()) {
  require_perm('calendar', 'edit');
  $a = appt_get_for((int)$mm[1], $me); if (!$a || !$a['matter_id']) not_found();
  o_update('appointments', (int)$a['id'], ['portal_visible' => !empty($_POST['on']) ? 1 : 0, 'updated_at' => now()]);
  if ((bool)$a['portal_visible'] !== !empty($_POST['on'])) share_mark($me, (int)$a['matter_id'], 'appointment', (int)$a['id'], !empty($_POST['on']));
  law_audit('appointment_portal', 'appointment', $a['id'], ['on' => !empty($_POST['on'])], null, $me);
  flash(t('saved')); redirect('/calendar/' . $a['id']);
}

/* ---------- case file work: procedures and judgments, delegations, templates ---------- */
if (preg_match('#^/matters/(\d+)/actions(?:/(\d+)/(edit|verify|share|unshare))?$#', $path, $mm)) {
  require_perm('matters', 'view');
  $m = matter_for((int)$mm[1], $me); if (!$m) not_found();
  $a = !empty($mm[2]) ? action_get((int)$mm[2], $me) : null; if (!empty($mm[2]) && (!$a || (int)$a['matter_id'] !== (int)$m['id'])) not_found();
  $act = $mm[3] ?? ''; $err = null;
  if ($act === '' || $act === 'edit') {
    require_perm('matters', 'edit'); if (office_readonly()) deny();
    $d = $a ?? ['kind' => 'procedure', 'action_on' => date('Y-m-d'), 'court_id' => $m['court_id'], 'source' => ''];
    if (is_post()) { $r = action_save($me, $m, $_POST, $a); if (isset($r['id'])) { flash(t('saved')); redirect('/matters/' . $m['id'] . '#actions'); } $err = $r['error']; $d = $r['data'] + $d; http_response_code(422); }
    elseif ($act === '') not_found();
    render('action_form', ['title' => t($a ? 'act_edit' : 'act_new'), 'm' => $m, 'a' => $a, 'd' => $d, 'err' => $err]); exit;
  }
  if (!is_post()) not_found();
  require_perm('matters', 'edit');
  $err = $act === 'verify' ? action_verify($me, $a) : action_share($me, $m, $a, $act === 'share');
  flash($err ? t($err) : t($act === 'share' ? 'act_shared' : 'saved'), $err ? 'err' : 'ok'); redirect('/matters/' . $m['id'] . '#actions');
}
if (preg_match('#^/matters/(\d+)/delegations$#', $path, $mm) && is_post()) {
  if (!feature_on('delegations')) not_found();
  require_perm('calendar', 'create'); if (office_readonly()) deny();
  $m = matter_for((int)$mm[1], $me); if (!$m) not_found();
  $r = deleg_create($me, $m, $_POST);
  if (isset($r['id'])) { flash(t(!empty($_POST['send']) ? 'deleg_sent' : 'deleg_saved_draft')); redirect('/delegations/' . $r['id']); }
  flash(t($r['error']), 'err');
  redirect(!empty($r['data']['appointment_id']) && post('back') === 'appt' ? '/calendar/' . (int)$r['data']['appointment_id'] . '#delegate' : '/matters/' . $m['id'] . '#delegations');
}
if ($path === '/delegations') {
  if (!feature_on('delegations')) not_found();
  $me = require_staff();
  $f = ['period' => in_array($_GET['period'] ?? 'week', ['day', 'week', 'all'], true) ? ($_GET['period'] ?? 'week') : 'week', 'd' => valid_date($_GET['d'] ?? null) ?? date('Y-m-d'),
        'status' => (string)($_GET['status'] ?? ''), 'staff' => (int)($_GET['staff'] ?? 0), 'mine' => !empty($_GET['mine'])];
  $list = deleg_list($me, $f);
  if (!empty($_GET['pdf'])) {
    [$from, $to] = deleg_period($f); $lbl = t('deleg_period_' . $f['period']) . ($from ? ' — ' . date('d/m/Y', strtotime($from)) . ($f['period'] === 'week' ? ' – ' . date('d/m/Y', strtotime("$to -1 day")) : '') : '');
    law_audit('delegation_report', null, null, ['period' => $f['period'], 'rows' => count($list)], null, $me);
    pdf_send(deleg_report_pdf($list, $lbl), 'delegations-' . $f['period'] . '-' . $f['d'] . '.pdf');
  }
  render('delegations', ['title' => t('mod_delegations'), 'list' => $list, 'f' => $f]); exit;
}
if (preg_match('#^/delegations/(\d+)(?:/(send|accept|reject|reassign|done|cancel|memo))?$#', $path, $mm)) {
  if (!feature_on('delegations')) not_found();
  $me = require_staff();
  $d = deleg_get((int)$mm[1], $me); if (!$d) not_found();
  $act = $mm[2] ?? '';
  if ($act === '') { render('delegation', ['title' => t('deleg_one'), 'd' => $d]); exit; }
  if (!is_post()) not_found(); if (office_readonly()) deny();
  if ($act === 'memo') {
    if (!can('documents', 'create', $me) || !(deleg_is_asker($d, $me) || deleg_is_delegate($d, $me))) deny();
    $r = deleg_memo($me, $d); if (isset($r['id'])) { flash(t('deleg_memo_saved')); redirect('/documents/' . $r['id']); }
    flash(t($r['error']), 'err'); redirect('/delegations/' . $d['id']);
  }
  $err = match ($act) {
    'send' => deleg_send($me, $d), 'accept' => deleg_respond($me, $d, true, post('note')), 'reject' => deleg_respond($me, $d, false, post('note')),
    'reassign' => deleg_reassign($me, $d, (int)post('to_staff'), post('instructions')), 'done' => deleg_complete($me, $d, (string)($_POST['result'] ?? '')), 'cancel' => deleg_cancel($me, $d),
  };
  if ($err === 'forbidden') deny();
  flash($err ? t($err) : t('saved'), $err ? 'err' : 'ok'); redirect('/delegations/' . $d['id']);
}
if ($path === '/templates' || $path === '/templates/new' || preg_match('#^/templates/(\d+)$#', $path, $mm)) {
  if (!feature_on('templates')) not_found();
  require_perm('documents', 'view');
  if ($path === '/templates') { render('templates', ['title' => t('mod_templates'), 'list' => tpl_doc_list(null, can('documents', 'edit')), 'kind' => (string)($_GET['kind'] ?? '')]); exit; }
  $old = isset($mm[1]) ? tpl_doc_get((int)$mm[1]) : null; if (isset($mm[1]) && !$old) not_found();
  $err = null; $bad = []; $d = $old ?? ['kind' => in_array($_GET['kind'] ?? '', TEMPLATE_KINDS, true) ? $_GET['kind'] : 'memo', 'name' => '', 'body' => '', 'active' => 1];
  if (is_post()) {
    require_perm('documents', 'edit'); if (office_readonly()) deny();
    $r = tpl_doc_save($me, $_POST, $_FILES['file'] ?? null, $old);
    if (isset($r['id'])) { flash(t('saved')); redirect('/templates/' . $r['id']); }
    $err = $r['error']; $bad = $r['fields'] ?? []; $d = $r['data'] + $d; http_response_code(422);
  }
  render('template_form', ['title' => $old ? $old['name'] : t('tpl_doc_new'), 't' => $old, 'd' => $d, 'err' => $err, 'bad' => $bad]); exit;
}
if ($path === '/compose') { redirect('/matters/' . (int)($_GET['matter'] ?? 0) . '/compose?template=' . (int)($_GET['template'] ?? 0)); }
if (preg_match('#^/matters/(\d+)/compose$#', $path, $mm)) {
  if (!feature_on('templates')) not_found();
  require_perm('documents', 'create');
  $m = matter_for((int)$mm[1], $me); if (!$m) not_found();
  $tp = tpl_doc_get((int)($_GET['template'] ?? $_POST['template'] ?? 0)); if (!$tp || !$tp['active']) not_found();
  [$text, $missing] = tpl_fill($tp['body'], tpl_values($m));
  $err = null; $title = $tp['name'] . ' — ' . $m['ref'];
  if (is_post()) {
    if (office_readonly()) deny();
    $text = mb_substr(str_replace("\r\n", "\n", (string)($_POST['body'] ?? '')), 0, 30000); $title = mb_substr(trim(post('title')), 0, 200) ?: $title;
    if (trim($text) === '') $err = 'tpl_body_required';
    else {
      $r = doc_create_generated($me, $m, $title, text_pdf($tp['name'], $text, $m['ref'] . ' — ' . $m['title']));
      if (isset($r['id'])) { law_audit('template_used', 'doc_template', $tp['id'], ['matter' => $m['id'], 'version' => $tp['version'], 'document' => $r['id']], null, $me); flash(t('tpl_doc_saved')); redirect('/documents/' . $r['id']); }
      $err = $r['error'];
    }
    http_response_code(422);
  }
  render('compose', ['title' => t('tpl_compose'), 'm' => $m, 'tp' => $tp, 'text' => $text, 'missing' => $missing, 'doc_title' => $title, 'err' => $err]); exit;
}

/* ---------- unfinished work ---------- */
if ($path === '/followup') {
  require_perm('calendar', 'view');
  $f = ['lead' => (int)($_GET['lead'] ?? 0), 'from' => valid_date($_GET['from'] ?? null)];
  render('followup', ['title' => t('fu_title'), 'list' => followup_list($me, $f), 'f' => $f]); exit;
}

/* ---------- recycle bin and backups ---------- */
if ($path === '/trash/move' && is_post()) {
  $me = require_staff(); if (office_readonly() || is_support($me)) deny();
  $kind = post('kind'); $id = (int)post('id'); $ok = false; $back = '/';
  switch ($kind) {
    case 'matter': $x = matter_for($id, $me); $ok = (bool)$x; $back = '/matters'; break;
    case 'appointment': $x = appt_get_for($id, $me); $ok = (bool)$x; $back = $x && $x['matter_id'] ? '/matters/' . $x['matter_id'] . '#hearings' : '/calendar'; break;
    case 'action': $x = action_get($id, $me); $ok = (bool)$x; $back = $x ? '/matters/' . $x['matter_id'] . '#actions' : '/'; break;
    case 'task': $x = task_get_for($id, $me); $ok = (bool)$x; $back = $x && $x['matter_id'] ? '/matters/' . $x['matter_id'] . '#tasks' : '/tasks'; break;
    case 'document': $x = doc_get_for($id, $me); $ok = (bool)$x; $back = $x && $x['matter_id'] ? '/matters/' . $x['matter_id'] . '#docs' : '/documents'; break;
    case 'note': $x = orow("SELECT * FROM matter_events WHERE office_id = :office AND id = :id AND kind = 'note'", ['id' => $id]); $ok = $x && matter_for((int)$x['matter_id'], $me) && can('legal_notes', 'view'); $back = $x ? '/matters/' . $x['matter_id'] . '#timeline' : '/'; break;
  }
  if (!$ok) not_found();
  $err = trash_move($me, $kind, $id); if ($err === 'forbidden') deny(); if ($err === 'not_found') not_found();
  if ($err) { flash(t($err), 'err'); redirect(post('back') && str_starts_with(post('back'), '/') && !str_starts_with(post('back'), '//') ? post('back') : $back); }
  flash(t('trash_moved')); redirect($back);
}
if ($path === '/trash' || preg_match('#^/trash/(\d+)/restore$#', $path, $mm)) {
  $me = require_staff(); if (!trash_can_view($me)) deny();
  if (isset($mm[1])) {
    if (!is_post()) not_found(); if (office_readonly()) deny();
    $err = trash_restore($me, (int)$mm[1]); if ($err === 'not_found') not_found();
    flash($err ? t($err) : t('trash_restored'), $err ? 'err' : 'ok'); redirect('/trash');
  }
  $f = ['kind' => (string)($_GET['kind'] ?? ''), 'who' => (int)($_GET['who'] ?? 0), 'from' => valid_date($_GET['from'] ?? null), 'restored' => !empty($_GET['restored'])];
  render('trash', ['title' => t('mod_trash'), 'list' => trash_list($f), 'f' => $f]); exit;
}
if ($path === '/settings/backups' || $path === '/settings/backups/run') {
  $me = require_staff(); if (!trash_can_view($me)) deny();
  if ($path === '/settings/backups/run') { if (!is_post() || $me['role'] !== 'partner') deny(); $r = backup_run('manual'); law_audit('backup_manual', null, null, ['ok' => $r['ok']], null, $me); flash(t($r['ok'] ? 'bk_done' : 'bk_failed'), $r['ok'] ? 'ok' : 'err'); redirect('/settings/backups'); }
  render('backups', ['title' => t('bk_title'), 'b' => backup_status()]); exit;
}

/* ---------- settings: lists ---------- */
if ($path === '/settings/lists') {
  require_perm('settings', 'view');
  $all = []; foreach (LOOKUP_KINDS as $k) $all[$k] = lookups($k, true);
  render('lists', ['title' => t('lists'), 'all' => $all, 'err' => null]); exit;
}
if (preg_match('#^/settings/lists/(add|\d+)$#', $path, $mm) && is_post()) {
  require_perm('settings', 'edit');
  $err = $mm[1] === 'add' ? lookup_add($me, post('kind'), post('name_ar'), post('name_en'), !empty($_POST['is_closed']))
                          : lookup_update($me, (int)$mm[1], post('name_ar'), post('name_en'), !empty($_POST['active']));
  if ($err === 'not_found') not_found();
  if ($err) flash(t($err), 'err'); else flash(t('saved'));
  redirect('/settings/lists');
}

/* ---------- clients and parties ---------- */
if ($path === '/clients') {
  require_perm('clients', 'view');
  $f = ['q' => (string)($_GET['q'] ?? ''), 'kind' => (string)($_GET['kind'] ?? ''), 'type' => (string)($_GET['type'] ?? ''), 'archived' => !empty($_GET['archived']), 'page' => max(1, (int)($_GET['page'] ?? 1))];
  [$list, $total] = contact_list($f);
  render('clients', ['title' => t('mod_clients'), 'list' => $list, 'total' => $total, 'f' => $f, 'pages' => max(1, (int)ceil($total / 25))]); exit;
}
if ($path === '/clients/new' || preg_match('#^/clients/(\d+)/edit$#', $path, $m)) {
  $id = isset($m[1]) ? (int)$m[1] : null;
  require_perm('clients', $id ? 'edit' : 'create');
  $c = $id ? contact_get($id) : null; if ($id && !$c) not_found();
  $err = null; $dups = []; $data = $c ?? ['kind' => (string)($_GET['kind'] ?? 'person'), 'is_client' => ($_GET['type'] ?? 'client') === 'client' ? 1 : 0, 'pref_lang' => 'ar'];
  if (is_post()) {
    $r = contact_save($me, $_POST, $id, !empty($_POST['confirm_dup']));
    if (isset($r['id'])) { flash(t('saved')); redirect('/clients/' . $r['id']); }
    $err = $r['error'] ?? null; $dups = $r['dups'] ?? []; $data = $r['data'];
  }
  render('client_form', ['title' => t($id ? 'contact_edit' : 'contact_new'), 'id' => $id, 'd' => $data, 'err' => $err, 'dups' => $dups]); exit;
}
if (preg_match('#^/clients/(\d+)$#', $path, $m)) {
  require_perm('clients', 'view');
  $c = contact_get((int)$m[1]); if (!$c) not_found();
  law_audit('contact_viewed', 'contact', $c['id'], [], null, $me);
  render('client', ['title' => contact_label($c), 'c' => $c, 'checks' => can('conflicts', 'view') ? checks_for_contact((int)$c['id']) : []]); exit;
}
if (preg_match('#^/clients/(\d+)/(archive|restore)$#', $path, $m) && is_post()) {
  require_perm('clients', 'delete');
  if (!contact_set_archived($me, (int)$m[1], $m[2] === 'archive')) not_found();
  flash(t($m[2] === 'archive' ? 'contact_archived' : 'contact_restored')); redirect('/clients/' . (int)$m[1]);
}

/* ---------- conflict checks ---------- */
if ($path === '/conflicts') {
  require_perm('conflicts', 'view');
  $st = (string)($_GET['status'] ?? ''); $page = max(1, (int)($_GET['page'] ?? 1));
  [$list, $total] = check_list($st, $page);
  render('conflicts', ['title' => t('mod_conflicts'), 'list' => $list, 'status' => $st, 'page' => $page, 'pages' => max(1, (int)ceil($total / 25))]); exit;
}
if ($path === '/conflicts/new') {
  require_perm('conflicts', 'create');
  $err = null; $in = ['subject' => '', 'client' => '', 'opponent' => '', 'related' => ''];
  if (!empty($_GET['contact']) && ($c = contact_get((int)$_GET['contact']))) $in['client'] = implode("\n", array_filter([$c['name_ar'], $c['name_en']]));
  if (is_post()) {
    $in = ['subject' => post('subject'), 'client' => (string)($_POST['client'] ?? ''), 'opponent' => (string)($_POST['opponent'] ?? ''), 'related' => (string)($_POST['related'] ?? '')];
    $r = check_create($me, $in['subject'], $in);
    if (isset($r['id'])) redirect('/conflicts/' . $r['id']);
    $err = $r['error'];
  }
  render('conflict_new', ['title' => t('check_new'), 'err' => $err, 'in' => $in]); exit;
}
if (preg_match('#^/conflicts/(\d+)$#', $path, $m)) {
  require_perm('conflicts', 'view');
  $c = check_get((int)$m[1]); if (!$c) not_found();
  law_audit('conflict_check_viewed', 'conflict_check', $c['id'], [], null, $me);
  render('conflict', ['title' => $c['ref'], 'c' => $c, 'groups' => check_hits((int)$c['id']), 'err' => null]); exit;
}
if (preg_match('#^/conflicts/(\d+)/(decide|rerun)$#', $path, $m) && is_post()) {
  require_perm('conflicts', $m[2] === 'decide' ? 'edit' : 'create');
  $err = $m[2] === 'decide' ? check_decide($me, (int)$m[1], post('decision'), post('note')) : check_rerun($me, (int)$m[1]);
  if ($err === 'not_found') not_found();
  if ($err === 'forbidden') deny();
  if ($err) { $c = check_get((int)$m[1]); http_response_code(422); render('conflict', ['title' => $c['ref'], 'c' => $c, 'groups' => check_hits((int)$c['id']), 'err' => $err, 'note' => post('note'), 'decision' => post('decision')]); exit; }
  flash(t($m[2] === 'decide' ? 'check_decision_saved' : 'check_rerun_done')); redirect('/conflicts/' . (int)$m[1]);
}

if ($path === '/audit') {
  require_perm('audit', 'view');
  $page = max(1, (int)($_GET['page'] ?? 1)); $per = 50;
  $w = ''; $ap = [];
  if (preg_match('/^[a-z_]{2,40}$/', (string)($_GET['entity'] ?? '')) && ctype_digit((string)($_GET['id'] ?? ''))) {
    $w = ' AND (entity = :en AND entity_id = :eid' . ($_GET['entity'] === 'support_grant' ? " OR actor = :sa" : '') . ')'; $ap = ['en' => $_GET['entity'], 'eid' => $_GET['id']];
    if ($_GET['entity'] === 'support_grant') $ap['sa'] = 'Al Imtyaz support (visit #' . (int)$_GET['id'] . ')';
  }
  $af = ['who' => (int)($_GET['who'] ?? 0), 'act' => preg_match('/^[a-z0-9_]{2,40}$/', (string)($_GET['act'] ?? '')) ? (string)$_GET['act'] : '', 'from' => valid_date($_GET['from'] ?? null), 'to' => valid_date($_GET['to'] ?? null)];
  if ($af['who']) { $w .= ' AND staff_id = :who'; $ap['who'] = $af['who']; }
  if ($af['act']) { $w .= ' AND action LIKE :act'; $ap['act'] = $af['act'] . '%'; }
  if ($af['from']) { $w .= ' AND at >= :afr'; $ap['afr'] = $af['from'] . ' 00:00:00'; }
  if ($af['to']) { $w .= ' AND at < :ato'; $ap['ato'] = date('Y-m-d', strtotime($af['to'] . ' +1 day')) . ' 00:00:00'; }
  $total = (int)orow('SELECT COUNT(*) n FROM audit_log WHERE office_id = :office' . $w, $ap)['n'];
  $list = orows('SELECT * FROM audit_log WHERE office_id = :office' . $w . ' ORDER BY id DESC LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per), $ap);
  $verify = isset($_GET['verify']) ? audit_verify(office_id()) : null;
  if ($verify) law_audit('audit_verified', null, null, ['ok' => $verify[0], 'entries' => $verify[1]]);
  render('audit', ['title' => t('act_log'), 'af' => $af, 'me' => $me, 'list' => $list, 'page' => $page, 'pages' => max(1, (int)ceil($total / $per)), 'verify' => $verify]); exit;
}
if ($path === '/audit/export') {
  require_perm('audit', 'download');
  law_audit('audit_exported');
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="audit-' . current_office()['code'] . '-' . date('Ymd') . '.csv"');
  $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
  fputcsv($out, ['id', 'at', 'staff_id', 'actor', 'action', 'entity', 'entity_id', 'detail', 'ip', 'hash']);
  foreach (orows('SELECT * FROM audit_log WHERE office_id = :office ORDER BY id') as $r) fputcsv($out, [$r['id'], $r['at'], $r['staff_id'], $r['actor'], $r['action'], $r['entity'], $r['entity_id'], $r['detail'], $r['ip'], $r['hash']]);
  exit;
}

not_found();
