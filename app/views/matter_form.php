<?php
$v = fn($k) => h($d[$k] ?? '');
$sel = fn($k, $id) => (int)($d[$k] ?? 0) === (int)$id ? ' selected' : '';
$isNew = $m === null;
$showSummary = $isNew ? can('legal_notes', 'create') : can('legal_notes', 'edit');
$checks = $isNew ? checks_available() : [];
$lk = function (string $field, string $kind, bool $req = false) use ($sel, $err): string {
  $o = '<select id="' . $field . '" name="' . $field . '"' . ($req ? ' required' : '') . fattr($err, $field) . '>' . ($req ? '' : '<option value="">' . h(t('none')) . '</option>');
  foreach (lookups($kind) as $l) if ($kind !== 'status' || !$l['is_closed']) $o .= '<option value="' . (int)$l['id'] . '"' . $sel($field, $l['id']) . '>' . h(lookup_label($l)) . '</option>';
  return $o . '</select>' . ferr($err, $field);
};
?>
<div class="page-head"><div><h1><?= h($title) ?><?= $m ? ' <span class="ref muted" dir="ltr">' . h($m['ref']) . '</span>' : '' ?></h1><p class="req-note"><?= h(t('req_note')) ?></p></div></div>
<section class="card">
  <?php if ($err): ?><div class="flash err" role="alert"><span><?= h(t($err)) ?></span></div><?php endif; ?>
  <?php if ($isNew && !$checks): ?>
    <?= empty_state(t('matter_no_checks'), t('matters_empty_help'), can('conflicts', 'create') ? '/conflicts/new' : null, t('check_new'), 'shield') ?>
  <?php else: ?>
  <form method="post" class="form-grid">
    <?= csrf_field() ?>
    <fieldset>
      <legend><?= h(t('grp_case')) ?></legend>
      <div class="form-grid">
      <?php if ($isNew): ?>
        <div class="full"><label for="check_id"><?= h(t('matter_check')) ?><?= req_mark() ?></label>
          <select id="check_id" name="check_id" required aria-describedby="check-help"<?= fattr($err, 'check_id') ?>><option value=""><?= h(t('choose')) ?></option>
            <?php foreach ($checks as $k): ?><option value="<?= (int)$k['id'] ?>"<?= $sel('check_id', $k['id']) ?>><?= h($k['ref'] . ' — ' . $k['subject'] . ' (' . t('chk_' . $k['status']) . ')') ?></option><?php endforeach; ?>
          </select><?= ferr($err, 'check_id') ?>
          <span id="check-help" class="hint"><?= h(t('matter_check_help')) ?></span></div>
        <div class="full"><label for="client_id"><?= h(t('ct_client')) ?><?= req_mark() ?></label>
          <select id="client_id" name="client_id" required aria-describedby="client-help"<?= fattr($err, 'client_id') ?>><option value=""><?= h(t('choose')) ?></option>
            <?php foreach (client_options() as $c): ?><option value="<?= (int)$c['id'] ?>"<?= $sel('client_id', $c['id']) ?>><?= h(contact_label($c + ['name_ar' => $c['name_ar'], 'name_en' => $c['name_en']])) ?></option><?php endforeach; ?>
          </select><?= ferr($err, 'client_id') ?>
          <span id="client-help" class="hint"><?= h(t('matter_client_help')) ?> <a href="/clients/new"><?= h(t('contact_new')) ?></a></span></div>
      <?php endif; ?>
        <div class="full"><label for="title"><?= h(t('matter_title')) ?><?= req_mark() ?></label><input id="title" name="title" required maxlength="200" value="<?= $v('title') ?>"<?= fattr($err, 'title') ?>><?= ferr($err, 'title') ?></div>
        <div><label for="case_type_id"><?= h(t('matter_type')) ?></label><?= $lk('case_type_id', 'case_type') ?></div>
        <?php if ($isNew): ?><div><label for="status_id"><?= h(t('status')) ?><?= req_mark() ?></label><?= $lk('status_id', 'status', true) ?></div><?php endif; ?>
      </div>
    </fieldset>
    <fieldset>
      <legend><?= h(t('grp_court')) ?></legend>
      <div class="form-grid">
        <div><label for="court_id"><?= h(t('matter_court')) ?></label><?= $lk('court_id', 'court') ?>
          <?php if (!lookups('court')): ?><span class="hint"><?= h(t('matter_no_courts')) ?><?php if (can('settings', 'edit')): ?> <a href="/settings/lists#court"><?= h(t('lists')) ?></a><?php endif; ?></span><?php endif; ?></div>
        <div><label for="court_level_id"><?= h(t('matter_level')) ?></label><?= $lk('court_level_id', 'court_level') ?></div>
        <div><label for="circuit"><?= h(t('matter_circuit')) ?></label><input id="circuit" name="circuit" maxlength="120" value="<?= $v('circuit') ?>"></div>
        <div><label for="official_no"><?= h(t('matter_official_no')) ?></label><input id="official_no" name="official_no" dir="ltr" maxlength="80" value="<?= $v('official_no') ?>"></div>
      </div>
    </fieldset>
    <fieldset>
      <legend><?= h(t('grp_people')) ?></legend>
      <div class="form-grid">
        <div><label for="lead_id"><?= h(t('matter_lead')) ?><?= req_mark() ?></label><select id="lead_id" name="lead_id" required<?= fattr($err, 'lead_id') ?>><?php foreach (active_staff() as $s): ?><option value="<?= (int)$s['id'] ?>"<?= $sel('lead_id', $s['id']) ?>><?= h($s['full_name'] . ' — ' . t('role_' . $s['role'])) ?></option><?php endforeach; ?></select><?= ferr($err, 'lead_id') ?></div>
        <?php if ($brs = branches()): ?><div><label for="branch_id"><?= h(t('branch')) ?></label><select id="branch_id" name="branch_id"><option value=""><?= h(t('none_dash')) ?></option><?php foreach ($brs as $b): ?><option value="<?= (int)$b['id'] ?>"<?= $sel('branch_id', $b['id']) ?>><?= h($b['name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
        <div><label for="opened_on"><?= h(t('matter_opened_on')) ?></label><input id="opened_on" name="opened_on" type="date" dir="ltr" value="<?= $v('opened_on') ?>"></div>
        <div><label for="referral"><?= h(t('matter_referral')) ?></label><input id="referral" name="referral" maxlength="200" value="<?= $v('referral') ?>"></div>
      </div>
    </fieldset>
    <fieldset>
      <legend><?= h(t('grp_access')) ?></legend>
      <?php if ($showSummary): ?>
      <label for="summary"><?= h(t('matter_summary')) ?> <span class="tag"><?= icon('lock') ?><?= h(t('internal')) ?></span></label><textarea id="summary" name="summary" rows="5" aria-describedby="summary-help"><?= $v('summary') ?></textarea><span id="summary-help" class="hint"><?= h(t('matter_summary_help')) ?></span>
      <?php endif; ?>
      <label class="radio"><input type="checkbox" name="restricted" value="1"<?= !empty($d['restricted']) ? ' checked' : '' ?>> <?= h(t('matter_restricted_label')) ?></label><span class="hint"><?= h(t('matter_restricted_help')) ?></span>
    </fieldset>
    <div class="full form-actions">
      <button class="btn"><?= h(t($isNew ? 'matter_open' : 'save')) ?></button>
      <a class="btn ghost" href="<?= $m ? '/matters/' . (int)$m['id'] : '/matters' ?>"><?= h(t('cancel')) ?></a>
    </div>
  </form>
  <?php endif; ?>
</section>
