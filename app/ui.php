<?php
/* ==========================================================================
   Interface helpers shared by the views: icons, breadcrumbs, empty states,
   field-level errors and status badges. Presentation only — no business
   rules live here.
   ========================================================================== */
declare(strict_types=1);

/** Line icons (24px grid, stroke). Decorative: always aria-hidden. */
function icon(string $n, string $cls = ''): string {
  static $p = [
    'home' => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V20h5v-6h4v6h5V9.5"/>',
    'folder' => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M3 10h18"/>',
    'users' => '<circle cx="9" cy="8" r="3.2"/><path d="M3 19c.8-3.3 3.2-5 6-5s5.2 1.7 6 5"/><path d="M16 5.2a3 3 0 0 1 0 5.6M18 14.5c1.6.7 2.7 2.2 3 4.5"/>',
    'shield' => '<path d="M12 3 4.5 6v5.5c0 4.6 3.1 8.2 7.5 9.5 4.4-1.3 7.5-4.9 7.5-9.5V6z"/><path d="m9 12 2.2 2.2L15.5 10"/>',
    'calendar' => '<rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M3.5 9.5h17M8 3v4M16 3v4"/><path d="M8 13.5h3v3H8z"/>',
    'tasks' => '<rect x="4" y="4" width="16" height="16" rx="3"/><path d="m8 12 2.5 2.5L16 9"/>',
    'file' => '<path d="M6 3h8l5 5v12a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"/><path d="M14 3v5h5M8.5 13h7M8.5 16.5h5"/>',
    'wallet' => '<path d="M4 7.5A2.5 2.5 0 0 1 6.5 5H18v3"/><rect x="4" y="8" width="16" height="11" rx="2"/><path d="M16 13.5h1.5"/>',
    'chart' => '<path d="M4 20V4M4 20h16"/><path d="M8 16v-4M12 16V8M16 16v-6"/>',
    'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
    'help' => '<circle cx="12" cy="12" r="9"/><path d="M9.6 9.3a2.5 2.5 0 0 1 4.8.9c0 1.7-2.4 2.2-2.4 3.6M12 17.2h.01"/>',
    'bell' => '<path d="M6 16V11a6 6 0 1 1 12 0v5l1.5 2h-15z"/><path d="M10 20.5a2 2 0 0 0 4 0"/>',
    'search' => '<circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.2-4.2"/>',
    'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
    'user' => '<circle cx="12" cy="8" r="3.6"/><path d="M5 20c.9-3.7 3.6-5.6 7-5.6s6.1 1.9 7 5.6"/>',
    'logout' => '<path d="M14 4h4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-4"/><path d="M10 16l-4-4 4-4M6 12h10"/>',
    'plus' => '<path d="M12 5v14M5 12h14"/>',
    'upload' => '<path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/>',
    'alert' => '<path d="M12 4 2.8 19.5h18.4z"/><path d="M12 10v4.2M12 17.2h.01"/>',
    'inbox' => '<path d="M4 13h4l1.5 3h5L16 13h4"/><path d="M5.5 5h13L21 13v5a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-5z"/>',
    'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2.5v2M12 19.5v2M4.6 4.6 6 6M18 18l1.4 1.4M2.5 12h2M19.5 12h2M4.6 19.4 6 18M18 6l1.4-1.4"/>',
    'moon' => '<path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z"/>',
    'clock' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
    'lock' => '<rect x="5" y="10.5" width="14" height="10" rx="2"/><path d="M8 10.5V8a4 4 0 0 1 8 0v2.5"/>',
    'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.6 3.7 5.6 3.7 9s-1.2 6.4-3.7 9c-2.5-2.6-3.7-5.6-3.7-9S9.5 5.6 12 3z"/>',
    'chevron' => '<path d="m9 6 6 6-6 6"/>',
    'delegate' => '<circle cx="8" cy="8" r="3"/><circle cx="16.5" cy="9.5" r="2.5"/><path d="M3 19c.6-3 2.6-4.6 5-4.6s4.4 1.6 5 4.6"/><path d="M14 15.5h6m-2-2 2 2-2 2"/>',
    'contract' => '<path d="M6 3h8l5 5v12a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"/><path d="M14 3v5h5"/><path d="M8.5 17c1-1.5 1.8-1.5 2.4 0s1.4 1.5 2.3 0 1.6-1.2 2.3 0"/><path d="M8.5 12h7"/>',
    'trash' => '<path d="M4 7h16M10 11v6M14 11v6"/><path d="M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3"/>',
    'gavel' => '<path d="m14 5 5 5M11.5 7.5l5 5M13 6l-6.5 6.5 5 5L18 11"/><path d="m8.5 15.5-5 5M4 21h8"/>',
    'receipt' => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6M9 16h3"/>',
    'portal' => '<rect x="3.5" y="4.5" width="17" height="15" rx="2"/><path d="M3.5 9h17M8 14h3"/>',
  ];
  return '<svg class="ico' . ($cls !== '' ? ' ' . $cls : '') . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . ($p[$n] ?? '') . '</svg>';
}

