<?php
/* ==========================================================================
   Provisioning API for the Al Imtyaz platform (server to server only).
   Off unless api_secret is set in config.php.
   Every request is signed:
     X-Imz-Timestamp  unix seconds (must be within 5 minutes)
     X-Imz-Nonce      32 hex characters, used once
     X-Imz-Signature  hex HMAC-SHA256(api_secret, ts \n nonce \n METHOD \n /api/v1/path \n raw body)
   Endpoints (JSON in and out):
     POST /api/v1/offices                 create an office and invite its managing partner
     POST /api/v1/offices/{code}/status   active | readonly | suspended
     POST /api/v1/offices/{code}/plan     plan name and limits, paid-until date, features (list or null)
     GET  /api/v1/offices/{code}/usage    counts only — never client or case data
   Ending a subscription is "readonly" or "suspended"; the API cannot delete data.
   ========================================================================== */
declare(strict_types=1);

const API_SKEW = 300;

function api_out(int $code, array $body): never {
  http_response_code($code); header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
  echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit;
}
function api_sign(string $secret, string $ts, string $nonce, string $method, string $path, string $body): string {
  return hash_hmac('sha256', $ts . "\n" . $nonce . "\n" . strtoupper($method) . "\n" . $path . "\n" . $body, $secret);
}
/** Check the signature and replay guard. Returns an error key or null. */
function api_verify(string $method, string $path, string $body, array $hdr): ?string {
  $secret = (string)cfg('api_secret', ''); if (strlen($secret) < 32) return 'off';
  $ts = (string)($hdr['ts'] ?? ''); $nonce = strtolower((string)($hdr['nonce'] ?? '')); $sig = strtolower((string)($hdr['sig'] ?? ''));
  if (!ctype_digit($ts) || abs(time() - (int)$ts) > API_SKEW) return 'bad_time';
  if (!preg_match('/^[0-9a-f]{32}$/', $nonce)) return 'bad_nonce';
  if (!hash_equals(api_sign($secret, $ts, $nonce, $method, $path, $body), $sig)) return 'bad_signature';
  q('DELETE FROM api_nonces WHERE at < ?', [date('Y-m-d H:i:s', time() - 2 * API_SKEW)]);
  try { q('INSERT INTO api_nonces (nonce, at) VALUES (?,?)', [$nonce, now()]); } catch (Throwable $e) { return 'replay'; }
  return null;
}
function api_office(string $code): ?array { return row('SELECT * FROM offices WHERE code=?', [strtoupper($code)]); }
function api_usage(array $o): array {
  $id = (int)$o['id']; $n = fn(string $sql) => (int)(row($sql, [$id])['n'] ?? 0);
  return ['code' => $o['code'], 'status' => $o['status'], 'plan' => $o['plan'], 'max_users' => $o['max_users'] === null ? null : (int)$o['max_users'],
    'max_storage_mb' => $o['max_storage_mb'] === null ? null : (int)$o['max_storage_mb'], 'paid_until' => $o['paid_until'], 'demo' => (bool)$o['demo'], 'features' => office_features($o), 'country' => $o['country'] ?? 'OM',
    'users' => $n("SELECT COUNT(*) n FROM staff WHERE office_id=? AND status <> 'disabled'"), 'active_users' => $n("SELECT COUNT(*) n FROM staff WHERE office_id=? AND status = 'active'"),
    'matters_open' => $n('SELECT COUNT(*) n FROM matters WHERE office_id=? AND closed_at IS NULL'), 'matters_total' => $n('SELECT COUNT(*) n FROM matters WHERE office_id=?'),
    'documents' => $n('SELECT COUNT(*) n FROM documents WHERE office_id=?'), 'storage_mb' => round((int)(row('SELECT COALESCE(SUM(size),0) n FROM document_versions WHERE office_id=?', [$id])['n']) / 1048576, 1),
    'portal_users' => $n("SELECT COUNT(*) n FROM portal_users WHERE office_id=? AND status = 'active'"),
    'last_sign_in' => row('SELECT MAX(last_login) n FROM staff WHERE office_id=?', [$id])['n'] ?? null];
}
function api_plan_fields(array $in): array|string {
  $d = [];
  if (array_key_exists('plan', $in)) $d['plan'] = $in['plan'] === null ? null : mb_substr(trim((string)$in['plan']), 0, 60);
  foreach (['max_users' => 1, 'max_storage_mb' => 1] as $k => $_) if (array_key_exists($k, $in)) {
    if ($in[$k] === null || $in[$k] === '') { $d[$k] = null; continue; }
    if (!is_numeric($in[$k]) || (int)$in[$k] < 1) return 'bad_' . $k; $d[$k] = (int)$in[$k];
  }
  if (array_key_exists('features', $in)) {   // null = every module; a list = only these
    if ($in['features'] === null) $d['features'] = null;
    elseif (!is_array($in['features']) || array_diff($in['features'], PLAN_FEATURES)) return 'bad_features';
    else $d['features'] = json_encode(array_values(array_unique($in['features'])));
  }
  if (array_key_exists('paid_until', $in)) { if ($in['paid_until'] !== null && !valid_date((string)$in['paid_until'])) return 'bad_paid_until'; $d['paid_until'] = $in['paid_until']; }
  if (array_key_exists('account_ref', $in)) $d['account_ref'] = $in['account_ref'] === null ? null : mb_substr(preg_replace('/[^\w.-]/', '', (string)$in['account_ref']), 0, 40);
  return $d;
}
/** Handle /api/v1/… and exit. */
function api_handle(string $path): never {
  $method = $_SERVER['REQUEST_METHOD'] ?? 'GET'; $body = (string)file_get_contents('php://input');
  $err = api_verify($method, $path, $body, ['ts' => $_SERVER['HTTP_X_IMZ_TIMESTAMP'] ?? '', 'nonce' => $_SERVER['HTTP_X_IMZ_NONCE'] ?? '', 'sig' => $_SERVER['HTTP_X_IMZ_SIGNATURE'] ?? '']);
  if ($err === 'off') api_out(404, ['error' => 'not_found']);
  if ($err) { error_log('LAW api refused: ' . $err . ' ' . ip()); api_out(401, ['error' => $err]); }
  $in = $body === '' ? [] : json_decode($body, true); if (!is_array($in)) api_out(400, ['error' => 'bad_json']);
  $who = ['id' => null, 'full_name' => 'Al Imtyaz platform (API)', 'email' => ''];
  if ($path === '/api/v1/offices' && $method === 'POST') {
    $plan = api_plan_fields($in); if (is_string($plan)) api_out(422, ['error' => $plan]);
    $r = create_office((string)($in['code'] ?? ''), (string)($in['name_ar'] ?? ''), (string)($in['name_en'] ?? ''), (string)($in['partner_email'] ?? ''), (string)($in['partner_name'] ?? ''),
      ['plan' => $plan['plan'] ?? null, 'max_users' => $plan['max_users'] ?? null, 'lang' => ($in['lang'] ?? 'ar') === 'en' ? 'en' : 'ar', 'send_mail' => !empty($in['send_invite'])]);
    if (isset($r['error'])) api_out(422, ['error' => $r['error']]);
    $rest = array_diff_key($plan, ['plan' => 1, 'max_users' => 1]); if ($rest) { $sets = implode(', ', array_map(fn($k) => "$k=?", array_keys($rest))); q("UPDATE offices SET $sets WHERE id=?", [...array_values($rest), $r['office_id']]); }
    $o = row('SELECT * FROM offices WHERE id=?', [$r['office_id']]);
    api_out(201, ['office' => api_usage($o), 'invite_link' => abs_url('/invite/' . $r['token']), 'invite_emailed' => !empty($in['send_invite'])]);
  }
  if (preg_match('#^/api/v1/offices/([A-Za-z0-9-]{3,20})(?:/(status|plan|usage))$#', $path, $m)) {
    $o = api_office($m[1]); if (!$o) api_out(404, ['error' => 'office_not_found']);
    if ($m[2] === 'usage' && $method === 'GET') api_out(200, ['office' => api_usage($o)]);
    if ($m[2] === 'status' && $method === 'POST') {
      $e = set_office_status((int)$o['id'], (string)($in['status'] ?? ''), 'Al Imtyaz platform (API)'); if ($e) api_out(422, ['error' => $e]);
      api_out(200, ['office' => api_usage(api_office($m[1]))]);
    }
    if ($m[2] === 'plan' && $method === 'POST') {
      $d = api_plan_fields($in); if (is_string($d)) api_out(422, ['error' => $d]); if (!$d) api_out(422, ['error' => 'nothing_to_change']);
      $sets = implode(', ', array_map(fn($k) => "$k=?", array_keys($d)));
      q("UPDATE offices SET $sets, updated_at=? WHERE id=?", [...array_values($d), now(), $o['id']]);
      law_audit('office_plan', 'office', $o['id'], $d, (int)$o['id'], $who);
      api_out(200, ['office' => api_usage(api_office($m[1]))]);
    }
  }
  api_out(404, ['error' => 'not_found']);
}
