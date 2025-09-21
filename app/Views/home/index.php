<?php
/**
 * Anasayfa Liste Şablonu
 *
 * Beklenen değişkenler:
 * - array $posts: [
 *      ['slug'=>..., 'title'=>..., 'excerpt'=>..., 'cover_url'=>?, 'section'=>?, 'tags'=>?, 'published_at'=>..., 'language_code'=>...],
 *   ]
 * - int   $page      : şu anki sayfa (1..)
 * - bool  $hasPrev
 * - bool  $hasNext
 * - int|null $totalPages  : opsiyonel (gönderirsen "1/12" gösterimi yaparız)
 * - string $sectionFilter : opsiyonel (örn: "tech")
 * - string $tagFilter     : opsiyonel (örn: "ai")
 *
 * Layout’a aktarılan metadata:
 * - $metaTitle, $metaDescription, $canonical, $hreflangs, $pageNo
 */

use App\Core\I18n;

$lang = I18n::get();
$dir  = I18n::isRtl($lang) ? 'rtl' : 'ltr';

/** URL yardımcıları */
$baseUrl = '/' . $lang . '/';
$makePostUrl = function(array $p) use ($lang) {
  return '/' . $lang . '/post?slug=' . rawurlencode($p['slug']);
};
$makePageUrl = function(int $p) use ($lang) {
  return $p <= 1 ? ('/' . $lang . '/') : ('/' . $lang . '/page/' . $p);
};
$makeSectionUrl = function(string $s) use ($lang) {
  return '/' . $lang . '/section/' . rawurlencode($s);
};
$makeTagUrl = function(string $t) use ($lang) {
  return '/' . $lang . '/tag/' . rawurlencode($t);
};

/** Başlık & açıklama */
$h1 = 'Latest stories';
if (!empty($sectionFilter)) $h1 = 'Section: ' . htmlspecialchars($sectionFilter);
if (!empty($tagFilter))     $h1 = 'Tag: ' . htmlspecialchars($tagFilter);

$metaTitle = $h1 . ($page > 1 ? " – Page {$page}" : '');
$metaDescription = 'Freshly published articles and trend insights.';
$pageNo = $page;

/** Canonical & hreflang (layout otomatik de üretiyor ama burada net veriyoruz) */
$canonical = $makePageUrl($page);
$hreflangs = [];
foreach (array_keys(I18n::supported()) as $code) {
  $href = $page <= 1 ? ("/{$code}/") : ("/{$code}/page/{$page}");
  // filtreli sayfa ise uyarlamak istersen route’ta set edebilirsin
  $hreflangs[$code] = $href;
}

?>
<section class="hero">
  <div class="e2-container">
    <h1 class="title"><?= htmlspecialchars($h1) ?></h1>
    <p class="lead subtitle">Daily trends & insights from around the world.</p>
  </div>
</section>

<section class="e2-container section">
  <?php if (empty($posts)): ?>
    <div class="alert alert-info">No posts yet.</div>
  <?php else: ?>
    <div class="grid cols-3">
      <?php foreach ($posts as $p): ?>
        <?php
          $url = $makePostUrl($p);
          $cover = $p['cover_url'] ?? null;
          $section = $p['section'] ?? null;
          $tags = [];
          if (!empty($p['tags'])) {
            // "ai, news, ml" gibi CSV geliyorsa parçala
            if (is_array($p['tags'])) $tags = $p['tags'];
            else $tags = array_filter(array_map('trim', explode(',', (string)$p['tags'])));
          }
          $pub = !empty($p['published_at']) ? date('M j, Y', strtotime($p['published_at'])) : '';
        ?>
        <article class="e2-card">
          <?php if ($cover): ?>
            <a href="<?= htmlspecialchars($url) ?>" class="d-block">
              <img class="cover" src="<?= htmlspecialchars($cover) ?>" alt="<?= htmlspecialchars($p['title']) ?>">
            </a>
          <?php endif; ?>
          <div class="pad">
            <div class="d-flex align-items-center gap-2 mb-2">
              <?php if ($section): ?>
                <a class="e2-tag" href="<?= htmlspecialchars($makeSectionUrl($section)) ?>"><?= htmlspecialchars($section) ?></a>
              <?php endif; ?>
              <?php if (!empty($tags)): ?>
                <?php foreach (array_slice($tags, 0, 2) as $t): ?>
                  <a class="e2-tag" href="<?= htmlspecialchars($makeTagUrl($t)) ?>"><?= htmlspecialchars($t) ?></a>
                <?php endforeach; ?>
              <?php endif; ?>
              <?php if ($pub): ?>
                <span class="muted ms-auto small"><?= htmlspecialchars($pub) ?></span>
              <?php endif; ?>
            </div>
            <h3 class="h5 mb-2"><a href="<?= htmlspecialchars($url) ?>"><?= htmlspecialchars($p['title']) ?></a></h3>
            <?php if (!empty($p['excerpt'])): ?>
              <p class="muted mb-0"><?= htmlspecialchars($p['excerpt']) ?></p>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php if ($hasPrev || $hasNext): ?>
  <nav class="e2-container section" aria-label="Pagination">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <?php if ($hasPrev): ?>
          <a class="e2-btn outline" href="<?= htmlspecialchars($makePageUrl($page - 1)) ?>">&larr; Newer</a>
        <?php endif; ?>
      </div>
      <div class="muted small">
        <?php if (!empty($totalPages)): ?>
          Page <?= (int)$page ?> / <?= (int)$totalPages ?>
        <?php else: ?>
          Page <?= (int)$page ?>
        <?php endif; ?>
      </div>
      <div>
        <?php if ($hasNext): ?>
          <a class="e2-btn outline" href="<?= htmlspecialchars($makePageUrl($page + 1)) ?>">Older &rarr;</a>
        <?php endif; ?>
      </div>
    </div>
  </nav>
<?php endif; ?>
