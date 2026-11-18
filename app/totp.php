<?php
/* Two-step sign-in with an authenticator app (TOTP, RFC 6238: SHA-1, 6 digits, 30-second steps, ±1 step).
   Each code works once. The shared secret is stored encrypted with a key derived from app_secret. */
declare(strict_types=1);

function b32_encode(string $bin): string { $a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $bits = ''; foreach (str_split($bin) as $c) $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT); $o = ''; foreach (str_split($bits, 5) as $ch) $o .= $a[bindec(str_pad($ch, 5, '0'))]; return $o; }
function b32_decode(string $s): string { $a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $bits = ''; foreach (str_split(strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $s))) as $c) $bits .= str_pad(decbin(strpos($a, $c)), 5, '0', STR_PAD_LEFT); $o = ''; foreach (str_split($bits, 8) as $by) if (strlen($by) === 8) $o .= chr(bindec($by)); return $o; }
function totp_at(string $secret, int $step): string {
  $h = hash_hmac('sha1', pack('N2', 0, $step), b32_decode($secret), true); $o = ord($h[19]) & 0xf;
  $n = ((ord($h[$o]) & 0x7f) << 24) | (ord($h[$o + 1]) << 16) | (ord($h[$o + 2]) << 8) | ord($h[$o + 3]);
  return str_pad((string)($n % 1000000), 6, '0', STR_PAD_LEFT);
}
/** Arabic-Indic and Persian digits are accepted. */
function digits_only(string $s): string { return preg_replace('/\D/', '', strtr($s, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9'])); }
/** Returns the matched time step, or null. A step at or before $last is refused (replay). */
function totp_check(string $secret, string $code, ?int $last, ?int $now = null): ?int {
  $code = digits_only($code); if (strlen($code) !== 6) return null;
  $now = intdiv($now ?? time(), 30);
  for ($i = -1; $i <= 1; $i++) { $st = $now + $i; if ($last !== null && $st <= $last) continue; if (hash_equals(totp_at($secret, $st), $code)) return $st; }
  return null;
}
function totp_new_secret(): string { return b32_encode(random_bytes(20)); }
function totp_uri(string $secret, string $label): string {
  return 'otpauth://totp/' . rawurlencode('Al Imtyaz Law:' . $label) . '?secret=' . $secret . '&issuer=' . rawurlencode('Al Imtyaz Law') . '&digits=6&period=30';
}

/* ---------- secret at rest ---------- */
function secret_box_key(): string { return sodium_crypto_generichash('law-totp|' . (string)cfg('app_secret'), '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES); }
function seal_secret(string $plain): string { $n = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES); return 'enc:' . base64_encode($n . sodium_crypto_secretbox($plain, $n, secret_box_key())); }
function open_secret(?string $stored): ?string {
  if (!$stored) return null;
  if (!str_starts_with($stored, 'enc:')) return null;
  $raw = base64_decode(substr($stored, 4), true); if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) return null;
  $p = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), secret_box_key());
  return $p === false ? null : $p;
}
