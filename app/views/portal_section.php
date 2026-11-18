<?php $ms = portal_matters($u); $mt = []; foreach ($ms as $x) $mt[(int)$x['id']] = $x; $ids = array_keys($mt); $in = $ids ? implode(',', $ids) : '0'; ?>
<p class="muted small"><a href="<?= h($base) ?>/"><?= h(t('portal_home')) ?></a></p>
<h1 class="p-title"><?= h(t('ps_' . $sec)) ?></h1>
<?php if ($sec === 'hearings'):
  $up = orows("SELECT id, matter_id, kind, title, starts_at, court_id, circuit, location, status FROM appointments WHERE office_id = :office AND matter_id IN ($in) AND portal_visible = 1 AND status = 'scheduled' AND starts_at >= :now ORDER BY starts_at", ['now' => now()]);
  $past = orows("SELECT id, matter_id, kind, title, starts_at, court_id, circuit, status FROM appointments WHERE office_id = :office AND matter_id IN ($in) AND portal_visible = 1 AND status <> 'cancelled' AND starts_at < :now ORDER BY starts_at DESC LIMIT 30", ['now' => now()]); ?>
  <section class="card"><h2><?= h(t('portal_upcoming')) ?></h2>
    <?php if (!$up): ?><?= empty_state(t('portal_no_upcoming'), '', null, null, 'calendar') ?><?php endif; ?>
    <?php foreach ($up as $a): ?><div class="p-hearing"><div class="p-date"><b dir="ltr"><?= h(date('d', strtotime($a['starts_at']))) ?></b><span><?= h(t('mon_' . (int)date('n', strtotime($a['starts_at'])))) ?></span></div>
      <div><b><?= h(t('ak_' . $a['kind'])) ?> · <span dir="ltr"><?= h(date('H:i', strtotime($a['starts_at']))) ?></span></b><span class="muted small"><?= $a['court_id'] ? h(lookup_name((int)$a['court_id'], 'court')) : '' ?><?= $a['circuit'] ? ' · ' . h($a['circuit']) : '' ?><?= $a['location'] ? ' · ' . h($a['location']) : '' ?></span><a class="small" href="<?= h($base) ?>/m/<?= (int)$a['matter_id'] ?>"><?= h($mt[(int)$a['matter_id']]['title']) ?></a></div></div><?php endforeach; ?>
    <p class="muted small"><?= h(t('portal_dates_note')) ?></p></section>
  <?php if ($past): ?><section class="card"><h2><?= h(t('portal_past')) ?></h2><ul class="plain one"><?php foreach ($past as $a): ?><li><span dir="ltr" class="muted"><?= h(date('d/m/Y', strtotime($a['starts_at']))) ?></span> <?= h(t('ak_' . $a['kind'])) ?> — <?= h($mt[(int)$a['matter_id']]['title']) ?><?= $a['status'] !== 'scheduled' ? ' <span class="tag">' . h(t('as_' . $a['status'])) . '</span>' : '' ?></li><?php endforeach; ?></ul></section><?php endif; ?>
<?php elseif ($sec === 'actions' || $sec === 'judgments'): $any = false; ?>
  <section class="card">
  <?php foreach ($ms as $m): $acts = portal_actions((int)$m['id'], $sec === 'judgments'); if (!$acts) continue; $any = true; ?>
    <h2 class="p-file"><a href="<?= h($base) ?>/m/<?= (int)$m['id'] ?>"><?= h($m['title']) ?></a></h2>
    <ol class="act-list"><?php foreach ($acts as $x): ?><li class="act"><div class="act-when" dir="ltr"><?= h(date('d/m/Y', strtotime($x['action_on']))) ?></div><div class="act-body"><div class="row"><span class="tag k-<?= h($x['kind']) ?>"><?= h(t('acts_' . $x['kind'])) ?></span><b><?= h($x['title']) ?></b></div><?php if ($x['details']): ?><p class="pre small"><?= h($x['details']) ?></p><?php endif; ?></div></li><?php endforeach; ?></ol>
  <?php endforeach; ?>
  <?php if (!$any): ?><?= empty_state(t($sec === 'judgments' ? 'portal_no_judgments' : 'portal_no_actions'), t('portal_shared_only'), null, null, $sec === 'judgments' ? 'gavel' : 'tasks') ?><?php endif; ?>
  </section>
