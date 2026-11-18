<?php $bal = trust_balance((int)$m['client_id']); ?>
<section class="card">
  <h1><?= h($title) ?> <span class="ref muted" dir="ltr"><?= h($m['ref']) ?></span></h1>
  <?php if ($err): ?><div class="flash err" role="alert"><?= h(t($err)) ?></div><?php endif; ?>
  <form method="post" class="form-grid"><?= csrf_field() ?>
    <div class="full"><label for="description"><?= h(t('exp_desc')) ?></label><input id="description" name="description" maxlength="300" required value="<?= h($d['description'] ?? '') ?>" placeholder="<?= h(t('exp_desc_ph')) ?>"></div>
    <div><label for="amount"><?= h(t('pay_amount')) ?> (<?= h(t('currency')) ?>)</label><input id="amount" name="amount" inputmode="decimal" dir="ltr" required value="<?= h($d['amount'] ?? '') ?>"></div>
    <div><label for="spent_on"><?= h(t('date')) ?></label><input id="spent_on" name="spent_on" type="date" dir="ltr" value="<?= h($d['spent_on'] ?? date('Y-m-d')) ?>"></div>
    <fieldset class="full"><legend><?= h(t('exp_category')) ?></legend>
      <?php foreach (EXPENSE_CATEGORIES as $ck): ?><label class="radio"><input type="radio" name="category" value="<?= $ck ?>"<?= ($d['category'] ?? 'expense') === $ck ? ' checked' : '' ?>> <?= h(t('ec_' . $ck)) ?></label><?php endforeach; ?>
      <p class="muted small"><?= h(t('exp_category_help')) ?></p></fieldset>
    <fieldset class="full"><legend><?= h(t('exp_paid_from')) ?></legend>
      <label class="radio"><input type="radio" name="paid_from" value="office"<?= ($d['paid_from'] ?? 'office') === 'office' ? ' checked' : '' ?>> <?= h(t('exp_from_office')) ?></label>
      <label class="radio"><input type="radio" name="paid_from" value="trust"<?= ($d['paid_from'] ?? '') === 'trust' ? ' checked' : '' ?>> <?= h(t('exp_from_trust', money($bal))) ?></label></fieldset>
    <div class="full"><label class="radio"><input type="checkbox" name="billable" value="1"<?= !isset($d['description']) || !empty($d['billable']) ? ' checked' : '' ?>> <?= h(t('exp_billable')) ?></label><p class="muted small"><?= h(t('exp_billable_help')) ?></p></div>
    <div class="full row"><button class="btn"><?= h(t('save')) ?></button><a class="btn ghost" href="/matters/<?= (int)$m['id'] ?>#finance"><?= h(t('cancel')) ?></a></div>
  </form>
</section>
