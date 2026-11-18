<?php $tone = ['folder' => 'gold', 'users' => 'gold', 'shield' => 'gold', 'delegate' => 'gold', 'calendar' => 'gold', 'tasks' => 'gold', 'file' => 'gold', 'contract' => 'gold', 'wallet' => 'gold', 'chart' => 'gold', 'trash' => 'red', 'settings' => 'gold', 'home' => 'gold'];
$nu = notif_unread($me); ?>
<div class="page-head"><div><h1><?= h(t('nav_more')) ?></h1><p><?= h($me['full_name']) ?> · <?= h(t('role_' . $me['role'])) ?></p></div></div>
<?php foreach (nav_groups($me) as $g => $items): $vis = array_values(array_filter($items, fn($i) => $i[3] && $i[0] !== '/')); if (!$vis) continue; ?>
  <h2 class="sec-title"><?= h(t($g ?: 'home')) ?></h2>
  <nav class="hubs list-hubs"><?php foreach ($vis as [$href, $k, $ic]) echo hub_tile($href, $ic, $tone[$ic] ?? 'gold', t($k)); ?></nav>
<?php endforeach; ?>
<h2 class="sec-title"><?= h(t('nav_you')) ?></h2>
<nav class="hubs list-hubs">
  <?= hub_tile('/notifications', 'bell', 'gold', t('notifications'), '', $nu ?: null, $nu > 0) ?>
  <?php if (!is_support($me)): ?><?= hub_tile('/account', 'user', 'gold', t('account')) ?><?php endif; ?>
  <?= hub_tile('/help', 'help', 'gold', t('help')) ?>
</nav>
<form method="post" action="/logout" class="mt"><?= csrf_field() ?><button class="btn ghost"><?= icon('logout', 'flip') ?><?= h(t('sign_out')) ?></button></form>