/** Where am I? [[label, href|null], …] built from the route and the page title. */
function crumbs(string $route, string $title): array {
  $map = ['matters' => 'mod_matters', 'clients' => 'mod_clients', 'conflicts' => 'mod_conflicts', 'calendar' => 'mod_calendar', 'tasks' => 'mod_tasks', 'documents' => 'mod_documents',
    'finance' => 'mod_finance', 'reports' => 'mod_reports', 'settings' => 'settings_menu', 'staff' => 'settings_menu', 'roles' => 'settings_menu', 'audit' => 'settings_menu', 'import' => 'settings_menu',
    'notifications' => 'notifications', 'account' => 'account', 'help' => 'help', 'search' => 'search'];
  if ($route === '/') return [[t('home'), null]];
  $seg = explode('/', trim($route, '/')); $root = $seg[0];
  $out = [[t('home'), '/']];
  if (isset($map[$root])) {
    $href = in_array($root, ['staff', 'roles', 'audit', 'import'], true) ? '/settings' : '/' . $root;
    $label = t($map[$root]);
    if ($route === $href) { $out[] = [$label, null]; return $out; }
    $out[] = [$label, $href];
    if ($root === 'finance' && ($seg[1] ?? '') === 'invoices' && count($seg) > 2) $out[] = [t('invoices'), '/finance/invoices'];
  }
  $out[] = [$title, null];
  return $out;
}

/** A friendly empty state that says what to do next. */
function empty_state(string $title, string $text = '', ?string $href = null, ?string $label = null, string $ico = 'inbox'): string {
  return '<div class="empty">' . icon($ico) . '<b>' . h($title) . '</b>' . ($text !== '' ? '<p>' . h($text) . '</p>' : '')
    . ($href && $label ? '<a class="btn small ghost" href="' . h($href) . '">' . h($label) . '</a>' : '') . '</div>';
}

/* ---------- field-level errors ----------
   Each error key the server returns is tied to the field it is about, so the
   message is shown next to that field (and still at the top for screen readers). */
const ERR_FIELDS = [
  'act_kind_required' => 'kind', 'act_title_required' => 'title', 'act_date_required' => 'action_on',
  'tpl_kind_required' => 'kind', 'tpl_name_required' => 'name', 'tpl_body_required' => 'body', 'tpl_unknown_field' => 'body', 'tpl_file_bad' => 'file',
  'contact_name_required' => 'name_ar', 'contact_email_bad' => 'email', 'contact_phone_bad' => 'phone', 'contact_id_pair' => 'id_number',
  'matter_title_required' => 'title', 'matter_check_required' => 'check_id', 'matter_check_not_cleared' => 'check_id', 'matter_check_used' => 'check_id',
  'matter_client_required' => 'client_id', 'matter_status_required' => 'status_id', 'matter_lead_required' => 'lead_id', 'matter_bad_list_item' => 'case_type_id',
  'appt_title_required' => 'title', 'appt_date_required' => 'date', 'appt_source_required' => 'source', 'appt_matter_bad' => 'matter_id',
  'task_title_required' => 'title', 'task_assignee_required' => 'assignee_id', 'task_recur_needs_due' => 'due_on',
  'check_subject_required' => 'subject', 'check_names_required' => 'client', 'check_too_many' => 'client',
  'pw_short' => 'password', 'pw_simple' => 'password', 'pw_has_email' => 'password', 'pw_mismatch' => 'confirm', 'pw_current_wrong' => 'current',
  'money_bad' => 'amount', 'invoice_line_desc' => 'description', 'fee_kind_required' => 'kind', 'payment_too_much' => 'amount', 'payment_method_required' => 'method', 'payment_future' => 'received_on',
  'export_pw_short' => 'password', 'support_reason_required' => 'reason',
];
/** Error message for this field, if the current error is about it. */
function ferr(?string $err, string $field): string {
  if (!$err || (ERR_FIELDS[$err] ?? null) !== $field) return '';
  return '<span class="f-err" id="err-' . h($field) . '">' . h(t($err)) . '</span>';
}
/** Attributes for an input: aria-invalid and aria-describedby when the error is about it. */
function fattr(?string $err, string $field): string {
  return $err && (ERR_FIELDS[$err] ?? null) === $field ? ' aria-invalid="true" aria-describedby="err-' . h($field) . '"' : '';
}
function req_mark(): string { return '<span class="req" aria-hidden="true">*</span>'; }

