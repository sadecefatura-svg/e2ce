<?php

namespace App\Core;

final class Invalidation
{
    /**
     * Bir yazı değiştiğinde etkilenmesi muhtemel yolları hesaplayıp
     * sadece o URL'lerin full-page cache'ini temizler.
     *
     * @param string      $slug     Yazının slug'ı
     * @param string|null $lang     Dil kodu (ör. "en"); null ise default kullan
     * @param string[]    $tags     Etiketler (["ai","news"] gibi)
     * @param string|null $section  Bölüm (ör. "tech")
     */
    public static function purgeForPost(string $slug, ?string $lang = null, array $tags = [], ?string $section = null): void
    {
        $cfg = require dirname(__DIR__, 2) . '/config/config.php';
        $lang = $lang ?: (\App\Core\I18n::get() ?: ($cfg['app']['default_lang'] ?? 'en'));

        // Etkilenecek yollar
        $paths = [];

        // Yazının kendisi
        $paths[] = '/' . $lang . '/post?slug=' . rawurlencode($slug);

        // Ana sayfa + RSS
        $paths[] = '/' . $lang . '/';
        $paths[] = '/' . $lang . '/rss.xml';

        // Section listeleri + RSS
        if ($section) {
            $paths[] = '/' . $lang . '/section/' . rawurlencode($section);
            $paths[] = '/' . $lang . '/section/' . rawurlencode($section) . '/rss.xml';
        }

        // Tag listeleri + RSS
        foreach ($tags as $t) {
            $t = trim((string)$t);
            if ($t === '') continue;
            $paths[] = '/' . $lang . '/tag/' . rawurlencode($t);
            $paths[] = '/' . $lang . '/tag/' . rawurlencode($t) . '/rss.xml';
        }

        // Sitemap (cache'leniyorsa)
        $paths[] = '/sitemap.xml';

        \App\Core\HttpCache::purgeByPaths($cfg, $paths);
    }

    /**
     * Tek bir taksonomi (tag/section) için purge (liste + RSS).
     * @param string $taxonomy "tag" | "section"
     * @param string $value    ör. "ai"
     * @param string|null $lang
     */
    public static function purgeForTaxonomy(string $taxonomy, string $value, ?string $lang = null): void
    {
        $cfg = require dirname(__DIR__, 2) . '/config/config.php';
        $lang = $lang ?: (\App\Core\I18n::get() ?: ($cfg['app']['default_lang'] ?? 'en'));
        $taxonomy = $taxonomy === 'section' ? 'section' : 'tag';
        $value = trim($value);
        if ($value === '') return;

        $base = '/' . $lang . '/' . $taxonomy . '/' . rawurlencode($value);
        $paths = [$base, $base . '/rss.xml'];
        \App\Core\HttpCache::purgeByPaths($cfg, $paths);
    }

    /**
     * Geniş kapsamlı değişikliklerde (tema ayarı, menü, şablon) hepsini temizle.
     */
    public static function purgeAll(): void
    {
        $cfg = require dirname(__DIR__, 2) . '/config/config.php';
        \App\Core\HttpCache::purgeAll($cfg);
    }
}
