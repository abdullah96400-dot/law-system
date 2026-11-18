<section class="s-sec"><div class="s-wrap">
  <h1><?= h(t('site_services')) ?></h1>
  <div class="s-grid"><?php foreach ($services as $x): ?>
    <a class="s-card" href="<?= h($base) ?>/services/<?= h($x['key']) ?>"><b><?= h(site_t($x, 'title')) ?></b><?php if (site_t($x, 'summary') !== ''): ?><span><?= h(site_t($x, 'summary')) ?></span><?php endif; ?><em><?= h(t('site_request_this')) ?></em></a>
  <?php endforeach; ?></div>
  <?php if (!$services): ?><p class="muted">—</p><?php endif; ?>
</div></section>
