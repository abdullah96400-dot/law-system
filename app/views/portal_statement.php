<?php $m = $s['m']; ?>
<p class="muted small"><a href="<?= h($base) ?>/payments"><?= h(t('ps_payments')) ?></a> · <a href="<?= h($base) ?>/m/<?= (int)$m['id'] ?>"><?= h($m['title']) ?></a></p>
<div class="page-head"><div><h1><?= h(t('statement')) ?></h1><p><?= h($m['title']) ?></p></div><div class="row"><a class="btn small" href="<?= h($base) ?>/s/<?= (int)$s['a']['id'] ?>/pdf"><?= icon('file') ?><?= h(t('st_pdf')) ?></a></div></div>
<section class="card"><div class="kpis st-kpis">
  <div class="kpi"><span><?= h(t('st_total')) ?></span><b><?= h(money($s['total'])) ?></b></div>
  <div class="kpi pos"><span><?= h(t('st_paid')) ?></span><b><?= h(money($s['paid'])) ?></b></div>
  <div class="kpi"><span><?= h(t('st_remaining')) ?></span><b><?= h(money($s['remaining'])) ?></b></div>
  <div class="kpi"><span><?= h(t('st_court_fees')) ?></span><b><?= h(money($s['court_fees'])) ?></b></div>
</div></section>
<section class="card"><h2><?= h(t('st_moves')) ?></h2>
  <?php if (!$s['lines']): ?><p class="muted"><?= h(t('st_no_moves')) ?></p><?php else: ?>
  <div class="table-wrap"><table class="st-table"><thead><tr><th><?= h(t('date')) ?></th><th><?= h(t('st_desc')) ?></th><th><?= h(t('st_ref')) ?></th><th class="num"><?= h(t('st_debit')) ?></th><th class="num"><?= h(t('st_credit')) ?></th><th class="num"><?= h(t('st_balance')) ?></th></tr></thead>
    <tbody><?php foreach ($s['lines'] as $l): ?><tr><td dir="ltr" data-label="<?= h(t('date')) ?>"><?= h(date('d/m/Y', strtotime($l['on']))) ?></td><td data-label="<?= h(t('st_desc')) ?>"><?= h($l['text']) ?></td><td dir="ltr" data-label="<?= h(t('st_ref')) ?>"><?= h($l['ref']) ?></td>
      <td class="num" data-label="<?= h(t('st_debit')) ?>"><?= $l['debit'] ? h(money_plain($l['debit'])) : '' ?></td><td class="num pos" data-label="<?= h(t('st_credit')) ?>"><?= $l['credit'] ? h(money_plain($l['credit'])) : '' ?></td><td class="num" data-label="<?= h(t('st_balance')) ?>"><b><?= h(money_plain($l['balance'])) ?></b></td></tr><?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
  <p class="muted small mt"><?= h(t('st_note')) ?></p>
</section>
