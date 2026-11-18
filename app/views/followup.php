<?php $nm = fn($r) => $r['name_ar'] || $r['name_en'] ? (lang() === 'ar' ? ($r['name_ar'] ?: $r['name_en']) : ($r['name_en'] ?: $r['name_ar'])) : '—'; $ed = !office_readonly() && can('calendar', 'edit'); ?>
<div class="page-head"><div><h1><?= h(t('fu_title')) ?></h1><p><?= h(t('fu_help')) ?></p></div>
  <div class="row"><span class="n-pill<?= $list ? ' warn' : '' ?>"><?= count($list) ?></span></div></div>
<section class="card">
  <form method="get" action="/followup" class="filters" data-auto>
    <div><label for="lead"><?= h(t('appt_lead')) ?></label><select id="lead" name="lead"><option value="0"><?= h(t('all')) ?></option><?php foreach (active_staff() as $s): ?><option value="<?= (int)$s['id'] ?>"<?= $f['lead'] === (int)$s['id'] ? ' selected' : '' ?>><?= h($s['full_name']) ?></option><?php endforeach; ?></select></div>
    <div><label for="from"><?= h(t('from')) ?></label><input id="from" name="from" type="date" dir="ltr" value="<?= h($f['from']) ?>"></div>
    <div><button class="btn small"><?= h(t('show')) ?></button></div>
  </form>
  <?php if (!$list): ?><?= empty_state(t('fu_empty'), t('fu_empty_help'), null, null, 'calendar') ?><?php else: ?>
  <div class="table-wrap"><table data-sortable class="fu-table">
    <thead><tr><th data-sort><?= h(t('ct_client')) ?></th><th data-sort><?= h(t('fu_subject')) ?></th><th data-sort><?= h(t('matter_court')) ?></th><th data-sort><?= h(t('matter_official_no')) ?></th><th data-sort><?= h(t('fu_section')) ?></th><th data-sort><?= h(t('fu_date')) ?></th><th data-sort><?= h(t('appt_lead')) ?></th><th><span class="sr"><?= h(t('actions')) ?></span></th></tr></thead>
    <tbody><?php foreach ($list as $r): $days = (int)floor((time() - strtotime($r['starts_at'])) / 86400); ?><tr>
      <td data-label="<?= h(t('ct_client')) ?>"><?= h($nm($r)) ?></td>
      <td data-label="<?= h(t('fu_subject')) ?>"><?= $r['matter_id'] ? '<a href="/matters/' . (int)$r['matter_id'] . '#hearings">' . h($r['matter_title']) . '</a>' : h($r['title']) ?><div class="muted small"><?= h(t('ak_' . $r['kind'])) ?> · <?= h($r['title']) ?></div></td>
      <td data-label="<?= h(t('matter_court')) ?>"><?= h(lookup_name($r['court_id'] ? (int)$r['court_id'] : null, 'court')) ?></td>
      <td data-label="<?= h(t('matter_official_no')) ?>" dir="ltr"><?= h($r['official_no'] ?: ($r['matter_ref'] ?? '—')) ?></td>
      <td data-label="<?= h(t('fu_section')) ?>"><?= h($r['circuit'] ?: '—') ?></td>
      <td data-label="<?= h(t('fu_date')) ?>" data-v="<?= h($r['starts_at']) ?>"><span dir="ltr"><?= h(date('d/m/Y', strtotime($r['starts_at']))) ?></span> <span class="ist ist-overdue"><?= h(t('fu_days', $days)) ?></span></td>
      <td data-label="<?= h(t('appt_lead')) ?>"><?= h(staff_name((int)$r['lead_id'])) ?></td>
      <td><?php if ($ed): ?><a class="btn small" href="/calendar/<?= (int)$r['id'] ?>#outcome"><?= h(t('fu_record')) ?></a><?php endif; ?></td></tr><?php endforeach; ?></tbody>
  </table></div><?php endif; ?>
</section>
