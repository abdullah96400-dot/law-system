<?php
/* ==========================================================================
   The office's public website and the requests it receives.
   - Which office: the server decides from the address only: an office's own
     domain (office_domains) or law.…/o/{code}. Never from form data.
   - Content is a draft until "Publish" copies it to live. Visitors see live
     only; staff can preview the draft.
   - A request names a service by its key; the server accepts it only if that
     service is published on THIS office's live site.
   - Website requests carry no documents; the office asks for them later.
   - Permissions (module "website"): view = see requests (without edit: only
     those assigned to you), edit = status, assignee, notes, create = turn a
     request into a client, conflict check and case file, share = edit and
     publish the website.
   ========================================================================== */
declare(strict_types=1);

const SITE_STATUSES = ['new', 'review', 'waiting', 'accepted', 'closed'];
const SITE_LANGS = ['both', 'ar', 'en'];
const SITE_RETENTION_DAYS = 180;        // closed requests that never became a case file are deleted after this
const SITE_MAX_ITEMS = 30;              // lawyers or services per site
const SITE_RATE_PER_HOUR = 5;           // requests from one network per office per hour

function site_default(array $o): array {
  return ['lang' => 'both', 'primary' => '#152A45', 'accent' => '#1F4E5A',
    'tagline_ar' => '', 'tagline_en' => '', 'about_ar' => '', 'about_en' => '', 'license_no' => '',
    'phone' => '', 'whatsapp' => '', 'email' => '', 'address_ar' => '', 'address_en' => '', 'hours_ar' => '', 'hours_en' => '', 'map_url' => '',
    'lawyers' => [], 'services' => []];
}
function site_row(): ?array { return orow('SELECT * FROM office_sites WHERE office_id = :office', []); }
/** The draft (for editing and preview) or the live content. */
function site_content(string $which = 'draft', ?array $row = null): array {
  $o = current_office(); $row ??= site_row();
  $raw = $row[$which === 'live' ? 'live' : 'draft'] ?? null;
  if ($which === 'draft' && !$raw) $raw = $row['live'] ?? null;
  $c = $raw ? json_decode((string)$raw, true) : null;
  return array_merge(site_default($o), is_array($c) ? $c : []);
}
function site_save_draft(array $me, array $c): void {
  $json = json_encode($c, JSON_UNESCAPED_UNICODE);
  if (site_row()) oq('UPDATE office_sites SET draft = :d, updated_by = :u, updated_at = :t WHERE office_id = :office', ['d' => $json, 'u' => $me['id'], 't' => now()]);
  else o_insert('office_sites', ['draft' => $json, 'updated_by' => $me['id'], 'updated_at' => now()]);
}
function site_is_live(): bool { $r = site_row(); return $r && (int)$r['published'] === 1 && $r['live']; }
function site_has_changes(): bool { $r = site_row(); return $r && $r['draft'] && $r['draft'] !== $r['live']; }
function site_can_manage(?array $me): bool { return $me && can('website', 'share', $me) && feature_on('website'); }

function site_color(string $v, string $def): string { return preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? strtoupper($v) : $def; }
function site_url_ok(string $u): string { return preg_match('#^https://[^\s<>"]{4,400}$#', $u) ? $u : ''; }
function site_key(): string { return substr(bin2hex(random_bytes(6)), 0, 10); }

