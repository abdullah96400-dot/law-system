<?php
/* ==========================================================================
   Clients and parties ("contacts"): people and companies.
   - A contact is a client, or a party only (opponent, witness, agent…);
     the role in a particular case is recorded with the case (batch 3).
   - Only what the office needs: an identity document is optional.
   - Saving warns about likely duplicates (same phone, email, ID number, or a
     very similar name); the user can still save after looking.
   - Nothing is deleted: "archive" hides a contact from lists and keeps it
     for conflict checks and history.
   ========================================================================== */
declare(strict_types=1);

const CONTACT_KINDS = ['person', 'company'];
const ID_TYPES = ['civil_id', 'passport', 'cr', 'other'];
const CONTACT_FIELDS = ['kind', 'is_client', 'name_ar', 'name_en', 'aliases', 'id_type', 'id_number', 'phone', 'email', 'pref_lang', 'address', 'notes'];

function phone_norm(?string $p): ?string { $d = preg_replace('/\D/', '', digits_only_keep((string)$p)); if (strlen($d) < 7) return null; return substr($d, -8); }
function contact_label(array $c): string {
  $a = trim((string)$c['name_ar']); $e = trim((string)$c['name_en']);
  return lang() === 'ar' ? ($a ?: $e) : ($e ?: $a);
}
function contact_get(int $id): ?array { return orow('SELECT * FROM contacts WHERE office_id = :office AND id = :id', ['id' => $id]); }

/** Clean form input. Returns [data, error key or null]. */
function contact_input(array $in): array {
  $s = fn($k, $max) => mb_substr(trim((string)($in[$k] ?? '')), 0, $max);
  $d = ['kind' => in_array($in['kind'] ?? '', CONTACT_KINDS, true) ? $in['kind'] : 'person', 'is_client' => !empty($in['is_client']) ? 1 : 0,
        'name_ar' => $s('name_ar', 200), 'name_en' => $s('name_en', 200), 'aliases' => $s('aliases', 500),
        'id_type' => in_array($in['id_type'] ?? '', ID_TYPES, true) ? $in['id_type'] : '', 'id_number' => $s('id_number', 60),
        'phone' => $s('phone', 40), 'email' => norm_email($s('email', 190)), 'pref_lang' => ($in['pref_lang'] ?? '') === 'en' ? 'en' : 'ar',
        'address' => $s('address', 300), 'notes' => $s('notes', 4000)];
  foreach ($d as $k => $v) if ($v === '' && !in_array($k, ['kind', 'pref_lang'], true)) $d[$k] = null;
  if (!$d['name_ar'] && !$d['name_en']) return [$d, 'contact_name_required'];
  if ($d['email'] && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) return [$d, 'contact_email_bad'];
  if ($d['phone'] && !preg_match('/^[+\d\s\-()٠-٩]{7,40}$/u', $d['phone'])) return [$d, 'contact_phone_bad'];
  if (($d['id_type'] === null) !== ($d['id_number'] === null)) return [$d, 'contact_id_pair'];
  return [$d, null];
}

