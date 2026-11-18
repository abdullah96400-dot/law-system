<?php $ro = office_readonly(); $o = current_office(); $c = contact_get((int)$n['client_id']); $m = orow('SELECT id, ref, title, official_no FROM matters WHERE office_id = :office AND id = :id', ['id' => $n['matter_id']]);
$pu = portal_user_for_contact((int)$n['client_id']); $portalOk = $pu && $pu['status'] === 'active'; $mail = $pu['email'] ?? ($c['email'] ?? ''); ?>
<p class="muted small"><a href="/finance/notices"><?= h(t('notices')) ?></a> · <a href="/matters/<?= (int)$m['id'] ?>#finance" dir="ltr"><?= h($m['ref']) ?></a></p>
<div class="grid2 wide-first">
<section class="card notice-paper" aria-label="<?= h(t('notice_preview')) ?>">
  <div class="np-head"><?php if (office_logo_file()): ?><img src="/office/logo.jpg" alt="" class="np-logo"><?php endif; ?><b><?= h(office_name($o)) ?></b></div>
  <h1><?= h(t('notice_title')) ?></h1>
  <div class="np-amount"><span><?= h(t('notice_amount')) ?></span><b><?= h(money((int)$n['amount'])) ?></b></div>
  <dl class="facts">
    <dt><?= h(t('ct_client')) ?></dt><dd><?= h(contact_label($c)) ?></dd>
    <dt><?= h(t('notice_due_on')) ?></dt><dd dir="ltr"><?= $n['due_on'] ? h(date('d/m/Y', strtotime($n['due_on']))) : '—' ?></dd>
    <dt><?= h(t('appt_matter')) ?></dt><dd><span dir="ltr"><?= h($m['ref']) ?></span><?= $m['official_no'] ? ' · <span dir="ltr">' . h($m['official_no']) . '</span>' : '' ?></dd>
  </dl>
  <p class="small"><?= h(t('notice_text', office_name($o))) ?></p>
</section>
<aside>
  <section class="card">
    <h2><?= h(t('status')) ?>: <span class="nst nst-<?= h($n['status']) ?>"><?= h(t('nst_' . $n['status'])) ?></span></h2>
    <?php if ($n['auto']): ?><p class="muted small"><?= h(t('notice_auto_help')) ?></p><?php endif; ?>
    <?php if ($n['status'] === 'sent'): ?>
      <dl class="facts"><dt><?= h(t('notice_channel')) ?></dt><dd><?= h(t('nch_' . $n['channel'])) ?></dd><dt><?= h(t('notice_delivery')) ?></dt><dd><?= h(t('ndl_' . $n['delivery'])) ?></dd>
        <dt><?= h(t('notice_sent_by')) ?></dt><dd><?= h(staff_name((int)$n['sent_by'])) ?> · <span dir="ltr"><?= h(fmt_dt($n['sent_at'])) ?></span></dd></dl>
    <?php elseif ($n['status'] === 'draft' && !$ro && can('finance', 'create')): ?>
      <form method="post" action="/finance/notices/<?= (int)$n['id'] ?>/send"><?= csrf_field() ?>
        <fieldset><legend><?= h(t('notice_how')) ?></legend>
          <label class="radio"><input type="radio" name="channel" value="email" required<?= $mail ? '' : ' disabled' ?>> <?= h(t('nch_email')) ?> <span class="muted small"><?= $mail ? '(' . h(t('notice_email_generic')) . ')' : '— ' . h(t('notice_no_email')) ?></span></label>
          <label class="radio"><input type="radio" name="channel" value="portal"<?= $portalOk ? '' : ' disabled' ?>> <?= h(t('nch_portal')) ?><?= $portalOk ? '' : ' <span class="muted small">— ' . h(t('notice_no_portal')) . '</span>' ?></label>
          <label class="radio"><input type="radio" name="channel" value="manual"> <?= h(t('nch_manual')) ?></label>
        </fieldset>
        <label for="note"><?= h(t('notice_manual_note')) ?></label><input id="note" name="note" maxlength="300" placeholder="<?= h(t('notice_manual_ph')) ?>">
        <p class="muted small"><?= h(t('notice_no_whatsapp')) ?></p>
        <button class="btn"><?= h(t('notice_send')) ?></button>
      </form>
      <form method="post" action="/finance/notices/<?= (int)$n['id'] ?>/discard" class="mt" data-confirm="<?= h(t('notice_discard_confirm')) ?>"><?= csrf_field() ?><button class="link small danger"><?= h(t('notice_discard')) ?></button></form>
    <?php endif; ?>
    <?php if (can('finance', 'download')): ?><p class="mt"><a class="btn small ghost" href="/finance/notices/<?= (int)$n['id'] ?>/pdf"><?= icon('file') ?><?= h(t('notice_pdf')) ?></a></p><?php endif; ?>
  </section>
</aside>
</div>
