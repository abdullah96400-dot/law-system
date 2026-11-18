<?php $sel = fn($k, $x) => (int)($d[$k] ?? 0) === (int)$x ? ' selected' : '';
[$vis, $p] = linked_visible_sql(current_staff());
$mlist = can('matters', 'view') ? orows('SELECT m.id, m.ref, m.title FROM matters m WHERE m.office_id = :office AND ' . linked_matter_sql($vis) . ' ORDER BY m.closed_at IS NOT NULL, m.id DESC', $p) : []; ?>
<section class="card">
  <h1><?= h(t('doc_upload')) ?></h1>
  <?php if (!files_key()): ?><div class="flash err" role="alert"><?= h(t('doc_no_key')) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="flash err" role="alert"><?= h(t($err)) ?></div><?php endif; ?>
  <form method="post" action="/documents/upload" enctype="multipart/form-data" class="form-grid">
    <?= csrf_field() ?>
    <div class="full"><label for="file"><?= h(t('doc_file')) ?></label><input id="file" name="file" type="file" required accept=".<?= implode(',.', array_keys(DOC_TYPES)) ?>" aria-describedby="file-help">
      <p id="file-help" class="muted small"><?= h(t('doc_file_help', implode(', ', array_keys(DOC_TYPES)), (int)cfg('max_upload_mb', 20))) ?></p></div>
    <div class="full"><label for="title"><?= h(t('doc_title')) ?></label><input id="title" name="title" maxlength="200" value="<?= h($d['title'] ?? '') ?>" placeholder="<?= h(t('doc_title_ph')) ?>"></div>
    <div><label for="category_id"><?= h(t('doc_category')) ?></label><select id="category_id" name="category_id"><option value=""><?= h(t('none')) ?></option><?php foreach (lookups('doc_category') as $l): ?><option value="<?= (int)$l['id'] ?>"<?= $sel('category_id', $l['id']) ?>><?= h(lookup_label($l)) ?></option><?php endforeach; ?></select></div>
    <div><label for="matter_id"><?= h(t('appt_matter')) ?></label><select id="matter_id" name="matter_id"><option value=""><?= h(t('appt_no_matter')) ?></option><?php foreach ($mlist as $m): ?><option value="<?= (int)$m['id'] ?>"<?= $sel('matter_id', $m['id']) ?>><?= h($m['ref'] . ' — ' . $m['title']) ?></option><?php endforeach; ?></select></div>
    <?php if (!empty($d['contact_id']) && ($c = contact_get((int)$d['contact_id']))): ?><input type="hidden" name="contact_id" value="<?= (int)$c['id'] ?>"><p class="full muted small"><?= h(t('doc_for_contact', contact_label($c))) ?></p><?php endif; ?>
    <p class="full muted small"><?= h(t('doc_privacy_note')) ?></p>
    <div class="full row"><button class="btn"><?= h(t('doc_upload')) ?></button></div>
  </form>
</section>
