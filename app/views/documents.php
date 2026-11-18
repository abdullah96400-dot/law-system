<?php $q = fn(array $x) => '/documents?' . http_build_query(array_filter(array_merge($f, $x))); $canUp = can('documents', 'create') && !office_readonly(); $ar = lang() === 'ar'; $filtered = $f['q'] !== '' || $f['category'] || $f['archived']; ?>
<div class="page-head">
  <div><h1><?= h(t('mod_documents')) ?></h1><p class="small"><?= h(t('results_count', $total)) ?></p></div>
  <?php if ($canUp): ?><a class="btn" href="/documents/upload"><?= icon('upload') ?><?= h(t('doc_upload')) ?></a><?php endif; ?>
</div>
<section class="card">
  <form method="get" action="/documents" class="filters" role="search" data-auto>
    <div class="grow"><label for="q"><?= h(t('search')) ?></label><input id="q" name="q" type="search" value="<?= h($f['q']) ?>"></div>
    <div><label for="category"><?= h(t('doc_category')) ?></label><select id="category" name="category"><option value=""><?= h(t('all')) ?></option><?php foreach (lookups('doc_category') as $l): ?><option value="<?= (int)$l['id'] ?>"<?= $f['category'] === (int)$l['id'] ? ' selected' : '' ?>><?= h(lookup_label($l)) ?></option><?php endforeach; ?></select></div>
    <div class="check"><label><input type="checkbox" name="archived" value="1"<?= $f['archived'] ? ' checked' : '' ?>> <?= h(t('show_archived')) ?></label></div>
    <div><button class="btn"><?= h(t('search')) ?></button></div>
  </form>
  <?php if (!$list): ?>
    <?= $filtered ? empty_state(t('no_results'), t('no_results_help'), '/documents', t('clear_filters'), 'search') : empty_state(t('nothing_here'), t('docs_empty_help'), $canUp ? '/documents/upload' : null, $canUp ? t('doc_upload') : null, 'file') ?>
  <?php else: ?>
  <div class="table-wrap">
  <table class="stack" data-sortable>
    <caption class="sr"><?= h(t('mod_documents')) ?></caption>
    <thead><tr><th scope="col" data-sort><?= h(t('doc_title')) ?></th><th scope="col" data-sort><?= h(t('col_kind')) ?></th><th scope="col" data-sort><?= h(t('doc_category')) ?></th><th scope="col" data-sort><?= h(t('col_linked')) ?></th><th scope="col" data-sort="num"><?= h(t('doc_version')) ?></th><th scope="col" data-sort><?= h(t('updated')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($list as $x): ?>
      <tr>
        <td data-label="<?= h(t('doc_title')) ?>"><a href="/documents/<?= (int)$x['id'] ?>"><?= h($x['title']) ?></a><?= $x['source'] === 'client' ? ' <span class="tag">' . h(t('doc_from_client')) . '</span>' : '' ?><?= $x['portal_visible'] ? ' <span class="badge-s b-green">' . h(t('doc_in_portal')) . '</span>' : '' ?></td>
        <td data-label="<?= h(t('col_kind')) ?>"><span class="tag" dir="ltr"><?= h(strtoupper((string)$x['ext'])) ?></span><?php if ($x['size']): ?> <span class="muted small" dir="ltr"><?= h(human_size((int)$x['size'])) ?></span><?php endif; ?></td>
        <td data-label="<?= h(t('doc_category')) ?>"><?= h(lookup_name($x['category_id'] ? (int)$x['category_id'] : null, 'doc_category')) ?></td>
        <td data-label="<?= h(t('col_linked')) ?>"><?php if ($x['matter_ref']): ?><a href="/matters/<?= (int)$x['matter_id'] ?>" dir="ltr" class="ref"><?= h($x['matter_ref']) ?></a><?php elseif ($x['contact_id']): ?><a href="/clients/<?= (int)$x['contact_id'] ?>"><?= h($ar ? ($x['ct_ar'] ?: $x['ct_en']) : ($x['ct_en'] ?: $x['ct_ar'])) ?></a><?php else: ?>—<?php endif; ?></td>
        <td data-label="<?= h(t('doc_version')) ?>" data-v="<?= (int)$x['current_version'] ?>" dir="ltr">v<?= (int)$x['current_version'] ?></td>
        <td data-label="<?= h(t('updated')) ?>" data-v="<?= h($x['updated_at']) ?>"><span dir="ltr"><?= h(fmt_dt($x['updated_at'])) ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
  <?php if ($total > 30): $pages = (int)ceil($total / 30); ?>
  <nav class="pager" aria-label="<?= h(t('pages')) ?>">
    <?php if ($f['page'] > 1): ?><a href="<?= h($q(['page' => $f['page'] - 1])) ?>"><?= h(t('previous')) ?></a><?php endif; ?>
    <span><?= h(t('page_of', $f['page'], $pages)) ?></span>
    <?php if ($f['page'] < $pages): ?><a href="<?= h($q(['page' => $f['page'] + 1])) ?>"><?= h(t('next')) ?></a><?php endif; ?>
  </nav>
  <?php endif; ?>
</section>
