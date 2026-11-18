<section class="card narrow">
  <h1><?= h(t('portal_enter_code')) ?></h1>
  <p class="muted"><?= h(t('portal_code_intro', PORTAL_CODE_MINUTES)) ?></p>
  <?php if ($err): ?><div class="flash err" role="alert"><?= h(t($err)) ?></div><?php endif; ?>
  <form method="post" action="<?= h($base) ?>/code"><?= csrf_field() ?>
    <label for="code"><?= h(t('2fa_code')) ?></label>
    <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" dir="ltr" maxlength="10" required autofocus>
    <button class="btn"><?= h(t('confirm')) ?></button>
  </form>
  <p class="small mt"><a href="<?= h($base) ?>/login"><?= h(t('portal_code_again')) ?></a></p>
</section>
