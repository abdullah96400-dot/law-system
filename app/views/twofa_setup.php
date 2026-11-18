<section class="card narrow">
  <h1><?= h(t('2fa_setup')) ?></h1>
  <p><?= h(t('2fa_setup_why')) ?></p>
  <ol class="steps">
    <li><?= h(t('2fa_step1')) ?></li>
    <li><?= h(t('2fa_step2')) ?>
      <div class="secret" dir="ltr"><code><?= h(trim(chunk_split($secret, 4, ' '))) ?></code></div>
      <p class="small muted"><?= h(t('2fa_step2_link')) ?> <a href="<?= h($uri) ?>" dir="ltr"><?= h(t('2fa_open_app')) ?></a></p>
    </li>
    <li><?= h(t('2fa_step3')) ?></li>
  </ol>
  <?php if ($err): ?><div class="flash err" role="alert"><?= h(t($err)) ?></div><?php endif; ?>
  <form method="post" action="/login/2fa-setup">
    <?= csrf_field() ?>
    <label for="code"><?= h(t('2fa_code')) ?></label>
    <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" dir="ltr" maxlength="10" required>
    <button class="btn"><?= h(t('2fa_activate')) ?></button>
  </form>
</section>
