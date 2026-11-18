<section class="card">
  <div class="head-row">
    <h1><?= h(t('mod_conflicts')) ?></h1>
    <?php if (can('conflicts', 'create') && !office_readonly()): ?><a class="btn" href="/conflicts/new"><?= h(t('check_new')) ?></a><?php endif; ?>
  </div>
  <p class="muted"><?= h(t('checks_intro')) ?></p>
  <nav class="tabs" aria-label="<?= h(t('status')) ?>">
    <?php foreach (['' => 'all', 'pending' => 'chk_pending', 'cleared' => 'chk_cleared', 'cleared_cond' => 'chk_cleared_cond', 'conflict' => 'chk_conflict'] as $k => $lbl): ?>
      <a href="/conflicts<?= $k ? '?status=' . $k : '' ?>"<?= $status === $k ? ' aria-current="page"' : '' ?>><?= h(t($lbl)) ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="table-wrap">
  <table class="stack">
    <caption class="sr"><?= h(t('mod_conflicts')) ?></caption>
    <thead><tr><th scope="col"><?= h(t('ref')) ?></th><th scope="col"><?= h(t('check_subject')) ?></th><th scope="col"><?= h(t('status')) ?></th><th scope="col"><?= h(t('matches')) ?></th><th scope="col"><?= h(t('created')) ?></th></tr></thead>
    <tbody>
    <?php if (!$list): ?><tr><td colspan="5" class="muted"><?= h(t('empty')) ?></td></tr><?php endif; ?>
    <?php foreach ($list as $k): ?>
      <tr>
        <td data-label="<?= h(t('ref')) ?>"><a href="/conflicts/<?= (int)$k['id'] ?>" dir="ltr"><?= h($k['ref']) ?></a></td>
        <td data-label="<?= h(t('check_subject')) ?>"><?= h($k['subject']) ?></td>
        <td data-label="<?= h(t('status')) ?>"><span class="chk chk-<?= h($k['status']) ?>"><?= h(t('chk_' . $k['status'])) ?></span></td>
        <td data-label="<?= h(t('matches')) ?>"><?= (int)$k['hit_count'] ?></td>
        <td data-label="<?= h(t('created')) ?>"><span dir="ltr"><?= h(fmt_dt($k['created_at'])) ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php if ($pages > 1): ?>
  <nav class="pager" aria-label="<?= h(t('pages')) ?>">
    <?php if ($page > 1): ?><a href="/conflicts?<?= h(http_build_query(['status' => $status, 'page' => $page - 1])) ?>"><?= h(t('previous')) ?></a><?php endif; ?>
    <span><?= h(t('page_of', $page, $pages)) ?></span>
    <?php if ($page < $pages): ?><a href="/conflicts?<?= h(http_build_query(['status' => $status, 'page' => $page + 1])) ?>"><?= h(t('next')) ?></a><?php endif; ?>
  </nav>
  <?php endif; ?>
</section>
