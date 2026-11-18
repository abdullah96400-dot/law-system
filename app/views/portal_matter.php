<?php $preview = !empty($preview); $live = !$preview && current_office()['status'] === 'active';
$acts = portal_actions((int)$m['id'], false); $judg = portal_actions((int)$m['id'], true); $sts = $preview ? array_values(array_filter(orows('SELECT * FROM fee_agreements WHERE office_id = :office AND matter_id = :m AND client_id = :c', ['m' => $m['id'], 'c' => $u['contact_id']]), fn($a) => statement_shared((int)$a['id']))) : portal_statements($u, (int)$m['id']);
$ups = portal_updates((int)$m['id']); $hs = portal_hearings((int)$m['id']); $docs = portal_documents((int)$m['id']); $invs = portal_invoices((int)$m['id'], (int)$u['contact_id']);
$reqs = portal_requests((int)$m['id']); $msgs = matter_messages((int)$m['id']); $open = array_filter($reqs, fn($r) => $r['status'] === 'open'); ?>
<section class="card">
  <h1><?= h($m['title']) ?></h1>
  <p><span class="chk big <?= $m['closed_at'] ? 'chk-closed' : 'chk-cleared' ?>"><?= h(lookup_name((int)$m['status_id'], 'status')) ?></span></p>
  <?php if ($err): ?><div class="flash err" role="alert"><?= h(t($err)) ?></div><?php endif; ?>
</section>

<section class="card">
  <h2><?= h(t('portal_updates')) ?></h2>
  <?php if (!$ups): ?><p class="muted"><?= h(t('portal_no_updates')) ?></p><?php endif; ?>
  <?php foreach ($ups as $x): ?><article class="update"><div class="when" dir="ltr"><?= h(date('d/m/Y', strtotime($x['published_at']))) ?></div><p class="pre"><?= h($x['body']) ?></p></article><?php endforeach; ?>
</section>

<?php if ($hs): ?>
<section class="card">
  <h2><?= h(t('portal_dates')) ?></h2>
  <ul class="plain one"><?php foreach ($hs as $a): ?><li class="appt"><span class="time" dir="ltr"><?= h(date('d/m/Y H:i', strtotime($a['starts_at']))) ?></span> <?= h(t('ak_' . $a['kind'])) ?><?= $a['court_id'] ? ' — ' . h(lookup_name((int)$a['court_id'], 'court')) : '' ?><?= $a['circuit'] ? ' · ' . h($a['circuit']) : '' ?><?= $a['status'] !== 'scheduled' ? ' <span class="tag">' . h(t('as_' . $a['status'])) . '</span>' : '' ?></li><?php endforeach; ?></ul>
  <p class="muted small"><?= h(t('portal_dates_note')) ?></p>
</section>
<?php endif; ?>

<?php foreach ([['judgments', $judg], ['actions', $acts]] as [$sk, $list]): if (!$list) continue; ?>
<section class="card">
  <h2><?= h(t('ps_' . $sk)) ?></h2>
  <ol class="act-list"><?php foreach ($list as $x): ?><li class="act"><div class="act-when" dir="ltr"><?= h(date('d/m/Y', strtotime($x['action_on']))) ?></div><div class="act-body"><div class="row"><span class="tag k-<?= h($x['kind']) ?>"><?= h(t('acts_' . $x['kind'])) ?></span><b><?= h($x['title']) ?></b></div><?php if ($x['details']): ?><p class="pre small"><?= h($x['details']) ?></p><?php endif; ?></div></li><?php endforeach; ?></ol>
</section>
<?php endforeach; ?>

