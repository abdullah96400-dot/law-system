<section class="card narrow">
  <h1><?= h(t('2fa')) ?></h1>
  <p class="muted"><?= h(t('2fa_enter')) ?></p>
  <?php if ($err): ?><div class="flash err" role="alert"><?= h(t($err)) ?></div><?php endif; ?>
  <form method="post" action="/login/2fa">
    <?= csrf_field() ?>
    <label for="code"><?= h(t('2fa_code')) ?></label>
    <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" dir="ltr" maxlength="10" required autofocus>
    <button class="btn"><?= h(t('confirm')) ?></button>
  </form>
  <p class="muted small"><?= h(t('2fa_lost')) ?></p>
</section>
