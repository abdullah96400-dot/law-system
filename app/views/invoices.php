<?php $qs = http_build_query(array_filter(['state' => $f['state'], 'from' => $f['from'], 'to' => $f['to'], 'matter' => $f['matter'] ?: null, 'client' => $f['client'] ?: null])); ?>
<section class="card">
  <div class="head-row"><h1><?= h(t('invoices')) ?></h1>
    <?php if (can('finance', 'download')): ?><a class="btn ghost" href="/finance/invoices.xlsx?<?= h($qs) ?>"><?= h(t('export_xlsx')) ?></a><?php endif; ?></div>
  <nav class="tabs" aria-label="<?= h(t('status')) ?>">
    <?php foreach (['' => 'all', 'open' => 'is_open', 'overdue' => 'is_overdue', 'paid' => 'is_paid', 'draft' => 'is_draft', 'void' => 'is_void'] as $k => $lbl): ?><a href="/finance/invoices<?= $k ? '?state=' . $k : '' ?>"<?= $f['state'] === $k ? ' aria-current="page"' : '' ?>><?= h(t($lbl)) ?></a><?php endforeach; ?>
  </nav>
  <form method="get" action="/finance/invoices" class="filters"><input type="hidden" name="state" value="<?= h($f['state']) ?>">
    <div><label for="from"><?= h(t('inv_issued_from')) ?></label><input id="from" name="from" type="date" dir="ltr" value="<?= h($f['from']) ?>"></div>
    <div><label for="to"><?= h(t('inv_issued_to')) ?></label><input id="to" name="to" type="date" dir="ltr" value="<?= h($f['to']) ?>"></div>
    <div><button class="btn"><?= h(t('show')) ?></button></div></form>
  <p class="muted small"><?= h(t('inv_sum', (int)$sum['n'], money((int)$sum['total']), money((int)$sum['paid']))) ?></p>
  <div class="table-wrap">
  <table class="stack">
    <caption class="sr"><?= h(t('invoices')) ?></caption>
    <thead><tr><th scope="col"><?= h(t('inv_number')) ?></th><th scope="col"><?= h(t('ct_client')) ?></th><th scope="col"><?= h(t('appt_matter')) ?></th><th scope="col"><?= h(t('inv_total')) ?></th><th scope="col"><?= h(t('inv_balance')) ?></th><th scope="col"><?= h(t('status')) ?></th></tr></thead>
    <tbody>
    <?php if (!$list): ?><tr><td colspan="6" class="muted"><?= h(t('empty')) ?></td></tr><?php endif; ?>
    <?php foreach ($list as $i): $st = invoice_state($i); ?>
      <tr>
        <td data-label="<?= h(t('inv_number')) ?>"><a href="/finance/invoices/<?= (int)$i['id'] ?>" dir="ltr"><?= h($i['number'] ?: t('inv_draft')) ?></a><?php if ($i['issued_on']): ?><br><span class="muted small" dir="ltr"><?= h(date('d/m/Y', strtotime($i['issued_on']))) ?></span><?php endif; ?></td>
        <td data-label="<?= h(t('ct_client')) ?>"><?= h(lang() === 'ar' ? ($i['name_ar'] ?: $i['name_en']) : ($i['name_en'] ?: $i['name_ar'])) ?></td>
        <td data-label="<?= h(t('appt_matter')) ?>"><a href="/matters/<?= (int)$i['matter_id'] ?>" dir="ltr"><?= h($i['matter_ref']) ?></a></td>
        <td data-label="<?= h(t('inv_total')) ?>" class="num"><?= h(money((int)$i['total'])) ?></td>
        <td data-label="<?= h(t('inv_balance')) ?>" class="num"><?= h(money((int)$i['total'] - (int)$i['paid'])) ?></td>
        <td data-label="<?= h(t('status')) ?>"><span class="inv inv-<?= h($st) ?>"><?= h(t('is_' . $st)) ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</section>