/** Save the general section (texts, colors, contact). Returns an error key or null. */
function site_update_general(array $me, array $in): ?string {
  $c = site_content('draft'); $s = fn($k, $m) => mb_substr(trim((string)($in[$k] ?? '')), 0, $m);
  $c['lang'] = in_array($in['lang'] ?? '', SITE_LANGS, true) ? $in['lang'] : 'both';
  $c['primary'] = site_color($s('primary', 7), '#152A45'); $c['accent'] = site_color($s('accent', 7), '#1F4E5A');
  foreach (['tagline_ar' => 200, 'tagline_en' => 200, 'about_ar' => 4000, 'about_en' => 4000, 'license_no' => 60, 'address_ar' => 300, 'address_en' => 300, 'hours_ar' => 200, 'hours_en' => 200] as $k => $m) $c[$k] = $s($k, $m);
  $c['phone'] = $s('phone', 40); $c['whatsapp'] = $s('whatsapp', 40); $c['email'] = norm_email($s('email', 190));
  $c['map_url'] = site_url_ok($s('map_url', 400));
  if ($c['email'] !== '' && !filter_var($c['email'], FILTER_VALIDATE_EMAIL)) return 'contact_email_bad';
  foreach (['phone', 'whatsapp'] as $k) if ($c[$k] !== '' && !preg_match('/^[+\d\s\-()]{7,40}$/', $c[$k])) return 'contact_phone_bad';
  if ($s('map_url', 400) !== '' && $c['map_url'] === '') return 'site_map_bad';
  site_save_draft($me, $c); law_audit('site_draft_saved', 'office_site', office_id(), ['part' => 'general'], null, $me);
  return null;
}
const SITE_ITEM_FIELDS = [
  'lawyers' => ['name_ar' => 160, 'name_en' => 160, 'title_ar' => 160, 'title_en' => 160, 'bio_ar' => 1500, 'bio_en' => 1500],
  'services' => ['title_ar' => 160, 'title_en' => 160, 'summary_ar' => 300, 'summary_en' => 300, 'body_ar' => 4000, 'body_en' => 4000],
];
function site_item(array $c, string $kind, string $key): ?array { foreach ($c[$kind] as $it) if ($it['key'] === $key) return $it; return null; }
/** Add or edit a lawyer or a service. Returns [key, null] or [null, error]. */
function site_item_save(array $me, string $kind, ?string $key, array $in): array {
  if (!isset(SITE_ITEM_FIELDS[$kind])) return [null, 'not_found'];
  $c = site_content('draft'); $it = ['key' => $key ?: site_key(), 'show' => !empty($in['show']) ? 1 : 0];
  foreach (SITE_ITEM_FIELDS[$kind] as $f => $m) $it[$f] = mb_substr(trim((string)($in[$f] ?? '')), 0, $m);
  $first = $kind === 'lawyers' ? ['name_ar', 'name_en'] : ['title_ar', 'title_en'];
  if ($it[$first[0]] === '' && $it[$first[1]] === '') return [null, $kind === 'lawyers' ? 'site_lawyer_name' : 'site_service_title'];
  $found = false;
  foreach ($c[$kind] as $i => $x) if ($x['key'] === $it['key']) { $c[$kind][$i] = $it; $found = true; }
  if (!$found) { if ($key) return [null, 'not_found']; if (count($c[$kind]) >= SITE_MAX_ITEMS) return [null, 'site_too_many']; $c[$kind][] = $it; }
  site_save_draft($me, $c); law_audit('site_draft_saved', 'office_site', office_id(), ['part' => $kind], null, $me);
  return [$it['key'], null];
}
function site_item_move(array $me, string $kind, string $key, string $do): void {
  if (!isset(SITE_ITEM_FIELDS[$kind])) return;
  $c = site_content('draft'); $list = array_values($c[$kind]); $i = null;
  foreach ($list as $n => $x) if ($x['key'] === $key) $i = $n;
  if ($i === null) return;
  if ($do === 'remove') array_splice($list, $i, 1);
  elseif ($do === 'up' && $i > 0) [$list[$i - 1], $list[$i]] = [$list[$i], $list[$i - 1]];
  elseif ($do === 'down' && $i < count($list) - 1) [$list[$i + 1], $list[$i]] = [$list[$i], $list[$i + 1]];
  $c[$kind] = $list; site_save_draft($me, $c); law_audit('site_draft_saved', 'office_site', office_id(), ['part' => $kind, 'do' => $do], null, $me);
}
function site_publish(array $me): ?string {
  $r = site_row(); if (!$r || !$r['draft']) return 'site_nothing';
  $c = site_content('draft', $r);
  if (!array_filter($c['services'], fn($s) => $s['show'])) return 'site_need_service';
  oq('UPDATE office_sites SET live = draft, published = 1, published_at = :t, published_by = :u WHERE office_id = :office', ['t' => now(), 'u' => $me['id']]);
  law_audit('site_published', 'office_site', office_id(), ['services' => count(array_filter($c['services'], fn($s) => $s['show']))], null, $me);
  return null;
}
function site_unpublish(array $me): void {
  oq('UPDATE office_sites SET published = 0 WHERE office_id = :office', []); law_audit('site_unpublished', 'office_site', office_id(), [], null, $me);
}

