<?php $canNew = can('matters', 'create') && !office_readonly(); $qs = fn(array $x) => '/matters?' . http_build_query(array_filter(array_merge($f, $x), fn($v) => $v !== '' && $v !== 0 && $v !== null)); $today = date('Y-m-d'); $ar = lang() === 'ar';
$filtered = $f['q'] !== '' || $f['status'] || $f['lead'] || $f['type'] || $f['state'] !== 'open'; ?>
<div class="page-head">
  <div><h1><?= h(t('mod_matters')) ?></h1><p class="small" role="status"><?= h(t('results_count', $total)) ?></p></div>
  <div class="row"><?php if ($canNew && import_allowed('matters', current_staff())): ?><a class="btn ghost" href="/import"><?= h(t('imp_title')) ?></a><?php endif; ?><?php if ($canNew): ?><a class="btn" href="/matters/new"><?= icon('plus') ?><?= h(t('matter_new')) ?></a><?php endif; ?></div>
</div>
<section class="card">
  <form method="get" action="/matters" class="filters" role="search" data-auto>
    <div class="grow"><label for="q"><?= h(t('search')) ?></label><input id="q" name="q" type="search" value="<?= h($f['q']) ?>" placeholder="<?= h(t('matter_search_ph')) ?>"></div>
    <div><label for="state"><?= h(t('matter_state')) ?></label><select id="state" name="state"><?php foreach (['open', 'closed', 'all'] as $s): ?><option value="<?= $s ?>"<?= $f['state'] === $s ? ' selected' : '' ?>><?= h(t('ms_' . $s)) ?></option><?php endforeach; ?></select></div>
    <div><label for="status"><?= h(t('status')) ?></label><select id="status" name="status"><option value=""><?= h(t('all')) ?></option><?php foreach (lookups('status') as $l): ?><option value="<?= (int)$l['id'] ?>"<?= $f['status'] === (int)$l['id'] ? ' selected' : '' ?>><?= h(lookup_label($l)) ?></option><?php endforeach; ?></select></div>
    <div><label for="lead"><?= h(t('matter_lead')) ?></label><select id="lead" name="lead"><option value=""><?= h(t('all')) ?></option><?php foreach (active_staff() as $s): ?><option value="<?= (int)$s['id'] ?>"<?= $f['lead'] === (int)$s['id'] ? ' selected' : '' ?>><?= h($s['full_name']) ?></option><?php endforeach; ?></select></div>
    <div><label for="type"><?= h(t('matter_type')) ?></label><select id="type" name="type"><option value=""><?= h(t('all')) ?></option><?php foreach (lookups('case_type') as $l): ?><option value="<?= (int)$l['id'] ?>"<?= $f['type'] === (int)$l['id'] ? ' selected' : '' ?>><?= h(lookup_label($l)) ?></option><?php endforeach; ?></select></div>
    <div><label for="sort"><?= h(t('sort_by')) ?></label><select id="sort" name="sort"><?php foreach (['next', 'hearing', 'new', 'updated', 'ref'] as $o): ?><option value="<?= $o ?>"<?= $f['sort'] === $o ? ' selected' : '' ?>><?= h(t('sort_' . $o)) ?></option><?php endforeach; ?></select></div>
    <div><button class="btn"><?= h(t('search')) ?></button></div>
  </form>
  <?php if (!$list): ?>
    <?= $filtered ? empty_state(t('no_results'), t('no_results_help'), '/matters', t('clear_filters'), 'search') : empty_state(t('matters_empty_t'), t('matters_empty_help'), can('conflicts', 'create') ? '/conflicts/new' : null, can('conflicts', 'create') ? t('check_new') : null, 'folder') ?>
    <p class="sr"><?= h($f['q'] !== '' ? t('no_results') : t('matters_empty')) ?></p>
  <?php else: ?>
  <div class="table-wrap">
  <table class="stack" data-sortable>
    <caption class="sr"><?= h(t('mod_matters')) ?></caption>
    <thead><tr><th scope="col" data-sort><?= h(t('ref')) ?></th><th scope="col" data-sort><?= h(t('matter_title')) ?></th><th scope="col" data-sort><?= h(t('ct_client')) ?></th><th scope="col" data-sort><?= h(t('col_type')) ?> / <?= h(t('col_court')) ?></th><th scope="col" data-sort><?= h(t('matter_lead')) ?></th><th scope="col" data-sort><?= h(t('status')) ?></th><th scope="col" data-sort><?= h(t('col_next_hearing')) ?></th><th scope="col" data-sort><?= h(t('matter_next')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($list as $m): $late = $m['next_due'] && $m['next_due'] < $today && !$m['closed_at']; ?>
      <tr>
        <td data-label="<?= h(t('ref')) ?>" data-v="<?= h($m['ref']) ?>"><a href="/matters/<?= (int)$m['id'] ?>" dir="ltr" class="ref"><?= h($m['ref']) ?></a><?= $m['restricted'] ? ' <span class="tag" title="' . h(t('restricted_note')) . '">' . icon('lock') . h(t('restricted')) . '</span>' : '' ?></td>
        <td data-label="<?= h(t('matter_title')) ?>"><a href="/matters/<?= (int)$m['id'] ?>"><?= h($m['title']) ?></a><?php if ($m['official_no']): ?><span class="sub" dir="ltr"><?= h($m['official_no']) ?></span><?php endif; ?></td>
        <td data-label="<?= h(t('ct_client')) ?>"><?= h($ar ? ($m['client_ar'] ?: $m['client_en']) : ($m['client_en'] ?: $m['client_ar'])) ?></td>
        <td data-label="<?= h(t('col_type')) ?>"><?= h($m['case_type_id'] ? lookup_name((int)$m['case_type_id'], 'case_type') : '—') ?><?php if ($m['court_id']): ?><span class="sub"><?= h(lookup_name((int)$m['court_id'], 'court')) ?></span><?php endif; ?></td>
        <td data-label="<?= h(t('matter_lead')) ?>"><?= h(staff_name((int)$m['lead_id'])) ?></td>
        <td data-label="<?= h(t('status')) ?>"><?= status_badge((int)$m['status_id']) ?></td>
        <td data-label="<?= h(t('col_next_hearing')) ?>" data-v="<?= h($m['next_hearing'] ?? '9999') ?>"><?= $m['next_hearing'] ? '<span dir="ltr">' . h(date('d/m/Y H:i', strtotime($m['next_hearing']))) . '</span>' : '<span class="muted">—</span>' ?></td>
        <td data-label="<?= h(t('matter_next')) ?>" data-v="<?= h($m['next_due'] ?? '9999') ?>"><?php if ($m['next_step']): ?><?= h($m['next_step']) ?><?php if ($m['next_due']): ?><span class="sub <?= $late ? 'late' : '' ?>" dir="ltr"><?= h(date('d/m/Y', strtotime($m['next_due']))) ?></span><?= $late ? ' <span class="tag late">' . h(t('overdue')) . '</span>' : '' ?><?php endif; ?><?php else: ?><span class="muted">—</span><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
  <?php if ($pages > 1): ?>
  <nav class="pager" aria-label="<?= h(t('pages')) ?>">
    <?php if ($f['page'] > 1): ?><a href="<?= h($qs(['page' => $f['page'] - 1])) ?>"><?= h(t('previous')) ?></a><?php endif; ?>
    <span><?= h(t('page_of', $f['page'], $pages)) ?></span>
    <?php if ($f['page'] < $pages): ?><a href="<?= h($qs(['page' => $f['page'] + 1])) ?>"><?= h(t('next')) ?></a><?php endif; ?>
  </nav>
  <?php endif; ?>
</section>
