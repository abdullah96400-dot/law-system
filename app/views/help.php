<?php $ar = lang() === 'ar'; $me = current_staff(); ?>
<section class="card">
  <h1><?= h(t('help')) ?></h1>
  <p class="muted"><?= h(t('help_intro')) ?></p>
  <nav aria-label="<?= h(t('help')) ?>"><ul class="plain">
    <?php foreach (['start', 'clients', 'matters', 'calendar', 'documents', 'finance', 'portal', 'reports', 'office', 'privacy'] as $k): ?><li><a href="#h-<?= $k ?>"><?= h(t('help_' . $k)) ?></a></li><?php endforeach; ?>
  </ul></nav>
</section>
<?php
$sections = $ar ? [
  'start' => ['ادخل بالبريد وكلمة المرور، ثم رمز التحقق من تطبيق المصادقة إن كان مطلوبًا لدورك.', 'الصفحة الرئيسية تعرض مواعيدك القادمة ومهامك ولمحة عن المكتب إن كان لك حق التقارير.', 'الجرس في الأعلى يعرض التنبيهات. التنبيهات لا تذكر تفاصيل القضايا، افتح الرابط لترى التفاصيل.'],
  'clients' => ['أضف الموكل أو الطرف مرة واحدة واستعمله في كل الملفات.', 'ينبهك النظام إذا وجد اسمًا أو هاتفًا أو بريدًا أو رقم هوية مشابهًا لسجل موجود.', 'قبل فتح أي ملف: أنشئ فحص تعارض مصالح بأسماء الموكل والخصوم، ثم يقرر المخوَّل: لا تعارض، أو لا تعارض بشروط، أو يوجد تعارض.'],
  'matters' => ['لا يُفتح ملف قضية إلا بفحص تعارض معتمد. كل فحص يُستعمل لملف واحد.', 'الملف المقيّد لا يراه إلا فريقه والشريك المدير.', 'سجل الملف يحفظ كل تغيير: الحالة، الملاحظات، الجلسات، المستندات، والفواتير.', 'الخطوة التالية وتاريخها تظهر في القوائم والتقارير.'],
  'calendar' => ['سجّل كل جلسة مع مصدرها (إعلان المحكمة، بوابة المحكمة، في الجلسة...). النظام لا يحسب مواعيد الطعن ولا يتصل بأنظمة المحاكم.', 'بعد الجلسة سجّل النتيجة. إذا أُجّلت أدخل الموعد الجديد فتُنشأ الجلسة التالية.', 'التذكير يصل للمسؤول قبل الموعد بالأيام التي تختارها، ويُرفع للمدير إن تأخرت المهمة.'],
  'documents' => ['المستندات تُشفَّر عند الحفظ. كل رفع جديد يُحفظ كنسخة ولا يُستبدل القديم.', 'يُرفض الملف إن احتوى على وحدات ماكرو أو نصوص برمجية أو كان نوعه لا يطابق امتداده.', 'رابط المشاركة له مدة وعدد تنزيلات، ويمكن إيقافه في أي وقت.'],
  'finance' => ['اتفاقية الأتعاب، ثم الفاتورة: مسودة قابلة للتعديل، ثم إصدار يقفلها برقم متسلسل.', 'لا تُحذف الفاتورة الصادرة ولا الدفعة؛ تُلغى بسبب مكتوب ويبقى أثرها.', 'أموال الموكلين (الأمانات) منفصلة، ولا يُصرف منها أكثر من رصيد الموكل.', 'الضريبة تُحسب حسب الإعدادات التي يحددها المكتب مع محاسبه.'],
  'portal' => ['يدخل الموكل برمز يصله على بريده، ولا يرى إلا الملفات التي فُعّلت له.', 'التحديث للموكل يُكتب كمسودة ويعتمده المحامي المسؤول قبل أن يظهر.', 'يمكنك طلب مستند من الموكل فيرفعه من البوابة.'],
  'reports' => ['التقارير تحسب فقط الملفات التي يحق لك رؤيتها. لكل رقم تعريف مكتوب بجانبه.', 'يمكن التصفية بالفترة والفرع والمحامي ونوع القضية، والتنزيل كملف Excel لمن له حق التنزيل.'],
  'office' => ['الشريك المدير يدعو الموظفين ويحدد أدوارهم، ويمكن تعديل صلاحيات كل دور.', 'الاستيراد من Excel أو CSV يمر بمعاينة قبل الحفظ. الملفات المستوردة تُسجَّل بفحص تعارض باسم من استوردها.', 'التصدير الكامل يعطي المكتب كل بياناته ومستنداته في ملف مضغوط مشفّر بكلمة مرور.', 'دعم الامتياز لا يدخل إلا بزيارة يفتحها الشريك المدير لمدة محددة، للعرض فقط، وكل صفحة تُسجَّل.'],
  'privacy' => ['لا نستعمل ملفات القضايا لتدريب أي نموذج ذكاء اصطناعي، ولا يوجد ذكاء اصطناعي في هذا الإصدار.', 'سجل التدقيق لا يمكن تعديله أو حذفه، ويمكن التحقق من سلامته من صفحة السجل.', 'إيقاف الاشتراك لا يحذف البيانات: يصبح المكتب للقراءة فقط ويمكن التصدير.', 'هذا النظام أداة عمل، ولا يغني عن مراجعة المتطلبات القانونية والمحاسبية مع مختص.'],
] : [
  'start' => ['Sign in with your email and password, then the code from your authenticator app if your role needs it.', 'Home shows your coming appointments, your tasks, and the office at a glance if you may see reports.', 'The bell shows notifications. They never include case details; open the link to see them.'],
  'clients' => ['Add a client or party once and use it in every file.', 'You are warned when a name, phone, email or ID number looks like an existing record.', 'Before opening any file, run a conflict check with the client and opposing names; an authorised person decides: cleared, cleared with conditions, or conflict.'],
  'matters' => ['A case file needs an approved conflict check. Each check opens one file only.', 'A restricted file is seen only by its team and the managing partner.', 'The file history keeps every change: status, notes, hearings, documents and invoices.', 'The next step and its date appear in lists and reports.'],
  'calendar' => ['Record every hearing with its source (court notice, court portal, in session…). The app does not compute appeal deadlines and is not connected to court systems.', 'After a hearing, record the outcome. If it was postponed, enter the new date and the next hearing is created.', 'Reminders go to the person responsible on the days you choose, and overdue tasks go up to the managers.'],
  'documents' => ['Documents are encrypted when saved. Each new upload is kept as a version; nothing is overwritten.', 'Files with macros or scripts, or whose type does not match the extension, are refused.', 'Share links have an end date and a download limit, and can be stopped at any time.'],
  'finance' => ['Fee agreement, then invoice: a draft you can change, then issued — locked with a running number.', 'Issued invoices and payments are never deleted; they are voided with a written reason and stay on record.', 'Client money (trust) is kept apart, and you cannot pay out more than the client\'s balance.', 'Tax follows the settings the office agrees with its accountant.'],
  'portal' => ['Clients sign in with a code sent to their email and see only the files turned on for them.', 'Updates for the client are written as drafts and approved by the responsible lawyer before they show.', 'You can ask the client for a document; they upload it in the portal.'],
  'reports' => ['Reports count only the files you may see. Each figure has its definition next to it.', 'Filter by period, branch, lawyer and case type; download as Excel if you have the download permission.'],
  'office' => ['The managing partner invites staff and sets their roles; each role\'s permissions can be changed.', 'Import from Excel or CSV shows a preview before saving. Imported files are recorded with a conflict check in the importer\'s name.', 'The full export gives the office all its data and documents in a password-encrypted ZIP.', 'Al Imtyaz support enters only during a visit the managing partner opens, for a set time, view only, and every page is logged.'],
  'privacy' => ['Case files are never used to train any AI model, and this release has no AI.', 'The audit log cannot be changed or deleted, and its integrity can be checked from the log page.', 'Stopping a subscription does not delete data: the office becomes read-only and can export.', 'This is a working tool; it does not replace review of legal and accounting requirements with a specialist.'],
];
foreach ($sections as $k => $items): ?>
<section class="card" id="h-<?= $k ?>">
  <h2><?= h(t('help_' . $k)) ?></h2>
  <ul><?php foreach ($items as $it): ?><li><?= h($it) ?></li><?php endforeach; ?></ul>
</section>
<?php endforeach; ?>
<section class="card"><p class="muted small"><?= h(t('help_contact')) ?></p></section>