<section class="card">
  <h2><?= h(t('mod_documents')) ?></h2>
  <?php if (!$docs): ?><p class="muted"><?= h(t('docs_empty')) ?></p><?php endif; ?>
  <ul class="plain one"><?php foreach ($docs as $d): ?><li><a href="<?= h($base) ?>/d/<?= (int)$d['id'] ?>"><?= h($d['title']) ?></a><?= $d['source'] === 'client' ? ' <span class="tag">' . h(t('portal_sent_by_you')) . '</span>' : '' ?></li><?php endforeach; ?></ul>
  <?php if ($open): ?><h3 class="mt"><?= h(t('portal_requested')) ?></h3><?php endif; ?>
  <?php foreach ($reqs as $r): ?>
    <div class="req"><b><?= h($r['title']) ?></b> <span class="tag"><?= h(t('dr_' . $r['status'])) ?></span>
      <?php if ($r['status'] === 'open' && $live): ?>
        <form method="post" action="<?= h($base) ?>/m/<?= (int)$m['id'] ?>/upload" enctype="multipart/form-data" class="row"><?= csrf_field() ?><input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
          <label class="sr" for="f-<?= (int)$r['id'] ?>"><?= h(t('doc_file')) ?></label><input id="f-<?= (int)$r['id'] ?>" name="file" type="file" required class="auto"><button class="btn small"><?= h(t('portal_send_file')) ?></button></form>
      <?php endif; ?></div>
  <?php endforeach; ?>
  <?php if ($live): ?>
  <details class="mt"><summary><?= h(t('portal_send_other')) ?></summary>
    <form method="post" action="<?= h($base) ?>/m/<?= (int)$m['id'] ?>/upload" enctype="multipart/form-data" class="row"><?= csrf_field() ?>
      <label class="sr" for="f-other"><?= h(t('doc_file')) ?></label><input id="f-other" name="file" type="file" required class="auto"><button class="btn small"><?= h(t('portal_send_file')) ?></button></form>
    <p class="muted small"><?= h(t('doc_file_help', implode(', ', array_keys(DOC_TYPES)), (int)cfg('max_upload_mb', 20))) ?></p></details>
  <?php endif; ?>
</section>

<?php if ($sts): ?>
<section class="card">
  <h2><?= h(t('statements')) ?></h2>
  <?php foreach ($sts as $a): $sm = agreement_summary($a); ?><a class="fee-card" href="<?= h($base) ?>/s/<?= (int)$a['id'] ?>"><div class="fee-nums"><span><?= h(t('st_total')) ?> <b><?= h(money($sm['total'])) ?></b></span><span><?= h(t('st_paid')) ?> <b class="pos"><?= h(money($sm['paid'])) ?></b></span><span><?= h(t('st_remaining')) ?> <b><?= h(money($sm['remaining'])) ?></b></span></div><div class="bar sm"><i class="w<?= (int)(round($sm['pct'] / 5) * 5) ?>"></i></div></a><?php endforeach; ?>
</section>
<?php endif; ?>

<?php if ($invs): ?>
<section class="card">
  <h2><?= h(t('invoices')) ?></h2>
  <ul class="plain one"><?php foreach ($invs as $i): $st = invoice_state($i); ?><li><a href="<?= h($base) ?>/i/<?= (int)$i['id'] ?>" dir="ltr"><?= h($i['number']) ?></a> · <?= h(money((int)$i['total'])) ?> · <span class="inv inv-<?= h($st) ?>"><?= h(t('is_' . $st)) ?></span><?= $st !== 'paid' ? ' · ' . h(t('inv_balance')) . ': ' . h(money((int)$i['total'] - (int)$i['paid'])) : '' ?></li><?php endforeach; ?></ul>
  <p class="muted small"><?= h(t('portal_pay_note')) ?></p>
</section>
<?php endif; ?>

<section class="card" id="messages">
  <h2><?= h(t('portal_messages')) ?></h2>
  <div class="thread">
    <?php if (!$msgs): ?><p class="muted"><?= h(t('portal_no_messages')) ?></p><?php endif; ?>
    <?php foreach ($msgs as $x): ?><div class="msg <?= $x['portal_user_id'] ? 'mine' : 'theirs' ?>"><div class="when"><span dir="ltr"><?= h(fmt_dt($x['created_at'])) ?></span> · <?= h($x['portal_user_id'] ? t('portal_you') : office_name()) ?></div><p class="pre"><?= h($x['body']) ?></p></div><?php endforeach; ?>
  </div>
  <?php if ($live): ?>
  <form method="post" action="<?= h($base) ?>/m/<?= (int)$m['id'] ?>/message"><?= csrf_field() ?>
    <label for="body"><?= h(t('portal_write')) ?></label><textarea id="body" name="body" rows="3" required maxlength="4000"></textarea>
    <button class="btn"><?= h(t('portal_send')) ?></button></form>
  <?php endif; ?>
</section>
