<?php
namespace App\Core;

use HTMLPurifier;
use HTMLPurifier_Config;

class Sanitizer
{
    private static ?HTMLPurifier $purifier = null;

    private static function buildPurifier(): HTMLPurifier
    {
        $cfgArr = require dirname(__DIR__, 2) . '/config/config.php';

        // 1) Varsayılan config
        $config = HTMLPurifier_Config::createDefault();

        // 2) Genel ayarlar (finalize ÖNCESİ)
        $config->set('Core.Encoding', 'UTF-8');

        // HTML5 yerine, Purifier’ın desteklediği bir doctype seçelim
        // (HTML5 elementlerini aşağıda manuel ekleyeceğiz)
        $config->set('HTML.Doctype', 'XHTML 1.0 Transitional');

        // Cache (Windows/IIS uyumlu dizin)
        $cacheDir = dirname(__DIR__, 2) . '/storage/cache/purifier';
        if (!is_dir($cacheDir)) @mkdir($cacheDir, 0777, true);
        $config->set('Cache.SerializerPath', $cacheDir);

        // Link hedefleri ve rel setleri
        $config->set('Attr.AllowedFrameTargets', ['_blank','_self']);
        // Bu direktif bazı sürümlerde bulunmayabilir; yoksa sessizce yok sayılır.
        $config->set('HTML.TargetBlank', true);
        $config->set('Attr.AllowedRel', ['noopener','noreferrer','nofollow','ugc','external']);

        // 3) Güvenli iframe whitelist
        $allowedHosts = $cfgArr['htmlpurifier']['allowed_iframe_hosts'] ?? [];
        if (!is_array($allowedHosts)) $allowedHosts = [];
        $escaped = array_map(function($h){
            $h = preg_quote($h, '#');
            if (!str_starts_with($h, 'www\.')) {
                $h = '(?:www\.)?' . $h;
            }
            return $h;
        }, $allowedHosts);
        if (!empty($escaped)) {
            $regex = '#^https://(' . implode('|', $escaped) . ')/#i';
            $config->set('HTML.SafeIframe', true);
            $config->set('URI.SafeIframeRegexp', $regex);
        }

        // 4) HTML Definition (yeni HTML5 etiketleri) – finalize ÖNCESİ
        // not: bazı sürümlerde getHTMLDefinition(true) ile değiştirilebilir tanım döner
        $def = $config->getHTMLDefinition(true);

        // figure / figcaption
        if (!isset($def->info['figure'])) {
            // 'figure' block düzeyinde, içinde Flow içeriği + opsiyonel figcaption
            // İçerik modeli: Optional: (figcaption, Flow) | (Flow, figcaption) | Flow
            $def->addElement('figure', 'Block', 'Optional: (figcaption, Flow) | (Flow, figcaption) | Flow', 'Common');
        }
        if (!isset($def->info['figcaption'])) {
            $def->addElement('figcaption', 'Inline', 'Flow', 'Common');
        }

        // semantik HTML5 etiketlerinden birkaçını izinli kılalım (ihtiyaç oldukça genişletirsin)
        foreach ([
            // inline/phrasing
            ['mark', 'Inline', 'Inline', 'Common'],
            // block/sektionsel
            ['section', 'Block', 'Flow', 'Common'],
            ['article', 'Block', 'Flow', 'Common'],
            ['nav', 'Block', 'Flow', 'Common'],
            ['aside', 'Block', 'Flow', 'Common'],
            ['main', 'Block', 'Flow', 'Common'],
            ['header', 'Block', 'Flow', 'Common'],
            ['footer', 'Block', 'Flow', 'Common'],
            ['figure', 'Block', 'Optional: (figcaption, Flow) | (Flow, figcaption) | Flow', 'Common'], // tekrar eklemeyecek
        ] as $el) {
            [$name,$contentSet,$contents,$attr] = $el;
            if (!isset($def->info[$name])) {
                $def->addElement($name, $contentSet, $contents, $attr);
            }
        }

        // 5) Purifier’ı şimdi oluştur (finalize)
        return new HTMLPurifier($config);
    }

    private static function get(): HTMLPurifier
    {
        if (self::$purifier instanceof HTMLPurifier) return self::$purifier;
        self::$purifier = self::buildPurifier();
        return self::$purifier;
    }

    public static function cleanHtml(?string $html): string
    {
        $html = (string)$html;
        if ($html === '') return '';
        return self::get()->purify($html);
    }

    public static function cleanText(?string $text): string
    {
        $text = (string)$text;
        if ($text === '') return '';
        $text = strip_tags(self::get()->purify($text));
        return trim(preg_replace('/\s+/u', ' ', $text));
    }
}
