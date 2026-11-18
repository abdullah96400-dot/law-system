<section class="card">
  <h1><?= h(t('account')) ?></h1>
  <dl class="facts">
    <dt><?= h(t('name')) ?></dt><dd><?= h($me['full_name']) ?></dd>
    <dt><?= h(t('email')) ?></dt><dd dir="ltr"><?= h($me['email']) ?></dd>
    <dt><?= h(t('role')) ?></dt><dd><?= h(t('role_' . $me['role'])) ?></dd>
    <dt><?= h(t('2fa')) ?></dt><dd><?= h($me['totp_secret'] ? t('on') : t('off')) ?></dd>
    <dt><?= h(t('last_login')) ?></dt><dd dir="ltr"><?= h(fmt_dt($me['last_login'])) ?></dd>
  </dl>
</section>

<?php if ($me['totp_secret'] && !row('SELECT require_2fa_all FROM offices WHERE id=?', [(int)$me['office_id']])['require_2fa_all']): ?>
<section class="card">
  <h2><?= h(t('twofa_off_h')) ?></h2>
  <p class="muted"><?= h(t('twofa_off_p')) ?></p>
  <form method="post" action="/account/2fa-off" class="narrow-form"><?= csrf_field() ?>
    <label for="cur2"><?= h(t('current_password')) ?></label>
    <input id="cur2" name="current" type="password" dir="ltr" autocomplete="current-password" required>
    <button class="btn ghost"><?= h(t('twofa_off_btn')) ?></button>
  </form>
</section>
<?php endif; ?>
<section class="card">
  <h2><?= h(t('change_password')) ?></h2>
  <?php if ($err): ?><div class="flash err" role="alert"><?= h(t($err)) ?></div><?php endif; ?>
  <form method="post" action="/account/password" class="narrow-form">
    <?= csrf_field() ?>
    <label for="current"><?= h(t('current_password')) ?></label>
    <input id="current" name="current" type="password" dir="ltr" autocomplete="current-password" required<?= fattr($err, 'current') ?>><?= ferr($err, 'current') ?>
    <label for="password"><?= h(t('new_password')) ?></label>
    <input id="password" name="password" type="password" dir="ltr" autocomplete="new-password" minlength="10" required aria-describedby="pwhelp"<?= fattr($err, 'password') ?>><?= ferr($err, 'password') ?>
    <p id="pwhelp" class="muted small"><?= h(t('pw_rules')) ?></p>
    <label for="confirm"><?= h(t('confirm_password')) ?></label>
    <input id="confirm" name="confirm" type="password" dir="ltr" autocomplete="new-password" required<?= fattr($err, 'confirm') ?>><?= ferr($err, 'confirm') ?>
    <button class="btn"><?= h(t('save_password')) ?></button>
  </form>
</section>

<section class="card">
  <h2><?= h(t('sessions')) ?></h2>
  <p class="muted"><?= h(t('sessions_intro')) ?></p>
  <form method="post" action="/account/signout-others"><?= csrf_field() ?><button class="btn ghost"><?= h(t('signout_others')) ?></button></form>
</section>
