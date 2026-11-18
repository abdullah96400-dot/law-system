<div class="page-head">
  <div><h1><?= h(t('notices')) ?></h1><p><?= h(t('notices_help')) ?></p></div>
</div>
<nav class="ptabs ready ltabs" aria-label="<?= h(t('status')) ?>">
  <?php foreach (['draft', 'sent', 'all'] as $k): ?><a href="/finance/notices?status=<?= $k ?>"<?= $st === $k ? ' aria-current="page" class="on"' : '' ?>><?= h(t($k === 'all' ? 'all' : 'nst_' . $k)) ?></a><?php endforeach; ?>
</nav>
<section class="card">
  <?php if (!$list): ?><?= empty_state(t('notices_empty'), t('notices_empty_help'), null, null, 'bell') ?><?php else: ?>
  <div class="due-grid"><?php foreach ($list as $x): $late = $x['due_on'] && $x['due_on'] < date('Y-m-d'); ?>
    <article class="due-card">
      <div class="due-top"><span class="p-ico t-<?= $x['status'] === 'sent' ? 'green' : ($late ? 'red' : 'gold') ?>"><?= icon('bell') ?></span><span class="nst nst-<?= h($x['status']) ?>"><?= h(t('nst_' . $x['status'])) ?></span></div>
      <span class="muted small"><?= h(t('notice_amount')) ?></span><b class="due-amt"><?= h(money((int)$x['amount'])) ?></b>
      <dl class="due-facts"><dt><?= h(t('ct_client')) ?></dt><dd><?= h(lang() === 'ar' ? ($x['name_ar'] ?: $x['name_en']) : ($x['name_en'] ?: $x['name_ar'])) ?></dd>
        <dt><?= h(t('notice_due_on')) ?></dt><dd dir="ltr" class="<?= $late ? 'late' : '' ?>"><?= $x['due_on'] ? h(date('d/m/Y', strtotime($x['due_on']))) : '—' ?></dd>
        <dt><?= h(t('appt_matter')) ?></dt><dd><span class="ref" dir="ltr"><?= h($x['matter_ref']) ?></span></dd></dl>
      <?= $x['auto'] ? '<span class="tag">' . h(t('notice_auto')) . '</span>' : '' ?>
      <?php if ($x['status'] === 'draft'): ?><a class="btn small ok" href="/finance/notices/<?= (int)$x['id'] ?>"><?= icon('bell') ?><?= h(t('notice_send_client')) ?></a>
      <?php else: ?><a class="btn small ghost" href="/finance/notices/<?= (int)$x['id'] ?>"><?= h(t('open')) ?></a><span class="muted small"><?= $x['channel'] ? h(t('nch_' . $x['channel'])) : '' ?></span><?php endif; ?>
    </article>
  <?php endforeach; ?></div>
  <?php endif; ?>
</section>
