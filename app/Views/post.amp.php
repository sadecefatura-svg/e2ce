<?php

use App\Core\I18n;
use App\Core\Seo;

$lang = $lang ?? I18n::get();
$cfg  = require __DIR__ . '/../../config/config.php';
$site = $cfg['app']['name'] ?? 'E2CE';
$titleTxt = (string)($post['title'] ?? 'Post');
$base = rtrim($cfg['app']['url'] ?? '', '/');

$canonical = $base . '/' . $lang . '/post?slug=' . rawurlencode((string)$post['slug']);
$ampTitle  = Seo::title($titleTxt, $site);

// body → amp-img dönüşümü
$body = \App\Core\Sanitizer::cleanHtml($post['body'] ?? '');
$body = preg_replace_callback('#<img[^>]*>#i', function ($m) {
    $tag = $m[0];
    $src = '';
    $w = null;
    $h = null;
    $alt = '';
    $class = '';
    if (preg_match('/src=["\']([^"\']+)["\']/', $tag, $mm)) $src = $mm[1];
    if (preg_match('/width=["\'](\d+)["\']/', $tag, $mm)) $w = (int)$mm[1];
    if (preg_match('/height=["\'](\d+)["\']/', $tag, $mm)) $h = (int)$mm[1];
    if (preg_match('/alt=["\']([^"\']*)["\']/', $tag, $mm)) $alt = $mm[1];
    if (preg_match('/class=["\']([^"\']*)["\']/', $tag, $mm)) $class = $mm[1];
    if (!$src) return '';
    $attrs = 'src="' . htmlspecialchars($src, ENT_QUOTES) . '" alt="' . htmlspecialchars($alt, ENT_QUOTES) . '"';
    if ($w && $h) $attrs .= ' width="' . $w . '" height="' . $h . '"';
    $layout = ($w && $h) ? '' : ' layout="responsive"';
    if ($class) $attrs .= ' class="' . htmlspecialchars($class, ENT_QUOTES) . '"';
    return '<amp-img ' . $attrs . $layout . '></amp-img>';
}, $body);
?>
<!doctype html>
<html amp lang="<?= htmlspecialchars($lang) ?>">

<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($ampTitle) ?></title>
    <link rel="canonical" href="<?= htmlspecialchars($canonical) ?>">
    <meta name="viewport" content="width=device-width,minimum-scale=1,initial-scale=1">
    <style amp-boilerplate>
        body {
            -webkit-animation: -amp-start 8s steps(1, end) 0s 1 normal both;
            -moz-animation: -amp-start 8s steps(1, end) 0s 1 normal both;
            -ms-animation: -amp-start 8s steps(1, end) 0s 1 normal both;
            animation: -amp-start 8s steps(1, end) 0s 1 normal both
        }

        @-webkit-keyframes -amp-start {
            from {
                visibility: hidden
            }

            to {
                visibility: visible
            }
        }

        @-moz-keyframes -amp-start {
            from {
                visibility: hidden
            }

            to {
                visibility: visible
            }
        }

        @-ms-keyframes -amp-start {
            from {
                visibility: hidden
            }

            to {
                visibility: visible
            }
        }

        @-o-keyframes -amp-start {
            from {
                visibility: hidden
            }

            to {
                visibility: visible
            }
        }

        @keyframes -amp-start {
            from {
                visibility: hidden
            }

            to {
                visibility: visible
            }
        }
    </style>
    <noscript>
        <style amp-boilerplate>
            body {
                -webkit-animation: none;
                -moz-animation: none;
                -ms-animation: none;
                animation: none
            }
        </style>
    </noscript>
    <script async src="https://cdn.ampproject.org/v0.js"></script>
    <style amp-custom>
        body {
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            padding: 16px;
            background: #fff;
            color: #111;
            max-width: 800px;
            margin: 0 auto;
        }

        header {
            margin-bottom: 16px
        }

        h1 {
            font-size: 28px;
            margin: 0 0 8px
        }

        .section {
            color: #666;
            margin-bottom: 8px
        }

        .lead {
            font-size: 18px;
            color: #333
        }
    </style>
</head>

<body>
    <header>
        <h1><?= htmlspecialchars($titleTxt) ?></h1>
        <?php if (!empty($post['section'])): ?><div class="section"><?= htmlspecialchars($post['section']) ?></div><?php endif; ?>
        <?php if (!empty($post['excerpt'])): ?><p class="lead"><?= htmlspecialchars($post['excerpt']) ?></p><?php endif; ?>
    </header>
    <main><?= $body ?></main>
</body>

</html>