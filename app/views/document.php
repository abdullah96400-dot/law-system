<?php $me = current_staff(); $ro = office_readonly(); $v = doc_version($d); $vers = doc_versions((int)$d['id']); $m = $d['matter_id'] ? matter_for((int)$d['matter_id'], $me) : null;
$c = $d['contact_id'] ? contact_get((int)$d['contact_id']) : null; $dl = can('documents', 'download'); ?>
<section class="card">
  <div class="head-row">
    <div><h1><?= h($d['title']) ?></h1>
      <p class="muted"><?= h(lookup_name($d['category_id'] ? (int)$d['category_id'] : null, 'doc_category')) ?> · <?= h(t('doc_version')) ?> <?= (int)$d['current_version'] ?><?= $d['archived'] ? ' · <span class="tag">' . h(t('archived')) . '</span>' : '' ?><?= $d['source'] === 'client' ? ' · <span class="tag">' . h(t('doc_from_client')) . '</span>' : '' ?></p></div>
    <div class="row">
      <?php if ($dl && $v): ?><a class="btn" href="/documents/<?= (int)$d['id'] ?>/download"><?= h(t('download')) ?></a>
        <?php if (isset(DOC_PREVIEW[$v['ext']])): ?><a class="btn ghost" href="/documents/<?= (int)$d['id'] ?>/preview" target="_blank" rel="noopener"><?= h(t('preview')) ?></a><?php endif; ?><?php endif; ?>
      <?php if (!$ro && can('documents', 'delete') && !is_support($me)): ?><form method="post" action="/trash/move" class="inline" data-confirm="<?= h(t('trash_confirm')) ?>"><?= csrf_field() ?><input type="hidden" name="kind" value="document"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><button class="link small danger"><?= icon('trash') ?> <?= h(t('trash_delete')) ?></button></form><?php endif; ?>
    </div>
  </div>
  <?php if ($err): ?><div class="flash err" role="alert"><?= h(t($err)) ?></div><?php endif; ?>
  <?php if ($link): ?><div class="flash ok" role="status"><b><?= h(t('share_link_ready')) ?></b><br><input readonly dir="ltr" value="<?= h($link) ?>" aria-label="<?= h(t('share_link_ready')) ?>" class="mt"><p class="small"><?= h(t('share_link_once')) ?></p></div><?php endif; ?>
  <dl class="facts">
    <?php if ($m): ?><dt><?= h(t('appt_matter')) ?></dt><dd><a href="/matters/<?= (int)$m['id'] ?>" dir="ltr"><?= h($m['ref']) ?></a> — <?= h($m['title']) ?></dd><?php endif; ?>
    <?php if ($c): ?><dt><?= h(t('ct_client')) ?></dt><dd><?= can('clients', 'view') ? '<a href="/clients/' . (int)$c['id'] . '">' . h(contact_label($c)) . '</a>' : h(contact_label($c)) ?></dd><?php endif; ?>
    <?php if ($v): ?><dt><?= h(t('doc_file')) ?></dt><dd><span dir="ltr"><?= h($v['orig_name']) ?></span> · <?= h(human_size((int)$v['size'])) ?></dd><?php endif; ?>
    <dt><?= h(t('doc_in_portal')) ?></dt><dd><?= h($d['portal_visible'] ? t('yes') : t('no')) ?></dd>
    <dt><?= h(t('added')) ?></dt><dd><?= h($d['created_by'] ? staff_name((int)$d['created_by']) : t('doc_from_client')) ?> · <span dir="ltr"><?= h(fmt_dt($d['created_at'])) ?></span></dd>
  </dl>
</section>

<section class="card">
  <h2><?= h(t('doc_versions')) ?></h2>
  <ul class="plain one">
    <?php foreach ($vers as $x): ?><li><b dir="ltr">v<?= (int)$x['version'] ?></b> <span dir="ltr"><?= h($x['orig_name']) ?></span> · <?= h(human_size((int)$x['size'])) ?> · <?= h($x['uploaded_by'] ? staff_name((int)$x['uploaded_by']) : t('doc_from_client')) ?> · <span dir="ltr"><?= h(fmt_dt($x['uploaded_at'])) ?></span>
      <?= $x['note'] ? ' — ' . h($x['note']) : '' ?><?php if ($dl && (int)$x['version'] !== (int)$d['current_version']): ?> <a class="small" href="/documents/<?= (int)$d['id'] ?>/download?v=<?= (int)$x['version'] ?>"><?= h(t('download')) ?></a><?php endif; ?></li><?php endforeach; ?>
  </ul>
  <?php if (!$ro && can('documents', 'edit') && !$d['archived']): ?>
    <details><summary><?= h(t('doc_new_version')) ?></summary>
      <form method="post" action="/documents/<?= (int)$d['id'] ?>/version" enctype="multipart/form-data" class="form-grid"><?= csrf_field() ?>
        <div class="full"><label for="vfile"><?= h(t('doc_file')) ?></label><input id="vfile" name="file" type="file" required></div>
        <div class="full"><label for="vnote"><?= h(t('doc_version_note')) ?></label><input id="vnote" name="note" maxlength="300"></div>
        <div class="full"><button class="btn small"><?= h(t('doc_upload')) ?></button></div></form></details>
  <?php endif; ?>
