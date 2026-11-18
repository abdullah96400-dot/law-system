<?php $me = current_staff(); $ok = $b['last_ok']; $fresh = $ok && strtotime($ok['at']) > time() - 36 * 3600; ?>
<div class="page-head"><div><h1><?= h(t('bk_title')) ?></h1><p><?= h(t('bk_help')) ?></p></div>
  <?php if ($me['role'] === 'partner' && !office_readonly()): ?><form method="post" action="/settings/backups/run"><?= csrf_field() ?><button class="btn small"><?= h(t('bk_run_now')) ?></button></form><?php endif; ?></div>
<section class="card">
  <div class="kpis compact">
    <div class="kpi <?= $fresh ? 'pos' : 'warn' ?>"><span><?= h(t('bk_last_ok')) ?></span><b dir="ltr"><?= $ok ? h(fmt_dt($ok['at'])) : '—' ?></b><small><?= h(t($fresh ? 'bk_fresh' : 'bk_stale')) ?></small></div>
    <div class="kpi"><span><?= h(t('bk_size')) ?></span><b><?= $ok && $ok['size'] ? h(human_size((int)$ok['size'])) : '—' ?></b></div>
    <div class="kpi"><span><?= h(t('bk_kept')) ?></span><b><?= (int)$b['kept'] ?></b><small><?= h(t('bk_kept_def')) ?></small></div>
  </div>
  <?php if ($b['last'] && !$b['last']['ok']): ?><div class="flash err mt" role="alert"><?= h(t('bk_last_failed', fmt_dt($b['last']['at']))) ?></div><?php endif; ?>
  <h2 class="mt"><?= h(t('bk_runs')) ?></h2>
  <?php if (!$b['runs']): ?><p class="muted"><?= h(t('bk_none')) ?></p><?php endif; ?>
  <ul class="plain one small"><?php foreach ($b['runs'] as $r): ?><li><span dir="ltr"><?= h(fmt_dt($r['at'])) ?></span> · <?= h(t('bk_kind_' . $r['kind'])) ?> · <?= $r['ok'] ? '<span class="ist ist-paid">' . h(t('bk_ok')) . '</span>' : '<span class="ist ist-overdue">' . h(t('bk_fail')) . '</span>' ?><?= $r['size'] ? ' · ' . h(human_size((int)$r['size'])) : '' ?></li><?php endforeach; ?></ul>
  <p class="note small mt"><?= h(t('bk_note')) ?></p>
</section>
