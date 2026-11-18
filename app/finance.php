<?php
/* ==========================================================================
   Fees and money.
   - Amounts are whole baisa (1 OMR = 1000 baisa); typed as 12.500 etc.
   - Fee agreements per case file: fixed, instalments, hourly or other, with
     instalments that can each become an invoice.
   - Invoices start as drafts (editable, no number). Issuing gives the next
     office number (INV-YYYY-NNNN) and fixes the content. An issued invoice
     is never edited or deleted — it can only be voided with a reason, after
     its payments are voided.
   - Payments get a receipt number (RC-YYYY-NNNN); they never exceed what is
     owed and are voided (with a reason), not deleted.
   - Expenses on a case are kept apart from fees, and can be billed.
   - Client trust money (amounts held for a client) is a separate ledger:
     money out can never exceed the client's balance.
   - Tax is off (0%) until the office sets it after advice from its own
     accountant. No online payment is offered: it is not set up.
   ========================================================================== */
declare(strict_types=1);

const AGREEMENT_KINDS = ['fixed', 'installments', 'hourly', 'other'];
const PAY_METHODS = ['cash', 'transfer', 'card', 'cheque'];

/** "12.5", "١٢٫٥٠٠", "1,250.000" → baisa. Null when not a valid amount. */
function money_in(?string $s): ?int {
  $s = str_replace(['٫', ',', ' ', 'ر.ع', 'OMR'], ['.', '', '', '', ''], digits_only_keep(trim((string)$s)));
  if ($s === '' || !preg_match('/^\d{1,9}(\.\d{1,3})?$/', $s)) return null;
  [$a, $b] = array_pad(explode('.', $s), 2, ''); return (int)$a * 1000 + (int)str_pad($b, 3, '0');
}
function money(int $baisa, bool $unit = true): string {
  $dp = function_exists('country_pack') && current_office() ? (int)country_pack()['decimals'] : 3;   // amounts are kept in thousandths; shown with the country's decimals
  $frac = str_pad((string)(abs($baisa) % 1000), 3, '0', STR_PAD_LEFT);
  $s = ($baisa < 0 ? '-' : '') . number_format(intdiv(abs($baisa), 1000)) . ($dp > 0 ? '.' . substr($frac, 0, $dp) : '');
  return $unit ? $s . ' ' . t('currency') : $s;
}
function money_plain(int $baisa): string { return money($baisa, false); }

/** Invoice state shown to people: draft, unpaid, partial, paid, overdue, void. */
function invoice_state(array $i): string {
  if ($i['status'] === 'draft' || $i['status'] === 'void') return $i['status'];
  if ((int)$i['paid'] >= (int)$i['total']) return 'paid';
  if ($i['due_on'] && $i['due_on'] < date('Y-m-d')) return 'overdue';
  return (int)$i['paid'] > 0 ? 'partial' : 'unpaid';
}
function finance_matter(int $matterId, array $me): ?array { return can('finance', 'view', $me) ? matter_for($matterId, $me) : null; }
function invoice_get_for(int $id, array $me): ?array {
  $i = orow('SELECT * FROM invoices WHERE office_id = :office AND id = :id', ['id' => $id]);
  return $i && finance_matter((int)$i['matter_id'], $me) ? $i : null;
}
function invoice_lines(int $id): array { return orows('SELECT * FROM invoice_lines WHERE office_id = :office AND invoice_id = :i ORDER BY id', ['i' => $id]); }
function invoice_payments(int $id): array { return orows('SELECT * FROM payments WHERE office_id = :office AND invoice_id = :i ORDER BY id', ['i' => $id]); }
function invoice_recalc(int $id): array {
  $i = orow('SELECT * FROM invoices WHERE office_id = :office AND id = :id', ['id' => $id]);
  $sub = (int)orow('SELECT COALESCE(SUM(amount),0) s FROM invoice_lines WHERE office_id = :office AND invoice_id = :i', ['i' => $id])['s'];
  $taxable = (int)orow("SELECT COALESCE(SUM(amount),0) s FROM invoice_lines WHERE office_id = :office AND invoice_id = :i AND kind <> 'expense'", ['i' => $id])['s'];   // tax on fees, not on disbursements
  $tax = intdiv($taxable * (int)$i['tax_rate_bp'] + 5000, 10000);
  $paid = (int)orow('SELECT COALESCE(SUM(amount),0) s FROM payments WHERE office_id = :office AND invoice_id = :i AND voided_at IS NULL', ['i' => $id])['s'];
  o_update('invoices', $id, ['subtotal' => $sub, 'tax' => $tax, 'total' => $sub + $tax, 'paid' => $paid, 'updated_at' => now()]);
  return orow('SELECT * FROM invoices WHERE office_id = :office AND id = :id', ['id' => $id]);
}

