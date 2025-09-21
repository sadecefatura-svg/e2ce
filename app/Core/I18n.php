<?php
namespace App\Core;

final class I18n {
  private static string $current = 'en';

  /** code => display name */
  public static function supported(): array {
    return [
      'en' => 'English',
      'es' => 'Español',
      'ar' => 'العربية',
      'tr' => 'Türkçe',
      'fr' => 'Français',
      'de' => 'Deutsch',
      'pt' => 'Português',
      'ru' => 'Русский',
      'hi' => 'हिन्दी',
      'zh' => '中文',
      'ja' => '日本語',
    ];
  }

  public static function isSupported(string $code): bool {
    return isset(self::supported()[$code]);
  }

  public static function set(string $code): void {
    self::$current = self::isSupported($code) ? $code : self::default();
    // 6 ay cookie
    setcookie('lang', self::$current, [
      'expires'  => time() + 60*60*24*180,
      'path'     => '/',
      'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
      'httponly' => false,
      'samesite' => 'Lax',
    ]);
  }

  public static function get(): string { return self::$current; }

  public static function default(): string { return 'en'; }

  public static function fromAcceptLanguage(string $header): string {
    $langs = self::supported();
    $best = self::default(); $bestQ = 0.0;
    foreach (explode(',', $header) as $part) {
      if (!preg_match('/^\s*([a-zA-Z-]+)(?:;\s*q=([0-9.]+))?/', $part, $m)) continue;
      $code = strtolower($m[1]);
      $q = isset($m[2]) ? (float)$m[2] : 1.0;
      $short = substr($code, 0, 2);
      if ($q > $bestQ) {
        if (isset($langs[$code])) { $best = $code; $bestQ = $q; }
        elseif (isset($langs[$short])) { $best = $short; $bestQ = $q; }
      }
    }
    return $best;
  }

  public static function isRtl(string $code): bool {
    return in_array($code, ['ar', 'he', 'fa', 'ur'], true);
  }

  /** /path → /{lang}/path */
  public static function prefix(string $path, ?string $lang=null): string {
    $lang = $lang ?: self::get();
    if ($path === '' || $path[0] !== '/') $path = '/' . $path;
    // admin ve teknik yolları öneklemeyelim
    if (preg_match('#^/(admin|robots\.txt|sitemap\.xml|documents|uploads|assets|static|vendor)/#', $path)) {
      return $path;
    }
    if ($path === '/robots.txt' || $path === '/sitemap.xml') return $path;
    return '/' . $lang . $path;
  }
}
