<?php $ro = office_readonly(); ?>
<section class="card">
  <h1><?= h(t('roles')) ?></h1>
  <p class="muted"><?= h(t('roles_intro')) ?></p>
</section>
<?php foreach (ROLES as $role): $edit = in_array($role, $editable, true) && !$ro; ?>
<section class="card" id="role-<?= $role ?>">
  <h2><?= h(t('role_' . $role)) ?><?= $role === 'partner' ? ' <span class="tag">' . h(t('all_permissions')) . '</span>' : '' ?></h2>
  <form method="post" action="/roles/<?= $role ?>">
    <?= csrf_field() ?>
    <div class="table-wrap">
    <table class="matrix">
      <caption class="sr"><?= h(t('role_' . $role)) ?></caption>
      <thead><tr><th scope="col"><?= h(t('module')) ?></th><?php foreach (ACTIONS as $a): ?><th scope="col"><?= h(t('act_' . $a)) ?></th><?php endforeach; ?></tr></thead>
      <tbody>
      <?php foreach (MODULES as $m): $g = grantable($role, $m); ?>
        <tr>
          <th scope="row"><?= h(t('mod_' . $m)) ?><?php if (!in_array($m, LIVE_MODULES, true)): ?> <span class="tag"><?= h(t('not_yet')) ?></span><?php endif; ?></th>
          <?php foreach (ACTIONS as $a): $on = !empty($mx[$role][$m][$a]); ?>
            <td><input type="checkbox" name="p[<?= $m ?>][<?= $a ?>]" value="1" aria-label="<?= h(t('mod_' . $m) . ' — ' . t('act_' . $a)) ?>"<?= $on ? ' checked' : '' ?><?= (!$edit || !$g) ? ' disabled' : '' ?>></td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php if ($edit): ?><button class="btn"><?= h(t('save_role', t('role_' . $role))) ?></button><?php elseif ($role !== 'partner'): ?><p class="muted small"><?= h(t('role_not_editable')) ?></p><?php endif; ?>
  </form>
</section>
<?php endforeach; ?>
