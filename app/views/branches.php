<?php $edit = can('settings', 'edit') && !office_readonly(); ?>
<section class="card">
  <h1><?= h(t('branches')) ?></h1>
  <p class="muted"><?= h(t('branches_intro')) ?></p>
  <?php if (!$list): ?><p class="muted"><?= h(t('branches_empty')) ?></p><?php endif; ?>
  <?php foreach ($list as $b): ?>
    <?php if ($edit): ?>
      <form method="post" action="/settings/branches" class="list-row<?= $b['active'] ? '' : ' off' ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
        <label class="sr" for="bn-<?= (int)$b['id'] ?>"><?= h(t('branch_name')) ?></label><input id="bn-<?= (int)$b['id'] ?>" name="name" value="<?= h($b['name']) ?>" maxlength="160">
        <label class="radio nowrap"><input type="checkbox" name="active" value="1"<?= $b['active'] ? ' checked' : '' ?>> <?= h(t('in_use')) ?></label>
        <button class="btn small ghost"><?= h(t('save')) ?></button>
      </form>
    <?php else: ?><p><?= h($b['name']) ?><?= $b['active'] ? '' : ' <span class="tag">' . h(t('not_in_use')) . '</span>' ?></p><?php endif; ?>
  <?php endforeach; ?>
  <?php if ($edit): ?>
    <form method="post" action="/settings/branches" class="list-row add"><?= csrf_field() ?>
      <label class="sr" for="bn-new"><?= h(t('branch_name')) ?></label><input id="bn-new" name="name" placeholder="<?= h(t('branch_name')) ?>" maxlength="160">
      <button class="btn small"><?= h(t('add')) ?></button>
    </form>
  <?php endif; ?>
</section>
