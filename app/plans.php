<?php
/* ==========================================================================
   Plan features. Al Imtyaz turns modules on or off per office through the
   provisioning API (features), to match the subscription plan. An office
   with no feature list has everything on. Turning a feature off hides it;
   nothing is deleted, and data comes back when it is turned on again.
   ========================================================================== */
declare(strict_types=1);

const PLAN_FEATURES = ['portal', 'finance', 'delegations', 'templates', 'reports', 'import', 'branches', 'website'];

function office_features(?array $o = null): ?array {
  $o ??= current_office(); if (!$o || empty($o['features'])) return null;
  $f = json_decode((string)$o['features'], true); return is_array($f) ? array_values(array_intersect($f, PLAN_FEATURES)) : null;
}
function feature_on(string $k, ?array $o = null): bool { $f = office_features($o); return $f === null || in_array($k, $f, true); }
function trash_can_view(?array $me): bool { return $me && in_array($me['role'], ['partner', 'office_manager'], true); }
