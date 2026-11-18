<?php $v = fn($k) => h($d[$k] ?? ''); $docs = orows('SELECT id, title FROM documents WHERE office_id = :office AND matter_id = :m AND archived = 0 ORDER BY id DESC', ['m' => $m['id']]);
$mv = fn($k) => isset($d[$k]) && is_numeric($d[$k]) && $a ? money_plain((int)$d[$k]) : ($d[$k] ?? ''); ?>
<section class="card">
  <h1><?= h($title) ?> <span class="ref muted" dir="ltr"><?= h($m['ref']) ?></span></h1>
  <?php if ($err): ?><div class="flash err" role="alert"><?= h(t($err)) ?></div><?php endif; ?>
  <form method="post" class="form-grid"><?= csrf_field() ?>
    <fieldset class="full"><legend><?= h(t('fee_kind')) ?></legend>
      <?php foreach (AGREEMENT_KINDS as $k): ?><label class="radio"><input type="radio" name="kind" value="<?= $k ?>" required<?= ($d['kind'] ?? '') === $k ? ' checked' : '' ?>> <?= h(t('fk_' . $k)) ?></label><?php endforeach; ?></fieldset>
    <div><label for="total"><?= h(t('fee_total')) ?> (<?= h(t('currency')) ?>)</label><input id="total" name="total" inputmode="decimal" dir="ltr" value="<?= h($mv('total')) ?>"></div>
    <div><label for="advance"><?= h(t('fee_advance')) ?> (<?= h(t('currency')) ?>)</label><input id="advance" name="advance" inputmode="decimal" dir="ltr" value="<?= h($mv('advance')) ?>"><p class="muted small"><?= h(t('fee_advance_help')) ?></p></div>
    <div><label for="hourly_rate"><?= h(t('fee_hourly')) ?> (<?= h(t('currency')) ?>)</label><input id="hourly_rate" name="hourly_rate" inputmode="decimal" dir="ltr" value="<?= h($mv('hourly_rate')) ?>"></div>
    <div><label for="signed_on"><?= h(t('fee_signed_on')) ?></label><input id="signed_on" name="signed_on" type="date" dir="ltr" value="<?= $v('signed_on') ?>"></div>
    <div><label for="document_id"><?= h(t('fee_document')) ?></label><select id="document_id" name="document_id"><option value=""><?= h(t('none')) ?></option><?php foreach ($docs as $x): ?><option value="<?= (int)$x['id'] ?>"<?= (int)($d['document_id'] ?? 0) === (int)$x['id'] ? ' selected' : '' ?>><?= h($x['title']) ?></option><?php endforeach; ?></select></div>
    <div class="full"><label for="terms"><?= h(t('fee_terms')) ?></label><textarea id="terms" name="terms" rows="4"><?= $v('terms') ?></textarea></div>
    <div class="full row"><button class="btn"><?= h(t('save')) ?></button><a class="btn ghost" href="/matters/<?= (int)$m['id'] ?>#finance"><?= h(t('cancel')) ?></a></div>
  </form>
</section>
