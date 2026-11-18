<section class="card narrow">
  <h1><?= h(t('portal_sign_in')) ?></h1>
  <p class="muted"><?= h(t('portal_sign_in_intro', office_name($o))) ?></p>
  <form method="post" action="<?= h($base) ?>/login"><?= csrf_field() ?>
    <label for="email"><?= h(t('email')) ?></label>
    <input id="email" name="email" type="email" dir="ltr" autocomplete="email" required>
    <button class="btn"><?= h(t('portal_send_code')) ?></button>
  </form>
  <form method="post" action="/lang" class="mt"><?= csrf_field() ?><input type="hidden" name="back" value="<?= h($base) ?>/login">
    <button class="link small" name="lang" value="<?= lang() === 'ar' ? 'en' : 'ar' ?>"><?= lang() === 'ar' ? 'English' : 'العربية' ?></button></form>
</section>
