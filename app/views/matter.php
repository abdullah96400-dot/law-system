<?php
$me = current_staff(); $ro = office_readonly(); $edit = !$ro && can('matters', 'edit'); $legal = can('legal_notes', 'view');
$client = contact_get((int)$m['client_id']); $check = check_get((int)$m['conflict_check_id']);
$parties = matter_parties((int)$m['id']); $team = matter_team((int)$m['id']); $events = matter_events((int)$m['id'], $me);
$late = $m['next_due'] && $m['next_due'] < date('Y-m-d') && !$m['closed_at'];
?>
<?php $cnt = fn(string $sql) => (int)orow($sql, ['m' => $m['id']])['n'];
$nAppt = can('calendar', 'view') ? $cnt("SELECT COUNT(*) n FROM appointments WHERE office_id = :office AND matter_id = :m AND status = 'scheduled'") : 0;
$nTask = $cnt("SELECT COUNT(*) n FROM tasks WHERE office_id = :office AND matter_id = :m AND status IN ('open','in_progress')");
$nAct = $cnt('SELECT COUNT(*) n FROM matter_actions WHERE office_id = :office AND matter_id = :m');
$dOn = feature_on('delegations'); $nDel = $dOn ? $cnt("SELECT COUNT(*) n FROM delegations WHERE office_id = :office AND matter_id = :m AND status IN ('draft','pending','accepted')") : 0;
$nDoc = can('documents', 'view') ? $cnt('SELECT COUNT(*) n FROM documents WHERE office_id = :office AND matter_id = :m AND archived = 0') : 0; ?>
<div class="page-head">
  <div>
    <h1><span class="ref" dir="ltr"><?= h($m['ref']) ?></span> — <?= h($m['title']) ?></h1>
    <p><?= h(contact_label($client)) ?> · <?= h(lookup_name($m['case_type_id'] ? (int)$m['case_type_id'] : null, 'case_type')) ?></p>
    <?php $nh = can('calendar', 'view') ? orow("SELECT id, starts_at, title FROM appointments WHERE office_id = :office AND matter_id = :m AND status = 'scheduled' AND starts_at >= :t ORDER BY starts_at LIMIT 1", ['m' => $m['id'], 't' => date('Y-m-d') . ' 00:00:00']) : null;
      $opp = array_values(array_filter($parties, fn($p) => $p['role'] === 'opponent')); ?>
    <ul class="case-strip">
      <li><span><?= h(t('matter_court')) ?></span><b><?= h(lookup_name($m['court_id'] ? (int)$m['court_id'] : null, 'court')) ?><?= $m['circuit'] ? ' · ' . h($m['circuit']) : '' ?></b></li>
      <li><span><?= h(t('matter_lead')) ?></span><b><?= h(staff_name((int)$m['lead_id'])) ?></b></li>
      <?php if ($opp): ?><li><span><?= h(t('prole_opponent')) ?></span><b><?= h(contact_label($opp[0])) ?><?= count($opp) > 1 ? ' +' . (count($opp) - 1) : '' ?></b></li><?php endif; ?>
      <li><span><?= h(t('next_hearing')) ?></span><b><?php if ($nh): ?><a href="/calendar/<?= (int)$nh['id'] ?>" dir="ltr"><?= h(date('d/m/Y H:i', strtotime($nh['starts_at']))) ?></a><?php else: ?>—<?php endif; ?></b></li>
      <li class="<?= $late ? 'late' : '' ?>"><span><?= h(t('matter_next')) ?></span><b><?= $m['next_step'] ? h(mb_strimwidth($m['next_step'], 0, 60, '…')) . ($m['next_due'] ? ' · <span dir="ltr">' . h(date('d/m', strtotime($m['next_due']))) . '</span>' : '') : '—' ?></b></li>
    </ul>
    <div class="row mt"><?= status_badge((int)$m['status_id']) ?><?php if ($m['restricted']): ?><span class="tag" title="<?= h(t('restricted_note')) ?>"><?= icon('lock') ?><?= h(t('restricted')) ?></span><?php endif; ?><?php if ($late): ?><span class="tag late"><?= h(t('overdue')) ?></span><?php endif; ?></div>
  </div>
  <div class="row">
    <?php if ($edit): ?><a class="btn ghost small" href="/matters/<?= (int)$m['id'] ?>/edit"><?= h(t('edit')) ?></a><?php endif; ?><?php if (!$ro && can('matters', 'delete') && !is_support($me)): ?><form method="post" action="/trash/move" class="inline" data-confirm="<?= h(t('trash_confirm')) ?>"><?= csrf_field() ?><input type="hidden" name="kind" value="matter"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="link small danger"><?= icon('trash') ?> <?= h(t('trash_delete')) ?></button></form><?php endif; ?>
    <?php if (!$ro && can('calendar', 'create')): ?><a class="btn ghost small" href="/calendar/new?matter=<?= (int)$m['id'] ?>"><?= icon('calendar') ?><?= h(t('appt_new')) ?></a><?php endif; ?>
    <?php if (!$ro && can('tasks', 'create')): ?><a class="btn ghost small" href="/tasks/new?matter=<?= (int)$m['id'] ?>"><?= icon('tasks') ?><?= h(t('task_new')) ?></a><?php endif; ?>
    <?php if (!$ro && can('documents', 'create')): ?><a class="btn small" href="/documents/upload?matter=<?= (int)$m['id'] ?>"><?= icon('upload') ?><?= h(t('doc_upload')) ?></a><?php endif; ?>
  </div>
</div>
<div class="ptabs" data-tabs aria-label="<?= h(t('mod_matters')) ?>">
  <button type="button" data-for="overview"><?= h(t('tab_summary')) ?></button>
  <button type="button" data-for="hearings"><?= h(t('tab_hearings')) ?><?= $nAppt ? ' <span class="n">' . $nAppt . '</span>' : '' ?></button>
  <button type="button" data-for="actions"><?= h(t('tab_actions')) ?><?= $nAct ? ' <span class="n">' . $nAct . '</span>' : '' ?></button>
  <button type="button" data-for="tasks"><?= h(t('tab_tasks')) ?><?= $nTask ? ' <span class="n">' . $nTask . '</span>' : '' ?></button>
  <button type="button" data-for="delegations"><?= h(t('tab_delegations')) ?><?= $nDel ? ' <span class="n">' . $nDel . '</span>' : '' ?></button>
  <button type="button" data-for="docs"><?= h(t('tab_docs')) ?><?= $nDoc ? ' <span class="n">' . $nDoc . '</span>' : '' ?></button>
  <button type="button" data-for="timeline"><?= h(t('tab_notes')) ?></button>
  <button type="button" data-for="fees"><?= h(t('tab_fees')) ?></button>
  <button type="button" data-for="portal"><?= h(t('tab_portal')) ?></button>
