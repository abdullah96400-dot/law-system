<?php $c = contact_get((int)$u['contact_id']); $n = $ov['next']; $up = $ov['update']; $ms = $ov['matters'];
$mt = []; foreach ($ms as $x) $mt[(int)$x['id']] = $x; ?>
<section class="p-hello">
  <h1><?= h(t('portal_hello', $c ? contact_label($c) : '')) ?></h1>
  <p class="muted"><?= h(t('portal_hello_sub', office_name($o))) ?></p>
</section>
<div class="p-sum">
  <a class="p-card" href="<?= h($base) ?>/#cases"><span class="p-ico t-ink"><?= icon('folder') ?></span><span><?= h(t('portal_my_cases')) ?></span><b><?= count($ms) ?></b></a>
  <a class="p-card" href="<?= h($base) ?>/hearings"><span class="p-ico t-petrol"><?= icon('calendar') ?></span><span><?= h(t('portal_next_hearing')) ?></span>
    <b><?= $n ? '<span dir="ltr">' . h(date('d/m/Y', strtotime($n['starts_at']))) . '</span>' : '—' ?></b><?php if ($n): ?><small><span dir="ltr"><?= h(date('H:i', strtotime($n['starts_at']))) ?></span><?= $n['court_id'] ? ' · ' . h(lookup_name((int)$n['court_id'], 'court')) : '' ?></small><?php endif; ?></a>
  <a class="p-card" href="<?= h($base) ?>/payments"><span class="p-ico t-gold"><?= icon('wallet') ?></span><span><?= h(t('portal_amount_due')) ?></span><b class="<?= $ov['due'] ? 'due' : '' ?>"><?= h(money($ov['due'])) ?></b><small><?= h(t('portal_amount_due_def')) ?></small></a>
</div>
<?php if ($up): ?>
<section class="card p-update">
  <div class="head-row"><h2><?= h(t('portal_last_approved')) ?></h2><span class="muted small" dir="ltr"><?= h(date('d/m/Y', strtotime($up['published_at']))) ?></span></div>
  <p class="pre"><?= h(mb_strimwidth($up['body'], 0, 420, '…')) ?></p>
  <?php if (isset($mt[(int)$up['matter_id']])): ?><a class="small" href="<?= h($base) ?>/m/<?= (int)$up['matter_id'] ?>"><?= h($mt[(int)$up['matter_id']]['title']) ?></a><?php endif; ?>
</section>
<?php endif; ?>
<nav class="p-tiles" aria-label="<?= h(t('portal_sections')) ?>">
  <?php foreach ([['hearings', 'calendar', 't-petrol', $ov['counts']['hearings']], ['actions', 'tasks', 't-ink', $ov['counts']['actions']], ['judgments', 'gavel', 't-gold', $ov['counts']['judgments']], ['documents', 'file', 't-blue', $ov['counts']['documents']], ['payments', 'receipt', 't-green', null]] as [$k, $ic, $tone, $cnt]): ?>
    <a class="p-tile" href="<?= h($base) ?>/<?= $k ?>"><span class="p-ico <?= $tone ?>"><?= icon($ic) ?></span><b><?= h(t('ps_' . $k)) ?></b><?= $cnt ? '<span class="n-pill">' . (int)$cnt . '</span>' : '' ?></a>
  <?php endforeach; ?>
</nav>
<section class="card" id="cases">
  <h2><?= h(t('portal_my_cases')) ?></h2>
  <?php if (!$ms): ?><?= empty_state(t('portal_no_cases'), t('portal_no_cases_help'), null, null, 'folder') ?><?php endif; ?>
  <?php foreach ($ms as $m): $nx = orow("SELECT starts_at FROM appointments WHERE office_id = :office AND matter_id = :m AND portal_visible = 1 AND status = 'scheduled' AND starts_at >= :now ORDER BY starts_at LIMIT 1", ['m' => $m['id'], 'now' => now()]);
    $unread = (int)orow('SELECT COUNT(*) n FROM portal_messages WHERE office_id = :office AND matter_id = :m AND staff_id IS NOT NULL AND read_by_client_at IS NULL', ['m' => $m['id']])['n']; ?>
    <a class="p-case" href="<?= h($base) ?>/m/<?= (int)$m['id'] ?>">
      <span class="p-case-t"><b><?= h($m['title']) ?></b><span class="chk <?= $m['closed_at'] ? 'chk-closed' : 'chk-cleared' ?>"><?= h(lookup_name((int)$m['status_id'], 'status')) ?></span></span>
      <span class="muted small"><?= $m['official_no'] ? h(t('matter_official_no')) . ': <span dir="ltr">' . h($m['official_no']) . '</span> · ' : '' ?><?= $nx ? h(t('portal_next_date', date('d/m/Y H:i', strtotime($nx['starts_at'])))) : h(t('portal_no_next')) ?></span>
      <?php if ($unread): ?><span class="tag"><?= h(t('portal_unread', $unread)) ?></span><?php endif; ?>
    </a>
  <?php endforeach; ?>
</section>
