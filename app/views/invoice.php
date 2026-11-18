<?php $me = current_staff(); $ro = office_readonly(); $st = invoice_state($i); $draft = $i['status'] === 'draft'; $lines = invoice_lines((int)$i['id']); $pays = invoice_payments((int)$i['id']);
$m = matter_for((int)$i['matter_id'], $me); $c = contact_get((int)$i['client_id']); $o = current_office();
$edit = !$ro && can('finance', 'edit'); $create = !$ro && can('finance', 'create');
$unbilled = $draft ? (int)orow('SELECT COUNT(*) n FROM expenses WHERE office_id = :office AND matter_id = :m AND billable = 1 AND invoice_line_id IS NULL', ['m' => $i['matter_id']])['n'] : 0; ?>
<section class="card">
  <div class="head-row">
    <div><h1><?= h(t('invoice')) ?> <span class="ref" dir="ltr"><?= h($i['number'] ?: t('inv_draft')) ?></span></h1>
      <p class="muted"><?= h(contact_label($c)) ?> · <a href="/matters/<?= (int)$m['id'] ?>" dir="ltr"><?= h($m['ref']) ?></a> — <?= h($m['title']) ?></p></div>
    <div class="row"><span class="inv big inv-<?= h($st) ?>"><?= h(t('is_' . $st)) ?></span>
      <?php if (can('finance', 'download')): ?><a class="btn small ghost" href="/finance/invoices/<?= (int)$i['id'] ?>/pdf" target="_blank" rel="noopener">PDF</a><?php endif; ?></div>
  </div>
  <?php if ($err): ?><div class="flash err" role="alert"><?= h(t($err)) ?></div><?php endif; ?>
  <?php if ($draft): ?><p class="note small"><?= h(t('inv_draft_help')) ?></p><?php endif; ?>
  <?php if ($i['status'] === 'void'): ?><p class="flash err"><?= h(t('inv_voided_because', $i['void_reason'])) ?></p><?php endif; ?>
  <dl class="facts">
    <dt><?= h(t('inv_issued_on')) ?></dt><dd dir="ltr"><?= $i['issued_on'] ? h(date('d/m/Y', strtotime($i['issued_on']))) : '—' ?></dd>
    <dt><?= h(t('inv_due_on')) ?></dt><dd dir="ltr"><?= $i['due_on'] ? h(date('d/m/Y', strtotime($i['due_on']))) : '—' ?></dd>
    <?php if (!$draft): ?><dt><?= h(t('inv_issued_by')) ?></dt><dd><?= h(staff_name($i['issued_by'] ? (int)$i['issued_by'] : null)) ?></dd><?php endif; ?>
    <dt><?= h(t('doc_in_portal')) ?></dt><dd><?= h($i['portal_visible'] ? t('yes') : t('no')) ?></dd>
  </dl>
</section>

