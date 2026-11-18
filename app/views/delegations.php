<?php $me = current_staff(); $staff = active_staff(); $q = fn(array $x) => '/delegations?' . http_build_query(array_filter($x + $f + ['d' => $f['d']], fn($v) => $v !== '' && $v !== 0 && $v !== false));
[$from, $to] = deleg_period($f);
$byLawyer = []; foreach ($list as $r) { $k = (int)$r['to_staff']; $byLawyer[$k] = ($byLawyer[$k] ?? 0) + 1; } arsort($byLawyer);
$shift = fn(int $n) => date('Y-m-d', strtotime($f['d'] . ' ' . ($n > 0 ? '+' : '') . $n . ($f['period'] === 'week' ? ' weeks' : ' days'))); ?>
<div class="page-head">
  <div><h1><?= h(t('mod_delegations')) ?></h1><p><?= h(t('deleg_help')) ?></p></div>
  <div class="row"><a class="btn ghost small" href="<?= h($q(['pdf' => 1])) ?>"><?= icon('file') ?><?= h(t('deleg_report_pdf')) ?></a></div>
</div>
<section class="card">
  <form class="filters" method="get" action="/delegations" data-auto>
    <div class="seg" role="group" aria-label="<?= h(t('deleg_period')) ?>">
      <?php foreach (['day', 'week', 'all'] as $p): ?><a href="<?= h($q(['period' => $p])) ?>"<?= $f['period'] === $p ? ' aria-current="page" class="on"' : '' ?>><?= h(t('deleg_period_' . $p)) ?></a><?php endforeach; ?>
    </div>
    <input type="hidden" name="period" value="<?= h($f['period']) ?>">
    <?php if ($f['period'] !== 'all'): ?>
      <a class="btn ghost small" href="<?= h($q(['d' => $shift(-1)])) ?>" aria-label="<?= h(t('previous')) ?>">‹</a>
      <label class="sr" for="d"><?= h(t('date')) ?></label><input id="d" name="d" type="date" dir="ltr" value="<?= h($f['d']) ?>" class="auto">
      <a class="btn ghost small" href="<?= h($q(['d' => $shift(1)])) ?>" aria-label="<?= h(t('next')) ?>">›</a>
    <?php endif; ?>
    <label class="sr" for="status"><?= h(t('status')) ?></label>
    <select id="status" name="status" class="auto"><option value=""><?= h(t('deleg_all_statuses')) ?></option><?php foreach (DELEG_STATUSES as $s): ?><option value="<?= $s ?>"<?= $f['status'] === $s ? ' selected' : '' ?>><?= h(t('dst_' . $s)) ?></option><?php endforeach; ?></select>
    <?php if (can('calendar', 'view')): ?><label class="sr" for="staff"><?= h(t('deleg_lawyer')) ?></label>
    <select id="staff" name="staff" class="auto"><option value="0"><?= h(t('deleg_all_lawyers')) ?></option><?php foreach ($staff as $s): ?><option value="<?= (int)$s['id'] ?>"<?= (int)$f['staff'] === (int)$s['id'] ? ' selected' : '' ?>><?= h($s['full_name']) ?></option><?php endforeach; ?></select><?php endif; ?>
    <label class="radio small"><input type="checkbox" name="mine" value="1"<?= $f['mine'] ? ' checked' : '' ?>> <?= h(t('deleg_only_mine')) ?></label>
    <noscript><button class="btn small"><?= h(t('show')) ?></button></noscript>
  </form>
  <?php if ($from): ?><p class="muted small"><?= h(t('deleg_period_' . $f['period'])) ?>: <span dir="ltr"><?= h(date('d/m/Y', strtotime($from))) ?><?= $f['period'] === 'week' ? ' – ' . h(date('d/m/Y', strtotime("$to -1 day"))) : '' ?></span> · <?= h(t('deleg_count', count($list))) ?></p><?php endif; ?>
  <?php if ($byLawyer && count($byLawyer) > 1): ?><p class="row small"><?php foreach ($byLawyer as $sid => $n): ?><span class="n-pill"><?= h(staff_name($sid)) ?> · <?= $n ?></span><?php endforeach; ?></p><?php endif; ?>
  <?php if (!$list): ?><?= empty_state(t('deleg_empty'), t('deleg_empty_help'), null, null, 'delegate') ?>
  <?php else: ?>
  <div class="table-wrap"><table data-sortable>
    <thead><tr><th data-sort><?= h(t('deleg_when')) ?></th><th data-sort><?= h(t('appt_matter')) ?></th><th><?= h(t('matter_court')) ?></th><th data-sort><?= h(t('deleg_from')) ?></th><th data-sort><?= h(t('deleg_to')) ?></th><th data-sort><?= h(t('status')) ?></th></tr></thead>
    <tbody><?php foreach ($list as $r): $when = $r['starts_at'] ?: $r['created_at']; ?>
      <tr><td data-v="<?= h($when) ?>"><a href="/delegations/<?= (int)$r['id'] ?>" dir="ltr"><?= h(date($r['starts_at'] ? 'd/m/Y H:i' : 'd/m/Y', strtotime($when))) ?></a><?= $r['appt_title'] ? '<div class="muted small">' . h($r['appt_title']) . '</div>' : '' ?></td>
        <td><span class="ref" dir="ltr"><?= h($r['matter_ref']) ?></span> <?= h($r['matter_title']) ?></td>
        <td><?= $r['appt_court'] ? h(lookup_name((int)$r['appt_court'], 'court')) . ($r['appt_circuit'] ? ' · ' . h($r['appt_circuit']) : '') : '—' ?></td>
        <td><?= h(staff_name((int)$r['from_staff'])) ?></td><td><?= h(staff_name((int)$r['to_staff'])) ?></td>
        <td data-v="<?= h($r['status']) ?>"><span class="dst dst-<?= h($r['status']) ?>"><?= h(t('dst_' . $r['status'])) ?></span></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</section>
