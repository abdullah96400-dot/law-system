<?php $ro = office_readonly(); $canCreate = can('staff', 'create') && !$ro; $canEdit = can('staff', 'edit') && !$ro; ?>
<section class="card">
  <h1><?= h(t('staff')) ?></h1>
  <?php if ($err): ?><div class="flash err" role="alert"><?= h(t($err)) ?></div><?php endif; ?>
  <div class="table-wrap">
  <table class="stack">
    <caption class="sr"><?= h(t('staff')) ?></caption>
    <thead><tr><th scope="col"><?= h(t('name')) ?></th><th scope="col"><?= h(t('email')) ?></th><th scope="col"><?= h(t('role')) ?></th><th scope="col"><?= h(t('status')) ?></th><th scope="col"><?= h(t('2fa_short')) ?></th><th scope="col"><?= h(t('last_login')) ?></th><?php if ($canEdit): ?><th scope="col"><?= h(t('actions')) ?></th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($list as $s): $mine = (int)$s['id'] === (int)$me['id']; $manage = $canEdit && can_manage($me, $s + ['office_id' => $me['office_id']]); ?>
      <tr>
        <td data-label="<?= h(t('name')) ?>"><?= h($s['full_name']) ?><?= $mine ? ' <span class="tag">' . h(t('you')) . '</span>' : '' ?></td>
        <td data-label="<?= h(t('email')) ?>"><span dir="ltr"><?= h($s['email']) ?></span></td>
        <td data-label="<?= h(t('role')) ?>"><?= h(t('role_' . $s['role'])) ?></td>
        <td data-label="<?= h(t('status')) ?>"><span class="st st-<?= h($s['status']) ?>"><?= h(t('st_' . $s['status'])) ?></span></td>
        <td data-label="<?= h(t('2fa_short')) ?>"><?= $s['has_2fa'] ? h(t('yes')) : h(t('no')) ?></td>
        <td data-label="<?= h(t('last_login')) ?>"><span dir="ltr"><?= h(fmt_dt($s['last_login'])) ?></span></td>
        <?php if ($canEdit): ?><td class="actions" data-label="<?= h(t('actions')) ?>">
          <?php if ($manage): ?>
            <details><summary><?= h(t('manage')) ?></summary>
              <form method="post" action="/staff/<?= (int)$s['id'] ?>/role"><?= csrf_field() ?>
                <label for="role-<?= (int)$s['id'] ?>"><?= h(t('role')) ?></label>
                <select id="role-<?= (int)$s['id'] ?>" name="role"><?php foreach (ROLES as $r) if (can_assign_role($me, $r)): ?><option value="<?= $r ?>"<?= $r === $s['role'] ? ' selected' : '' ?>><?= h(t('role_' . $r)) ?></option><?php endif; ?></select>
                <button class="btn small"><?= h(t('save')) ?></button></form>
              <?php if ($brs = branches()): ?><form method="post" action="/staff/<?= (int)$s['id'] ?>/branch"><?= csrf_field() ?>
                <label for="br-<?= (int)$s['id'] ?>"><?= h(t('branch')) ?></label>
                <select id="br-<?= (int)$s['id'] ?>" name="branch_id"><option value=""><?= h(t('none_dash')) ?></option><?php foreach ($brs as $b): ?><option value="<?= (int)$b['id'] ?>"<?= (int)$s['branch_id'] === (int)$b['id'] ? ' selected' : '' ?>><?= h($b['name']) ?></option><?php endforeach; ?></select>
                <button class="btn small ghost"><?= h(t('save')) ?></button></form><?php endif; ?>
              <?php if ($s['status'] !== 'disabled'): ?>
                <form method="post" action="/staff/<?= (int)$s['id'] ?>/reinvite"><?= csrf_field() ?><button class="btn small ghost"><?= h(t($s['status'] === 'active' ? 'send_reset' : 'resend_invite')) ?></button></form>
                <form method="post" action="/staff/<?= (int)$s['id'] ?>/revoke"><?= csrf_field() ?><button class="btn small ghost"><?= h(t('revoke_sessions')) ?></button></form>
                <?php if ($s['has_2fa']): ?><form method="post" action="/staff/<?= (int)$s['id'] ?>/reset_2fa" data-confirm="<?= h(t('confirm_reset_2fa')) ?>"><?= csrf_field() ?><button class="btn small ghost"><?= h(t('reset_2fa')) ?></button></form><?php endif; ?>
                <form method="post" action="/staff/<?= (int)$s['id'] ?>/disable" data-confirm="<?= h(t('confirm_disable', $s['full_name'])) ?>"><?= csrf_field() ?><button class="btn small danger"><?= h(t('disable')) ?></button></form>
              <?php else: ?>
                <form method="post" action="/staff/<?= (int)$s['id'] ?>/enable"><?= csrf_field() ?><button class="btn small"><?= h(t('enable')) ?></button></form>
              <?php endif; ?>
            </details>
          <?php else: ?><span class="muted">—</span><?php endif; ?>
        </td><?php endif; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</section>

<?php if ($canCreate): ?>
<section class="card">
  <h2><?= h(t('invite_staff')) ?></h2>
  <p class="muted"><?= h(t('invite_intro')) ?></p>
  <form method="post" action="/staff/invite" class="form-grid">
    <?= csrf_field() ?>
    <div><label for="full_name"><?= h(t('name')) ?></label><input id="full_name" name="full_name" required maxlength="160"></div>
    <div><label for="inv-email"><?= h(t('email')) ?></label><input id="inv-email" name="email" type="email" dir="ltr" required></div>
    <div><label for="inv-role"><?= h(t('role')) ?></label><select id="inv-role" name="role"><?php foreach (ROLES as $r) if (can_assign_role($me, $r)): ?><option value="<?= $r ?>"<?= $r === 'lawyer' ? ' selected' : '' ?>><?= h(t('role_' . $r)) ?></option><?php endif; ?></select></div>
    <div><label for="inv-lang"><?= h(t('language')) ?></label><select id="inv-lang" name="lang"><option value="ar">العربية</option><option value="en">English</option></select></div>
    <div class="full"><button class="btn"><?= h(t('send_invite')) ?></button></div>
  </form>
</section>
<?php endif; ?>
