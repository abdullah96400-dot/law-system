<?php
/* Shared start for command-line tools. Never reachable from the web (.htaccess + this check). */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../app/bootstrap.php';
function arg(string $name, ?string $def = null): ?string {
  global $argv; foreach ($argv as $a) if (str_starts_with($a, "--$name=")) return substr($a, strlen($name) + 3);
  return in_array("--$name", $argv, true) ? '1' : $def;
}
function out(string $s): void { fwrite(STDOUT, $s . "\n"); }
function fail(string $s): never { fwrite(STDERR, "Error: $s\n"); exit(1); }
