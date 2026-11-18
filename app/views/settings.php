<?php $me = current_staff(); ?>
<?php $edit = can('settings', 'edit') && !office_readonly(); ?>
<section class="card">
  <h1><?= h(t('settings_menu')) ?></h1>
  <nav class="hubs mt" aria-label="<?= h(t('settings_menu')) ?>"><?php
    $hs = [];
    if (can('settings', 'view')) $hs[] = ['#office', 'settings', 'ink', t('office_settings'), t('hub_office_sub')];
    if (can('staff', 'view')) $hs[] = ['/staff', 'users', 'blue', t('staff'), t('tile_staff')];
    if (can('settings', 'view')) { $hs[] = ['/roles', 'shield', 'violet', t('roles'), t('tile_roles')]; $hs[] = ['/settings/lists', 'tasks', 'petrol', t('lists'), t('tile_lists')]; if (feature_on('branches')) $hs[] = ['/settings/branches', 'globe', 'green', t('branches'), t('tile_branches')]; }
    if (site_can_manage($me)) $hs[] = ['/site', 'globe', 'blue', t('site_admin'), t(site_is_live() ? 'site_state_live' : 'site_state_off')];
    if (can('audit', 'view')) $hs[] = ['/audit', 'clock', 'gold', t('act_log'), t('tile_audit')];
    if (trash_can_view($me)) { $hs[] = ['/settings/backups', 'shield', 'petrol', t('bk_title'), t('hub_backup_sub')]; $hs[] = ['/trash', 'trash', 'red', t('mod_trash'), t('hub_trash_sub')]; }
    if (feature_on('templates') && can('documents', 'view')) $hs[] = ['/templates', 'contract', 'gold', t('mod_templates'), ''];
    if (feature_on('import') && (import_allowed('contacts', $me) || import_allowed('matters', $me))) $hs[] = ['/import', 'upload', 'blue', t('imp_title'), t('tile_import')];
    if ($me['role'] === 'partner') { $hs[] = ['/settings/export', 'inbox', 'ink', t('export_title'), t('tile_export')]; $hs[] = ['/settings/support', 'help', 'green', t('support_title'), t('tile_support')]; }
    foreach ($hs as $x) echo hub_tile(...$x); ?></nav>
  </div>
</section>
<?php if (can('settings', 'view')): ?>
<section class="card" id="office">
  <h2><?= h(t('office_settings')) ?></h2>
  <form method="post" action="/settings/office" class="form-grid"><?= csrf_field() ?>
    <div class="full"><label class="radio"><input type="checkbox" name="require_2fa_all" value="1"<?= $o['require_2fa_all'] ? ' checked' : '' ?><?= $edit ? '' : ' disabled' ?>> <?= h(t('set_2fa_all')) ?></label></div>
    <div><label for="escalate_days"><?= h(t('set_escalate')) ?></label><input id="escalate_days" name="escalate_days" type="number" min="1" max="60" dir="ltr" value="<?= h($o['escalate_days']) ?>"<?= $edit ? '' : ' disabled' ?> aria-describedby="esc-help"><p id="esc-help" class="muted small"><?= h(t('set_escalate_help')) ?></p></div>
    <?php if ($edit): ?><div class="full"><button class="btn"><?= h(t('save')) ?></button></div><?php endif; ?>
  </form>
</section>
<section class="card" id="office-logo">
  <h2><?= h(t('set_logo')) ?></h2>
  <div class="row"><?php if (office_logo_file()): ?><img src="/office/logo.jpg?v=<?= h(substr((string)$o['logo'], 5, 6)) ?>" alt="<?= h(t('set_logo')) ?>" class="logo-preview"><?php else: ?><span class="muted small"><?= h(t('set_logo_none')) ?></span><?php endif; ?></div>
  <?php if ($edit): ?><form method="post" action="/settings/logo" enctype="multipart/form-data" class="row mt"><?= csrf_field() ?><label class="sr" for="logo"><?= h(t('set_logo')) ?></label><input id="logo" name="logo" type="file" accept="image/jpeg,image/png" required class="auto"><button class="btn small"><?= h(t('upload')) ?></button></form>
  <p class="muted small"><?= h(t('set_logo_help')) ?></p><?php endif; ?>
