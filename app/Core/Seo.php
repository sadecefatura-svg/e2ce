<?php

namespace App\Core;

final class Seo
{
    private static array $localeMap = [
        'en' => 'en_US',
        'es' => 'es_ES',
        'ar' => 'ar',
        'tr' => 'tr_TR',
        'fr' => 'fr_FR',
        'de' => 'de_DE',
        'pt' => 'pt_PT',
        'ru' => 'ru_RU',
        'hi' => 'hi_IN',
        'zh' => 'zh_CN',
        'ja' => 'ja_JP'
    ];

    public static function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
    public static function locale(string $lang): string
    {
        return self::$localeMap[$lang] ?? $lang;
    }
    /**
     * Minimal Article / NewsArticle JSON-LD üretir.
     * Beklenen alanlar:
     *  - title (string)
     *  - canonical (string)   // tam URL önerilir ama zorunlu değil
     *  - lang (string)        // ör. "en"
     *  - image (string[]|string) (opsiyonel)
     *  - section (string|null)
     *  - tags (string|null|array) // CSV de olur
     *  - published_at (Y-m-d H:i:s|null)
     *  - updated_at   (Y-m-d H:i:s|null)
     *  - publisher (array|null)   // ['name'=>'...', 'logo'=>['url'=>'...','width'=>512,'height'=>512]]
     */
    public static function articleJsonLd(array $o): array
    {
        $title = (string)($o['title'] ?? '');
        $lang  = (string)($o['lang']  ?? 'en');
        $canon = (string)($o['canonical'] ?? ($o['url'] ?? ''));
        $pub   = $o['published_at'] ?? null;
        $upd   = $o['updated_at']   ?? $pub;

        // son 30 günde yayımlanmışsa NewsArticle, değilse Article
        $isNews = false;
        if (!empty($pub)) {
            $ts = strtotime((string)$pub);
            if ($ts !== false && (time() - $ts) < 30 * 24 * 60 * 60) $isNews = true;
        }

        // image normalizasyonu
        $imgs = [];
        if (!empty($o['image'])) {
            if (is_array($o['image'])) {
                $imgs = array_values(array_filter($o['image']));
            } elseif (is_string($o['image'])) {
                $imgs = [$o['image']];
            }
        }

        // tags normalizasyonu
        $tagsCsvOrArr = $o['tags'] ?? null;
        $tags = is_array($tagsCsvOrArr)
            ? array_values(array_filter(array_map('trim', $tagsCsvOrArr)))
            : self::normalizeTags(is_string($tagsCsvOrArr) ? $tagsCsvOrArr : null);

        $data = [
            '@context' => 'https://schema.org',
            '@type'    => $isNews ? 'NewsArticle' : 'Article',
            'headline' => mb_substr($title, 0, 110, 'UTF-8'),
            'inLanguage' => $lang,
        ];

        if ($canon !== '')            $data['mainEntityOfPage'] = $canon;
        if (!empty($pub))             $data['datePublished']    = date('c', strtotime((string)$pub));
        if (!empty($upd))             $data['dateModified']     = date('c', strtotime((string)$upd));
        if (!empty($imgs))            $data['image']            = $imgs;
        if (!empty($o['section']))    $data['articleSection']   = (string)$o['section'];
        if (!empty($tags))            $data['keywords']         = implode(', ', $tags);

        // publisher (opsiyonel)
        if (!empty($o['publisher']) && is_array($o['publisher'])) {
            $pubArr = ['@type' => 'Organization', 'name' => (string)($o['publisher']['name'] ?? '')];
            if (!empty($o['publisher']['logo']) && is_array($o['publisher']['logo'])) {
                $logo = $o['publisher']['logo'];
                $pubArr['logo'] = [
                    '@type'  => 'ImageObject',
                    'url'    => (string)($logo['url'] ?? ''),
                    'width'  => isset($logo['width'])  ? (int)$logo['width']  : 512,
                    'height' => isset($logo['height']) ? (int)$logo['height'] : 512,
                ];
            }
            if (!empty($pubArr['name'])) $data['publisher'] = $pubArr;
        }

        return $data;
    }
    public static function firstImageFromHtml(string $html, string $baseUrl): ?string
    {
        if (!preg_match('/<img[^>]+src=["\']?([^"\'>\s]+)["\']?/i', $html, $m)) return null;
        $src = $m[1] ?? '';
        if ($src === '') return null;
        if (preg_match('#^https?://#i', $src)) return $src;
        if (strpos($src, '//') === 0) return 'https:' . $src;
        $base = rtrim($baseUrl, '/');
        if ($src[0] === '/') return $base . $src;
        return $base . '/' . ltrim($src, '/');
    }

