<?php $fields = SITE_ITEM_FIELDS[$kind]; $v = fn($k) => h((string)($it[$k] ?? '')); $long = ['bio_ar', 'bio_en', 'body_ar', 'body_en']; ?>
<section class="card narrow">
  <p class="small"><a href="/site#<?= $kind ?>"><?= h(t('site_admin')) ?></a></p>
  <h1><?= h($title) ?></h1>
  <?php if ($err): ?><div class="flash err" role="alert"><span><?= h(t($err)) ?></span></div><?php endif; ?>
  <form method="post" action="/site/<?= $kind ?>/<?= h($key ?? 'new') ?>"><?= csrf_field() ?>
    <?php foreach ($fields as $f => $max): $ar = str_ends_with($f, '_ar'); $base = substr($f, 0, -3); ?>
      <label for="<?= $f ?>"><?= h(t('site_f_' . $base)) ?> (<?= $ar ? 'عربي' : 'English' ?>)</label>
      <?php if (in_array($f, $long, true)): ?><textarea id="<?= $f ?>" name="<?= $f ?>" dir="<?= $ar ? 'rtl' : 'ltr' ?>" rows="6" maxlength="<?= $max ?>"><?= $v($f) ?></textarea>
      <?php else: ?><input id="<?= $f ?>" name="<?= $f ?>" dir="<?= $ar ? 'rtl' : 'ltr' ?>" maxlength="<?= $max ?>" value="<?= $v($f) ?>"><?php endif; ?>
    <?php endforeach; ?>
    <label class="radio"><input type="checkbox" name="show" value="1"<?= !empty($it['show']) ? ' checked' : '' ?>> <?= h(t('site_show')) ?></label>
    <?php if ($kind === 'services'): ?><p class="note small"><?= h(t('site_service_rules')) ?></p><?php endif; ?>
    <button class="btn"><?= h(t('site_save_draft')) ?></button>
  </form>
</section>
