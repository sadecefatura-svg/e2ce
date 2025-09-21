<?php

namespace App\Core;

use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;

class ImageKit
{
    private static ?ImageManager $im = null;

    private static function cfg(): array
    {
        $cfgFile = dirname(__DIR__, 2) . '/config/config.php';
        return is_file($cfgFile) ? (require $cfgFile) : [];
    }

    private static function manager(): ImageManager
    {
        if (self::$im) return self::$im;
        $cfg = self::cfg();
        $drv = strtolower($cfg['images']['driver'] ?? 'auto');
        if ($drv === 'imagick' && class_exists(\Imagick::class)) {
            self::$im = new ImageManager(\Intervention\Image\Drivers\Imagick\Driver::class);
        } elseif ($drv === 'auto' && class_exists(\Imagick::class)) {
            self::$im = new ImageManager(\Intervention\Image\Drivers\Imagick\Driver::class);
        } else {
            self::$im = new ImageManager(\Intervention\Image\Drivers\Gd\Driver::class);
        }
        return self::$im;
    }

    public static function validateUpload(array $file): array
    {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new \RuntimeException('Invalid upload.');
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($mime, $allowed, true)) {
            throw new \RuntimeException('Unsupported image type.');
        }
        try {
            self::manager()->read($file['tmp_name']);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Corrupt image.');
        }
        return ['mime' => $mime];
    }

    public static function moveOriginal(array $file, ?string $subdirDate = null): string
    {
        $cfg  = self::cfg();
        $root = rtrim($cfg['images']['root'] ?? (dirname(__DIR__, 2) . '/public/uploads'), '/\\');

        $sub = $subdirDate ?: date('Y/m/d');
        $dir = $root . DIRECTORY_SEPARATOR . $sub;
        if (!is_dir($dir)) @mkdir($dir, 0777, true);

        $ext  = self::extFromName($file['name'] ?? '') ?? self::extFromMime($file['type'] ?? '') ?? 'jpg';
        $base = pathinfo($file['name'] ?? ('img_' . time()), PATHINFO_FILENAME);
        $base = self::slugify($base) ?: 'image';

        $dest = $dir . DIRECTORY_SEPARATOR . $base . '.' . $ext;
        $i = 1;
        while (file_exists($dest)) $dest = $dir . DIRECTORY_SEPARATOR . $base . '-' . $i++ . '.' . $ext;

        if (!@move_uploaded_file($file['tmp_name'], $dest)) {
            if (!@rename($file['tmp_name'], $dest)) throw new \RuntimeException('Failed to move upload.');
        }

        $webBase = rtrim($cfg['images']['base'] ?? '/uploads', '/');
        return $webBase . '/' . str_replace(DIRECTORY_SEPARATOR, '/', $sub) . '/' . basename($dest);
    }

    public static function generateVariants(string $origUrl): array
    {
        $cfg  = self::cfg();
        $root = rtrim($cfg['images']['root'] ?? (dirname(__DIR__, 2) . '/public/uploads'), '/\\');
        $base = rtrim($cfg['images']['base'] ?? '/uploads', '/');

        if (!str_starts_with($origUrl, $base . '/')) return [];

        $rel = substr($origUrl, strlen($base) + 1); // 2025/09/18/my.jpg
        $src = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (!is_file($src)) return [];

        $variantsConf = $cfg['images']['variants'] ?? [];
        if (!$variantsConf) return [];

        $out = [];
        foreach ($variantsConf as $name => $opt) {
            $subdir = dirname($rel);
            $vdir = $root . DIRECTORY_SEPARATOR . 'derived' . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $subdir);
            if (!is_dir($vdir)) @mkdir($vdir, 0777, true);

            try {
                // v3: EXIF’e göre döndürme ->orient()
                $img = self::manager()->read($src)->orient();
                $img = self::applyResize($img, (int)($opt['w'] ?? 0), (int)($opt['h'] ?? 0), (string)($opt['mode'] ?? 'fit'));
                $quality = (int)($opt['quality'] ?? 85);

                $preferred   = self::pickPreferredFormat($opt['format'] ?? 'auto');
                $saveResult  = self::saveWithFallbacks($img, $vdir, pathinfo($src, PATHINFO_FILENAME), $preferred, $quality);

                if ($saveResult) {
                    [$extUsed, $fileName] = $saveResult;
                    $web = $base . '/derived/' . $name . '/' . $subdir . '/' . $fileName;
                    $out[$name] = str_replace('\\', '/', $web);
                }
            } catch (\Throwable $e) {
                self::log("variant={$name} error=" . $e->getMessage());
                continue;
            }
        }
        return $out;
    }

    public static function ensureDerivedForContent(string $html): ?array
    {
        if (!preg_match('~<img[^>]+src=["\']([^"\']+)~i', $html, $m)) return null;
        $src = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
        if (!is_string($src)) return null;
        return self::generateVariants($src);
    }

    // helpers

    private static function applyResize(ImageInterface $img, int $w, int $h = null, string $mode = 'fit'): ImageInterface
    {
        if ($mode === 'cover' && $w > 0 && $h > 0) return $img->cover($w, $h);
        if ($mode === 'fit' && $w > 0) return $img->scaleDown($w, $h ?: null);
        return $img;
    }

    private static function pickPreferredFormat(string $format): string
    {
        $format = strtolower($format);
        if ($format === 'auto') return self::supportsWebp() ? 'webp' : 'jpg';
        if (in_array($format, ['webp', 'jpg', 'jpeg', 'png'], true)) return $format === 'jpeg' ? 'jpg' : $format;
        return 'jpg';
    }

    private static function supportsWebp(): bool
    {
        if (self::isUsingGD()) return function_exists('imagewebp');
        if (class_exists(\Imagick::class)) {
            try {
                $fmts = (new \Imagick())->queryFormats('WEBP');
                return is_array($fmts) && !empty($fmts);
            } catch (\Throwable $e) {
                return false;
            }
        }
        return false;
    }

    private static function isUsingGD(): bool
    {
        return function_exists('imagecreatetruecolor');
    }

    private static function saveWithFallbacks(ImageInterface $img, string $dir, string $name, string $preferred, int $quality): ?array
    {
        $candidates = [];
        foreach ([$preferred, 'jpg', 'png'] as $ext) if (!in_array($ext, $candidates, true)) $candidates[] = $ext;

        foreach ($candidates as $ext) {
            $file = $name . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
            $dst  = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $file;
            try {
                $img->save($dst, $quality);
                return [$ext, $file];
            } catch (\Throwable $e) {
                self::log("save fail ext={$ext} msg=" . $e->getMessage());
            }
        }
        return null;
    }

    private static function extFromName(?string $name): ?string
    {
        if (!$name) return null;
        $e = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        return match ($e) {
            'jpeg' => 'jpg',
            'jpg', 'png', 'webp', 'gif' => $e,
            default => null,
        };
    }

    private static function extFromMime(?string $mime): ?string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
            default => null,
        };
    }

    private static function slugify(string $s): string
    {
        $s = @iconv('UTF-8', 'ASCII//TRANSLIT', $s) ?: $s;
        $s = strtolower($s);
        $s = preg_replace('~[^a-z0-9]+~', '-', $s);
        return trim($s, '-');
    }

    private static function log(string $msg): void
    {
        $dir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($dir)) @mkdir($dir, 0777, true);
        @file_put_contents($dir . '/imagekit.log', '[' . date('Y-m-d H:i:s') . "] {$msg}\n", FILE_APPEND);
        error_log('[ImageKit] ' . $msg);
    }
}