/* ---------- agreements ---------- */
function agreement_save(array $me, array $m, array $in, ?array $old = null): array {
  $kind = in_array($in['kind'] ?? '', AGREEMENT_KINDS, true) ? $in['kind'] : null; if (!$kind) return ['error' => 'fee_kind_required'];
  $total = ($in['total'] ?? '') === '' ? null : money_in($in['total']); if (($in['total'] ?? '') !== '' && $total === null) return ['error' => 'money_bad'];
  $rate = ($in['hourly_rate'] ?? '') === '' ? null : money_in($in['hourly_rate']); if (($in['hourly_rate'] ?? '') !== '' && $rate === null) return ['error' => 'money_bad'];
  $doc = (int)($in['document_id'] ?? 0) ?: null; if ($doc && !orow('SELECT id FROM documents WHERE office_id = :office AND id = :id AND matter_id = :m', ['id' => $doc, 'm' => $m['id']])) return ['error' => 'fee_doc_bad'];
  $adv = ($in['advance'] ?? '') === '' ? null : money_in($in['advance']); if (($in['advance'] ?? '') !== '' && $adv === null) return ['error' => 'money_bad'];
  if ($adv !== null && $total !== null && $adv > $total) return ['error' => 'fee_advance_over'];
  $d = ['kind' => $kind, 'total' => $total, 'advance' => $adv, 'hourly_rate' => $rate, 'terms' => mb_substr(trim((string)($in['terms'] ?? '')), 0, 8000) ?: null, 'signed_on' => valid_date($in['signed_on'] ?? null), 'document_id' => $doc, 'updated_at' => now()];
  if ($old) { o_update('fee_agreements', (int)$old['id'], $d); law_audit('fee_agreement_changed', 'fee_agreement', $old['id'], [], null, $me); return ['id' => (int)$old['id']]; }
  $id = o_insert('fee_agreements', $d + ['matter_id' => $m['id'], 'client_id' => $m['client_id'], 'status' => 'active', 'created_by' => $me['id'], 'created_at' => now()]);
  matter_event((int)$m['id'], $me, 'fee_agreement', null, ['agreement' => $id, 'kind' => $kind]);
  law_audit('fee_agreement_added', 'fee_agreement', $id, ['matter' => $m['id'], 'kind' => $kind], null, $me);
  return ['id' => $id];
}
function agreement_get_for(int $id, array $me): ?array { $a = orow('SELECT * FROM fee_agreements WHERE office_id = :office AND id = :id', ['id' => $id]); return $a && finance_matter((int)$a['matter_id'], $me) ? $a : null; }
function installment_add(array $me, array $a, string $label, ?string $due, ?string $amount): ?string {
  $amt = money_in($amount); if (!$amt) return 'money_bad';
  $label = mb_substr(trim($label), 0, 160); if ($label === '') return 'fee_label_required';
  o_insert('fee_installments', ['agreement_id' => $a['id'], 'label' => $label, 'due_on' => valid_date($due), 'amount' => $amt]);
  law_audit('fee_installment_added', 'fee_agreement', $a['id'], ['amount' => $amt], null, $me);
  return null;
}

