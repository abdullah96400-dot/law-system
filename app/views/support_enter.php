<section class="card narrow">
  <h1><?= h(t('support_visit')) ?></h1>
  <p><?= h(t('support_enter_intro', $office['name_en'] . ' — ' . $office['name_ar'])) ?></p>
  <p class="muted small"><?= h(t('support_enter_until')) ?> <span dir="ltr"><?= h(date('d/m/Y H:i', strtotime($g['expires_at']))) ?></span></p>
  <?php if ($err): ?><div class="flash err" role="alert"><?= h(t($err)) ?></div><?php endif; ?>
  <form method="post" class="form-grid"><?= csrf_field() ?>
    <div class="full"><label for="pass"><?= h(t('support_pass')) ?></label><input id="pass" name="pass" type="password" autocomplete="off" required></div>
    <div class="full"><button class="btn"><?= h(t('support_start')) ?></button></div>
  </form>
</section>
