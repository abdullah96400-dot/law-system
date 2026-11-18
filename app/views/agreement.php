<?php $ro = office_readonly(); $ins = installments_state((int)$a['id']); $s = agreement_summary($a, $ins);
$canPay = !$ro && can('finance', 'create') && can('finance', 'edit'); $canNotice = !$ro && can('finance', 'create'); $today = date('Y-m-d'); ?>
<p class="muted small"><a href="/matters/<?= (int)$m['id'] ?>#finance" dir="ltr"><?= h($m['ref']) ?></a> — <?= h($m['title']) ?></p>
<div class="page-head">
  <div><h1><?= h(t('fee_agreement')) ?> — <?= h(t('fk_' . $a['kind'])) ?></h1>
    <p><?= h(contact_label(contact_get((int)$a['client_id']))) ?><?= $a['signed_on'] ? ' · ' . h(t('fee_signed_on')) . ' <span dir="ltr">' . h(date('d/m/Y', strtotime($a['signed_on']))) . '</span>' : '' ?></p></div>
  <div class="row">
    <a class="btn ghost small" href="/finance/agreements/<?= (int)$a['id'] ?>/statement"><?= icon('receipt') ?><?= h(t('statement')) ?></a>
    <?php if (!$ro && can('finance', 'edit')): ?><a class="btn ghost small" href="/finance/agreements/<?= (int)$a['id'] ?>/edit"><?= h(t('edit')) ?></a><?php endif; ?>
  </div>
</div>
<?php if ($err): ?><div class="flash err" role="alert"><?= h(t($err)) ?></div><?php endif; ?>
<section class="card fee-sum">
  <div class="fee-top">
    <div><span><?= h(t('st_total')) ?></span><b><?= h(money($s['total'])) ?></b></div>
    <div><span><?= h(t('st_paid')) ?></span><b class="pos"><?= h(money($s['paid'])) ?></b></div>
    <div><span><?= h(t('st_remaining')) ?></span><b><?= h(money($s['remaining'])) ?></b></div>
  </div>
  <div class="bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $s['pct'] ?>" aria-label="<?= h(t('fee_progress')) ?>"><i class="w<?= (int)(round($s['pct'] / 5) * 5) ?>"></i></div>
  <p class="muted small"><?= h(t('fee_progress_text', $s['pct'])) ?></p>
  <div class="fee-more">
    <?php if ($s['advance'] !== null): ?><div><span><?= h(t('fee_advance')) ?></span><b><?= h(money($s['advance'])) ?></b></div><?php endif; ?>
    <div class="<?= $s['due_now'] ? 'soon' : '' ?>"><span><?= h(t('fee_due_now')) ?></span><b><?= h(money($s['due_now'])) ?></b></div>
    <div class="<?= $s['overdue'] ? 'late' : '' ?>"><span><?= h(t('fee_overdue')) ?></span><b><?= h(money($s['overdue'])) ?></b><?= $s['overdue_n'] ? '<small>' . h(t('fee_overdue_n', $s['overdue_n'])) . '</small>' : '' ?></div>
    <?php if ($a['hourly_rate'] !== null): ?><div><span><?= h(t('fee_hourly')) ?></span><b><?= h(money((int)$a['hourly_rate'])) ?></b></div><?php endif; ?>
  </div>
  <?php if ($s['unscheduled'] > 0 && $ins): ?><p class="note small mt"><?= h(t('fee_unscheduled', money($s['unscheduled']))) ?></p><?php endif; ?>
  <?php if ($a['terms']): ?><details class="mt"><summary><?= h(t('fee_terms')) ?></summary><p class="pre"><?= h($a['terms']) ?></p></details><?php endif; ?>
  <?php if ($a['document_id']): ?><p class="small mt"><a href="/documents/<?= (int)$a['document_id'] ?>"><?= icon('file') ?> <?= h(t('fee_document')) ?></a></p><?php endif; ?>
</section>

