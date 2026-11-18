<?php $me = current_staff(); $office = $me ? current_office() : null; $f = flash(); $route = $GLOBALS['ROUTE'] ?? '/'; $ar = lang() === 'ar'; ?>
<!doctype html>
<html lang="<?= lang() ?>" dir="<?= dir_attr() ?>" data-theme="<?= ui_theme() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="<?= ui_theme() === 'dark' ? '#0B1626' : '#152A45' ?>">
<title><?= h(($title ?? '') . ' · ' . t('product')) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/law.css?v=16">
<script src="/assets/law.js?v=15" defer></script>
</head>
<?php
$flashHtml = $f ? '<div class="flash ' . ($f[0] === 'err' ? 'err' : ($f[0] === 'warn' ? 'warn' : 'ok')) . '" role="' . ($f[0] === 'err' ? 'alert' : 'status') . '"><span>' . h($f[1]) . '</span><button type="button" class="x" data-dismiss aria-label="' . h(t('close')) . '">×</button></div>' : '';
$langForm = '<form method="post" action="/lang" class="lang-seg">' . csrf_field() . '<input type="hidden" name="back" value="' . h($route) . '">'
  . ($ar ? '<b>العربية</b><span aria-hidden="true">|</span><button class="lang-btn" name="lang" value="en" lang="en">EN</button>' : '<button class="lang-btn" name="lang" value="ar" lang="ar">العربية</button><span aria-hidden="true">|</span><b>EN</b>') . '</form>';
if (!$me): /* ---------- signed out ---------- */ ?>
<body class="out">
<a class="skip" href="#main"><?= h(t('skip_to_content')) ?></a>
<header class="top">
  <a class="brand" href="/"><span class="logo"><img src="/assets/logo-white.png?v=1" alt="Al Imtyaz" width="80" height="38"></span><span class="brand-t"><b><?= h(t('product_short')) ?></b></span></a>
  <div class="tools"><?= theme_toggle($route) ?><?= $langForm ?></div>
</header>
<main id="main"><?= $flashHtml ?><?= $content ?></main>
<footer class="foot"><?= h(t('footer_note')) ?></footer>
</body>
</html>
<?php return; endif;

/* ---------- signed in: sidebar + top bar ---------- */
$nu = notif_unread($me);
$admin = can('staff', 'view') || can('settings', 'view') || can('audit', 'view');
$on = function (string $href) use ($route): bool {
  if ($href === '/') return $route === '/';
  if ($href === '/settings') return (bool)array_filter(['/settings', '/staff', '/roles', '/audit', '/import'], fn($p) => $route === $p || str_starts_with($route, $p . '/'));
  return $route === $href || str_starts_with($route, $href . '/');
};
$groups = nav_groups($me, $admin);
$cr = crumbs($route, (string)($title ?? ''));
$canSearch = can('matters', 'view') || can('clients', 'view') || can('documents', 'view');
?>
<body class="shell">
<a class="skip" href="#main"><?= h(t('skip_to_content')) ?></a>
<aside class="side" id="side" aria-label="<?= h(t('main_menu')) ?>">
  <a class="side-head" href="/"><img src="/assets/logo-white.png?v=1" alt="Al Imtyaz" width="63" height="30"><span><b><?= h(t('product_short')) ?></b><small><?= h(office_name($office)) ?></small></span></a>
  <nav class="side-nav">
    <?php foreach ($groups as $g => $items): $vis = array_filter($items, fn($i) => $i[3]); if (!$vis) continue; ?>
      <?php if ($g): ?><div class="side-grp"><?= h(t($g)) ?></div><?php endif; ?>
      <?php foreach ($vis as [$href, $k, $ic]): ?><a href="<?= $href ?>"<?= $on($href) ? ' aria-current="page"' : '' ?>><?= icon($ic) ?><span><?= h(t($k)) ?></span></a><?php endforeach; ?>
    <?php endforeach; ?>
    <div class="side-grp"><?= h(t('nav_you')) ?></div>
    <a href="/notifications"<?= $on('/notifications') ? ' aria-current="page"' : '' ?>><?= icon('bell') ?><span><?= h(t('notifications')) ?></span><?php if ($nu): ?><span class="count"><?= $nu > 99 ? '99+' : $nu ?></span><?php endif; ?></a>
    <a href="/help"<?= $on('/help') ? ' aria-current="page"' : '' ?>><?= icon('help') ?><span><?= h(t('help')) ?></span></a>
  </nav>
  <div class="side-foot"><b><?= h($me['full_name']) ?></b><?= h(t('role_' . $me['role'])) ?></div>
