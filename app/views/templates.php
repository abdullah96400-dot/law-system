<?php $ed = !office_readonly() && can('documents', 'edit'); $kinds = []; foreach ($list as $x) $kinds[$x['kind']][] = $x;
$want = in_array($kind, TEMPLATE_KINDS, true) ? $kind : ''; ?>
<div class="page-head">
  <div><h1><?= h(t('mod_templates')) ?></h1><p><?= h(t('tpl_lib_help')) ?></p></div>
  <?php if ($ed): ?><div class="row"><a class="btn small" href="/templates/new"><?= icon('plus') ?><?= h(t('tpl_doc_new')) ?></a></div><?php endif; ?>
</div>
<nav class="ptabs ready ltabs" aria-label="<?= h(t('tpl_kind')) ?>">
  <a href="/templates"<?= $want === '' ? ' aria-current="page" class="on"' : '' ?>><?= h(t('all')) ?> <span class="n"><?= count($list) ?></span></a>
  <?php foreach (TEMPLATE_KINDS as $k): ?><a href="/templates?kind=<?= $k ?>"<?= $want === $k ? ' aria-current="page" class="on"' : '' ?>><?= h(t('tk_' . $k)) ?> <span class="n"><?= count($kinds[$k] ?? []) ?></span></a><?php endforeach; ?>
</nav>
<section class="card">
  <?php $show = $want ? ($kinds[$want] ?? []) : $list; ?>
  <?php if (!$show): ?><?= empty_state(t('tpl_lib_empty'), t('tpl_lib_empty_help'), $ed ? '/templates/new' . ($want ? '?kind=' . $want : '') : null, $ed ? t('tpl_doc_new') : null, 'file') ?>
  <?php else: ?>
  <div class="table-wrap"><table data-sortable>
    <thead><tr><th data-sort><?= h(t('tpl_name')) ?></th><th data-sort><?= h(t('tpl_kind')) ?></th><th><?= h(t('tpl_fields_used')) ?></th><th data-sort><?= h(t('tpl_version')) ?></th><th data-sort><?= h(t('col_updated')) ?></th></tr></thead>
    <tbody><?php foreach ($show as $x): $fl = tpl_fields_in($x['body']); ?>
      <tr><td><a href="/templates/<?= (int)$x['id'] ?>"><?= h($x['name']) ?></a><?= $x['active'] ? '' : ' <span class="tag">' . h(t('inactive')) . '</span>' ?></td>
        <td><?= h(t('tk_' . $x['kind'])) ?></td><td class="small muted"><?= $fl ? h(implode(t('list_sep'), array_map(fn($k) => t('tf_' . $k), array_slice($fl, 0, 4)))) . (count($fl) > 4 ? ' …' : '') : '—' ?></td>
        <td data-v="<?= (int)$x['version'] ?>">v<?= (int)$x['version'] ?></td><td data-v="<?= h($x['updated_at']) ?>"><span dir="ltr"><?= h(date('d/m/Y', strtotime($x['updated_at']))) ?></span> · <?= h(staff_name($x['updated_by'] ? (int)$x['updated_by'] : null)) ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
  <p class="muted small mt"><?= h(t('tpl_use_from_file')) ?></p>
</section>