/* ---------- addresses ---------- */
function site_host_norm(string $h): string { return strtolower(preg_replace('/:\d+$/', '', trim($h))); }
/** Office for an own domain (www. accepted), or null. Sets the office scope. */
function site_office_by_host(string $host): ?array {
  $host = site_host_norm($host); if ($host === '') return null;
  $d = row('SELECT office_id FROM office_domains WHERE host IN (?, ?)', [$host, preg_replace('/^www\./', '', $host)]);
  if (!$d) return null;
  return site_office_open((int)$d['office_id']);
}
function site_office_by_code(string $code): ?array {
  $o = row('SELECT id FROM offices WHERE code = ?', [strtoupper($code)]); return $o ? site_office_open((int)$o['id']) : null;
}
function site_office_open(int $id): ?array {
  $o = row('SELECT * FROM offices WHERE id = ?', [$id]);
  if (!$o || $o['status'] === 'suspended' || !feature_on('website', $o)) return null;
  set_office_scope($id);
  return $o;
}
function site_domains(): array { return orows('SELECT * FROM office_domains WHERE office_id = :office ORDER BY host', []); }
function site_domain_add(array $me, string $host): ?string {
  $host = site_host_norm(preg_replace('#^https?://#i', '', $host)); $host = rtrim(explode('/', $host)[0], '.');
  if (!preg_match('/^(?=.{4,190}$)([a-z0-9-]+\.)+[a-z]{2,}$/', $host)) return 'site_domain_bad';
  if (str_ends_with($host, 'alimtyazgcc.com') && !preg_match('/^[a-z0-9-]+\.alimtyazgcc\.com$/', $host)) return 'site_domain_bad';
  if (in_array(explode('.', $host)[0], ['law', 'oman', 'team', 'www', 'mail'], true) && str_ends_with($host, 'alimtyazgcc.com')) return 'site_domain_taken';
  if (row('SELECT id FROM office_domains WHERE host = ?', [$host])) return 'site_domain_taken';
  o_insert('office_domains', ['host' => $host, 'created_at' => now()]); law_audit('site_domain_added', 'office_site', office_id(), ['host' => $host], null, $me);
  return null;
}
function site_domain_remove(array $me, int $id): void {
  $d = orow('SELECT * FROM office_domains WHERE office_id = :office AND id = :id', ['id' => $id]); if (!$d) return;
  oq('DELETE FROM office_domains WHERE office_id = :office AND id = :id', ['id' => $id]); law_audit('site_domain_removed', 'office_site', office_id(), ['host' => $d['host']], null, $me);
}

/* ---------- language on the public site ---------- */
function site_lang_pick(array $c): string {
  if ($c['lang'] !== 'both') return $c['lang'];
  $l = $_GET['lang'] ?? null; if (in_array($l, ['ar', 'en'], true)) $_SESSION['site_lang'] = $l;
  return $_SESSION['site_lang'] ?? 'ar';
}
/** A field in the visitor's language, falling back to the other one. */
function site_t(array $x, string $f): string { $l = lang(); $o = $l === 'ar' ? 'en' : 'ar'; return (string)(($x[$f . '_' . $l] ?? '') !== '' ? $x[$f . '_' . $l] : ($x[$f . '_' . $o] ?? '')); }
function site_visible(array $c, string $kind): array { return array_values(array_filter($c[$kind], fn($x) => !empty($x['show']))); }

