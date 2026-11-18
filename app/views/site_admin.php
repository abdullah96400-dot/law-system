<?php $me = current_staff(); $o = current_office(); $live = $row && (int)$row['published'] === 1 && $row['live'];
$pub = '/o/' . strtolower($o['code']); $changes = site_has_changes(); $v = fn($k) => h((string)($c[$k] ?? ''));
$langs = ['both' => t('site_lang_both'), 'ar' => t('site_lang_ar'), 'en' => t('site_lang_en')]; ?>
<section class="card">
  <div class="head-row">
    <div><h1><?= h(t('site_admin')) ?></h1><p class="muted"><?= h(t('site_admin_intro')) ?></p></div>
    <div class="row"><a class="btn ghost" href="/site/preview" target="_blank" rel="noopener"><?= h(t('site_preview')) ?></a>
      <form method="post" action="/site/publish" class="inline"><?= csrf_field() ?><button class="btn"><?= h(t($live ? 'site_publish_changes' : 'site_publish')) ?></button></form></div>
  </div>
  <div class="kpis compact">
    <div class="kpi <?= $live ? 'pos' : 'warn' ?>"><span><?= h(t('status')) ?></span><b><?= h(t($live ? 'site_state_live' : 'site_state_off')) ?></b><?php if ($live): ?><small dir="ltr"><?= h(fmt_dt($row['published_at'])) ?></small><?php endif; ?></div>
    <div class="kpi <?= $changes ? 'warn' : '' ?>"><span><?= h(t('site_draft')) ?></span><b><?= h(t($changes ? 'site_unpublished_changes' : 'site_no_changes')) ?></b></div>
    <div class="kpi"><span><?= h(t('site_address_label')) ?></span><b class="small break" dir="ltr"><?php if ($live): ?><a href="<?= h($pub) ?>/" target="_blank" rel="noopener"><?= h(parse_url(abs_url('/'), PHP_URL_HOST) . $pub) ?></a><?php else: ?><?= h(parse_url(abs_url('/'), PHP_URL_HOST) . $pub) ?><?php endif; ?></b></div>
  </div>
  <?php if ($live): ?><div class="row mt">
    <?php if ($changes): ?><form method="post" action="/site/discard" class="inline" data-confirm="<?= h(t('site_discard_confirm')) ?>"><?= csrf_field() ?><button class="btn ghost small"><?= h(t('site_discard')) ?></button></form><?php endif; ?>
    <form method="post" action="/site/unpublish" class="inline" data-confirm="<?= h(t('site_unpublish_confirm')) ?>"><?= csrf_field() ?><button class="btn ghost small"><?= h(t('site_unpublish')) ?></button></form>
  </div><?php endif; ?>
  <p class="note small mt"><?= h(t('site_rules_note')) ?></p>
</section>

<section class="card" id="general">
  <h2><?= h(t('site_general')) ?></h2>
  <form method="post" action="/site/general" class="form-grid"><?= csrf_field() ?>
    <div><label for="lang"><?= h(t('site_lang')) ?></label><select id="lang" name="lang"><?php foreach ($langs as $k => $l): ?><option value="<?= $k ?>"<?= $c['lang'] === $k ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select></div>
    <div><label for="license_no"><?= h(t('site_license_no')) ?></label><input id="license_no" name="license_no" dir="ltr" maxlength="60" value="<?= $v('license_no') ?>"></div>
    <div><label for="primary"><?= h(t('site_color_primary')) ?></label><input id="primary" name="primary" type="color" value="<?= $v('primary') ?>"></div>
    <div><label for="accent"><?= h(t('site_color_accent')) ?></label><input id="accent" name="accent" type="color" value="<?= $v('accent') ?>"></div>
    <div><label for="tagline_ar"><?= h(t('site_tagline')) ?> (عربي)</label><input id="tagline_ar" name="tagline_ar" dir="rtl" maxlength="200" value="<?= $v('tagline_ar') ?>"></div>
    <div><label for="tagline_en"><?= h(t('site_tagline')) ?> (English)</label><input id="tagline_en" name="tagline_en" dir="ltr" maxlength="200" value="<?= $v('tagline_en') ?>"></div>
    <div class="full"><label for="about_ar"><?= h(t('site_about')) ?> (عربي)</label><textarea id="about_ar" name="about_ar" dir="rtl" rows="5" maxlength="4000"><?= $v('about_ar') ?></textarea></div>
    <div class="full"><label for="about_en"><?= h(t('site_about')) ?> (English)</label><textarea id="about_en" name="about_en" dir="ltr" rows="5" maxlength="4000"><?= $v('about_en') ?></textarea></div>
    <div><label for="phone"><?= h(t('sr_phone')) ?></label><input id="phone" name="phone" type="tel" dir="ltr" maxlength="40" value="<?= $v('phone') ?>"></div>
    <div><label for="whatsapp">WhatsApp</label><input id="whatsapp" name="whatsapp" type="tel" dir="ltr" maxlength="40" value="<?= $v('whatsapp') ?>"></div>
    <div><label for="email"><?= h(t('sr_email')) ?></label><input id="email" name="email" type="email" dir="ltr" maxlength="190" value="<?= $v('email') ?>"></div>
    <div><label for="map_url"><?= h(t('site_map_link')) ?></label><input id="map_url" name="map_url" type="url" dir="ltr" maxlength="400" placeholder="https://maps.app.goo.gl/…" value="<?= $v('map_url') ?>"></div>
    <div><label for="address_ar"><?= h(t('site_address')) ?> (عربي)</label><input id="address_ar" name="address_ar" dir="rtl" maxlength="300" value="<?= $v('address_ar') ?>"></div>
    <div><label for="address_en"><?= h(t('site_address')) ?> (English)</label><input id="address_en" name="address_en" dir="ltr" maxlength="300" value="<?= $v('address_en') ?>"></div>
    <div><label for="hours_ar"><?= h(t('site_hours')) ?> (عربي)</label><input id="hours_ar" name="hours_ar" dir="rtl" maxlength="200" value="<?= $v('hours_ar') ?>"></div>
    <div><label for="hours_en"><?= h(t('site_hours')) ?> (English)</label><input id="hours_en" name="hours_en" dir="ltr" maxlength="200" value="<?= $v('hours_en') ?>"></div>
    <p class="full muted small"><?= h(t('site_logo_note')) ?> <a href="/settings#office"><?= h(t('settings_menu')) ?></a></p>
    <div class="full"><button class="btn"><?= h(t('site_save_draft')) ?></button></div>
  </form>
