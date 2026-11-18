<?php $me = current_staff(); $ro = office_readonly(); $edit = !$ro && can('calendar', 'edit'); $m = $a['matter_id'] ? matter_for((int)$a['matter_id'], $me) : null;
$past = strtotime($a['starts_at']) < time(); ?>
<section class="card">
  <div class="head-row">
    <div>
      <h1><?= h($a['title']) ?></h1>
      <p class="muted"><span class="tag"><?= h(t('ak_' . $a['kind'])) ?></span> <span dir="ltr"><?= h(date('d/m/Y H:i', strtotime($a['starts_at']))) ?></span> · <?= h(t('dow_' . date('w', strtotime($a['starts_at'])))) ?></p>
    </div>
    <div class="row"><span class="chk big <?= $a['status'] === 'scheduled' ? 'chk-pending' : 'chk-closed' ?>"><?= h(t('as_' . $a['status'])) ?></span>
      <?php if ($edit && $a['status'] === 'scheduled'): ?><a class="btn small ghost" href="/calendar/<?= (int)$a['id'] ?>/edit"><?= h(t('edit')) ?></a><?php endif; ?><?php if (!$ro && can('calendar', 'delete')): ?><form method="post" action="/trash/move" class="inline" data-confirm="<?= h(t('trash_confirm')) ?>"><?= csrf_field() ?><input type="hidden" name="kind" value="appointment"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="link small danger"><?= icon('trash') ?> <?= h(t('trash_delete')) ?></button></form><?php endif; ?></div>
  </div>
  <?php if ($err): ?><div class="flash err" role="alert"><?= h(t($err)) ?></div><?php endif; ?>
  <dl class="facts">
    <?php if ($m): ?><dt><?= h(t('appt_matter')) ?></dt><dd><a href="/matters/<?= (int)$m['id'] ?>" dir="ltr"><?= h($m['ref']) ?></a> — <?= h($m['title']) ?></dd><?php endif; ?>
    <dt><?= h(t('matter_court')) ?></dt><dd><?= h(lookup_name($a['court_id'] ? (int)$a['court_id'] : null, 'court')) ?><?= $a['circuit'] ? ' · ' . h($a['circuit']) : '' ?></dd>
    <?php if ($a['location']): ?><dt><?= h(t('appt_location')) ?></dt><dd><?= h($a['location']) ?></dd><?php endif; ?>
    <dt><?= h(t('appt_lead')) ?></dt><dd><?= h(staff_name((int)$a['lead_id'])) ?></dd>
    <dt><?= h(t('appt_source')) ?></dt><dd><?= h(t('src_' . $a['source'])) ?><?= $a['source_note'] ? ' — ' . h($a['source_note']) : '' ?></dd>
    <dt><?= h(t('appt_entered_by')) ?></dt><dd><?= h(staff_name((int)$a['entered_by'])) ?> · <span dir="ltr"><?= h(fmt_dt($a['created_at'])) ?></span></dd>
    <dt><?= h(t('appt_reviewed_by')) ?></dt><dd><?php if ($a['reviewed_by']): ?><?= h(staff_name((int)$a['reviewed_by'])) ?> · <span dir="ltr"><?= h(fmt_dt($a['reviewed_at'])) ?></span><?php else: ?><span class="tag late"><?= h(t('appt_unreviewed')) ?></span><?php endif; ?></dd>
    <dt><?= h(t('appt_remind')) ?></dt><dd dir="ltr"><?= h($a['remind_days'] ?: '—') ?></dd>
  </dl>
  <?php if ($edit && !$a['reviewed_by']): ?>
    <form method="post" action="/calendar/<?= (int)$a['id'] ?>/review" class="mt"><?= csrf_field() ?>
      <p class="small muted"><?= h(t('appt_review_help')) ?></p>
      <button class="btn small"><?= h(t('appt_review_btn')) ?></button></form>
  <?php endif; ?>
  <?php if ($edit && $a['matter_id'] && in_array($a['kind'], ['hearing', 'meeting', 'deadline'], true)): ?>
    <form method="post" action="/calendar/<?= (int)$a['id'] ?>/portal" class="mt"><?= csrf_field() ?><input type="hidden" name="on" value="<?= $a['portal_visible'] ? '' : '1' ?>">
      <span class="small"><?= h(t($a['portal_visible'] ? 'appt_in_portal' : 'appt_not_in_portal')) ?></span> <button class="link small"><?= h(t($a['portal_visible'] ? 'portal_stop_sharing' : 'appt_show_in_portal')) ?></button></form>
  <?php endif; ?>
  <p class="note small mt"><?= h(t('calendar_disclaimer')) ?></p>
</section>

