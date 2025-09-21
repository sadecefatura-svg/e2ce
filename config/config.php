<?php

declare(strict_types=1);

use App\Core\Env;

$root = dirname(__DIR__);
Env::load($root . '/.env'); // silently loads if exists

return [
  'app' => [
    'env'   => Env::get('APP_ENV', 'dev'),
    'debug' => (bool) Env::get('APP_DEBUG', '0'),
    'url'   => rtrim(Env::get('APP_URL', 'http://localhost'), '/'),
    'default_lang' => 'en',
    'supported_langs' => ['en', 'es', 'ar', 'tr', 'fr', 'de', 'pt', 'ru', 'hi', 'zh', 'ja'],
  ],
  'publisher' => [
    // Kare, en az 112x112 olmalı; tam URL
    'logo_url' => 'https://e2ce.com/assets/logo-512.png',
    'logo_width' => 512,
    'logo_height' => 512,
    'name' => 'E2CE',
    'twitter_site'    => '@e2ce',   // kurum hesabı (og:site_name zaten app.name’den)
    'twitter_creator' => '@e2ce',   // varsayılan yazar
  ],
  'db' => [
    'host'    => Env::get('DB_HOST', '127.0.0.1'),
    'name'    => Env::get('DB_NAME', 'e2ce'),
    'user'    => Env::get('DB_USER', 'root'),
    'pass'    => Env::get('DB_PASS', ''),
    'charset' => Env::get('DB_CHARSET', 'utf8mb4'),
  ],
  'mail' => [
    // Gönderen
    'from'      => 'ismail@gencan.com.tr',
    'from_name' => 'E2CE',

    // SMTP DSN örnekleri:
    // - STARTTLS (587):  smtp://USER:PASS@smtp.yourhost.com:587
    // - SMTPS   (465):  smtps://USER:PASS@smtp.yourhost.com:465
    // - Microsoft 365:   smtp://USER:PASS@smtp.office365.com:587
    // - Gmail (uygulama şifresi): smtp://USER:APP_PASS@smtp.gmail.com:587
    'dsn' => 'smtps://ismail@gencan.com.tr:' . rawurlencode('GRIP##155') . '@smtp.yandex.com:465',

    // İsteğe bağlı
    'reply_to'  => null,           // 'support@e2ce.com'
    'timeout'   => 15,             // saniye
    'debug_log' => true            // gerçek gönderim başarısızsa log’a yaz
  ],
  'htmlpurifier' => [
    'allowed_iframe_hosts' => ['www.youtube.com', 'player.vimeo.com', 'www.dailymotion.com', 'open.spotify.com'],
  ],

  'images' => [
    'driver' => 'auto',    // 'auto' | 'gd' | 'imagick'
    'root'   => dirname(__DIR__) . '/public/uploads', // fiziksel path
    'base'   => '/uploads',                          // URL base
    'variants' => [
      'og'    => ['w' => 1200, 'h' => 630,  'mode' => 'cover', 'quality' => 85, 'format' => 'auto'], // jpg/webp auto
      'thumb' => ['w' => 600,  'h' => 400,  'mode' => 'cover', 'quality' => 82, 'format' => 'auto'],
      'amp'   => ['w' => 1200, 'h' => null, 'mode' => 'fit',   'quality' => 82, 'format' => 'auto'],
    ],
    // webp desteği yoksa otomatik jpg'e düşer
  ],
  'security' => [
    'session_name' => Env::get('SESSION_NAME', 'E2CESESSID'),
    'csrf_key'     => Env::get('CSRF_KEY', 'dev-key'),
    // rate limit
    'login_max_per_minute' => (int) Env::get('LOGIN_MAX_PER_MINUTE', '8'), // dk’da en fazla 8 deneme
    'login_burst'          => (int) Env::get('LOGIN_BURST', '8'),
    'lockout_seconds'      => (int) Env::get('LOGIN_LOCK', '900'), // 15 dk
  ],
  'cache' => [
    'full_page' => [
      'enabled' => true,
      'ttl'     => 600,
      'dir'     => dirname(__DIR__) . '/storage/cache/html',
      'session_cookie' => Env::get('SESSION_NAME', 'E2CESESSID'),
      'exclude' => [
        '#^/admin#',
        '#^/login#',
        '#^/logout#',
        '#^/api/#',
        '#^/uploads/#',
        '#^/lang($|/)#',
      ],
      // ↓ slug eklendi
      'vary_query' => ['page', 'lang', 'q', 'tag', 'section', 'slug'],
      'vary_encoding' => false
    ],
  ],

];
