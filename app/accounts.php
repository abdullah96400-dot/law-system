<?php
/* ==========================================================================
   Fee agreement view, statements of account, due notices, financial reports.
   - Instalment status comes from the money actually received on the invoice
     made for it: upcoming, due today, partly paid, paid, overdue.
   - Recording a payment for an instalment makes and issues its invoice if
     there is none yet, then records the payment with a receipt — the same
     rules as any invoice (never more than owed, voided not deleted).
   - Court fees are kept apart from other case expenses everywhere.
   - A due notice is a draft until a person sends it. The email never carries
     amounts or case details: it says a notice is waiting in the portal.
     "Delivered by hand / phone" is only recorded, nothing is sent. Daily
     reminders create drafts and tell the finance team; they send nothing.
   - Reports say their basis: income is money received (cash basis);
     expenses are what the office paid from its own money; court fees are a
     separate line; client money held (trust) is never income.
   ========================================================================== */
declare(strict_types=1);

const EXPENSE_CATEGORIES = ['expense', 'court_fee'];
const NOTICE_CHANNELS = ['email', 'portal', 'manual'];

/* ---------- office logo ---------- */
function office_logo_file(?array $o = null): ?string {
  $o = $o ?? current_office(); if (!$o || empty($o['logo'])) return null;
  $f = storage_dir() . '/logos/' . (int)$o['id'] . '/' . basename((string)$o['logo']);
  return is_file($f) ? $f : null;
}
/** Save an uploaded logo as a JPEG (re-drawn on white when GD is there, which drops anything hidden in the file). */
function office_logo_save(array $me, ?array $f): ?string {
  if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) return 'logo_bad';
  if ((int)$f['size'] > 1048576) return 'logo_bad';
  $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
  if (!in_array($mime, ['image/jpeg', 'image/png'], true)) return 'logo_bad';
  $dir = storage_dir() . '/logos/' . office_id(); if (!is_dir($dir)) @mkdir($dir, 0750, true);
  $name = 'logo-' . bin2hex(random_bytes(6)) . '.jpg'; $dst = "$dir/$name";
  if (function_exists('imagecreatefromstring')) {
    $src = @imagecreatefromstring((string)file_get_contents($f['tmp_name'])); if (!$src) return 'logo_bad';
    $w = imagesx($src); $h = imagesy($src); $nw = min(600, $w); $nh = max(1, (int)round($h * $nw / $w));
    $img = imagecreatetruecolor($nw, $nh); imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
    imagecopyresampled($img, $src, 0, 0, 0, 0, $nw, $nh, $w, $h); imagejpeg($img, $dst, 88);
  } elseif ($mime === 'image/jpeg') { copy($f['tmp_name'], $dst); }
  else return 'logo_png_needs_gd';
  $old = office_logo_file();
  q('UPDATE offices SET logo=?, updated_at=? WHERE id=?', [$name, now(), office_id()]);
  if ($old) @unlink($old);
  law_audit('office_logo_changed', 'office', office_id(), [], null, $me);
  return null;
}
/** Put the logo at the top of a PDF page (top-left in English, top-right in Arabic). Returns the height used. */
function pdf_logo(ImzPdf $pdf, float $x, float $y, float $maxH, bool $rtl): float {
  $f = office_logo_file(); if (!$f) return 0;
  $sz = @getimagesize($f); if (!$sz || $sz[2] !== IMAGETYPE_JPEG) return 0;
  $h = $maxH; $w = $sz[0] * $h / $sz[1]; if ($w > 160) { $w = 160; $h = $sz[1] * $w / $sz[0]; }
  $pdf->setImage((string)file_get_contents($f), $sz[0], $sz[1]); $pdf->image($rtl ? $pdf->w - $x - $w : $x, $y, $w, $h);
  return $h;
}