</div>
<nav class="hubs case-hub" data-panel="overview" aria-label="<?= h(t('mod_matters')) ?>"><?php
  foreach (array_filter([[can('calendar', 'view'), '#hearings', 'calendar', 'petrol', t('tab_hearings'), $nAppt], [true, '#actions', 'gavel', 'gold', t('tab_actions'), $nAct], [true, '#tasks', 'tasks', 'blue', t('tab_tasks'), $nTask],
    [$dOn, '#delegations', 'delegate', 'violet', t('tab_delegations'), $nDel], [can('documents', 'view'), '#docs', 'file', 'green', t('tab_docs'), $nDoc], [can('finance', 'view') && feature_on('finance'), '#fees', 'contract', 'ink', t('tab_fees'), null],
    [true, '#timeline', 'clock', 'ink', t('tab_notes'), null], [!is_support($me), '#portal', 'portal', 'petrol', t('tab_portal'), null]], fn($x) => $x[0]) as [, $hh, $ic, $tn, $lb, $cn]) echo hub_tile($hh, $ic, $tn, $lb, '', $cn ?: null); ?></nav>
<section class="card" data-panel="overview">
  <?php if ($err): ?><div class="flash err" role="alert"><?= h(t($err)) ?></div><?php endif; ?>

  <div class="next <?= $late ? 'late-box' : '' ?>" id="next">
    <b><?= h(t('matter_next')) ?>:</b>
    <?php if ($m['next_step']): ?><?= h($m['next_step']) ?><?php if ($m['next_due']): ?> — <span dir="ltr"><?= h(date('d/m/Y', strtotime($m['next_due']))) ?></span><?= $late ? ' <span class="tag late">' . h(t('overdue')) . '</span>' : '' ?><?php endif; ?>
    <?php else: ?><span class="muted"><?= h(t('matter_next_none')) ?></span><?php endif; ?>
    <?php if ($edit): ?>
      <details><summary><?= h(t('matter_next_set')) ?></summary>
        <form method="post" action="/matters/<?= (int)$m['id'] ?>/next" class="form-grid"><?= csrf_field() ?>
          <div><label for="next_step"><?= h(t('matter_next_step')) ?></label><input id="next_step" name="next_step" maxlength="300" value="<?= h($m['next_step']) ?>"></div>
          <div><label for="next_due"><?= h(t('matter_next_due')) ?></label><input id="next_due" name="next_due" type="date" dir="ltr" value="<?= h($m['next_due']) ?>"></div>
          <div class="full"><button class="btn small"><?= h(t('save')) ?></button></div></form>
      </details>
    <?php endif; ?>
  </div>

  <dl class="facts mt">
    <dt><?= h(t('matter_court')) ?></dt><dd><?= h(lookup_name($m['court_id'] ? (int)$m['court_id'] : null, 'court')) ?></dd>
    <dt><?= h(t('matter_level')) ?></dt><dd><?= h(lookup_name($m['court_level_id'] ? (int)$m['court_level_id'] : null, 'court_level')) ?></dd>
    <dt><?= h(t('matter_circuit')) ?></dt><dd><?= h($m['circuit'] ?: '—') ?></dd>
    <dt><?= h(t('matter_official_no')) ?></dt><dd><span dir="ltr"><?= h($m['official_no'] ?: '—') ?></span></dd>
    <dt><?= h(t('matter_lead')) ?></dt><dd><?= h(staff_name((int)$m['lead_id'])) ?></dd>
    <?php if ($m['branch_id']): ?><dt><?= h(t('branch')) ?></dt><dd><?= h(orow('SELECT name FROM branches WHERE office_id = :office AND id = :id', ['id' => $m['branch_id']])['name'] ?? '—') ?></dd><?php endif; ?>
    <dt><?= h(t('matter_opened_on')) ?></dt><dd><span dir="ltr"><?= h(date('d/m/Y', strtotime($m['opened_on']))) ?></span></dd>
    <?php if ($m['referral']): ?><dt><?= h(t('matter_referral')) ?></dt><dd><?= h($m['referral']) ?></dd><?php endif; ?>
    <dt><?= h(t('matter_check')) ?></dt><dd><?php if (can('conflicts', 'view')): ?><a href="/conflicts/<?= (int)$check['id'] ?>" dir="ltr"><?= h($check['ref']) ?></a><?php else: ?><span dir="ltr"><?= h($check['ref']) ?></span><?php endif; ?> — <?= h(t('chk_' . $check['status'])) ?></dd>
  </dl>
  <?php if ($legal && $m['summary']): ?><h2 class="mt"><?= h(t('matter_summary')) ?> <span class="tag"><?= h(t('internal')) ?></span></h2><p class="pre"><?= h($m['summary']) ?></p><?php endif; ?>
</section>

<?php if ($edit): ?>
<section class="card" id="status" data-panel="overview">
  <h2><?= h(t('matter_change_status')) ?></h2>
  <form method="post" action="/matters/<?= (int)$m['id'] ?>/status" class="form-grid"><?= csrf_field() ?>
    <div><label for="status_id"><?= h(t('status')) ?></label><select id="status_id" name="status_id"><?php foreach (lookups('status') as $l): ?><option value="<?= (int)$l['id'] ?>"<?= (int)$l['id'] === (int)$m['status_id'] ? ' selected' : '' ?>><?= h(lookup_label($l)) ?><?= $l['is_closed'] ? ' — ' . h(t('closes_file')) : '' ?></option><?php endforeach; ?></select></div>
    <div><label for="snote"><?= h(t('matter_status_note')) ?></label><input id="snote" name="note" maxlength="2000"></div>
    <div class="full"><button class="btn small"><?= h(t('save')) ?></button></div>
  </form>
</section>
<?php endif; ?>

