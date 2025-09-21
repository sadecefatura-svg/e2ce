<?php
namespace App\Core;

final class Env {
  public static function load(string $file): void {
    if (!is_file($file)) return;
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
      if (str_starts_with(trim($line), '#')) continue;
      [$k, $v] = array_map('trim', explode('=', $line, 2) + [null, null]);
      if ($k === null) continue;
      if (!array_key_exists($k, $_ENV) && !getenv($k)) {
        $_ENV[$k] = $v;
        putenv("$k=$v");
      }
    }
  }
  public static function get(string $key, $default=null) {
    $v = getenv($key);
    return $v !== false ? $v : $default;
  }
}
