<?php
/* Create an office and invite its managing partner.
     php law/tools/create_office.php --code=SLL-001 --name-ar="مكتب ..." --name-en="... Law Office" \
         --owner-email=partner@example.com --owner-name="..." [--plan=Business] [--max-users=10] [--demo] [--send-mail] [--lang=en]
   Prints the one-time invitation link. With --send-mail it is also emailed. */
require __DIR__ . '/_cli.php';
mig_up();
$r = create_office((string)arg('code', ''), (string)arg('name-ar', ''), (string)arg('name-en', ''), (string)arg('owner-email', ''), (string)arg('owner-name', ''), [
  'plan' => arg('plan'), 'max_users' => arg('max-users') !== null ? (int)arg('max-users') : null, 'demo' => (bool)arg('demo'),
  'send_mail' => (bool)arg('send-mail'), 'lang' => arg('lang', 'ar') === 'en' ? 'en' : 'ar']);
if (isset($r['error'])) { $GLOBALS['LANG_FORCE'] = 'en'; fail(t($r['error'])); }
out("Office created (id {$r['office_id']}).");
out('Invitation link for the managing partner (works once, 72 hours):');
out(abs_url('/invite/' . $r['token']));
