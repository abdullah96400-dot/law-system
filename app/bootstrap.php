<?php
/* ==========================================================================
   AL IMTYAZ LAW — Oman. Core: settings, session, security headers, database,
   helpers, language, CSRF, views, mail.
   This app never loads the oman./team. platform code or database: it has its
   own config.php, its own database and its own files folder.
   ========================================================================== */
declare(strict_types=1);

$LAW_ROOT = dirname(__DIR__);
$cfgFile = getenv('LAW_CONFIG') ?: $LAW_ROOT . '/config.php';
if (!is_file($cfgFile)) {
  http_response_code(500);
  $here = htmlspecialchars($LAW_ROOT, ENT_QUOTES, 'UTF-8');
  exit('<meta charset="utf-8"><div style="font:16px system-ui;margin:40px;line-height:1.7"><p>Missing <b>config.php</b>. Upload it to this folder, next to index.php:</p><p><code>' . $here . '/config.php</code></p><p dir="rtl">ملف الإعدادات config.php غير موجود. ارفعه إلى هذا المجلد بجانب index.php، وتأكد أن اسمه config.php تمامًا.</p></div>');
}
$CFG = require $cfgFile;
date_default_timezone_set('Asia/Muscat');
mb_internal_encoding('UTF-8');

function cfg(string $k, $d = null) { global $CFG; return $CFG[$k] ?? $d; }
function law_root(): string { return dirname(__DIR__); }
function storage_dir(): string { $d = cfg('storage_dir', law_root() . '/storage'); if (!is_dir($d)) @mkdir($d, 0750, true); return $d; }

/* ---------- session + headers (web only) ---------- */
if (PHP_SAPI !== 'cli') {
  $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
  session_name('imzlaw');
  session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
  ini_set('session.use_strict_mode', '1');
  session_start();
  header('X-Frame-Options: DENY');
  header('X-Content-Type-Options: nosniff');
  header('Referrer-Policy: no-referrer');
  header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; script-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
  header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
  header('Cache-Control: private, no-store');
  if ($https) header('Strict-Transport-Security: max-age=31536000');
} elseif (!isset($_SESSION)) {
  $_SESSION = [];
}

/* ---------- database ---------- */
function db(): PDO {
  static $pdo = null;
  if ($pdo) return $pdo;
  $pdo = new PDO(cfg('db_dsn'), cfg('db_user'), cfg('db_pass'), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
  if (is_sqlite()) $pdo->exec('PRAGMA foreign_keys = ON');
  return $pdo;
}
function is_sqlite(): bool { return str_starts_with((string)cfg('db_dsn'), 'sqlite:'); }
function q(string $sql, array $p = []): PDOStatement { $s = db()->prepare($sql); $s->execute($p); return $s; }
function row(string $sql, array $p = []): ?array { $r = q($sql, $p)->fetch(); return $r ?: null; }
function rows(string $sql, array $p = []): array { return q($sql, $p)->fetchAll(); }
function now(): string { return date('Y-m-d H:i:s'); }

/* ---------- helpers ---------- */
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function ip(): string { return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45); }
function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'; }
function post(string $k, string $d = ''): string { $v = $_POST[$k] ?? $d; return is_string($v) ? trim($v) : $d; }
function url(string $p): string { return $p; }
function abs_url(string $p): string { return rtrim((string)cfg('site_url', ''), '/') . $p; }
function redirect(string $to): never { header('Location: ' . url($to)); exit; }
function flash(?string $msg = null, string $kind = 'ok'): ?array {
  if ($msg !== null) { $_SESSION['flash'] = [$kind, $msg]; return null; }
  $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f;
}
function token(int $bytes = 32): string { return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '='); }
function keyed_hash(string $v): string { return hash_hmac('sha256', $v, (string)cfg('app_secret')); }
function norm_email(string $e): string { return mb_strtolower(trim($e)); }

/* ---------- language ---------- */
function lang(): string {
  if (!empty($GLOBALS['LANG_FORCE'])) return $GLOBALS['LANG_FORCE'];
  $l = $_SESSION['lang'] ?? null;
  if (!$l && function_exists('current_staff') && ($s = current_staff())) $l = $s['lang'];
  return in_array($l, ['ar', 'en'], true) ? $l : 'ar';
}
function dir_attr(): string { return lang() === 'ar' ? 'rtl' : 'ltr'; }
/** Translate. Extra args fill {0}, {1}… */
function t(string $k, ...$a): string {
  global $I18N; $s = $I18N[$k][lang() === 'ar' ? 1 : 0] ?? $I18N[$k][0] ?? $k;
  foreach ($a as $i => $v) $s = str_replace('{' . $i . '}', (string)$v, $s);
  return $s;
}
/** Dates are stored as Y-m-d H:i:s; shown as 15/11/2026 14:05 in both languages (western digits keep tables aligned). */
function fmt_dt(?string $dt): string { if (!$dt) return '—'; $t = strtotime($dt); return $t ? date('d/m/Y H:i', $t) : '—'; }

/* ---------- CSRF ---------- */
function csrf(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . csrf() . '">'; }
function check_csrf(): void {
  $have = (string)($_SESSION['csrf'] ?? ''); $sent = (string)($_POST['_csrf'] ?? '');
  // a fresh session has no token: never let "empty = empty" pass
  if ($have === '' || !hash_equals($have, $sent)) { http_response_code(400); render('error', ['title' => t('error'), 'msg' => t('csrf_failed')]); exit; }
}

