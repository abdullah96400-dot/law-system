<?php $me = current_staff(); $all = sr_can_all($me); $qs = fn(array $x) => '/requests?' . http_build_query(array_filter(array_merge($f, ['page' => ''], $x), fn($v) => $v !== '' && $v !== null)); ?>
<section class="card">
  <div class="head-row"><div><h1><?= h(t('sr_title')) ?></h1><p class="muted"><?= h(t($all ? 'sr_intro' : 'sr_intro_mine')) ?></p></div>
    <?php if (site_can_manage($me)): ?><a class="btn ghost" href="/site"><?= h(t('site_admin')) ?></a><?php endif; ?></div>
  <nav class="chips" aria-label="<?= h(t('status')) ?>">
    <a href="<?= h($qs(['status' => ''])) ?>"<?= $f['status'] === '' ? ' aria-current="true"' : '' ?>><?= h(t('sr_open')) ?> <b><?= array_sum($counts) - $counts['closed'] ?></b></a>
    <?php foreach (SITE_STATUSES as $st): ?><a href="<?= h($qs(['status' => $st])) ?>"<?= $f['status'] === $st ? ' aria-current="true"' : '' ?>><?= h(t('sr_st_' . $st)) ?> <b><?= (int)$counts[$st] ?></b></a><?php endforeach; ?>
  </nav>
  <form method="get" action="/requests" class="filters">
    <input type="hidden" name="status" value="<?= h($f['status']) ?>">
    <div><label for="service"><?= h(t('sr_service')) ?></label><select id="service" name="service"><option value=""><?= h(t('all')) ?></option>
      <?php foreach ($services as $x): ?><option value="<?= h($x['key']) ?>"<?= $f['service'] === $x['key'] ? ' selected' : '' ?>><?= h(site_t($x, 'title')) ?></option><?php endforeach; ?></select></div>
    <?php if ($all): ?><div><label for="assignee"><?= h(t('sr_assignee')) ?></label><select id="assignee" name="assignee"><option value=""><?= h(t('all')) ?></option><option value="none"<?= $f['assignee'] === 'none' ? ' selected' : '' ?>><?= h(t('sr_unassigned')) ?></option>
      <?php foreach ($staff as $s): ?><option value="<?= (int)$s['id'] ?>"<?= $f['assignee'] === (string)$s['id'] ? ' selected' : '' ?>><?= h($s['full_name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
    <div><button class="btn"><?= h(t('filter')) ?></button></div>
  </form>
  <p class="muted small" role="status"><?= h(t('results_count', $total)) ?></p>
  <div class="table-wrap"><table class="stack">
    <caption class="sr"><?= h(t('sr_title')) ?></caption>
    <thead><tr><th scope="col"><?= h(t('sr_ref')) ?></th><th scope="col"><?= h(t('sr_name')) ?></th><th scope="col"><?= h(t('sr_service')) ?></th><th scope="col"><?= h(t('status')) ?></th><th scope="col"><?= h(t('sr_assignee')) ?></th><th scope="col"><?= h(t('sr_received')) ?></th></tr></thead>
    <tbody>
    <?php if (!$list): ?><tr><td colspan="6" class="muted"><?= h(t('sr_empty')) ?></td></tr><?php endif; ?>
    <?php foreach ($list as $r): ?><tr>
      <td data-label="<?= h(t('sr_ref')) ?>"><a href="/requests/<?= (int)$r['id'] ?>" dir="ltr"><b><?= h($r['ref']) ?></b></a></td>
      <td data-label="<?= h(t('sr_name')) ?>"><?= h($r['name']) ?></td>
      <td data-label="<?= h(t('sr_service')) ?>"><?= h($r['service_title']) ?></td>
      <td data-label="<?= h(t('status')) ?>"><span class="ist sr-<?= h($r['status']) ?>"><?= h(t('sr_st_' . $r['status'])) ?></span></td>
      <td data-label="<?= h(t('sr_assignee')) ?>"><?= h($r['assignee_id'] ? staff_name((int)$r['assignee_id']) : t('sr_unassigned')) ?></td>
      <td data-label="<?= h(t('sr_received')) ?>"><span dir="ltr"><?= h(fmt_dt($r['created_at'])) ?></span></td>
    </tr><?php endforeach; ?>
    </tbody></table></div>
  <?php $pages = (int)ceil($total / 30); if ($pages > 1): ?><nav class="pager"><?php for ($i = 1; $i <= $pages; $i++): ?><a href="<?= h($qs(['page' => $i])) ?>"<?= $i === max(1, $f['page']) ? ' aria-current="page"' : '' ?>><?= $i ?></a><?php endfor; ?></nav><?php endif; ?>
</section>
