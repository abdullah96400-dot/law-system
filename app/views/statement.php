<?php $ro = office_readonly(); $a = $s['a']; $m = $s['m']; $c = $s['c'];
$base = $a ? '/finance/agreements/' . (int)$a['id'] . '/statement' : '/finance/clients/' . (int)$c['id'] . '/statement';
$shared = $a ? statement_shared((int)$a['id']) : false; ?>
<p class="muted small"><?php if ($a): ?><a href="/finance/agreements/<?= (int)$a['id'] ?>"><?= h(t('fee_agreement')) ?></a> · <a href="/matters/<?= (int)$m['id'] ?>#finance" dir="ltr"><?= h($m['ref']) ?></a><?php else: ?><a href="/clients/<?= (int)$c['id'] ?>"><?= h(contact_label($c)) ?></a><?php endif; ?></p>
<div class="page-head">
  <div><h1><?= h(t('statement')) ?></h1><p><?= h(contact_label($c)) ?><?= $m ? ' · ' . h($m['title']) : ' · ' . h(t('st_all_files')) ?></p>
    <?php if ($a): ?><div class="row mt"><?= $shared ? '<span class="tag">' . icon('portal') . h(t('st_in_portal')) . '</span>' : '<span class="muted small">' . h(t('st_not_in_portal')) . '</span>' ?></div><?php endif; ?></div>
  <div class="row">
    <?php if (can('finance', 'download')): ?><a class="btn small" href="<?= h($base) ?>?pdf=1"><?= icon('file') ?><?= h(t('st_pdf')) ?></a><?php endif; ?>
    <?php if ($a && !$ro && !is_support(current_staff())): ?><form method="post" action="<?= h($base) ?>/<?= $shared ? 'unshare' : 'share' ?>"<?= $shared ? '' : ' data-confirm="' . h(t('st_share_confirm')) . '"' ?>><?= csrf_field() ?><button class="btn small ghost"><?= icon('portal') ?><?= h(t($shared ? 'portal_stop_sharing' : 'st_share')) ?></button></form><?php endif; ?>
  </div>
</div>
<section class="card">
  <div class="kpis st-kpis">
    <div class="kpi"><span><?= h(t('st_total')) ?></span><b><?= h(money($s['total'])) ?></b></div>
    <div class="kpi pos"><span><?= h(t('st_paid')) ?></span><b><?= h(money($s['paid'])) ?></b></div>
    <div class="kpi"><span><?= h(t('st_remaining')) ?></span><b><?= h(money($s['remaining'])) ?></b></div>
    <div class="kpi"><span><?= h(t('st_court_fees')) ?></span><b><?= h(money($s['court_fees'])) ?></b></div>
    <div class="kpi"><span><?= h(t('st_expenses')) ?></span><b><?= h(money($s['expenses'])) ?></b></div>
    <div class="kpi"><span><?= h(t('st_difference')) ?></span><b><?= h(money($s['difference'])) ?></b><small><?= h(t('st_difference_def')) ?></small></div>
  </div>
</section>
<section class="card">
  <h2><?= h(t('st_moves')) ?></h2>
  <?php if (!$s['lines']): ?><?= empty_state(t('st_no_moves'), t('st_no_moves_help'), null, null, 'receipt') ?><?php else: ?>
  <div class="table-wrap"><table class="st-table">
    <thead><tr><th><?= h(t('date')) ?></th><th><?= h(t('st_desc')) ?></th><th><?= h(t('st_ref')) ?></th><th class="num"><?= h(t('st_debit')) ?></th><th class="num"><?= h(t('st_credit')) ?></th><th class="num"><?= h(t('st_balance')) ?></th></tr></thead>
    <tbody><?php foreach ($s['lines'] as $l): ?><tr class="mv-<?= h($l['kind']) ?>">
      <td dir="ltr" data-label="<?= h(t('date')) ?>"><?= h(date('d/m/Y', strtotime($l['on']))) ?></td><td data-label="<?= h(t('st_desc')) ?>"><a href="<?= h($l['link']) ?>"><?= h($l['text']) ?></a></td>
      <td dir="ltr" data-label="<?= h(t('st_ref')) ?>"><?= h($l['ref']) ?></td><td class="num" data-label="<?= h(t('st_debit')) ?>"><?= $l['debit'] ? h(money_plain($l['debit'])) : '' ?></td>
      <td class="num pos" data-label="<?= h(t('st_credit')) ?>"><?= $l['credit'] ? h(money_plain($l['credit'])) : '' ?></td><td class="num" data-label="<?= h(t('st_balance')) ?>"><b><?= h(money_plain($l['balance'])) ?></b></td></tr><?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
  <?php if ($s['spent']): ?>
    <h3 class="mt"><?= h(t('st_spent_title')) ?></h3>
    <ul class="plain one"><?php foreach ($s['spent'] as $e): ?><li><span dir="ltr" class="muted small"><?= h(date('d/m/Y', strtotime($e['on']))) ?></span> <span class="tag<?= $e['kind'] === 'court_fee' ? ' k-judgment' : '' ?>"><?= h(t('ec_' . $e['kind'])) ?></span> <?= h($e['text']) ?> · <b><?= h(money($e['amount'])) ?></b><?= $e['billed'] ? ' <span class="muted small">' . h(t('exp_tag_billed')) . '</span>' : '' ?></li><?php endforeach; ?></ul>
  <?php endif; ?>
  <p class="muted small mt"><?= h(t('st_note')) ?></p>
</section>
<?php if ($a && ($log = orows("SELECT * FROM portal_shares WHERE office_id = :office AND kind = 'statement' AND item_id = :a ORDER BY id DESC LIMIT 10", ['a' => $a['id']]))): ?>
<section class="card"><h2><?= h(t('share_log')) ?></h2><ul class="plain one small muted"><?php foreach ($log as $x): ?><li><span dir="ltr"><?= h(fmt_dt($x['at'])) ?></span> · <?= h(staff_name($x['staff_id'] ? (int)$x['staff_id'] : null)) ?> · <?= h(t($x['shared'] ? 'share_on' : 'share_off', t('sk_statement'))) ?></li><?php endforeach; ?></ul></section>
<?php endif; ?>
