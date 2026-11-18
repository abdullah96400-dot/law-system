<?php $ed = !office_readonly() && can('documents', 'edit'); $v = fn($k) => h($d[$k] ?? '');
$vers = $t ? tpl_doc_versions((int)$t['id']) : [];
[$vis, $p] = linked_visible_sql(current_staff());
$mlist = $t && can('documents', 'create') ? orows('SELECT m.id, m.ref, m.title FROM matters m WHERE m.office_id = :office AND m.closed_at IS NULL AND ' . linked_matter_sql($vis) . ' ORDER BY m.id DESC LIMIT 200', $p) : []; ?>
<p class="muted small"><a href="/templates"><?= h(t('mod_templates')) ?></a></p>
<div class="grid2 wide-first">
<section class="card">
  <h1><?= h($title) ?><?= $t ? ' <span class="tag">v' . (int)$t['version'] . '</span>' : '' ?></h1>
  <?php if ($err): ?><div class="flash err" role="alert"><?= h(t($err)) ?><?= $bad ? ': ' . h(implode(', ', array_map(fn($k) => '{{' . $k . '}}', $bad))) : '' ?></div><?php endif; ?>
  <?php if ($ed): ?>
  <form method="post" enctype="multipart/form-data" class="form-grid"><?= csrf_field() ?>
    <div><label for="name"><?= h(t('tpl_name')) ?> <?= req_mark() ?></label><input id="name" name="name" required maxlength="160" value="<?= $v('name') ?>"<?= fattr($err, 'name') ?>><?= ferr($err, 'name') ?></div>
    <div><label for="kind"><?= h(t('tpl_kind')) ?> <?= req_mark() ?></label><select id="kind" name="kind" required<?= fattr($err, 'kind') ?>><?php foreach (TEMPLATE_KINDS as $k): ?><option value="<?= $k ?>"<?= ($d['kind'] ?? '') === $k ? ' selected' : '' ?>><?= h(t('tk_' . $k)) ?></option><?php endforeach; ?></select><?= ferr($err, 'kind') ?></div>
    <div class="full"><label for="body"><?= h(t('tpl_body')) ?> <?= req_mark() ?></label>
      <textarea id="body" name="body" rows="16" class="mono-ish"<?= fattr($err, 'body') ?>><?= $v('body') ?></textarea><?= ferr($err, 'body') ?>
      <p class="muted small"><?= h(t('tpl_body_help')) ?></p></div>
    <div class="full"><label for="file"><?= h(t('tpl_upload_txt')) ?></label><input id="file" name="file" type="file" accept=".txt,text/plain"<?= fattr($err, 'file') ?>><?= ferr($err, 'file') ?><p class="muted small"><?= h(t('tpl_upload_help')) ?></p></div>
    <?php if ($t): ?><div class="full"><label class="radio"><input type="checkbox" name="active" value="1"<?= !empty($d['active']) ? ' checked' : '' ?>> <?= h(t('tpl_active')) ?></label></div><?php endif; ?>
    <div class="full row"><button class="btn"><?= h(t('save')) ?></button><a class="btn ghost" href="/templates"><?= h(t('cancel')) ?></a></div>
  </form>
  <?php else: ?><p class="pre"><?= h($t['body'] ?? '') ?></p><?php endif; ?>
</section>
<aside>
  <section class="card">
    <h2><?= h(t('tpl_fields')) ?></h2>
    <p class="muted small"><?= h(t('tpl_fields_help')) ?></p>
    <ul class="plain one small fields-list"><?php foreach (TEMPLATE_FIELDS as $k): ?><li><code dir="ltr">{{<?= $k ?>}}</code> <?= h(t('tf_' . $k)) ?></li><?php endforeach; ?></ul>
  </section>
  <?php if ($mlist): ?>
  <section class="card">
    <h2><?= h(t('tpl_use')) ?></h2>
    <form method="get" action="/compose" class="form-grid">
      <div class="full"><label for="cm"><?= h(t('appt_matter')) ?></label><select id="cm" name="matter" required><?php foreach ($mlist as $mm): ?><option value="<?= (int)$mm['id'] ?>"><?= h($mm['ref'] . ' — ' . $mm['title']) ?></option><?php endforeach; ?></select></div>
      <input type="hidden" name="template" value="<?= (int)$t['id'] ?>">
      <div class="full"><button class="btn small"><?= h(t('tpl_preview_fill')) ?></button></div>
    </form>
  </section>
  <?php endif; ?>
  <?php if ($vers): ?>
  <section class="card">
    <h2><?= h(t('tpl_versions')) ?></h2>
    <ul class="plain one small"><?php foreach ($vers as $x): ?><li><details><summary>v<?= (int)$x['version'] ?> · <span dir="ltr"><?= h(fmt_dt($x['created_at'])) ?></span> · <?= h(staff_name($x['created_by'] ? (int)$x['created_by'] : null)) ?></summary><p class="pre small"><?= h($x['body']) ?></p></details></li><?php endforeach; ?></ul>
  </section>
  <?php endif; ?>
</aside>
</div>
