<section class="card narrow">
  <h1><?= h(t('sign_in')) ?></h1>
  <p class="muted"><?= h(t('sign_in_intro')) ?></p>
  <?php if ($err): ?><div class="flash err" role="alert"><?= h(t($err)) ?></div><?php endif; ?>
  <form method="post" action="/login" novalidate>
    <?= csrf_field() ?>
    <label for="email"><?= h(t('email')) ?></label>
    <input id="email" name="email" type="email" dir="ltr" autocomplete="username" required value="<?= h($email) ?>">
    <label for="password"><?= h(t('password')) ?></label>
    <input id="password" name="password" type="password" dir="ltr" autocomplete="current-password" required>
    <button class="btn"><?= h(t('sign_in')) ?></button>
  </form>
  <p class="small"><a href="/login/forgot"><?= h(t('forgot_link')) ?></a></p>
</section>
