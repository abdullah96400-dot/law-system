<?php $staff = active_staff(); $ro = office_readonly(); ?>
<div class="page-head"><div><h1><?= h(t('mod_trash')) ?></h1><p><?= h(t('trash_help')) ?></p></div>
  <div class="row"><a class="btn ghost small" href="/settings/backups"><?= icon('shield') ?><?= h(t('bk_title')) ?></a></div></div>
<section class="card">
  <form method="get" action="/trash" class="filters" data-auto>
    <div><label for="kind"><?= h(t('col_kind')) ?></label><select id="kind" name="kind"><option value=""><?= h(t('all')) ?></option><?php foreach (TRASH_KINDS as $k): ?><option value="<?= $k ?>"<?= $f['kind'] === $k ? ' selected' : '' ?>><?= h(t('tk2_' . $k)) ?></option><?php endforeach; ?></select></div>
    <div><label for="who"><?= h(t('trash_by')) ?></label><select id="who" name="who"><option value="0"><?= h(t('all')) ?></option><?php foreach ($staff as $s): ?><option value="<?= (int)$s['id'] ?>"<?= $f['who'] === (int)$s['id'] ? ' selected' : '' ?>><?= h($s['full_name']) ?></option><?php endforeach; ?></select></div>
    <div><label for="from"><?= h(t('from')) ?></label><input id="from" name="from" type="date" dir="ltr" value="<?= h($f['from']) ?>"></div>
    <div><label class="radio"><input type="checkbox" name="restored" value="1"<?= $f['restored'] ? ' checked' : '' ?>> <?= h(t('trash_show_restored')) ?></label></div>
    <div><button class="btn small"><?= h(t('show')) ?></button></div>
  </form>
  <?php if (!$list): ?><?= empty_state(t('trash_empty'), t('trash_empty_help'), null, null, 'trash') ?><?php else: ?>
  <div class="table-wrap"><table data-sortable>
    <thead><tr><th data-sort><?= h(t('col_kind')) ?></th><th data-sort><?= h(t('trash_item')) ?></th><th data-sort><?= h(t('trash_by')) ?></th><th data-sort><?= h(t('trash_when')) ?></th><th><span class="sr"><?= h(t('actions')) ?></span></th></tr></thead>
    <tbody><?php foreach ($list as $x): ?><tr>
      <td><span class="tag"><?= h(t('tk2_' . $x['kind'])) ?></span></td><td><?= h($x['label']) ?></td>
      <td><?= h(staff_name((int)$x['deleted_by'])) ?></td><td data-v="<?= h($x['deleted_at']) ?>" dir="ltr"><?= h(fmt_dt($x['deleted_at'])) ?></td>
      <td><?php if ($x['restored_at']): ?><span class="muted small"><?= h(t('trash_restored_by', staff_name((int)$x['restored_by']), fmt_dt($x['restored_at']))) ?></span>
        <?php elseif (!$ro): ?><form method="post" action="/trash/<?= (int)$x['id'] ?>/restore" data-confirm="<?= h(t('trash_restore_confirm')) ?>"><?= csrf_field() ?><button class="btn small ghost"><?= h(t('trash_restore')) ?></button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody>
  </table></div><?php endif; ?>
  <p class="note small mt"><?= h(t('trash_money_note')) ?></p>
</section>
