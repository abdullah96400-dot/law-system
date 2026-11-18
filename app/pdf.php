<?php
/* PDF writer copied from the platform (app/pdf.php): DejaVu Sans subset, Arabic shaping and right-to-left ordering. No outside library. */
declare(strict_types=1);

final class ImzFont {
  public int $upm = 1000; public int $asc = 800; public int $desc = -200; public array $bbox = [0, -200, 1000, 800]; public int $cap = 700;
  public array $cmap = []; public array $adv = []; public string $data; public array $used = [];
  public function __construct(public string $name, string $path) {
    $this->data = (string)file_get_contents($path); $d = $this->data; $n = unpack('n', $d, 4)[1]; $t = [];
    for ($i = 0; $i < $n; $i++) { $r = unpack('a4tag/Nsum/Noff/Nlen', $d, 12 + 16 * $i); $t[$r['tag']] = $r['off']; }
    $h = $t['head']; $this->upm = unpack('n', $d, $h + 18)[1]; $b = unpack('n4', $d, $h + 36); $s = fn($v) => $v >= 0x8000 ? $v - 0x10000 : $v; $this->bbox = [$s($b[1]), $s($b[2]), $s($b[3]), $s($b[4])];
    $hh = $t['hhea']; $this->asc = $s(unpack('n', $d, $hh + 4)[1]); $this->desc = $s(unpack('n', $d, $hh + 6)[1]); $nm = unpack('n', $d, $hh + 34)[1];
    if (isset($t['OS/2']) && unpack('n', $d, $t['OS/2'])[1] >= 2) $this->cap = $s(unpack('n', $d, $t['OS/2'] + 88)[1]); else $this->cap = $this->asc;
    $ng = unpack('n', $d, $t['maxp'] + 4)[1]; $last = 0;
    for ($g = 0; $g < $ng; $g++) { if ($g < $nm) $last = unpack('n', $d, $t['hmtx'] + 4 * $g)[1]; $this->adv[$g] = $last; }
    $c = $t['cmap']; $nt = unpack('n', $d, $c + 2)[1]; $sub = null;
    for ($i = 0; $i < $nt; $i++) { $r = unpack('npid/neid/Noff', $d, $c + 4 + 8 * $i); if (($r['pid'] === 3 && $r['eid'] === 1) || ($r['pid'] === 0 && $sub === null)) $sub = $c + $r['off']; }
    if ($sub !== null && unpack('n', $d, $sub)[1] === 4) {
      $seg = unpack('n', $d, $sub + 6)[1] / 2; $e = $sub + 14; $st = $e + 2 * $seg + 2; $dl = $st + 2 * $seg; $ro = $dl + 2 * $seg;
      for ($i = 0; $i < $seg; $i++) { $end = unpack('n', $d, $e + 2 * $i)[1]; $start = unpack('n', $d, $st + 2 * $i)[1]; $delta = unpack('n', $d, $dl + 2 * $i)[1]; $rof = unpack('n', $d, $ro + 2 * $i)[1];
        if ($start === 0xFFFF) continue;
        for ($ch = $start; $ch <= $end; $ch++) { if ($rof === 0) $g = ($ch + $delta) & 0xFFFF; else { $p = $ro + 2 * $i + $rof + 2 * ($ch - $start); $g = unpack('n', $d, $p)[1]; if ($g) $g = ($g + $delta) & 0xFFFF; } if ($g) $this->cmap[$ch] = $g; } }
    }
  }
  /** Glyph ids for code points (unknown characters become "?"). */
  public function glyphs(array $cps): array { $o = []; foreach ($cps as $c) { $g = $this->cmap[$c] ?? ($this->cmap[63] ?? 0); $this->used[$g] = $c; $o[] = $g; } return $o; }
  public function width(array $cps, float $size): float { $w = 0; foreach ($cps as $c) $w += $this->adv[$this->cmap[$c] ?? ($this->cmap[63] ?? 0)] ?? 500; return $w * $size / $this->upm; }
}