/* ---------- the request form ---------- */
/** A signed timestamp put in the form: rejects instant (scripted) and very old submissions. */
function site_form_stamp(): string { $t = (string)time(); return $t . '.' . substr(keyed_hash('site-form|' . office_id() . '|' . $t), 0, 20); }
function site_form_stamp_ok(string $v): bool {
  if (!preg_match('/^(\d{10})\.([0-9a-f]{20})$/', $v, $m)) return false;
  if (!hash_equals(substr(keyed_hash('site-form|' . office_id() . '|' . $m[1]), 0, 20), $m[2])) return false;
  $age = time() - (int)$m[1]; return $age >= 3 && $age <= 86400;
}
/** Save a request from the public site. Returns ['ref' => …] or ['error' => key]. The office is the one in scope (from the address). */
function site_request_submit(array $in): array {
  if (trim((string)($in['website'] ?? '')) !== '') return ['error' => 'sr_refused'];   // hidden field: only scripts fill it
  if (!site_form_stamp_ok((string)($in['stamp'] ?? ''))) return ['error' => 'sr_refused'];
  $live = site_is_live() ? site_content('live') : null; if (!$live) return ['error' => 'sr_refused'];
  $svc = null; foreach (site_visible($live, 'services') as $x) if ($x['key'] === (string)($in['service'] ?? '')) $svc = $x;
  if (!$svc) return ['error' => 'sr_service_gone'];
  $s = fn($k, $m) => mb_substr(trim((string)($in[$k] ?? '')), 0, $m);
  $d = ['name' => $s('name', 160), 'phone' => $s('phone', 40), 'email' => norm_email($s('email', 190)), 'message' => $s('message', 2000)];
  if (mb_strlen($d['name']) < 2) return ['error' => 'sr_err_name', 'data' => $d];
  $pn = phone_norm($d['phone']); if (!$pn || !preg_match('/^[+\d\s\-()٠-٩]{7,40}$/u', $d['phone'])) return ['error' => 'sr_err_phone', 'data' => $d];
  if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) return ['error' => 'sr_err_email', 'data' => $d];
  if (empty($in['consent'])) return ['error' => 'sr_err_consent', 'data' => $d];
  // the same person asking for the same service again within a day gets the same reference (no duplicate)
  $dup = orow('SELECT ref FROM site_requests WHERE office_id = :office AND phone_norm = :p AND service_key = :k AND created_at > :t ORDER BY id DESC LIMIT 1',
    ['p' => $pn, 'k' => $svc['key'], 't' => date('Y-m-d H:i:s', time() - 86400)]);
  if ($dup) return ['ref' => $dup['ref'], 'dup' => true];
  $ipH = keyed_hash('site|' . ip());
  if ((int)orow('SELECT COUNT(*) n FROM site_requests WHERE office_id = :office AND ip_hash = :i AND created_at > :t', ['i' => $ipH, 't' => date('Y-m-d H:i:s', time() - 3600)])['n'] >= SITE_RATE_PER_HOUR)
    return ['error' => 'sr_too_many', 'data' => $d];
  $title = ($svc['title_ar'] ?: $svc['title_en']) . (($svc['title_ar'] && $svc['title_en']) ? ' / ' . $svc['title_en'] : '');
  $ref = next_ref('W', 'W');
  $id = o_insert('site_requests', ['ref' => $ref, 'service_key' => $svc['key'], 'service_title' => mb_substr($title, 0, 200), 'name' => $d['name'], 'phone' => $d['phone'], 'phone_norm' => $pn,
    'email' => $d['email'] ?: null, 'message' => $d['message'] ?: null, 'lang' => lang(), 'status' => 'new', 'ip_hash' => $ipH, 'created_at' => now(), 'updated_at' => now()]);
  law_audit('site_request_received', 'site_request', $id, ['ref' => $ref], null, ['id' => null, 'full_name' => 'Website', 'email' => '']);
  foreach (site_handlers() as $sid) notify($sid, 'site_request', 'sr_notif_new', [$ref], '/requests/' . $id, 'srn' . $id);   // no personal details in the notice
  return ['ref' => $ref];
}
/** Staff who handle requests: the partner(s) and every role with website "edit". */
function site_handlers(): array {
  $out = [];
  foreach (orows("SELECT id, role FROM staff WHERE office_id = :office AND status = 'active'", []) as $s)
    if ($s['role'] === 'partner' || !empty(role_matrix($s['role'])['website']['edit'])) $out[] = (int)$s['id'];
  return $out;
}