<section class="card" id="parties" data-panel="overview">
  <h2><?= h(t('matter_parties')) ?></h2>
  <ul class="plain one">
    <?php foreach ($parties as $p): $main = $p['role'] === 'client' && (int)$p['contact_id'] === (int)$m['client_id']; ?>
      <li class="party">
        <span class="tag"><?= h(t('prole_' . $p['role'])) ?></span>
        <?php if (can('clients', 'view')): ?><a href="/clients/<?= (int)$p['contact_id'] ?>"><?= h(contact_label($p)) ?></a><?php else: ?><?= h(contact_label($p)) ?><?php endif; ?>
        <?= $p['note'] ? '<span class="muted small"> — ' . h($p['note']) . '</span>' : '' ?>
        <?php if ($edit && !$main): ?><form method="post" action="/matters/<?= (int)$m['id'] ?>/parties/<?= (int)$p['id'] ?>/remove" class="inline" data-confirm="<?= h(t('confirm_remove_party')) ?>"><?= csrf_field() ?><button class="link small"><?= h(t('remove')) ?></button></form><?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php if ($edit): ?>
    <details><summary><?= h(t('matter_add_party')) ?></summary>
      <form method="post" action="/matters/<?= (int)$m['id'] ?>/parties" class="form-grid"><?= csrf_field() ?>
        <div><label for="contact_id"><?= h(t('matter_party_who')) ?></label><select id="contact_id" name="contact_id" required><option value=""><?= h(t('choose')) ?></option><?php foreach (client_options() as $c): ?><option value="<?= (int)$c['id'] ?>"><?= h(contact_label($c)) ?></option><?php endforeach; ?></select></div>
        <div><label for="role"><?= h(t('matter_party_role')) ?></label><select id="role" name="role"><?php foreach (PARTY_ROLES as $r): ?><option value="<?= $r ?>"<?= $r === 'opponent' ? ' selected' : '' ?>><?= h(t('prole_' . $r)) ?></option><?php endforeach; ?></select></div>
        <div class="full"><label for="pnote"><?= h(t('matter_party_note')) ?></label><input id="pnote" name="note" maxlength="300"></div>
        <div class="full"><button class="btn small"><?= h(t('add')) ?></button> <a class="small" href="/clients/new?type=party"><?= h(t('matter_party_new_contact')) ?></a></div>
      </form>
      <p class="muted small"><?= h(t('matter_party_check_hint')) ?></p>
    </details>
  <?php endif; ?>
</section>

<section class="card" id="team" data-panel="overview">
  <h2><?= h(t('matter_team')) ?></h2>
  <ul class="plain one">
    <?php foreach ($team as $s): ?>
      <li><?= h($s['full_name']) ?> <span class="muted small">— <?= h(t('role_' . $s['role'])) ?><?= (int)$s['id'] === (int)$m['lead_id'] ? ' · ' . h(t('matter_lead')) : '' ?><?= $s['status'] !== 'active' ? ' · ' . h(t('st_' . $s['status'])) : '' ?></span>
        <?php if ($edit && (int)$s['id'] !== (int)$m['lead_id']): ?><form method="post" action="/matters/<?= (int)$m['id'] ?>/team/<?= (int)$s['id'] ?>/remove" class="inline"><?= csrf_field() ?><button class="link small"><?= h(t('remove')) ?></button></form><?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php if ($edit): ?>
    <form method="post" action="/matters/<?= (int)$m['id'] ?>/team" class="row"><?= csrf_field() ?>
      <label for="staff_id" class="sr"><?= h(t('matter_add_member')) ?></label>
      <select id="staff_id" name="staff_id" class="auto"><?php foreach (active_staff() as $s): ?><option value="<?= (int)$s['id'] ?>"><?= h($s['full_name']) ?></option><?php endforeach; ?></select>
      <button class="btn small"><?= h(t('matter_add_member')) ?></button>
    </form>
  <?php endif; ?>
  <?php if ($m['restricted']): ?><p class="muted small"><?= h(t('matter_restricted_help')) ?></p><?php endif; ?>
</section>

