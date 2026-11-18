<?php $canNew = can('clients', 'create') && !office_readonly(); $qs = fn(array $x) => '/clients?' . http_build_query(array_filter(array_merge($f, $x), fn($v) => $v !== '' && $v !== false && $v !== null)); ?>
<section class="card">
  <div class="head-row">
    <h1><?= h(t('mod_clients')) ?></h1>
    <div class="row"><?php if ($canNew && import_allowed('contacts', current_staff())): ?><a class="btn ghost" href="/import"><?= h(t('imp_title')) ?></a><?php endif; ?><?php if ($canNew): ?><a class="btn" href="/clients/new"><?= h(t('contact_new')) ?></a><?php endif; ?></div>
  </div>
  <form method="get" action="/clients" class="filters" role="search">
    <div class="grow"><label for="q"><?= h(t('search')) ?></label><input id="q" name="q" type="search" value="<?= h($f['q']) ?>" placeholder="<?= h(t('contact_search_ph')) ?>"></div>
    <div><label for="type"><?= h(t('contact_type')) ?></label>
      <select id="type" name="type"><option value=""><?= h(t('all')) ?></option><option value="client"<?= $f['type'] === 'client' ? ' selected' : '' ?>><?= h(t('ct_client')) ?></option><option value="party"<?= $f['type'] === 'party' ? ' selected' : '' ?>><?= h(t('ct_party')) ?></option></select></div>
    <div><label for="kind"><?= h(t('contact_kind')) ?></label>
      <select id="kind" name="kind"><option value=""><?= h(t('all')) ?></option><?php foreach (CONTACT_KINDS as $k): ?><option value="<?= $k ?>"<?= $f['kind'] === $k ? ' selected' : '' ?>><?= h(t('kind_' . $k)) ?></option><?php endforeach; ?></select></div>
    <div class="check"><label><input type="checkbox" name="archived" value="1"<?= $f['archived'] ? ' checked' : '' ?>> <?= h(t('show_archived')) ?></label></div>
    <div><button class="btn"><?= h(t('search')) ?></button></div>
  </form>
  <p class="muted small" role="status"><?= h(t('results_count', $total)) ?></p>
  <div class="table-wrap">
  <table class="stack">
    <caption class="sr"><?= h(t('mod_clients')) ?></caption>
    <thead><tr><th scope="col"><?= h(t('name')) ?></th><th scope="col"><?= h(t('contact_type')) ?></th><th scope="col"><?= h(t('contact_kind')) ?></th><th scope="col"><?= h(t('phone')) ?></th><th scope="col"><?= h(t('email')) ?></th><th scope="col"><?= h(t('updated')) ?></th></tr></thead>
    <tbody>
    <?php if (!$list): ?><tr><td colspan="6" class="muted"><?= h($f['q'] !== '' ? t('no_results') : t('contacts_empty')) ?></td></tr><?php endif; ?>
    <?php foreach ($list as $c): ?>
      <tr>
        <td data-label="<?= h(t('name')) ?>"><a href="/clients/<?= (int)$c['id'] ?>"><?= h(contact_label($c)) ?></a><?php $other = lang() === 'ar' ? $c['name_en'] : $c['name_ar']; if ($other && $other !== contact_label($c)): ?><br><span class="muted small"><?= h($other) ?></span><?php endif; ?></td>
        <td data-label="<?= h(t('contact_type')) ?>"><?= h(t($c['is_client'] ? 'ct_client' : 'ct_party')) ?></td>
        <td data-label="<?= h(t('contact_kind')) ?>"><?= h(t('kind_' . $c['kind'])) ?></td>
        <td data-label="<?= h(t('phone')) ?>"><span dir="ltr"><?= h($c['phone'] ?: '—') ?></span></td>
        <td data-label="<?= h(t('email')) ?>"><span dir="ltr"><?= h($c['email'] ?: '—') ?></span></td>
        <td data-label="<?= h(t('updated')) ?>"><span dir="ltr"><?= h(fmt_dt($c['updated_at'])) ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php if ($pages > 1): ?>
  <nav class="pager" aria-label="<?= h(t('pages')) ?>">
    <?php if ($f['page'] > 1): ?><a href="<?= h($qs(['page' => $f['page'] - 1])) ?>"><?= h(t('previous')) ?></a><?php endif; ?>
    <span><?= h(t('page_of', $f['page'], $pages)) ?></span>
    <?php if ($f['page'] < $pages): ?><a href="<?= h($qs(['page' => $f['page'] + 1])) ?>"><?= h(t('next')) ?></a><?php endif; ?>
  </nav>
  <?php endif; ?>
</section>
