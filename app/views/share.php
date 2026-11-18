<section class="card narrow">
  <h1><?= h(t('share_page_title')) ?></h1>
  <p><?= h(t('share_from', office_name($office))) ?></p>
  <dl class="facts">
    <dt><?= h(t('doc_title')) ?></dt><dd><?= h($sh['doc']['title']) ?></dd>
    <dt><?= h(t('doc_file')) ?></dt><dd><span dir="ltr"><?= h(strtoupper($v['ext'])) ?></span> · <?= h(human_size((int)$v['size'])) ?></dd>
    <dt><?= h(t('share_valid_until')) ?></dt><dd dir="ltr"><?= h(date('d/m/Y H:i', strtotime($sh['expires_at']))) ?></dd>
  </dl>
  <a class="btn" href="/s/<?= h($tok) ?>/download"><?= h(t('download')) ?></a>
  <p class="muted small mt"><?= h(t('share_page_note')) ?></p>
</section>
