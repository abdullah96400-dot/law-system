<?php $me = current_staff(); $year = (int)date('Y'); $fy = fin_report($me, 'monthly', $year); $mo = $fy['months'];
$mx = max(1, max(array_map(fn($x) => max($x['income'], $x['expense'] + $x['court_fee']), $mo)));
$w5 = fn(int $v) => (int)(round(min(100, $v * 100 / $mx) / 5) * 5);
$net = $tot['received'] - $tot['expenses'];
$notices = array_slice(notice_list($me, 'draft'), 0, 6);
$nm = fn($r) => lang() === 'ar' ? ($r['name_ar'] ?: $r['name_en']) : ($r['name_en'] ?: $r['name_ar']); ?>
<div class="page-head">
  <div><h1><?= h(t('fin_dash')) ?></h1><p><?= h(t('fin_dash_period')) ?> <span dir="ltr"><?= h(date('d/m/Y', strtotime($from))) ?> – <?= h(date('d/m/Y', strtotime("$to -1 day"))) ?></span></p></div>
  <form method="get" action="/finance" class="row year-pick">
    <label class="sr" for="from"><?= h(t('from')) ?></label><input id="from" name="from" type="date" dir="ltr" value="<?= h($from) ?>" class="auto">
    <label class="sr" for="to"><?= h(t('to_excl')) ?></label><input id="to" name="to" type="date" dir="ltr" value="<?= h($to) ?>" class="auto">
    <button class="btn small"><?= h(t('show')) ?></button>
  </form>
</div>
<div class="kpis fin-kpis">
  <div class="kpi kpi-i"><span class="p-ico t-green"><?= icon('wallet') ?></span><span><?= h(t('kpi_received')) ?></span><b class="pos"><?= h(money($tot['received'])) ?></b></div>
  <div class="kpi kpi-i"><span class="p-ico t-gold"><?= icon('receipt') ?></span><span><?= h(t('kpi_expenses')) ?></span><b><?= h(money($tot['expenses'])) ?></b></div>
  <div class="kpi kpi-i"><span class="p-ico t-petrol"><?= icon('chart') ?></span><span><?= h(t('fr_net')) ?></span><b class="<?= $net < 0 ? 'late' : '' ?>"><?= h(money($net)) ?></b></div>
  <div class="kpi kpi-i"><span class="p-ico t-blue"><?= icon('contract') ?></span><span><?= h(t('kpi_outstanding')) ?></span><b><?= h(money($tot['outstanding'])) ?></b></div>
  <div class="kpi kpi-i warn"><span class="p-ico t-red"><?= icon('alert') ?></span><span><?= h(t('kpi_overdue')) ?></span><b><?= h(money($tot['overdue'])) ?></b></div>
</div>
<p class="muted small"><?= h(t('finance_note')) ?></p>
<nav class="hubs" aria-label="<?= h(t('mod_finance')) ?>"><?php
  foreach ([['/finance/invoices', 'receipt', 'ink', t('invoices'), ''], ['/finance/notices', 'bell', 'gold', t('notices'), '', count(notice_list($me, 'draft'))], ['/finance/reports', 'chart', 'petrol', t('fin_reports'), ''],
    ['/finance/reports?view=contracts', 'contract', 'blue', t('fr_contracts'), ''], ['/finance/reports?view=aging', 'clock', 'red', t('fr_aging'), ''], ['/finance/trust', 'shield', 'violet', t('trust'), '']] as $x) echo hub_tile($x[0], $x[1], $x[2], $x[3], $x[4], $x[5] ?? null, !empty($x[5])); ?></nav>
<div class="grid2 wide-first">
  <section class="card">
    <div class="head-row"><h2><?= h(t('fin_chart_title', $year)) ?></h2><a class="small" href="/finance/reports?view=monthly"><?= h(t('na_open_list')) ?></a></div>
    <div class="mbars" role="img" aria-label="<?= h(t('fin_chart_alt', money($fy['year']['income']), money($fy['year']['expense'] + $fy['year']['court_fee']))) ?>"><?php foreach ($mo as $i => $x): ?><div class="mb"><div class="mb-cols"><i class="in h<?= $w5($x['income']) ?>"></i><i class="out h<?= $w5($x['expense'] + $x['court_fee']) ?>"></i></div><span><?= h(t('mon_' . $i)) ?></span></div><?php endforeach; ?></div>
    <p class="legend small"><span class="lg in"></span><?= h(t('fr_income')) ?> <span class="lg out"></span><?= h(t('fr_out_all')) ?></p>
  </section>
  <section class="card">
    <div class="head-row"><h2><?= h(t('fin_notices_due')) ?></h2><a class="small" href="/finance/notices"><?= h(t('na_open_list')) ?></a></div>
    <?php if (!$notices): ?><?= empty_state(t('notices_empty'), t('notices_empty_help'), null, null, 'bell') ?><?php endif; ?>
    <?php foreach ($notices as $x): ?>
      <div class="due-card"><div class="due-top"><b><?= h(money((int)$x['amount'])) ?></b><span class="ist ist-<?= $x['due_on'] && $x['due_on'] < date('Y-m-d') ? 'overdue' : 'due' ?>" dir="ltr"><?= $x['due_on'] ? h(date('d/m/Y', strtotime($x['due_on']))) : '—' ?></span></div>
        <span class="small"><?= h($nm($x)) ?> · <span class="ref" dir="ltr"><?= h($x['matter_ref']) ?></span></span>
        <a class="btn small ok" href="/finance/notices/<?= (int)$x['id'] ?>"><?= icon('bell') ?><?= h(t('notice_send_client')) ?></a></div>
    <?php endforeach; ?>
  </section>
</div>
<section class="card">
  <h2><?= h(t('inv_overdue_list')) ?></h2>
  <?php if (!$overdue): ?><?= empty_state(t('fin_no_overdue'), '', null, null, 'receipt') ?><?php else: ?>
  <div class="table-wrap"><table data-sortable>
    <thead><tr><th data-sort><?= h(t('invoice')) ?></th><th data-sort><?= h(t('ct_client')) ?></th><th data-sort class="num"><?= h(t('st_remaining')) ?></th><th data-sort><?= h(t('inv_due_on')) ?></th></tr></thead>
    <tbody><?php foreach ($overdue as $i): ?><tr><td><a href="/finance/invoices/<?= (int)$i['id'] ?>" dir="ltr"><?= h($i['number']) ?></a></td><td><?= h($nm($i)) ?></td><td class="num" data-v="<?= (int)$i['total'] - (int)$i['paid'] ?>"><b><?= h(money((int)$i['total'] - (int)$i['paid'])) ?></b></td><td class="late" data-v="<?= h($i['due_on']) ?>" dir="ltr"><?= h(date('d/m/Y', strtotime($i['due_on']))) ?></td></tr><?php endforeach; ?></tbody>
  </table></div><?php endif; ?>
</section>