<section class="card">
  <h2><?= h(t('inv_lines')) ?></h2>
  <div class="table-wrap"><table class="stack">
    <caption class="sr"><?= h(t('inv_lines')) ?></caption>
    <thead><tr><th scope="col"><?= h(t('inv_desc')) ?></th><th scope="col"><?= h(t('inv_qty')) ?></th><th scope="col"><?= h(t('inv_unit')) ?></th><th scope="col"><?= h(t('inv_amount')) ?></th><?php if ($draft && $edit): ?><th scope="col"><span class="sr"><?= h(t('actions')) ?></span></th><?php endif; ?></tr></thead>
    <tbody>
      <?php if (!$lines): ?><tr><td colspan="5" class="muted"><?= h(t('inv_no_lines')) ?></td></tr><?php endif; ?>
      <?php foreach ($lines as $l): ?><tr>
        <td data-label="<?= h(t('inv_desc')) ?>"><?= $l['kind'] === 'expense' ? '<span class="tag">' . h(t('lk_expense')) . '</span> ' : '' ?><?= h($l['description']) ?></td>
        <td data-label="<?= h(t('inv_qty')) ?>" class="num"><?= (int)$l['qty'] ?></td><td data-label="<?= h(t('inv_unit')) ?>" class="num"><?= h(money_plain((int)$l['unit'])) ?></td><td data-label="<?= h(t('inv_amount')) ?>" class="num"><?= h(money_plain((int)$l['amount'])) ?></td>
        <?php if ($draft && $edit): ?><td><form method="post" action="/finance/invoices/<?= (int)$i['id'] ?>/lines/<?= (int)$l['id'] ?>/remove" class="inline"><?= csrf_field() ?><button class="link small"><?= h(t('remove')) ?></button></form></td><?php endif; ?>
      </tr><?php endforeach; ?>
    </tbody>
  </table></div>
  <dl class="facts totals">
    <dt><?= h(t('inv_subtotal')) ?></dt><dd class="num"><?= h(money((int)$i['subtotal'])) ?></dd>
    <?php if ((int)$i['tax_rate_bp'] > 0): ?><dt><?= h(($o['tax_label'] ?: t('tax')) . ' ' . ($i['tax_rate_bp'] / 100) . '%') ?></dt><dd class="num"><?= h(money((int)$i['tax'])) ?></dd><?php endif; ?>
    <dt><b><?= h(t('inv_total')) ?></b></dt><dd class="num"><b><?= h(money((int)$i['total'])) ?></b></dd>
    <dt><?= h(t('inv_paid')) ?></dt><dd class="num"><?= h(money((int)$i['paid'])) ?></dd>
    <dt><b><?= h(t('inv_balance')) ?></b></dt><dd class="num"><b><?= h(money((int)$i['total'] - (int)$i['paid'])) ?></b></dd>
  </dl>
  <?php if ($draft && $edit): ?>
    <details open><summary><?= h(t('inv_add_line')) ?></summary>
      <form method="post" action="/finance/invoices/<?= (int)$i['id'] ?>/line" class="form-grid"><?= csrf_field() ?>
        <div class="full"><label for="ldesc"><?= h(t('inv_desc')) ?></label><input id="ldesc" name="description" maxlength="300" required></div>
        <div><label for="lkind"><?= h(t('inv_line_kind')) ?></label><select id="lkind" name="kind"><option value="fee"><?= h(t('lk_fee')) ?></option><option value="expense"><?= h(t('lk_expense')) ?></option><option value="other"><?= h(t('lk_other')) ?></option></select></div>
        <div><label for="lqty"><?= h(t('inv_qty')) ?></label><input id="lqty" name="qty" type="number" min="1" value="1" dir="ltr"></div>
        <div><label for="lunit"><?= h(t('inv_unit')) ?> (<?= h(t('currency')) ?>)</label><input id="lunit" name="unit" inputmode="decimal" dir="ltr" placeholder="0.000" required></div>
        <div class="full"><button class="btn small"><?= h(t('add')) ?></button></div>
      </form></details>
    <?php if ($unbilled): ?><form method="post" action="/finance/invoices/<?= (int)$i['id'] ?>/expenses" class="mt"><?= csrf_field() ?><button class="btn small ghost"><?= h(t('inv_add_expenses', $unbilled)) ?></button></form><?php endif; ?>
    <form method="post" action="/finance/invoices/<?= (int)$i['id'] ?>/draft" class="form-grid mt"><?= csrf_field() ?>
      <div><label for="due_on"><?= h(t('inv_due_on')) ?></label><input id="due_on" name="due_on" type="date" dir="ltr" value="<?= h($i['due_on']) ?>"></div>
      <div class="full"><label for="notes"><?= h(t('inv_notes')) ?></label><textarea id="notes" name="notes" rows="2"><?= h($i['notes']) ?></textarea></div>
      <div class="full"><button class="btn small ghost"><?= h(t('save')) ?></button></div></form>
    <div class="row mt">
      <form method="post" action="/finance/invoices/<?= (int)$i['id'] ?>/issue" data-confirm="<?= h(t('inv_confirm_issue')) ?>"><?= csrf_field() ?><button class="btn"><?= h(t('inv_issue')) ?></button></form>
      <?php if (can('finance', 'delete')): ?><form method="post" action="/finance/invoices/<?= (int)$i['id'] ?>/delete" data-confirm="<?= h(t('inv_confirm_delete')) ?>"><?= csrf_field() ?><button class="btn ghost"><?= h(t('inv_delete_draft')) ?></button></form><?php endif; ?>
    </div>
  <?php endif; ?>
</section>

