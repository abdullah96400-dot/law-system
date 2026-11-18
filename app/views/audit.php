<section class="card">
  <div class="head-row"><div class="row"><span class="p-ico t-gold"><?= icon('clock') ?></span><h1><?= h(t('act_log')) ?></h1></div></div>
  <p class="muted"><?= h(t('audit_intro')) ?></p>
  <div class="row">
    <a class="btn small ghost" href="/audit?verify=1"><?= h(t('audit_verify')) ?></a>
    <?php if (can('audit', 'download')): ?><a class="btn small ghost" href="/audit/export"><?= h(t('export_csv')) ?></a><?php endif; ?>
  </div>
  <form method="get" action="/audit" class="filters act-filters mt" data-auto>
    <div><label for="who"><?= h(t('who')) ?></label><select id="who" name="who"><option value="0"><?= h(t('all')) ?></option><?php foreach (orows('SELECT id, full_name FROM staff WHERE office_id = :office ORDER BY full_name') as $s): ?><option value="<?= (int)$s['id'] ?>"<?= $af['who'] === (int)$s['id'] ? ' selected' : '' ?>><?= h($s['full_name']) ?></option><?php endforeach; ?></select></div>
    <div><label for="act"><?= h(t('what')) ?></label><select id="act" name="act"><option value=""><?= h(t('all')) ?></option><?php foreach (['login' => 'alog_login', 'matter' => 'alog_matter', 'appointment' => 'alog_appt', 'document' => 'alog_doc', 'invoice' => 'alog_invoice', 'payment' => 'alog_payment', 'delegation' => 'alog_deleg', 'portal' => 'alog_portal', 'trash' => 'alog_trash', 'staff' => 'alog_staff'] as $k => $lb): ?><option value="<?= $k ?>"<?= $af['act'] === $k ? ' selected' : '' ?>><?= h(t($lb)) ?></option><?php endforeach; ?></select></div>
    <div><label for="afrom"><?= h(t('from')) ?></label><input id="afrom" name="from" type="date" dir="ltr" value="<?= h($af['from']) ?>"></div>
    <div><label for="ato"><?= h(t('to')) ?></label><input id="ato" name="to" type="date" dir="ltr" value="<?= h($af['to']) ?>"></div>
    <div><button class="btn small"><?= h(t('show')) ?></button><?php if (array_filter($af)): ?> <a class="small" href="/audit"><?= h(t('clear_filters')) ?></a><?php endif; ?></div>
  </form>
  <?php if ($verify): ?>
    <div class="flash <?= $verify[0] ? 'ok' : 'err' ?>" role="status"><?= h($verify[0] ? t('audit_ok', $verify[1]) : t('audit_broken', $verify[2])) ?></div>
  <?php endif; ?>
  <div class="table-wrap">
  <table class="stack">
    <caption class="sr"><?= h(t('audit')) ?></caption>
    <thead><tr><th scope="col"><?= h(t('when')) ?></th><th scope="col"><?= h(t('who')) ?></th><th scope="col"><?= h(t('what')) ?></th><th scope="col"><?= h(t('details')) ?></th><th scope="col">IP</th></tr></thead>
    <tbody>
    <?php if (!$list): ?><tr><td colspan="5" class="muted"><?= h(t('empty')) ?></td></tr><?php endif; ?>
    <?php foreach ($list as $r): ?>
      <tr>
        <td data-label="<?= h(t('when')) ?>"><span dir="ltr"><?= h(fmt_dt($r['at'])) ?></span></td>
        <td data-label="<?= h(t('who')) ?>"><?= h($r['actor'] ?: '—') ?></td>
        <td data-label="<?= h(t('what')) ?>"><?= h(t('a_' . $r['action']) !== 'a_' . $r['action'] ? t('a_' . $r['action']) : $r['action']) ?></td>
        <td class="detail" dir="ltr" data-label="<?= h(t('details')) ?>"><?= h(trim(($r['entity'] ? $r['entity'] . ' #' . $r['entity_id'] . ' ' : '') . ($r['detail'] ?? ''))) ?></td>
        <td data-label="IP"><span dir="ltr"><?= h($r['ip']) ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php if ($pages > 1): ?>
  <nav class="pager" aria-label="<?= h(t('pages')) ?>">
    <?php if ($page > 1): ?><a href="/audit?<?= h(http_build_query(array_filter($af) + ['page' => $page - 1])) ?>"><?= h(t('newer')) ?></a><?php endif; ?>
    <span><?= h(t('page_of', $page, $pages)) ?></span>
    <?php if ($page < $pages): ?><a href="/audit?<?= h(http_build_query(array_filter($af) + ['page' => $page + 1])) ?>"><?= h(t('older')) ?></a><?php endif; ?>
  </nav>
  <?php endif; ?>
</section>