<?php if ($m && feature_on('delegations')): $dls = deleg_for_appt((int)$a['id']); ?>
<section class="card" id="delegate">
  <h2><?= h(t('deleg_for_hearing')) ?></h2>
  <?php foreach ($dls as $x): ?><p><a href="/delegations/<?= (int)$x['id'] ?>"><?= h(t('deleg_from_to', staff_name((int)$x['from_staff']), staff_name((int)$x['to_staff']))) ?></a> <span class="dst dst-<?= h($x['status']) ?>"><?= h(t('dst_' . $x['status'])) ?></span></p><?php endforeach; ?>
  <?php if (!$dls && $a['status'] === 'scheduled' && !$ro && can('calendar', 'create')): ?>
    <form method="post" action="/matters/<?= (int)$m['id'] ?>/delegations" class="form-grid"><?= csrf_field() ?>
      <input type="hidden" name="appointment_id" value="<?= (int)$a['id'] ?>"><input type="hidden" name="back" value="appt">
      <div><label for="dl_to"><?= h(t('deleg_to')) ?></label><select id="dl_to" name="to_staff" required><option value=""><?= h(t('choose')) ?></option><?php foreach (active_staff() as $sx): if ((int)$sx['id'] === (int)$me['id']) continue; ?><option value="<?= (int)$sx['id'] ?>"><?= h($sx['full_name']) ?></option><?php endforeach; ?></select></div>
      <div class="full"><label for="dl_instr"><?= h(t('deleg_instructions')) ?></label><textarea id="dl_instr" name="instructions" rows="2" required placeholder="<?= h(t('deleg_instr_ph')) ?>"></textarea></div>
      <div class="full row"><button class="btn small" name="send" value="1"><?= h(t('deleg_send')) ?></button><button class="btn small ghost"><?= h(t('deleg_save_draft')) ?></button></div>
    </form>
  <?php elseif (!$dls): ?><p class="muted small"><?= h(t('deleg_none')) ?></p><?php endif; ?>
</section>
<?php endif; ?>

<section class="card" id="outcome">
  <h2><?= h(t('appt_outcome')) ?></h2>
  <?php if ($a['status'] !== 'scheduled'): ?>
    <p><b><?= h(t('as_' . $a['status'])) ?></b> — <?= h(staff_name($a['outcome_by'] ? (int)$a['outcome_by'] : null)) ?> · <span dir="ltr"><?= h(fmt_dt($a['outcome_at'])) ?></span></p>
    <?php if ($a['outcome']): ?><p class="pre"><?= h($a['outcome']) ?></p><?php endif; ?>
    <?php if ($a['next_step']): ?><p><b><?= h(t('matter_next')) ?>:</b> <?= h($a['next_step']) ?></p><?php endif; ?>
  <?php elseif (!$edit): ?><p class="muted"><?= h(t('appt_outcome_none')) ?></p>
  <?php else: ?>
    <?php if (!$past): ?><p class="muted small"><?= h(t('appt_outcome_future')) ?></p><?php endif; ?>
    <form method="post" action="/calendar/<?= (int)$a['id'] ?>/outcome" class="form-grid" enctype="multipart/form-data"><?= csrf_field() ?>
      <fieldset class="full"><legend><?= h(t('appt_what_happened')) ?></legend>
        <?php foreach (['done', 'postponed', 'cancelled'] as $s): ?><label class="radio"><input type="radio" name="status" value="<?= $s ?>" required> <?= h(t('as_' . $s)) ?></label><?php endforeach; ?>
      </fieldset>
      <div class="full"><label for="outcome"><?= h(t('appt_outcome_text')) ?></label><textarea id="outcome" name="outcome" rows="4"></textarea></div>
      <div><label for="new_date"><?= h(t('appt_new_date')) ?></label><input id="new_date" name="new_date" type="date" dir="ltr"></div>
      <div><label for="new_time"><?= h(t('time')) ?></label><input id="new_time" name="new_time" type="time" dir="ltr"></div>
      <p class="full muted small"><?= h(t('appt_new_date_help')) ?></p>
      <div class="full"><label for="next_step"><?= h(t('matter_next')) ?></label><input id="next_step" name="next_step" maxlength="300"></div>
      <?php if ($m): ?><div class="full"><label class="radio"><input type="checkbox" name="set_matter_next" value="1" checked> <?= h(t('appt_set_matter_next')) ?></label></div><?php endif; ?>
      <?php if ($m && can('tasks', 'create')): ?>
      <fieldset class="full"><legend><?= h(t('appt_followup')) ?></legend>
        <p class="muted small"><?= h(t('appt_followup_help')) ?></p>
        <div class="form-grid">
          <div class="full"><label for="task_title"><?= h(t('task_title')) ?></label><input id="task_title" name="task_title" maxlength="200"></div>
          <div><label for="task_assignee"><?= h(t('task_assignee')) ?></label><select id="task_assignee" name="task_assignee"><?php foreach (active_staff() as $sx): ?><option value="<?= (int)$sx['id'] ?>"<?= (int)$sx['id'] === (int)$a['lead_id'] ? ' selected' : '' ?>><?= h($sx['full_name']) ?></option><?php endforeach; ?></select></div>
          <div><label for="task_due"><?= h(t('task_due')) ?></label><input id="task_due" name="task_due" type="date" dir="ltr"></div>
        </div>
      </fieldset>
      <?php endif; ?>
      <?php if ($m && can('documents', 'create') && files_key()): ?>
      <fieldset class="full"><legend><?= h(t('appt_attach')) ?></legend>
        <div class="form-grid">
          <div><label for="file"><?= h(t('doc_file')) ?></label><input id="file" name="file" type="file"></div>
          <div><label for="doc_title"><?= h(t('doc_title')) ?></label><input id="doc_title" name="doc_title" maxlength="200" placeholder="<?= h(t('appt_attach_ph')) ?>"></div>
        </div>
      </fieldset>
      <?php endif; ?>
      <div class="full"><button class="btn"><?= h(t('appt_save_outcome')) ?></button></div>
    </form>
  <?php endif; ?>
</section>
