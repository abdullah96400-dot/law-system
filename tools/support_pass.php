<?php
/* Make the support passphrase hash for config.php (support_pass_hash).
     php law/tools/support_pass.php
   Type the passphrase when asked. Keep the passphrase with Al Imtyaz support only. */
require __DIR__ . '/_cli.php';
echo 'Support passphrase (16+ characters): ';
$p = trim((string)fgets(STDIN));
if (mb_strlen($p) < 16) fail('Too short: use 16 characters or more.');
out("'support_pass_hash' => '" . password_hash($p, PASSWORD_DEFAULT) . "',");
