<?php
/* ==========================================================================
   Conflict-of-interest checks — a review step before accepting a client or
   a case.
   1. Enter the client, the other side and related names.
   2. The office's own records are searched: its contacts (clients and
      parties) and the names in its earlier checks. Never another office's.
   3. Matches are saved with the check, with links to the matching record.
   4. A reviewer with permission records the outcome and notes. A check with
      matches cannot be cleared without a note. Once recorded, the decision
      is fixed; a new situation needs a new check.
   The system only points out possible matches. The decision is the lawyer's.
   ========================================================================== */
declare(strict_types=1);

const CHECK_ROLES = ['client', 'opponent', 'related'];
const CHECK_DECISIONS = ['cleared', 'cleared_cond', 'conflict'];
const CHECK_MAX_NAMES = 40;

function next_ref(string $k, string $prefix): string {
  $key = $k . date('Y');
  for ($try = 0; $try < 3; $try++) {
    if (oq('UPDATE counters SET n = n + 1 WHERE office_id = :office AND k = :k', ['k' => $key])->rowCount() === 0) {
      try { o_insert('counters', ['k' => $key, 'n' => 1]); } catch (PDOException $e) { continue; }   // another request created it first
    }
    $n = (int)orow('SELECT n FROM counters WHERE office_id = :office AND k = :k', ['k' => $key])['n'];
    return sprintf('%s-%s-%04d', $prefix, date('Y'), $n);
  }
  throw new RuntimeException('Could not number the record');
}
function check_get(int $id): ?array { return orow('SELECT * FROM conflict_checks WHERE office_id = :office AND id = :id', ['id' => $id]); }