<?php if (!$draft): ?>
<section class="card">
  <h2><?= h(t('payments')) ?></h2>
  <?php if (!$pays): ?><p class="muted"><?= h(t('empty')) ?></p><?php endif; ?>
  <ul class="plain one">
    <?php foreach ($pays as $p): ?><li><b dir="ltr"><?= h($p['receipt_no']) ?></b> · <?= h(money((int)$p['amount'])) ?> · <?= h(t('pm_' . $p['method'])) ?> · <span dir="ltr"><?= h(date('d/m/Y', strtotime($p['received_on']))) ?></span><?= $p['reference'] ? ' · ' . h($p['reference']) : '' ?>
      <?php if ($p['voided_at']): ?> <span class="tag late"><?= h(t('pay_voided')) ?>: <?= h($p['void_reason']) ?></span>
      <?php else: ?><?php if (can('finance', 'download')): ?> <a class="small" href="/finance/invoices/<?= (int)$i['id'] ?>/payments/<?= (int)$p['id'] ?>/receipt" target="_blank" rel="noopener"><?= h(t('receipt')) ?> PDF</a><?php endif; ?>
        <?php if ($create): ?><details class="inline-details"><summary class="small"><?= h(t('pay_void')) ?></summary><form method="post" action="/finance/invoices/<?= (int)$i['id'] ?>/payments/<?= (int)$p['id'] ?>/void" class="row"><?= csrf_field() ?><input name="reason" required placeholder="<?= h(t('reason')) ?>" class="auto"><button class="btn small ghost"><?= h(t('pay_void')) ?></button></form></details><?php endif; ?><?php endif; ?></li><?php endforeach; ?>
  </ul>
  <?php if ($create && $i['status'] === 'issued' && (int)$i['paid'] < (int)$i['total']): ?>
    <form method="post" action="/finance/invoices/<?= (int)$i['id'] ?>/notice" class="mt"><?= csrf_field() ?><button class="btn small ghost"><?= icon('bell') ?><?= h(t('notice_prepare')) ?></button></form>
    <form method="post" action="/finance/invoices/<?= (int)$i['id'] ?>/payment" class="form-grid mt"><?= csrf_field() ?>
      <div><label for="pamount"><?= h(t('pay_amount')) ?> (<?= h(t('currency')) ?>)</label><input id="pamount" name="amount" inputmode="decimal" dir="ltr" required value="<?= h(money_plain((int)$i['total'] - (int)$i['paid'])) ?>"></div>
      <div><label for="pmethod"><?= h(t('pay_method')) ?></label><select id="pmethod" name="method" required><option value=""><?= h(t('choose')) ?></option><?php foreach (PAY_METHODS as $pm): ?><option value="<?= $pm ?>"><?= h(t('pm_' . $pm)) ?></option><?php endforeach; ?></select></div>
      <div><label for="pdate"><?= h(t('pay_date')) ?></label><input id="pdate" name="received_on" type="date" dir="ltr" value="<?= date('Y-m-d') ?>"></div>
      <div><label for="pref"><?= h(t('pay_ref')) ?></label><input id="pref" name="reference" maxlength="120"></div>
      <div class="full"><button class="btn"><?= h(t('pay_record')) ?></button></div>
      <p class="full muted small"><?= h(t('pay_online_off')) ?></p>
    </form>
  <?php endif; ?>
</section>
<?php if ($edit && $i['status'] === 'issued'): ?>
<section class="card">
  <form method="post" action="/finance/invoices/<?= (int)$i['id'] ?>/portal" class="row"><?= csrf_field() ?>
    <input type="hidden" name="on" value="<?= $i['portal_visible'] ? '' : '1' ?>"><button class="btn small ghost"><?= h(t($i['portal_visible'] ? 'inv_portal_off' : 'inv_portal_on')) ?></button></form>
  <?php if ((int)$i['paid'] === 0): ?>
    <details><summary><?= h(t('inv_void')) ?></summary><form method="post" action="/finance/invoices/<?= (int)$i['id'] ?>/void" class="row"><?= csrf_field() ?><input name="reason" required placeholder="<?= h(t('reason')) ?>" class="auto"><button class="btn small danger"><?= h(t('inv_void')) ?></button></form><p class="muted small"><?= h(t('inv_void_help')) ?></p></details>
  <?php endif; ?>
</section>
<?php endif; ?>
<?php endif; ?>
