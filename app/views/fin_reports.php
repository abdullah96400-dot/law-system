<?php $tabs = ['contracts', 'aging', 'monthly', 'pl']; $nm = fn($r) => lang() === 'ar' ? ($r['name_ar'] ?: $r['name_en']) : ($r['name_en'] ?: $r['name_ar']);
$w5 = fn(int $v, int $max) => 'w' . ($max > 0 ? (int)(round(min(100, $v * 100 / $max) / 5) * 5) : 0); ?>
<div class="page-head">
  <div><h1><?= h(t('fin_reports')) ?></h1><p><?= h(t('fin_reports_help')) ?></p></div>
  <div class="row"><a class="btn ghost small" href="/finance"><?= h(t('mod_finance')) ?></a><a class="btn ghost small" href="/finance/notices"><?= h(t('notices')) ?></a></div>
</div>
<nav class="ptabs ready ltabs" aria-label="<?= h(t('fin_reports')) ?>">
  <?php foreach ($tabs as $k): ?><a href="/finance/reports?view=<?= $k ?>&amp;year=<?= $year ?>"<?= $r === $k ? ' aria-current="page" class="on"' : '' ?>><?= h(t('fr_' . $k)) ?></a><?php endforeach; ?>
</nav>
<?php if ($r === 'contracts'): $tt = $data['tot']; ?>
<section class="card">
  <div class="kpis compact">
    <div class="kpi"><span><?= h(t('st_total')) ?></span><b><?= h(money($tt['total'])) ?></b></div>
    <div class="kpi pos"><span><?= h(t('st_paid')) ?></span><b><?= h(money($tt['paid'])) ?></b></div>
    <div class="kpi"><span><?= h(t('st_remaining')) ?></span><b><?= h(money($tt['remaining'])) ?></b></div>
    <div class="kpi warn"><span><?= h(t('fee_overdue')) ?></span><b><?= h(money($tt['overdue'])) ?></b></div>
  </div>
  <p class="muted small"><?= h(t('fr_contracts_def')) ?></p>
  <?php if (!$data['rows']): ?><?= empty_state(t('fr_none'), '', null, null, 'contract') ?><?php else: ?>
  <div class="table-wrap"><table data-sortable>
    <thead><tr><th data-sort><?= h(t('ct_client')) ?></th><th data-sort><?= h(t('appt_matter')) ?></th><th data-sort><?= h(t('fee_kind')) ?></th><th data-sort class="num"><?= h(t('st_total')) ?></th><th data-sort class="num"><?= h(t('st_paid')) ?></th><th data-sort class="num"><?= h(t('st_remaining')) ?></th><th data-sort class="num"><?= h(t('fee_overdue')) ?></th><th><?= h(t('fee_progress')) ?></th></tr></thead>
    <tbody><?php foreach ($data['rows'] as $x): $s = $x['s']; ?><tr>
      <td><a href="/finance/agreements/<?= (int)$x['id'] ?>"><?= h($nm($x)) ?></a></td><td><span class="ref" dir="ltr"><?= h($x['matter_ref']) ?></span></td><td><?= h(t('fk_' . $x['kind'])) ?></td>
      <td class="num" data-v="<?= $s['total'] ?>"><?= h(money_plain($s['total'])) ?></td><td class="num" data-v="<?= $s['paid'] ?>"><?= h(money_plain($s['paid'])) ?></td><td class="num" data-v="<?= $s['remaining'] ?>"><b><?= h(money_plain($s['remaining'])) ?></b></td>
      <td class="num <?= $s['overdue'] ? 'late' : '' ?>" data-v="<?= $s['overdue'] ?>"><?= h(money_plain($s['overdue'])) ?></td>
      <td data-v="<?= $s['pct'] ?>"><div class="bar sm"><i class="w<?= (int)(round($s['pct'] / 5) * 5) ?>"></i></div><span class="small muted"><?= $s['pct'] ?>%</span></td></tr><?php endforeach; ?></tbody>
  </table></div><?php endif; ?>
