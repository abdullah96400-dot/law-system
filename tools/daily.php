<?php
/* Daily reminders for every office (same as /cron/daily):  php law/tools/daily.php [--date=YYYY-MM-DD] */
require __DIR__ . '/_cli.php';
mig_up();
foreach (reminders_all(arg('date')) as $code => $n) out("$code: {$n['notifications']} new reminders, {$n['emails']} emails");
