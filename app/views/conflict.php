<?php $ro = office_readonly(); $pending = $c['status'] === 'pending'; $seeContacts = can('clients', 'view'); ?>
<section class="card">
  <div class="head-row">
    <div>
      <h1 dir="ltr" class="ref"><?= h($c['ref']) ?></h1>
      <p><?= h($c['subject']) ?></p>
      <p class="muted small"><?= h(t('created_by', staff_name($c['created_by'] ? (int)$c['created_by'] : null))) ?> · <span dir="ltr"><?= h(fmt_dt($c['created_at'])) ?></span></p>
    </div>
    <span class="chk big chk-<?= h($c['status']) ?>"><?= h(t('chk_' . $c['status'])) ?></span>
  </div>
  <p class="note"><?= h(t('check_disclaimer')) ?></p>
</section>

<section class="card">
  <div class="head-row">
    <h2><?= h(t('check_results', (int)$c['hit_count'])) ?></h2>
    <?php if ($pending && !$ro && can('conflicts', 'create')): ?>
      <form method="post" action="/conflicts/<?= (int)$c['id'] ?>/rerun"><?= csrf_field() ?><button class="btn small ghost"><?= h(t('check_rerun')) ?></button></form>
    <?php endif; ?>
  </div>
  <?php foreach ($groups as [$n, $hits]): ?>
    <div class="hit-group">
      <h3><?= h($n['name']) ?> <span class="tag"><?= h(t('check_role_' . $n['role'])) ?></span></h3>
      <?php if (!$hits): ?><p class="muted small"><?= h(t('no_match')) ?></p><?php else: ?>
      <ul class="hits">
        <?php foreach ($hits as $hh): $i = $hh['info']; ?>
          <li>
            <span class="score" title="<?= h(t('similarity')) ?>"><?= (int)$hh['score'] ?>%</span>
            <?php if ($hh['contact_id']): $lbl = lang() === 'ar' ? (($i['name_ar'] ?? '') ?: ($i['name_en'] ?? '')) : (($i['name_en'] ?? '') ?: ($i['name_ar'] ?? '')); ?>
              <?php if ($seeContacts): ?><a href="/clients/<?= (int)$hh['contact_id'] ?>"><?= h($lbl) ?></a><?php else: ?><b><?= h($lbl) ?></b><?php endif; ?>
              — <?= h(t(!empty($i['client']) ? 'ct_client' : 'ct_party')) ?><?= h(t('list_sep')) ?><?= h(t('kind_' . ($i['kind'] ?? 'person'))) ?><?= !empty($i['archived']) ? h(t('list_sep')) . h(t('archived')) : '' ?>
            <?php else: ?>
              <?= h(t('earlier_check')) ?> <a href="/conflicts/<?= (int)$hh['other_check_id'] ?>" dir="ltr"><?= h($i['ref'] ?? '') ?></a>: <?= h($i['name'] ?? '') ?> (<?= h(t('check_role_' . ($i['role'] ?? 'related'))) ?>) — <?= h($i['subject'] ?? '') ?> · <?= h(t('chk_' . ($i['status'] ?? 'pending'))) ?>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</section>

<section class="card">
  <h2><?= h(t('check_decision')) ?></h2>
  <?php if ($err && !$pending): ?><div class="flash err" role="alert"><?= h(t($err)) ?></div><?php endif; ?>
  <?php if (!$pending): ?>
    <p><span class="chk chk-<?= h($c['status']) ?>"><?= h(t('chk_' . $c['status'])) ?></span> — <?= h(t('decided_by', staff_name((int)$c['decided_by']))) ?> · <span dir="ltr"><?= h(fmt_dt($c['decided_at'])) ?></span></p>
    <?php if ($c['decision_note']): ?><p class="pre"><?= h($c['decision_note']) ?></p><?php endif; ?>
    <p class="muted small"><?= h(t('check_fixed')) ?></p>
    <?php if (in_array($c['status'], ['cleared', 'cleared_cond'], true)): $lm = matter_for_check((int)$c['id']); ?>
      <?php if ($lm): ?><p><?= h(t('check_opened_matter')) ?> <a href="/matters/<?= (int)$lm['id'] ?>" dir="ltr"><?= h($lm['ref']) ?></a></p>
      <?php elseif (!$ro && can('matters', 'create')): ?><a class="btn" href="/matters/new?check=<?= (int)$c['id'] ?>"><?= h(t('matter_open_from_check')) ?></a><?php endif; ?>
    <?php endif; ?>
  <?php elseif ($ro || !can('conflicts', 'edit')): ?>
    <p class="muted"><?= h(t('check_awaiting_reviewer')) ?></p>
  <?php else: ?>
    <?php if ($err): ?><div class="flash err" role="alert"><?= h(t($err)) ?></div><?php endif; ?>
    <form method="post" action="/conflicts/<?= (int)$c['id'] ?>/decide" data-confirm="<?= h(t('check_confirm_decision')) ?>">
      <?= csrf_field() ?>
      <fieldset>
        <legend><?= h(t('check_outcome')) ?></legend>
        <?php foreach (CHECK_DECISIONS as $dcs): ?>
          <label class="radio"><input type="radio" name="decision" value="<?= $dcs ?>" required<?= ($decision ?? '') === $dcs ? ' checked' : '' ?>> <?= h(t('chk_' . $dcs)) ?> <span class="muted small">— <?= h(t('chk_help_' . $dcs)) ?></span></label>
        <?php endforeach; ?>
      </fieldset>
      <label for="note"><?= h(t('check_note')) ?></label>
      <textarea id="note" name="note" rows="4" maxlength="4000" aria-describedby="note-help"><?= h($note ?? '') ?></textarea>
      <p id="note-help" class="muted small"><?= h((int)$c['hit_count'] > 0 ? t('check_note_required_help') : t('check_note_help')) ?></p>
      <button class="btn"><?= h(t('check_save_decision')) ?></button>
    </form>
  <?php endif; ?>
</section>