</section>

<?php if (!$ro && can('documents', 'edit')): ?>
<section class="card">
  <h2><?= h(t('doc_details')) ?></h2>
  <form method="post" action="/documents/<?= (int)$d['id'] ?>/edit" class="form-grid"><?= csrf_field() ?><input type="hidden" name="portal_form" value="1">
    <div class="full"><label for="etitle"><?= h(t('doc_title')) ?></label><input id="etitle" name="title" maxlength="200" value="<?= h($d['title']) ?>"></div>
    <div><label for="ecat"><?= h(t('doc_category')) ?></label><select id="ecat" name="category_id"><option value=""><?= h(t('none')) ?></option><?php foreach (lookups('doc_category') as $l): ?><option value="<?= (int)$l['id'] ?>"<?= (int)$d['category_id'] === (int)$l['id'] ? ' selected' : '' ?>><?= h(lookup_label($l)) ?></option><?php endforeach; ?></select></div>
    <?php if (can('documents', 'share') && $d['matter_id']): ?><div class="full"><label class="radio"><input type="checkbox" name="portal_visible" value="1"<?= $d['portal_visible'] ? ' checked' : '' ?>> <?= h(t('doc_portal_label')) ?></label><p class="muted small"><?= h(t('doc_portal_help')) ?></p></div><?php endif; ?>
    <div class="full"><button class="btn small"><?= h(t('save')) ?></button></div>
  </form>
</section>
<?php endif; ?>

<?php if (can('documents', 'share')): $shares = doc_shares((int)$d['id']); ?>
<section class="card">
  <h2><?= h(t('doc_share_links')) ?></h2>
  <p class="muted small"><?= h(t('doc_share_help')) ?></p>
  <?php if ($shares): ?><ul class="plain one">
    <?php foreach ($shares as $s): $live = !$s['revoked_at'] && strtotime($s['expires_at']) > time() && ($s['max_downloads'] === null || (int)$s['downloads'] < (int)$s['max_downloads']); ?>
      <li><?= h($s['label'] ?: t('doc_share_unnamed')) ?> — <?= h(t('share_until', date('d/m/Y H:i', strtotime($s['expires_at'])))) ?> · <?= h(t('share_downloads', (int)$s['downloads'], $s['max_downloads'] ?? '∞')) ?>
        <?php if ($live && !$ro): ?><form method="post" action="/documents/<?= (int)$d['id'] ?>/shares/<?= (int)$s['id'] ?>/revoke" class="inline" data-confirm="<?= h(t('share_confirm_revoke')) ?>"><?= csrf_field() ?><button class="link small"><?= h(t('share_revoke')) ?></button></form>
        <?php else: ?><span class="tag"><?= h($s['revoked_at'] ? t('share_revoked') : t('share_ended')) ?></span><?php endif; ?></li>
    <?php endforeach; ?></ul><?php endif; ?>
  <?php if (!$ro && !$d['archived']): ?>
    <form method="post" action="/documents/<?= (int)$d['id'] ?>/share" class="form-grid"><?= csrf_field() ?>
      <div><label for="slabel"><?= h(t('share_label')) ?></label><input id="slabel" name="label" maxlength="160" placeholder="<?= h(t('share_label_ph')) ?>"></div>
      <div><label for="sdays"><?= h(t('share_days')) ?></label><input id="sdays" name="days" type="number" min="1" max="<?= SHARE_MAX_DAYS ?>" value="7" dir="ltr"></div>
      <div><label for="smax"><?= h(t('share_max')) ?></label><input id="smax" name="max_downloads" type="number" min="1" max="100" dir="ltr" placeholder="∞"></div>
      <div class="full"><button class="btn small"><?= h(t('share_create')) ?></button></div>
    </form>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if (!$ro && can('documents', 'delete')): ?>
<section class="card">
  <form method="post" action="/documents/<?= (int)$d['id'] ?>/<?= $d['archived'] ? 'restore' : 'archive' ?>"<?= $d['archived'] ? '' : ' data-confirm="' . h(t('doc_confirm_archive')) . '"' ?>><?= csrf_field() ?><button class="btn ghost"><?= h(t($d['archived'] ? 'restore' : 'archive')) ?></button></form>
  <p class="muted small"><?= h(t('doc_archive_help')) ?></p>
</section>
<?php endif; ?>