/* ---------- invoices ---------- */
function invoice_create(array $me, array $m, ?int $installmentId = null): array {
  $o = current_office();
  $id = o_insert('invoices', ['matter_id' => $m['id'], 'client_id' => $m['client_id'], 'status' => 'draft', 'tax_rate_bp' => (int)$o['tax_rate_bp'], 'created_by' => $me['id'], 'created_at' => now(), 'updated_at' => now()]);
  if ($installmentId) {
    $ins = orow('SELECT i.* FROM fee_installments i JOIN fee_agreements a ON a.id = i.agreement_id AND a.office_id = i.office_id WHERE i.office_id = :office AND i.id = :id AND a.matter_id = :m AND i.invoice_id IS NULL', ['id' => $installmentId, 'm' => $m['id']]);
    if ($ins) {
      o_insert('invoice_lines', ['invoice_id' => $id, 'kind' => 'fee', 'description' => $ins['label'], 'qty' => 1, 'unit' => $ins['amount'], 'amount' => $ins['amount'], 'installment_id' => $ins['id']]);
      o_update('fee_installments', (int)$ins['id'], ['invoice_id' => $id]); o_update('invoices', $id, ['agreement_id' => $ins['agreement_id'], 'due_on' => $ins['due_on']]);   // the invoice falls due when the instalment does
    }
  }
  invoice_recalc($id);
  law_audit('invoice_draft_created', 'invoice', $id, ['matter' => $m['id']], null, $me);
  return ['id' => $id];
}
function invoice_add_line(array $me, array $i, string $kind, string $desc, string $qty, string $unit): ?string {
  if ($i['status'] !== 'draft') return 'invoice_not_draft';
  $kind = in_array($kind, ['fee', 'expense', 'other'], true) ? $kind : 'fee';
  $desc = mb_substr(trim($desc), 0, 300); if ($desc === '') return 'invoice_line_desc';
  $q = max(1, min(10000, (int)digits_only_keep($qty ?: '1'))); $u = money_in($unit); if ($u === null || $u <= 0) return 'money_bad';
  o_insert('invoice_lines', ['invoice_id' => $i['id'], 'kind' => $kind, 'description' => $desc, 'qty' => $q, 'unit' => $u, 'amount' => $q * $u]);
  invoice_recalc((int)$i['id']); return null;
}
function invoice_remove_line(array $me, array $i, int $lineId): ?string {
  if ($i['status'] !== 'draft') return 'invoice_not_draft';
  $l = orow('SELECT * FROM invoice_lines WHERE office_id = :office AND id = :id AND invoice_id = :i', ['id' => $lineId, 'i' => $i['id']]); if (!$l) return 'not_found';
  oq('DELETE FROM invoice_lines WHERE office_id = :office AND id = :id', ['id' => $lineId]);
  if ($l['expense_id']) o_update('expenses', (int)$l['expense_id'], ['invoice_line_id' => null]);
  if ($l['installment_id']) o_update('fee_installments', (int)$l['installment_id'], ['invoice_id' => null]);
  invoice_recalc((int)$i['id']); return null;
}
/** Add the case's billable expenses not billed yet. */
function invoice_add_expenses(array $me, array $i): int {
  if ($i['status'] !== 'draft') return 0; $n = 0;
  foreach (orows('SELECT * FROM expenses WHERE office_id = :office AND matter_id = :m AND billable = 1 AND invoice_line_id IS NULL', ['m' => $i['matter_id']]) as $e) {
    $lid = o_insert('invoice_lines', ['invoice_id' => $i['id'], 'kind' => 'expense', 'description' => $e['description'], 'qty' => 1, 'unit' => $e['amount'], 'amount' => $e['amount'], 'expense_id' => $e['id']]);
    o_update('expenses', (int)$e['id'], ['invoice_line_id' => $lid]); $n++;
  }
  invoice_recalc((int)$i['id']); return $n;
}
function invoice_update_draft(array $me, array $i, array $in): ?string {
  if ($i['status'] !== 'draft') return 'invoice_not_draft';
  o_update('invoices', (int)$i['id'], ['due_on' => valid_date($in['due_on'] ?? null), 'notes' => mb_substr(trim((string)($in['notes'] ?? '')), 0, 2000) ?: null, 'updated_at' => now()]);
  return null;
}
function invoice_issue(array $me, array $i): ?string {
  if ($i['status'] !== 'draft') return 'invoice_not_draft';
  $i = invoice_recalc((int)$i['id']); if ((int)$i['total'] <= 0) return 'invoice_empty';
  $pdo = db(); $own = !$pdo->inTransaction(); if ($own) $pdo->beginTransaction();
  try {
    $num = next_ref('INV', 'INV');
    o_update('invoices', (int)$i['id'], ['number' => $num, 'status' => 'issued', 'issued_on' => date('Y-m-d'), 'due_on' => $i['due_on'] ?: date('Y-m-d', strtotime('+14 days')), 'issued_by' => $me['id'], 'updated_at' => now()]);
    if ($own) $pdo->commit();
  } catch (Throwable $e) { if ($own) $pdo->rollBack(); throw $e; }
  matter_event((int)$i['matter_id'], $me, 'invoice_issued', null, ['invoice' => (int)$i['id'], 'number' => $num, 'total' => (int)$i['total']]);
  law_audit('invoice_issued', 'invoice', $i['id'], ['number' => $num, 'total' => (int)$i['total']], null, $me);
  return null;
}
function invoice_void(array $me, array $i, string $reason): ?string {
  if ($i['status'] !== 'issued') return 'invoice_not_issued';
  if ((int)$i['paid'] > 0) return 'invoice_has_payments';
  $reason = mb_substr(trim($reason), 0, 300); if ($reason === '') return 'reason_required';
  o_update('invoices', (int)$i['id'], ['status' => 'void', 'void_reason' => $reason, 'portal_visible' => 0, 'updated_at' => now()]);
  foreach (orows('SELECT * FROM invoice_lines WHERE office_id = :office AND invoice_id = :i', ['i' => $i['id']]) as $l) {
    if ($l['expense_id']) o_update('expenses', (int)$l['expense_id'], ['invoice_line_id' => null]);
    if ($l['installment_id']) o_update('fee_installments', (int)$l['installment_id'], ['invoice_id' => null]);
  }
  law_audit('invoice_voided', 'invoice', $i['id'], ['number' => $i['number']], null, $me);
  return null;
}
function invoice_delete_draft(array $me, array $i): ?string {
  if ($i['status'] !== 'draft') return 'invoice_not_draft';
  foreach (invoice_lines((int)$i['id']) as $l) invoice_remove_line($me, $i, (int)$l['id']);
  oq('DELETE FROM invoices WHERE office_id = :office AND id = :id AND status = :s', ['id' => $i['id'], 's' => 'draft']);
  law_audit('invoice_draft_deleted', 'invoice', $i['id'], [], null, $me);
  return null;
}
function invoice_set_portal(array $me, array $i, bool $on): ?string {
  if ($on && $i['status'] !== 'issued') return 'invoice_not_issued';
  o_update('invoices', (int)$i['id'], ['portal_visible' => $on ? 1 : 0, 'updated_at' => now()]);
  if ((bool)$i['portal_visible'] !== $on) share_mark($me, (int)$i['matter_id'], 'invoice', (int)$i['id'], $on);
  law_audit('invoice_portal', 'invoice', $i['id'], ['on' => $on], null, $me);
  return null;
}