/* ---------- agreements and instalments ---------- */
/** Instalments with what was received for each (an invoice's payments go to its lines in order). */
function installments_state(int $agreementId, ?string $today = null): array {
  $today = $today ?? date('Y-m-d');
  $ins = orows('SELECT * FROM fee_installments WHERE office_id = :office AND agreement_id = :a ORDER BY COALESCE(due_on, :far), id', ['a' => $agreementId, 'far' => '9999-12-31']);
  $paidBy = [];
  $invIds = array_values(array_unique(array_filter(array_map(fn($x) => (int)$x['invoice_id'], $ins))));
  foreach ($invIds as $iid) {
    $inv = orow("SELECT paid, status FROM invoices WHERE office_id = :office AND id = :id", ['id' => $iid]); if (!$inv || $inv['status'] !== 'issued') continue;
    $left = (int)$inv['paid'];
    foreach (orows('SELECT installment_id, amount FROM invoice_lines WHERE office_id = :office AND invoice_id = :i ORDER BY id', ['i' => $iid]) as $l) {
      $take = min($left, (int)$l['amount']); $left -= $take;
      if ($l['installment_id']) $paidBy[(int)$l['installment_id']] = ($paidBy[(int)$l['installment_id']] ?? 0) + $take;
    }
  }
  foreach ($ins as &$x) {
    $p = $paidBy[(int)$x['id']] ?? 0; $amt = (int)$x['amount']; $x['paid'] = $p; $x['left'] = max(0, $amt - $p);
    $x['state'] = $p >= $amt ? 'paid' : ($x['due_on'] && $x['due_on'] < $today ? 'overdue' : ($p > 0 ? 'partial' : ($x['due_on'] && $x['due_on'] <= $today ? 'due' : 'upcoming')));
  }
  return $ins;
}
function agreement_summary(array $a, ?array $ins = null): array {
  $ins = $ins ?? installments_state((int)$a['id']); $today = date('Y-m-d');
  $schedule = array_sum(array_map(fn($x) => (int)$x['amount'], $ins));
  $total = $a['total'] !== null ? (int)$a['total'] : $schedule;
  $paid = (int)orow("SELECT COALESCE(SUM(paid),0) s FROM invoices WHERE office_id = :office AND agreement_id = :a AND status = 'issued'", ['a' => $a['id']])['s'];
  $dueNow = 0; $overdue = 0; $overN = 0;
  foreach ($ins as $x) { if ($x['due_on'] && $x['due_on'] <= $today) $dueNow += $x['left']; if ($x['state'] === 'overdue') { $overdue += $x['left']; $overN++; } }
  return ['total' => $total, 'advance' => $a['advance'] !== null ? (int)$a['advance'] : null, 'paid' => $paid, 'remaining' => max(0, $total - $paid), 'due_now' => $dueNow,
          'overdue' => $overdue, 'overdue_n' => $overN, 'schedule' => $schedule, 'unscheduled' => max(0, $total - $schedule), 'pct' => $total > 0 ? min(100, (int)floor($paid * 100 / $total)) : 0];
}
/** Record money received for one instalment: make and issue its invoice if needed, then the payment. */
function installment_pay(array $me, array $a, int $insId, array $in): array {
  $m = orow('SELECT * FROM matters WHERE office_id = :office AND id = :id', ['id' => $a['matter_id']]);
  $x = orow('SELECT * FROM fee_installments WHERE office_id = :office AND id = :id AND agreement_id = :a', ['id' => $insId, 'a' => $a['id']]); if (!$x) return ['error' => 'not_found'];
  $amt = money_in($in['amount'] ?? null); if (!$amt) return ['error' => 'money_bad'];
  $pdo = db(); $pdo->beginTransaction();
  try {
    $iid = (int)$x['invoice_id'];
    if (!$iid) { $iid = invoice_create($me, $m, (int)$x['id'])['id']; $i = orow('SELECT * FROM invoices WHERE office_id = :office AND id = :id', ['id' => $iid]); if ($e = invoice_issue($me, $i)) { $pdo->rollBack(); return ['error' => $e]; } }
    $i = orow('SELECT * FROM invoices WHERE office_id = :office AND id = :id', ['id' => $iid]);
    if ($i['status'] === 'draft') { if ($e = invoice_issue($me, $i)) { $pdo->rollBack(); return ['error' => $e]; } $i = orow('SELECT * FROM invoices WHERE office_id = :office AND id = :id', ['id' => $iid]); }
    $r = payment_add($me, $i, $in); if (isset($r['error'])) { $pdo->rollBack(); return $r; }
    $pdo->commit();
  } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
  return ['id' => $r['id'], 'invoice' => $iid];
}

