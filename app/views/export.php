<section class="card">
  <h1><?= h(t('export_title')) ?></h1>
  <p><?= h(t('export_intro')) ?></p>
  <ul><li><?= h(t('export_has_data')) ?></li><li><?= h(t('export_has_docs')) ?></li><li><?= h(t('export_left_out')) ?></li></ul>
  <p class="note small"><?= h(t('export_note')) ?></p>
  <?php if ($err): ?><div class="flash err" role="alert"><?= h(t($err)) ?></div><?php endif; ?>
  <form method="post" action="/settings/export" class="form-grid"><?= csrf_field() ?>
    <div><label for="password"><?= h(t('export_pw')) ?></label><input id="password" name="password" type="password" minlength="10" autocomplete="new-password" required aria-describedby="pw-help"><p id="pw-help" class="muted small"><?= h(t('export_pw_help')) ?></p></div>
    <div class="full"><button class="btn"><?= h(t('export_go')) ?></button></div>
  </form>
</section>