<section class="card" id="timeline" data-panel="timeline">
  <h2><?= h(t('matter_timeline')) ?></h2>
  <?php if (!$ro && can('legal_notes', 'create')): ?>
    <form method="post" action="/matters/<?= (int)$m['id'] ?>/notes"><?= csrf_field() ?>
      <label for="body"><?= h(t('matter_add_note')) ?> <span class="tag"><?= h(t('internal')) ?></span></label>
      <textarea id="body" name="body" rows="3" required></textarea>
      <button class="btn small"><?= h(t('add')) ?></button>
    </form>
  <?php endif; ?>
  <ol class="timeline">
    <?php foreach ($events as $e): $dt = json_decode((string)$e['data'], true) ?: []; ?>
      <li class="ev ev-<?= h($e['kind']) ?>">
        <div class="when"><span dir="ltr"><?= h(fmt_dt($e['at'])) ?></span> · <?= h($e['full_name'] ?? '—') ?></div>
        <div><?php switch ($e['kind']) {
          case 'created': echo h(t('ev_created', $dt['check'] ?? '')); break;
          case 'status': echo h(t('ev_status', lookup_name($dt['from'] ?? null, 'status'), lookup_name($dt['to'] ?? null, 'status'))); break;
          case 'fields': echo h(t('ev_fields', implode(t('list_sep'), array_map(fn($f) => t('mf_' . $f), $dt['fields'] ?? [])))); break;
          case 'next': echo h($e['body'] ? t('ev_next', $e['body']) : t('ev_next_cleared')); break;
          case 'party_add': case 'party_remove': $pc = contact_get((int)($dt['contact'] ?? 0)); echo h(t('ev_' . $e['kind'], $pc ? contact_label($pc) : '—', t('prole_' . ($dt['role'] ?? 'other')))); break;
          case 'team_add': case 'team_remove': echo h(t('ev_' . $e['kind'], staff_name((int)($dt['staff'] ?? 0)))); break;
          case 'note': echo '<span class="tag">' . h(t('internal')) . '</span> '; break;
          case 'appt_add': case 'appt_change': case 'appt_review': case 'appt_outcome': $ap = orow('SELECT id, title, starts_at FROM appointments WHERE office_id = :office AND id = :id', ['id' => (int)($dt['appt'] ?? 0)]);
            echo h(t('ev_' . $e['kind'], $ap['title'] ?? '—', $ap ? date('d/m/Y', strtotime($ap['starts_at'])) : '', isset($dt['status']) ? t('as_' . $dt['status']) : '')); break;
          case 'doc_add': echo h(t(!empty($dt['client']) ? 'ev_doc_add_client' : 'ev_doc_add', $dt['title'] ?? '—')); break;
          case 'update_published': echo h(t('ev_update_published')); break;
          case 'portal_share': $pc = contact_get((int)($dt['contact'] ?? 0)); echo h(t(!empty($dt['on']) ? 'ev_portal_on' : 'ev_portal_off', $pc ? contact_label($pc) : '—')); break;
          case 'invoice_issued': echo h(t('ev_invoice_issued', $dt['number'] ?? '', money((int)($dt['total'] ?? 0)))); break;
          case 'fee_agreement': echo h(t('ev_fee_agreement', t('fk_' . ($dt['kind'] ?? 'other')))); break;
          case 'task_add': case 'task_done': echo h(t('ev_' . $e['kind'], $dt['title'] ?? '—')); break;
          case 'action_add': case 'action_change': case 'action_verify': echo h(t('ev_' . $e['kind'], $dt['title'] ?? '—')); break;
          case 'notice_sent': echo h(t('ev_notice_sent', money((int)($dt['amount'] ?? 0))) . ' · ' . t('nch_' . ($dt['channel'] ?? 'manual'))); break;
          case 'trashed': case 'restored': echo h(t('ev_' . $e['kind'], t('tk2_' . ($dt['kind'] ?? 'note')), $dt['title'] ?? '')); break;
          case 'item_share': echo h(t(!empty($dt['on']) ? 'ev_item_shared' : 'ev_item_unshared', $dt['title'] ?? '—')); break;
          case 'deleg_sent': case 'deleg_accepted': case 'deleg_rejected': case 'deleg_done': case 'deleg_cancelled': case 'deleg_reassigned': echo h(t('ev_' . $e['kind'], staff_name((int)($dt['to'] ?? 0)))); break;
        } ?></div>
        <?php if ($e['kind'] === 'note' && !$ro && (can('legal_notes', 'delete') || $me['role'] === 'partner')): ?><div class="ev-del"><?php if (true): ?><form method="post" action="/trash/move" class="inline" data-confirm="<?= h(t('trash_confirm')) ?>"><?= csrf_field() ?><input type="hidden" name="kind" value="note"><input type="hidden" name="id" value="<?= (int)$e['id'] ?>"><button class="link small danger"><?= icon('trash') ?> <?= h(t('trash_delete')) ?></button></form><?php endif; ?></div><?php endif; ?>
        <?php if ($e['body'] && in_array($e['kind'], ['note', 'status', 'appt_outcome', 'deleg_rejected', 'deleg_done', 'notice_sent'], true)): ?><p class="pre"><?= h($e['body']) ?></p><?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ol>
</section>

<?php if (can('calendar', 'view')): $appts = appt_for_matter((int)$m['id']); ?>
<section class="card" id="hearings" data-panel="hearings">
  <div class="head-row"><h2><?= h(t('matter_hearings')) ?></h2>
    <?php if (!$ro && can('calendar', 'create')): ?><a class="btn small ghost" href="/calendar/new?matter=<?= (int)$m['id'] ?>"><?= h(t('appt_new')) ?></a><?php endif; ?></div>
  <?php if (!$appts): ?><?= empty_state(t('nothing_here'), '', null, null, 'calendar') ?><?php endif; ?>
  <ul class="plain one">
    <?php foreach ($appts as $a): ?><li class="appt"><span class="time" dir="ltr"><?= h(date('d/m/Y H:i', strtotime($a['starts_at']))) ?></span> <a href="/calendar/<?= (int)$a['id'] ?>"><?= h($a['title']) ?></a> <span class="tag"><?= h(t('ak_' . $a['kind'])) ?></span>
      <?php if ($a['status'] !== 'scheduled'): ?><span class="badge-s <?= appt_badge($a) ?>"><?= h(t('as_' . $a['status'])) ?></span><?php elseif (appt_badge($a) === 'b-late'): ?><span class="badge-s b-late"><?= h(t('appt_st_late')) ?></span><?php elseif (!$a['reviewed_by'] && in_array($a['kind'], ['hearing', 'deadline'], true)): ?><span class="tag late"><?= h(t('appt_unreviewed')) ?></span><?php endif; ?></li><?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?php $acts = actions_for((int)$m['id']); $approver = can_approve_update($me, $m); ?>
