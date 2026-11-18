<?php
/* ==========================================================================
   Encryption at rest for documents, backups and stored sign-in secrets.
   Uses libsodium when PHP has it; otherwise OpenSSL AES-256-GCM (some
   shared hosts ship PHP without sodium). Files carry their format, so a file
   written either way can still be read after the host adds or drops sodium.
   ========================================================================== */
declare(strict_types=1);

const LAW_GCM_MAGIC = "LAWG1";
const LAW_CHUNK = 65536;

function crypt_has_sodium(): bool { return getenv('LAW_FORCE_OPENSSL') !== '1' && function_exists('sodium_crypto_secretstream_xchacha20poly1305_init_push'); }
function crypt_has_openssl(): bool { return function_exists('openssl_encrypt') && in_array('aes-256-gcm', openssl_get_cipher_methods(), true); }
function crypt_available(): bool { return crypt_has_sodium() || crypt_has_openssl(); }
function crypt_engine(): string { return crypt_has_sodium() ? 'sodium' : (crypt_has_openssl() ? 'openssl' : 'none'); }

/** Encrypt the file $src into $dst with a 32-byte key. */
function crypt_encrypt_file(string $src, string $dst, string $key): void {
  $in = fopen($src, 'rb'); $out = fopen($dst, 'wb');
  if (!$in || !$out) throw new RuntimeException('Cannot open files for encryption');
  try {
    if (crypt_has_sodium()) {
      [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
      fwrite($out, $header);
      while (true) {
        $chunk = fread($in, LAW_CHUNK); $last = feof($in);
        fwrite($out, sodium_crypto_secretstream_xchacha20poly1305_push($state, (string)$chunk, '', $last ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE));
        if ($last) break;
      }
      return;
    }
    if (!crypt_has_openssl()) throw new RuntimeException('No encryption available: enable sodium or openssl in PHP');
    // OpenSSL format: magic | file id(16) | chunks of [final(1) | length(4) | nonce(12) | tag(16) | ciphertext]
    // Each chunk is bound to the file id, its position and the final flag, so chunks cannot be moved, cut or swapped.
    $fid = random_bytes(16); fwrite($out, LAW_GCM_MAGIC . $fid); $i = 0;
    while (true) {
      $chunk = (string)fread($in, LAW_CHUNK); $last = feof($in);
      $flag = $last ? "\x01" : "\x00"; $nonce = random_bytes(12); $tag = '';
      $ct = openssl_encrypt($chunk, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, $fid . pack('N', $i) . $flag, 16);
      if ($ct === false) throw new RuntimeException('Encryption failed');
      fwrite($out, $flag . pack('N', strlen($ct)) . $nonce . $tag . $ct);
      $i++; if ($last) break;
    }
  } finally { fclose($in); fclose($out); }
}

/** Decrypt the file $src to the stream $out. False if the file is damaged, altered or cut short. */
function crypt_decrypt_file(string $src, $out, string $key): bool {
  $in = @fopen($src, 'rb'); if (!$in) return false;
  try {
    $head = (string)fread($in, strlen(LAW_GCM_MAGIC));
    if ($head === LAW_GCM_MAGIC) {
      if (!crypt_has_openssl()) return false;
      $fid = (string)fread($in, 16); if (strlen($fid) !== 16) return false; $i = 0;
      while (true) {
        $h = (string)fread($in, 33); if (strlen($h) !== 33) return false;
        $flag = $h[0]; $len = unpack('N', substr($h, 1, 4))[1]; $nonce = substr($h, 5, 12); $tag = substr($h, 17, 16);
        if ($len > LAW_CHUNK + 16) return false;
        $ct = $len > 0 ? (string)fread($in, $len) : ''; if (strlen($ct) !== $len) return false;
        $pt = openssl_decrypt($ct, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, $fid . pack('N', $i) . $flag);
        if ($pt === false) return false;
        fwrite($out, $pt); $i++;
        if ($flag === "\x01") return fread($in, 1) === '' && feof($in);
      }
    }
    if (!crypt_has_sodium()) return false;
    $header = $head . (string)fread($in, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES - strlen($head));
    if (strlen($header) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES) return false;
    $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $key);
    while (!feof($in)) {
      $c = fread($in, LAW_CHUNK + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES); if ($c === '' || $c === false) break;
      $r = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $c); if ($r === false) return false;
      fwrite($out, $r[0]); if ($r[1] === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) return true;
    }
    return false;
  } finally { fclose($in); }
}

/** Seal a short secret (sign-in app secrets). 'enc:' = sodium, 'enc2:' = OpenSSL. */
function crypt_seal(string $plain, string $label): string {
  if (crypt_has_sodium()) {
    $k = sodium_crypto_generichash($label . '|' . (string)cfg('app_secret'), '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    $n = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return 'enc:' . base64_encode($n . sodium_crypto_secretbox($plain, $n, $k));
  }
  if (!crypt_has_openssl()) throw new RuntimeException('No encryption available: enable sodium or openssl in PHP');
  $k = hash_hmac('sha256', $label, (string)cfg('app_secret'), true); $n = random_bytes(12); $tag = '';
  $ct = openssl_encrypt($plain, 'aes-256-gcm', $k, OPENSSL_RAW_DATA, $n, $tag, 'enc2', 16);
  return 'enc2:' . base64_encode($n . $tag . $ct);
}
function crypt_open(?string $stored, string $label): ?string {
  if (!$stored) return null;
  if (str_starts_with($stored, 'enc2:')) {
    if (!crypt_has_openssl()) return null;
    $raw = base64_decode(substr($stored, 5), true); if ($raw === false || strlen($raw) < 28) return null;
    $k = hash_hmac('sha256', $label, (string)cfg('app_secret'), true);
    $p = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $k, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16), 'enc2');
    return $p === false ? null : $p;
  }
  if (!str_starts_with($stored, 'enc:') || !crypt_has_sodium()) return null;
  $raw = base64_decode(substr($stored, 4), true); if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) return null;
  $k = sodium_crypto_generichash($label . '|' . (string)cfg('app_secret'), '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
  $p = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $k);
  return $p === false ? null : $p;
}
