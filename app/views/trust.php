<section class="card">
  <h1><?= h(t('trust')) ?><?= $client ? ' — ' . h(contact_label($client)) : '' ?></h1>
  <p class="muted small"><?= h(t('trust_help')) ?></p>
  <?php if ($err): ?><div class="flash err" role="alert"><?= h(t($err)) ?></div><?php endif; ?>
  <?php if (!$client): ?>
    <?php if (!$rows): ?><p class="muted"><?= h(t('trust_empty')) ?></p><?php endif; ?>
    <ul class="plain one"><?php foreach ($rows as $r): ?><li><a href="/finance/trust/<?= (int)$r['id'] ?>"><?= h(lang() === 'ar' ? ($r['name_ar'] ?: $r['name_en']) : ($r['name_en'] ?: $r['name_ar'])) ?></a> — <b><?= h(money((int)$r['bal'])) ?></b> <span class="muted small" dir="ltr"><?= h(date('d/m/Y', strtotime($r['last']))) ?></span></li><?php endforeach; ?></ul>
    <p class="muted small"><?= h(t('trust_open_client')) ?></p>
  <?php else: $entries = orows('SELECT * FROM trust_entries WHERE office_id = :office AND client_id = :c ORDER BY on_date, id', ['c' => $client['id']]); $run = 0; ?>
    <p><b><?= h(t('trust_balance', money(trust_balance((int)$client['id'])))) ?></b></p>
    <div class="table-wrap"><table class="stack">
      <caption class="sr"><?= h(t('trust')) ?></caption>
      <thead><tr><th scope="col"><?= h(t('date')) ?></th><th scope="col"><?= h(t('exp_desc')) ?></th><th scope="col"><?= h(t('trust_in')) ?></th><th scope="col"><?= h(t('trust_out')) ?></th><th scope="col"><?= h(t('trust_running')) ?></th></tr></thead>
      <tbody><?php foreach ($entries as $e): $run += $e['kind'] === 'in' ? (int)$e['amount'] : -(int)$e['amount']; ?><tr>
        <td data-label="<?= h(t('date')) ?>" dir="ltr"><?= h(date('d/m/Y', strtotime($e['on_date']))) ?></td><td data-label="<?= h(t('exp_desc')) ?>"><?= h($e['description']) ?><?= $e['reference'] ? ' · ' . h($e['reference']) : '' ?></td>
        <td data-label="<?= h(t('trust_in')) ?>" class="num"><?= $e['kind'] === 'in' ? h(money_plain((int)$e['amount'])) : '' ?></td><td data-label="<?= h(t('trust_out')) ?>" class="num"><?= $e['kind'] === 'out' ? h(money_plain((int)$e['amount'])) : '' ?></td>
        <td data-label="<?= h(t('trust_running')) ?>" class="num"><?= h(money_plain($run)) ?></td></tr><?php endforeach; ?></tbody>
    </table></div>
    <?php if (!office_readonly() && can('finance', 'create')): ?>
      <form method="post" class="form-grid mt"><?= csrf_field() ?>
        <fieldset class="full"><legend><?= h(t('trust_entry')) ?></legend>
          <label class="radio"><input type="radio" name="kind" value="in" checked> <?= h(t('trust_in')) ?></label><label class="radio"><input type="radio" name="kind" value="out"> <?= h(t('trust_out')) ?></label></fieldset>
        <div><label for="tamount"><?= h(t('pay_amount')) ?> (<?= h(t('currency')) ?>)</label><input id="tamount" name="amount" inputmode="decimal" dir="ltr" required></div>
        <div><label for="tdate"><?= h(t('date')) ?></label><input id="tdate" name="on_date" type="date" dir="ltr" value="<?= date('Y-m-d') ?>"></div>
        <div class="full"><label for="tdesc"><?= h(t('exp_desc')) ?></label><input id="tdesc" name="description" maxlength="300" required></div>
        <div><label for="tref"><?= h(t('pay_ref')) ?></label><input id="tref" name="reference" maxlength="120"></div>
        <div class="full"><button class="btn"><?= h(t('save')) ?></button></div></form>
    <?php endif; ?>
  <?php endif; ?>
</section>