/** Arabic joining and right-to-left ordering. */
final class ImzBidi {
  // letter => [alone, final, initial, middle] (0 = this form does not exist: the letter joins only to the right)
  private const F = [0x0621 => [0xFE80, 0, 0, 0], 0x0622 => [0xFE81, 0xFE82, 0, 0], 0x0623 => [0xFE83, 0xFE84, 0, 0], 0x0624 => [0xFE85, 0xFE86, 0, 0], 0x0625 => [0xFE87, 0xFE88, 0, 0],
    0x0626 => [0xFE89, 0xFE8A, 0xFE8B, 0xFE8C], 0x0627 => [0xFE8D, 0xFE8E, 0, 0], 0x0628 => [0xFE8F, 0xFE90, 0xFE91, 0xFE92], 0x0629 => [0xFE93, 0xFE94, 0, 0], 0x062A => [0xFE95, 0xFE96, 0xFE97, 0xFE98],
    0x062B => [0xFE99, 0xFE9A, 0xFE9B, 0xFE9C], 0x062C => [0xFE9D, 0xFE9E, 0xFE9F, 0xFEA0], 0x062D => [0xFEA1, 0xFEA2, 0xFEA3, 0xFEA4], 0x062E => [0xFEA5, 0xFEA6, 0xFEA7, 0xFEA8], 0x062F => [0xFEA9, 0xFEAA, 0, 0],
    0x0630 => [0xFEAB, 0xFEAC, 0, 0], 0x0631 => [0xFEAD, 0xFEAE, 0, 0], 0x0632 => [0xFEAF, 0xFEB0, 0, 0], 0x0633 => [0xFEB1, 0xFEB2, 0xFEB3, 0xFEB4], 0x0634 => [0xFEB5, 0xFEB6, 0xFEB7, 0xFEB8],
    0x0635 => [0xFEB9, 0xFEBA, 0xFEBB, 0xFEBC], 0x0636 => [0xFEBD, 0xFEBE, 0xFEBF, 0xFEC0], 0x0637 => [0xFEC1, 0xFEC2, 0xFEC3, 0xFEC4], 0x0638 => [0xFEC5, 0xFEC6, 0xFEC7, 0xFEC8], 0x0639 => [0xFEC9, 0xFECA, 0xFECB, 0xFECC],
    0x063A => [0xFECD, 0xFECE, 0xFECF, 0xFED0], 0x0641 => [0xFED1, 0xFED2, 0xFED3, 0xFED4], 0x0642 => [0xFED5, 0xFED6, 0xFED7, 0xFED8], 0x0643 => [0xFED9, 0xFEDA, 0xFEDB, 0xFEDC], 0x0644 => [0xFEDD, 0xFEDE, 0xFEDF, 0xFEE0],
    0x0645 => [0xFEE1, 0xFEE2, 0xFEE3, 0xFEE4], 0x0646 => [0xFEE5, 0xFEE6, 0xFEE7, 0xFEE8], 0x0647 => [0xFEE9, 0xFEEA, 0xFEEB, 0xFEEC], 0x0648 => [0xFEED, 0xFEEE, 0, 0], 0x0649 => [0xFEEF, 0xFEF0, 0, 0],
    0x064A => [0xFEF1, 0xFEF2, 0xFEF3, 0xFEF4], 0x0671 => [0xFB50, 0xFB51, 0, 0], 0x067E => [0xFB56, 0xFB57, 0xFB58, 0xFB59], 0x0686 => [0xFB7A, 0xFB7B, 0xFB7C, 0xFB7D], 0x0698 => [0xFB8A, 0xFB8B, 0, 0],
    0x06A9 => [0xFB8E, 0xFB8F, 0xFB90, 0xFB91], 0x06AF => [0xFB92, 0xFB93, 0xFB94, 0xFB95], 0x06CC => [0xFBFC, 0xFBFD, 0xFBFE, 0xFBFF], 0x0640 => [0x0640, 0x0640, 0x0640, 0x0640]];
  private const LA = [0x0622 => 0xFEF5, 0x0623 => 0xFEF7, 0x0625 => 0xFEF9, 0x0627 => 0xFEFB];
  private const MIRROR = [0x28 => 0x29, 0x29 => 0x28, 0x5B => 0x5D, 0x5D => 0x5B, 0x7B => 0x7D, 0x7D => 0x7B, 0x3C => 0x3E, 0x3E => 0x3C, 0xAB => 0xBB, 0xBB => 0xAB];
  public static function cps(string $s): array { return $s === '' ? [] : array_map(fn($c) => mb_ord($c, 'UTF-8'), mb_str_split($s, 1, 'UTF-8')); }
  private static function isAr(int $c): bool { return ($c >= 0x0600 && $c <= 0x06FF) || ($c >= 0xFB50 && $c <= 0xFDFF) || ($c >= 0xFE70 && $c <= 0xFEFF); }
  private static function isL(int $c): bool { return ($c >= 0x30 && $c <= 0x39) || ($c >= 0x41 && $c <= 0x5A) || ($c >= 0x61 && $c <= 0x7A) || ($c >= 0xC0 && $c <= 0x24F) || ($c >= 0x660 && $c <= 0x669); }
  public static function hasAr(string $s): bool { return (bool)preg_match('/[\x{0600}-\x{06FF}]/u', $s); }
  /** Join Arabic letters into their connected forms (logical order kept). */
  public static function shape(array $c): array {
    $c = array_values(array_filter($c, fn($x) => !($x >= 0x064B && $x <= 0x065F) && $x !== 0x0670 && $x !== 0x200F && $x !== 0x200E));   // vowel marks are left out
    $o = []; $n = count($c);
    $joinsLeft = fn($x) => isset(self::F[$x]) && self::F[$x][2] !== 0;   // can connect to the next letter
    for ($i = 0; $i < $n; $i++) {
      $x = $c[$i]; if (!isset(self::F[$x])) { $o[] = $x; continue; }
      $prev = $i > 0 && $joinsLeft($c[$i - 1]); $next = $i + 1 < $n && isset(self::F[$c[$i + 1]]);
      if ($x === 0x0644 && $i + 1 < $n && isset(self::LA[$c[$i + 1]])) { $o[] = self::LA[$c[$i + 1]] + ($prev ? 1 : 0); $i++; continue; }
      $f = self::F[$x]; $canNext = $f[2] !== 0 && $next;
      $o[] = $prev && $canNext ? $f[3] : ($prev ? ($f[1] ?: $f[0]) : ($canNext ? $f[2] : $f[0]));
    }
    return $o;
  }
  /** Visual order for drawing left to right. Text with Arabic is a right-to-left line; numbers and Latin words stay left to right inside it. */
  public static function visual(string $s): array {
    $c = self::cps($s); if (!self::hasAr($s)) return $c;
    $c = self::shape($c); $n = count($c); $t = [];
    foreach ($c as $i => $x) $t[$i] = self::isAr($x) ? 'R' : (self::isL($x) ? 'L' : 'N');
    for ($i = 0; $i < $n; $i++) if ($t[$i] === 'N') {   // a neutral between two left-to-right characters (like the dot in 25.000 or the dash in a date) stays with them
      $p = $i - 1; while ($p >= 0 && $t[$p] === 'N' && $c[$p] !== 0x20) $p--; $q = $i + 1; while ($q < $n && $t[$q] === 'N' && $c[$q] !== 0x20) $q++;
      $L = $p >= 0 && $t[$p] === 'L' && $c[$i] !== 0x20; $Lq = $q < $n && $t[$q] === 'L';
      $prefix = in_array($c[$i], [0x2B, 0x2D, 0x23, 0x24], true) && $i + 1 < $n && $t[$i + 1] === 'L';   // + - # $ in front of a number belong to it
      $t[$i] = $prefix || ($L && ($Lq || $q >= $n || $c[$q] === 0x20 || in_array($c[$i], [0x25, 0x2E, 0x3A, 0x2F], true))) || ($p >= 0 && $t[$p] === 'L' && $Lq) ? 'L' : 'R';
    }
    $runs = []; foreach ($c as $i => $x) { if ($runs && end($runs)[0] === $t[$i]) $runs[array_key_last($runs)][1][] = $x; else $runs[] = [$t[$i], [$x]]; }
    $o = []; foreach (array_reverse($runs) as [$d, $xs]) { if ($d === 'R') { $xs = array_reverse($xs); $xs = array_map(fn($x) => self::MIRROR[$x] ?? $x, $xs); } array_push($o, ...$xs); }
    return $o;
  }
}