<section class="card" id="schedule">
  <h2><?= h(t('fee_installments')) ?></h2>
  <?php if (!$ins): ?><?= empty_state(t('fee_no_schedule'), t('fee_no_schedule_help'), null, null, 'contract') ?><?php else: ?>
  <div class="table-wrap"><table class="inst">
    <thead><tr><th><?= h(t('fee_inst_label')) ?></th><th><?= h(t('inv_due_on')) ?></th><th><?= h(t('pay_amount')) ?></th><th><?= h(t('st_paid')) ?></th><th><?= h(t('st_remaining')) ?></th><th><?= h(t('status')) ?></th><th><span class="sr"><?= h(t('actions')) ?></span></th></tr></thead>
    <tbody><?php foreach ($ins as $x): ?>
      <tr class="is-<?= h($x['state']) ?>">
        <td><?= h($x['label']) ?><?= $x['invoice_id'] ? ' <a class="small" href="/finance/invoices/' . (int)$x['invoice_id'] . '">' . h(t('invoice')) . '</a>' : '' ?></td>
        <td dir="ltr"><?= $x['due_on'] ? h(date('d/m/Y', strtotime($x['due_on']))) : '—' ?></td>
        <td class="num"><?= h(money_plain((int)$x['amount'])) ?></td><td class="num"><?= h(money_plain($x['paid'])) ?></td><td class="num"><b><?= h(money_plain($x['left'])) ?></b></td>
        <td><span class="ist ist-<?= h($x['state']) ?>"><?= h(t('ist_' . $x['state'])) ?></span></td>
        <td class="row-actions"><?php if ($x['left'] > 0 && ($canPay || $canNotice)): ?>
          <details class="pop" data-pop><summary class="btn small ghost"><?= h(t('actions')) ?></summary><div class="pop-body">
            <?php if ($canPay): ?><form method="post" action="/finance/agreements/<?= (int)$a['id'] ?>/installments/<?= (int)$x['id'] ?>/pay" class="form-grid"><?= csrf_field() ?>
              <div><label for="pa<?= (int)$x['id'] ?>"><?= h(t('pay_amount')) ?></label><input id="pa<?= (int)$x['id'] ?>" name="amount" inputmode="decimal" dir="ltr" required value="<?= h(money_plain($x['left'])) ?>"></div>
              <div><label for="pd<?= (int)$x['id'] ?>"><?= h(t('pay_date')) ?></label><input id="pd<?= (int)$x['id'] ?>" name="received_on" type="date" dir="ltr" value="<?= h($today) ?>" max="<?= h($today) ?>"></div>
              <div><label for="pm<?= (int)$x['id'] ?>"><?= h(t('pay_method')) ?></label><select id="pm<?= (int)$x['id'] ?>" name="method" required><?php foreach (PAY_METHODS as $pm): ?><option value="<?= $pm ?>"><?= h(t('pm_' . $pm)) ?></option><?php endforeach; ?></select></div>
              <div><label for="pr<?= (int)$x['id'] ?>"><?= h(t('pay_ref')) ?></label><input id="pr<?= (int)$x['id'] ?>" name="reference" maxlength="120" dir="ltr"></div>
              <p class="full muted small"><?= h(t($x['invoice_id'] ? 'pay_inst_help_inv' : 'pay_inst_help')) ?></p>
              <div class="full"><button class="btn small ok"><?= h(t('pay_record')) ?></button></div></form><?php endif; ?>
            <?php if ($canNotice): ?><form method="post" action="/finance/agreements/<?= (int)$a['id'] ?>/installments/<?= (int)$x['id'] ?>/notice" class="mt"><?= csrf_field() ?><button class="link small"><?= icon('bell') ?> <?= h(t('notice_prepare')) ?></button></form><?php endif; ?>
          </div></details>
        <?php elseif (!$x['invoice_id'] && !$ro && can('finance', 'create')): ?><form method="post" action="/matters/<?= (int)$a['matter_id'] ?>/invoice" class="inline"><?= csrf_field() ?><input type="hidden" name="installment_id" value="<?= (int)$x['id'] ?>"><button class="link small"><?= h(t('fee_make_invoice')) ?></button></form><?php endif; ?></td>
      </tr>
    <?php endforeach; ?></tbody>
    <tfoot><tr><th><?= h(t('inv_total')) ?></th><th></th><th class="num"><?= h(money_plain($s['schedule'])) ?></th><th class="num"><?= h(money_plain(array_sum(array_column($ins, 'paid')))) ?></th><th class="num"><?= h(money_plain(array_sum(array_column($ins, 'left')))) ?></th><th colspan="2"></th></tr></tfoot>
  </table></div>
  <?php endif; ?>
  <?php if (!$ro && can('finance', 'edit')): ?>
    <details class="mt"<?= !$ins ? ' open' : '' ?>><summary><?= h(t('fee_add_installment')) ?></summary>
    <form method="post" action="/finance/agreements/<?= (int)$a['id'] ?>/installment" class="form-grid"><?= csrf_field() ?>
      <div><label for="ilabel"><?= h(t('fee_inst_label')) ?></label><input id="ilabel" name="label" maxlength="160" required placeholder="<?= h(t('fee_inst_ph')) ?>"></div>
      <div><label for="iamount"><?= h(t('pay_amount')) ?> (<?= h(t('currency')) ?>)</label><input id="iamount" name="amount" inputmode="decimal" dir="ltr" required></div>
      <div><label for="idue"><?= h(t('inv_due_on')) ?></label><input id="idue" name="due_on" type="date" dir="ltr"></div>
      <div class="full"><button class="btn small"><?= h(t('add')) ?></button></div></form></details>
  <?php endif; ?>
</section>
<?php $nts = orows('SELECT * FROM due_notices WHERE office_id = :office AND matter_id = :m ORDER BY id DESC LIMIT 20', ['m' => $a['matter_id']]); if ($nts): ?>
<section class="card">
  <h2><?= h(t('notices')) ?></h2>
  <ul class="plain one"><?php foreach ($nts as $x): ?><li><a href="/finance/notices/<?= (int)$x['id'] ?>"><?= h(money((int)$x['amount'])) ?></a> · <span dir="ltr"><?= $x['due_on'] ? h(date('d/m/Y', strtotime($x['due_on']))) : '—' ?></span> <span class="nst nst-<?= h($x['status']) ?>"><?= h(t('nst_' . $x['status'])) ?></span><?= $x['channel'] ? ' <span class="muted small">' . h(t('nch_' . $x['channel'])) . ' · <span dir="ltr">' . h(fmt_dt($x['sent_at'])) . '</span></span>' : '' ?></li><?php endforeach; ?></ul>
</section>
<?php endif; ?>
