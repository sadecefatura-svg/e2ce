<?php

use App\Core\I18n;
use App\Core\Seo;

$lang = $lang ?? I18n::get();
$cfg  = require __DIR__ . '/../../config/config.php';
$site = $cfg['app']['name'] ?? 'E2CE';
$base = rtrim($cfg['app']['url'] ?? '', '/');

$post = $post ?? [];
$titleTxt = (string)($post['title'] ?? 'Post');
$descTxt  = Seo::summarize($post['excerpt'] ?? ($post['body'] ?? ''), 160);
$canonical = $base . '/' . $lang . '/post?slug=' . rawurlencode((string)($post['slug'] ?? ''));
$ogImage   = Seo::firstImageFromHtml((string)($post['body'] ?? ''), $base) ?? ($base . '/assets/og-default.jpg');

/** hreflang alternates (translation_key varsa doldur) */
$hreflangs = [];
if (!empty($post['translation_key'])) {
  $pdo = \App\Core\DB::conn($cfg['db']);
  $stmt = $pdo->prepare("SELECT language_code, slug FROM posts WHERE translation_key=? AND status='published'");
  $stmt->execute([$post['translation_key']]);
  foreach ($stmt->fetchAll() ?: [] as $r) {
    $hreflangs[$r['language_code']] = $base . '/' . $r['language_code'] . '/post?slug=' . rawurlencode($r['slug']);
  }
}

/** layout için meta değişkenlerini hazırla */
$metaTitle       = Seo::title($titleTxt, $site);
$metaDescription = $descTxt;
$ogType          = 'article';
$canonical       = $canonical;
$hreflangs       = $hreflangs;
$ogImage         = $ogImage;
$pageNo          = null;

/** JSON-LD */
$jsonLd = Seo::articleJsonLd([
  'title'        => $titleTxt,
  'canonical'    => $canonical,
  'lang'         => $lang,
  'image'        => $ogImage ? [$ogImage] : [],
  'section'      => $post['section'] ?? null,
  'tags'         => $post['tags'] ?? null,
  'published_at' => $post['published_at'] ?? ($post['created_at'] ?? null),
  'updated_at'   => $post['updated_at']   ?? ($post['created_at'] ?? null),
  'publisher'    => [
    'name' => $cfg['publisher']['name'] ?? ($cfg['app']['site_name'] ?? $site),
    'logo' => [
      'url'    => $cfg['publisher']['logo_url'] ?? ($base . '/assets/logo-512.png'),
      'width'  => (int)($cfg['publisher']['logo_width']  ?? 512),
      'height' => (int)($cfg['publisher']['logo_height'] ?? 512),
    ],
  ],
]);

/** içerik */
$bodyHtml = \App\Core\Sanitizer::cleanHtml((string)($post['body'] ?? ''));
?>
<article class="e2-article">
  <header class="mb-3">
    <h1 class="display-6"><?= htmlspecialchars($titleTxt) ?></h1>
    <div class="text-muted small d-flex flex-wrap gap-3">
      <?php if (!empty($post['section'])): ?>
        <span><?= htmlspecialchars($post['section']) ?></span>
      <?php endif; ?>
      <?php if (!empty($post['published_at'])): ?>
        <time datetime="<?= htmlspecialchars(date('c', strtotime($post['published_at']))) ?>">
          <?= htmlspecialchars(date('M d, Y', strtotime($post['published_at']))) ?>
        </time>
      <?php endif; ?>
      <?php if (!empty($post['tags'])):
        $tags = array_filter(array_map('trim', explode(',', (string)$post['tags']))); ?>
        <span>
          <?php foreach ($tags as $t): ?>
            <a class="badge rounded-pill text-bg-light text-decoration-none"
              href="/<?= htmlspecialchars($lang) ?>/tag/<?= rawurlencode($t) ?>">#<?= htmlspecialchars($t) ?></a>
          <?php endforeach; ?>
        </span>
      <?php endif; ?>
    </div>
    <?php if (!empty($post['excerpt'])): ?>
      <p class="lead mt-2"><?= htmlspecialchars($post['excerpt']) ?></p>
    <?php endif; ?>
  </header>

  <div class="e2-article-body">
    <?= $bodyHtml ?>
  </div>

  <nav class="mt-4">
    <a href="/<?= htmlspecialchars($lang) ?>/" class="btn btn-outline-secondary">&larr; Home</a>
  </nav>
</article>