/* ---------- statements of account ---------- */
/** $scope 'agreement' (one fee agreement) or 'client' (all the client's visible case files). */
function statement_data(?array $me, string $scope, int $id): ?array {
  if ($scope === 'agreement') {
    $a = $me === null ? orow('SELECT * FROM fee_agreements WHERE office_id = :office AND id = :id', ['id' => $id]) : agreement_get_for($id, $me); if (!$a) return null;   // null: the caller (client portal) has already checked access
    $m = orow('SELECT * FROM matters WHERE office_id = :office AND id = :id', ['id' => $a['matter_id']]); $c = contact_get((int)$a['client_id']);
    $invs = orows("SELECT * FROM invoices WHERE office_id = :office AND agreement_id = :a AND status = 'issued' ORDER BY issued_on, id", ['a' => $a['id']]);
    $mids = [(int)$a['matter_id']]; $sum = agreement_summary($a);
  } else {
    $c = contact_get($id); if (!$me || !$c || !can('finance', 'view', $me)) return null;
    $mids = array_map(fn($x) => (int)$x['id'], array_filter(orows('SELECT id FROM matters WHERE office_id = :office AND client_id = :c', ['c' => $id]), fn($x) => matter_for((int)$x['id'], $me)));
    $a = null; $m = null; $sum = null;
    $invs = $mids ? orows("SELECT * FROM invoices WHERE office_id = :office AND status = 'issued' AND matter_id IN (" . implode(',', $mids) . ') ORDER BY issued_on, id') : [];
  }
  $lines = []; $charged = 0; $paid = 0;
  foreach ($invs as $i) {
    $lines[] = ['on' => $i['issued_on'], 'kind' => 'invoice', 'text' => t('st_invoice', $i['number']), 'ref' => $i['number'], 'debit' => (int)$i['total'], 'credit' => 0, 'link' => '/finance/invoices/' . $i['id']]; $charged += (int)$i['total'];
    foreach (orows('SELECT * FROM payments WHERE office_id = :office AND invoice_id = :i AND voided_at IS NULL ORDER BY received_on, id', ['i' => $i['id']]) as $p) {
      $lines[] = ['on' => $p['received_on'], 'kind' => 'payment', 'text' => t('st_payment', t('pm_' . $p['method'])) . ($p['reference'] ? ' — ' . $p['reference'] : ''), 'ref' => $p['receipt_no'], 'debit' => 0, 'credit' => (int)$p['amount'], 'link' => '/finance/invoices/' . $i['id']]; $paid += (int)$p['amount'];
    }
  }
  $exp = ['expense' => 0, 'court_fee' => 0]; $spent = [];
  if ($mids) foreach (orows("SELECT * FROM expenses WHERE office_id = :office AND paid_from = 'office' AND matter_id IN (" . implode(',', $mids) . ') ORDER BY spent_on, id') as $e) {
    $cat = in_array($e['category'], EXPENSE_CATEGORIES, true) ? $e['category'] : 'expense'; $exp[$cat] += (int)$e['amount'];
    $spent[] = ['on' => $e['spent_on'], 'kind' => $cat, 'text' => $e['description'], 'amount' => (int)$e['amount'], 'billed' => (bool)$e['invoice_line_id']];
  }
  usort($lines, fn($x, $y) => [$x['on'], $x['kind'] === 'payment'] <=> [$y['on'], $y['kind'] === 'payment']);
  $bal = 0; foreach ($lines as &$l) { $bal += $l['debit'] - $l['credit']; $l['balance'] = $bal; } unset($l);
  $total = $sum['total'] ?? $charged;
  return ['scope' => $scope, 'a' => $a, 'm' => $m, 'c' => $c, 'lines' => $lines, 'spent' => $spent, 'charged' => $charged, 'paid' => $paid, 'balance' => $charged - $paid,
    'total' => $total, 'remaining' => max(0, $total - $paid), 'court_fees' => $exp['court_fee'], 'expenses' => $exp['expense'], 'difference' => $paid - $exp['court_fee'] - $exp['expense'], 'sum' => $sum];
}
function statement_pdf(array $s): string {
  require_once __DIR__ . '/pdf.php';
  $o = current_office(); $ar = lang() === 'ar'; $pdf = new ImzPdf(false, $ar); $pdf->addPage(); $W = $pdf->w; $x0 = 44; $x1 = $W - 44;
  $X = fn(float $x) => $ar ? $W - $x : $x; $al = $ar ? 'R' : 'L'; $ar2 = $ar ? 'L' : 'R';
  $lh = pdf_logo($pdf, $x0, 36, 40, $ar);
  $pdf->text($X($x1), 52, t('statement'), 17, true, $ar2); $pdf->text($X($x1), 70, date('d/m/Y'), 10, false, $ar2, [0.4, 0.4, 0.45]);
  $y = max(60, 40 + $lh + 14); $pdf->text($X($x0), $y, office_name($o), 12, true, $al); $y += 14;
  $pdf->line($x0, $y, $x1, $y, [0.66, 0.54, 0.31], 0.8); $y += 20;
  $rows = [[t('ct_client'), $s['c'] ? contact_label($s['c']) : '—']];
  if ($s['m']) $rows[] = [t('appt_matter'), $s['m']['ref'] . ' — ' . $s['m']['title']];
  if ($s['a']) $rows[] = [t('fee_agreement'), t('fk_' . $s['a']['kind']) . ($s['a']['signed_on'] ? ' · ' . date('d/m/Y', strtotime($s['a']['signed_on'])) : '')];
  foreach ($rows as [$k, $v]) { $pdf->text($X($x0), $y, $k, 10, true, $al); $pdf->text($X($x0 + 110), $y, $pdf->fit($v, 380, 10), 10, false, $al); $y += 15; }
  $y += 8;
  $boxes = [[t('st_total'), $s['total']], [t('st_paid'), $s['paid']], [t('st_remaining'), $s['remaining']], [t('st_court_fees'), $s['court_fees']], [t('st_expenses'), $s['expenses']]];
  if (empty($s['portal'])) $boxes[] = [t('st_difference'), $s['difference']];   // the office's own margin is not shown to the client
  $bw = ($x1 - $x0) / 3;
  foreach ($boxes as $k => [$lbl, $val]) {
    $bx = $x0 + ($k % 3) * $bw; $by = $y + intdiv($k, 3) * 40;
    $pdf->rect($bx + 2, $by, $bw - 4, 36, [0.97, 0.96, 0.94], [0.85, 0.82, 0.76]);
    $pdf->text($X($bx + 10), $by + 14, $lbl, 8.5, false, $al, [0.4, 0.4, 0.45]); $pdf->text($X($bx + 10), $by + 29, money($val), 11, true, $al);
  }
  $y += 92;
  $cols = [[t('date'), 70], [t('st_desc'), 205], [t('st_ref'), 80], [t('st_debit'), 58], [t('st_credit'), 58], [t('st_balance'), 0]];
  $head = function () use ($pdf, $X, $al, $x0, $x1, $cols, &$y) {
    $pdf->rect($x0, $y - 13, $x1 - $x0, 19, [0.09, 0.09, 0.11]); $x = $x0 + 6;
    foreach ($cols as [$c, $w]) { $pdf->text($X($x), $y, $c, 9, true, $al, [1, 1, 1]); $x += $w; } $y += 20;
  };
  $head();
  if (!$s['lines']) { $pdf->text($X($x0 + 6), $y, t('st_no_moves'), 10, false, $al); $y += 18; }
  foreach ($s['lines'] as $l) {
    if ($y > $pdf->h - 70) { $pdf->addPage(); $y = 60; $head(); }
    $v = [date('d/m/Y', strtotime($l['on'])), $l['text'], (string)$l['ref'], $l['debit'] ? money_plain($l['debit']) : '', $l['credit'] ? money_plain($l['credit']) : '', money_plain($l['balance'])];
    $x = $x0 + 6; foreach ($cols as $i => [, $w]) { $pdf->text($X($x), $y, $pdf->fit($v[$i], ($w ?: 70) - 6, 9), 9, $i === 5, $al); $x += $w; }
    $pdf->line($x0, $y + 6, $x1, $y + 6, [0.9, 0.9, 0.9]); $y += 17;
  }
  if ($s['spent']) {
    $y += 12; if ($y > $pdf->h - 100) { $pdf->addPage(); $y = 60; }
    $pdf->text($X($x0), $y, t('st_spent_title'), 11, true, $al); $y += 16;
    foreach ($s['spent'] as $e) {
      if ($y > $pdf->h - 70) { $pdf->addPage(); $y = 60; }
      $pdf->text($X($x0 + 6), $y, date('d/m/Y', strtotime($e['on'])), 9, false, $al); $pdf->text($X($x0 + 76), $y, $pdf->fit('[' . t('ec_' . $e['kind']) . '] ' . $e['text'], 330, 9), 9, false, $al);
      $pdf->text($X($x1 - 6), $y, money_plain($e['amount']), 9, false, $ar2); $y += 15;
    }
  }
  $pdf->text($X($x0), $pdf->h - 52, $pdf->fit(t('st_note'), $x1 - $x0, 8), 8, false, $al, [0.4, 0.4, 0.45]);
  $pdf->text($W / 2, $pdf->h - 34, t('pdf_generated', date('d/m/Y H:i')), 8, false, 'C', [0.45, 0.45, 0.5]);
  return $pdf->output(t('statement'));
}
/** Share a statement with the client portal (logged). The portal shows it live from the same data. */
function statement_share(array $me, array $a, bool $on): ?string {
  $m = orow('SELECT * FROM matters WHERE office_id = :office AND id = :id', ['id' => $a['matter_id']]);
  if (!can_approve_update($me, $m) && !can('finance', 'edit', $me)) return 'act_share_forbidden';
  o_insert('portal_shares', ['matter_id' => $m['id'], 'kind' => 'statement', 'item_id' => $a['id'], 'shared' => $on ? 1 : 0, 'staff_id' => $me['id'], 'at' => now()]);
  matter_event((int)$m['id'], $me, 'item_share', null, ['kind' => 'statement', 'title' => t('statement'), 'on' => $on]);
  law_audit($on ? 'statement_shared' : 'statement_unshared', 'fee_agreement', $a['id'], [], null, $me);
  if ($on) portal_notify_matter($m);
  return null;
}
function statement_shared(int $agreementId): bool {
  $r = orow("SELECT shared FROM portal_shares WHERE office_id = :office AND kind = 'statement' AND item_id = :a ORDER BY id DESC LIMIT 1", ['a' => $agreementId]);
  return $r && (int)$r['shared'] === 1;
}

