<section class="s-sec"><div class="s-wrap">
  <h1><?= h(t('site_lawyers')) ?></h1>
  <div class="s-grid"><?php foreach ($lawyers as $x): ?>
    <article class="s-card static"><b><?= h(site_t($x, 'name')) ?></b><?php if (site_t($x, 'title') !== ''): ?><span class="s-role"><?= h(site_t($x, 'title')) ?></span><?php endif; ?>
      <?php if (site_t($x, 'bio') !== ''): ?><p><?= nl2br(h(site_t($x, 'bio'))) ?></p><?php endif; ?></article>
  <?php endforeach; ?></div>
  <?php if (!$lawyers): ?><p class="muted">—</p><?php endif; ?>
</div></section>
