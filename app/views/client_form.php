<?php $v = fn($k) => h($d[$k] ?? ''); ?>
<div class="page-head"><div><h1><?= h($title) ?></h1><p class="req-note"><?= h(t('req_note')) ?></p></div></div>
<section class="card">
  <?php if ($err): ?><div class="flash err" role="alert"><span><?= h(t($err)) ?></span></div><?php endif; ?>
  <?php if ($dups): ?>
    <div class="flash warn" role="alert"><div>
      <b><?= h(t('dup_title')) ?></b>
      <ul>
        <?php foreach ($dups as [$c, $why]): ?>
          <li><a href="/clients/<?= (int)$c['id'] ?>" target="_blank" rel="noopener"><?= h(contact_label($c)) ?></a> — <?= h(t($why)) ?><?= $c['archived'] ? ' (' . h(t('archived')) . ')' : '' ?></li>
        <?php endforeach; ?>
      </ul>
      <p class="small"><?= h(t('dup_help')) ?></p>
    </div></div>
  <?php endif; ?>
  <form method="post" class="form-grid" novalidate>
    <?= csrf_field() ?>
    <fieldset>
      <legend><?= h(t('grp_identity')) ?></legend>
      <div class="form-grid">
        <div><span class="sr"><?= h(t('contact_type')) ?></span>
          <label class="radio"><input type="radio" name="is_client" value="1"<?= !empty($d['is_client']) ? ' checked' : '' ?>> <?= h(t('ct_client')) ?></label>
          <label class="radio"><input type="radio" name="is_client" value=""<?= empty($d['is_client']) ? ' checked' : '' ?>> <?= h(t('ct_party_long')) ?></label></div>
        <div><span class="sr"><?= h(t('contact_kind')) ?></span>
          <?php foreach (CONTACT_KINDS as $k): ?><label class="radio"><input type="radio" name="kind" value="<?= $k ?>"<?= ($d['kind'] ?? 'person') === $k ? ' checked' : '' ?>> <?= h(t('kind_' . $k)) ?></label><?php endforeach; ?></div>
        <div><label for="name_ar"><?= h(t('name_ar')) ?><?= req_mark() ?></label><input id="name_ar" name="name_ar" dir="rtl" maxlength="200" value="<?= $v('name_ar') ?>"<?= fattr($err, 'name_ar') ?>><?= ferr($err, 'name_ar') ?></div>
        <div><label for="name_en"><?= h(t('name_en')) ?></label><input id="name_en" name="name_en" dir="ltr" maxlength="200" value="<?= $v('name_en') ?>"></div>
        <p class="full hint"><?= h(t('name_both_hint')) ?></p>
        <div class="full"><label for="aliases"><?= h(t('aliases')) ?></label><input id="aliases" name="aliases" maxlength="500" value="<?= $v('aliases') ?>" aria-describedby="aliases-help"><span id="aliases-help" class="hint"><?= h(t('aliases_help')) ?></span></div>
      </div>
    </fieldset>
    <fieldset>
      <legend><?= h(t('grp_contact')) ?></legend>
      <div class="form-grid">
        <div><label for="phone"><?= h(t('phone')) ?></label><input id="phone" name="phone" type="tel" dir="ltr" maxlength="40" value="<?= $v('phone') ?>"<?= fattr($err, 'phone') ?>><?= ferr($err, 'phone') ?></div>
        <div><label for="email"><?= h(t('email')) ?></label><input id="email" name="email" type="email" dir="ltr" maxlength="190" value="<?= $v('email') ?>"<?= fattr($err, 'email') ?>><?= ferr($err, 'email') ?></div>
        <div><label for="pref_lang"><?= h(t('pref_lang')) ?></label><select id="pref_lang" name="pref_lang"><option value="ar"<?= ($d['pref_lang'] ?? 'ar') === 'ar' ? ' selected' : '' ?>>العربية</option><option value="en"<?= ($d['pref_lang'] ?? '') === 'en' ? ' selected' : '' ?>>English</option></select></div>
        <div><label for="address"><?= h(t('address')) ?></label><input id="address" name="address" maxlength="300" value="<?= $v('address') ?>"></div>
      </div>
    </fieldset>
    <fieldset>
      <legend><?= h(t('id_doc')) ?> <span class="muted small">(<?= h(t('optional')) ?>)</span></legend>
      <p class="hint"><?= h(t('id_doc_help')) ?></p>
      <div class="form-grid">
        <div><label for="id_type"><?= h(t('id_type')) ?></label><select id="id_type" name="id_type"><option value=""><?= h(t('none')) ?></option><?php foreach (ID_TYPES as $it): ?><option value="<?= $it ?>"<?= ($d['id_type'] ?? '') === $it ? ' selected' : '' ?>><?= h(t('idt_' . $it)) ?></option><?php endforeach; ?></select></div>
        <div><label for="id_number"><?= h(t('id_number')) ?></label><input id="id_number" name="id_number" dir="ltr" maxlength="60" value="<?= $v('id_number') ?>"<?= fattr($err, 'id_number') ?>><?= ferr($err, 'id_number') ?></div>
      </div>
    </fieldset>
    <fieldset>
      <legend><?= h(t('grp_more')) ?></legend>
      <label for="notes"><?= h(t('internal_notes')) ?> <span class="tag"><?= icon('lock') ?><?= h(t('internal')) ?></span></label><textarea id="notes" name="notes" rows="3" maxlength="4000" aria-describedby="notes-help"><?= $v('notes') ?></textarea><span id="notes-help" class="hint"><?= h(t('internal_notes_help')) ?></span>
    </fieldset>
    <div class="full form-actions">
      <?php if ($dups): ?>
        <button class="btn" name="confirm_dup" value="1"><?= h(t('dup_save_anyway')) ?></button>
      <?php else: ?>
        <button class="btn"><?= h(t('save')) ?></button>
      <?php endif; ?>
      <a class="btn ghost" href="<?= $id ? '/clients/' . (int)$id : '/clients' ?>"><?= h(t('cancel')) ?></a>
    </div>
  </form>
</section>
