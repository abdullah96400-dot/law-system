<?php $v = fn($k) => h($d[$k] ?? ''); $sel = fn($k, $x) => (string)($d[$k] ?? '') === (string)$x ? ' selected' : ''; ?>
<section class="card mid">
  <p class="muted small"><a href="/matters/<?= (int)$m['id'] ?>#actions" dir="ltr"><?= h($m['ref']) ?></a> — <?= h($m['title']) ?></p>
  <h1><?= h($title) ?></h1>
  <?php if ($err): ?><div class="flash err" role="alert"><?= h(t($err)) ?></div><?php endif; ?>
  <?php if ($a && $a['verified_by']): ?><p class="note small"><?= h(t('act_edit_resets')) ?></p><?php endif; ?>
  <p class="muted small"><?= h(t('req_note')) ?></p>
  <form method="post" action="/matters/<?= (int)$m['id'] ?>/actions<?= $a ? '/' . (int)$a['id'] . '/edit' : '' ?>" class="form-grid"><?= csrf_field() ?>
    <fieldset class="full"><legend><?= h(t('act_kind')) ?> <?= req_mark() ?></legend>
      <?php foreach (ACTION_KINDS as $k): ?><label class="radio"><input type="radio" name="kind" value="<?= $k ?>"<?= ($d['kind'] ?? '') === $k ? ' checked' : '' ?> required> <?= h(t('acts_' . $k)) ?></label><?php endforeach; ?><?= ferr($err, 'kind') ?>
    </fieldset>
    <div class="full"><label for="title"><?= h(t('act_title')) ?> <?= req_mark() ?></label><input id="title" name="title" required maxlength="200" value="<?= $v('title') ?>" placeholder="<?= h(t('act_title_ph')) ?>"<?= fattr($err, 'title') ?>><?= ferr($err, 'title') ?></div>
    <div><label for="action_on"><?= h(t('act_date')) ?> <?= req_mark() ?></label><input id="action_on" name="action_on" type="date" dir="ltr" required value="<?= $v('action_on') ?>"<?= fattr($err, 'action_on') ?>><?= ferr($err, 'action_on') ?></div>
    <div><label for="court_id"><?= h(t('matter_court')) ?></label><select id="court_id" name="court_id"><option value=""><?= h(t('none')) ?></option><?php foreach (lookups('court') as $l): ?><option value="<?= (int)$l['id'] ?>"<?= $sel('court_id', $l['id']) ?>><?= h(lookup_label($l)) ?></option><?php endforeach; ?></select></div>
    <div class="full"><label for="details"><?= h(t('act_details')) ?></label><textarea id="details" name="details" rows="5" placeholder="<?= h(t('act_details_ph')) ?>"><?= $v('details') ?></textarea></div>
    <fieldset class="full"><legend><?= h(t('appt_source')) ?> <?= req_mark() ?></legend>
      <p class="muted small"><?= h(t('act_source_help')) ?></p>
      <div class="form-grid">
        <div><label for="source"><?= h(t('appt_source_where')) ?></label><select id="source" name="source" required<?= fattr($err, 'source') ?>><option value=""><?= h(t('choose')) ?></option><?php foreach (APPT_SOURCES as $s): ?><option value="<?= $s ?>"<?= $sel('source', $s) ?>><?= h(t('src_' . $s)) ?></option><?php endforeach; ?></select><?= ferr($err, 'source') ?></div>
        <div><label for="source_note"><?= h(t('appt_source_note')) ?></label><input id="source_note" name="source_note" maxlength="300" value="<?= $v('source_note') ?>"></div>
      </div>
    </fieldset>
    <p class="full note small"><?= h(t('act_no_calc')) ?></p>
    <div class="full row"><button class="btn"><?= h(t('save')) ?></button><a class="btn ghost" href="/matters/<?= (int)$m['id'] ?>#actions"><?= h(t('cancel')) ?></a></div>
  </form>
</section>