final class ImzPdf {
  public float $w; public float $h; private array $pages = []; private int $p = -1; private array $fonts; private ?array $img = null; public bool $rtl = false;
  public function __construct(bool $landscape = true, bool $rtl = false) {
    [$this->w, $this->h] = $landscape ? [841.89, 595.28] : [595.28, 841.89]; $this->rtl = $rtl;
    $this->fonts = ['R' => new ImzFont('F1', __DIR__ . '/fonts/imz-sans.ttf'), 'B' => new ImzFont('F2', __DIR__ . '/fonts/imz-sans-bold.ttf')];
  }
  public function addPage(): void { $this->pages[] = ''; $this->p = count($this->pages) - 1; }
  public function pageCount(): int { return count($this->pages); }
  public function onPage(int $i): void { $this->p = $i; }
  private function out(string $s): void { $this->pages[$this->p] .= $s . "\n"; }
  private static function n(float $v): string { return rtrim(rtrim(sprintf('%.2F', $v), '0'), '.'); }
  public function width(string $s, float $size, bool $bold = false): float { return $this->fonts[$bold ? 'B' : 'R']->width(ImzBidi::visual($s), $size); }
  /** Draw text. $x is the left edge, or the right edge when $align is 'R', or the middle for 'C'. $y is from the top of the page. */
  public function text(float $x, float $y, string $s, float $size = 10, bool $bold = false, string $align = 'L', array $rgb = [0.1, 0.1, 0.15]): void {
    if ($s === '') return; $f = $this->fonts[$bold ? 'B' : 'R']; $cps = ImzBidi::visual($s); $w = $f->width($cps, $size);
    if ($align === 'R') $x -= $w; elseif ($align === 'C') $x -= $w / 2;
    $hex = implode('', array_map(fn($g) => sprintf('%04X', $g), $f->glyphs($cps)));
    $this->out(sprintf('BT %s %s %s rg /%s %s Tf %s %s Td <%s> Tj ET', self::n($rgb[0]), self::n($rgb[1]), self::n($rgb[2]), $f->name, self::n($size), self::n($x), self::n($this->h - $y), $hex));
  }
  /** Text cut to fit a width, with "…" when it is too long. */
  public function fit(string $s, float $max, float $size, bool $bold = false): string {
    if ($this->width($s, $size, $bold) <= $max) return $s; $c = mb_str_split($s);
    while ($c && $this->width(implode('', $c) . '…', $size, $bold) > $max) array_pop($c); return implode('', $c) . '…';
  }
  public function rect(float $x, float $y, float $w, float $h, ?array $fill = null, ?array $stroke = null, float $lw = 0.5): void {
    $op = $fill && $stroke ? 'B' : ($fill ? 'f' : 'S'); $s = '';
    if ($fill) $s .= sprintf('%s %s %s rg ', self::n($fill[0]), self::n($fill[1]), self::n($fill[2]));
    if ($stroke) $s .= sprintf('%s %s %s RG %s w ', self::n($stroke[0]), self::n($stroke[1]), self::n($stroke[2]), self::n($lw));
    $this->out($s . sprintf('%s %s %s %s re %s', self::n($x), self::n($this->h - $y - $h), self::n($w), self::n($h), $op));
  }
  public function line(float $x1, float $y1, float $x2, float $y2, array $rgb = [0.8, 0.8, 0.85], float $lw = 0.5): void {
    $this->out(sprintf('%s %s %s RG %s w %s %s m %s %s l S', self::n($rgb[0]), self::n($rgb[1]), self::n($rgb[2]), self::n($lw), self::n($x1), self::n($this->h - $y1), self::n($x2), self::n($this->h - $y2)));
  }
  /** Use one JPEG picture (bytes, pixel width and height); draw it with image(). */
  public function setImage(string $jpeg, int $pw, int $ph): void { $this->img = [$jpeg, $pw, $ph]; }
  public function image(float $x, float $y, float $w, float $h): void { if ($this->img) $this->out(sprintf('q %s 0 0 %s %s %s cm /Im1 Do Q', self::n($w), self::n($h), self::n($x), self::n($this->h - $y - $h))); }

