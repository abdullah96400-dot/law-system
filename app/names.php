<?php
/* ==========================================================================
   Name matching for duplicate warnings and conflict checks.
   - Arabic: diacritics and tatweel removed; أ إ آ ٱ → ا, ة → ه, ى → ي,
     ؤ → و, ئ → ي; a leading "ال" is dropped from longer words.
   - English: lower case; "al"/"el" prefixes dropped.
   - Connecting words (bin, bint, ibn, بن, بنت…) and company suffixes
     (LLC, SAOC, شركة, مؤسسة…) are ignored.
   - Words match when equal, or when long enough and one letter apart
     (Mohammed / Mohamed, Salim / Salem, Harthy / Harthi).
   Limitation: Arabic and English spellings of the same name are not
   compared with each other. Enter both forms when you know them.
   The result is a prompt for a lawyer to review, never a decision.
   ========================================================================== */
declare(strict_types=1);

const NAME_STOP = ['بن', 'بنت', 'ابن', 'ابنه', 'و', 'bin', 'bint', 'ibn', 'bn', 'b', 'al', 'el', 'the', 'and', 'of',
  'llc', 'spc', 'saoc', 'saog', 'co', 'company', 'est', 'establishment', 'trading', 'group', 'ltd', 'inc',
  'شركه', 'مؤسسه', 'ش', 'م', 'ع', 'ل', 'ذ', 'مجموعه', 'للتجاره', 'التجاريه', 'ش.م.م'];
const HIT_SCORE = 60;

function name_tokens(string $s): array {
  $s = mb_strtolower($s);
  $s = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $s);
  $s = strtr($s, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا', 'ة' => 'ه', 'ى' => 'ي', 'ؤ' => 'و', 'ئ' => 'ي', 'گ' => 'ك', 'ک' => 'ك', 'ی' => 'ي']);
  $s = digits_only_keep($s);
  $s = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s);
  $out = [];
  foreach (preg_split('/\s+/u', trim($s)) as $w) {
    if ($w === '' || in_array($w, NAME_STOP, true)) continue;
    if (mb_strlen($w) > 4 && str_starts_with($w, 'ال')) $w = mb_substr($w, 2);
    elseif (preg_match('/^(al|el)([a-z]{4,})$/', $w, $m)) $w = $m[2];
    if (!in_array($w, $out, true)) $out[] = $w;
  }
  return $out;
}
function digits_only_keep(string $s): string { return strtr($s, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']); }
function name_norm(string $s): string { return implode(' ', name_tokens($s)); }

/** Edit distance, capped: returns max+1 as soon as it is exceeded. Multibyte-safe. */
function mb_lev(string $a, string $b, int $max = 1): int {
  $x = mb_str_split($a); $y = mb_str_split($b); $n = count($x); $m = count($y);
  if (abs($n - $m) > $max) return $max + 1;
  $prev = range(0, $m);
  for ($i = 1; $i <= $n; $i++) {
    $cur = [$i]; $best = $i;
    for ($j = 1; $j <= $m; $j++) { $cur[$j] = min($prev[$j] + 1, $cur[$j - 1] + 1, $prev[$j - 1] + ($x[$i - 1] === $y[$j - 1] ? 0 : 1)); $best = min($best, $cur[$j]); }
    if ($best > $max) return $max + 1;
    $prev = $cur;
  }
  return $prev[$m];
}
function token_match(string $a, string $b): bool {
  if ($a === $b) return true;
  return mb_strlen($a) >= 4 && mb_strlen($b) >= 4 && mb_lev($a, $b, 1) <= 1;
}
/** 0–100. Two normalised names (space-separated tokens). */
function name_score(string $na, string $nb): int {
  $A = $na === '' ? [] : explode(' ', $na); $B = $nb === '' ? [] : explode(' ', $nb);
  if (!$A || !$B) return 0;
  if ($na === $nb) return 100;
  [$short, $long] = count($A) <= count($B) ? [$A, $B] : [$B, $A];
  $matched = 0; $used = [];
  foreach ($short as $t) foreach ($long as $i => $u) if (!isset($used[$i]) && token_match($t, $u)) { $matched++; $used[$i] = true; break; }
  $score = (int)round(100 * $matched / count($long));
  if (count($short) >= 2 && $matched === count($short)) $score = max($score, 85);   // every word of the shorter name appears in the longer one
  return $score;
}
