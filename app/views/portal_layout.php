<?php $o = current_office(); $pu = current_portal_user(); $f = flash(); $base = portal_base(); ?>
<!doctype html>
<html lang="<?= lang() ?>" dir="<?= dir_attr() ?>" data-theme="<?= ui_theme() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= h(($title ?? '') . ' · ' . office_name($o)) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/law.css?v=16">
<script src="/assets/law.js?v=15" defer></script>
</head>
<body class="portal">
<a class="skip" href="#main"><?= h(t('skip_to_content')) ?></a>
<header class="top portal-top">
  <a class="brand" href="<?= h($base) ?>/"><?php if (office_logo_file($o)): ?><img class="p-logo" src="<?= h($base) ?>/logo.jpg" alt=""><?php else: ?><span class="mark" aria-hidden="true">⚖</span><?php endif; ?><span class="brand-t"><b><?= h(office_name($o)) ?></b><small><?= h(t('portal_name')) ?></small></span></a>
  <div class="tools">
    <?= theme_toggle(strtok($_SERVER['REQUEST_URI'] ?? '/', '?')) ?>
    <?php if ($pu): ?>
      <a class="link hide-phone" href="<?= h($base) ?>/"><?= h(t('portal_home')) ?></a>
      <a class="link hide-phone" href="<?= h($base) ?>/hearings"><?= h(t('ps_hearings')) ?></a>
      <a class="link hide-phone" href="<?= h($base) ?>/documents"><?= h(t('ps_documents')) ?></a>
      <a class="link hide-phone" href="<?= h($base) ?>/payments"><?= h(t('ps_payments')) ?></a>
      <a class="link" href="<?= h($base) ?>/settings"><?= h(t('portal_settings')) ?></a>
      <form method="post" action="<?= h($base) ?>/logout" class="inline"><?= csrf_field() ?><button class="link"><?= h(t('sign_out')) ?></button></form>
    <?php endif; ?>
  </div>
</header>
<?php if ($o['demo']): ?><div class="banner demo" role="status"><?= h(t('demo_banner')) ?></div><?php endif; ?>
<main id="main">
<?php if ($f): ?><div class="flash <?= $f[0] === 'err' ? 'err' : 'ok' ?>" role="<?= $f[0] === 'err' ? 'alert' : 'status' ?>"><span><?= h($f[1]) ?></span><button type="button" class="x" data-dismiss aria-label="<?= h(t('close')) ?>">×</button></div><?php endif; ?>
<?= $content ?>
</main>
<?php if ($pu): $cur = preg_replace('#^' . preg_quote($base, '#') . '#', '', strtok($_SERVER['REQUEST_URI'] ?? '/', '?')) ?: '/'; ?>
<nav class="bottom-nav p-bottom" aria-label="<?= h(t('portal_sections')) ?>">
  <?php foreach ([['/', 'home', 'portal_home'], ['/hearings', 'calendar', 'ps_hearings'], ['/documents', 'file', 'ps_documents'], ['/payments', 'wallet', 'ps_payments_short'], ['/settings', 'settings', 'portal_settings']] as [$hr, $ic, $lb]): ?>
    <a href="<?= h($base . $hr) ?>"<?= $cur === $hr ? ' aria-current="page"' : '' ?>><?= icon($ic) ?><span><?= h(t($lb)) ?></span></a>
  <?php endforeach; ?>
</nav>
<?php endif; ?>
<footer class="foot"><?= h(t('portal_footer', office_name($o))) ?></footer>
</body>
</html>
