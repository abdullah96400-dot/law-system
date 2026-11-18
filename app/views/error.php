<section class="card narrow">
  <h1><?= h($title ?? t('error')) ?></h1>
  <p><?= h($msg) ?></p>
  <p><a href="/"><?= h(t('back_home')) ?></a></p>
</section>
