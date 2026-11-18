<section class="card narrow">
  <h1><?= h(t('set_password')) ?></h1>
  <p><?= h(t('invite_welcome', $inv['full_name'], office_name($office))) ?></p>
  <p class="muted small" dir="ltr"><?= h($inv['email']) ?></p>
  <?php if ($err): ?><div class="flash err" role="alert"><?= h(t($err)) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label for="password"><?= h(t('new_password')) ?></label>
    <input id="password" name="password" type="password" dir="ltr" autocomplete="new-password" minlength="10" required aria-describedby="pwhelp">
    <p id="pwhelp" class="muted small"><?= h(t('pw_rules')) ?></p>
    <label for="confirm"><?= h(t('confirm_password')) ?></label>
    <input id="confirm" name="confirm" type="password" dir="ltr" autocomplete="new-password" required>
    <button class="btn"><?= h(t('save_password')) ?></button>
  </form>
</section>