/* ---------- requests inside the platform ---------- */
function sr_can_all(array $me): bool { return can('website', 'edit', $me); }
function sr_get(int $id, array $me): ?array {
  $r = orow('SELECT * FROM site_requests WHERE office_id = :office AND id = :id', ['id' => $id]); if (!$r) return null;
  if (!sr_can_all($me) && (int)$r['assignee_id'] !== (int)$me['id']) return null;
  return $r;
}
function sr_list(array $me, array $f, int $per = 30): array {
  $w = ['office_id = :office']; $p = [];
  if (!sr_can_all($me)) { $w[] = 'assignee_id = :me'; $p['me'] = $me['id']; }
  if (in_array($f['status'] ?? '', SITE_STATUSES, true)) { $w[] = 'status = :st'; $p['st'] = $f['status']; }
  elseif (($f['status'] ?? '') === '') { $w[] = "status <> 'closed'"; }
  if (($f['service'] ?? '') !== '') { $w[] = 'service_key = :sv'; $p['sv'] = $f['service']; }
  if (($f['assignee'] ?? '') === 'none') $w[] = 'assignee_id IS NULL';
  elseif ((int)($f['assignee'] ?? 0)) { $w[] = 'assignee_id = :as'; $p['as'] = (int)$f['assignee']; }
  $where = implode(' AND ', $w); $page = max(1, (int)($f['page'] ?? 1));
  $total = (int)orow("SELECT COUNT(*) n FROM site_requests WHERE $where", $p)['n'];
  $rows = orows("SELECT * FROM site_requests WHERE $where ORDER BY CASE status WHEN 'new' THEN 0 ELSE 1 END, id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $p);
  return [$rows, $total];
}
function sr_counts(array $me): array {
  $w = 'office_id = :office' . (sr_can_all($me) ? '' : ' AND assignee_id = :me'); $p = sr_can_all($me) ? [] : ['me' => $me['id']];
  $out = array_fill_keys(SITE_STATUSES, 0); foreach (orows("SELECT status, COUNT(*) n FROM site_requests WHERE $w GROUP BY status", $p) as $r) $out[$r['status']] = (int)$r['n'];
  return $out;
}
function sr_notes(int $id): array { return orows('SELECT n.*, s.full_name FROM site_request_notes n LEFT JOIN staff s ON s.id = n.staff_id WHERE n.office_id = :office AND n.request_id = :r ORDER BY n.id', ['r' => $id]); }
function sr_log(int $id, ?array $me, string $kind, string $body): void { o_insert('site_request_notes', ['request_id' => $id, 'staff_id' => $me['id'] ?? null, 'kind' => $kind, 'body' => mb_substr($body, 0, 4000), 'created_at' => now()]); }

function sr_set_status(array $me, array $r, string $st): ?string {
  if (!can('website', 'edit', $me)) return 'forbidden';
  if (!in_array($st, SITE_STATUSES, true)) return 'bad_status';
  if ($st === $r['status']) return null;
  o_update('site_requests', (int)$r['id'], ['status' => $st, 'updated_at' => now(), 'closed_at' => $st === 'closed' ? now() : null]);
  sr_log((int)$r['id'], $me, 'status', $r['status'] . '>' . $st);
  law_audit('site_request_status', 'site_request', $r['id'], ['from' => $r['status'], 'to' => $st], null, $me);
  return null;
}
function sr_assign(array $me, array $r, int $staffId): ?string {
  if (!can('website', 'edit', $me)) return 'forbidden';
  if ($staffId && !staff_active_in_office($staffId)) return 'not_found';
  o_update('site_requests', (int)$r['id'], ['assignee_id' => $staffId ?: null, 'updated_at' => now(), 'status' => $r['status'] === 'new' && $staffId ? 'review' : $r['status']]);
  sr_log((int)$r['id'], $me, 'assign', (string)$staffId);
  law_audit('site_request_assigned', 'site_request', $r['id'], ['to' => $staffId ?: null], null, $me);
  if ($staffId && $staffId !== (int)$me['id']) notify($staffId, 'site_request', 'sr_notif_assigned', [$r['ref']], '/requests/' . $r['id'], 'sra' . $r['id'] . '-' . $staffId . '-' . time());
  return null;
}
function sr_note(array $me, array $r, string $body): ?string {
  if (!can('website', 'edit', $me) && (int)$r['assignee_id'] !== (int)$me['id']) return 'forbidden';
  $body = trim($body); if ($body === '') return 'note_required';
  sr_log((int)$r['id'], $me, 'note', $body); o_update('site_requests', (int)$r['id'], ['updated_at' => now()]);
  return null;
}
/** Step 1 of turning a request into work: link an existing contact, or create one from the request. */
function sr_link_contact(array $me, array $r, int $contactId, bool $createNew, bool $confirmed = false): array {
  if (!can('website', 'create', $me) || !can('clients', 'create', $me)) return ['error' => 'forbidden'];
  if ($r['matter_id']) return ['error' => 'sr_already_converted'];
  if ($createNew) {
    $res = contact_save($me, ['kind' => 'person', 'is_client' => 0, (preg_match('/\p{Arabic}/u', $r['name']) ? 'name_ar' : 'name_en') => $r['name'], 'phone' => $r['phone'], 'email' => $r['email'],
      'pref_lang' => $r['lang'], 'notes' => t('sr_contact_note', $r['ref'])], null, $confirmed);
    if (isset($res['dups'])) return ['dups' => $res['dups']];
    if (isset($res['error'])) return ['error' => $res['error']];
    $contactId = (int)$res['id'];
  } elseif (!contact_get($contactId)) return ['error' => 'not_found'];
  o_update('site_requests', (int)$r['id'], ['contact_id' => $contactId, 'updated_at' => now()]);
  sr_log((int)$r['id'], $me, 'contact', (string)$contactId);
  law_audit('site_request_contact', 'site_request', $r['id'], ['contact' => $contactId, 'new' => $createNew], null, $me);
  return ['id' => $contactId];
}
/** Step 2: start the conflict check for this request (the office decides it on the usual conflict page). */
function sr_start_check(array $me, array $r): array {
  if (!can('website', 'create', $me) || !can('conflicts', 'create', $me)) return ['error' => 'forbidden'];
  if ($r['check_id'] && check_get((int)$r['check_id'])) return ['id' => (int)$r['check_id']];
  $c = $r['contact_id'] ? contact_get((int)$r['contact_id']) : null;
  $names = array_filter([$c['name_ar'] ?? null, $c['name_en'] ?? null, $c ? null : $r['name']]);
  $res = check_create($me, t('sr_check_subject', $r['ref'], $r['service_title']), ['client' => implode("\n", array_unique($names))]);
  if (isset($res['error'])) return $res;
  o_update('site_requests', (int)$r['id'], ['check_id' => $res['id'], 'updated_at' => now()]);
  sr_log((int)$r['id'], $me, 'check', (string)$res['id']);
  return $res;
}
/** Step 3 happens on the normal "open case file" form; this links the new file back to the request. */
function sr_link_matter(array $me, int $requestId, int $matterId, int $checkId): void {
  $r = orow('SELECT * FROM site_requests WHERE office_id = :office AND id = :id', ['id' => $requestId]);
  if (!$r || $r['matter_id'] || (int)$r['check_id'] !== $checkId) return;
  o_update('site_requests', $requestId, ['matter_id' => $matterId, 'status' => 'accepted', 'updated_at' => now()]);
  sr_log($requestId, $me, 'matter', (string)$matterId);
  matter_event($matterId, $me, 'note', t('sr_from_request', $r['ref']));
  law_audit('site_request_converted', 'site_request', $requestId, ['matter' => $matterId], null, $me);
}
/** Daily: closed requests that never became a case file are deleted after the retention period. Returns how many. */
function sr_purge(): int {
  $n = 0; $cut = date('Y-m-d H:i:s', time() - SITE_RETENTION_DAYS * 86400);
  foreach (rows("SELECT id, office_id, ref FROM site_requests WHERE status = 'closed' AND matter_id IS NULL AND closed_at IS NOT NULL AND closed_at < ?", [$cut]) as $r) {
    q('DELETE FROM site_request_notes WHERE request_id = ? AND office_id = ?', [$r['id'], $r['office_id']]);
    q('DELETE FROM site_requests WHERE id = ? AND office_id = ?', [$r['id'], $r['office_id']]);
    law_audit('site_request_purged', 'site_request', $r['id'], ['ref' => $r['ref']], (int)$r['office_id'], ['id' => null, 'full_name' => 'Retention', 'email' => '']);
    $n++;
  }
  return $n;
}

/* ---------- public pages ---------- */
/** Serve the office's public site. $base: '' on an own domain, '/o/{code}' on law.…, '/site/preview' for staff preview. */
function site_dispatch(array $o, string $base, string $sub, bool $preview = false): never {
  $row = site_row();
  if (!$preview && (!$row || !(int)$row['published'] || !$row['live'])) not_found();
  $c = site_content($preview ? 'draft' : 'live', $row);
  $GLOBALS['LANG_FORCE'] = site_lang_pick($c);
  $GLOBALS['LAYOUT'] = 'site_layout';
  $GLOBALS['SITE'] = ['o' => $o, 'c' => $c, 'base' => $base, 'preview' => $preview, 'sub' => $sub];
  if ($sub === '/site.css') {
    header('Content-Type: text/css; charset=utf-8'); header('X-Content-Type-Options: nosniff'); header('Cache-Control: ' . ($preview ? 'no-store' : 'public, max-age=300'));
    echo ':root{--s-primary:' . site_color($c['primary'], '#152A45') . ';--s-accent:' . site_color($c['accent'], '#1F4E5A') . "}\n"; exit;
  }
  if ($sub === '/logo.jpg') { $f = office_logo_file($o); if (!$f) not_found(); header('Content-Type: image/jpeg'); header('Cache-Control: public, max-age=3600'); readfile($f); exit; }
  $services = site_visible($c, 'services'); $lawyers = site_visible($c, 'lawyers');
  $v = ['o' => $o, 'c' => $c, 'base' => $base, 'preview' => $preview, 'services' => $services, 'lawyers' => $lawyers];
  if ($sub === '/') { render('site_home', $v + ['title' => office_name($o)]); exit; }
  if ($sub === '/about') { render('site_about', $v + ['title' => t('site_about')]); exit; }
  if ($sub === '/lawyers') { render('site_lawyers', $v + ['title' => t('site_lawyers')]); exit; }
  if ($sub === '/services') { render('site_services', $v + ['title' => t('site_services')]); exit; }
  if ($sub === '/contact') { render('site_contact', $v + ['title' => t('site_contact')]); exit; }
  if ($sub === '/thanks') {
    $ref = $_SESSION['sr_done'][office_id()] ?? null; if (!$ref) redirect($base . '/');
    render('site_thanks', $v + ['title' => t('sr_thanks_title'), 'ref' => $ref]); exit;
  }
  if (preg_match('#^/services/([a-f0-9]{6,16})$#', $sub, $m)) {
    $svc = null; foreach ($services as $x) if ($x['key'] === $m[1]) $svc = $x;
    if (!$svc) not_found();
    $err = null; $d = [];
    if (is_post()) {
      if ($preview) $err = 'sr_preview_only';
      elseif ($o['status'] !== 'active') $err = 'sr_refused';
      else {
        $r = site_request_submit(['service' => $svc['key']] + $_POST);
        if (isset($r['ref'])) { $_SESSION['sr_done'][office_id()] = $r['ref']; redirect($base . '/thanks'); }
        $err = $r['error']; $d = $r['data'] ?? [];
      }
    }
    render('site_service', $v + ['title' => site_t($svc, 'title'), 'svc' => $svc, 'err' => $err, 'd' => $d]); exit;
  }
  not_found();
}
