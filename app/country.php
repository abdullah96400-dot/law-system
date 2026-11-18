<?php
/* ==========================================================================
   Country packs. What differs between GCC countries is kept here, so a new
   country is a new pack, not new code: currency and its decimals, the first
   day of the week and the weekend, the time zone, the phone prefix, the kinds
   of identity number, and the starting lists a new office gets.
   Only Oman ('OM') is filled in for this release. The lists are generic
   starting points the office edits; nothing here is an official list, and
   no deadline or legal rule is derived from a pack.
   ========================================================================== */
declare(strict_types=1);

const COUNTRY_PACKS = [
  'OM' => [
    'name' => ['Oman', 'سلطنة عُمان'], 'currency' => 'OMR', 'currency_label' => ['OMR', 'ر.ع.'], 'decimals' => 3,
    'week_start' => 6, 'weekend' => [5, 6], 'timezone' => 'Asia/Muscat', 'phone_prefix' => '+968',
    'id_types' => ['civil_id', 'passport', 'cr'],
    'lists' => [
      'case_type' => [['مدني', 'Civil'], ['تجاري', 'Commercial'], ['عمالي', 'Labour'], ['أحوال شخصية', 'Personal status'], ['جزائي', 'Criminal'],
                      ['إداري', 'Administrative'], ['عقاري وإيجارات', 'Property and rent'], ['استشارة', 'Advice only']],
      'court_level' => [['ابتدائي', 'First instance'], ['استئناف', 'Appeal'], ['عليا', 'Supreme']],
    ],
  ],
];
function country_code(?array $o = null): string { $o ??= current_office(); $c = strtoupper((string)($o['country'] ?? cfg('country', 'OM'))); return isset(COUNTRY_PACKS[$c]) ? $c : 'OM'; }
function country_pack(?array $o = null): array { return COUNTRY_PACKS[country_code($o)]; }
function country_week_start(): int { return (int)country_pack()['week_start']; }