<section class="card" id="actions" data-panel="actions">
  <div class="head-row"><h2><?= h(t('matter_actions')) ?></h2>
    <?php if ($edit): ?><div class="row"><?php if (can('calendar', 'create')): ?><a class="btn small ghost" href="/calendar/new?matter=<?= (int)$m['id'] ?>&amp;kind=deadline"><?= icon('clock') ?><?= h(t('act_add_deadline')) ?></a><?php endif; ?><a class="btn small" href="#act-new" data-open="act-new"><?= icon('plus') ?><?= h(t('act_new')) ?></a></div><?php endif; ?></div>
  <p class="muted small"><?= h(t('act_help')) ?></p>
  <?php if (!$acts): ?><?= empty_state(t('act_empty'), t('act_empty_help'), null, null, 'gavel') ?><?php endif; ?>
  <ol class="act-list">
    <?php foreach ($acts as $x): ?>
      <li class="act act-<?= h($x['kind']) ?>">
        <div class="act-when" dir="ltr"><?= h(date('d/m/Y', strtotime($x['action_on']))) ?></div>
        <div class="act-body">
          <div class="row"><span class="tag k-<?= h($x['kind']) ?>"><?= h(t('acts_' . $x['kind'])) ?></span><b><?= h($x['title']) ?></b>
            <?= $x['verified_by'] ? '<span class="badge-s b-ok">' . h(t('act_verified')) . '</span>' : '<span class="tag late">' . h(t('act_unverified')) . '</span>' ?>
            <?= $x['portal_visible'] ? '<span class="tag">' . icon('portal') . h(t('act_in_portal')) . '</span>' : '' ?></div>
          <?php if ($x['details']): ?><p class="pre small"><?= h($x['details']) ?></p><?php endif; ?>
          <p class="muted small"><?= h(t('src_' . $x['source'])) ?><?= $x['source_note'] ? ' — ' . h($x['source_note']) : '' ?> · <?= h(t('act_entered', staff_name((int)$x['created_by']))) ?><?= $x['verified_by'] ? ' · ' . h(t('act_checked_by', staff_name((int)$x['verified_by']))) : '' ?></p>
          <?php if ($edit): ?><div class="row small">
            <a class="link" href="/matters/<?= (int)$m['id'] ?>/actions/<?= (int)$x['id'] ?>/edit"><?= h(t('edit')) ?></a><?php if (can('matters', 'delete')): ?><form method="post" action="/trash/move" class="inline" data-confirm="<?= h(t('trash_confirm')) ?>"><?= csrf_field() ?><input type="hidden" name="kind" value="action"><input type="hidden" name="id" value="<?= (int)$x['id'] ?>"><button class="link small danger"><?= icon('trash') ?> <?= h(t('trash_delete')) ?></button></form><?php endif; ?>
            <?php if (!$x['verified_by'] && ((int)$x['created_by'] !== (int)$me['id'] || single_member_office())): ?><form method="post" action="/matters/<?= (int)$m['id'] ?>/actions/<?= (int)$x['id'] ?>/verify" class="inline"><?= csrf_field() ?><button class="link"><?= h(t('act_verify')) ?></button></form>
            <?php elseif (!$x['verified_by']): ?><span class="muted"><?= h(t('act_verify_other_short')) ?></span><?php endif; ?>
            <?php if ($x['verified_by'] && $approver && !is_support($me)): ?><form method="post" action="/matters/<?= (int)$m['id'] ?>/actions/<?= (int)$x['id'] ?>/<?= $x['portal_visible'] ? 'unshare' : 'share' ?>" class="inline"<?= $x['portal_visible'] ? '' : ' data-confirm="' . h(t('act_share_confirm', $x['title'])) . '"' ?>><?= csrf_field() ?><button class="link"><?= h(t($x['portal_visible'] ? 'portal_stop_sharing' : 'act_share')) ?></button></form><?php endif; ?>
            <?php if ($x['kind'] === 'judgment' && can('calendar', 'create')): ?><a class="link" href="/calendar/new?matter=<?= (int)$m['id'] ?>&amp;kind=deadline&amp;title=<?= h(rawurlencode(t('act_deadline_title', $x['title']))) ?>"><?= h(t('act_add_deadline')) ?></a><?php endif; ?>
          </div><?php endif; ?>
        </div>
      </li>
    <?php endforeach; ?>
  </ol>
  <?php if ($edit): ?>
    <details id="act-new"><summary><?= h(t('act_new')) ?></summary>
      <form method="post" action="/matters/<?= (int)$m['id'] ?>/actions" class="form-grid"><?= csrf_field() ?>
        <div><label for="act_kind"><?= h(t('act_kind')) ?></label><select id="act_kind" name="kind" required><?php foreach (ACTION_KINDS as $k): ?><option value="<?= $k ?>"><?= h(t('acts_' . $k)) ?></option><?php endforeach; ?></select></div>
        <div><label for="act_on"><?= h(t('act_date')) ?></label><input id="act_on" name="action_on" type="date" dir="ltr" required value="<?= h(date('Y-m-d')) ?>"></div>
        <div class="full"><label for="act_title"><?= h(t('act_title')) ?></label><input id="act_title" name="title" required maxlength="200" placeholder="<?= h(t('act_title_ph')) ?>"></div>
        <div class="full"><label for="act_details"><?= h(t('act_details')) ?></label><textarea id="act_details" name="details" rows="3"></textarea></div>
        <div><label for="act_source"><?= h(t('appt_source_where')) ?></label><select id="act_source" name="source" required><option value=""><?= h(t('choose')) ?></option><?php foreach (APPT_SOURCES as $sx): ?><option value="<?= $sx ?>"><?= h(t('src_' . $sx)) ?></option><?php endforeach; ?></select></div>
        <div><label for="act_snote"><?= h(t('appt_source_note')) ?></label><input id="act_snote" name="source_note" maxlength="300"></div>
        <input type="hidden" name="court_id" value="<?= (int)$m['court_id'] ?>">
        <p class="full note small"><?= h(t('act_no_calc')) ?></p>
        <div class="full"><button class="btn small"><?= h(t('save')) ?></button></div>
      </form></details>
  <?php endif; ?>
</section>

<?php if ($dOn): $dels = deleg_for_matter((int)$m['id']); $upAppts = orows("SELECT id, title, starts_at FROM appointments WHERE office_id = :office AND matter_id = :m AND status = 'scheduled' AND starts_at >= :t ORDER BY starts_at", ['m' => $m['id'], 't' => date('Y-m-d') . ' 00:00:00']); ?>
<section class="card" id="delegations" data-panel="delegations">
  <div class="head-row"><h2><?= h(t('mod_delegations')) ?></h2><a class="small" href="/delegations?period=all&amp;staff=0"><?= h(t('deleg_all_link')) ?></a></div>
  <?php if (!$dels): ?><?= empty_state(t('deleg_empty_file'), t('deleg_empty_file_help'), null, null, 'delegate') ?><?php endif; ?>
  <ul class="plain one">
    <?php foreach ($dels as $x): $ap = $x['appointment_id'] ? orow('SELECT title, starts_at FROM appointments WHERE office_id = :office AND id = :id', ['id' => $x['appointment_id']]) : null; ?>
      <li><a href="/delegations/<?= (int)$x['id'] ?>"><?= h(t('deleg_from_to', staff_name((int)$x['from_staff']), staff_name((int)$x['to_staff']))) ?></a>
        <span class="dst dst-<?= h($x['status']) ?>"><?= h(t('dst_' . $x['status'])) ?></span>
        <span class="muted small"><?= $ap ? '— ' . h($ap['title']) . ' · <span dir="ltr">' . h(date('d/m/Y H:i', strtotime($ap['starts_at']))) . '</span>' : '' ?></span>
        <?= $x['memo_document_id'] ? ' <a class="tag" href="/documents/' . (int)$x['memo_document_id'] . '">' . h(t('deleg_memo')) . '</a>' : '' ?></li>
    <?php endforeach; ?>
  </ul>
  <?php if (!$ro && can('calendar', 'create')): ?>
    <details<?= !$dels ? ' open' : '' ?>><summary><?= h(t('deleg_new')) ?></summary>
      <form method="post" action="/matters/<?= (int)$m['id'] ?>/delegations" class="form-grid"><?= csrf_field() ?>
        <div><label for="dl_to"><?= h(t('deleg_to')) ?></label><select id="dl_to" name="to_staff" required><option value=""><?= h(t('choose')) ?></option><?php foreach (active_staff() as $sx): if ((int)$sx['id'] === (int)$me['id']) continue; ?><option value="<?= (int)$sx['id'] ?>"><?= h($sx['full_name']) ?></option><?php endforeach; ?></select></div>
        <div><label for="dl_appt"><?= h(t('deleg_hearing')) ?></label><select id="dl_appt" name="appointment_id"><option value=""><?= h(t('deleg_no_hearing')) ?></option><?php foreach ($upAppts as $ax): ?><option value="<?= (int)$ax['id'] ?>"><?= h(date('d/m/Y H:i', strtotime($ax['starts_at'])) . ' — ' . $ax['title']) ?></option><?php endforeach; ?></select></div>
        <div class="full"><label for="dl_instr"><?= h(t('deleg_instructions')) ?></label><textarea id="dl_instr" name="instructions" rows="3" required placeholder="<?= h(t('deleg_instr_ph')) ?>"></textarea></div>
        <?php if ($m['restricted']): ?><p class="full muted small"><?= h(t('deleg_restricted_hint')) ?></p><?php endif; ?>
        <div class="full row"><button class="btn small" name="send" value="1"><?= h(t('deleg_send')) ?></button><button class="btn small ghost"><?= h(t('deleg_save_draft')) ?></button></div>
      </form></details>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php [$mtasks] = task_list($me, ['scope' => 'all', 'state' => 'open', 'matter' => (int)$m['id']]); ?>