<?php elseif ($sec === 'documents'): $docs = orows("SELECT * FROM documents WHERE office_id = :office AND matter_id IN ($in) AND portal_visible = 1 AND archived = 0 ORDER BY id DESC"); ?>
  <section class="card">
    <?php if (!$docs): ?><?= empty_state(t('docs_empty'), t('portal_shared_only'), null, null, 'file') ?><?php endif; ?>
    <ul class="plain one p-docs"><?php foreach ($docs as $d): $v = doc_version($d); ?><li><a href="<?= h($base) ?>/d/<?= (int)$d['id'] ?>"><?= icon('file') ?> <?= h($d['title']) ?></a> <span class="muted small"><?= h(strtoupper((string)($v['ext'] ?? ''))) ?> · <?= h($mt[(int)$d['matter_id']]['title']) ?></span><?= $d['source'] === 'client' ? ' <span class="tag">' . h(t('portal_sent_by_you')) . '</span>' : '' ?></li><?php endforeach; ?></ul>
  </section>
<?php else: $sts = portal_statements($u); $invs = orows("SELECT * FROM invoices WHERE office_id = :office AND client_id = :c AND portal_visible = 1 AND status = 'issued' AND matter_id IN ($in) ORDER BY id DESC", ['c' => $u['contact_id']]); $nts = portal_notices($u); ?>
  <section class="card"><h2><?= h(t('statements')) ?></h2>
    <?php if (!$sts): ?><p class="muted"><?= h(t('portal_no_statements')) ?></p><?php endif; ?>
    <?php foreach ($sts as $a): $sm = agreement_summary($a); ?>
      <a class="fee-card" href="<?= h($base) ?>/s/<?= (int)$a['id'] ?>"><div class="row"><b><?= h($a['matter_title']) ?></b><?= $sm['overdue'] ? '<span class="ist ist-overdue">' . h(t('fee_overdue')) . ' ' . h(money($sm['overdue'])) . '</span>' : ($sm['due_now'] ? '<span class="ist ist-due">' . h(t('fee_due_now')) . ' ' . h(money($sm['due_now'])) . '</span>' : '') ?></div>
        <div class="fee-nums"><span><?= h(t('st_total')) ?> <b><?= h(money($sm['total'])) ?></b></span><span><?= h(t('st_paid')) ?> <b class="pos"><?= h(money($sm['paid'])) ?></b></span><span><?= h(t('st_remaining')) ?> <b><?= h(money($sm['remaining'])) ?></b></span></div>
        <div class="bar sm"><i class="w<?= (int)(round($sm['pct'] / 5) * 5) ?>"></i></div></a>
    <?php endforeach; ?></section>
  <?php if ($nts): ?><section class="card"><h2><?= h(t('portal_notices')) ?></h2><ul class="plain one"><?php foreach ($nts as $x): ?><li><b><?= h(money((int)$x['amount'])) ?></b> · <?= h(t('notice_due_on')) ?> <span dir="ltr"><?= $x['due_on'] ? h(date('d/m/Y', strtotime($x['due_on']))) : '—' ?></span> <span class="muted small">— <?= h($mt[(int)$x['matter_id']]['title']) ?></span></li><?php endforeach; ?></ul></section><?php endif; ?>
  <?php if ($invs): ?><section class="card"><h2><?= h(t('invoices')) ?></h2><ul class="plain one"><?php foreach ($invs as $i): $st = invoice_state($i); ?><li><a href="<?= h($base) ?>/i/<?= (int)$i['id'] ?>" dir="ltr"><?= h($i['number']) ?></a> · <?= h(money((int)$i['total'])) ?> · <span class="inv inv-<?= h($st) ?>"><?= h(t('is_' . $st)) ?></span><?= $st !== 'paid' ? ' · ' . h(t('inv_balance')) . ': ' . h(money((int)$i['total'] - (int)$i['paid'])) : '' ?></li><?php endforeach; ?></ul></section><?php endif; ?>
  <p class="muted small"><?= h(t('portal_pay_note')) ?></p>
<?php endif; ?>
