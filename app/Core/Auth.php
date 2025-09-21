<?php

namespace App\Core;

use App\Core\DB;

class Auth
{
  /** Proje config’inden session adını al */
  private static function sessionName(): string
  {
    $cfg = require dirname(__DIR__, 2) . "/config/config.php";
    return $cfg['security']['session_name'] ?? 'E2CESESSID';
  }

  /** Oturumu sadece ÇEREZ varsa başlat (anonim GET’te yeni session açma!) */
  private static function ensureSessionIfCookie(): void
  {
    $sess = self::sessionName();
    if (session_status() === PHP_SESSION_ACTIVE) return;
    if (!isset($_COOKIE[$sess])) return;                 // çerez yoksa sessizce dön
    session_name($sess);
    if (function_exists('session_cache_limiter')) session_cache_limiter('');
    @session_start();
  }

  /** Giriş yaparken (başarılıysa) oturumu başlat */
  private static function startSessionForLogin(): void
  {
    $sess = self::sessionName();
    if (session_status() !== PHP_SESSION_ACTIVE) {
      session_name($sess);
      if (function_exists('session_cache_limiter')) session_cache_limiter('');
      @session_start();
    }
  }

  public static function check(): bool
  {
    if (session_status() !== PHP_SESSION_ACTIVE) {
      // Session cookie adını config’ten uygula (varsa)
      $cfgFile = dirname(__DIR__, 2) . '/config/config.php';
      if (is_file($cfgFile)) {
        $cfg = require $cfgFile;
        if (!empty($cfg['security']['session_name'])) {
          session_name($cfg['security']['session_name']);
        }
      }
      session_start();
    }
    $uid = $_SESSION['uid'] ?? null;
    if (!$uid) return false;

    // DB'de aktif kullanıcı var mı? (rolü de çekelim)
    try {
      $cfg = require dirname(__DIR__, 2) . "/config/config.php";
      $pdo = DB::conn($cfg['db']);
      $st = $pdo->prepare("SELECT u.id, r.name AS role
                           FROM users u
                           JOIN roles r ON r.id=u.role_id
                           WHERE u.id=? AND u.is_active=1
                           LIMIT 1");
      $st->execute([(int)$uid]);
      $row = $st->fetch();
      if (!$row) {
        // Artık geçerli değil -> oturumu kapat
        self::logout();
        return false;
      }
      // Rol senkron değilse güncelle
      if (empty($_SESSION['role']) || $_SESSION['role'] !== $row['role']) {
        $_SESSION['role'] = $row['role'];
      }
      return true;
    } catch (\Throwable $e) {
      // DB erişimi başarısızsa güvenli davran: girişsiz say
      self::logout();
      return false;
    }
  }


  public static function id(): ?int
  {
    self::ensureSessionIfCookie();
    return isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : null;
  }

  public static function role(): ?string
  {
    self::ensureSessionIfCookie();
    return $_SESSION['role'] ?? null;
  }

  public static function requireRole(array $roles): void
  {
    if (!self::check() || !in_array(self::role(), $roles, true)) {
      http_response_code(403);
      echo "Forbidden";
      exit;
    }
  }

  public static function attempt(string $email, string $password): bool
  {
    $cfg = require dirname(__DIR__, 2) . "/config/config.php";
    $pdo = DB::conn($cfg['db']);
    $stmt = $pdo->prepare("SELECT u.id, u.password_hash, r.name AS role FROM users u JOIN roles r ON r.id=u.role_id WHERE u.email=? AND u.is_active=1 LIMIT 1");
    $stmt->execute([$email]);
    $row = $stmt->fetch();
    if ($row && password_verify($password, $row['password_hash'])) {
      self::startSessionForLogin();
      $_SESSION['uid']  = (int)$row['id'];
      $_SESSION['role'] = (string)$row['role'];
      return true;
    }
    return false;
  }

  public static function logout(): void
  {
    $sess = self::sessionName();
    // sadece aktifse kapat
    if (session_status() === PHP_SESSION_ACTIVE) {
      $_SESSION = [];
      @session_destroy();
    }
    // çerezi de sıfırla
    @setcookie($sess, '', time() - 3600, '/');
  }
}
