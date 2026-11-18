<?php $wa = preg_replace('/\D/', '', $c['whatsapp']); ?>
<section class="s-sec"><div class="s-wrap s-narrow">
  <h1><?= h(t('site_contact')) ?></h1>
  <dl class="s-facts">
    <?php if ($c['phone'] !== ''): ?><dt><?= h(t('sr_phone')) ?></dt><dd><a href="tel:<?= h(preg_replace('/[^\d+]/', '', $c['phone'])) ?>" dir="ltr"><?= h($c['phone']) ?></a></dd><?php endif; ?>
    <?php if ($wa !== ''): ?><dt>WhatsApp</dt><dd><a href="https://wa.me/<?= h($wa) ?>" dir="ltr" rel="noopener" target="_blank"><?= h($c['whatsapp']) ?></a></dd><?php endif; ?>
    <?php if ($c['email'] !== ''): ?><dt><?= h(t('sr_email')) ?></dt><dd><a href="mailto:<?= h($c['email']) ?>" dir="ltr"><?= h($c['email']) ?></a></dd><?php endif; ?>
    <?php if (site_t($c, 'address') !== ''): ?><dt><?= h(t('site_address')) ?></dt><dd><?= h(site_t($c, 'address')) ?><?php if ($c['map_url'] !== ''): ?><br><a href="<?= h($c['map_url']) ?>" rel="noopener" target="_blank"><?= h(t('site_open_map')) ?></a><?php endif; ?></dd><?php endif; ?>
    <?php if (site_t($c, 'hours') !== ''): ?><dt><?= h(t('site_hours')) ?></dt><dd><?= h(site_t($c, 'hours')) ?></dd><?php endif; ?>
  </dl>
  <?php if ($services): ?><p><?= h(t('site_contact_services')) ?> <a href="<?= h($base) ?>/services"><?= h(t('site_services')) ?></a></p><?php endif; ?>
</div></section>
