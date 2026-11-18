<?php
/* Prints new random values for config.php. Run once per deployment:  php law/tools/keygen.php
   Does not need config.php. Keep an OFFLINE copy of files_key: without it stored documents cannot be opened. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
echo "'app_secret' => '" . rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=') . "',\n";
echo "'files_key'  => '" . base64_encode(random_bytes(32)) . "',\n";