/** Likely duplicates of $d among the office's contacts (excluding $exclude). Each: [contact row, reason key]. */
function contact_duplicates(array $d, ?int $exclude = null): array {
  $out = [];
  $add = function (array $c, string $why) use (&$out, $exclude) { if ((int)$c['id'] === (int)$exclude || isset($out[$c['id']])) return; $out[$c['id']] = [$c, $why]; };
  if ($pn = phone_norm($d['phone'])) foreach (orows('SELECT * FROM contacts WHERE office_id = :office AND phone_norm = :p', ['p' => $pn]) as $c) $add($c, 'dup_phone');
  if ($d['email']) foreach (orows('SELECT * FROM contacts WHERE office_id = :office AND email = :e', ['e' => $d['email']]) as $c) $add($c, 'dup_email');
  if ($d['id_number']) foreach (orows('SELECT * FROM contacts WHERE office_id = :office AND id_number = :n', ['n' => $d['id_number']]) as $c) $add($c, 'dup_id');
  $mine = array_filter([name_norm((string)$d['name_ar']), name_norm((string)$d['name_en'])]);
  if ($mine) {
    $best = [];
    foreach (orows('SELECT contact_id, norm FROM contact_names WHERE office_id = :office') as $n)
      foreach ($mine as $m) { $sc = name_score($m, $n['norm']); if ($sc >= 85) $best[$n['contact_id']] = max($best[$n['contact_id']] ?? 0, $sc); }
    if ($best) {
      $ids = array_keys($best); $ph = []; $p = [];
      foreach ($ids as $i => $id) { $ph[] = ":c$i"; $p["c$i"] = (int)$id; }
      foreach (orows('SELECT * FROM contacts WHERE office_id = :office AND id IN (' . implode(',', $ph) . ')', $p) as $c) $add($c, 'dup_name');
    }
  }
  return array_values($out);
}
function contact_reindex(int $id): void {
  $c = contact_get($id); if (!$c) return;
  oq('DELETE FROM contact_names WHERE office_id = :office AND contact_id = :id', ['id' => $id]);
  $forms = [['ar', $c['name_ar']], ['en', $c['name_en']]];
  foreach (preg_split('/[\n,،;؛]+/u', (string)$c['aliases']) as $a) $forms[] = ['alias', $a];
  $seen = [];
  foreach ($forms as [$src, $txt]) { $n = name_norm((string)$txt); if ($n === '' || isset($seen[$n])) continue; $seen[$n] = 1; o_insert('contact_names', ['contact_id' => $id, 'norm' => mb_substr($n, 0, 255), 'source' => $src]); }
}
/** Create or update. Returns ['id'] | ['error'] | ['dups' => [...]] (when duplicates are found and not yet confirmed). */
function contact_save(array $me, array $in, ?int $id = null, bool $confirmed = false): array {
  [$d, $err] = contact_input($in); if ($err) return ['error' => $err, 'data' => $d];
  if ($id !== null && !contact_get($id)) return ['error' => 'not_found', 'data' => $d];
  if (!$confirmed && ($dups = contact_duplicates($d, $id))) return ['dups' => $dups, 'data' => $d];
  $d['phone_norm'] = phone_norm($d['phone']);
  if ($id === null) {
    $id = o_insert('contacts', $d + ['created_by' => $me['id'], 'created_at' => now(), 'updated_at' => now()]);
    law_audit('contact_created', 'contact', $id, ['kind' => $d['kind'], 'client' => (bool)$d['is_client'], 'duplicate_warning_overridden' => $confirmed], null, $me);
  } else {
    $old = contact_get($id); $changed = [];
    foreach (CONTACT_FIELDS as $f) if ((string)$old[$f] !== (string)$d[$f]) $changed[] = $f;   // field names only: values may be sensitive
    o_update('contacts', $id, $d + ['updated_at' => now()]);
    if ($changed) law_audit('contact_updated', 'contact', $id, ['fields' => $changed], null, $me);
  }
  contact_reindex($id);
  return ['id' => $id];
}
function contact_set_archived(array $me, int $id, bool $archived): bool {
  if (!o_update('contacts', $id, ['archived' => $archived ? 1 : 0, 'updated_at' => now()])) return false;
  law_audit($archived ? 'contact_archived' : 'contact_restored', 'contact', $id, [], null, $me);
  return true;
}
/** List with search. $f: q, kind, type (client|party), archived (0|1), page. Returns [rows, total]. */
function contact_list(array $f, int $per = 25): array {
  $w = ['office_id = :office', 'archived = :arch']; $p = ['arch' => !empty($f['archived']) ? 1 : 0];
  if (in_array($f['kind'] ?? '', CONTACT_KINDS, true)) { $w[] = 'kind = :kind'; $p['kind'] = $f['kind']; }
  if (($f['type'] ?? '') === 'client') $w[] = 'is_client = 1'; elseif (($f['type'] ?? '') === 'party') $w[] = 'is_client = 0';
  $q = trim((string)($f['q'] ?? ''));
  if ($q !== '') {
    $p['q'] = '%' . $q . '%'; $or = ['name_ar LIKE :q', 'name_en LIKE :q', 'email LIKE :q', 'id_number LIKE :q'];
    if ($pn = phone_norm($q)) { $or[] = 'phone_norm = :pn'; $p['pn'] = $pn; }
    if (($n = name_norm($q)) !== '') { $or[] = 'id IN (SELECT contact_id FROM contact_names WHERE office_id = :office AND norm LIKE :qn)'; $p['qn'] = '%' . $n . '%'; }
    $w[] = '(' . implode(' OR ', $or) . ')';
  }
  $where = implode(' AND ', $w);
  $total = (int)orow("SELECT COUNT(*) n FROM contacts WHERE $where", $p)['n'];
  $page = max(1, (int)($f['page'] ?? 1));
  $rows = orows("SELECT * FROM contacts WHERE $where ORDER BY updated_at DESC, id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $p);
  return [$rows, $total];
}
