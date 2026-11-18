<?php
/* ==========================================================================
   Office-editable lists used by case files: case types, courts, court
   levels and case statuses. Each office edits its own lists.
   A new office starts with a short generic list (no court names: the office
   adds its own courts). Nothing here claims to be an official Omani list;
   items are renamed or switched off, never deleted, so old files keep their
   labels.
   ========================================================================== */
declare(strict_types=1);

const LOOKUP_KINDS = ['case_type', 'court', 'court_level', 'status', 'doc_category'];
const LOOKUP_DEFAULTS = [
  'case_type' => [['مدني', 'Civil'], ['تجاري', 'Commercial'], ['عمالي', 'Labour'], ['أحوال شخصية', 'Personal status'], ['جزائي', 'Criminal'],
                  ['إداري', 'Administrative'], ['عقاري وإيجارات', 'Property and rent'], ['استشارة', 'Advice only']],
  'doc_category' => [['وكالة', 'Power of attorney'], ['عقد', 'Contract'], ['اتفاقية أتعاب', 'Fee agreement'], ['صحيفة دعوى ومذكرات', 'Pleadings and memos'],
                     ['حكم أو قرار', 'Judgment or decision'], ['مراسلات', 'Correspondence'], ['مستند من الموكل', 'From the client'], ['أخرى', 'Other']],
  'court_level' => [['ابتدائي', 'First instance'], ['استئناف', 'Appeal'], ['عليا', 'Supreme']],
  'status' => [['استشارة أولية', 'Initial consultation'], ['قيد المراجعة', 'Under review'], ['نشطة', 'Active'], ['بانتظار جلسة', 'Awaiting hearing'],
               ['بانتظار مستند', 'Awaiting document'], ['مؤجلة', 'Postponed'], ['صدر حكم', 'Judgment issued'], ['استئناف', 'Appeal'],
               ['تنفيذ', 'Enforcement'], ['مغلقة', 'Closed', 1]],
];

/** Items of a kind for the office in scope. Seeds the starting list the first time. */
function lookups(string $kind, bool $withInactive = false): array {
  if (!in_array($kind, LOOKUP_KINDS, true)) return [];
  $any = orow('SELECT COUNT(*) n FROM lookups WHERE office_id = :office AND kind = :k', ['k' => $kind]);
  $defaults = country_pack()['lists'][$kind] ?? LOOKUP_DEFAULTS[$kind] ?? null;   // the country pack first, then the generic list
  if ((int)$any['n'] === 0 && $defaults) foreach ($defaults as $i => $d)
    o_insert('lookups', ['kind' => $kind, 'name_ar' => $d[0], 'name_en' => $d[1], 'is_closed' => (int)($d[2] ?? 0), 'active' => 1, 'sort' => ($i + 1) * 10]);
  return orows('SELECT * FROM lookups WHERE office_id = :office AND kind = :k' . ($withInactive ? '' : ' AND active = 1') . ' ORDER BY sort, id', ['k' => $kind]);
}
function lookup_get(?int $id, string $kind): ?array {
  if (!$id) return null;
  return orow('SELECT * FROM lookups WHERE office_id = :office AND id = :id AND kind = :k', ['id' => $id, 'k' => $kind]);
}
function lookup_label(?array $l): string { if (!$l) return '—'; return lang() === 'ar' ? ($l['name_ar'] ?: $l['name_en']) : ($l['name_en'] ?: $l['name_ar']); }
function lookup_name(?int $id, string $kind): string { return lookup_label(lookup_get($id, $kind)); }

function lookup_add(array $me, string $kind, string $ar, string $en, bool $closed = false): ?string {
  if (!in_array($kind, LOOKUP_KINDS, true)) return 'not_found';
  $ar = mb_substr(trim($ar), 0, 160); $en = mb_substr(trim($en), 0, 160);
  if ($ar === '' && $en === '') return 'lookup_name_required';
  lookups($kind);   // make sure defaults exist first, so the new item sorts after them
  $max = orow('SELECT MAX(sort) m FROM lookups WHERE office_id = :office AND kind = :k', ['k' => $kind]);
  $id = o_insert('lookups', ['kind' => $kind, 'name_ar' => $ar ?: $en, 'name_en' => $en ?: $ar, 'is_closed' => $kind === 'status' && $closed ? 1 : 0, 'active' => 1, 'sort' => (int)$max['m'] + 10]);
  law_audit('list_item_added', 'lookup', $id, ['kind' => $kind], null, $me);
  return null;
}
function lookup_update(array $me, int $id, string $ar, string $en, bool $active): ?string {
  $l = orow('SELECT * FROM lookups WHERE office_id = :office AND id = :id', ['id' => $id]); if (!$l) return 'not_found';
  $ar = mb_substr(trim($ar), 0, 160); $en = mb_substr(trim($en), 0, 160);
  if ($ar === '' && $en === '') return 'lookup_name_required';
  if (!$active && $l['kind'] === 'status' && !$l['is_closed']) {
    $open = orow('SELECT COUNT(*) n FROM lookups WHERE office_id = :office AND kind = :k AND active = 1 AND is_closed = 0 AND id <> :id', ['k' => 'status', 'id' => $id]);
    if ((int)$open['n'] === 0) return 'lookup_last_open_status';
  }
  o_update('lookups', $id, ['name_ar' => $ar ?: $en, 'name_en' => $en ?: $ar, 'active' => $active ? 1 : 0]);
  law_audit('list_item_updated', 'lookup', $id, ['kind' => $l['kind'], 'active' => $active], null, $me);
  return null;
}