/* ---------- payments ---------- */
function payment_add(array $me, array $i, array $in): array {
  if ($i['status'] !== 'issued') return ['error' => 'invoice_not_issued'];
  $amt = money_in($in['amount'] ?? null); if (!$amt) return ['error' => 'money_bad'];
  $due = (int)$i['total'] - (int)$i['paid']; if ($amt > $due) return ['error' => 'payment_too_much'];
  $method = in_array($in['method'] ?? '', PAY_METHODS, true) ? $in['method'] : null; if (!$method) return ['error' => 'payment_method_required'];
  $on = valid_date($in['received_on'] ?? null) ?? date('Y-m-d'); if ($on > date('Y-m-d')) return ['error' => 'payment_future'];
  $id = o_insert('payments', ['invoice_id' => $i['id'], 'amount' => $amt, 'method' => $method, 'reference' => mb_substr(trim((string)($in['reference'] ?? '')), 0, 120) ?: null,
    'received_on' => $on, 'receipt_no' => next_ref('RC', 'RC'), 'note' => mb_substr(trim((string)($in['note'] ?? '')), 0, 300) ?: null, 'created_by' => $me['id'], 'created_at' => now()]);
  invoice_recalc((int)$i['id']);
  law_audit('payment_recorded', 'invoice', $i['id'], ['payment' => $id, 'amount' => $amt, 'method' => $method], null, $me);
  return ['id' => $id];
}
function payment_void(array $me, array $i, int $pid, string $reason): ?string {
  $reason = mb_substr(trim($reason), 0, 300); if ($reason === '') return 'reason_required';
  if (!oq('UPDATE payments SET voided_at = :now, voided_by = :me, void_reason = :r WHERE office_id = :office AND id = :id AND invoice_id = :i AND voided_at IS NULL', ['now' => now(), 'me' => $me['id'], 'r' => $reason, 'id' => $pid, 'i' => $i['id']])->rowCount()) return 'not_found';
  invoice_recalc((int)$i['id']);
  law_audit('payment_voided', 'invoice', $i['id'], ['payment' => $pid], null, $me);
  return null;
}

