<p class="muted small"><a href="/matters/<?= (int)$m['id'] ?>#docs" dir="ltr"><?= h($m['ref']) ?></a> — <?= h($m['title']) ?></p>
<section class="card">
  <div class="head-row"><div><h1><?= h(t('tpl_compose')) ?></h1><p class="muted"><?= h($tp['name']) ?> · <?= h(t('tk_' . $tp['kind'])) ?> · v<?= (int)$tp['version'] ?></p></div></div>
  <?php if ($err): ?><div class="flash err" role="alert"><?= h(t($err)) ?></div><?php endif; ?>
  <?php if ($missing): ?><div class="flash warn" role="status"><?= h(t('tpl_missing', implode(t('list_sep'), array_map(fn($k) => t('tf_' . $k), $missing)))) ?></div><?php endif; ?>
  <p class="note small"><?= h(t('tpl_review_note')) ?></p>
  <form method="post" action="/matters/<?= (int)$m['id'] ?>/compose" class="form-grid"><?= csrf_field() ?>
    <input type="hidden" name="template" value="<?= (int)$tp['id'] ?>">
    <div class="full"><label for="title"><?= h(t('doc_title')) ?></label><input id="title" name="title" maxlength="200" value="<?= h($doc_title) ?>"></div>
    <div class="full"><label for="body"><?= h(t('tpl_filled_text')) ?></label><textarea id="body" name="body" rows="20" class="doc-paper"><?= h($text) ?></textarea></div>
    <div class="full row"><button class="btn"><?= icon('file') ?><?= h(t('tpl_save_pdf')) ?></button><a class="btn ghost" href="/matters/<?= (int)$m['id'] ?>#docs"><?= h(t('cancel')) ?></a></div>
  </form>
</section>