</section>

<?php foreach (['services' => 'title', 'lawyers' => 'name'] as $kind => $nf): $items = $c[$kind]; ?>
<section class="card" id="<?= $kind ?>">
  <div class="head-row"><h2><?= h(t($kind === 'services' ? 'site_services' : 'site_lawyers')) ?> · <?= count($items) ?></h2>
    <a class="btn small" href="/site/<?= $kind ?>/new"><?= h(t($kind === 'services' ? 'site_add_service' : 'site_add_lawyer')) ?></a></div>
  <?php if ($kind === 'services'): ?><p class="muted small"><?= h(t('site_services_note')) ?></p><?php endif; ?>
  <?php if (!$items): ?><p class="muted"><?= h(t($kind === 'services' ? 'site_no_services' : 'site_no_lawyers')) ?></p><?php endif; ?>
  <ul class="plain one site-items">
  <?php foreach ($items as $i => $x): ?>
    <li><div><a href="/site/<?= $kind ?>/<?= h($x['key']) ?>"><b><?= h(site_t($x, $nf) ?: '—') ?></b></a> <?php if (empty($x['show'])): ?><span class="tag"><?= h(t('site_hidden')) ?></span><?php endif; ?></div>
      <div class="row">
        <?php foreach (['up' => '↑', 'down' => '↓'] as $do => $sym): if (($do === 'up' && $i === 0) || ($do === 'down' && $i === count($items) - 1)) continue; ?>
          <form method="post" action="/site/<?= $kind ?>/<?= h($x['key']) ?>/<?= $do ?>" class="inline"><?= csrf_field() ?><button class="btn ghost small" aria-label="<?= h(t('site_move_' . $do)) ?>"><?= $sym ?></button></form>
        <?php endforeach; ?>
        <form method="post" action="/site/<?= $kind ?>/<?= h($x['key']) ?>/remove" class="inline" data-confirm="<?= h(t('site_remove_confirm')) ?>"><?= csrf_field() ?><button class="btn ghost small"><?= h(t('remove')) ?></button></form>
      </div></li>
  <?php endforeach; ?>
  </ul>
</section>
<?php endforeach; ?>

<section class="card" id="domains">
  <h2><?= h(t('site_domains')) ?></h2>
  <p class="muted small"><?= h(t('site_domains_note')) ?></p>
  <ul class="plain one"><?php foreach ($domains as $dm): ?><li><span dir="ltr"><?= h($dm['host']) ?></span>
    <?php if ($me['role'] === 'partner'): ?><form method="post" action="/site/domains/<?= (int)$dm['id'] ?>/remove" class="inline" data-confirm="<?= h(t('site_remove_confirm')) ?>"><?= csrf_field() ?><button class="btn ghost small"><?= h(t('remove')) ?></button></form><?php endif; ?></li><?php endforeach; ?>
  <?php if (!$domains): ?><li class="muted"><?= h(t('site_no_domains')) ?></li><?php endif; ?></ul>
  <?php if ($me['role'] === 'partner'): ?><form method="post" action="/site/domains" class="filters"><?= csrf_field() ?>
    <div class="grow"><label for="host"><?= h(t('site_domain')) ?></label><input id="host" name="host" dir="ltr" placeholder="www.example.om" required></div>
    <div><button class="btn"><?= h(t('add')) ?></button></div></form><?php endif; ?>
</section>
