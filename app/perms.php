<?php
/* ==========================================================================
   Roles and permissions — checked on the server for every request.
   - Each office has its own matrix: role × module × action (stored in
     role_permissions; a row = granted). New offices get the defaults below.
   - The managing partner has every permission and cannot be limited, so an
     office can never lock itself out.
   - Staff, settings and audit permissions can be given to the office
     manager only, and only the managing partner changes the office manager's
     row. Nobody can raise their own access.
   - Client portal users are not staff and never pass these checks.
   ========================================================================== */
declare(strict_types=1);

const ROLES = ['partner', 'office_manager', 'lawyer', 'trainee', 'coordinator', 'accountant', 'reception'];
const MODULES = ['clients', 'conflicts', 'matters', 'calendar', 'tasks', 'documents', 'legal_notes', 'finance', 'reports', 'staff', 'settings', 'audit'];
const ACTIONS = ['view', 'create', 'edit', 'download', 'share', 'delete'];
const ADMIN_MODULES = ['staff', 'settings', 'audit'];
/** Modules whose screens exist in this release. The others are configurable now and take effect when they ship. */
const LIVE_MODULES = ['clients', 'conflicts', 'matters', 'legal_notes', 'calendar', 'tasks', 'documents', 'finance', 'reports', 'staff', 'settings', 'audit'];
/** What Al Imtyaz support may open during an approved visit: view only, never documents, money or legal notes. */
const SUPPORT_MODULES = ['clients', 'conflicts', 'matters', 'calendar', 'tasks', 'reports', 'staff', 'settings', 'audit'];
/** Roles that must use two-step sign-in. */
const ROLES_2FA = ['partner', 'office_manager'];

/** Starting matrix for a new office. v=view c=create e=edit d=download s=share x=delete */
const ROLE_DEFAULTS = [
  'office_manager' => ['clients' => 'vcedsx', 'conflicts' => 'vce', 'matters' => 'vcedsx', 'calendar' => 'vcex', 'tasks' => 'vcex', 'documents' => 'vcedsx',
                       'finance' => 'vcedx', 'reports' => 'vd', 'staff' => 'vcex', 'settings' => 've', 'audit' => 'vd'],
  'lawyer'      => ['clients' => 'vce', 'conflicts' => 'vc', 'matters' => 'vce', 'calendar' => 'vce', 'tasks' => 'vce', 'documents' => 'vceds', 'legal_notes' => 'vce', 'finance' => 'v', 'reports' => 'v'],
  'trainee'     => ['clients' => 'v', 'conflicts' => 'v', 'matters' => 'v', 'calendar' => 'vce', 'tasks' => 'vce', 'documents' => 'vcd', 'legal_notes' => 'vc'],
  'coordinator' => ['clients' => 'vce', 'conflicts' => 'vc', 'matters' => 'vce', 'calendar' => 'vce', 'tasks' => 'vce', 'documents' => 'vcd', 'reports' => 'v'],
  'accountant'  => ['clients' => 'v', 'matters' => 'v', 'finance' => 'vced', 'reports' => 'vd'],
  'reception'   => ['clients' => 'vc', 'conflicts' => 'vc', 'calendar' => 'vc', 'tasks' => 'v'],
];
const ACTION_LETTERS = ['v' => 'view', 'c' => 'create', 'e' => 'edit', 'd' => 'download', 's' => 'share', 'x' => 'delete'];

function seed_role_permissions(int $officeId): void {
  foreach (ROLE_DEFAULTS as $role => $mods) foreach ($mods as $mod => $letters) foreach (str_split($letters) as $l)
    q('INSERT INTO role_permissions (office_id, role, module, action) VALUES (?,?,?,?)', [$officeId, $role, $mod, ACTION_LETTERS[$l]]);
}
/** [module => [action => true]] for a role in the office in scope. */
function role_matrix(string $role): array {
  if ($role === 'partner') { $all = []; foreach (MODULES as $m) foreach (ACTIONS as $a) $all[$m][$a] = true; return $all; }
  $out = []; foreach (orows('SELECT module, action FROM role_permissions WHERE office_id = :office AND role = :role', ['role' => $role]) as $r) $out[$r['module']][$r['action']] = true;
  return $out;
}
function can(string $module, string $action, ?array $staff = null): bool {
  $s = $staff ?? current_staff(); if (!$s) return false;
  if ((int)$s['office_id'] !== office_id()) return false;
  if ($s['role'] === 'support') return $action === 'view' && in_array($module, SUPPORT_MODULES, true);
  if ($s['role'] === 'partner') return true;
  static $cache = [];
  $key = office_id() . '|' . $s['role'];
  $cache[$key] ??= role_matrix($s['role']);
  return !empty($cache[$key][$module][$action]);
}
function require_perm(string $module, string $action): array { $s = require_staff(); if (!can($module, $action, $s)) deny(); return $s; }
function perms_cache_clear(): void { /* role_matrix is cached per request; a new request reads the saved matrix */ }

/** Which role rows the signed-in person may change. */
function editable_roles(array $me): array {
  if ($me['role'] === 'partner') return array_values(array_diff(ROLES, ['partner']));
  if (can('settings', 'edit', $me)) return array_values(array_diff(ROLES, ['partner', 'office_manager']));
  return [];
}
/** Can this cell be granted to this role at all? */
function grantable(string $role, string $module): bool { return !in_array($module, ADMIN_MODULES, true) || $role === 'office_manager'; }

/** Replace a role's matrix from a form ($grid[module][action] = '1'). Returns [added, removed] counts. */
function save_role_matrix(array $me, string $role, array $grid): array {
  if (!in_array($role, editable_roles($me), true)) throw new RuntimeException('forbidden');
  $want = [];
  foreach (MODULES as $m) foreach (ACTIONS as $a) if (!empty($grid[$m][$a]) && grantable($role, $m)) $want["$m.$a"] = true;
  // "view" is needed for any other action on a module
  foreach (array_keys($want) as $k) { [$m] = explode('.', $k); $want["$m.view"] = true; }
  $have = []; foreach (role_matrix($role) as $m => $acts) foreach ($acts as $a => $_) $have["$m.$a"] = true;
  $add = array_diff_key($want, $have); $del = array_diff_key($have, $want);
  foreach (array_keys($add) as $k) { [$m, $a] = explode('.', $k); o_insert('role_permissions', ['role' => $role, 'module' => $m, 'action' => $a]); }
  foreach (array_keys($del) as $k) { [$m, $a] = explode('.', $k); oq('DELETE FROM role_permissions WHERE office_id = :office AND role = :role AND module = :m AND action = :a', ['role' => $role, 'm' => $m, 'a' => $a]); }
  if ($add || $del) law_audit('permissions_changed', 'role', $role, ['added' => array_keys($add), 'removed' => array_keys($del)]);
  return [count($add), count($del)];
}
