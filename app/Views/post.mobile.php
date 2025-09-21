<?php

use App\Core\I18n;
use App\Core\Seo;

$lang = $lang ?? I18n::get();
$cfg  = require __DIR__ . '/../../config/config.php';
$site = $cfg['app']['name'] ?? 'E2CE';
$titleTxt = (string)($post['title'] ?? 'Post');
$bodyHtml = \App\Core\Sanitizer::cleanHtml($post['body'] ?? '');
?>
<!doctype html>
<html lang="<?= htmlspecialchars($lang) ?>">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(Seo::title($titleTxt, $site)) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        body {
            padding: 12px;
            background: #fff
        }

        article {
            max-width: 700px;
            margin: 0 auto
        }

        img {
            max-width: 100%;
            height: auto
        }
    </style>
</head>

<body>
    <article>
        <h1 class="h3"><?= htmlspecialchars($titleTxt) ?></h1>
        <?php if (!empty($post['excerpt'])): ?>
            <p class="text-muted"><?= nl2br(htmlspecialchars($post['excerpt'])) ?></p>
        <?php endif; ?>
        <div><?= $bodyHtml ?></div>
    </article>
</body>

</html>