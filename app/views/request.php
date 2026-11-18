<?php $me = current_staff(); $edit = can('website', 'edit', $me) && !office_readonly(); $conv = can('website', 'create', $me) && !office_readonly();
$contact = $r['contact_id'] ? contact_get((int)$r['contact_id']) : null; $check = $r['check_id'] ? check_get((int)$r['check_id']) : null;
$cleared = $check && in_array($check['status'], ['cleared', 'cleared_cond'], true); ?>
<section class="card">
  <p class="small"><a href="/requests"><?= h(t('sr_title')) ?></a></p>
  <div class="head-row"><h1 dir="ltr"><?= h($r['ref']) ?></h1><span class="ist sr-<?= h($r['status']) ?>"><?= h(t('sr_st_' . $r['status'])) ?></span></div>
  <dl class="facts">
    <dt><?= h(t('sr_service')) ?></dt><dd><?= h($r['service_title']) ?></dd>
    <dt><?= h(t('sr_name')) ?></dt><dd><?= h($r['name']) ?></dd>
    <dt><?= h(t('sr_phone')) ?></dt><dd><a href="tel:<?= h(preg_replace('/[^\d+]/', '', $r['phone'])) ?>" dir="ltr"><?= h($r['phone']) ?></a></dd>
    <dt><?= h(t('sr_email')) ?></dt><dd><?= $r['email'] ? '<a href="mailto:' . h($r['email']) . '" dir="ltr">' . h($r['email']) . '</a>' : '—' ?></dd>
    <dt><?= h(t('sr_lang')) ?></dt><dd><?= h($r['lang'] === 'en' ? 'English' : 'العربية') ?></dd>
    <dt><?= h(t('sr_received')) ?></dt><dd><span dir="ltr"><?= h(fmt_dt($r['created_at'])) ?></span></dd>
    <dt><?= h(t('sr_assignee')) ?></dt><dd><?= h($r['assignee_id'] ? staff_name((int)$r['assignee_id']) : t('sr_unassigned')) ?></dd>
  </dl>
  <h2 class="mt"><?= h(t('sr_message')) ?></h2>
  <p class="pre"><?= $r['message'] ? nl2br(h($r['message'])) : '<span class="muted">—</span>' ?></p>
  <p class="note small"><?= h(t('sr_docs_note')) ?></p>
</section>

<?php if ($edit): ?>
<section class="card">
  <h2><?= h(t('sr_handle')) ?></h2>
  <div class="form-grid">
    <form method="post" action="/requests/<?= (int)$r['id'] ?>/status"><?= csrf_field() ?><label for="status"><?= h(t('status')) ?></label>
      <div class="row"><select id="status" name="status"><?php foreach (SITE_STATUSES as $st): ?><option value="<?= $st ?>"<?= $r['status'] === $st ? ' selected' : '' ?>><?= h(t('sr_st_' . $st)) ?></option><?php endforeach; ?></select><button class="btn small"><?= h(t('save')) ?></button></div></form>
    <form method="post" action="/requests/<?= (int)$r['id'] ?>/assign"><?= csrf_field() ?><label for="assignee"><?= h(t('sr_assignee')) ?></label>
      <div class="row"><select id="assignee" name="assignee"><option value="0"><?= h(t('sr_unassigned')) ?></option><?php foreach ($staff as $s): ?><option value="<?= (int)$s['id'] ?>"<?= (int)$r['assignee_id'] === (int)$s['id'] ? ' selected' : '' ?>><?= h($s['full_name']) ?></option><?php endforeach; ?></select><button class="btn small"><?= h(t('save')) ?></button></div></form>
  </div>
</section>
<?php endif; ?>

