<?php

declare(strict_types=1);
$__reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
if (str_starts_with($__reqPath, '/uploads/')) {
  error_log('[STATIC DEBUG] fell into PHP: ' . $__reqPath);
  $__full = __DIR__ . $__reqPath;
  if (is_file($__full)) {
    $ext = strtolower(pathinfo($__full, PATHINFO_EXTENSION));
    $mime = [
      'png' => 'image/png',
      'jpg' => 'image/jpeg',
      'jpeg' => 'image/jpeg',
      'gif' => 'image/gif',
      'webp' => 'image/webp',
      'svg' => 'image/svg+xml'
    ][$ext] ?? 'application/octet-stream';
    header('Content-Type: ' . $mime);
    readfile($__full);
    exit;
  } else {
    error_log('[STATIC DEBUG] file not found on disk: ' . $__full);
  }
}
// ===== Autoload =====
$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($vendorAutoload)) {
  require $vendorAutoload;
} else {
  // Tiny PSR-4 autoloader for App\*
  spl_autoload_register(function ($class) {
    if (str_starts_with($class, 'App\\')) {
      $path = __DIR__ . '/../' . str_replace(['App\\', '\\'], ['app/', '/'], $class) . '.php';
      if (is_file($path)) require $path;
    }
  });
}

use App\Core\I18n;
use App\Core\HttpCache;

// ===== Config + Session =====
$cfg = require __DIR__ . '/../config/config.php';

// ===== Session: sadece gerektiğinde aç =====
// admin/login/logout veya POST/PUT/PATCH/DELETE isteklerde session açalım.
// GET ile gelen anonim sayfalar için session **açmayacağız**.
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri    = $_SERVER['REQUEST_URI'] ?? '/';
$needsSession = (
  strtoupper($method) !== 'GET'
  || preg_match('#^/(admin|login|logout|api)(/|$)#i', parse_url($uri, PHP_URL_PATH) ?? '/')
);

// PHP’nin “nocache” başlıklarını engelle
if ($needsSession) {
  if (function_exists('session_cache_limiter')) session_cache_limiter('');
  $sessName = $cfg['security']['session_name'] ?? 'E2CESESSID';
  if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name($sessName);
    session_start();
  }
} else {
  // emin olmak için açık bir oturum varsa kapat
  if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
  }
}

// ===== Router bootstrap =====
require __DIR__ . '/../config/router.php';
require __DIR__ . '/../config/routes.php';

// ===== Dil tespiti / prefix yönlendirmesi =====
$rawPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$parts = array_values(array_filter(explode('/', trim($rawPath, '/'))));
$supported = array_keys(I18n::supported());
$candidate = $parts[0] ?? '';

if ($candidate && in_array($candidate, $supported, true)) {
  I18n::set($candidate);
  array_shift($parts);
  $newPath = '/' . implode('/', $parts);
  if ($newPath === '//') $newPath = '/';
  $_SERVER['REQUEST_URI'] = $newPath === '' ? '/' : $newPath;
} else {
  $lang = $_COOKIE['lang'] ?? null;
  if (!$lang || !I18n::isSupported($lang)) {
    $lang = isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])
      ? I18n::fromAcceptLanguage($_SERVER['HTTP_ACCEPT_LANGUAGE'])
      : I18n::default();
  }
  I18n::set($lang);
  if ($rawPath === '/' || $rawPath === '') {
    header('Location: /' . I18n::get() . '/', true, 302);
    exit;
  }
}

$GLOBALS['__LANG__'] = I18n::get();
$GLOBALS['__DIR__']  = I18n::isRtl(I18n::get()) ? 'rtl' : 'ltr';

// ===== Full Page Cache: SERVE (HIT) =====
if (HttpCache::isEnabled($cfg)) {
  if (HttpCache::tryServe($cfg)) {
    exit; // HIT
  }
  ob_start(); // MISS: capture
}

// ===== Dispatch =====
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// --- Language switch short-circuit: /lang/{code}?next=/... ---
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$pathOnly   = parse_url($requestUri, PHP_URL_PATH) ?? '/';

if (preg_match('~^/lang/([a-z-]+)$~i', $pathOnly, $m)) {
  $code = strtolower($m[1]);

  // Güvenli next
  $next = isset($_GET['next']) ? urldecode((string)$_GET['next']) : ('/' . $code . '/');
  if ($next === '' || $next[0] !== '/' || strpos($next, '://') !== false) {
    $next = '/' . $code . '/';
  }

  // Geçerli dil değilse default’a düş
  if (!\App\Core\I18n::isSupported($code)) {
    $code = \App\Core\I18n::default();
    $next = '/' . $code . '/';
  }

  \App\Core\I18n::set($code);

  // Bu endpoint asla cache’lenmesin
  header('Cache-Control: no-store, no-cache, must-revalidate');
  header('Pragma: no-cache');
  header('Expires: 0');

  header('Location: ' . $next, true, 302);
  exit;
}



$router->dispatch($method, $pathOnly);

// ===== Full Page Cache: STORE (MISS) =====
if (HttpCache::isEnabled($cfg)) {
  $key = HttpCache::keyFromRequest($cfg);
  if ($key) {
    HttpCache::captureAndStore($cfg, $key);
    if (!headers_sent()) header('X-Cache: MISS');
  }
  ob_end_flush();
}
