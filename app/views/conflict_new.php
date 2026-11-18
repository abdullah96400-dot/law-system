<section class="card">
  <h1><?= h(t('check_new')) ?></h1>
  <p class="muted"><?= h(t('check_new_intro')) ?></p>
  <?php if ($err): ?><div class="flash err" role="alert"><?= h(t($err)) ?></div><?php endif; ?>
  <form method="post" action="/conflicts/new">
    <?= csrf_field() ?>
    <label for="subject"><?= h(t('check_subject')) ?></label>
    <input id="subject" name="subject" maxlength="300" required value="<?= h($in['subject']) ?>" aria-describedby="subject-help"<?= fattr($err, 'subject') ?>><?= ferr($err, 'subject') ?>
    <p id="subject-help" class="muted small"><?= h(t('check_subject_help')) ?></p>
    <?php foreach (CHECK_ROLES as $r): ?>
      <label for="n-<?= $r ?>"><?= h(t('check_role_' . $r)) ?></label>
      <textarea id="n-<?= $r ?>" name="<?= $r ?>" rows="3" aria-describedby="n-<?= $r ?>-help"><?= h($in[$r]) ?></textarea>
      <p id="n-<?= $r ?>-help" class="muted small"><?= h(t('check_role_help_' . $r)) ?></p>
    <?php endforeach; ?>
    <button class="btn"><?= h(t('check_run')) ?></button>
  </form>
</section>