/** Create and run a check. $names = ['client' => "one per line", 'opponent' => …, 'related' => …]. */
function check_create(array $me, string $subject, array $names): array {
  $subject = mb_substr(trim($subject), 0, 300);
  $list = [];
  foreach (CHECK_ROLES as $r) foreach (preg_split('/\r?\n/', (string)($names[$r] ?? '')) as $line) {
    $line = mb_substr(trim($line), 0, 200);
    if ($line !== '' && name_norm($line) !== '') $list[] = [$r, $line];
  }
  if ($subject === '') return ['error' => 'check_subject_required'];
  if (!$list) return ['error' => 'check_names_required'];
  if (count($list) > CHECK_MAX_NAMES) return ['error' => 'check_too_many'];
  $pdo = db(); $pdo->beginTransaction();
  try {
    $id = o_insert('conflict_checks', ['ref' => next_ref('CC', 'CC'), 'subject' => $subject, 'status' => 'pending', 'created_by' => $me['id'], 'created_at' => now(), 'updated_at' => now()]);
    foreach ($list as [$r, $n]) o_insert('conflict_check_names', ['check_id' => $id, 'role' => $r, 'name' => $n, 'norm' => mb_substr(name_norm($n), 0, 255)]);
    $hits = check_run($id);
    $pdo->commit();
  } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
  law_audit('conflict_check_created', 'conflict_check', $id, ['names' => count($list), 'matches' => $hits], null, $me);
  return ['id' => $id];
}
/** (Re)compute the matches of a check from the office's current records. Returns the number of matches. */
function check_run(int $id): int {
  oq('DELETE FROM conflict_hits WHERE office_id = :office AND check_id = :id', ['id' => $id]);
  $names = orows('SELECT * FROM conflict_check_names WHERE office_id = :office AND check_id = :id', ['id' => $id]);
  $cnames = orows('SELECT n.contact_id, n.norm, c.name_ar, c.name_en, c.kind, c.is_client, c.archived FROM contact_names n JOIN contacts c ON c.id = n.contact_id AND c.office_id = n.office_id WHERE n.office_id = :office');
  $pnames = orows('SELECT n.check_id, n.name, n.norm, n.role, k.ref, k.subject, k.status FROM conflict_check_names n JOIN conflict_checks k ON k.id = n.check_id AND k.office_id = n.office_id WHERE n.office_id = :office AND n.check_id <> :id', ['id' => $id]);
  $total = 0;
  foreach ($names as $nm) {
    $best = [];
    foreach ($cnames as $c) { $sc = name_score($nm['norm'], $c['norm']); if ($sc >= HIT_SCORE && $sc > ($best['c' . $c['contact_id']][0] ?? 0)) $best['c' . $c['contact_id']] = [$sc, $c]; }
    foreach ($pnames as $c) { $sc = name_score($nm['norm'], $c['norm']); if ($sc >= HIT_SCORE && $sc > ($best['k' . $c['check_id']][0] ?? 0)) $best['k' . $c['check_id']] = [$sc, $c]; }
    foreach ($best as $key => [$sc, $c]) {
      if ($key[0] === 'c') {
        $label = json_encode(['name_ar' => $c['name_ar'], 'name_en' => $c['name_en'], 'kind' => $c['kind'], 'client' => (int)$c['is_client'], 'archived' => (int)$c['archived']], JSON_UNESCAPED_UNICODE);
        o_insert('conflict_hits', ['check_id' => $id, 'name_id' => $nm['id'], 'contact_id' => $c['contact_id'], 'matched' => mb_substr($c['norm'], 0, 200), 'label' => mb_substr($label, 0, 300), 'score' => $sc]);
      } else {
        $label = json_encode(['ref' => $c['ref'], 'subject' => mb_substr($c['subject'], 0, 120), 'status' => $c['status'], 'role' => $c['role'], 'name' => $c['name']], JSON_UNESCAPED_UNICODE);
        o_insert('conflict_hits', ['check_id' => $id, 'name_id' => $nm['id'], 'other_check_id' => $c['check_id'], 'matched' => mb_substr($c['norm'], 0, 200), 'label' => mb_substr($label, 0, 300), 'score' => $sc]);
      }
      $total++;
    }
  }
  o_update('conflict_checks', $id, ['hit_count' => $total, 'updated_at' => now()]);
  return $total;
}
function check_rerun(array $me, int $id): ?string {
  $c = check_get($id); if (!$c) return 'not_found';
  if ($c['status'] !== 'pending') return 'check_decided';
  $n = check_run($id);
  law_audit('conflict_check_rerun', 'conflict_check', $id, ['matches' => $n], null, $me);
  return null;
}
function check_decide(array $me, int $id, string $decision, string $note): ?string {
  $c = check_get($id); if (!$c) return 'not_found';
  if (!can('conflicts', 'edit', $me)) return 'forbidden';
  if ($c['status'] !== 'pending') return 'check_decided';
  if (!in_array($decision, CHECK_DECISIONS, true)) return 'check_decision_required';
  $note = mb_substr(trim($note), 0, 4000);
  if ($note === '' && ((int)$c['hit_count'] > 0 || $decision !== 'cleared')) return 'check_note_required';
  o_update('conflict_checks', $id, ['status' => $decision, 'decision_note' => $note ?: null, 'decided_by' => $me['id'], 'decided_at' => now(), 'updated_at' => now()]);
  law_audit('conflict_check_decided', 'conflict_check', $id, ['decision' => $decision, 'matches' => (int)$c['hit_count']], null, $me);
  return null;
}
/** Matches of a check grouped by entered name: [[name row, [hits]], …] */
function check_hits(int $id): array {
  $out = [];
  foreach (orows('SELECT * FROM conflict_check_names WHERE office_id = :office AND check_id = :id ORDER BY id', ['id' => $id]) as $n) $out[$n['id']] = [$n, []];
  foreach (orows('SELECT * FROM conflict_hits WHERE office_id = :office AND check_id = :id ORDER BY score DESC, id', ['id' => $id]) as $h) if (isset($out[$h['name_id']])) $out[$h['name_id']][1][] = $h + ['info' => json_decode($h['label'], true) ?: []];
  return array_values($out);
}
function check_list(string $status, int $page, int $per = 25): array {
  $w = 'office_id = :office'; $p = [];
  if (in_array($status, array_merge(['pending'], CHECK_DECISIONS), true)) { $w .= ' AND status = :s'; $p['s'] = $status; }
  $total = (int)orow("SELECT COUNT(*) n FROM conflict_checks WHERE $w", $p)['n'];
  $rows = orows("SELECT * FROM conflict_checks WHERE $w ORDER BY id DESC LIMIT $per OFFSET " . ((max(1, $page) - 1) * $per), $p);
  return [$rows, $total];
}
/** Checks in which a contact was matched. */
function checks_for_contact(int $contactId): array {
  return orows('SELECT DISTINCT k.id, k.ref, k.subject, k.status, k.created_at FROM conflict_hits h JOIN conflict_checks k ON k.id = h.check_id AND k.office_id = h.office_id WHERE h.office_id = :office AND h.contact_id = :c ORDER BY k.id DESC', ['c' => $contactId]);
}
function staff_name(?int $id): string { if (!$id) return '—'; $s = orow('SELECT full_name FROM staff WHERE office_id = :office AND id = :id', ['id' => $id]); return $s['full_name'] ?? '—'; }
