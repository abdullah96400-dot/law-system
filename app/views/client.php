<?php $ro = office_readonly(); ?>
<?php $me = current_staff(); $ms = can('matters', 'view') ? matters_for_contact((int)$c['id'], $me) : []; ?>
<div class="page-head">
  <div>
    <h1><?= h(contact_label($c)) ?></h1>
    <div class="row mt"><span class="badge-s <?= $c['is_client'] ? 'b-green' : 'b-blue' ?>"><?= h(t($c['is_client'] ? 'ct_client' : 'ct_party')) ?></span><span class="tag"><?= h(t('kind_' . $c['kind'])) ?></span><?= $c['archived'] ? '<span class="tag">' . h(t('archived')) . '</span>' : '' ?></div>
  </div>
  <div class="row">
    <?php if (!$ro && can('clients', 'edit')): ?><a class="btn ghost small" href="/clients/<?= (int)$c['id'] ?>/edit"><?= h(t('edit')) ?></a><?php endif; ?>
    <?php if (!$ro && can('conflicts', 'create')): ?><a class="btn small" href="/conflicts/new?contact=<?= (int)$c['id'] ?>"><?= icon('shield') ?><?= h(t('check_for_contact')) ?></a><?php endif; ?>
  </div>
</div>
<div class="ptabs" data-tabs aria-label="<?= h(t('mod_clients')) ?>">
  <button type="button" data-for="details"><?= h(t('tab_details')) ?></button>
  <button type="button" data-for="matters"><?= h(t('tab_matters')) ?> <span class="n"><?= count($ms) ?></span></button>
  <button type="button" data-for="checks"><?= h(t('tab_checks')) ?></button>
  <button type="button" data-for="docs"><?= h(t('tab_docs')) ?></button>
  <button type="button" data-for="portal"><?= h(t('tab_portal')) ?></button>
</div>
<section class="card" data-panel="details">
  <h2><?= h(t('grp_contact')) ?></h2>
  <dl class="facts">
    <dt><?= h(t('name_ar')) ?></dt><dd dir="rtl"><?= h($c['name_ar'] ?: '—') ?></dd>
    <dt><?= h(t('name_en')) ?></dt><dd dir="ltr"><?= h($c['name_en'] ?: '—') ?></dd>
    <?php if ($c['aliases']): ?><dt><?= h(t('aliases')) ?></dt><dd><?= h($c['aliases']) ?></dd><?php endif; ?>
    <dt><?= h(t('phone')) ?></dt><dd><span dir="ltr"><?= h($c['phone'] ?: '—') ?></span></dd>
    <dt><?= h(t('email')) ?></dt><dd><span dir="ltr"><?= h($c['email'] ?: '—') ?></span></dd>
    <dt><?= h(t('pref_lang')) ?></dt><dd><?= $c['pref_lang'] === 'en' ? 'English' : 'العربية' ?></dd>
    <?php if ($c['address']): ?><dt><?= h(t('address')) ?></dt><dd><?= h($c['address']) ?></dd><?php endif; ?>
    <?php if ($c['id_type']): ?><dt><?= h(t('idt_' . $c['id_type'])) ?></dt><dd><span dir="ltr"><?= h($c['id_number']) ?></span></dd><?php endif; ?>
    <dt><?= h(t('added')) ?></dt><dd><span dir="ltr"><?= h(fmt_dt($c['created_at'])) ?></span> · <?= h(staff_name($c['created_by'] ? (int)$c['created_by'] : null)) ?></dd>
  </dl>
  <?php if ($c['notes']): ?><h3 class="mt"><?= h(t('internal_notes')) ?> <span class="tag"><?= icon('lock') ?><?= h(t('internal')) ?></span></h3><p class="pre"><?= h($c['notes']) ?></p><?php endif; ?>
</section>

<?php if (can('conflicts', 'view')): ?>
<section class="card" data-panel="checks">
  <h2><?= h(t('checks_for_contact')) ?></h2>
  <?php if (!$checks): ?><p class="muted"><?= h(t('checks_none_for_contact')) ?></p><?php else: ?>
  <ul class="plain one">
    <?php foreach ($checks as $k): ?><li><a href="/conflicts/<?= (int)$k['id'] ?>" dir="ltr"><?= h($k['ref']) ?></a> — <?= h($k['subject']) ?> <span class="chk chk-<?= h($k['status']) ?>"><?= h(t('chk_' . $k['status'])) ?></span></li><?php endforeach; ?>
  </ul>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if (can('matters', 'view')): ?>
