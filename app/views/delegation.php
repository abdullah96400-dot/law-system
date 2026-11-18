<?php $me = current_staff(); $ro = office_readonly(); $asker = deleg_is_asker($d, $me); $delegate = deleg_is_delegate($d, $me);
$m = matter_for((int)$d['matter_id'], $me); $a = $d['appointment_id'] ? orow('SELECT * FROM appointments WHERE office_id = :office AND id = :id', ['id' => $d['appointment_id']]) : null;
$st = $d['status']; $steps = ['draft', 'pending', 'accepted', 'done']; $si = array_search($st, $steps, true); ?>
<div class="page-head">
  <div>
    <h1><?= h(t('deleg_one')) ?> — <span class="ref" dir="ltr"><?= h($m['ref']) ?></span></h1>
    <p><?= h(t('deleg_from_to', staff_name((int)$d['from_staff']), staff_name((int)$d['to_staff']))) ?></p>
    <div class="row mt"><span class="dst dst-<?= h($st) ?>"><?= h(t('dst_' . $st)) ?></span><?php if ($d['memo_document_id']): ?><a class="tag" href="/documents/<?= (int)$d['memo_document_id'] ?>"><?= icon('file') ?><?= h(t('deleg_memo')) ?></a><?php endif; ?></div>
  </div>
  <div class="row"><a class="btn ghost small" href="/matters/<?= (int)$m['id'] ?>#delegations"><?= h(t('deleg_open_file')) ?></a></div>
</div>
<?php if ($si !== false): ?><ol class="flow-steps" aria-label="<?= h(t('status')) ?>"><?php foreach ($steps as $i => $s): ?><li class="<?= $i < $si ? 'past' : ($i === $si ? 'now' : '') ?>"<?= $i === $si ? ' aria-current="step"' : '' ?>><?= h(t('dst_' . $s)) ?></li><?php endforeach; ?></ol><?php endif; ?>
<div class="grid2">
<section class="card">
  <h2><?= h(t('deleg_request')) ?></h2>
  <dl class="facts">
    <dt><?= h(t('appt_matter')) ?></dt><dd><a href="/matters/<?= (int)$m['id'] ?>" dir="ltr"><?= h($m['ref']) ?></a> — <?= h($m['title']) ?></dd>
    <?php if ($a): ?>
      <dt><?= h(t('deleg_hearing')) ?></dt><dd><a href="/calendar/<?= (int)$a['id'] ?>"><?= h($a['title']) ?></a> · <span dir="ltr"><?= h(date('d/m/Y H:i', strtotime($a['starts_at']))) ?></span></dd>
      <dt><?= h(t('matter_court')) ?></dt><dd><?= h(lookup_name($a['court_id'] ? (int)$a['court_id'] : null, 'court')) ?><?= $a['circuit'] ? ' · ' . h($a['circuit']) : '' ?></dd>
    <?php endif; ?>
    <dt><?= h(t('deleg_from')) ?></dt><dd><?= h(staff_name((int)$d['from_staff'])) ?> · <span dir="ltr"><?= h(fmt_dt($d['created_at'])) ?></span></dd>
    <dt><?= h(t('deleg_to')) ?></dt><dd><?= h(staff_name((int)$d['to_staff'])) ?><?= $d['responded_at'] ? ' · <span dir="ltr">' . h(fmt_dt($d['responded_at'])) . '</span>' : '' ?></dd>
  </dl>
  <h3 class="mt"><?= h(t('deleg_instructions')) ?></h3><p class="pre"><?= h($d['instructions']) ?></p>
  <?php if ($d['response_note']): ?><h3 class="mt"><?= h(t($st === 'rejected' ? 'deleg_reason' : 'deleg_reply')) ?></h3><p class="pre"><?= h($d['response_note']) ?></p><?php endif; ?>
  <?php if ($d['result_note']): ?><h3 class="mt"><?= h(t('deleg_result')) ?></h3><p class="pre"><?= h($d['result_note']) ?></p><p class="muted small" dir="ltr"><?= h(fmt_dt($d['done_at'])) ?></p><?php endif; ?>
