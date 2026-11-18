<?php /* Public website of one office. Colors come from {base}/site.css (no inline styles). */
$S = $GLOBALS['SITE']; $o = $S['o']; $c = $S['c']; $base = $S['base']; $cur = $S['sub'];
$nav = [['/', 'site_home'], ['/about', 'site_about'], ['/lawyers', 'site_lawyers'], ['/services', 'site_services'], ['/contact', 'site_contact']];
if (!site_visible($c, 'lawyers')) $nav = array_values(array_filter($nav, fn($n) => $n[0] !== '/lawyers'));
$other = lang() === 'ar' ? 'en' : 'ar'; $f = flash(); ?>
<!doctype html>
<html lang="<?= lang() ?>" dir="<?= dir_attr() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php if ($S['preview'] || $o['demo']): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
<title><?= h(($title ?? '') === office_name($o) ? office_name($o) : ($title . ' · ' . office_name($o))) ?></title>
<meta name="description" content="<?= h(mb_substr(site_t($c, 'tagline') ?: site_t($c, 'about'), 0, 160)) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/law.css?v=17">
<link rel="stylesheet" href="<?= h($base) ?>/site.css">
<script src="/assets/law.js?v=15" defer></script>
</head>
<body class="site">
<a class="skip" href="#main"><?= h(t('skip_to_content')) ?></a>
<?php if ($S['preview']): ?><div class="banner preview" role="status"><?= h(t('site_preview_banner')) ?> <a href="/site"><?= h(t('site_back_admin')) ?></a></div><?php endif; ?>
<?php if ($o['demo']): ?><div class="banner demo" role="status"><?= h(t('demo_banner')) ?></div><?php endif; ?>
<header class="s-top">
  <div class="s-wrap s-bar">
    <a class="s-brand" href="<?= h($base) ?>/"><?php if (office_logo_file($o)): ?><img src="<?= h($base) ?>/logo.jpg" alt=""><?php endif; ?><b><?= h(office_name($o)) ?></b></a>
    <?php if ($c['lang'] === 'both'): ?><a class="s-lang" href="?lang=<?= $other ?>" lang="<?= $other ?>"><?= $other === 'ar' ? 'العربية' : 'English' ?></a><?php endif; ?>
  </div>
  <nav class="s-wrap s-nav" aria-label="<?= h(t('site_menu')) ?>">
    <?php foreach ($nav as [$href, $label]): $on = $href === '/' ? $cur === '/' : str_starts_with($cur, $href); ?>
      <a href="<?= h($base . $href) ?>"<?= $on ? ' aria-current="page"' : '' ?>><?= h(t($label)) ?></a>
    <?php endforeach; ?>
  </nav>
</header>
<main id="main" class="s-main">
<?php if ($f): ?><div class="s-wrap"><div class="flash <?= $f[0] === 'err' ? 'err' : 'ok' ?>" role="<?= $f[0] === 'err' ? 'alert' : 'status' ?>"><span><?= h($f[1]) ?></span></div></div><?php endif; ?>
<?= $content ?>
</main>
<footer class="s-foot">
  <div class="s-wrap">
    <p><b><?= h(office_name($o)) ?></b><?php if ($c['license_no'] !== ''): ?> · <?= h(t('site_license', $c['license_no'])) ?><?php endif; ?></p>
    <?php if (site_t($c, 'address') !== ''): ?><p><?= h(site_t($c, 'address')) ?></p><?php endif; ?>
    <p class="small"><?= h(t('site_disclaimer')) ?></p>
  </div>
</footer>
</body>
</html>
