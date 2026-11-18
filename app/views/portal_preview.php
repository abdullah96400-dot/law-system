<?php $me = current_staff(); $edit = !office_readonly() && can('matters', 'edit'); $acc = portal_user_for_contact((int)$cp['contact_id']);
$u = ['id' => 0, 'contact_id' => (int)$cp['contact_id'], 'lang' => lang()]; $base = portal_base(); $preview = true; $err = null; ?>
<p class="muted small"><a href="/matters/<?= (int)$m['id'] ?>#portal" dir="ltr"><?= h($m['ref']) ?></a> — <?= h($m['title']) ?></p>
<div class="page-head">
  <div><h1><?= h(t('pv_title')) ?></h1><p><?= h(t('pv_help', contact_label($cp))) ?></p>
    <div class="row mt"><?= $cp['portal_visible'] ? '<span class="tag">' . icon('portal') . h(t('portal_shared')) . '</span>' : '<span class="tag late">' . h(t('pv_not_shared_yet')) . '</span>' ?>
      <?= !$acc || $acc['status'] !== 'active' ? '<span class="tag">' . h(t('portal_no_account_short')) . '</span>' : '' ?></div></div>
  <div class="row">
    <?php if (count($cps) > 1): ?><form method="get" class="row"><label class="sr" for="contact"><?= h(t('ct_client')) ?></label><select id="contact" name="contact" class="auto"><?php foreach ($cps as $p): ?><option value="<?= (int)$p['contact_id'] ?>"<?= (int)$p['contact_id'] === (int)$cp['contact_id'] ? ' selected' : '' ?>><?= h(contact_label($p)) ?></option><?php endforeach; ?></select><button class="btn small ghost"><?= h(t('show')) ?></button></form><?php endif; ?>
    <?php if ($edit): ?><form method="post" action="/matters/<?= (int)$m['id'] ?>/portal/<?= (int)$cp['id'] ?>/<?= $cp['portal_visible'] ? 'off' : 'on' ?>"<?= $cp['portal_visible'] ? '' : ' data-confirm="' . h(t('portal_confirm_share')) . '"' ?>><?= csrf_field() ?><button class="btn small<?= $cp['portal_visible'] ? ' ghost' : '' ?>"><?= icon('portal') ?><?= h(t($cp['portal_visible'] ? 'portal_stop_sharing' : 'pv_share_now')) ?></button></form><?php endif; ?>
  </div>
</div>
<p class="note small"><?= h(t('pv_note')) ?></p>
<div class="preview-frame" inert aria-label="<?= h(t('pv_title')) ?>">
  <?php include __DIR__ . '/portal_matter.php'; ?>
</div>
<?php if ($log = share_log((int)$m['id'], 30)): ?>
<section class="card mt"><h2><?= h(t('share_log')) ?></h2>
  <ul class="plain one small"><?php foreach ($log as $x): ?><li><span dir="ltr" class="muted"><?= h(fmt_dt($x['at'])) ?></span> · <?= h(staff_name($x['staff_id'] ? (int)$x['staff_id'] : null)) ?> · <?= h(t($x['shared'] ? 'share_on' : 'share_off', t('sk_' . $x['kind']))) ?></li><?php endforeach; ?></ul></section>
<?php endif; ?>