<section class="card" id="tasks" data-panel="tasks">
  <div class="head-row"><h2><?= h(t('matter_tasks')) ?></h2>
    <?php if (!$ro && can('tasks', 'create')): ?><a class="btn small ghost" href="/tasks/new?matter=<?= (int)$m['id'] ?>"><?= h(t('task_new')) ?></a><?php endif; ?></div>
  <?php if (!$mtasks): ?><?= empty_state(t('tasks_empty'), '', null, null, 'tasks') ?><?php endif; ?>
  <ul class="plain one">
    <?php foreach ($mtasks as $x): $lt = $x['due_on'] && $x['due_on'] < date('Y-m-d'); ?><li><a href="/tasks/<?= (int)$x['id'] ?>"><?= h($x['title']) ?></a> <span class="muted small">— <?= h(staff_name((int)$x['assignee_id'])) ?><?= $x['due_on'] ? ' · <span dir="ltr" class="' . ($lt ? 'late' : '') . '">' . h(date('d/m/Y', strtotime($x['due_on']))) . '</span>' : '' ?></span></li><?php endforeach; ?>
  </ul>
  <?php if (!$ro && can('tasks', 'create') && ($tpls = tpl_list())): ?>
    <details><summary><?= h(t('tpl_apply')) ?></summary>
      <form method="post" action="/matters/<?= (int)$m['id'] ?>/template" class="form-grid"><?= csrf_field() ?>
        <div><label for="template_id"><?= h(t('tpl_title')) ?></label><select id="template_id" name="template_id"><?php foreach ($tpls as $tp): ?><option value="<?= (int)$tp['id'] ?>"><?= h($tp['name']) ?></option><?php endforeach; ?></select></div>
        <div><label for="tpl_assignee"><?= h(t('task_assignee')) ?></label><select id="tpl_assignee" name="assignee_id"><?php foreach (active_staff() as $s): ?><option value="<?= (int)$s['id'] ?>"<?= (int)$s['id'] === (int)$m['lead_id'] ? ' selected' : '' ?>><?= h($s['full_name']) ?></option><?php endforeach; ?></select></div>
        <div class="full"><button class="btn small"><?= h(t('tpl_apply_btn')) ?></button></div>
      </form></details>
  <?php endif; ?>
</section>

<?php if (can('documents', 'view')): [$mdocs] = doc_list($me, ['matter' => (int)$m['id']], 100); ?>
<section class="card" id="documents" data-panel="docs">
  <div class="head-row"><h2><?= h(t('mod_documents')) ?></h2>
    <?php if (!$ro && can('documents', 'create')): ?><a class="btn small ghost" href="/documents/upload?matter=<?= (int)$m['id'] ?>"><?= h(t('doc_upload')) ?></a><?php endif; ?></div>
  <?php if (!$ro && can('documents', 'create') && feature_on('templates') && ($dtpls = tpl_doc_list())): ?>
    <form method="get" action="/matters/<?= (int)$m['id'] ?>/compose" class="row compose-row"><label for="ctpl" class="small"><?= h(t('tpl_from_template')) ?></label>
      <select id="ctpl" name="template" class="auto"><?php foreach ($dtpls as $tx): ?><option value="<?= (int)$tx['id'] ?>"><?= h(t('tk_' . $tx['kind']) . ' — ' . $tx['name']) ?></option><?php endforeach; ?></select>
      <button class="btn small ghost"><?= h(t('tpl_preview_fill')) ?></button></form>
  <?php endif; ?>
  <?php if (!$mdocs): ?><?= empty_state(t('docs_empty'), t('docs_empty_help'), null, null, 'file') ?><?php endif; ?>
  <ul class="plain one">
    <?php foreach ($mdocs as $x): ?><li><a href="/documents/<?= (int)$x['id'] ?>"><?= h($x['title']) ?></a> <span class="muted small">— <?= h(lookup_name($x['category_id'] ? (int)$x['category_id'] : null, 'doc_category')) ?> · v<?= (int)$x['current_version'] ?></span><?= $x['source'] === 'client' ? ' <span class="tag">' . h(t('doc_from_client')) . '</span>' : '' ?><?= $x['portal_visible'] ? ' <span class="tag">' . h(t('doc_in_portal')) . '</span>' : '' ?></li><?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?php if (!is_support($me)):   /* client messages and updates are not shown during a support visit */ ?>
