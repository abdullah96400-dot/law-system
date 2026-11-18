<?php $q = http_build_query(array_filter($f)); $types = lookups('case_type'); $brs = branches(true); ?>
<section class="card">
  <div class="head-row"><h1><?= h(t('mod_reports')) ?></h1>
    <?php if (can('reports', 'download')): ?><a class="btn ghost" href="/reports?<?= h($q) ?>&amp;export=1"><?= h(t('rp_export')) ?></a><?php endif; ?></div>
  <form method="get" action="/reports" class="filters">
    <div><label for="from"><?= h(t('from')) ?></label><input id="from" name="from" type="date" dir="ltr" value="<?= h($f['from']) ?>"></div>
    <div><label for="to"><?= h(t('to_excl')) ?></label><input id="to" name="to" type="date" dir="ltr" value="<?= h($f['to']) ?>"></div>
    <?php if ($brs): ?><div><label for="branch"><?= h(t('branch')) ?></label><select id="branch" name="branch"><option value=""><?= h(t('all')) ?></option><?php foreach ($brs as $b): ?><option value="<?= (int)$b['id'] ?>"<?= $f['branch'] === (int)$b['id'] ? ' selected' : '' ?>><?= h($b['name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
    <div><label for="lead"><?= h(t('matter_lead')) ?></label><select id="lead" name="lead"><option value=""><?= h(t('all')) ?></option><?php foreach (active_staff() as $s): ?><option value="<?= (int)$s['id'] ?>"<?= $f['lead'] === (int)$s['id'] ? ' selected' : '' ?>><?= h($s['full_name']) ?></option><?php endforeach; ?></select></div>
    <?php if ($types): ?><div><label for="type"><?= h(t('matter_type')) ?></label><select id="type" name="type"><option value=""><?= h(t('all')) ?></option><?php foreach ($types as $ty): ?><option value="<?= (int)$ty['id'] ?>"<?= $f['type'] === (int)$ty['id'] ? ' selected' : '' ?>><?= h(lookup_label($ty)) ?></option><?php endforeach; ?></select></div><?php endif; ?>
    <div><button class="btn"><?= h(t('show')) ?></button></div>
  </form>
  <p class="muted small"><?= h(t('rp_scope_note')) ?></p>
  <div class="kpis">
    <div class="kpi"><span><?= h(t('rp_open_now')) ?></span><b><?= (int)$d['open_now'] ?></b><small><?= h(t('rp_open_now_def')) ?></small></div>
    <div class="kpi"><span><?= h(t('rp_opened')) ?></span><b><?= (int)$d['opened'] ?></b><small><?= h(t('rp_opened_def')) ?></small></div>
    <div class="kpi"><span><?= h(t('rp_closed')) ?></span><b><?= (int)$d['closed'] ?></b><small><?= h(t('rp_closed_def')) ?></small></div>
    <div class="kpi<?= $d['hearings_no_outcome'] ? ' warn' : '' ?>"><span><?= h(t('rp_hearings_no_outcome')) ?></span><b><?= (int)$d['hearings_no_outcome'] ?></b><small><?= h(t('rp_hearings_no_outcome_def')) ?></small></div>
    <div class="kpi<?= $d['tasks_overdue'] ? ' warn' : '' ?>"><span><?= h(t('rp_tasks_overdue')) ?></span><b><?= (int)$d['tasks_overdue'] ?></b><small><?= h(t('rp_tasks_overdue_def')) ?></small></div>
  </div>
  <?php if (isset($d['money'])): $mo = $d['money']; ?>
  <h2><?= h(t('rp_money')) ?></h2>
  <div class="kpis">
    <div class="kpi"><span><?= h(t('kpi_invoiced')) ?></span><b><?= h(money((int)$mo['invoiced'])) ?></b><small><?= h(t('kpi_invoiced_def')) ?></small></div>
    <div class="kpi"><span><?= h(t('kpi_received')) ?></span><b><?= h(money((int)$mo['received'])) ?></b><small><?= h(t('kpi_received_def')) ?></small></div>
    <div class="kpi"><span><?= h(t('kpi_outstanding')) ?></span><b><?= h(money((int)$mo['outstanding'])) ?></b><small><?= h(t('rp_outstanding_def')) ?></small></div>
    <div class="kpi warn"><span><?= h(t('kpi_overdue')) ?></span><b><?= h(money((int)$mo['overdue'])) ?></b><small><?= h(t('rp_overdue_def')) ?></small></div>
  </div>
  <?php endif; ?>
</section>

<section class="grid two">
  <div class="card">
    <h2><?= h(t('rp_by_status')) ?></h2>
    <?php if (!$d['by_status']): ?><p class="muted"><?= h(t('empty')) ?></p><?php else: ?>
    <table class="stack"><thead><tr><th scope="col"><?= h(t('status')) ?></th><th scope="col" class="num"><?= h(t('rp_files')) ?></th></tr></thead><tbody>
      <?php foreach ($d['by_status'] as $r): ?><tr><td data-label="<?= h(t('status')) ?>"><a href="/matters?status=<?= (int)$r['status_id'] ?><?= $f['lead'] ? '&amp;lead=' . $f['lead'] : '' ?>"><?= h(lookup_name((int)$r['status_id'], 'status')) ?></a></td><td class="num" data-label="<?= h(t('rp_files')) ?>"><?= (int)$r['n'] ?></td></tr><?php endforeach; ?>
    </tbody></table><?php endif; ?>
  </div>
  <div class="card">
    <h2><?= h(t('rp_portal')) ?></h2>
    <dl class="facts">
      <dt><?= h(t('rp_portal_accounts')) ?></dt><dd><?= (int)$d['portal']['accounts'] ?></dd>
      <dt><?= h(t('rp_portal_logins')) ?></dt><dd><?= (int)$d['portal']['logins'] ?></dd>
      <dt><?= h(t('rp_portal_messages')) ?></dt><dd><?= (int)$d['portal']['messages'] ?></dd>
      <dt><?= h(t('rp_portal_uploads')) ?></dt><dd><?= (int)$d['portal']['uploads'] ?></dd>
    </dl>
    <p class="muted small"><?= h(t('rp_portal_def')) ?></p>
  </div>
</section>

<section class="card">
  <h2><?= h(t('rp_workload')) ?></h2>
  <p class="muted small"><?= h(t('rp_workload_def')) ?></p>
  <table class="stack"><thead><tr><th scope="col"><?= h(t('name')) ?></th><th scope="col" class="num"><?= h(t('rp_files_lead')) ?></th><th scope="col" class="num"><?= h(t('rp_open_tasks')) ?></th><th scope="col" class="num"><?= h(t('rp_hearings_30')) ?></th></tr></thead><tbody>
    <?php foreach ($d['workload'] as $r): ?><tr><td data-label="<?= h(t('name')) ?>"><?= h($r['full_name']) ?></td><td class="num" data-label="<?= h(t('rp_files_lead')) ?>"><?= (int)$r['files'] ?></td><td class="num" data-label="<?= h(t('rp_open_tasks')) ?>"><?= (int)$r['tasks'] ?></td><td class="num" data-label="<?= h(t('rp_hearings_30')) ?>"><?= (int)$r['hearings'] ?></td></tr><?php endforeach; ?>
  </tbody></table>
</section>

<section class="card" id="stale">
  <h2><?= h(t('rp_stale')) ?></h2>
  <p class="muted small"><?= h(t('rp_stale_def')) ?></p>
  <?php if (!$d['stale']): ?><p class="muted"><?= h(t('empty')) ?></p><?php endif; ?>
  <ul class="plain one">
    <?php foreach ($d['stale'] as $r): ?><li><a href="/matters/<?= (int)$r['id'] ?>"><span dir="ltr"><?= h($r['ref']) ?></span> — <?= h($r['title']) ?></a> <span class="muted small">· <?= h(staff_name((int)$r['lead_id'])) ?> · <?= h(t('rp_last_entry')) ?>: <span dir="ltr"><?= h($r['last_at'] ? date('d/m/Y', strtotime($r['last_at'])) : '—') ?></span></span></li><?php endforeach; ?>
  </ul>
</section>

<?php if (isset($d['by_file'])): ?>
<section class="card">
  <h2><?= h(t('rp_by_file')) ?></h2>
  <p class="muted small"><?= h(t('rp_by_file_def')) ?></p>
  <?php if (!$d['by_file']): ?><p class="muted"><?= h(t('empty')) ?></p><?php else: ?>
  <table class="stack"><thead><tr><th scope="col"><?= h(t('mod_matters')) ?></th><th scope="col" class="num"><?= h(t('kpi_invoiced')) ?></th><th scope="col" class="num"><?= h(t('kpi_received')) ?></th><th scope="col" class="num"><?= h(t('kpi_expenses')) ?></th></tr></thead><tbody>
    <?php foreach ($d['by_file'] as $r): ?><tr><td data-label="<?= h(t('mod_matters')) ?>"><a href="/matters/<?= (int)$r['id'] ?>"><span dir="ltr"><?= h($r['ref']) ?></span> — <?= h($r['title']) ?></a></td><td class="num" data-label="<?= h(t('kpi_invoiced')) ?>"><?= h(money((int)$r['invoiced'])) ?></td><td class="num" data-label="<?= h(t('kpi_received')) ?>"><?= h(money((int)$r['received'])) ?></td><td class="num" data-label="<?= h(t('kpi_expenses')) ?>"><?= h(money((int)$r['expenses'])) ?></td></tr><?php endforeach; ?>
  </tbody></table><?php endif; ?>
</section>
<?php endif; ?>
