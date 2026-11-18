<?php $o = current_office(); $today = date('Y-m-d'); $ro = office_readonly() || is_support($me); $ar = lang() === 'ar';
$canOffice = can('reports', 'view') || can('tasks', 'view');
$scope = dash_scope($me, $_GET['scope'] ?? null);
$td = dash_today($me, $scope);
$na = needs_action($me, $scope);
[$myTasks] = task_list($me, ['scope' => 'mine', 'state' => 'open'], 50);
$dueToday = array_values(array_filter($myTasks, fn($x) => $x['due_on'] && $x['due_on'] <= $today));
$recent = [];
if (can('matters', 'view')) {   // latest file activity the person may see; internal notes and their wording are never shown here
  [$vis, $vp] = matter_visible_sql($me);
  $recent = orows("SELECT e.kind, e.at, e.matter_id, m.ref, m.title, s.full_name FROM matter_events e JOIN matters m ON m.id = e.matter_id AND m.office_id = e.office_id LEFT JOIN staff s ON s.id = e.staff_id AND s.office_id = e.office_id
    WHERE e.office_id = :office AND $vis AND e.kind <> 'note'" . ($scope === 'mine' ? ' AND m.lead_id = :me9' : '') . " ORDER BY e.id DESC LIMIT 6", $vp + ($scope === 'mine' ? ['me9' => $me['id']] : []));
}
$more = array_filter([
  [can('clients', 'create') && !$ro, '/clients/new', 'qa_client', 'users'], [can('calendar', 'create') && !$ro, '/calendar/new', 'qa_appt', 'calendar'],
  [can('tasks', 'create') && !$ro, '/tasks/new', 'qa_task', 'tasks'], [can('documents', 'create') && !$ro, '/documents/upload', 'qa_doc', 'upload'],
], fn($x) => $x[0]);
$apptLine = function (array $a) use ($today): string {
  $court = $a['court_id'] ? lookup_name((int)$a['court_id'], 'court') : ($a['location'] ?: '');
  return '<a class="appt-card" href="/calendar/' . (int)$a['id'] . '"><span class="ac-time" dir="ltr">' . h(date(substr($a['starts_at'], 0, 10) === $today ? 'H:i' : 'd/m H:i', strtotime($a['starts_at']))) . '</span>'
    . '<span class="ac-body"><b>' . h($a['title']) . '</b><span class="sub">' . ($a['matter_ref'] ? '<span class="ref" dir="ltr">' . h($a['matter_ref']) . '</span> · ' : '') . h($court) . ($court ? ' · ' : '') . h(staff_name((int)$a['lead_id'])) . '</span></span>'
    . '<span class="tag">' . h(t('ak_' . $a['kind'])) . '</span></a>';
};
?>
<div class="page-head dash-head">
  <div><h1><?= h(t('dash_today_title')) ?></h1><p><?= h(t('welcome', $me['full_name'])) ?> · <span dir="ltr"><?= h(date('d/m/Y')) ?></span></p></div>
  <?php if (!$ro): ?><div class="row">
    <?php if (can('matters', 'create')): ?><a class="btn" href="/matters/new"><?= icon('folder') ?><?= h(t('qa_matter')) ?></a><?php endif; ?>
    <?php if (can('conflicts', 'create')): ?><a class="btn ghost" href="/conflicts/new"><?= icon('shield') ?><?= h(t('qa_check')) ?></a><?php endif; ?>
    <?php if ($more): ?><details class="qa-menu" data-pop><summary class="btn ghost" aria-label="<?= h(t('qa_title')) ?>"><?= icon('plus') ?><span><?= h(t('qa_more')) ?></span></summary>
      <div class="menu-pop" role="menu"><?php foreach ($more as [, $href, $k, $ic]): ?><a role="menuitem" href="<?= $href ?>"><?= icon($ic) ?><?= h(t($k)) ?></a><?php endforeach; ?></div></details><?php endif; ?>
  </div><?php endif; ?>
</div>
<?php if ($canOffice): ?>
<nav class="seg" aria-label="<?= h(t('dash_scope')) ?>"><a href="/?scope=mine"<?= $scope === 'mine' ? ' aria-current="page"' : '' ?>><?= h(t('dash_mine')) ?></a><a href="/?scope=office"<?= $scope === 'office' ? ' aria-current="page"' : '' ?>><?= h(t('dash_office')) ?></a></nav>
<?php endif; ?>

<section class="today-grid">
  <div class="card today">
    <div class="head-row"><h2><?= h(t('dash_hearings_today')) ?></h2><?php if (can('calendar', 'view')): ?><a class="small" href="/calendar?view=day"><?= h(t('mod_calendar')) ?></a><?php endif; ?></div>
    <?php if ($td['today']): foreach ($td['today'] as $a) echo $apptLine($a); ?>
    <?php elseif ($td['next']): ?><p class="small muted"><?= h(t('dash_nothing_today_next')) ?></p><?= $apptLine($td['next']) ?>
    <?php else: ?><?= empty_state(t('dash_week_empty'), '', can('calendar', 'create') && !$ro ? '/calendar/new' : null, t('qa_appt'), 'calendar') ?><?php endif; ?>
  </div>
  <div class="card today">
    <div class="head-row"><h2><?= h(t('dash_tasks_today')) ?><?= $dueToday ? ' <span class="n-pill">' . count($dueToday) . '</span>' : '' ?></h2><a class="small" href="/tasks"><?= h(t('mod_tasks')) ?></a></div>
    <?php if (!$dueToday): ?><?= empty_state(t('dash_tasks_clear'), '', null, null, 'tasks') ?><?php endif; ?>
    <ul class="plain one"><?php foreach (array_slice($dueToday, 0, 6) as $x): $lt = $x['due_on'] < $today; ?><li>
      <a href="/tasks/<?= (int)$x['id'] ?>"><?= h($x['title']) ?></a><?= $x['priority'] === 'high' ? ' <span class="pri pri-high">' . h(t('tp_high')) . '</span>' : '' ?>
      <span class="sub"><?= $x['matter_ref'] ? '<span class="ref" dir="ltr">' . h($x['matter_ref']) . '</span> · ' : '' ?><span class="<?= $lt ? 'late' : '' ?>" dir="ltr"><?= h(date('d/m', strtotime($x['due_on']))) ?></span><?= $lt ? ' · ' . h(t('overdue')) : '' ?></span>
    </li><?php endforeach; ?></ul>
  </div>
</section>

<?php /* quick-access tiles with live counts (only what the person may open) */
$hubs = [];
if (can('matters', 'view')) { [$mv, $mp] = matter_visible_sql($me); $hubs[] = ['/matters', 'folder', 'ink', t('mod_matters'), t('hub_open_files'), (int)orow("SELECT COUNT(*) n FROM matters m WHERE m.office_id = :office AND m.closed_at IS NULL AND $mv", $mp)['n'], false]; }
if (can('calendar', 'view')) { [$lv, $lp] = linked_visible_sql($me); $wk = (int)orow("SELECT COUNT(*) n FROM appointments x LEFT JOIN matters m ON m.id = x.matter_id AND m.office_id = x.office_id WHERE x.office_id = :office AND $lv AND x.status = 'scheduled' AND x.starts_at >= :a AND x.starts_at < :b", $lp + ['a' => "$today 00:00:00", 'b' => date('Y-m-d', strtotime('+7 days')) . ' 00:00:00'])['n'];
  $hubs[] = ['/calendar', 'calendar', 'petrol', t('mod_calendar'), t('hub_week'), $wk, false];
  $fu = count(followup_list($me, [])); $hubs[] = ['/followup', 'alert', 'red', t('fu_short'), t('fu_short_sub'), $fu, $fu > 0]; }
$hubs[] = ['/tasks', 'tasks', 'blue', t('mod_tasks'), t('hub_my_open'), count($myTasks), false];
if (feature_on('delegations')) { $dn = (int)orow("SELECT COUNT(*) n FROM delegations WHERE office_id = :office AND to_staff = :me AND status = 'pending'", ['me' => $me['id']])['n']; $hubs[] = ['/delegations', 'delegate', 'violet', t('mod_delegations'), t('hub_waiting_you'), $dn, $dn > 0]; }
if (can('documents', 'view')) { $hubs[] = ['/documents', 'file', 'green', t('mod_documents'), '', null, false]; if (feature_on('templates')) $hubs[] = ['/templates', 'contract', 'gold', t('mod_templates'), '', null, false]; }
if (can('finance', 'view') && feature_on('finance')) { $ov = (int)orow("SELECT COUNT(*) n FROM invoices WHERE office_id = :office AND status = 'issued' AND paid < total AND due_on < :t", ['t' => $today])['n']; $hubs[] = ['/finance', 'wallet', 'gold', t('hub_fees'), t('hub_overdue_inv'), $ov, $ov > 0]; }
if (can('reports', 'view') && feature_on('reports')) $hubs[] = ['/reports', 'chart', 'ink', t('mod_reports'), '', null, false]; ?>
<h2 class="sec-title"><?= h(t('hub_title')) ?></h2>
<nav class="hubs" aria-label="<?= h(t('hub_title')) ?>"><?php foreach ($hubs as $x) echo hub_tile(...$x); ?></nav>

<section class="card" aria-labelledby="na-h">
  <div class="head-row"><h2 id="na-h"><?= h(t('na_title')) ?></h2><?php if ($na): ?><span class="n-pill warn"><?= array_sum(array_column($na, 'n')) ?></span><?php endif; ?></div>
  <?php if (!$na): ?><?= empty_state(t('na_clear'), t('na_clear_help'), null, null, 'tasks') ?><?php endif; ?>
  <div class="na-groups">
  <?php foreach ($na as $grp): ?>
    <details class="na-group"<?= $grp === $na[0] ? ' open' : '' ?>>
      <summary><span class="na-title"><?= h(t($grp['key'])) ?></span><span class="n-pill<?= in_array($grp['key'], ['na_no_outcome', 'na_tasks', 'na_next_late', 'na_due'], true) ? ' warn' : '' ?>"><?= (int)$grp['n'] ?></span><?php if (isset($grp['sum'])): ?><span class="muted small"><?= h(money($grp['sum'])) ?></span><?php endif; ?></summary>
      <ul class="na-list">
        <?php foreach ($grp['items'] as $it): ?><li><a href="<?= h($it['href']) ?>"><b><?= h($it['title']) ?></b>
          <span class="sub"><?= $it['ref'] ? '<span class="ref" dir="ltr">' . h($it['ref']) . '</span>' : '' ?><?= $it['when'] ? ' · <span dir="ltr" class="' . ($it['late'] ? 'late' : '') . '">' . h(date(strlen($it['when']) > 10 ? 'd/m H:i' : 'd/m/Y', strtotime($it['when']))) . '</span>' : '' ?><?= $it['who'] ? ' · ' . h(staff_name($it['who'])) : '' ?><?= isset($it['tag']) ? ' · ' . h(t($it['tag'])) : '' ?></span></a></li><?php endforeach; ?>
      </ul>
      <a class="small" href="<?= h($grp['link']) ?>"><?= h(t('na_open_list')) ?></a>
    </details>
  <?php endforeach; ?>
  </div>
</section>

<?php if (can('reports', 'view')): $rd = report_data($me, report_filters($scope === 'mine' ? ['lead' => $me['id']] : [])); ?>
<section class="kpis compact" aria-label="<?= h(t('home_office_glance')) ?>">
  <a class="kpi" href="/matters<?= $scope === 'mine' ? '?lead=' . (int)$me['id'] : '' ?>"><span><?= h(t('rp_open_now')) ?></span><b><?= (int)$rd['open_now'] ?></b></a>
  <a class="kpi" href="/reports"><span><?= h(t('rp_opened')) ?> · <?= h(t('this_month')) ?></span><b><?= (int)$rd['opened'] ?></b></a>
  <a class="kpi" href="/reports"><span><?= h(t('rp_closed')) ?> · <?= h(t('this_month')) ?></span><b><?= (int)$rd['closed'] ?></b></a>
  <?php if (isset($rd['money'])): ?><a class="kpi" href="/finance"><span><?= h(t('kpi_received')) ?> · <?= h(t('this_month')) ?></span><b><?= h(money((int)$rd['money']['received'])) ?></b></a><?php endif; ?>
</section>
<p class="small muted"><a href="/reports"><?= h(t('rp_full')) ?></a> — <?= h(t('dash_kpi_note')) ?></p>
<?php endif; ?>

<?php if (can('matters', 'view')): ?>
<section class="card">
  <div class="head-row"><h2><?= h(t('dash_recent')) ?></h2><a class="small" href="/matters?sort=updated"><?= h(t('mod_matters')) ?></a></div>
  <?php if (!$recent): ?><?= empty_state(t('dash_no_recent'), '', null, null, 'clock') ?><?php endif; ?>
  <ul class="feed">
    <?php foreach ($recent as $e): ?><li><span class="dot k-<?= h($e['kind']) ?>" aria-hidden="true"></span><div>
      <a href="/matters/<?= (int)$e['matter_id'] ?>#timeline"><span class="ref" dir="ltr"><?= h($e['ref']) ?></span> — <?= h($e['title']) ?></a>
      <div class="meta"><?= h(t('rk_' . $e['kind'])) ?> · <?= h($e['full_name'] ?? '—') ?> · <span dir="ltr"><?= h(fmt_dt($e['at'])) ?></span></div>
    </div></li><?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>
<?php if (false): ?><p class="note small"><?= h(t('2fa_optional_hint')) ?> <a href="/account"><?= h(t('account')) ?></a></p><?php endif; ?>
