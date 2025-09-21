<?php

use App\Core\I18n;

$langs = I18n::supported();
$lang  = $lang ?? I18n::get();
$rtl   = I18n::isRtl($lang);

// Basit başlık & açıklama
$cfg = require __DIR__ . '/../../config/config.php';
$site = $cfg['app']['name'] ?? 'E2CE';
$pageTitle = $site . ' — ' . ($langs[$lang] ?? strtoupper($lang));
$pageDesc  = 'Trending topics, daily insights and curated articles in ' . $lang . '.';

// SEO tags
$title = $pageTitle;
$desc  = $pageDesc;
$robots = 'index,follow';
// canonical otomatik partial içinde üretilecek, istersen elle de tanımlayabilirsin:
// $canonical = rtrim($cfg['app']['url'],'/').'/'.$lang.'/';
?>
<?php include __DIR__ . '/partials/seo.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h2 class="m-0">E2CE</h2>
  <form method="get" action="" class="d-flex align-items-center gap-2">
    <label class="form-label m-0 me-2">Language</label>
    <select class="form-select form-select-sm" onchange="location.href='/' + this.value + '/'">
      <?php foreach ($langs as $code => $name): ?>
        <option value="<?= htmlspecialchars($code) ?>" <?= $code === $lang ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<?php if (!empty($rows)): ?>
  <ul class="list-group">
    <?php foreach ($rows as $r): ?>
      <li class="list-group-item d-flex justify-content-between align-items-center <?= $rtl ? 'text-end' : '' ?>">
        <a href="/<?= htmlspecialchars($lang) ?>/post?slug=<?= htmlspecialchars($r['slug']) ?>" class="text-decoration-none">
          <?= htmlspecialchars($r['title']) ?>
        </a>
        <?php if (!empty($r['published_at'])): ?>
          <small class="text-muted"><?= htmlspecialchars(date('Y-m-d', strtotime($r['published_at']))) ?></small>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
<?php else: ?>
  <div class="alert alert-info">No posts yet for this language.</div>
<?php endif; ?>