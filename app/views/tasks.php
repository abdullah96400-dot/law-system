<?php $today = date('Y-m-d'); $q = fn(array $x) => '/tasks?' . http_build_query(array_merge($f, $x)); ?>
<div class="page-head">
  <div><h1><?= h(t('mod_tasks')) ?></h1><p class="small" role="status"><?= h(t('results_count', $total)) ?></p></div>
  <?php if (can('tasks', 'create') && !office_readonly()): ?><a class="btn" href="/tasks/new"><?= icon('plus') ?><?= h(t('task_new')) ?></a><?php endif; ?>
</div>
<section class="card">
  <nav class="tabs" aria-label="<?= h(t('mod_tasks')) ?>">
    <a href="<?= h($q(['scope' => 'mine', 'page' => 1])) ?>"<?= $f['scope'] === 'mine' ? ' aria-current="page"' : '' ?>><?= h(t('task_mine')) ?></a>
    <?php if (can('tasks', 'view')): ?><a href="<?= h($q(['scope' => 'all', 'page' => 1])) ?>"<?= $f['scope'] === 'all' ? ' aria-current="page"' : '' ?>><?= h(t('task_all')) ?></a><?php endif; ?>
  </nav>
  <nav class="tabs" aria-label="<?= h(t('status')) ?>">
    <?php foreach (['open', 'overdue', 'done'] as $st): ?><a href="<?= h($q(['state' => $st, 'page' => 1])) ?>"<?= $f['state'] === $st ? ' aria-current="page"' : '' ?>><?= h(t('tstate_' . $st)) ?></a><?php endforeach; ?>
  </nav>
  <?php if (!$list): ?><?= empty_state(t('tasks_empty'), '', null, null, 'tasks') ?><?php else: ?>
  <div class="table-wrap">
  <table class="stack" data-sortable>
    <caption class="sr"><?= h(t('mod_tasks')) ?></caption>
    <thead><tr><th scope="col" data-sort><?= h(t('task_title')) ?></th><th scope="col" data-sort><?= h(t('task_due')) ?></th><th scope="col" data-sort><?= h(t('task_assignee')) ?></th><th scope="col" data-sort><?= h(t('task_priority')) ?></th><th scope="col" data-sort><?= h(t('status')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($list as $x): $late = $x['due_on'] && $x['due_on'] < $today && in_array($x['status'], ['open', 'in_progress'], true); ?>
      <tr>
        <td data-label="<?= h(t('task_title')) ?>"><a href="/tasks/<?= (int)$x['id'] ?>"><?= h($x['title']) ?></a><?php if ($x['matter_ref']): ?><span class="sub ref" dir="ltr"><?= h($x['matter_ref']) ?></span><?php endif; ?></td>
        <td data-label="<?= h(t('task_due')) ?>" data-v="<?= h($x['due_on'] ?? '9999') ?>"><?php if ($x['due_on']): ?><span dir="ltr" class="<?= $late ? 'late' : '' ?>"><?= h(date('d/m/Y', strtotime($x['due_on']))) ?></span><?= $late ? ' <span class="tag late">' . h(t('overdue')) . '</span>' : '' ?><?php else: ?>—<?php endif; ?></td>
        <td data-label="<?= h(t('task_assignee')) ?>"><?= h(staff_name((int)$x['assignee_id'])) ?></td>
        <td data-label="<?= h(t('task_priority')) ?>"><span class="pri pri-<?= h($x['priority']) ?>"><?= h(t('tp_' . $x['priority'])) ?></span></td>
        <td data-label="<?= h(t('status')) ?>"><span class="badge-s <?= ['done' => 'b-done', 'in_progress' => 'b-blue', 'open' => $late ? 'b-late' : 'b-open', 'cancelled' => 'b-cancelled'][$x['status']] ?? '' ?>"><?= h(t('ts_' . $x['status'])) ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</section>
