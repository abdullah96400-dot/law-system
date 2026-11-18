<section class="card narrow">
  <h1><?= h(t('portal_settings')) ?></h1>
  <p class="muted small" dir="ltr"><?= h($u['email']) ?></p>
  <form method="post" action="<?= h($base) ?>/settings"><?= csrf_field() ?>
    <label for="lang"><?= h(t('language')) ?></label><select id="lang" name="lang"><option value="ar"<?= $u['lang'] === 'ar' ? ' selected' : '' ?>>العربية</option><option value="en"<?= $u['lang'] === 'en' ? ' selected' : '' ?>>English</option></select>
    <label class="radio mt"><input type="checkbox" name="notify" value="1"<?= $u['notify'] ? ' checked' : '' ?>> <?= h(t('portal_notify')) ?></label>
    <p class="muted small"><?= h(t('portal_notify_help')) ?></p>
    <button class="btn"><?= h(t('save')) ?></button>
  </form>
</section>