<?php if ($conv): ?>
<section class="card">
  <h2><?= h(t('sr_convert')) ?></h2>
  <p class="muted small"><?= h(t('sr_convert_intro')) ?></p>
  <?php if ($r['matter_id']): ?>
    <p><?= h(t('sr_converted')) ?> <a href="/matters/<?= (int)$r['matter_id'] ?>"><?= h(t('sr_open_file')) ?></a></p>
  <?php else: ?>
  <ol class="steps">
    <li><b><?= h(t('sr_step_client')) ?></b>
      <?php if ($contact): ?><p><?= h(t('sr_linked')) ?> <a href="/clients/<?= (int)$contact['id'] ?>"><?= h(contact_label($contact)) ?></a></p><?php endif; ?>
      <?php if ($dups): ?>
        <div class="flash warn" role="alert"><span><?= h(t('sr_dups')) ?></span></div>
        <ul class="plain one"><?php foreach ($dups as [$dc, $why]): ?><li><?= h(contact_label($dc)) ?> <span class="muted small">(<?= h(t($why)) ?>)</span>
          <form method="post" action="/requests/<?= (int)$r['id'] ?>/contact" class="inline"><?= csrf_field() ?><input type="hidden" name="mode" value="existing"><input type="hidden" name="contact_id" value="<?= (int)$dc['id'] ?>"><button class="btn small"><?= h(t('sr_use_this')) ?></button></form></li><?php endforeach; ?></ul>
        <form method="post" action="/requests/<?= (int)$r['id'] ?>/contact"><?= csrf_field() ?><input type="hidden" name="mode" value="new"><input type="hidden" name="confirm" value="1"><button class="btn ghost small"><?= h(t('sr_create_anyway')) ?></button></form>
      <?php elseif (!$contact): ?>
        <form method="post" action="/requests/<?= (int)$r['id'] ?>/contact" class="filters"><?= csrf_field() ?><input type="hidden" name="mode" value="existing">
          <div class="grow"><label for="contact_id"><?= h(t('sr_pick_client')) ?></label><select id="contact_id" name="contact_id" required><option value=""><?= h(t('choose')) ?></option>
            <?php foreach (client_options() as $co): ?><option value="<?= (int)$co['id'] ?>"><?= h(contact_label($co)) ?></option><?php endforeach; ?></select></div>
          <div><button class="btn small"><?= h(t('sr_link')) ?></button></div></form>
        <form method="post" action="/requests/<?= (int)$r['id'] ?>/contact"><?= csrf_field() ?><input type="hidden" name="mode" value="new"><button class="btn ghost small"><?= h(t('sr_create_contact')) ?></button></form>
      <?php endif; ?></li>
    <li><b><?= h(t('sr_step_check')) ?></b>
      <?php if ($check): ?><p><a href="/conflicts/<?= (int)$check['id'] ?>" dir="ltr"><?= h($check['ref']) ?></a> · <?= h(t('chk_' . $check['status'])) ?></p>
      <?php elseif ($contact): ?><form method="post" action="/requests/<?= (int)$r['id'] ?>/check"><?= csrf_field() ?><button class="btn small"><?= h(t('sr_start_check')) ?></button></form>
      <?php else: ?><p class="muted small"><?= h(t('sr_after_client')) ?></p><?php endif; ?></li>
    <li><b><?= h(t('sr_step_file')) ?></b>
      <?php if ($cleared && $contact && can('matters', 'create', $me)): ?><div class="row">
        <a class="btn small" href="/matters/new?req=<?= (int)$r['id'] ?>&amp;as=consult"><?= h(t('sr_as_consult')) ?></a>
        <a class="btn ghost small" href="/matters/new?req=<?= (int)$r['id'] ?>"><?= h(t('sr_as_case')) ?></a></div>
      <?php else: ?><p class="muted small"><?= h(t('sr_after_check')) ?></p><?php endif; ?></li>
  </ol>
  <?php endif; ?>
</section>
<?php endif; ?>

<section class="card">
  <h2><?= h(t('sr_notes')) ?></h2>
  <ul class="plain one small"><?php foreach ($notes as $n): ?><li><span dir="ltr" class="muted"><?= h(fmt_dt($n['created_at'])) ?></span> · <?= h($n['full_name'] ?: '—') ?> ·
    <?php if ($n['kind'] === 'note'): ?><?= nl2br(h($n['body'])) ?>
    <?php elseif ($n['kind'] === 'status'): [$a, $b] = array_pad(explode('>', $n['body']), 2, ''); ?><?= h(t('sr_log_status', t('sr_st_' . $a), t('sr_st_' . $b))) ?>
    <?php elseif ($n['kind'] === 'assign'): ?><?= h(t('sr_log_assign', (int)$n['body'] ? staff_name((int)$n['body']) : t('sr_unassigned'))) ?>
    <?php else: ?><?= h(t('sr_log_' . $n['kind'])) ?><?php endif; ?></li><?php endforeach; ?>
  <?php if (!$notes): ?><li class="muted"><?= h(t('sr_no_notes')) ?></li><?php endif; ?></ul>
  <?php if (!office_readonly() && ($edit || (int)$r['assignee_id'] === (int)$me['id'])): ?>
  <form method="post" action="/requests/<?= (int)$r['id'] ?>/note"><?= csrf_field() ?><label for="body"><?= h(t('sr_add_note')) ?></label><textarea id="body" name="body" rows="3" maxlength="4000" required></textarea><p class="muted small"><?= h(t('sr_notes_internal')) ?></p><button class="btn small"><?= h(t('save')) ?></button></form>
  <?php endif; ?>
</section>
