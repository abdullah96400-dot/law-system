<section class="card">
  <h1><?= h(t('notifications')) ?></h1>
  <p class="muted small"><?= h(t('notifications_intro')) ?></p>
  <?php if (!$list): ?><p class="muted"><?= h(t('empty')) ?></p><?php endif; ?>
  <ul class="plain one notif">
    <?php foreach ($list as $n): ?>
      <li class="<?= $n['read_at'] ? '' : 'unread' ?>"><span class="muted small" dir="ltr"><?= h(fmt_dt($n['created_at'])) ?></span><br>
        <?php if ($n['link']): ?><a href="<?= h($n['link']) ?>"><?= h($n['title']) ?></a><?php else: ?><?= h($n['title']) ?><?php endif; ?></li>
    <?php endforeach; ?>
  </ul>
</section>
