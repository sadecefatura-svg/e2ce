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

$navLinks = [
  ['label' => 'Home',   'href' => '/' . $lang . '/'],
  ['label' => 'Trends', 'href' => '/' . $lang . '/section/trends'],
  ['label' => 'Tech',   'href' => '/' . $lang . '/section/tech'],
  ['label' => 'World',  'href' => '/' . $lang . '/section/world'],
];
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
      <button class="btn e2-nav-toggle d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#primaryNav" aria-controls="primaryNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="e2-nav-toggle-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Menu</span>
      </button>

      <nav class="flex-grow-1" aria-label="Primary navigation">
        <div class="collapse d-md-flex" id="primaryNav">
          <div class="e2-nav-menu">
            <ul class="e2-nav-links list-unstyled mb-0">
              <?php foreach ($navLinks as $link): ?>
                <li>
                  <a href="<?= htmlspecialchars($link['href']) ?>" class="muted"><?= htmlspecialchars($link['label']) ?></a>
                </li>
              <?php endforeach; ?>
            </ul>

            <div class="e2-nav-controls">
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
            </div>
          </div>
        </div>
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