<?php $cparties = array_filter($parties, fn($p) => in_array($p['role'], ['client', 'co_client'], true));
  $updates = orows('SELECT * FROM client_updates WHERE office_id = :office AND matter_id = :m ORDER BY id DESC LIMIT 30', ['m' => $m['id']]);
  $msgs = matter_messages((int)$m['id']); $reqs = portal_requests((int)$m['id']); $approver = can_approve_update($me, $m);
  oq('UPDATE portal_messages SET read_by_staff_at = :now WHERE office_id = :office AND matter_id = :m AND portal_user_id IS NOT NULL AND read_by_staff_at IS NULL', ['now' => now(), 'm' => $m['id']]); ?>
<section class="card" id="portal" data-panel="portal">
  <h2><?= h(t('portal_name')) ?></h2>
  <p class="muted small"><?= h(t('portal_matter_help')) ?></p>
  <ul class="plain one">
    <?php foreach ($cparties as $p): $acc = portal_user_for_contact((int)$p['contact_id']); ?>
      <li><?= h(contact_label($p)) ?> — <?= $p['portal_visible'] ? '<b>' . h(t('portal_shared')) . '</b>' : h(t('portal_not_shared')) ?><?= !$acc || $acc['status'] !== 'active' ? ' <span class="tag">' . h(t('portal_no_account_short')) . '</span>' : '' ?> <a class="small" href="/matters/<?= (int)$m['id'] ?>/portal-preview?contact=<?= (int)$p['contact_id'] ?>"><?= icon('portal') ?> <?= h(t('pv_link')) ?></a>
        <?php if ($edit): ?><form method="post" action="/matters/<?= (int)$m['id'] ?>/portal/<?= (int)$p['id'] ?>/<?= $p['portal_visible'] ? 'off' : 'on' ?>" class="inline"<?= $p['portal_visible'] ? '' : ' data-confirm="' . h(t('portal_confirm_share')) . '"' ?>><?= csrf_field() ?><button class="link small"><?= h(t($p['portal_visible'] ? 'portal_stop_sharing' : 'portal_share')) ?></button></form><?php endif; ?></li>
    <?php endforeach; ?>
  </ul>
  <?php if ($sl2 = share_log((int)$m['id'], 8)): ?><details class="mt"><summary><?= h(t('share_log')) ?></summary><ul class="plain one small muted"><?php foreach ($sl2 as $x): ?><li><span dir="ltr"><?= h(fmt_dt($x['at'])) ?></span> · <?= h(staff_name($x['staff_id'] ? (int)$x['staff_id'] : null)) ?> · <?= h(t($x['shared'] ? 'share_on' : 'share_off', t('sk_' . $x['kind']))) ?></li><?php endforeach; ?></ul></details><?php endif; ?>
  <h3 class="mt"><?= h(t('portal_updates')) ?></h3>
  <?php foreach ($updates as $x): ?><div class="update <?= $x['status'] ?>"><div class="when"><span class="tag"><?= h(t('ups_' . $x['status'])) ?></span> <span dir="ltr"><?= h(fmt_dt($x['published_at'] ?: $x['created_at'])) ?></span> · <?= h(staff_name((int)$x['created_by'])) ?><?= $x['approved_by'] ? ' · ' . h(t('approved_by', staff_name((int)$x['approved_by']))) : '' ?></div><p class="pre"><?= h($x['body']) ?></p>
    <?php if ($x['status'] === 'draft' && $edit): ?><div class="row"><?php if ($approver): ?><form method="post" action="/matters/<?= (int)$m['id'] ?>/updates/<?= (int)$x['id'] ?>/publish" data-confirm="<?= h(t('update_confirm_publish')) ?>"><?= csrf_field() ?><button class="btn small"><?= h(t('update_publish')) ?></button></form><?php else: ?><span class="muted small"><?= h(t('update_waiting')) ?></span><?php endif; ?>
      <form method="post" action="/matters/<?= (int)$m['id'] ?>/updates/<?= (int)$x['id'] ?>/discard"><?= csrf_field() ?><button class="btn small ghost"><?= h(t('update_discard')) ?></button></form></div><?php endif; ?></div><?php endforeach; ?>
  <?php if ($edit): ?><details><summary><?= h(t('update_new')) ?></summary><form method="post" action="/matters/<?= (int)$m['id'] ?>/updates"><?= csrf_field() ?>
    <label for="ubody" class="sr"><?= h(t('update_new')) ?></label><textarea id="ubody" name="body" rows="3" required placeholder="<?= h(t('update_ph')) ?>"></textarea>
    <p class="muted small"><?= h(t('update_help')) ?></p><button class="btn small"><?= h(t('update_save_draft')) ?></button></form></details><?php endif; ?>
  <h3 class="mt"><?= h(t('portal_requested')) ?></h3>
  <?php if (!$reqs): ?><p class="muted small"><?= h(t('empty')) ?></p><?php endif; ?>
  <ul class="plain one"><?php foreach ($reqs as $r): ?><li><?= h($r['title']) ?> <span class="tag"><?= h(t('dr_' . $r['status'])) ?></span><?= $r['document_id'] ? ' <a class="small" href="/documents/' . (int)$r['document_id'] . '">' . h(t('open')) . '</a>' : '' ?></li><?php endforeach; ?></ul>
  <?php if (!$ro && can('documents', 'create')): ?><form method="post" action="/matters/<?= (int)$m['id'] ?>/requests" class="row"><?= csrf_field() ?><label class="sr" for="rtitle"><?= h(t('dr_new')) ?></label><input id="rtitle" name="title" maxlength="200" required class="auto" placeholder="<?= h(t('dr_ph')) ?>"><button class="btn small ghost"><?= h(t('dr_new')) ?></button></form><?php endif; ?>
  <h3 class="mt"><?= h(t('portal_messages')) ?></h3>
  <div class="thread"><?php if (!$msgs): ?><p class="muted small"><?= h(t('portal_no_messages')) ?></p><?php endif; ?>
    <?php foreach ($msgs as $x): ?><div class="msg <?= $x['staff_id'] ? 'mine' : 'theirs' ?>"><div class="when"><span dir="ltr"><?= h(fmt_dt($x['created_at'])) ?></span> · <?= h($x['staff_id'] ? staff_name((int)$x['staff_id']) : t('portal_client')) ?></div><p class="pre"><?= h($x['body']) ?></p></div><?php endforeach; ?></div>
  <?php if ($edit && $msgs): ?><form method="post" action="/matters/<?= (int)$m['id'] ?>/reply"><?= csrf_field() ?><label for="reply"><?= h(t('portal_reply')) ?></label><textarea id="reply" name="body" rows="2" required></textarea><button class="btn small"><?= h(t('portal_send')) ?></button></form><?php endif; ?>
