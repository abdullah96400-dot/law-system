<?php $ar = lang() === 'ar'; $mark = function (string $s) use ($q): string { $e = h($s); if ($q === '') return $e; $pos = mb_stripos($s, $q); if ($pos === false) return $e;
  return h(mb_substr($s, 0, $pos)) . '<mark>' . h(mb_substr($s, $pos, mb_strlen($q))) . '</mark>' . h(mb_substr($s, $pos + mb_strlen($q))); };
$total = array_sum(array_map(fn($r) => $r[0], $res)); ?>
<div class="page-head"><div><h1><?= h($q !== '' ? t('search_for', $q) : t('search_title')) ?></h1><p class="small"><?= h(t('search_scope')) ?></p></div></div>
<section class="card">
  <form method="get" action="/search" role="search" class="filters">
    <div class="grow"><label for="sq"><?= h(t('gsearch')) ?></label><input id="sq" name="q" type="search" value="<?= h($q) ?>" placeholder="<?= h(t('gsearch_ph')) ?>" minlength="2"></div>
    <div><button class="btn"><?= icon('search') ?><?= h(t('search')) ?></button></div>
  </form>
  <?php if (mb_strlen($q) < 2): ?><?= empty_state(t('search_short'), '', null, null, 'search') ?>
  <?php elseif (!$total): ?><?= empty_state(t('search_none'), t('search_none_help'), null, null, 'search') ?><?php endif; ?>
</section>
<?php if ($res['matters'][0]): ?>
<section class="card sr-group" aria-labelledby="sg-m"><h2 id="sg-m"><?= h(t('mod_matters')) ?> <span class="n">(<?= (int)$res['matters'][0] ?>)</span></h2>
  <?php foreach ($res['matters'][1] as $m): ?><a class="sr-item" href="/matters/<?= (int)$m['id'] ?>"><span><b><?= $mark($m['title']) ?></b><span class="sub"><?= h($ar ? ($m['client_ar'] ?: $m['client_en']) : ($m['client_en'] ?: $m['client_ar'])) ?></span></span><span class="ref" dir="ltr"><?= $mark($m['ref']) ?></span></a><?php endforeach; ?>
  <?php if ($res['matters'][0] > 10): ?><p class="small muted"><a href="/matters?state=all&amp;q=<?= h(rawurlencode($q)) ?>"><?= h(t('search_more', 10)) ?></a></p><?php endif; ?>
</section><?php endif; ?>
<?php if ($res['clients'][0]): ?>
<section class="card sr-group" aria-labelledby="sg-c"><h2 id="sg-c"><?= h(t('mod_clients')) ?> <span class="n">(<?= (int)$res['clients'][0] ?>)</span></h2>
  <?php foreach ($res['clients'][1] as $c): ?><a class="sr-item" href="/clients/<?= (int)$c['id'] ?>"><span><b><?= $mark(contact_label($c)) ?></b><span class="sub"><?= h(t('kind_' . $c['kind'])) ?><?= $c['is_client'] ? ' · ' . h(t('ct_client')) : '' ?></span></span><?php if ($c['phone']): ?><span dir="ltr" class="muted small"><?= h($c['phone']) ?></span><?php endif; ?></a><?php endforeach; ?>
  <?php if ($res['clients'][0] > 10): ?><p class="small muted"><a href="/clients?q=<?= h(rawurlencode($q)) ?>"><?= h(t('search_more', 10)) ?></a></p><?php endif; ?>
</section><?php endif; ?>
<?php if ($res['documents'][0]): ?>
<section class="card sr-group" aria-labelledby="sg-d"><h2 id="sg-d"><?= h(t('mod_documents')) ?> <span class="n">(<?= (int)$res['documents'][0] ?>)</span></h2>
  <?php foreach ($res['documents'][1] as $x): ?><a class="sr-item" href="/documents/<?= (int)$x['id'] ?>"><span><b><?= $mark($x['title']) ?></b><span class="sub"><?= h(fmt_dt($x['updated_at'])) ?></span></span><?php if ($x['matter_ref']): ?><span class="ref" dir="ltr"><?= h($x['matter_ref']) ?></span><?php endif; ?></a><?php endforeach; ?>
  <?php if ($res['documents'][0] > 10): ?><p class="small muted"><a href="/documents?q=<?= h(rawurlencode($q)) ?>"><?= h(t('search_more', 10)) ?></a></p><?php endif; ?>
</section><?php endif; ?>
