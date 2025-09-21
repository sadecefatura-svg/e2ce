<?php

/** @var string $content  (View::render'dan gelen içerik) */

use App\Core\I18n;

$lang     = $lang ?? I18n::get();
$dir      = I18n::isRtl($lang) ? 'rtl' : 'ltr';
$siteName = $siteName ?? 'E2CE';

/** View’ler title/desc/robots/canonical gibi değişkenleri set etmemişse
 *  partials/seo.php için makul varsayılanlar hazırlıyoruz.
 */
$title = $title ?? ($metaTitle ?? $siteName);
$desc  = $desc  ?? ($metaDescription ?? '');
$robots = $robots ?? 'index,follow';

// AMP veya mobil alternatif (opsiyonel; post view set edebilir)
$ampUrl    = $ampUrl    ?? null;
$mobileUrl = $mobileUrl ?? null;
?>
<!doctype html>
<html lang="<?= htmlspecialchars($lang) ?>" dir="<?= htmlspecialchars($dir) ?>">

<head>
  <meta charset="utf-8">
  <meta http-equiv="x-ua-compatible" content="ie=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <?php
  // Tüm SEO / OpenGraph / Twitter / JSON-LD etiketleri burada üretilir:
  // View tarafından $post, $title, $desc, $lang, $canonical, $ampUrl, $mobileUrl vs set edildiyse
  // partials/seo.php bunları kullanır; yoksa kendi içinde varsayılanları hesaplar.
  include __DIR__ . '/partials/seo.php';
  ?>

  <!-- Favicons (isteğe bağlı) -->
  <link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
  <link rel="icon" type="image/png" href="/assets/favicon.png">

  <!-- Bootstrap 5 (CDN) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">

  <!-- Tema CSS -->
  <link rel="stylesheet" href="/assets/css/app.css?v=1">
</head>

<body class="<?= !empty($bodyClass) ? htmlspecialchars($bodyClass) : '' ?>">
  <!-- Üst Navigasyon -->
  <header class="e2-nav">
    <div class="e2-container inner">
      <a class="e2-brand" href="/<?= htmlspecialchars($lang) ?>/">
        <img src="/assets/logo.png" alt="<?= htmlspecialchars($siteName) ?>" width="96">
      </a>

      <nav class="d-none d-md-flex align-items-center gap-3">
        <a href="/<?= htmlspecialchars($lang) ?>/" class="muted">Home</a>
        <a href="/<?= htmlspecialchars($lang) ?>/section/trends" class="muted">Trends</a>
        <a href="/<?= htmlspecialchars($lang) ?>/section/tech" class="muted">Tech</a>
        <a href="/<?= htmlspecialchars($lang) ?>/section/world" class="muted">World</a>

        <!-- Dil menüsü -->
        <div class="dropdown">
          <a class="e2-btn ghost dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <?= strtoupper(htmlspecialchars($lang)) ?>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <?php
            $supported = array_keys(I18n::supported());
            $current = $_SERVER['REQUEST_URI'] ?? '/';
            foreach ($supported as $code):
              $next = preg_replace('~^/[a-z-]+/~', '/' . $code . '/', $current);
              if ($next === null) $next = '/' . $code . '/';
              $langUrl = '/lang/' . $code . '?next=' . rawurlencode($next);
            ?>
              <li><a class="dropdown-item" href="<?= htmlspecialchars($langUrl) ?>"><?= strtoupper(htmlspecialchars($code)) ?></a></li>
            <?php endforeach; ?>
          </ul>
        </div>

        <button id="theme-toggle" class="e2-btn ghost" type="button" aria-label="Toggle theme">Theme</button>
      </nav>
    </div>
  </header>

  <!-- Ana içerik -->
  <main class="e2-container section">
    <?= $content ?? '' ?>
  </main>

  <!-- Alt bilgi -->
  <footer class="e2-footer">
    <div class="e2-container d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
      <div class="small">
        © <?= date('Y') ?> <?= htmlspecialchars($siteName) ?> — All rights reserved.
      </div>
      <div class="small d-flex gap-3">
        <a class="muted" href="/<?= htmlspecialchars($lang) ?>/about">About</a>
        <a class="muted" href="/<?= htmlspecialchars($lang) ?>/contact">Contact</a>
        <a class="muted" href="/sitemap.xml">Sitemap</a>
        <a class="muted" href="/feed-<?= htmlspecialchars($lang) ?>.xml">RSS</a>
      </div>
    </div>
  </footer>

  <!-- Minimal JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
  <script defer src="/assets/js/theme.js"></script>
</body>

</html>