</section>

<?php endif; ?>
<?php if (can('finance', 'view') && feature_on('finance')): $fa = orows('SELECT * FROM fee_agreements WHERE office_id = :office AND matter_id = :m ORDER BY id', ['m' => $m['id']]);
  $fi = orows('SELECT * FROM invoices WHERE office_id = :office AND matter_id = :m ORDER BY id DESC', ['m' => $m['id']]);
  $fe = orows('SELECT * FROM expenses WHERE office_id = :office AND matter_id = :m ORDER BY spent_on DESC, id DESC', ['m' => $m['id']]); $fc = !$ro && can('finance', 'create'); ?>
<section class="card" id="finance" data-panel="fees">
  <h2><?= h(t('mod_finance')) ?></h2>
  <h3><?= h(t('fee_agreements')) ?></h3>
  <?php if (!$fa): ?><p class="muted small"><?= h(t('empty')) ?></p><?php endif; ?>
  <?php foreach ($fa as $x): $fs = agreement_summary($x); ?>
    <a class="fee-card" href="/finance/agreements/<?= (int)$x['id'] ?>">
      <div class="row"><b><?= h(t('fk_' . $x['kind'])) ?></b><?= $fs['overdue'] ? '<span class="ist ist-overdue">' . h(t('fee_overdue')) . ' ' . h(money($fs['overdue'])) . '</span>' : ($fs['due_now'] ? '<span class="ist ist-due">' . h(t('fee_due_now')) . ' ' . h(money($fs['due_now'])) . '</span>' : '') ?></div>
      <div class="fee-nums"><span><?= h(t('st_total')) ?> <b><?= h(money($fs['total'])) ?></b></span><span><?= h(t('st_paid')) ?> <b class="pos"><?= h(money($fs['paid'])) ?></b></span><span><?= h(t('st_remaining')) ?> <b><?= h(money($fs['remaining'])) ?></b></span></div>
      <div class="bar sm"><i class="w<?= (int)(round($fs['pct'] / 5) * 5) ?>"></i></div>
    </a>
    <p class="small"><a href="/finance/agreements/<?= (int)$x['id'] ?>/statement"><?= icon('receipt') ?> <?= h(t('statement')) ?></a><?= statement_shared((int)$x['id']) ? ' <span class="tag">' . h(t('st_in_portal')) . '</span>' : '' ?></p>
  <?php endforeach; ?>
  <?php if ($fc): ?><a class="btn small ghost" href="/matters/<?= (int)$m['id'] ?>/agreement"><?= h(t('fee_new')) ?></a><?php endif; ?>
  <h3 class="mt"><?= h(t('invoices')) ?></h3>
  <?php if (!$fi): ?><p class="muted small"><?= h(t('empty')) ?></p><?php endif; ?>
  <ul class="plain one"><?php foreach ($fi as $x): $s = invoice_state($x); ?><li><a href="/finance/invoices/<?= (int)$x['id'] ?>" dir="ltr"><?= h($x['number'] ?: t('inv_draft')) ?></a> · <?= h(money((int)$x['total'])) ?> · <span class="inv inv-<?= h($s) ?>"><?= h(t('is_' . $s)) ?></span></li><?php endforeach; ?></ul>
  <?php if ($fc): ?><form method="post" action="/matters/<?= (int)$m['id'] ?>/invoice" class="inline"><?= csrf_field() ?><button class="btn small ghost"><?= h(t('inv_new')) ?></button></form><?php endif; ?>
  <h3 class="mt"><?= h(t('expenses')) ?></h3>
  <?php if (!$fe): ?><p class="muted small"><?= h(t('empty')) ?></p><?php endif; ?>
  <ul class="plain one"><?php foreach ($fe as $x): ?><li><?= $x['category'] === 'court_fee' ? '<span class="tag k-judgment">' . h(t('ec_court_fee')) . '</span> ' : '' ?><?= h($x['description']) ?> · <?= h(money((int)$x['amount'])) ?> · <span dir="ltr"><?= h(date('d/m/Y', strtotime($x['spent_on']))) ?></span> <span class="tag"><?= h(t($x['paid_from'] === 'trust' ? 'exp_tag_trust' : ($x['invoice_line_id'] ? 'exp_tag_billed' : ($x['billable'] ? 'exp_tag_billable' : 'exp_tag_office')))) ?></span></li><?php endforeach; ?></ul>
  <?php if ($fe): $cf = array_sum(array_map(fn($x) => $x['category'] === 'court_fee' && $x['paid_from'] === 'office' ? (int)$x['amount'] : 0, $fe)); $oe = array_sum(array_map(fn($x) => $x['category'] !== 'court_fee' && $x['paid_from'] === 'office' ? (int)$x['amount'] : 0, $fe)); ?>
    <p class="muted small"><?= h(t('st_court_fees')) ?>: <b><?= h(money($cf)) ?></b> · <?= h(t('st_expenses')) ?>: <b><?= h(money($oe)) ?></b> <?= h(t('exp_office_only')) ?></p><?php endif; ?>
  <?php if ($nts = notices_for_matter((int)$m['id'])): ?><h3 class="mt"><?= h(t('notices')) ?></h3><ul class="plain one"><?php foreach (array_slice($nts, 0, 5) as $x): ?><li><a href="/finance/notices/<?= (int)$x['id'] ?>"><?= h(money((int)$x['amount'])) ?></a> <span class="nst nst-<?= h($x['status']) ?>"><?= h(t('nst_' . $x['status'])) ?></span></li><?php endforeach; ?></ul><?php endif; ?>
  <p class="small mt"><a href="/finance/clients/<?= (int)$m['client_id'] ?>/statement"><?= h(t('st_client_link')) ?></a></p>
  <?php if ($fc): ?><a class="btn small ghost" href="/matters/<?= (int)$m['id'] ?>/expense"><?= h(t('exp_new')) ?></a> <a class="small" href="/finance/trust/<?= (int)$m['client_id'] ?>"><?= h(t('trust_balance', money(trust_balance((int)$m['client_id'])))) ?></a><?php endif; ?>
</section>
<?php endif; ?>
