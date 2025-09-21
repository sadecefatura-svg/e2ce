<?php
namespace App\Core;

class Csrf {
  /** Config’ten session adı */
  private static function sessionName(): string {
    $cfgFile = dirname(__DIR__, 2) . '/config/config.php';
    $cfg = is_file($cfgFile) ? (require $cfgFile) : [];
    return $cfg['security']['session_name'] ?? 'E2CESESSID';
  }

  /** Config’ten CSRF key (override verilirse onu kullan) */
  private static function keyFromConfig(?string $override = null): string {
    if ($override && $override !== '') return $override;
    $cfgFile = dirname(__DIR__, 2) . '/config/config.php';
    $cfg = is_file($cfgFile) ? (require $cfgFile) : [];
    return (string)($cfg['security']['csrf_key'] ?? 'change_this_default_csrf_key');
  }

  /** Yalnızca çerez varsa session’ı aç (anonim GET’te cookie yoksa açma) */
  private static function ensureSessionIfCookie(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $sess = self::sessionName();
    if (!isset($_COOKIE[$sess])) return; // çerez yok → anonim; cache için dokunma
    session_name($sess);
    if (function_exists('session_cache_limiter')) session_cache_limiter('');
    @session_start();
  }

  /** Zorunlu olarak session başlat (login/admin/form işlemleri için) */
  private static function startSessionForce(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name(self::sessionName());
    if (function_exists('session_cache_limiter')) session_cache_limiter('');
    @session_start();
  }

  /**
   * CSRF token üretir.
   * $forceStart=true ise gerekirse session başlatır (admin/form sayfalarında kullanın).
   * $forceStart=false ise yalnız çerez varsa açar; anonim GET’te session oluşturmaz.
   */
  public static function token(?string $key = null, bool $forceStart = false): string {
    if ($forceStart) self::startSessionForce(); else self::ensureSessionIfCookie();
    if (session_status() !== PHP_SESSION_ACTIVE) {
      // Anonim GET’te çağrıldıysa token üretmeyelim (cache bozulmasın)
      return '';
    }
    $key = self::keyFromConfig($key);
    $_SESSION['_csrf_key'] = $key;
    if (empty($_SESSION['_csrf'])) {
      $_SESSION['_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['_csrf'];
  }

  /**
   * CSRF doğrulama.
   * Session çerezi yoksa veya oturum kapalıysa false döner.
   */
  public static function check(string $token, ?string $key = null): bool {
    self::ensureSessionIfCookie();
    if (session_status() !== PHP_SESSION_ACTIVE) return false;
    $key = self::keyFromConfig($key);
    return isset($_SESSION['_csrf_key'], $_SESSION['_csrf'])
      && hash_equals($_SESSION['_csrf_key'], $key)
      && hash_equals($_SESSION['_csrf'], (string)$token);
  }

  /** Gerekirse token döndürüp yeniler. */
  public static function rotate(?string $key = null, bool $forceStart = false): string {
    if ($forceStart) self::startSessionForce(); else self::ensureSessionIfCookie();
    if (session_status() !== PHP_SESSION_ACTIVE) return '';
    $key = self::keyFromConfig($key);
    $_SESSION['_csrf_key'] = $key;
    $_SESSION['_csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['_csrf'];
  }
}