<section class="card" data-panel="matters">
  <h2><?= h(t('contact_matters')) ?></h2>
  <?php if (!$ms): ?><?= empty_state(t('contact_matters_none'), '', null, null, 'folder') ?><?php else: ?>
  <ul class="plain one">
    <?php foreach ($ms as $x): ?><li><a href="/matters/<?= (int)$x['id'] ?>" dir="ltr"><?= h($x['ref']) ?></a> — <?= h($x['title']) ?> <span class="tag"><?= h(t('prole_' . $x['role'])) ?></span> <?= status_badge((int)$x['status_id']) ?></li><?php endforeach; ?>
  </ul>
  <?php endif; ?>
  <p class="muted small"><?= h(t('contact_history_later')) ?></p>
</section>
<?php endif; ?>

<?php if (can('documents', 'view')): [$cdocs] = doc_list(current_staff(), ['contact' => (int)$c['id']], 50); ?>
<section class="card" data-panel="docs">
  <div class="head-row"><h2><?= h(t('contact_docs')) ?></h2>
    <?php if (!$ro && can('documents', 'create')): ?><a class="btn small ghost" href="/documents/upload?contact=<?= (int)$c['id'] ?>"><?= h(t('doc_upload')) ?></a><?php endif; ?></div>
  <?php if (!$cdocs): ?><?= empty_state(t('docs_empty'), '', null, null, 'file') ?><?php endif; ?>
  <ul class="plain one"><?php foreach ($cdocs as $x): ?><li><a href="/documents/<?= (int)$x['id'] ?>"><?= h($x['title']) ?></a></li><?php endforeach; ?></ul>
</section>
<?php endif; ?>

<?php $pu = portal_user_for_contact((int)$c['id']); ?>
<section class="card" id="portal" data-panel="portal">
  <h2><?= h(t('portal_name')) ?></h2>
  <?php if ($pu): ?><p><?= h(t('portal_account_status', t('pst_' . $pu['status']))) ?> · <span dir="ltr"><?= h($pu['email']) ?></span><?= $pu['last_login'] ? ' · ' . h(t('last_login')) . ' <span dir="ltr">' . h(fmt_dt($pu['last_login'])) . '</span>' : '' ?></p>
  <?php else: ?><p class="muted"><?= h(t('portal_no_account')) ?></p><?php endif; ?>
  <p class="muted small"><?= h(t('portal_staff_help')) ?></p>
  <?php if (!$ro && can('clients', 'edit') && $c['is_client']): ?>
    <div class="row">
      <form method="post" action="/clients/<?= (int)$c['id'] ?>/portal/invite"><?= csrf_field() ?><button class="btn small"<?= $c['email'] ? '' : ' disabled' ?>><?= h(t($pu && $pu['status'] === 'active' ? 'portal_resend' : 'portal_invite')) ?></button></form>
      <?php if ($pu && $pu['status'] === 'active'): ?><form method="post" action="/clients/<?= (int)$c['id'] ?>/portal/disable" data-confirm="<?= h(t('portal_confirm_disable')) ?>"><?= csrf_field() ?><button class="btn small ghost"><?= h(t('portal_disable')) ?></button></form><?php endif; ?>
    </div>
    <?php if (!$c['email']): ?><p class="muted small"><?= h(t('portal_needs_email')) ?></p><?php endif; ?>
  <?php endif; ?>
</section>

<?php if (!$ro && can('clients', 'delete')): ?>
<section class="card" data-panel="details">
  <?php if (!$c['archived']): ?>
    <form method="post" action="/clients/<?= (int)$c['id'] ?>/archive" data-confirm="<?= h(t('confirm_archive')) ?>"><?= csrf_field() ?><button class="btn danger"><?= h(t('archive')) ?></button></form>
    <p class="muted small"><?= h(t('archive_help')) ?></p>
  <?php else: ?>
    <form method="post" action="/clients/<?= (int)$c['id'] ?>/restore"><?= csrf_field() ?><button class="btn ghost"><?= h(t('restore')) ?></button></form>
  <?php endif; ?>
</section>
<?php endif; ?>
