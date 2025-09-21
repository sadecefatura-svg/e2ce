<?php
use App\Core\I18n;
$lang = $lang ?? I18n::get();
$rtl  = I18n::isRtl($lang);
$cfg = require __DIR__ . '/../../config/config.php';
$site = $cfg['app']['name'] ?? 'E2CE';
$title = ($title ?? 'Listing').' | '.$site;
$desc  = 'Latest posts';
include __DIR__ . '/partials/seo.php';
?>
<h1 class="mb-3"><?= htmlspecialchars($heading ?? ($title ?? 'Listing')) ?></h1>
<?php if (!empty($rows)): ?>
  <ul class="list-group">
    <?php foreach ($rows as $r): ?>
      <li class="list-group-item d-flex justify-content-between align-items-center <?= $rtl?'text-end':'' ?>">
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
  <div class="alert alert-info">No posts.</div>
<?php endif; ?>