/* ---------- expenses and trust ---------- */
function trust_balance(int $clientId): int {
  return (int)orow("SELECT COALESCE(SUM(CASE WHEN kind = 'in' THEN amount ELSE -amount END),0) b FROM trust_entries WHERE office_id = :office AND client_id = :c", ['c' => $clientId])['b'];
}
function trust_add(array $me, int $clientId, ?int $matterId, string $kind, ?string $amount, ?string $on, string $desc, string $ref): array {
  $c = contact_get($clientId); if (!$c) return ['error' => 'matter_client_required'];
  if ($matterId && !finance_matter($matterId, $me)) return ['error' => 'appt_matter_bad'];
  if (!in_array($kind, ['in', 'out'], true)) return ['error' => 'not_found'];
  $amt = money_in($amount); if (!$amt) return ['error' => 'money_bad'];
  $desc = mb_substr(trim($desc), 0, 300); if ($desc === '') return ['error' => 'invoice_line_desc'];
  $pdo = db(); $own = !$pdo->inTransaction(); if ($own) $pdo->beginTransaction();
  try {
    if (!is_sqlite()) q('SELECT id FROM contacts WHERE id=? FOR UPDATE', [$clientId]);   // one balance change at a time per client
    if ($kind === 'out' && $amt > trust_balance($clientId)) { if ($own) $pdo->rollBack(); return ['error' => 'trust_insufficient']; }
    $id = o_insert('trust_entries', ['client_id' => $clientId, 'matter_id' => $matterId, 'kind' => $kind, 'amount' => $amt, 'on_date' => valid_date($on) ?? date('Y-m-d'),
      'description' => $desc, 'reference' => mb_substr(trim($ref), 0, 120) ?: null, 'created_by' => $me['id'], 'created_at' => now()]);
    if ($own) $pdo->commit();
  } catch (Throwable $e) { if ($own && $pdo->inTransaction()) $pdo->rollBack(); throw $e; }
  law_audit('trust_' . $kind, 'contact', $clientId, ['entry' => $id, 'amount' => $amt, 'matter' => $matterId], null, $me);
  return ['id' => $id];
}
function expense_add(array $me, array $m, array $in): array {
  $amt = money_in($in['amount'] ?? null); if (!$amt) return ['error' => 'money_bad'];
  $desc = mb_substr(trim((string)($in['description'] ?? '')), 0, 300); if ($desc === '') return ['error' => 'invoice_line_desc'];
  $from = ($in['paid_from'] ?? 'office') === 'trust' ? 'trust' : 'office';
  $pdo = db(); $pdo->beginTransaction();
  try {
    $trust = null;
    if ($from === 'trust') { $r = trust_add($me, (int)$m['client_id'], (int)$m['id'], 'out', $in['amount'] ?? null, $in['spent_on'] ?? null, $desc, ''); if (isset($r['error'])) { $pdo->rollBack(); return $r; } $trust = $r['id']; }
    $id = o_insert('expenses', ['matter_id' => $m['id'], 'description' => $desc, 'amount' => $amt, 'spent_on' => valid_date($in['spent_on'] ?? null) ?? date('Y-m-d'),
      'paid_from' => $from, 'category' => ($in['category'] ?? '') === 'court_fee' ? 'court_fee' : 'expense', 'billable' => $from === 'office' && !empty($in['billable']) ? 1 : 0, 'trust_entry_id' => $trust, 'created_by' => $me['id'], 'created_at' => now()]);
    $pdo->commit();
  } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
  law_audit('expense_added', 'matter', $m['id'], ['expense' => $id, 'amount' => $amt, 'from' => $from], null, $me);
  return ['id' => $id];
}

