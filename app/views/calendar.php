<?php
$q = fn(array $x) => '/calendar?' . http_build_query(array_filter(array_merge(['view' => $view, 'd' => $d, 'lead' => $f['lead'] ?: null, 'kind' => $f['kind'] ?: null], $x)));
$step = ['day' => '1 day', 'week' => '7 days', 'month' => '1 month', 'list' => '60 days'][$view];
$prev = date('Y-m-d', strtotime("$d -$step")); $next = date('Y-m-d', strtotime("$d +$step"));
$byDay = []; foreach ($list as $a) $byDay[substr($a['starts_at'], 0, 10)][] = $a;
$days = []; for ($x = $from; $x < $to; $x = date('Y-m-d', strtotime("$x +1 day"))) $days[] = $x;
$today = date('Y-m-d'); $canNew = can('calendar', 'create') && !office_readonly();
?>
<div class="page-head">
  <div><h1><?= h(t('mod_calendar')) ?></h1></div>
  <?php if ($canNew): ?><a class="btn" href="/calendar/new"><?= icon('plus') ?><?= h(t('appt_new')) ?></a><?php endif; ?>
</div>
<section class="card">
  <p class="note small"><?= h(t('calendar_disclaimer')) ?></p>
  <nav class="tabs" aria-label="<?= h(t('calendar_view')) ?>">
    <?php foreach (['day', 'week', 'month', 'list'] as $v): ?><a href="<?= h($q(['view' => $v])) ?>"<?= $view === $v ? ' aria-current="page"' : '' ?>><?= h(t('cv_' . $v)) ?></a><?php endforeach; ?>
  </nav>
  <form method="get" action="/calendar" class="filters" data-auto>
    <input type="hidden" name="view" value="<?= h($view) ?>"><input type="hidden" name="d" value="<?= h($d) ?>">
    <div><label for="lead"><?= h(t('appt_lead')) ?></label><select id="lead" name="lead"><option value=""><?= h(t('all')) ?></option><?php foreach (active_staff() as $s): ?><option value="<?= (int)$s['id'] ?>"<?= $f['lead'] === (int)$s['id'] ? ' selected' : '' ?>><?= h($s['full_name']) ?></option><?php endforeach; ?></select></div>
    <div><label for="kind"><?= h(t('appt_kind')) ?></label><select id="kind" name="kind"><option value=""><?= h(t('all')) ?></option><?php foreach (APPT_KINDS as $k): ?><option value="<?= $k ?>"<?= $f['kind'] === $k ? ' selected' : '' ?>><?= h(t('ak_' . $k)) ?></option><?php endforeach; ?></select></div>
    <div><button class="btn"><?= h(t('show')) ?></button></div>
  </form>
  <div class="row cal-nav">
    <a class="btn small ghost" href="<?= h($q(['d' => $prev])) ?>"><?= h(t('previous')) ?></a>
    <b dir="ltr"><?= h(date('d/m/Y', strtotime($from))) ?><?= $view !== 'day' ? ' – ' . h(date('d/m/Y', strtotime("$to -1 day"))) : '' ?></b>
    <a class="btn small ghost" href="<?= h($q(['d' => $next])) ?>"><?= h(t('next')) ?></a>
    <a class="btn small ghost" href="<?= h($q(['d' => $today])) ?>"><?= h(t('today')) ?></a>
  </div>
</section>
<section class="card">
  <?php $shown = 0; foreach ($days as $day): $items = $byDay[$day] ?? []; if (!$items && $view !== 'week' && $view !== 'day') continue; $shown++; ?>
    <div class="cal-day<?= $day === $today ? ' is-today' : '' ?>">
      <h2 class="cal-date"><?= h(t('dow_' . date('w', strtotime($day)))) ?> <span dir="ltr"><?= h(date('d/m/Y', strtotime($day))) ?></span><?= $day === $today ? ' <span class="badge-s b-amber">' . h(t('today')) . '</span>' : '' ?></h2>
      <?php if (!$items): ?><p class="muted small"><?= h(t('cal_nothing')) ?></p><?php endif; ?>
      <ul class="plain one">
        <?php foreach ($items as $a): ?>
          <li class="appt appt-<?= h($a['kind']) ?>">
            <span class="time" dir="ltr"><?= h(substr($a['starts_at'], 11, 5)) ?></span>
            <a href="/calendar/<?= (int)$a['id'] ?>"><?= h($a['title']) ?></a>
            <span class="tag"><?= h(t('ak_' . $a['kind'])) ?></span>
            <?php if ($a['status'] !== 'scheduled'): ?><span class="badge-s <?= appt_badge($a) ?>"><?= h(t('as_' . $a['status'])) ?></span><?php elseif (appt_badge($a) === 'b-late'): ?><span class="badge-s b-late"><?= h(t('appt_st_late')) ?></span><?php endif; ?>
            <?php if (!$a['reviewed_by'] && in_array($a['kind'], ['hearing', 'deadline'], true) && $a['status'] === 'scheduled'): ?><span class="badge-s b-amber"><?= h(t('appt_unreviewed')) ?></span><?php endif; ?>
            <?php if ($a['matter_ref']): ?><a class="ref small" dir="ltr" href="/matters/<?= (int)$a['matter_id'] ?>"><?= h($a['matter_ref']) ?></a><?php endif; ?>
            <span class="muted small">— <?= h(staff_name((int)$a['lead_id'])) ?><?= $a['court_id'] ? ' · ' . h(lookup_name((int)$a['court_id'], 'court')) : '' ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endforeach; ?>
  <?php if (!$shown): ?><?= empty_state(t('cal_nothing_range'), '', $canNew ? '/calendar/new' : null, t('appt_new'), 'calendar') ?><?php endif; ?>
</section>
