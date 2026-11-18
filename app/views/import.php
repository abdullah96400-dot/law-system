<?php $kinds = array_values(array_filter(['contacts', 'matters'], fn($k) => import_allowed($k, current_staff()))); $ro = office_readonly(); ?>
<section class="card">
  <h1><?= h(t('imp_title')) ?></h1>
  <p class="muted"><?= h(t('imp_intro')) ?></p>
  <?php if ($err): ?><div class="flash err" role="alert"><?= h(t($err)) ?></div><?php endif; ?>
</section>
<?php if ($checked !== null):
  $bad = array_filter($checked, fn($c) => $c['error']); $warn = array_filter($checked, fn($c) => !$c['error'] && $c['warn']); $good = count($checked) - count($bad); ?>
<section class="card" aria-labelledby="pv-h">
  <h2 id="pv-h"><?= h(t('imp_preview_' . $kind)) ?></h2>
  <p role="status"><?= h(t('imp_summary', count($checked), $good, count($bad))) ?></p>
  <?php if ($kind === 'matters'): ?><p class="note small"><?= h(t('imp_matters_check_note')) ?></p><?php endif; ?>
  <table class="stack"><thead><tr><th scope="col"><?= h(t('imp_row')) ?></th><th scope="col"><?= h(t('name')) ?></th><th scope="col"><?= h(t('imp_result')) ?></th></tr></thead><tbody>
    <?php foreach (array_slice($checked, 0, 300) as $c): ?><tr>
      <td data-label="<?= h(t('imp_row')) ?>" dir="ltr"><?= (int)$c['row'] ?></td>
      <td data-label="<?= h(t('name')) ?>"><?= h($c['label'] ?: '—') ?></td>
      <td data-label="<?= h(t('imp_result')) ?>"><?php if ($c['error']): ?><span class="tag late"><?= h(t('imp_skip')) ?></span> <?= h(t($c['error'])) ?><?php elseif ($c['warn']): ?><span class="tag"><?= h(t('imp_ok_warn')) ?></span> <?= h(t($c['warn'])) ?><?php else: ?><?= h(t('imp_ok')) ?><?php endif; ?></td>
    </tr><?php endforeach; ?>
  </tbody></table>
  <?php if (count($checked) > 300): ?><p class="muted small"><?= h(t('imp_more_rows', count($checked) - 300)) ?></p><?php endif; ?>
  <?php if ($good && !$ro): ?>
    <form method="post" action="/import/<?= h($kind) ?>/confirm" class="row"><?= csrf_field() ?><button class="btn"><?= h(t('imp_confirm', $good)) ?></button> <a class="btn ghost" href="/import"><?= h(t('cancel')) ?></a></form>
  <?php else: ?><p><a href="/import"><?= h(t('imp_again')) ?></a></p><?php endif; ?>
</section>
<?php else: foreach ($kinds as $k): ?>
<section class="card">
  <h2><?= h(t('imp_kind_' . $k)) ?></h2>
  <p class="muted small"><?= h(t('imp_help_' . $k)) ?></p>
  <p><a href="/import/<?= $k ?>/template"><?= h(t('imp_template')) ?></a></p>
  <?php if (!$ro): ?>
  <form method="post" action="/import/<?= $k ?>" enctype="multipart/form-data" class="row"><?= csrf_field() ?>
    <label for="f-<?= $k ?>"><?= h(t('imp_file')) ?></label><input id="f-<?= $k ?>" type="file" name="file" accept=".xlsx,.csv" required>
    <button class="btn"><?= h(t('imp_check')) ?></button>
  </form>
  <?php endif; ?>
</section>
<?php endforeach; endif; ?>
