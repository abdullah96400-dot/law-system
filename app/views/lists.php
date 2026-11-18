<?php $edit = can('settings', 'edit') && !office_readonly(); ?>
<section class="card">
  <h1><?= h(t('lists')) ?></h1>
  <p class="muted"><?= h(t('lists_intro')) ?></p>
</section>
<?php foreach (LOOKUP_KINDS as $k): ?>
<section class="card" id="<?= $k ?>">
  <h2><?= h(t('lk_' . $k)) ?></h2>
  <?php if (!$all[$k]): ?><p class="muted"><?= h(t($k === 'court' ? 'lists_no_courts' : 'empty')) ?></p><?php endif; ?>
  <?php foreach ($all[$k] as $l): ?>
    <?php if ($edit): ?>
      <form method="post" action="/settings/lists/<?= (int)$l['id'] ?>" class="list-row<?= $l['active'] ? '' : ' off' ?>"><?= csrf_field() ?>
        <label class="sr" for="ar-<?= (int)$l['id'] ?>"><?= h(t('name_ar')) ?></label><input id="ar-<?= (int)$l['id'] ?>" name="name_ar" dir="rtl" value="<?= h($l['name_ar']) ?>" maxlength="160">
        <label class="sr" for="en-<?= (int)$l['id'] ?>"><?= h(t('name_en')) ?></label><input id="en-<?= (int)$l['id'] ?>" name="name_en" dir="ltr" value="<?= h($l['name_en']) ?>" maxlength="160">
        <label class="radio nowrap"><input type="checkbox" name="active" value="1"<?= $l['active'] ? ' checked' : '' ?>> <?= h(t('in_use')) ?></label>
        <?php if ($l['is_closed']): ?><span class="tag"><?= h(t('closes_file')) ?></span><?php endif; ?>
        <button class="btn small ghost"><?= h(t('save')) ?></button>
      </form>
    <?php else: ?>
      <p><?= h(lookup_label($l)) ?><?= $l['active'] ? '' : ' <span class="tag">' . h(t('not_in_use')) . '</span>' ?></p>
    <?php endif; ?>
  <?php endforeach; ?>
  <?php if ($edit): ?>
    <form method="post" action="/settings/lists/add" class="list-row add"><?= csrf_field() ?><input type="hidden" name="kind" value="<?= $k ?>">
      <label class="sr" for="nar-<?= $k ?>"><?= h(t('name_ar')) ?></label><input id="nar-<?= $k ?>" name="name_ar" dir="rtl" placeholder="<?= h(t('name_ar')) ?>" maxlength="160">
      <label class="sr" for="nen-<?= $k ?>"><?= h(t('name_en')) ?></label><input id="nen-<?= $k ?>" name="name_en" dir="ltr" placeholder="<?= h(t('name_en')) ?>" maxlength="160">
      <?php if ($k === 'status'): ?><label class="radio nowrap"><input type="checkbox" name="is_closed" value="1"> <?= h(t('closes_file')) ?></label><?php endif; ?>
      <button class="btn small"><?= h(t('add')) ?></button>
    </form>
  <?php endif; ?>
</section>
<?php endforeach; ?>