</section>
<?php if (can('finance', 'view')): ?>
<section class="card" id="finance">
  <h2><?= h(t('set_finance')) ?></h2>
  <p class="note small"><?= h(t('set_finance_note')) ?></p>
  <?php $fe2 = $edit && can('finance', 'edit'); ?>
  <form method="post" action="/settings/finance" class="form-grid"><?= csrf_field() ?>
    <div><label for="tax_rate"><?= h(t('set_tax_rate')) ?></label><input id="tax_rate" name="tax_rate" inputmode="decimal" dir="ltr" value="<?= h($o['tax_rate_bp'] ? $o['tax_rate_bp'] / 100 : '') ?>" placeholder="0"<?= $fe2 ? '' : ' disabled' ?>></div>
    <div><label for="tax_label"><?= h(t('set_tax_label')) ?></label><input id="tax_label" name="tax_label" maxlength="60" value="<?= h($o['tax_label']) ?>"<?= $fe2 ? '' : ' disabled' ?>></div>
    <div><label for="tax_no"><?= h(t('set_tax_no')) ?></label><input id="tax_no" name="tax_no" maxlength="60" dir="ltr" value="<?= h($o['tax_no']) ?>"<?= $fe2 ? '' : ' disabled' ?>></div>
    <div><label for="notice_days"><?= h(t('set_notice_days')) ?></label><input id="notice_days" name="notice_days" dir="ltr" maxlength="20" value="<?= h($o['notice_days']) ?>" placeholder="7,1"<?= $fe2 ? '' : ' disabled' ?>><p class="muted small"><?= h(t('set_notice_days_help')) ?></p></div>
    <div class="full"><label for="invoice_footer"><?= h(t('set_invoice_footer')) ?></label><textarea id="invoice_footer" name="invoice_footer" rows="2" maxlength="600"<?= $fe2 ? '' : ' disabled' ?>><?= h($o['invoice_footer']) ?></textarea></div>
    <?php if ($fe2): ?><div class="full"><button class="btn"><?= h(t('save')) ?></button></div><?php endif; ?>
  </form>
</section>
<?php endif; ?>
<section class="card" id="templates">
  <h2><?= h(t('tpl_title')) ?></h2>
  <p class="muted small"><?= h(t('tpl_help')) ?></p>
  <?php foreach ($tpls as $tp): $lines = implode("\n", array_map(fn($i) => $i['title'] . ($i['days'] !== null ? ' | ' . $i['days'] : ''), json_decode($tp['items'], true) ?: [])); ?>
    <form method="post" action="/settings/templates/<?= (int)$tp['id'] ?>" class="tpl"><?= csrf_field() ?>
      <label for="tn-<?= (int)$tp['id'] ?>"><?= h(t('tpl_name')) ?></label><input id="tn-<?= (int)$tp['id'] ?>" name="name" value="<?= h($tp['name']) ?>"<?= $edit ? '' : ' disabled' ?>>
      <label for="ti-<?= (int)$tp['id'] ?>"><?= h(t('tpl_items')) ?></label><textarea id="ti-<?= (int)$tp['id'] ?>" name="items" rows="4"<?= $edit ? '' : ' disabled' ?>><?= h($lines) ?></textarea>
      <?php if ($edit): ?><label class="radio"><input type="checkbox" name="active" value="1"<?= $tp['active'] ? ' checked' : '' ?>> <?= h(t('in_use')) ?></label> <button class="btn small ghost"><?= h(t('save')) ?></button><?php endif; ?>
    </form>
  <?php endforeach; ?>
  <?php if ($edit): ?>
    <form method="post" action="/settings/templates/new" class="tpl"><?= csrf_field() ?>
      <h3><?= h(t('tpl_new')) ?></h3>
      <label for="tn-new"><?= h(t('tpl_name')) ?></label><input id="tn-new" name="name" placeholder="<?= h(t('tpl_name_ph')) ?>">
      <label for="ti-new"><?= h(t('tpl_items')) ?></label><textarea id="ti-new" name="items" rows="4" placeholder="<?= h(t('tpl_items_ph')) ?>"></textarea>
      <button class="btn small"><?= h(t('add')) ?></button>
    </form>
  <?php endif; ?>
</section>
<?php endif; ?>