/** Badge class for an appointment status. */
function appt_badge(array $a): string {
  if ($a['status'] === 'scheduled' && $a['starts_at'] < date('Y-m-d') . ' 00:00:00') return 'b-late';
  return ['scheduled' => 'b-open', 'done' => 'b-done', 'postponed' => 'b-postponed', 'cancelled' => 'b-cancelled'][$a['status']] ?? '';
}
/** Badge for a case file status (lists are office-editable, so only open/closed carries colour). */
function status_badge(?int $statusId): string {
  $l = lookup_get($statusId, 'status');
  return '<span class="badge-s ' . ($l && $l['is_closed'] ? 'b-closed' : 'b-blue') . '">' . h(lookup_label($l)) . '</span>';
}
function initials(string $name): string {
  $w = preg_split('/\s+/u', trim(preg_replace('/\(.*?\)/u', '', $name))) ?: [];
  $s = mb_substr($w[0] ?? '', 0, 1) . (count($w) > 1 ? mb_substr(end($w), 0, 1) : '');
  return $s !== '' ? mb_strtoupper($s) : '؟';
}

/** A navigation tile with a soft-tinted icon, a title, an optional line and count. Tones: ink, petrol, gold, blue, green, red, violet. */
function hub_tile(string $href, string $icon, string $tone, string $title, string $sub = '', ?int $count = null, bool $alert = false): string {
  return '<a class="hub" href="' . h($href) . '"><span class="p-ico t-' . h($tone) . '">' . icon($icon) . '</span><b>' . h($title) . '</b>'
    . ($sub !== '' ? '<span class="hub-sub">' . h($sub) . '</span>' : '') . ($count ? '<span class="n-pill' . ($alert ? ' warn' : '') . '">' . $count . '</span>' : '') . '</a>';
}

/** The colour theme for this device: light (default: ivory workspace, navy sidebar) or dark (navy). */
function ui_theme(): string { return ($_COOKIE['theme'] ?? 'light') === 'dark' ? 'dark' : 'light'; }
/** Sun / moon button that switches the theme (works without JavaScript; with it, switches at once). */
function theme_toggle(string $back): string {
  $to = ui_theme() === 'dark' ? 'light' : 'dark';
  return '<form method="post" action="/theme" class="theme-form">' . csrf_field() . '<input type="hidden" name="back" value="' . h($back) . '"><button class="icon-btn theme-btn" name="theme" value="' . $to . '" data-theme-toggle aria-label="' . h(t($to === 'light' ? 'theme_light' : 'theme_dark')) . '">' . icon('sun', 'i-sun') . icon('moon', 'i-moon') . '</button></form>';
}

/** The menu: groups of [href, label key, icon, visible]. Used by the sidebar and the phone "More" page. */
function nav_groups(array $me, ?bool $admin = null): array {
  $admin ??= can('staff', 'view') || can('settings', 'view') || can('audit', 'view');
  return [
  null => [['/', 'home', 'home', true]],
  'nav_work' => [['/matters', 'mod_matters', 'folder', can('matters', 'view')], ['/clients', 'mod_clients', 'users', can('clients', 'view')], ['/conflicts', 'mod_conflicts', 'shield', can('conflicts', 'view')], ['/delegations', 'mod_delegations', 'delegate', can('calendar', 'view') && feature_on('delegations')]],
  'nav_schedule' => [['/calendar', 'mod_calendar', 'calendar', can('calendar', 'view')], ['/tasks', 'mod_tasks', 'tasks', true]],
  'nav_files' => [['/documents', 'mod_documents', 'file', can('documents', 'view')], ['/templates', 'mod_templates', 'contract', can('documents', 'view') && feature_on('templates')], ['/finance', 'mod_finance', 'wallet', can('finance', 'view') && feature_on('finance')]],
  'nav_office' => [['/reports', 'mod_reports', 'chart', can('reports', 'view') && feature_on('reports')], ['/trash', 'mod_trash', 'trash', trash_can_view($me)], ['/settings', 'settings_menu', 'settings', $admin]],
];
}