</section>
<?php elseif ($r === 'aging'): $b = $data['buckets']; $mx = max($b ?: [0]); ?>
<section class="card">
  <h2><?= h(t('fr_aging')) ?> · <?= h(money($data['total'])) ?></h2>
  <p class="muted small"><?= h(t('fr_aging_def')) ?></p>
  <div class="hbars"><?php foreach ($b as $k => $v): ?><div class="hb hb-<?= $k ?>"><span><?= h(t('ag_' . $k)) ?></span><div class="bar"><i class="<?= $w5($v, $mx) ?>"></i></div><b><?= h(money($v)) ?></b></div><?php endforeach; ?></div>
  <?php if ($data['rows']): ?>
  <div class="table-wrap mt"><table data-sortable>
    <thead><tr><th data-sort><?= h(t('invoice')) ?></th><th data-sort><?= h(t('ct_client')) ?></th><th data-sort><?= h(t('inv_due_on')) ?></th><th data-sort class="num"><?= h(t('fr_days_late')) ?></th><th data-sort class="num"><?= h(t('st_remaining')) ?></th></tr></thead>
    <tbody><?php foreach ($data['rows'] as $i): ?><tr><td><a href="/finance/invoices/<?= (int)$i['id'] ?>" dir="ltr"><?= h($i['number']) ?></a></td><td><?= h($nm($i)) ?></td><td dir="ltr" data-v="<?= h($i['due_on']) ?>"><?= $i['due_on'] ? h(date('d/m/Y', strtotime($i['due_on']))) : '—' ?></td><td class="num <?= $i['late'] ? 'late' : '' ?>" data-v="<?= $i['late'] ?>"><?= $i['late'] ?></td><td class="num" data-v="<?= $i['left'] ?>"><b><?= h(money_plain($i['left'])) ?></b></td></tr><?php endforeach; ?></tbody>
  </table></div><?php endif; ?>
</section>
<?php else: $mo = $data['months']; $yr = $data['year']; $mx = max(1, max(array_map(fn($x) => max($x['income'], $x['expense'] + $x['court_fee']), $mo))); ?>
<section class="card">
  <form method="get" action="/finance/reports" class="row year-pick"><input type="hidden" name="view" value="<?= h($r) ?>">
    <a class="btn ghost small" href="/finance/reports?view=<?= h($r) ?>&amp;year=<?= $year - 1 ?>" aria-label="<?= h(t('previous')) ?>">‹</a><label class="sr" for="year"><?= h(t('fr_year')) ?></label><input id="year" name="year" type="number" min="2000" max="2100" value="<?= $year ?>" dir="ltr" class="auto"><a class="btn ghost small" href="/finance/reports?view=<?= h($r) ?>&amp;year=<?= $year + 1 ?>" aria-label="<?= h(t('next')) ?>">›</a><button class="btn small"><?= h(t('show')) ?></button></form>
  <div class="kpis compact">
    <div class="kpi pos"><span><?= h(t('fr_income')) ?></span><b><?= h(money($yr['income'])) ?></b></div>
    <div class="kpi"><span><?= h(t('fr_expenses')) ?></span><b><?= h(money($yr['expense'])) ?></b></div>
    <div class="kpi"><span><?= h(t('st_court_fees')) ?></span><b><?= h(money($yr['court_fee'])) ?></b></div>
    <div class="kpi <?= $yr['net'] < 0 ? 'warn' : '' ?>"><span><?= h(t('fr_net')) ?></span><b><?= h(money($yr['net'])) ?></b></div>
  </div>
  <p class="note small"><?= h(t('fr_basis')) ?></p>
  <?php if ($r === 'monthly'): ?>
  <div class="mbars" aria-hidden="true"><?php foreach ($mo as $i => $x): ?><div class="mb"><div class="mb-cols"><i class="in h<?= substr($w5($x['income'], $mx), 1) ?>"></i><i class="out h<?= substr($w5($x['expense'] + $x['court_fee'], $mx), 1) ?>"></i></div><span><?= h(t('mon_' . $i)) ?></span></div><?php endforeach; ?></div>
  <p class="legend small"><span class="lg in"></span><?= h(t('fr_income')) ?> <span class="lg out"></span><?= h(t('fr_out_all')) ?></p>
  <?php endif; ?>
  <div class="table-wrap"><table>
    <thead><tr><th><?= h(t('fr_month')) ?></th><th class="num"><?= h(t('fr_income')) ?></th><th class="num"><?= h(t('fr_expenses')) ?></th><th class="num"><?= h(t('st_court_fees')) ?></th><th class="num"><?= h(t('fr_net')) ?></th></tr></thead>
    <tbody><?php foreach ($mo as $i => $x): ?><tr><td><?= h(t('mon_' . $i)) ?></td><td class="num"><?= h(money_plain($x['income'])) ?></td><td class="num"><?= h(money_plain($x['expense'])) ?></td><td class="num"><?= h(money_plain($x['court_fee'])) ?></td><td class="num <?= $x['net'] < 0 ? 'late' : '' ?>"><b><?= h(money_plain($x['net'])) ?></b></td></tr><?php endforeach; ?></tbody>
    <tfoot><tr><th><?= h(t('fr_year_total', $year)) ?></th><th class="num"><?= h(money_plain($yr['income'])) ?></th><th class="num"><?= h(money_plain($yr['expense'])) ?></th><th class="num"><?= h(money_plain($yr['court_fee'])) ?></th><th class="num"><?= h(money_plain($yr['net'])) ?></th></tr></tfoot>
  </table></div>
</section>
<?php endif; ?>