    public static function normalizeTags(?string $csv): array
    {
        if (!$csv) return [];
        $parts = array_filter(array_map('trim', explode(',', $csv)), fn($v) => $v !== '');
        $uniq = [];
        foreach ($parts as $p) $uniq[strtolower($p)] = $p;
        return array_values($uniq);
    }

    /**
     * $o: title, description, canonical, url, image, lang, alternates, robots, type, jsonld
     *     + site_name, twitter_site, twitter_creator, amphtml(optional), mobile_alternate(optional)
     */
    public static function tags(array $o): string
    {
        $t = [];
        $title = $o['title'] ?? '';
        $desc  = $o['description'] ?? '';
        $canon = $o['canonical'] ?? $o['url'] ?? '';
        $url   = $o['url'] ?? $canon;
        $img   = $o['image'] ?? '';
        $lang  = $o['lang'] ?? 'en';
        $type  = $o['type'] ?? 'website';
        $robots = $o['robots'] ?? 'index,follow';
        $alts  = $o['alternates'] ?? [];
        $siteName = $o['site_name'] ?? null;
        $twSite   = $o['twitter_site'] ?? null;
        $twCreator = $o['twitter_creator'] ?? null;
        $amphtml  = $o['amphtml'] ?? null;
        $mAlt     = $o['mobile_alternate'] ?? null;

        if ($title !== '') {
            $t[] = '<title>' . self::esc($title) . '</title>';
            $t[] = '<meta property="og:title" content="' . self::esc($title) . '">';
            $t[] = '<meta name="twitter:title" content="' . self::esc($title) . '">';
        }
        if ($desc !== '') {
            $t[] = '<meta name="description" content="' . self::esc($desc) . '">';
            $t[] = '<meta property="og:description" content="' . self::esc($desc) . '">';
            $t[] = '<meta name="twitter:description" content="' . self::esc($desc) . '">';
        }
        if ($canon !== '') {
            $t[] = '<link rel="canonical" href="' . self::esc($canon) . '">';
        }
        if ($amphtml) {
            $t[] = '<link rel="amphtml" href="' . self::esc($amphtml) . '">';
        }
        if ($mAlt) {
            $t[] = '<link rel="alternate" media="only screen and (max-width: 640px)" href="' . self::esc($mAlt) . '">';
        }
        if (!empty($alts)) {
            foreach ($alts as $code => $href) {
                $t[] = '<link rel="alternate" hreflang="' . self::esc($code) . '" href="' . self::esc($href) . '">';
            }
            if (!isset($alts['x-default'])) {
                $any = $alts['en'] ?? reset($alts);
                if ($any) $t[] = '<link rel="alternate" hreflang="x-default" href="' . self::esc($any) . '">';
            }
        }

        $t[] = '<meta name="robots" content="' . self::esc($robots) . '">';
        $t[] = '<meta property="og:type" content="' . self::esc($type) . '">';
        if ($url) {
            $t[] = '<meta property="og:url" content="' . self::esc($url) . '">';
            $t[] = '<meta name="twitter:url" content="' . self::esc($url) . '">';
        }
        $t[] = '<meta property="og:locale" content="' . self::esc(self::locale($lang)) . '">';
        if ($siteName) $t[] = '<meta property="og:site_name" content="' . self::esc($siteName) . '">';
        if ($img) {
            $t[] = '<meta property="og:image" content="' . self::esc($img) . '">';
            $t[] = '<meta name="twitter:card" content="summary_large_image">';
            $t[] = '<meta name="twitter:image" content="' . self::esc($img) . '">';
        } else {
            $t[] = '<meta name="twitter:card" content="summary">';
        }
        if ($twSite)    $t[] = '<meta name="twitter:site" content="' . self::esc($twSite) . '">';
        if ($twCreator) $t[] = '<meta name="twitter:creator" content="' . self::esc($twCreator) . '">';

        if (!empty($o['jsonld'])) {
            $t[] = '<script type="application/ld+json">' . json_encode($o['jsonld'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
        }
        return implode("\n", $t) . "\n";
    }

    public static function title(string $main, string $site): string
    {
        $main = trim($main);
        $site = trim($site);
        if ($site === '') return $main;
        if ($main === '') return $site;
        return $main . ' | ' . $site;
    }

    public static function summarize(?string $text, int $limit = 160): string
    {
        $text = trim((string)$text);
        $text = strip_tags($text);
        $text = preg_replace('/\s+/', ' ', $text);
        if (mb_strlen($text, 'UTF-8') > $limit) $text = mb_substr($text, 0, $limit - 1, 'UTF-8') . '…';
        return $text;
    }
}
