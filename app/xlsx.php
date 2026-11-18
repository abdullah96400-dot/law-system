<?php
/* ==========================================================================
   Minimal Excel (.xlsx) writer and reader — no outside library.
   Writer: one or more sheets of plain rows; numbers stay numbers, text is
   stored as inline strings (Arabic is kept as UTF-8). Sheets are written
   right-to-left when asked.
   Reader: first sheet only, returns rows of cell texts (shared strings and
   inline strings), enough for importing a template filled in Excel.
   ========================================================================== */
declare(strict_types=1);

function xlsx_col(int $i): string { $s = ''; $i++; while ($i > 0) { $m = ($i - 1) % 26; $s = chr(65 + $m) . $s; $i = intdiv($i - $m, 26); } return $s; }
function xlsx_esc(string $s): string { return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s), ENT_XML1 | ENT_QUOTES, 'UTF-8'); }
/** $sheets = [name => [[cell, …], …]]. Returns the file bytes. */
function xlsx_build(array $sheets, bool $rtl = false): string {
  $tmp = tempnam(sys_get_temp_dir(), 'xlsx'); $z = new ZipArchive(); $z->open($tmp, ZipArchive::OVERWRITE);
  $n = count($sheets); $ct = ''; $wbs = ''; $rels = '';
  $i = 0;
  foreach ($sheets as $name => $rows) {
    $i++; $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"' . ($rtl ? ' rightToLeft="1"' : '') . '/></sheetViews><sheetData>';
    foreach (array_values($rows) as $r => $cells) {
      $xml .= '<row r="' . ($r + 1) . '">';
      foreach (array_values($cells) as $c => $v) {
        $ref = xlsx_col($c) . ($r + 1);
        if ($v === null || $v === '') continue;
        if ((is_int($v) || is_float($v)) && is_finite((float)$v)) $xml .= '<c r="' . $ref . '"' . ($r === 0 ? ' s="1"' : '') . '><v>' . $v . '</v></c>';
        else $xml .= '<c r="' . $ref . '" t="inlineStr"' . ($r === 0 ? ' s="1"' : '') . '><is><t xml:space="preserve">' . xlsx_esc((string)$v) . '</t></is></c>';
      }
      $xml .= '</row>';
    }
    $z->addFromString("xl/worksheets/sheet$i.xml", $xml . '</sheetData></worksheet>');
    $ct .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
    $wbs .= '<sheet name="' . xlsx_esc(mb_substr(preg_replace('#[\[\]:*?/\\\\]#', ' ', (string)$name), 0, 31)) . '" sheetId="' . $i . '" r:id="rId' . $i . '"/>';
    $rels .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
  }
  $rels .= '<Relationship Id="rId' . ($n + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
  $z->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' . $ct . '</Types>');
  $z->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
  $z->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>' . $wbs . '</sheets></workbook>');
  $z->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels . '</Relationships>');
  $z->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Arial"/></font><font><b/><sz val="11"/><name val="Arial"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="2"><xf fontId="0"/><xf fontId="1" applyFont="1"/></cellXfs></styleSheet>');
  $z->close(); $bytes = (string)file_get_contents($tmp); @unlink($tmp); return $bytes;
}
function xlsx_send(string $filename, array $sheets): never {
  $b = xlsx_build($sheets, lang() === 'ar');
  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  header('Content-Disposition: attachment; filename="' . $filename . '"'); header('Content-Length: ' . strlen($b));
  echo $b; exit;
}
/** First sheet as rows of strings. Returns null if the file is not a readable .xlsx. */
function xlsx_read(string $path, int $maxRows = 5000): ?array {
  $z = new ZipArchive(); if ($z->open($path) !== true) return null;
  $shared = [];
  if (($ss = $z->getFromName('xl/sharedStrings.xml')) !== false) {
    $x = @simplexml_load_string($ss, 'SimpleXMLElement', LIBXML_NONET); if ($x) foreach ($x->si as $si) { $t = ''; if (isset($si->t)) $t = (string)$si->t; else foreach ($si->r as $r) $t .= (string)$r->t; $shared[] = $t; }
  }
  $sheet = $z->getFromName('xl/worksheets/sheet1.xml'); $z->close();
  if ($sheet === false) return null;
  $x = @simplexml_load_string($sheet, 'SimpleXMLElement', LIBXML_NONET); if (!$x) return null;
  $rows = [];
  foreach ($x->sheetData->row as $row) {
    $cells = [];
    foreach ($row->c as $c) {
      $ref = (string)$c['r']; $col = 0; if (preg_match('/^([A-Z]+)/', $ref, $m)) { foreach (str_split($m[1]) as $ch) $col = $col * 26 + ord($ch) - 64; $col--; }
      $t = (string)$c['t'];
      $v = $t === 's' ? ($shared[(int)$c->v] ?? '') : ($t === 'inlineStr' ? (string)$c->is->t : (string)$c->v);
      $cells[$col] = trim($v);
    }
    if ($cells) { $max = max(array_keys($cells)); $r = []; for ($i = 0; $i <= $max; $i++) $r[] = $cells[$i] ?? ''; $rows[] = $r; }
    if (count($rows) >= $maxRows) break;
  }
  return $rows;
}
