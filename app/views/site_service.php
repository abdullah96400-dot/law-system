<?php $v = fn($k) => h((string)($d[$k] ?? '')); ?>
<section class="s-sec"><div class="s-wrap s-narrow">
  <p class="small"><a href="<?= h($base) ?>/services"><?= h(t('site_services')) ?></a></p>
  <h1><?= h(site_t($svc, 'title')) ?></h1>
  <?php if (site_t($svc, 'summary') !== ''): ?><p class="s-lead"><?= h(site_t($svc, 'summary')) ?></p><?php endif; ?>
  <?php if (site_t($svc, 'body') !== ''): ?><div class="s-text"><?= nl2br(h(site_t($svc, 'body'))) ?></div><?php endif; ?>
</div></section>
<section class="s-sec alt" id="request"><div class="s-wrap s-narrow">
  <h2><?= h(t('sr_form_title')) ?></h2>
  <p class="muted small"><?= h(t('sr_form_intro')) ?></p>
  <?php if ($err): ?><div class="flash err" role="alert"><span><?= h(t($err)) ?></span></div><?php endif; ?>
  <form method="post" action="<?= h($base) ?>/services/<?= h($svc['key']) ?>#request" class="s-form">
    <?= csrf_field() ?><input type="hidden" name="stamp" value="<?= h(site_form_stamp()) ?>">
    <div class="hp" aria-hidden="true"><label for="website">Website</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
    <p class="s-svc"><?= h(t('sr_service')) ?>: <b><?= h(site_t($svc, 'title')) ?></b></p>
    <label for="name"><?= h(t('sr_name')) ?></label><input id="name" name="name" required maxlength="160" autocomplete="name" value="<?= $v('name') ?>">
    <label for="phone"><?= h(t('sr_phone')) ?></label><input id="phone" name="phone" type="tel" dir="ltr" required maxlength="40" autocomplete="tel" inputmode="tel" value="<?= $v('phone') ?>">
    <label for="email"><?= h(t('sr_email')) ?></label><input id="email" name="email" type="email" dir="ltr" maxlength="190" autocomplete="email" value="<?= $v('email') ?>">
    <label for="message"><?= h(t('sr_message')) ?></label><textarea id="message" name="message" rows="4" maxlength="2000" aria-describedby="msg-help"><?= $v('message') ?></textarea>
    <p id="msg-help" class="muted small"><?= h(t('sr_message_help')) ?></p>
    <label class="s-consent"><input type="checkbox" name="consent" value="1" required> <?= h(t('sr_consent')) ?></label>
    <button class="s-btn"><?= h(t('sr_send')) ?></button>
  </form>
</div></section>