</section>
<section class="card">
  <h2><?= h(t('deleg_next')) ?></h2>
  <?php if ($ro): ?><p class="muted"><?= h(t('office_readonly')) ?></p>
  <?php elseif ($delegate && $st === 'pending'): ?>
    <form method="post" action="/delegations/<?= (int)$d['id'] ?>/accept"><?= csrf_field() ?>
      <label for="anote"><?= h(t('deleg_reply_opt')) ?></label><input id="anote" name="note" maxlength="600">
      <button class="btn"><?= h(t('deleg_accept')) ?></button></form>
    <details class="mt"><summary><?= h(t('deleg_reject')) ?></summary>
      <form method="post" action="/delegations/<?= (int)$d['id'] ?>/reject"><?= csrf_field() ?>
        <label for="rnote"><?= h(t('deleg_reason')) ?> <?= req_mark() ?></label><input id="rnote" name="note" maxlength="600" required>
        <button class="btn ghost"><?= h(t('deleg_reject')) ?></button></form></details>
  <?php elseif ($delegate && $st === 'accepted'): ?>
    <form method="post" action="/delegations/<?= (int)$d['id'] ?>/done"><?= csrf_field() ?>
      <label for="result"><?= h(t('deleg_result')) ?> <?= req_mark() ?></label><textarea id="result" name="result" rows="4" required placeholder="<?= h(t('deleg_result_ph')) ?>"></textarea>
      <button class="btn"><?= h(t('deleg_complete')) ?></button></form>
    <?php if ($a && $a['status'] === 'scheduled' && can('calendar', 'edit')): ?><p class="muted small mt"><?= h(t('deleg_outcome_hint')) ?> <a href="/calendar/<?= (int)$a['id'] ?>#outcome"><?= h(t('appt_outcome')) ?></a></p><?php endif; ?>
  <?php elseif ($asker && $st === 'draft'): ?>
    <form method="post" action="/delegations/<?= (int)$d['id'] ?>/send"><?= csrf_field() ?><p class="muted small"><?= h(t('deleg_send_help')) ?></p><button class="btn"><?= h(t('deleg_send')) ?></button></form>
  <?php elseif ($st === 'pending'): ?><p class="muted"><?= h(t('deleg_waiting', staff_name((int)$d['to_staff']))) ?></p>
  <?php elseif ($st === 'done' && $a && $a['status'] === 'scheduled' && can('calendar', 'edit')): ?><p><?= h(t('deleg_done_outcome')) ?> <a class="btn small" href="/calendar/<?= (int)$a['id'] ?>#outcome"><?= h(t('appt_outcome')) ?></a></p>
  <?php else: ?><p class="muted"><?= h(t('deleg_nothing_to_do')) ?></p><?php endif; ?>

  <?php if (!$ro && in_array($st, ['accepted', 'done'], true) && ($asker || $delegate) && can('documents', 'create')): ?>
    <form method="post" action="/delegations/<?= (int)$d['id'] ?>/memo" class="mt"><?= csrf_field() ?>
      <p class="muted small"><?= h(t($d['memo_document_id'] ? 'deleg_memo_again' : 'deleg_memo_help')) ?></p><button class="btn ghost small"><?= icon('file') ?><?= h(t('deleg_memo_make')) ?></button></form>
  <?php endif; ?>
  <?php if (!$ro && $asker && in_array($st, ['pending', 'rejected', 'accepted'], true)): ?>
    <details class="mt"><summary><?= h(t('deleg_reassign')) ?></summary>
      <form method="post" action="/delegations/<?= (int)$d['id'] ?>/reassign"><?= csrf_field() ?>
        <label for="to_staff"><?= h(t('deleg_to')) ?></label><select id="to_staff" name="to_staff" required><?php foreach (active_staff() as $s): if (in_array((int)$s['id'], [(int)$d['to_staff'], (int)$d['from_staff']], true)) continue; ?><option value="<?= (int)$s['id'] ?>"><?= h($s['full_name']) ?></option><?php endforeach; ?></select>
        <label for="instructions"><?= h(t('deleg_instructions_new')) ?></label><textarea id="instructions" name="instructions" rows="2"></textarea>
        <button class="btn small"><?= h(t('deleg_reassign')) ?></button></form></details>
  <?php endif; ?>
  <?php if (!$ro && $asker && in_array($st, ['draft', 'pending', 'accepted', 'rejected'], true)): ?>
    <form method="post" action="/delegations/<?= (int)$d['id'] ?>/cancel" class="mt" data-confirm="<?= h(t('deleg_cancel_confirm')) ?>"><?= csrf_field() ?><button class="link small danger"><?= h(t('deleg_cancel')) ?></button></form>
  <?php endif; ?>
</section>
</div>
