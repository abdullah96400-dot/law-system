<?php
/* Al Imtyaz Law Office Management — Oman. Configuration.
   Copy this file to law/config.php on the server and fill it in. Never commit or share config.php.
   This app has its OWN database and its OWN files folder. Never point it at the oman./team. platform database. */
return [
  // Database: a NEW, separate MySQL database (hPanel → Databases). Not the platform's database.
  'db_dsn'  => 'mysql:host=localhost;dbname=u000000000_law;charset=utf8mb4',
  'db_user' => 'u000000000_law',
  'db_pass' => 'CHANGE_ME',

  // Address of the law app, without a trailing slash
  'site_url' => 'https://law.alimtyazgcc.com',

  // Long random text (40+ characters). Set once, keep it. Run: php law/tools/keygen.php
  'app_secret' => 'CHANGE_ME_TO_A_LONG_RANDOM_TEXT',

  // Case documents: a folder OUTSIDE public_html, e.g. /home/u000000000/law_files. Create it first.
  'files_dir' => __DIR__ . '/storage/files',
  // File encryption key (base64, 32 bytes). Run: php law/tools/keygen.php
  // KEEP AN OFFLINE COPY. Losing this key means losing every stored document.
  'files_key' => '',

  // Largest upload in MB. Hosting limits (upload_max_filesize, post_max_size) must allow it too.
  'max_upload_mb' => 20,
  // Optional antivirus: a ClamAV daemon reachable by PHP, e.g. '/var/run/clamav/clamd.ctl' or 'tcp://127.0.0.1:3310'.
  // When set, every upload is scanned and refused if the scanner cannot be reached. Shared hosting usually has none.
  'clamav_socket' => '',

  // Email for sign-in invitations: 'smtp' (Hostinger account), 'mail' (PHP mail) or 'log' (testing only → law/storage/mail.log)
  'mail_mode' => 'smtp',
  'smtp_host' => 'smtp.hostinger.com',
  'smtp_port' => 465,
  'smtp_user' => 'info@alimtyazgcc.com',
  'smtp_pass' => 'CHANGE_ME',
  'mail_from' => 'info@alimtyazgcc.com',
  'mail_from_name' => 'Al Imtyaz Law | الامتياز للمحاماة',

  // Two-step sign-in: each office can require it for everyone (Settings). true = always required for partners and office managers.
  'twofa_managers' => false,

  // Sign out after this many idle minutes
  'session_idle_minutes' => 60,

  // Daily reminders. In hPanel → Advanced → Cron Jobs, once a day (e.g. 06:00):
  //   wget -q -O /dev/null "https://law.alimtyazgcc.com/cron/daily?key=YOUR-LONG-RANDOM-KEY"
  // or with SSH:  php law/tools/daily.php       Empty = the web address is switched off.
  'cron_key' => '',

  // Provisioning API for the Al Imtyaz platform (server to server, signed). 64 random hex characters from
  // php law/tools/keygen.php; put the same value in the platform's config.php under law_api.secret. Empty = off.
  'api_secret' => '',

  // Support visits: hash of the passphrase that only Al Imtyaz support holds (php law/tools/support_pass.php).
  // Empty = support visits cannot start, even with a link from an office.
  'support_pass_hash' => '',

  // First install without SSH: open https://law.alimtyazgcc.com/setup?key=THIS-VALUE to check the server and create
  // the demo office and the first office. 40+ random characters. DELETE this line when done (the page then says "not found").
  'setup_key' => '',

  // Country pack for this deployment. One deployment (and one database) per country.
  'country' => 'OM',
];