</aside>
<div class="side-scrim" data-side-close></div>
<div class="main-col">
  <header class="topbar">
    <button type="button" class="icon-btn side-toggle" data-side-toggle aria-controls="side" aria-expanded="false" aria-label="<?= h(t('open_menu')) ?>"><?= icon('menu') ?></button>
    <a class="top-logo" href="/" aria-label="<?= h(t('home')) ?>"><img src="/assets/logo-white.png?v=1" alt="Al Imtyaz" width="63" height="30"></a>
    <nav class="crumbs" aria-label="<?= h(t('you_are_here')) ?>"><ol>
      <?php foreach ($cr as $i => [$lbl, $href]): ?><li><?= $href ? '<a href="' . h($href) . '">' . h($lbl) . '</a>' : '<span aria-current="page">' . h($lbl) . '</span>' ?></li><?php endforeach; ?>
    </ol></nav>
    <?php if ($canSearch): ?>
    <a class="icon-btn search-btn" href="/search" aria-label="<?= h(t('gsearch')) ?>"><?= icon('search') ?></a>
    <form class="gsearch" method="get" action="/search" role="search"><?= icon('search') ?><label class="sr" for="gq"><?= h(t('gsearch')) ?></label><input id="gq" name="q" type="search" placeholder="<?= h(t('gsearch_ph')) ?>" value="<?= h($route === '/search' ? (string)($_GET['q'] ?? '') : '') ?>" autocomplete="off"></form>
    <?php endif; ?>
    <div class="top-tools">
      <?= theme_toggle($route) ?>
      <?= $langForm ?>
      <a class="icon-btn" href="/notifications" aria-label="<?= h(t('notifications')) ?><?= $nu ? ' (' . $nu . ')' : '' ?>"><?= icon('bell') ?><?php if ($nu): ?><span class="badge"><?= $nu > 99 ? '99+' : $nu ?></span><?php endif; ?></a>
      <details class="profile" data-pop>
        <summary aria-label="<?= h(t('your_menu')) ?>"><span class="avatar" aria-hidden="true"><?= h(initials($me['full_name'])) ?></span><span class="p-name"><?= h($me['full_name']) ?></span></summary>
        <div class="menu-pop" role="menu">
          <div class="who"><b><?= h($me['full_name']) ?></b><span><?= h(t('role_' . $me['role'])) ?> · <?= h(office_name($office)) ?></span></div>
          <?php if (!is_support($me)): ?><a href="/account" role="menuitem"><?= icon('user') ?><?= h(t('account')) ?></a><?php endif; ?>
          <a href="/help" role="menuitem"><?= icon('help') ?><?= h(t('help')) ?></a>
          <form method="post" action="/logout"><?= csrf_field() ?><button role="menuitem"><?= icon('logout', 'flip') ?><?= h(t('sign_out')) ?></button></form>
        </div>
      </details>
    </div>
  </header>
  <?php if ($office && $office['status'] === 'readonly'): ?><div class="banner warn" role="status"><?= icon('lock') ?><?= h(t('office_readonly_banner')) ?></div><?php endif; ?>
  <?php if (is_support($me)): ?><div class="banner warn" role="status"><?= icon('lock') ?><?= h(t('support_banner', date('d/m H:i', strtotime($me['grant']['expires_at'])))) ?></div><?php endif; ?>
  <?php if ($office && $office['demo']): ?><div class="banner demo" role="status"><?= h(t('demo_banner')) ?></div><?php endif; ?>
  <main id="main" tabindex="-1"><?= $flashHtml ?><?= $content ?></main>
  <footer class="foot"><?= h(t('footer_note')) ?></footer>
</div>
<?php $quick = array_filter([[can('matters', 'create') && !office_readonly(), '/matters/new', 'qa_matter', 'folder'], [can('conflicts', 'create') && !office_readonly(), '/conflicts/new', 'qa_check', 'shield'],
  [can('calendar', 'create') && !office_readonly(), '/calendar/new', 'qa_appt', 'calendar'], [can('tasks', 'create') && !office_readonly(), '/tasks/new', 'qa_task', 'tasks'],
  [can('clients', 'create') && !office_readonly(), '/clients/new', 'qa_client', 'users'], [can('documents', 'create') && !office_readonly(), '/documents/upload', 'qa_doc', 'upload']], fn($x) => $x[0]); ?>
<nav class="bottom-nav" aria-label="<?= h(t('main_menu')) ?>">
  <a href="/"<?= $on('/') ? ' aria-current="page"' : '' ?>><?= icon('home') ?><span><?= h(t('home')) ?></span></a>
  <?php if (can('matters', 'view')): ?><a href="/matters"<?= $on('/matters') ? ' aria-current="page"' : '' ?>><?= icon('folder') ?><span><?= h(t('mod_matters_short')) ?></span></a><?php else: ?><a href="/tasks"<?= $on('/tasks') ? ' aria-current="page"' : '' ?>><?= icon('tasks') ?><span><?= h(t('mod_tasks')) ?></span></a><?php endif; ?>
  <?php if ($quick): ?><details class="fab" data-pop><summary aria-label="<?= h(t('qa_new')) ?>"><span class="fab-b"><?= icon('plus') ?></span><span><?= h(t('qa_new')) ?></span></summary>
    <div class="fab-menu" role="menu"><?php foreach ($quick as [, $hh, $kk, $ii]): ?><a role="menuitem" href="<?= $hh ?>"><?= icon($ii) ?><?= h(t($kk)) ?></a><?php endforeach; ?></div></details><?php else: ?><span></span><?php endif; ?>
  <?php if (can('calendar', 'view')): ?><a href="/calendar"<?= $on('/calendar') ? ' aria-current="page"' : '' ?>><?= icon('calendar') ?><span><?= h(t('mod_calendar_short')) ?></span></a><?php else: ?><a href="/notifications"><?= icon('bell') ?><span><?= h(t('notifications')) ?></span></a><?php endif; ?>
  <a href="/more"<?= $on('/more') ? ' aria-current="page"' : '' ?>><?= icon('menu') ?><span><?= h(t('nav_more')) ?></span><?php if ($nu): ?><span class="dot-badge" aria-hidden="true"></span><?php endif; ?></a>
</nav>
<dialog class="dlg" id="confirm-dlg" aria-labelledby="dlg-t">
  <form method="dialog">
    <div class="dlg-body"><h2 id="dlg-t"><?= icon('alert') ?><?= h(t('confirm_title')) ?></h2><p id="dlg-m"></p></div>
    <div class="dlg-act"><button class="btn ghost" value="no"><?= h(t('cancel')) ?></button><button class="btn" value="yes" id="dlg-ok"><?= h(t('confirm_go')) ?></button></div>
  </form>
</dialog>
</body>
</html>
