<?php
/* Create a demo office filled with made-up data, for sales demos and training.
     php law/tools/demo_office.php [--code=DEMO-01]
   Prints the sign-in emails and one-time passwords. The office is marked demo:
   every screen shows the demo banner. Remove it later by setting it suspended
   (php law/tools/office_status.php --code=DEMO-01 --status=suspended). */
require __DIR__ . '/_cli.php';
mig_up();
if (!files_key()) fail('files_key is not set in config.php (php law/tools/keygen.php).');
$code = (string)arg('code', 'DEMO-' . date('md'));
$r = demo_create($code);
if (isset($r['error'])) { $GLOBALS['LANG_FORCE'] = 'en'; fail(t($r['error'])); }
out("Demo office {$r['code']} created (id {$r['office_id']}).");
out('Sign-in (save these now, they are not shown again):');
foreach ($r['logins'] as [$role, $email, $pw]) out(sprintf('  %-15s %-40s %s', $role, $email, $pw));
out('Client portal: ' . abs_url('/p/' . strtolower($r['code']) . '/login') . '  (email: ' . $r['portal'] . ', the code goes to that address)');
out('The managing partner and office manager set up two-step sign-in on first sign-in.');
