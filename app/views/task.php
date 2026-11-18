<?php $me = current_staff(); $ro = office_readonly(); $mine = (int)$t['assignee_id'] === (int)$me['id']; $act = !$ro && ($mine || can('tasks', 'edit'));
$open = in_array($t['status'], ['open', 'in_progress'], true); $m = $t['matter_id'] ? matter_for((int)$t['matter_id'], $me) : null;
$late = $open && $t['due_on'] && $t['due_on'] < date('Y-m-d'); ?>
<section class="card">
  <div class="head-row">
    <div><h1><?= h($t['title']) ?></h1>
      <p class="muted"><span class="pri pri-<?= h($t['priority']) ?>"><?= h(t('tp_' . $t['priority'])) ?></span> · <?= h(t('ts_' . $t['status'])) ?><?= $late ? ' · <span class="tag late">' . h(t('overdue')) . '</span>' : '' ?></p></div>
    <?php if (!$ro && can('tasks', 'edit') && $open): ?><a class="btn small ghost" href="/tasks/<?= (int)$t['id'] ?>/edit"><?= h(t('edit')) ?></a><?php endif; ?><?php if (!$ro && can('tasks', 'delete')): ?><form method="post" action="/trash/move" class="inline" data-confirm="<?= h(t('trash_confirm')) ?>"><?= csrf_field() ?><input type="hidden" name="kind" value="task"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><button class="link small danger"><?= icon('trash') ?> <?= h(t('trash_delete')) ?></button></form><?php endif; ?>
  </div>
  <?php if ($err): ?><div class="flash err" role="alert"><?= h(t($err)) ?></div><?php endif; ?>
  <dl class="facts">
    <?php if ($m): ?><dt><?= h(t('appt_matter')) ?></dt><dd><a href="/matters/<?= (int)$m['id'] ?>" dir="ltr"><?= h($m['ref']) ?></a> — <?= h($m['title']) ?></dd><?php endif; ?>
    <dt><?= h(t('task_assignee')) ?></dt><dd><?= h(staff_name((int)$t['assignee_id'])) ?></dd>
    <dt><?= h(t('task_reviewer')) ?></dt><dd><?= h(staff_name($t['reviewer_id'] ? (int)$t['reviewer_id'] : null)) ?></dd>
    <dt><?= h(t('task_due')) ?></dt><dd dir="ltr"><?= $t['due_on'] ? h(date('d/m/Y', strtotime($t['due_on']))) : '—' ?></dd>
    <dt><?= h(t('task_recur')) ?></dt><dd><?= h(t('tr_' . $t['recur'])) ?></dd>
    <dt><?= h(t('added')) ?></dt><dd><?= h(staff_name($t['created_by'] ? (int)$t['created_by'] : null)) ?> · <span dir="ltr"><?= h(fmt_dt($t['created_at'])) ?></span></dd>
    <?php if ($t['escalated_at']): ?><dt><?= h(t('task_escalated')) ?></dt><dd dir="ltr"><?= h(fmt_dt($t['escalated_at'])) ?></dd><?php endif; ?>
  </dl>
  <?php if ($t['details']): ?><p class="pre mt"><?= h($t['details']) ?></p><?php endif; ?>
  <?php if (!$open && $t['done_at']): ?><p class="mt"><b><?= h(t('task_done_by', staff_name((int)$t['done_by']))) ?></b> · <span dir="ltr"><?= h(fmt_dt($t['done_at'])) ?></span></p><?php if ($t['done_note']): ?><p class="pre"><?= h($t['done_note']) ?></p><?php endif; ?><?php endif; ?>
</section>
<?php if ($act): ?>
<section class="card">
  <?php if ($open): ?>
    <form method="post" action="/tasks/<?= (int)$t['id'] ?>/done"><?= csrf_field() ?>
      <label for="note"><?= h(t('task_done_note')) ?></label><textarea id="note" name="note" rows="2"></textarea>
      <button class="btn"><?= h(t('task_mark_done')) ?></button></form>
    <div class="row mt">
      <?php if ($t['status'] === 'open'): ?><form method="post" action="/tasks/<?= (int)$t['id'] ?>/status"><?= csrf_field() ?><input type="hidden" name="status" value="in_progress"><button class="btn small ghost"><?= h(t('task_start')) ?></button></form><?php endif; ?>
      <form method="post" action="/tasks/<?= (int)$t['id'] ?>/status" data-confirm="<?= h(t('task_confirm_cancel')) ?>"><?= csrf_field() ?><input type="hidden" name="status" value="cancelled"><button class="btn small ghost"><?= h(t('task_cancel')) ?></button></form>
    </div>
  <?php else: ?>
    <form method="post" action="/tasks/<?= (int)$t['id'] ?>/status"><?= csrf_field() ?><input type="hidden" name="status" value="open"><button class="btn small ghost"><?= h(t('task_reopen')) ?></button></form>
  <?php endif; ?>
</section>
<?php endif; ?>