/* ---------- lists and totals ---------- */
function invoice_list(array $me, array $f, int $per = 50): array {
  [$vis, $p] = linked_visible_sql($me);
  $w = ['x.office_id = :office', $vis];
  $st = $f['state'] ?? '';
  if ($st === 'draft' || $st === 'void') { $w[] = 'x.status = :st'; $p['st'] = $st; }
  elseif ($st === 'open') $w[] = "x.status = 'issued' AND x.paid < x.total";
  elseif ($st === 'overdue') { $w[] = "x.status = 'issued' AND x.paid < x.total AND x.due_on < :today"; $p['today'] = date('Y-m-d'); }
  elseif ($st === 'paid') $w[] = "x.status = 'issued' AND x.paid >= x.total";
  if (!empty($f['matter'])) { $w[] = 'x.matter_id = :mt'; $p['mt'] = (int)$f['matter']; }
  if (!empty($f['client'])) { $w[] = 'x.client_id = :cl'; $p['cl'] = (int)$f['client']; }
  if (!empty($f['from'])) { $w[] = 'x.issued_on >= :fr'; $p['fr'] = $f['from']; }
  if (!empty($f['to'])) { $w[] = 'x.issued_on <= :tt'; $p['tt'] = $f['to']; }
  $sql = 'FROM invoices x JOIN matters m ON m.id = x.matter_id AND m.office_id = x.office_id JOIN contacts c ON c.id = x.client_id AND c.office_id = x.office_id WHERE ' . implode(' AND ', $w);
  $sum = orow("SELECT COUNT(*) n, COALESCE(SUM(CASE WHEN x.status='issued' THEN x.total ELSE 0 END),0) total, COALESCE(SUM(CASE WHEN x.status='issued' THEN x.paid ELSE 0 END),0) paid $sql", $p);
  $rows = orows("SELECT x.*, m.ref AS matter_ref, c.name_ar, c.name_en $sql ORDER BY x.id DESC LIMIT $per OFFSET " . ((max(1, (int)($f['page'] ?? 1)) - 1) * $per), $p);
  return [$rows, $sum];
}
/** Office totals for the finance page (visible case files only). */
function finance_totals(array $me, string $from, string $to): array {
  [$vis, $p] = linked_visible_sql($me);
  $j = 'FROM invoices x JOIN matters m ON m.id = x.matter_id AND m.office_id = x.office_id WHERE x.office_id = :office AND ' . $vis;
  $inv = orow("SELECT COALESCE(SUM(x.total),0) s FROM invoices x JOIN matters m ON m.id = x.matter_id AND m.office_id = x.office_id WHERE x.office_id = :office AND $vis AND x.status = 'issued' AND x.issued_on >= :f AND x.issued_on < :t", $p + ['f' => $from, 't' => $to])['s'];
  $paid = orow("SELECT COALESCE(SUM(y.amount),0) s FROM payments y JOIN invoices x ON x.id = y.invoice_id AND x.office_id = y.office_id JOIN matters m ON m.id = x.matter_id AND m.office_id = x.office_id WHERE y.office_id = :office AND $vis AND y.voided_at IS NULL AND y.received_on >= :f AND y.received_on < :t", $p + ['f' => $from, 't' => $to])['s'];
  $open = orow("SELECT COALESCE(SUM(x.total - x.paid),0) s $j AND x.status = 'issued'", $p)['s'];
  $over = orow("SELECT COALESCE(SUM(x.total - x.paid),0) s, COUNT(*) n $j AND x.status = 'issued' AND x.paid < x.total AND x.due_on < :today", $p + ['today' => date('Y-m-d')]);
  $exp = orow("SELECT COALESCE(SUM(y.amount),0) s FROM expenses y JOIN matters m ON m.id = y.matter_id AND m.office_id = y.office_id WHERE y.office_id = :office AND " . str_replace('x.matter_id', 'y.matter_id', $vis) . " AND y.spent_on >= :f AND y.spent_on < :t", $p + ['f' => $from, 't' => $to])['s'];
  return ['invoiced' => (int)$inv, 'received' => (int)$paid, 'outstanding' => (int)$open, 'overdue' => (int)$over['s'], 'overdue_n' => (int)$over['n'], 'expenses' => (int)$exp];
}