/* ---------- views ---------- */
function render(string $__tpl, array $vars = []): void {
  unset($vars['__tpl'], $vars['content']);
  extract($vars);   // page data never overwrites the template name
  ob_start(); require __DIR__ . '/views/' . $__tpl . '.php'; $content = ob_get_clean();
  require __DIR__ . '/views/' . ($GLOBALS['LAYOUT'] ?? 'layout') . '.php';
}
function deny(string $why = 'forbidden'): never {
  if (function_exists('law_audit') && function_exists('current_staff') && current_staff()) law_audit('denied', null, null, ['path' => $GLOBALS['ROUTE'] ?? '', 'why' => $why]);
  http_response_code(403); render('error', ['title' => t('error'), 'msg' => t($why)]); exit;
}
function not_found(): never { http_response_code(404); render('error', ['title' => t('error'), 'msg' => t('not_found')]); exit; }

/* ---------- mail (same approach as the platform; its own settings) ---------- */
function send_mail(string $to, string $subject, string $text): bool {
  $mode = cfg('mail_mode', 'log');
  $from = (string)cfg('mail_from', 'no-reply@localhost'); $name = (string)cfg('mail_from_name', 'Al Imtyaz Law');
  $subj = '=?UTF-8?B?' . base64_encode($subject) . '?=';
  $headers = "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\nFrom: =?UTF-8?B?" . base64_encode($name) . "?= <$from>\r\nReply-To: $from";
  $body = chunk_split(base64_encode($text));
  if ($mode === 'log') { file_put_contents(storage_dir() . '/mail.log', '---- ' . now() . " TO: $to\nSUBJECT: $subject\n$text\n", FILE_APPEND); return true; }
  if ($mode === 'mail') return @mail($to, $subj, $body, $headers);
  if (smtp_send($to, $subj, $headers, $body)) return true;
  error_log('LAW mail: SMTP failed, trying PHP mail()');
  return @mail($to, $subj, $body, $headers, '-f' . $from);
}
function smtp_send(string $to, string $subj, string $headers, string $body): bool {
  $fp = @stream_socket_client('ssl://' . cfg('smtp_host') . ':' . cfg('smtp_port'), $en, $es, 15);
  if (!$fp) { error_log("LAW SMTP connect failed: $es"); return false; }
  $read = function () use ($fp) { $d = ''; while (($l = fgets($fp, 515)) !== false) { $d .= $l; if (isset($l[3]) && $l[3] === ' ') break; } return $d; };
  $cmd = function ($c, $ok) use ($fp, $read) { fwrite($fp, $c . "\r\n"); $r = $read(); if (!str_starts_with($r, (string)$ok)) throw new RuntimeException("SMTP: $c -> $r"); return $r; };
  $host = parse_url((string)cfg('site_url'), PHP_URL_HOST) ?: 'localhost';
  try {
    $read();
    $cmd('EHLO ' . $host, 250);
    $cmd('AUTH LOGIN', 334); $cmd(base64_encode((string)cfg('smtp_user')), 334); $cmd(base64_encode((string)cfg('smtp_pass')), 235);
    $cmd('MAIL FROM:<' . cfg('mail_from') . '>', 250); $cmd("RCPT TO:<$to>", 250); $cmd('DATA', 354);
    $msg = "To: <$to>\r\nSubject: $subj\r\nDate: " . date('r') . "\r\nMessage-ID: <" . bin2hex(random_bytes(8)) . "@$host>\r\n$headers\r\n\r\n$body";
    $cmd(str_replace("\n.", "\n..", $msg) . "\r\n.", 250); $cmd('QUIT', 221);
  } catch (Throwable $e) { error_log('LAW mail: ' . preg_replace('/AUTH.*?->/s', 'AUTH ->', $e->getMessage())); fclose($fp); return false; }
  fclose($fp); return true;
}

require __DIR__ . '/i18n.php';
require __DIR__ . '/migrate.php';
require __DIR__ . '/tenant.php';
require __DIR__ . '/audit.php';
require __DIR__ . '/perms.php';
require __DIR__ . '/totp.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/offices.php';
require __DIR__ . '/names.php';
require __DIR__ . '/contacts.php';
require __DIR__ . '/conflicts.php';
require __DIR__ . '/lookups.php';
require __DIR__ . '/matters.php';
require __DIR__ . '/agenda.php';
require __DIR__ . '/documents.php';
require __DIR__ . '/xlsx.php';
require __DIR__ . '/finance.php';
require __DIR__ . '/portal.php';
require __DIR__ . '/reports.php';
require __DIR__ . '/support.php';
require __DIR__ . '/imports.php';
require __DIR__ . '/export.php';
require __DIR__ . '/api.php';
require __DIR__ . '/demo.php';
require __DIR__ . '/ui.php';
require __DIR__ . '/workspace.php';
require __DIR__ . '/plans.php';
require __DIR__ . '/country.php';
require __DIR__ . '/casework.php';
require __DIR__ . '/accounts.php';
require __DIR__ . '/trash.php';
