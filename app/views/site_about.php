<section class="s-sec"><div class="s-wrap s-narrow">
  <h1><?= h(t('site_about')) ?></h1>
  <?php if (site_t($c, 'about') !== ''): ?><p class="s-text"><?= nl2br(h(site_t($c, 'about'))) ?></p><?php else: ?><p class="muted">—</p><?php endif; ?>
  <?php if ($c['license_no'] !== ''): ?><p class="small muted"><?= h(t('site_license', $c['license_no'])) ?></p><?php endif; ?>
</div></section>
