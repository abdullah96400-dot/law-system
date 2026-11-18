<?php
/* ==========================================================================
   Office isolation (tenancy).
   Rule: every query on office data goes through these helpers.
   - The SQL must contain the named placeholder :office; the helper fills it
     with the signed-in person's office. A query without :office is refused
     (LogicException), and a caller cannot pass its own 'office' value.
   - Inserts get office_id set here; updates and deletes always add
     "AND office_id = :office".
   Only `offices` itself and schema_migrations are read without a scope.
   ========================================================================== */
declare(strict_types=1);

/** The office whose data this request may touch. Set at sign-in; tests and CLI tools may set it explicitly. */
function office_id(): int {
  $id = $GLOBALS['OFFICE_SCOPE'] ?? null;
  if (!$id) throw new LogicException('No office in scope');
  return (int)$id;
}
function set_office_scope(?int $id): void { $GLOBALS['OFFICE_SCOPE'] = $id; }
function current_office(): ?array {
  $id = $GLOBALS['OFFICE_SCOPE'] ?? null; if (!$id) return null;
  static $cache = []; return $cache[$id] ??= row('SELECT * FROM offices WHERE id=?', [(int)$id]);
}

function oq_check(string $sql, array $p): array {
  if (!str_contains($sql, ':office')) throw new LogicException('Office query without :office scope');
  if (array_key_exists('office', $p)) throw new LogicException('The office scope cannot be passed in');
  foreach ($p as $k => $_) if (is_int($k)) throw new LogicException('Office queries use named parameters only');
  $p['office'] = office_id();
  return $p;
}
/** MySQL's native prepared statements refuse a named placeholder used twice (e.g. :office in a sub-query).
    Give each repeat its own name with the same value. */
function oq_expand(string $sql, array $p): array {
  $seen = [];
  $sql = preg_replace_callback('/(?<!:):([a-z_][a-z0-9_]*)/i', function ($m) use (&$seen, &$p) {
    $k = $m[1]; $seen[$k] = ($seen[$k] ?? 0) + 1;
    if ($seen[$k] === 1 || !array_key_exists($k, $p)) return $m[0];
    $nk = $k . '__' . $seen[$k]; $p[$nk] = $p[$k]; return ':' . $nk;
  }, $sql);
  return [$sql, $p];
}
function oq(string $sql, array $p = []): PDOStatement { [$sql, $p] = oq_expand($sql, oq_check($sql, $p)); return q($sql, $p); }
function orow(string $sql, array $p = []): ?array { $r = oq($sql, $p)->fetch(); return $r ?: null; }
function orows(string $sql, array $p = []): array { return oq($sql, $p)->fetchAll(); }

/** Insert a row into an office table; office_id is always the scope's. Returns the new id. */
function o_insert(string $table, array $data): int {
  if (!preg_match('/^[a-z_]+$/', $table)) throw new LogicException('Bad table');
  unset($data['office_id']); $data = ['office_id' => office_id()] + $data;
  foreach (array_keys($data) as $c) if (!preg_match('/^[a-z][a-z0-9_]*$/', $c)) throw new LogicException('Bad column');
  $cols = array_keys($data);
  q("INSERT INTO $table (" . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', array_values($data));
  return (int)db()->lastInsertId();
}
/** Update one row by id inside the office. Returns rows changed (0 = not this office's row). */
function o_update(string $table, int $id, array $data): int {
  if (!preg_match('/^[a-z_]+$/', $table)) throw new LogicException('Bad table');
  unset($data['office_id'], $data['id']); if (!$data) return 0;
  $set = []; $p = [];
  foreach ($data as $c => $v) { if (!preg_match('/^[a-z][a-z0-9_]*$/', $c)) throw new LogicException('Bad column'); $set[] = "$c = :v_$c"; $p["v_$c"] = $v; }
  $p['id'] = $id;
  return oq("UPDATE $table SET " . implode(', ', $set) . ' WHERE id = :id AND office_id = :office', $p)->rowCount();
}