/* ---------- due notices ---------- */
function notice_get(int $id, array $me): ?array { $n = orow('SELECT * FROM due_notices WHERE office_id = :office AND id = :id', ['id' => $id]); return $n && finance_matter((int)$n['matter_id'], $me) ? notice_refresh($n) : null; }
/** A draft follows the money: its amount is what is still unpaid now (payments may have come in since it was prepared). */
function notice_refresh(array $n): array {
  if ($n['status'] !== 'draft') return $n;
  $left = null;
  if ($n['installment_id']) { $x = orow('SELECT agreement_id FROM fee_installments WHERE office_id = :office AND id = :id', ['id' => $n['installment_id']]);
    if ($x) foreach (installments_state((int)$x['agreement_id']) as $r) if ((int)$r['id'] === (int)$n['installment_id']) $left = $r['left']; }
  elseif ($n['invoice_id']) { $i = orow('SELECT total, paid FROM invoices WHERE office_id = :office AND id = :id', ['id' => $n['invoice_id']]); if ($i) $left = (int)$i['total'] - (int)$i['paid']; }
  if ($left !== null && $left !== (int)$n['amount']) { oq('UPDATE due_notices SET amount = :a WHERE office_id = :office AND id = :id', ['a' => max(0, $left), 'id' => $n['id']]); $n['amount'] = max(0, $left); }
  return $n;
}
function notices_for_matter(int $matterId): array { return orows('SELECT * FROM due_notices WHERE office_id = :office AND matter_id = :m ORDER BY id DESC', ['m' => $matterId]); }
/** A draft notice for an instalment's unpaid part or an issued invoice's balance. */
function notice_create(array $me, ?array $a, ?int $insId, ?int $invoiceId, bool $auto = false): array {
  if ($insId && $a) {
    $x = null; foreach (installments_state((int)$a['id']) as $r) if ((int)$r['id'] === $insId) $x = $r;
    if (!$x) return ['error' => 'not_found']; if ($x['left'] <= 0) return ['error' => 'notice_nothing_due'];
    $d = ['client_id' => $a['client_id'], 'matter_id' => $a['matter_id'], 'installment_id' => $insId, 'invoice_id' => $x['invoice_id'] ?: null, 'amount' => $x['left'], 'due_on' => $x['due_on']];
  } elseif ($invoiceId) {
    $i = invoice_get_for($invoiceId, $me); if (!$i || $i['status'] !== 'issued') return ['error' => 'invoice_not_issued'];
    $left = (int)$i['total'] - (int)$i['paid']; if ($left <= 0) return ['error' => 'notice_nothing_due'];
    $d = ['client_id' => $i['client_id'], 'matter_id' => $i['matter_id'], 'installment_id' => null, 'invoice_id' => $i['id'], 'amount' => $left, 'due_on' => $i['due_on']];
  } else return ['error' => 'not_found'];
  $id = o_insert('due_notices', $d + ['status' => 'draft', 'auto' => $auto ? 1 : 0, 'created_by' => $auto ? null : $me['id'], 'created_at' => now()]);
  law_audit('notice_drafted', 'due_notice', $id, ['amount' => $d['amount'], 'auto' => $auto], null, $auto ? ['id' => null, 'full_name' => 'system', 'email' => ''] : $me);
  return ['id' => $id];
}
/** Send (or record) a notice. email: generic message to the client; portal: shown in the portal; manual: recorded only. */
function notice_send(array $me, array $n, string $channel, string $note = ''): ?string {
  if ($n['status'] !== 'draft') return 'notice_not_draft';
  $n = notice_refresh($n); if ((int)$n['amount'] <= 0) return 'notice_nothing_due';
  if (!in_array($channel, NOTICE_CHANNELS, true)) return 'notice_channel_required';
  $c = contact_get((int)$n['client_id']); $delivery = 'recorded';
  if ($channel === 'email') {
    $pu = portal_user_for_contact((int)$n['client_id']); $to = $pu['email'] ?? ($c['email'] ?? '');
    if (!$to) return 'notice_no_email';
    $GLOBALS['LANG_FORCE'] = $pu['lang'] ?? ($c['pref_lang'] ?? 'ar');
    $ok = send_mail($to, t('mail_notice_subject', office_name()), t('mail_notice_body', office_name()) . ($pu && $pu['status'] === 'active' ? "\n\n" . abs_url(portal_base() . '/login') : ''));
    unset($GLOBALS['LANG_FORCE']); $delivery = $ok ? 'sent' : 'failed';
    if (!$ok) { law_audit('notice_failed', 'due_notice', $n['id'], ['channel' => $channel], null, $me); return 'notice_send_failed'; }
  } elseif ($channel === 'portal') {
    $pu = portal_user_for_contact((int)$n['client_id']); if (!$pu || $pu['status'] !== 'active') return 'notice_no_portal';
    $delivery = 'in_portal';
  }
  $note = mb_substr(trim($note), 0, 300);
  o_update('due_notices', (int)$n['id'], ['status' => 'sent', 'channel' => $channel, 'delivery' => $delivery, 'sent_at' => now(), 'sent_by' => $me['id']]);
  matter_event((int)$n['matter_id'], $me, 'notice_sent', $note ?: null, ['notice' => (int)$n['id'], 'amount' => (int)$n['amount'], 'channel' => $channel]);
  law_audit('notice_sent', 'due_notice', $n['id'], ['channel' => $channel, 'delivery' => $delivery], null, $me);
  return null;
}
function notice_discard(array $me, array $n): ?string {
  if ($n['status'] !== 'draft') return 'notice_not_draft';
  o_update('due_notices', (int)$n['id'], ['status' => 'void']);
  law_audit('notice_discarded', 'due_notice', $n['id'], [], null, $me);
  return null;
}
function notice_pdf(array $n): string {
  require_once __DIR__ . '/pdf.php';
  $o = current_office(); $ar = lang() === 'ar'; $pdf = new ImzPdf(false, $ar); $pdf->addPage(); $W = $pdf->w; $x0 = 56; $x1 = $W - 56;
  $X = fn(float $x) => $ar ? $W - $x : $x; $al = $ar ? 'R' : 'L';
  $lh = pdf_logo($pdf, $x0, 40, 46, $ar); $y = 46 + max($lh, 10) + 16;
  $pdf->text($X($x0), $y, office_name($o), 13, true, $al); $y += 12; $pdf->line($x0, $y, $x1, $y, [0.66, 0.54, 0.31], 0.8); $y += 34;
  $pdf->text($X($x0), $y, t('notice_title'), 18, true, $al); $y += 30;
  $c = contact_get((int)$n['client_id']); $m = orow('SELECT ref, title, official_no FROM matters WHERE office_id = :office AND id = :id', ['id' => $n['matter_id']]);
  $pdf->rect($x0, $y - 4, $x1 - $x0, 66, [0.97, 0.96, 0.94], [0.85, 0.82, 0.76]);
  $pdf->text($X($x0 + 14), $y + 16, t('notice_amount'), 10, false, $al, [0.4, 0.4, 0.45]); $pdf->text($X($x0 + 14), $y + 44, money((int)$n['amount']), 22, true, $al); $y += 90;
  $rows = [[t('ct_client'), $c ? contact_label($c) : '—'], [t('notice_due_on'), $n['due_on'] ? date('d/m/Y', strtotime($n['due_on'])) : '—'], [t('appt_matter'), $m['ref'] . ($m['official_no'] ? ' · ' . $m['official_no'] : '')]];
  foreach ($rows as [$k, $v]) { $pdf->text($X($x0), $y, $k, 11, true, $al); $pdf->text($X($x0 + 130), $y, $pdf->fit($v, 330, 11), 11, false, $al); $y += 20; }
  $y += 20; foreach (pdf_wrap($pdf, t('notice_text', office_name($o)), $x1 - $x0, 10.5) as $ln) { $pdf->text($X($x0), $y, $ln, 10.5, false, $al); $y += 16; }
  if ($o['invoice_footer']) $pdf->text($X($x0), $pdf->h - 60, $pdf->fit($o['invoice_footer'], $x1 - $x0, 9), 9, false, $al);
  $pdf->text($W / 2, $pdf->h - 40, t('pdf_generated', date('d/m/Y H:i')), 8, false, 'C', [0.45, 0.45, 0.5]);
  return $pdf->output(t('notice_title'));
}
/** Daily: draft notices for instalments and invoices due in exactly one of the office's notice days; tell the finance team. */
function notices_auto(?string $today = null): int {
  $nd = row('SELECT notice_days FROM offices WHERE id=?', [office_id()])['notice_days'] ?? ''; $days = array_filter(array_map('intval', explode(',', (string)$nd)), fn($d) => $d >= 0 && $d <= 60); if (!$days) return 0;
  $today = $today ?? date('Y-m-d'); $n = 0;
  foreach ($days as $dd) {
    $on = date('Y-m-d', strtotime("$today +$dd days"));
    foreach (orows('SELECT i.*, a.matter_id, a.client_id FROM fee_installments i JOIN fee_agreements a ON a.id = i.agreement_id AND a.office_id = i.office_id WHERE i.office_id = :office AND i.due_on = :d', ['d' => $on]) as $x) {
      if (orow("SELECT id FROM due_notices WHERE office_id = :office AND installment_id = :i AND due_on = :d AND status <> 'void'", ['i' => $x['id'], 'd' => $on])) continue;
      $a = orow('SELECT * FROM fee_agreements WHERE office_id = :office AND id = :id', ['id' => $x['agreement_id']]);
      $r = notice_create(['id' => null], $a, (int)$x['id'], null, true); if (isset($r['id'])) $n++;
    }
    foreach (orows("SELECT * FROM invoices WHERE office_id = :office AND status = 'issued' AND paid < total AND due_on = :d", ['d' => $on]) as $i) {
      if (orow("SELECT id FROM due_notices WHERE office_id = :office AND (invoice_id = :i OR installment_id IN (SELECT installment_id FROM invoice_lines WHERE office_id = :office AND invoice_id = :i AND installment_id IS NOT NULL)) AND due_on = :d AND status <> 'void'", ['i' => $i['id'], 'd' => $on])) continue;
      $left = (int)$i['total'] - (int)$i['paid'];
      $id = o_insert('due_notices', ['client_id' => $i['client_id'], 'matter_id' => $i['matter_id'], 'installment_id' => null, 'invoice_id' => $i['id'], 'amount' => $left, 'due_on' => $on, 'status' => 'draft', 'auto' => 1, 'created_by' => null, 'created_at' => now()]);
      law_audit('notice_drafted', 'due_notice', $id, ['amount' => $left, 'auto' => true], null, ['id' => null, 'full_name' => 'system', 'email' => '']); $n++;
    }
  }
  if ($n) foreach (orows("SELECT s.id, s.role FROM staff s WHERE s.office_id = :office AND s.status = 'active'") as $s) {
    if (can('finance', 'create', orow('SELECT * FROM staff WHERE office_id = :office AND id = :id', ['id' => $s['id']]))) notify((int)$s['id'], 'notice', 'n_notices_drafted', [$n], '/finance/notices', 'notices:' . $today);
  }
  return $n;
}
function notice_list(array $me, string $status = 'draft'): array {
  [$vis, $p] = linked_visible_sql($me);
  $w = $status === 'all' ? '' : ' AND x.status = :st'; if ($status !== 'all') $p['st'] = $status;
  return array_map('notice_refresh', orows("SELECT x.*, m.ref AS matter_ref, c.name_ar, c.name_en FROM due_notices x JOIN matters m ON m.id = x.matter_id AND m.office_id = x.office_id JOIN contacts c ON c.id = x.client_id AND c.office_id = x.office_id
    WHERE x.office_id = :office AND $vis$w ORDER BY x.id DESC LIMIT 200", $p));
}

/* ---------- financial reports ---------- */
function fin_report(array $me, string $kind, int $year): array {
  [$vis, $p] = linked_visible_sql($me); $today = date('Y-m-d');
  $mj = 'JOIN matters m ON m.id = x.matter_id AND m.office_id = x.office_id';
  if ($kind === 'contracts') {
    $rows = orows("SELECT x.*, m.ref AS matter_ref, m.title AS matter_title, c.name_ar, c.name_en FROM fee_agreements x $mj JOIN contacts c ON c.id = x.client_id AND c.office_id = x.office_id WHERE x.office_id = :office AND $vis ORDER BY x.id DESC", $p);
    $tot = ['total' => 0, 'paid' => 0, 'remaining' => 0, 'overdue' => 0];
    foreach ($rows as &$r) { $r['s'] = agreement_summary($r); foreach ($tot as $k => $_) $tot[$k] += $r['s'][$k]; } unset($r);
    return ['rows' => $rows, 'tot' => $tot];
  }
  if ($kind === 'aging') {
    $b = ['current' => 0, 'd30' => 0, 'd60' => 0, 'd90' => 0, 'd90p' => 0]; $rows = [];
    foreach (orows("SELECT x.*, m.ref AS matter_ref, c.name_ar, c.name_en FROM invoices x $mj JOIN contacts c ON c.id = x.client_id AND c.office_id = x.office_id WHERE x.office_id = :office AND $vis AND x.status = 'issued' AND x.paid < x.total ORDER BY x.due_on", $p) as $i) {
      $late = $i['due_on'] ? (int)floor((strtotime($today) - strtotime($i['due_on'])) / 86400) : 0;
      $k = $late <= 0 ? 'current' : ($late <= 30 ? 'd30' : ($late <= 60 ? 'd60' : ($late <= 90 ? 'd90' : 'd90p')));
      $left = (int)$i['total'] - (int)$i['paid']; $b[$k] += $left; $i['bucket'] = $k; $i['late'] = max(0, $late); $i['left'] = $left; $rows[] = $i;
    }
    return ['rows' => $rows, 'buckets' => $b, 'total' => array_sum($b)];
  }
  // monthly income and expenses for the year, and profit and loss
  $months = []; for ($i = 1; $i <= 12; $i++) $months[$i] = ['income' => 0, 'expense' => 0, 'court_fee' => 0];
  $f = "$year-01-01"; $t = ($year + 1) . '-01-01';
  foreach (orows("SELECT y.received_on d, y.amount a FROM payments y JOIN invoices x ON x.id = y.invoice_id AND x.office_id = y.office_id $mj WHERE y.office_id = :office AND $vis AND y.voided_at IS NULL AND y.received_on >= :f AND y.received_on < :t", $p + ['f' => $f, 't' => $t]) as $r) $months[(int)substr($r['d'], 5, 2)]['income'] += (int)$r['a'];
  foreach (orows("SELECT x.spent_on d, x.amount a, x.category c FROM expenses x $mj WHERE x.office_id = :office AND $vis AND x.paid_from = 'office' AND x.spent_on >= :f AND x.spent_on < :t", $p + ['f' => $f, 't' => $t]) as $r) $months[(int)substr($r['d'], 5, 2)][$r['c'] === 'court_fee' ? 'court_fee' : 'expense'] += (int)$r['a'];
  $y = ['income' => 0, 'expense' => 0, 'court_fee' => 0];
  foreach ($months as &$mo) { $mo['net'] = $mo['income'] - $mo['expense'] - $mo['court_fee']; foreach ($y as $k => $_) $y[$k] += $mo[$k]; } unset($mo);
  $y['net'] = $y['income'] - $y['expense'] - $y['court_fee'];
  return ['months' => $months, 'year' => $y];
}
