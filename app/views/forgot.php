<section class="card narrow">
  <h1><?= h(t('forgot_title')) ?></h1>
  <?php if ($sent): ?>
    <div class="flash ok" role="status"><?= h(t('forgot_sent')) ?></div>
    <p><a href="/login"><?= h(t('back_to_sign_in')) ?></a></p>
  <?php else: ?>
    <p class="muted"><?= h(t('forgot_intro')) ?></p>
    <form method="post" action="/login/forgot"><?= csrf_field() ?>
      <label for="email"><?= h(t('email')) ?></label>
      <input id="email" name="email" type="email" dir="ltr" autocomplete="username" required>
      <button class="btn"><?= h(t('forgot_send')) ?></button>
    </form>
    <p class="small"><a href="/login"><?= h(t('back_to_sign_in')) ?></a></p>
  <?php endif; ?>
</section>
