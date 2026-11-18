<?php $v = fn($k) => h($d[$k] ?? ''); $sel = fn($k, $x) => (string)($d[$k] ?? '') === (string)$x ? ' selected' : '';
[$vis, $p] = linked_visible_sql(current_staff());
$mlist = can('matters', 'view') ? orows("SELECT m.id, m.ref, m.title FROM matters m WHERE m.office_id = :office AND m.closed_at IS NULL AND " . linked_matter_sql($vis) . ' ORDER BY m.id DESC', $p) : []; ?>
<section class="card">
  <h1><?= h($title) ?></h1>
  <?php if ($err): ?><div class="flash err" role="alert"><?= h(t($err)) ?></div><?php endif; ?>
  <form method="post" class="form-grid">
    <?= csrf_field() ?>
    <div class="full"><label for="title"><?= h(t('task_title')) ?></label><input id="title" name="title" required maxlength="200" value="<?= $v('title') ?>"<?= fattr($err, 'title') ?>><?= ferr($err, 'title') ?></div>
    <div class="full"><label for="details"><?= h(t('task_details')) ?></label><textarea id="details" name="details" rows="3"><?= $v('details') ?></textarea></div>
    <div class="full"><label for="matter_id"><?= h(t('appt_matter')) ?></label><select id="matter_id" name="matter_id"<?= fattr($err, 'matter_id') ?>><option value=""><?= h(t('appt_no_matter')) ?></option><?php foreach ($mlist as $m): ?><option value="<?= (int)$m['id'] ?>"<?= $sel('matter_id', $m['id']) ?>><?= h($m['ref'] . ' — ' . $m['title']) ?></option><?php endforeach; ?></select><?= ferr($err, 'matter_id') ?></div>
    <div><label for="assignee_id"><?= h(t('task_assignee')) ?></label><select id="assignee_id" name="assignee_id" required<?= fattr($err, 'assignee_id') ?>><?php foreach (active_staff() as $s): ?><option value="<?= (int)$s['id'] ?>"<?= $sel('assignee_id', $s['id']) ?>><?= h($s['full_name']) ?></option><?php endforeach; ?></select><?= ferr($err, 'assignee_id') ?></div>
    <div><label for="reviewer_id"><?= h(t('task_reviewer')) ?></label><select id="reviewer_id" name="reviewer_id"><option value=""><?= h(t('none')) ?></option><?php foreach (active_staff() as $s): ?><option value="<?= (int)$s['id'] ?>"<?= $sel('reviewer_id', $s['id']) ?>><?= h($s['full_name']) ?></option><?php endforeach; ?></select></div>
    <div><label for="due_on"><?= h(t('task_due')) ?></label><input id="due_on" name="due_on" type="date" dir="ltr" value="<?= $v('due_on') ?>"<?= fattr($err, 'due_on') ?>><?= ferr($err, 'due_on') ?></div>
    <div><label for="priority"><?= h(t('task_priority')) ?></label><select id="priority" name="priority"><?php foreach (TASK_PRIORITIES as $x): ?><option value="<?= $x ?>"<?= $sel('priority', $x) ?>><?= h(t('tp_' . $x)) ?></option><?php endforeach; ?></select></div>
    <div><label for="recur"><?= h(t('task_recur')) ?></label><select id="recur" name="recur"><?php foreach (TASK_RECUR as $x): ?><option value="<?= $x ?>"<?= $sel('recur', $x) ?>><?= h(t('tr_' . $x)) ?></option><?php endforeach; ?></select></div>
    <div class="full row"><button class="btn"><?= h(t('save')) ?></button><a class="btn ghost" href="<?= $t ? '/tasks/' . (int)$t['id'] : '/tasks' ?>"><?= h(t('cancel')) ?></a></div>
  </form>
</section>
