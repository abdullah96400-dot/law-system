<section class="s-hero"><div class="s-wrap">
  <h1><?= h(office_name($o)) ?></h1>
  <?php if (site_t($c, 'tagline') !== ''): ?><p class="s-lead"><?= h(site_t($c, 'tagline')) ?></p><?php endif; ?>
  <div class="s-cta"><a class="s-btn" href="<?= h($base) ?>/services"><?= h(t('site_see_services')) ?></a><a class="s-btn ghost" href="<?= h($base) ?>/contact"><?= h(t('site_contact')) ?></a></div>
</div></section>
<?php if ($services): ?>
<section class="s-sec"><div class="s-wrap">
  <h2><?= h(t('site_services')) ?></h2>
  <div class="s-grid"><?php foreach (array_slice($services, 0, 6) as $x): ?>
    <a class="s-card" href="<?= h($base) ?>/services/<?= h($x['key']) ?>"><b><?= h(site_t($x, 'title')) ?></b><?php if (site_t($x, 'summary') !== ''): ?><span><?= h(site_t($x, 'summary')) ?></span><?php endif; ?><em><?= h(t('site_request_this')) ?></em></a>
  <?php endforeach; ?></div>
  <?php if (count($services) > 6): ?><p><a href="<?= h($base) ?>/services"><?= h(t('site_all_services')) ?></a></p><?php endif; ?>
</div></section>
<?php endif; ?>
<?php if (site_t($c, 'about') !== ''): ?>
<section class="s-sec alt"><div class="s-wrap s-narrow">
  <h2><?= h(t('site_about')) ?></h2>
  <p class="s-text"><?= nl2br(h(mb_substr(site_t($c, 'about'), 0, 600))) ?><?= mb_strlen(site_t($c, 'about')) > 600 ? '…' : '' ?></p>
  <p><a href="<?= h($base) ?>/about"><?= h(t('site_read_more')) ?></a></p>
</div></section>
<?php endif; ?>
