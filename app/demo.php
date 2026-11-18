<?php
/* ==========================================================================
   Demo office: made-up people and case files for showing the product.
   - The office is marked demo, and every screen shows the demo banner.
   - Every name, number and email is invented (example.com / example.om).
   - Staff passwords are random and printed once by the tool.
   The data covers the demo path in docs/law/demo-path.md.
   ========================================================================== */
declare(strict_types=1);

function demo_pdf(string $title, array $lines): string {
  require_once __DIR__ . '/pdf.php';
  $pdf = new ImzPdf(false, true); $pdf->addPage(); $y = 70; $W = $pdf->w;
  $pdf->text($W - 50, $y, $title, 16, true, 'R'); $y += 28;
  $pdf->text($W - 50, $y, 'مستند تجريبي — بيانات مصطنعة', 10, false, 'R', [0.6, 0.2, 0.2]); $y += 24;
  foreach ($lines as $l) { $pdf->text($W - 50, $y, $l, 11, false, 'R'); $y += 18; }
  return $pdf->output($title);
}
function demo_doc(array $me, int $matterId, string $title, string $bytes, string $name, bool $portal = false): int {
  $tmp = tempnam(sys_get_temp_dir(), 'demo'); file_put_contents($tmp, $bytes);
  $stored = store_encrypted($tmp, office_id());
  $id = o_insert('documents', ['matter_id' => $matterId, 'contact_id' => null, 'title' => $title, 'category_id' => null, 'current_version' => 1, 'source' => 'staff',
    'portal_visible' => $portal ? 1 : 0, 'archived' => 0, 'created_by' => $me['id'], 'created_at' => now(), 'updated_at' => now()]);
  o_insert('document_versions', ['document_id' => $id, 'version' => 1, 'stored_name' => $stored, 'orig_name' => $name, 'ext' => 'pdf', 'mime' => 'application/pdf', 'size' => strlen($bytes),
    'sha256' => hash('sha256', $bytes), 'enc' => 1, 'uploaded_by' => $me['id'], 'uploaded_at' => now()]);
  @unlink($tmp); matter_event($matterId, $me, 'doc_add', null, ['doc' => $id, 'title' => $title, 'client' => false]);
  return $id;
}
/** Create a demo office with data. Returns ['code', 'logins' => [[role, email, password]], 'portal' => email]. */
function demo_create(string $code): array {
  $code = strtoupper($code);
  $r = create_office($code, 'مكتب الامتياز التجريبي للمحاماة', 'Al Imtyaz Demo Law Office', 'partner.' . strtolower($code) . '@example.om', 'سالم التجريبي (شريك)', ['demo' => true, 'plan' => 'Demo', 'max_users' => 10]);
  if (isset($r['error'])) return $r;
  $oid = (int)$r['office_id']; set_office_scope($oid);
  $logins = [];
  $mk = function (string $role, string $name, string $email) use (&$logins) {
    $pw = 'Demo-' . substr(token(9), 0, 10) . '7';
    $id = o_insert('staff', ['email' => $email, 'full_name' => $name, 'role' => $role, 'lang' => 'ar', 'status' => 'active', 'password_hash' => password_hash($pw, PASSWORD_DEFAULT),
      'password_set_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    $logins[] = [$role, $email, $pw]; return $id;
  };
  $pw = 'Demo-' . substr(token(9), 0, 10) . '7';
  q("UPDATE staff SET status='active', password_hash=?, password_set_at=?, updated_at=? WHERE id=?", [password_hash($pw, PASSWORD_DEFAULT), now(), now(), $r['owner_id']]);
  q('UPDATE invites SET used_at=? WHERE staff_id=?', [now(), $r['owner_id']]);
  $logins[] = ['partner', 'partner.' . strtolower($code) . '@example.om', $pw];
  $me = row('SELECT * FROM staff WHERE id=?', [$r['owner_id']]);
  $lawyer = $mk('lawyer', 'مريم التجريبية (محامية)', 'lawyer.' . strtolower($code) . '@example.om');
  $mk('office_manager', 'خالد التجريبي (مدير المكتب)', 'manager.' . strtolower($code) . '@example.om');
  $mk('accountant', 'هدى التجريبية (محاسبة)', 'accounts.' . strtolower($code) . '@example.om');
  o_insert('branches', ['name' => 'الفرع الرئيسي — مسقط (تجريبي)', 'active' => 1, 'created_at' => now()]);
  $br2 = o_insert('branches', ['name' => 'فرع صلالة (تجريبي)', 'active' => 1, 'created_at' => now()]);

  $people = [['شركة النخيل التجريبية للتجارة', 'Demo Palm Trading LLC', 'company', 'client.' . strtolower($code) . '@example.com'],
             ['أحمد بن سعيد التجريبي', 'Ahmed Said (demo)', 'person', null], ['فاطمة بنت ناصر التجريبية', 'Fatma Nasser (demo)', 'person', 'fatma.demo@example.com'],
             ['مؤسسة الوادي التجريبية', 'Demo Wadi Est.', 'company', null]];
  $ids = [];
  foreach ($people as $i => [$ar, $en, $kind, $email]) {
    $c = contact_save($me, ['kind' => $kind, 'is_client' => $i !== 1 && $i !== 3 ? 1 : 0, 'name_ar' => $ar, 'name_en' => $en, 'email' => $email, 'phone' => '+968 9000 00' . (10 + $i), 'notes' => 'بيانات تجريبية'], null, true);
    $ids[] = $c['id'];
  }
  $status = lookups('status'); $open = array_values(array_filter($status, fn($s) => !$s['is_closed']));
  $types = lookups('case_type'); $courts = lookups('court');
  $files = [];
  foreach ([['مطالبة بقيمة بضاعة — النخيل ضد الوادي', 0, 3, $me['id'], null], ['نزاع إيجار — فاطمة', 2, 1, $lawyer, $br2], ['استشارة عقد توريد', 0, null, $lawyer, null]] as $k => [$title, $cl, $opp, $lead, $br]) {
    $names = ['client' => contact_label(contact_get($ids[$cl])), 'opponent' => $opp !== null ? contact_label(contact_get($ids[$opp])) : ''];
    $chk = check_create($me, 'تجريبي: ' . $title, $names); check_decide($me, $chk['id'], $opp !== null ? 'cleared_cond' : 'cleared', $opp !== null ? 'تجريبي: لا يوجد تعارض مع الطرف الآخر، بشرط عدم تمثيله لاحقًا.' : 'تجريبي: التطابق مع سجل الموكل نفسه فقط.');
    $m = matter_open($me, ['title' => $title, 'check_id' => $chk['id'], 'client_id' => $ids[$cl], 'status_id' => $open[min($k, count($open) - 1)]['id'], 'lead_id' => $lead,
      'case_type_id' => $types[$k % max(1, count($types))]['id'] ?? null, 'court_id' => $courts[0]['id'] ?? null, 'opened_on' => date('Y-m-d', strtotime('-' . (40 - 10 * $k) . ' days')), 'summary' => 'ملخص تجريبي.']);
    if ($br) o_update('matters', $m['id'], ['branch_id' => $br]);
    if ($opp !== null) matter_party_add($me, matter_for($m['id'], $me), $ids[$opp], 'opponent', '');
    $files[] = matter_for($m['id'], $me);
  }
  $m0 = $files[0];
  matter_note_add($me, $m0, 'تم استلام المستندات الأصلية من الموكل (تجريبي).');
  matter_set_next($me, $m0, 'تقديم مذكرة الرد', date('Y-m-d', strtotime('+6 days')));
  $past = appt_save($me, ['kind' => 'hearing', 'title' => 'جلسة أولى (تجريبية)', 'date' => date('Y-m-d', strtotime('-7 days')), 'time' => '09:00', 'court_id' => $courts[0]['id'] ?? 0, 'lead_id' => $me['id'], 'source' => 'court_portal', 'matter_id' => $m0['id']]);
  if (isset($past['id'])) appt_outcome($me, row('SELECT * FROM appointments WHERE id=?', [$past['id']]), ['status' => 'postponed', 'outcome' => 'تأجيل لتقديم المستندات (تجريبي)', 'new_date' => date('Y-m-d', strtotime('+9 days')), 'new_time' => '09:30']);
  appt_save($me, ['kind' => 'meeting', 'title' => 'اجتماع مع الموكل (تجريبي)', 'date' => date('Y-m-d', strtotime('+2 days')), 'time' => '11:00', 'lead_id' => $lawyer, 'source' => 'client', 'matter_id' => $files[1]['id']]);
  appt_save($me, ['kind' => 'hearing', 'title' => 'جلسة سابقة بلا نتيجة مسجلة (تجريبية)', 'date' => date('Y-m-d', strtotime('-3 days')), 'time' => '10:00', 'lead_id' => $lawyer, 'source' => 'court_portal', 'matter_id' => $files[1]['id']]);
  task_save($me, ['title' => 'إعداد مذكرة الرد (تجريبي)', 'assignee_id' => $lawyer, 'due_on' => date('Y-m-d', strtotime('+5 days')), 'priority' => 'high', 'matter_id' => $m0['id']]);
  task_save($me, ['title' => 'متابعة سداد الدفعة الثانية (تجريبي)', 'assignee_id' => $me['id'], 'due_on' => date('Y-m-d', strtotime('-2 days')), 'matter_id' => $m0['id']]);
  demo_doc($me, $m0['id'], 'صحيفة الدعوى (تجريبية)', demo_pdf('صحيفة الدعوى — نموذج تجريبي', ['هذا مستند تجريبي لعرض النظام فقط.', 'لا يحتوي على بيانات حقيقية.']), 'claim-demo.pdf', true);
  demo_doc($me, $m0['id'], 'مذكرة داخلية (تجريبية)', demo_pdf('مذكرة داخلية — تجريبية', ['ملاحظات العمل الداخلية لا تظهر للموكل.']), 'memo-demo.pdf');
  $ag = agreement_save($me, $m0, ['kind' => 'fixed', 'total' => '1500', 'terms' => 'اتفاقية أتعاب تجريبية', 'signed_on' => date('Y-m-d', strtotime('-38 days'))]);
  $a = agreement_get_for($ag['id'], $me);
  installment_add($me, $a, 'الدفعة الأولى', date('Y-m-d', strtotime('-30 days')), '750');
  installment_add($me, $a, 'الدفعة الثانية', date('Y-m-d', strtotime('-2 days')), '750');
  $ins = orows('SELECT id FROM fee_installments WHERE office_id = :office AND agreement_id = :a ORDER BY id', ['a' => $a['id']]);
  foreach ($ins as $n => $in) {
    $inv = invoice_create($me, $m0, (int)$in['id']); $i = invoice_get_for($inv['id'], $me);
    invoice_update_draft($me, $i, ['due_on' => date('Y-m-d', strtotime($n ? '-2 days' : '-20 days'))]);
    invoice_issue($me, invoice_get_for($inv['id'], $me));
    q('UPDATE invoices SET issued_on=? WHERE id=?', [date('Y-m-d', strtotime($n ? '-12 days' : '-30 days')), $inv['id']]);   // demo only: dates spread over the past month
    $i = invoice_get_for($inv['id'], $me);
    invoice_set_portal($me, $i, true);
    if ($n === 0) payment_add($me, invoice_get_for($inv['id'], $me), ['amount' => '750', 'method' => 'transfer', 'received_on' => date('Y-m-d', strtotime('-25 days')), 'reference' => 'DEMO-TRF-1']);
  }
  expense_add($me, $m0, ['description' => 'رسوم قيد الدعوى (تجريبية)', 'amount' => '25', 'spent_on' => date('Y-m-d', strtotime('-35 days')), 'paid_from' => 'office', 'billable' => '1', 'category' => 'court_fee']);
  party_portal_toggle($me, $m0, (int)orow("SELECT id FROM matter_parties WHERE office_id = :office AND matter_id = :m AND role = 'client'", ['m' => $m0['id']])['id'], true);
  $c0 = contact_get($ids[0]); portal_invite($me, $c0);
  update_draft($me, $m0, 'تم تأجيل الجلسة لتقديم المستندات. سنوافيكم بالموعد القادم. (تحديث تجريبي)');
  update_publish($me, $m0, (int)orow("SELECT id FROM client_updates WHERE office_id = :office AND matter_id = :m ORDER BY id DESC", ['m' => $m0['id']])['id']);
  doc_request_add($me, $m0, 'صورة السجل التجاري (تجريبي)');
  /* v7 workspace: procedures and judgments, a delegation, office templates, statement and notice */
  $lw = row('SELECT * FROM staff WHERE id=?', [$lawyer]);
  foreach ([['procedure', 'قيد صحيفة الدعوى (تجريبي)', -38, 'court_portal', true], ['judgment', 'حكم تمهيدي بندب خبير حسابي (تجريبي)', -7, 'in_session', true], ['memo', 'مذكرة دفاع مقدمة (تجريبية — لم تُراجع)', -1, 'other', false]] as [$kind, $title, $days, $src, $share]) {
    $ra = action_save($me, $m0, ['kind' => $kind, 'title' => $title, 'action_on' => date('Y-m-d', strtotime("$days days")), 'source' => $src, 'details' => $kind === 'judgment' ? 'قررت المحكمة ندب خبير حسابي لفحص الفواتير وتقديم تقريره (نص تجريبي).' : '']);
    if ($share) { $act = action_get($ra['id'], $me); action_verify($lw, $act); action_share($me, $m0, action_get($ra['id'], $me), true); }
  }
  foreach ([['delegation', 'نوتة إنابة (نموذج المكتب — تجريبي)', deleg_default_body()],
            ['letter', 'خطاب إلى الموكل (تجريبي)', "{{office_name}}\nالتاريخ: {{today}}\n\nالسادة/ {{client_name}} المحترمين\n\nالموضوع: {{matter_title}} — ملف رقم {{matter_ref}}\n\nنود إفادتكم بأن الجلسة القادمة في الدعوى أمام {{court}} محددة بتاريخ {{next_hearing}}.\n\nوتفضلوا بقبول فائق الاحترام،\n{{lead_name}}\n\n(نموذج تجريبي)"],
            ['fee_agreement', 'عقد أتعاب (هيكل تجريبي)', "عقد أتعاب محاماة (هيكل تجريبي — ليس نصًا قانونيًا معتمدًا)\n\nالطرف الأول: {{office_name}}\nالطرف الثاني: {{client_name}}\nموضوع التكليف: {{matter_title}}\n\n[يُكمل المكتب البنود وفق نموذجه المعتمد]"]] as [$k, $n, $b])
    tpl_doc_save($me, ['kind' => $k, 'name' => $n, 'body' => $b], null);
  $nextAppt = orow("SELECT id FROM appointments WHERE office_id = :office AND matter_id = :m AND status = 'scheduled' AND kind = 'hearing' ORDER BY starts_at LIMIT 1", ['m' => $m0['id']]);
  if ($nextAppt) { $dl = deleg_create($me, $m0, ['to_staff' => $lawyer, 'appointment_id' => $nextAppt['id'], 'instructions' => 'حضور الجلسة وطلب أجل لتقديم المستندات الأصلية (تجريبي).', 'send' => 1]);
    if (isset($dl['id'])) { deleg_respond($lw, row('SELECT * FROM delegations WHERE id=?', [$dl['id']]), true, 'سأحضر (تجريبي)'); deleg_memo($lw, row('SELECT * FROM delegations WHERE id=?', [$dl['id']])); } }
  statement_share($me, $a, true);
  if ($nextAppt) { o_update('appointments', (int)$nextAppt['id'], ['portal_visible' => 1]); share_mark($me, (int)$m0['id'], 'appointment', (int)$nextAppt['id'], true); }
  $ins2 = orow('SELECT id FROM fee_installments WHERE office_id = :office AND agreement_id = :a ORDER BY id DESC LIMIT 1', ['a' => $a['id']]);
  if ($ins2) notice_create($me, agreement_get_for((int)$a['id'], $me), (int)$ins2['id'], null);
  q('UPDATE offices SET notice_days=? WHERE id=?', ['7,1', $oid]);
  law_audit('demo_seeded', 'office', $oid, ['files' => count($files)], $oid, ['id' => null, 'full_name' => 'Al Imtyaz', 'email' => '']);
  return ['code' => $code, 'office_id' => $oid, 'logins' => $logins, 'portal' => $c0['email']];
}