/* ---------- PDF ---------- */
function invoice_pdf(array $i, ?array $payment = null): string {
  require_once __DIR__ . '/pdf.php';
  $o = current_office(); $c = contact_get((int)$i['client_id']); $m = orow('SELECT ref, title FROM matters WHERE office_id = :office AND id = :id', ['id' => $i['matter_id']]);
  $ar = lang() === 'ar'; $pdf = new ImzPdf(false, $ar); $pdf->addPage(); $W = $pdf->w; $x0 = 50; $x1 = $W - 50;
  $X = fn(float $x) => $ar ? $W - $x : $x; $al = $ar ? 'R' : 'L'; $ar2 = $ar ? 'L' : 'R';
  $y = 60;
  $pdf->text($X($x0), $y, office_name($o), 16, true, $al); $y += 18;
  if ($o['tax_no']) { $pdf->text($X($x0), $y, ($o['tax_label'] ?: t('tax')) . ': ' . $o['tax_no'], 9, false, $al); $y += 14; }
  $title = $payment ? t('receipt') : ($i['status'] === 'void' ? t('invoice') . ' — ' . t('inv_void') : t('invoice'));
  $pdf->text($X($x1), 60, $title, 18, true, $ar2);
  $pdf->text($X($x1), 80, $payment ? $payment['receipt_no'] : (string)$i['number'], 11, true, $ar2);
  $y += 16; $pdf->line($x0, $y, $x1, $y); $y += 22;
  $rows = [[t('ct_client'), $c ? contact_label($c) : '—'], [t('appt_matter'), ($m['ref'] ?? '') . ' — ' . ($m['title'] ?? '')]];
  if ($payment) $rows = array_merge($rows, [[t('invoice'), (string)$i['number']], [t('pay_date'), date('d/m/Y', strtotime($payment['received_on']))], [t('pay_method'), t('pm_' . $payment['method'])], [t('pay_amount'), money((int)$payment['amount'])]]);
  else $rows = array_merge($rows, [[t('inv_issued_on'), $i['issued_on'] ? date('d/m/Y', strtotime($i['issued_on'])) : '—'], [t('inv_due_on'), $i['due_on'] ? date('d/m/Y', strtotime($i['due_on'])) : '—']]);
  foreach ($rows as [$k, $v]) { $pdf->text($X($x0), $y, $k, 10, true, $al); $pdf->text($X($x0 + 130), $y, $pdf->fit($v, 330, 10), 10, false, $al); $y += 16; }
  if (!$payment) {
    $y += 10; $pdf->rect($x0, $y - 14, $x1 - $x0, 20, [0.93, 0.95, 0.97]);
    $pdf->text($X($x0 + 6), $y, t('inv_desc'), 10, true, $al); $pdf->text($X($x1 - 160), $y, t('inv_qty'), 10, true, $ar2); $pdf->text($X($x1 - 6), $y, t('inv_amount'), 10, true, $ar2); $y += 22;
    foreach (invoice_lines((int)$i['id']) as $l) {
      if ($y > $pdf->h - 120) { $pdf->addPage(); $y = 60; }
      $pdf->text($X($x0 + 6), $y, $pdf->fit(($l['kind'] === 'expense' ? '[' . t('lk_expense') . '] ' : '') . $l['description'], 300, 10), 10, false, $al);
      $pdf->text($X($x1 - 160), $y, (string)$l['qty'], 10, false, $ar2); $pdf->text($X($x1 - 6), $y, money_plain((int)$l['amount']), 10, false, $ar2); $y += 16;
    }
    $pdf->line($x0, $y - 6, $x1, $y - 6); $y += 12;
    $tot = [[t('inv_subtotal'), (int)$i['subtotal']]];
    if ((int)$i['tax_rate_bp'] > 0) $tot[] = [($o['tax_label'] ?: t('tax')) . ' ' . rtrim(rtrim(number_format($i['tax_rate_bp'] / 100, 2), '0'), '.') . '%', (int)$i['tax']];
    $tot[] = [t('inv_total'), (int)$i['total']]; $tot[] = [t('inv_paid'), (int)$i['paid']]; $tot[] = [t('inv_balance'), (int)$i['total'] - (int)$i['paid']];
    foreach ($tot as $k => [$lbl, $val]) { $b = $k === count($tot) - 1 || $lbl === t('inv_total'); $pdf->text($X($x1 - 160), $y, $lbl, 10, $b, $ar2); $pdf->text($X($x1 - 6), $y, money($val), 10, $b, $ar2); $y += 16; }
    if ($i['notes']) { $y += 10; $pdf->text($X($x0), $y, $pdf->fit($i['notes'], $x1 - $x0, 9), 9, false, $al); $y += 14; }
  }
  if ($o['invoice_footer']) $pdf->text($X($x0), $pdf->h - 60, $pdf->fit($o['invoice_footer'], $x1 - $x0, 9), 9, false, $al);
  $pdf->text($W / 2, $pdf->h - 40, t('pdf_generated', date('d/m/Y H:i')), 8, false, 'C', [0.45, 0.45, 0.5]);
  return $pdf->output($title);
}
function pdf_send(string $bytes, string $name): never {
  header('Content-Type: application/pdf'); header('Content-Disposition: inline; filename="' . $name . '"'); header('Content-Length: ' . strlen($bytes));
  header("Content-Security-Policy: default-src 'none'; object-src 'self'"); echo $bytes; exit;
}
