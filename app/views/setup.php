<?php
/* First-install page (law/setup). Shows what the server has, then creates the first office and/or the demo office.
   Remove setup_key from config.php when done: the page then answers "not found". */
$ok = fn(bool $c) => $c ? '<span class="badge-s b-green">OK</span>' : '<span class="badge-s b-late">✗</span>';
$fd = (string)cfg('files_dir', ''); if ($fd !== '' && !is_dir($fd)) @mkdir($fd, 0750, true);
$checks = [
  ['PHP ' . PHP_VERSION . ' (8.1+)', version_compare(PHP_VERSION, '8.1', '>=')],
  ['sodium (encryption)', function_exists('sodium_crypto_secretstream_xchacha20poly1305_init_push')],
  ['zip (full export)', class_exists('ZipArchive')],
  ['mbstring', function_exists('mb_strlen')],
  ['Database: ' . (is_sqlite() ? 'SQLite' : 'MySQL') . ' — tables created (' . count(mig_applied()) . '/' . count(mig_files()) . ')', count(mig_applied()) === count(mig_files())],
  ['Audit-log protection triggers', !is_file(storage_dir() . '/audit_triggers_missing.txt')],
  ['files_key set (32 bytes)', files_key() !== null],
  ['files_dir writable: ' . $fd, $fd !== '' && is_dir($fd) && is_writable($fd)],
  ['storage writable', is_writable(storage_dir())],
  ['site_url uses https', str_starts_with((string)cfg('site_url', ''), 'https://')],
  ['This page opened over https', !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'],
  ['Email: ' . cfg('mail_mode', 'log'), cfg('mail_mode', 'log') !== 'log'],
  ['cron_key set (daily reminders)', strlen((string)cfg('cron_key', '')) >= 24],
];
?>
<section class="card">
  <h1>Setup — Al Imtyaz Law</h1>
  <p class="muted small">This page works only while <code>setup_key</code> is in config.php. Remove it when you are done.</p>
  <?php if ($err): ?><div class="flash err" role="alert"><span><?= h($err === 'files_key' ? 'files_key is missing or wrong in config.php.' : $err) ?></span></div><?php endif; ?>
  <div class="table-wrap"><table><tbody>
    <?php foreach ($checks as [$label, $c]): ?><tr><td><?= h($label) ?></td><td class="num"><?= $ok($c) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</section>
<?php if ($out && isset($out['link'])): ?>
<section class="card"><h2>Office created</h2><p>The invitation was emailed to the managing partner. This one-time link does the same (valid 72 hours):</p><p class="break" dir="ltr"><code><?= h($out['link']) ?></code></p></section>
<?php endif; ?>
<?php if ($out && isset($out['logins'])): ?>
<section class="card"><h2>Demo office DEMO-01 created</h2><p class="note small">Save these now. They are not shown again.</p>
  <div class="table-wrap"><table><thead><tr><th>Role</th><th>Email</th><th>Password</th></tr></thead><tbody>
  <?php foreach ($out['logins'] as [$r, $e, $p]): ?><tr><td><?= h($r) ?></td><td dir="ltr"><?= h($e) ?></td><td dir="ltr"><code><?= h($p) ?></code></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <p class="small">Client portal: <span dir="ltr"><?= h(abs_url('/p/demo-01/login')) ?></span> — <?= h($out['portal']) ?> (the code goes to that address; example.com addresses do not receive mail).</p>
</section>
<?php endif; ?>
<?php if (!$demo): ?>
<section class="card"><h2>1. Demo office (made-up data)</h2>
  <form method="post" action="/setup"><?= csrf_field() ?><input type="hidden" name="key" value="<?= h($key) ?>"><input type="hidden" name="do" value="demo"><button class="btn">Create demo office DEMO-01</button></form>
</section>
<?php endif; ?>
<?php if (!$real): ?>
<section class="card"><h2>2. First real office</h2>
  <form method="post" action="/setup" class="form-grid"><?= csrf_field() ?><input type="hidden" name="key" value="<?= h($key) ?>"><input type="hidden" name="do" value="office">
    <div><label for="code">Office code</label><input id="code" name="code" dir="ltr" required pattern="[A-Za-z0-9-]{3,20}" placeholder="SLL-001"></div>
    <div><label for="name_ar">Office name (Arabic)</label><input id="name_ar" name="name_ar" dir="rtl" required></div>
    <div><label for="name_en">Office name (English)</label><input id="name_en" name="name_en" dir="ltr" required></div>
    <div><label for="name">Managing partner name</label><input id="name" name="name" required></div>
    <div><label for="email">Managing partner email</label><input id="email" name="email" type="email" dir="ltr" required></div>
    <div class="full"><button class="btn">Create office and send the invitation</button></div>
  </form>
</section>
<?php else: ?><section class="card"><p>A real office exists. Create more offices from the Al Imtyaz team app (Law offices) or with SSH (law/tools/create_office.php).</p></section><?php endif; ?>
