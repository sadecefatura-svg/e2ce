<?php
namespace App\Core;

final class Util {
  public static function slugify(string $text): string {
    // Try convert accents → ASCII
    if (function_exists('iconv')) {
      $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
      if ($converted !== false) $text = $converted;
    }
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = trim($text, '-');
    $text = strtolower($text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    return $text !== '' ? $text : 'n-a';
  }

  public static function sanitizeFileName(string $name): string {
    $name = str_replace('\\', '/', $name);
    $name = basename($name);
    $name = preg_replace('/[^\w\.\-]+/u', '_', $name);
    if (strlen($name) > 200) $name = substr($name, 0, 200);
    return $name ?: 'file';
  }

  public static function randomString(int $len = 8): string {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $s = '';
    for ($i=0; $i<$len; $i++) $s .= $chars[random_int(0, strlen($chars)-1)];
    return $s;
  }

  public static function ensureDir(string $path): void {
    if (!is_dir($path)) {
      mkdir($path, 0775, true);
    }
  }

  /** "20M", "512K", "2G" → bytes */
  public static function toBytes(string $val): int {
    $val = trim($val);
    if ($val === '') return 0;
    $last = strtolower(substr($val, -1));
    $num = (int)$val;
    switch ($last) {
      case 'g': return $num * 1024 * 1024 * 1024;
      case 'm': return $num * 1024 * 1024;
      case 'k': return $num * 1024;
      default:  return (int)$val;
    }
  }
}
