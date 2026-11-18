<?php $v = fn($k) => h($d[$k] ?? ''); $sel = fn($k, $x) => (string)($d[$k] ?? '') === (string)$x ? ' selected' : '';
[$vis, $p] = linked_visible_sql(current_staff());
$mlist = can('matters', 'view') ? orows("SELECT m.id, m.ref, m.title FROM matters m WHERE m.office_id = :office AND m.closed_at IS NULL AND " . linked_matter_sql($vis) . ' ORDER BY m.id DESC', $p) : []; ?>
<section class="card">
  <h1><?= h($title) ?></h1>
  <?php if ($err): ?><div class="flash err" role="alert"><?= h(t($err)) ?></div><?php endif; ?>
  <?php if ($a && $a['reviewed_by']): ?><p class="note small"><?= h(t('appt_edit_clears_review')) ?></p><?php endif; ?>
  <form method="post" class="form-grid">
    <?= csrf_field() ?>
    <fieldset class="full"><legend><?= h(t('appt_kind')) ?></legend>
      <?php foreach (APPT_KINDS as $k): ?><label class="radio"><input type="radio" name="kind" value="<?= $k ?>"<?= ($d['kind'] ?? 'hearing') === $k ? ' checked' : '' ?>> <?= h(t('ak_' . $k)) ?></label><?php endforeach; ?>
    </fieldset>
    <div class="full"><label for="title"><?= h(t('appt_title')) ?></label><input id="title" name="title" required maxlength="200" value="<?= $v('title') ?>"<?= fattr($err, 'title') ?>><?= ferr($err, 'title') ?></div>
    <div><label for="date"><?= h(t('date')) ?></label><input id="date" name="date" type="date" dir="ltr" required value="<?= $v('date') ?>"<?= fattr($err, 'date') ?>><?= ferr($err, 'date') ?></div>
    <div><label for="time"><?= h(t('time')) ?></label><input id="time" name="time" type="time" dir="ltr" value="<?= $v('time') ?>"></div>
    <div class="full"><label for="matter_id"><?= h(t('appt_matter')) ?></label><select id="matter_id" name="matter_id"<?= fattr($err, 'matter_id') ?>><option value=""><?= h(t('appt_no_matter')) ?></option><?php foreach ($mlist as $m): ?><option value="<?= (int)$m['id'] ?>"<?= $sel('matter_id', $m['id']) ?>><?= h($m['ref'] . ' — ' . $m['title']) ?></option><?php endforeach; ?></select><?= ferr($err, 'matter_id') ?></div>
    <div><label for="court_id"><?= h(t('matter_court')) ?></label><select id="court_id" name="court_id"><option value=""><?= h(t('none')) ?></option><?php foreach (lookups('court') as $l): ?><option value="<?= (int)$l['id'] ?>"<?= $sel('court_id', $l['id']) ?>><?= h(lookup_label($l)) ?></option><?php endforeach; ?></select></div>
    <div><label for="circuit"><?= h(t('matter_circuit')) ?></label><input id="circuit" name="circuit" maxlength="120" value="<?= $v('circuit') ?>"></div>
    <div><label for="location"><?= h(t('appt_location')) ?></label><input id="location" name="location" maxlength="200" value="<?= $v('location') ?>"></div>
    <div><label for="lead_id"><?= h(t('appt_lead')) ?></label><select id="lead_id" name="lead_id" required<?= fattr($err, 'lead_id') ?>><?php foreach (active_staff() as $s): ?><option value="<?= (int)$s['id'] ?>"<?= $sel('lead_id', $s['id']) ?>><?= h($s['full_name']) ?></option><?php endforeach; ?></select><?= ferr($err, 'lead_id') ?></div>
    <fieldset class="full"><legend><?= h(t('appt_source')) ?></legend>
      <p class="muted small"><?= h(t('appt_source_help')) ?></p>
      <div class="form-grid">
        <div><label for="source"><?= h(t('appt_source_where')) ?></label><select id="source" name="source" required<?= fattr($err, 'source') ?>><option value=""><?= h(t('choose')) ?></option><?php foreach (APPT_SOURCES as $s): ?><option value="<?= $s ?>"<?= $sel('source', $s) ?>><?= h(t('src_' . $s)) ?></option><?php endforeach; ?></select><?= ferr($err, 'source') ?></div>
        <div><label for="source_note"><?= h(t('appt_source_note')) ?></label><input id="source_note" name="source_note" maxlength="300" value="<?= $v('source_note') ?>"></div>
      </div>
    </fieldset>
    <div><label for="remind_days"><?= h(t('appt_remind')) ?></label><input id="remind_days" name="remind_days" dir="ltr" value="<?= $v('remind_days') ?>" aria-describedby="remind-help"><p id="remind-help" class="muted small"><?= h(t('appt_remind_help')) ?></p></div>
    <div class="full row"><button class="btn"><?= h(t('save')) ?></button><a class="btn ghost" href="<?= $a ? '/calendar/' . (int)$a['id'] : '/calendar' ?>"><?= h(t('cancel')) ?></a></div>
  </form>
</section>