  /** The finished file. */
  public function output(string $title = ''): string {
    $obj = []; $add = function (string $s) use (&$obj) { $obj[] = $s; return count($obj); };
    $fontRefs = [];
    foreach ($this->fonts as $k => $f) {
      $ttf = gzcompress($f->data); $ff = $add('<< /Length ' . strlen($ttf) . ' /Length1 ' . strlen($f->data) . " /Filter /FlateDecode >>\nstream\n" . $ttf . "\nendstream");
      $sc = 1000 / $f->upm; $bb = array_map(fn($v) => (int)round($v * $sc), $f->bbox);
      $fd = $add(sprintf('<< /Type /FontDescriptor /FontName /%s /Flags 32 /FontBBox [%s] /ItalicAngle 0 /Ascent %d /Descent %d /CapHeight %d /StemV 80 /FontFile2 %d 0 R >>', $k === 'B' ? 'ImzSans-Bold' : 'ImzSans', implode(' ', $bb), (int)round($f->asc * $sc), (int)round($f->desc * $sc), (int)round($f->cap * $sc), $ff));
      ksort($f->used); $wArr = ''; foreach ($f->used as $g => $_) $wArr .= $g . ' [' . (int)round(($f->adv[$g] ?? 0) * $sc) . '] ';
      $cid = $add(sprintf('<< /Type /Font /Subtype /CIDFontType2 /BaseFont /%s /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /FontDescriptor %d 0 R /W [%s] /CIDToGIDMap /Identity /DW 500 >>', $k === 'B' ? 'ImzSans-Bold' : 'ImzSans', $fd, $wArr));
      $cm = "/CIDInit /ProcSet findresource begin 12 dict begin begincmap /CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> def /CMapName /Adobe-Identity-UCS def /CMapType 2 def 1 begincodespacerange <0000> <FFFF> endcodespacerange\n";
      foreach (array_chunk($f->used, 100, true) as $ch) { $cm .= count($ch) . " beginbfchar\n"; foreach ($ch as $g => $u) $cm .= sprintf("<%04X> <%04X>\n", $g, $u); $cm .= "endbfchar\n"; }
      $cm .= 'endcmap CMapName currentdict /CMap defineresource pop end end'; $cmz = gzcompress($cm);
      $tu = $add('<< /Length ' . strlen($cmz) . " /Filter /FlateDecode >>\nstream\n" . $cmz . "\nendstream");
      $fontRefs[$f->name] = $add(sprintf('<< /Type /Font /Subtype /Type0 /BaseFont /%s /Encoding /Identity-H /DescendantFonts [%d 0 R] /ToUnicode %d 0 R >>', $k === 'B' ? 'ImzSans-Bold' : 'ImzSans', $cid, $tu));
    }
    $imgRef = $this->img ? $add(sprintf('<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>', $this->img[1], $this->img[2], strlen($this->img[0])) . "\nstream\n" . $this->img[0] . "\nendstream") : null;
    $res = '<< /Font << ' . implode(' ', array_map(fn($n, $r) => "/$n $r 0 R", array_keys($fontRefs), $fontRefs)) . ' >>' . ($imgRef ? " /XObject << /Im1 $imgRef 0 R >>" : '') . ' >>';
    $pagesId = count($obj) + 1 + 2 * count($this->pages); $kids = [];
    foreach ($this->pages as $c) { $z = gzcompress($c); $cs = $add('<< /Length ' . strlen($z) . " /Filter /FlateDecode >>\nstream\n" . $z . "\nendstream");
      $kids[] = $add(sprintf('<< /Type /Page /Parent %d 0 R /MediaBox [0 0 %s %s] /Resources %s /Contents %d 0 R >>', $pagesId, self::n($this->w), self::n($this->h), $res, $cs)); }
    $add('<< /Type /Pages /Kids [' . implode(' ', array_map(fn($k) => "$k 0 R", $kids)) . '] /Count ' . count($kids) . ' >>');
    $titleHex = 'FEFF' . strtoupper(bin2hex(mb_convert_encoding($title, 'UTF-16BE', 'UTF-8')));
    $info = $add("<< /Title <$titleHex> /Producer (Al Imtyaz) /CreationDate (D:" . date('YmdHis') . ') >>');
    $cat = $add("<< /Type /Catalog /Pages $pagesId 0 R" . ($this->rtl ? ' /ViewerPreferences << /Direction /R2L >>' : '') . ' >>');
    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n"; $off = [];
    foreach ($obj as $i => $s) { $off[$i + 1] = strlen($pdf); $pdf .= ($i + 1) . " 0 obj\n" . $s . "\nendobj\n"; }
    $x = strlen($pdf); $pdf .= 'xref' . "\n0 " . (count($obj) + 1) . "\n0000000000 65535 f \n"; foreach ($off as $o) $pdf .= sprintf("%010d 00000 n \n", $o);
    return $pdf . 'trailer << /Size ' . (count($obj) + 1) . " /Root $cat 0 R /Info $info 0 R >>\nstartxref\n$x\n%%EOF\n";
  }
}

