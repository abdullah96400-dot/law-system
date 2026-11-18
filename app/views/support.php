<section class="card">
  <h1><?= h(t('support_title')) ?></h1>
  <p><?= h(t('support_intro')) ?></p>
  <ul><li><?= h(t('support_rule_view')) ?></li><li><?= h(t('support_rule_hidden')) ?></li><li><?= h(t('support_rule_log')) ?></li><li><?= h(t('support_rule_end')) ?></li></ul>
  <?php if ($err): ?><div class="flash err" role="alert"><?= h(t($err)) ?></div><?php endif; ?>
  <?php if ($link): ?>
    <div class="note" role="status"><p><b><?= h(t('support_link_ready')) ?></b></p><p class="break" dir="ltr"><code><?= h($link) ?></code></p><p class="small"><?= h(t('support_link_once')) ?></p></div>
  <?php elseif (!office_readonly()): ?>
  <form method="post" action="/settings/support" class="form-grid"><?= csrf_field() ?>
    <div class="full"><label for="reason"><?= h(t('support_reason')) ?></label><input id="reason" name="reason" maxlength="300" required placeholder="<?= h(t('support_reason_ph')) ?>"></div>
    <div><label for="hours"><?= h(t('support_hours')) ?></label><select id="hours" name="hours"><?php foreach ([2, 8, 24, 72] as $hh): ?><option value="<?= $hh ?>"<?= $hh === 24 ? ' selected' : '' ?>><?= h(t('support_hours_n', $hh)) ?></option><?php endforeach; ?></select></div>
    <div class="full"><button class="btn"><?= h(t('support_open')) ?></button></div>
  </form>
  <?php endif; ?>
</section>
<section class="card">
  <h2><?= h(t('support_history')) ?></h2>
  <?php if (!$list): ?><p class="muted"><?= h(t('empty')) ?></p><?php endif; ?>
  <ul class="plain one">
    <?php foreach ($list as $g): $live = support_grant_live($g); ?><li>
      <b><?= h($g['reason']) ?></b> <span class="muted small">· <?= h(t('support_until')) ?> <span dir="ltr"><?= h(date('d/m/Y H:i', strtotime($g['expires_at']))) ?></span> · <?= h($g['first_used_at'] ? t('support_used') : t('support_not_used')) ?></span>
      <?php if ($live): ?><span class="tag"><?= h(t('support_live')) ?></span>
        <form method="post" action="/settings/support/<?= (int)$g['id'] ?>/revoke" class="inline" data-confirm="<?= h(t('support_end_confirm')) ?>"><?= csrf_field() ?><button class="btn small danger"><?= h(t('support_end')) ?></button></form>
      <?php else: ?><span class="tag"><?= h($g['revoked_at'] ? t('support_ended') : t('support_expired')) ?></span><?php endif; ?>
      <a class="small" href="/audit?entity=support_grant&amp;id=<?= (int)$g['id'] ?>"><?= h(t('support_see_log')) ?></a>
    </li><?php endforeach; ?>
  </ul>
</section>
