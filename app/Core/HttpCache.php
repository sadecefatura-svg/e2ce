<?php

namespace App\Core;

/**
 * Basit full-page dosya cache’i:
 * - Anahtar: host + path + whitelisted query + lang  → JSON → md5 ile dosya adı
 * - Store sırasında “tehlikeli başlıkları” (Set-Cookie, Cache-Control, vb.) filtreler
 * - ETag/Last-Modified üretir; If-None-Match / If-Modified-Since ile 304 döner
 * - Hedefli purge için her kayıtta logical_key “pieces” meta’sını da saklar
 */
final class HttpCache
{
    public static function isEnabled(array $cfg): bool
    {
        return !empty($cfg['cache']['full_page']['enabled']);
    }

    /** Basit dosya log’u */
    private static function log(string $msg): void
    {
        $logDir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($logDir)) @mkdir($logDir, 0777, true);
        $line = '[' . date('Y-m-d H:i:s') . "] HttpCache: " . $msg . PHP_EOL;
        @file_put_contents($logDir . '/cache.log', $line, FILE_APPEND);
    }

    /** İstekten mantıksal key “parçaları”nı üret (host/path/qs/lang/ae) */
    private static function piecesFromRequest(array $cfg): ?array
    {
        if (!self::isEnabled($cfg)) return null;

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            if (!headers_sent()) header('X-Cache-Bypass: method', false);
            self::log('bypass: method=' . $method);
            return null;
        }

        // Oturum varsa cache bypass
        $sessCookie = $cfg['cache']['full_page']['session_cookie'] ?? 'E2CESESSID';
        if (!empty($_COOKIE[$sessCookie])) {
            if (!headers_sent()) header('X-Cache-Bypass: session', false);
            self::log('bypass: session cookie present');
            return null;
        }

        $uri  = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        // Hariç tutulan path?
        foreach (($cfg['cache']['full_page']['exclude'] ?? []) as $rx) {
            if (@preg_match($rx, $path)) {
                if (!headers_sent()) header('X-Cache-Bypass: excluded-path', false);
                self::log('bypass: excluded path ' . $path . ' by ' . $rx);
                return null;
            }
        }

        $host = strtolower($_SERVER['HTTP_HOST'] ?? 'localhost');

        // Query beyaz liste
        $qs = [];
        if (!empty($cfg['cache']['full_page']['vary_query'])) {
            $whitelist = array_flip($cfg['cache']['full_page']['vary_query']);
            foreach ($_GET as $k => $v) {
                if (isset($whitelist[$k])) $qs[$k] = (string)$v;
            }
            ksort($qs);
        }

        // dili anahtara her zaman ekle
        $lang = \App\Core\I18n::get();

        $pieces = [
            'host' => $host,
            'path' => $path,
            'qs'   => $qs,
            'lang' => $lang,
        ];

        if (!empty($cfg['cache']['full_page']['vary_encoding'])) {
            $pieces['ae'] = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
        }

        return $pieces;
    }

    /** Mantıksal key → JSON string (hash’lenecek) */
    private static function logicalKeyFromPieces(array $pieces): string
    {
        return json_encode($pieces, JSON_UNESCAPED_SLASHES);
    }

    public static function keyFromRequest(array $cfg): ?string
    {
        $pieces = self::piecesFromRequest($cfg);
        return $pieces ? self::logicalKeyFromPieces($pieces) : null;
    }

    public static function tryServe(array $cfg): bool
    {
        $pieces = self::piecesFromRequest($cfg);
        if (!$pieces) return false;

        $logicalKey = self::logicalKeyFromPieces($pieces);
        $file = self::pathForKey($cfg, $logicalKey);
        if (!is_file($file)) {
            self::log('MISS (no file): ' . $file);
            return false;
        }

        $ttl = (int)($cfg['cache']['full_page']['ttl'] ?? 600);
        if ($ttl > 0 && (time() - filemtime($file)) > $ttl) {
            @unlink($file);
            if (!headers_sent()) header('X-Cache: EXPIRED', false);
            self::log('EXPIRED: ' . $file);
            return false;
        }

        $payload = @file_get_contents($file);
        if ($payload === false || $payload === '') {
            self::log('MISS (empty read): ' . $file);
            return false;
        }

        $data = @json_decode($payload, true);
        if (!is_array($data) || !isset($data['body'])) {
            self::log('MISS (bad json): ' . $file);
            return false;
        }

        // Koşullu GET
        $etag = $data['etag'] ?? null;
        $lastMod = $data['last_modified'] ?? null;
        $ifNone = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
        $ifMod  = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';

        if ($etag && $ifNone && trim($ifNone) === $etag) {
            header('ETag: ' . $etag);
            if ($lastMod) header('Last-Modified: ' . $lastMod);
            header('Cache-Control: public, max-age=' . max(1, (int)($cfg['cache']['full_page']['ttl'] ?? 600)));
            header('X-Cache: HIT-304');
            http_response_code(304);
            return true;
        }
        if ($lastMod && $ifMod) {
            $ims = strtotime($ifMod);
            $srv = isset($data['ts']) ? (int)$data['ts'] : time();
            if ($ims !== false && $ims >= $srv) {
                if ($etag) header('ETag: ' . $etag);
                header('Last-Modified: ' . $lastMod);
                header('Cache-Control: public, max-age=' . max(1, (int)($cfg['cache']['full_page']['ttl'] ?? 600)));
                header('X-Cache: HIT-304');
                http_response_code(304);
                return true;
            }
        }

        http_response_code((int)($data['status'] ?? 200));
        header('Content-Type: ' . ($data['content_type'] ?? 'text/html; charset=utf-8'));
        header('Cache-Control: public, max-age=' . max(1, (int)($cfg['cache']['full_page']['ttl'] ?? 600)));
        header('X-Cache: HIT');
        if ($etag) header('ETag: ' . $etag);
        if ($lastMod) header('Last-Modified: ' . $lastMod);

        if (!empty($data['headers']) && is_array($data['headers'])) {
            foreach ($data['headers'] as $h) {
                if (stripos($h, 'X-Cache') === 0) continue;
                if (stripos($h, 'ETag:') === 0) continue;
                if (stripos($h, 'Last-Modified:') === 0) continue;
                header($h, false);
            }
        }

        echo (string)$data['body'];
        self::log('HIT: ' . $file);
        return true;
    }

    public static function captureAndStore(array $cfg, ?string $logicalKey): void
    {
        if (!$logicalKey) {
            if (!headers_sent()) header('X-Cache-Store: skipped-no-key', false);
            self::log('STORE skip: no key');
            return;
        }

        if (ob_get_level() <= 0) {
            if (!headers_sent()) header('X-Cache-Store: skipped-no-buffer', false);
            self::log('STORE skip: no buffer');
            return;
        }

        $status = http_response_code();
        if ($status !== 200) {
            if (!headers_sent()) header('X-Cache-Store: skipped-status-' . $status, false);
            self::log('STORE skip: status=' . $status);
            return;
        }

        $headers = function_exists('headers_list') ? headers_list() : [];
        // Eğer Set-Cookie varsa, asla cache’e yazma
        foreach ($headers as $h) {
            if (stripos($h, 'Set-Cookie:') === 0) {
                if (!headers_sent()) header('X-Cache-Store: skipped-set-cookie', false);
                self::log('STORE skip: set-cookie present');
                return;
            }
        }

        // Sadece HTML cache’le
        $ct = 'text/html; charset=utf-8';
        foreach ($headers as $h) {
            if (stripos($h, 'Content-Type:') === 0) {
                $ct = trim(substr($h, strlen('Content-Type:')));
                break;
            }
        }
        if (stripos($ct, 'text/html') !== 0) {
            if (!headers_sent()) header('X-Cache-Store: skipped-non-html', false);
            self::log('STORE skip: non-html');
            return;
        }

        $body = ob_get_contents();
        if ($body === false || $body === '') {
            if (!headers_sent()) header('X-Cache-Store: skipped-empty-body', false);
            self::log('STORE skip: empty body');
            return;
        }

        // Başlık filtresi (cache’e gömmek istemediklerimiz)
        $deny = ['set-cookie:', 'cache-control:', 'pragma:', 'expires:', 'transfer-encoding:', 'content-length:'];
        $safeHeaders = [];
        foreach ($headers as $h) {
            $hl = strtolower($h);
            $blocked = false;
            foreach ($deny as $pfx) {
                if (str_starts_with($hl, $pfx)) {
                    $blocked = true;
                    break;
                }
            }
            if ($blocked) continue;
            if (str_starts_with($hl, 'x-cache')) continue;
            if (str_starts_with($hl, 'etag:')) continue;
            if (str_starts_with($hl, 'last-modified:')) continue;
            $safeHeaders[] = $h;
        }

        $file = self::pathForKey($cfg, $logicalKey);
        $dir  = dirname($file);
        if (!is_dir($dir)) @mkdir($dir, 0777, true);

        // ETag / Last-Modified
        $etag = '"W/' . md5($body) . '"';
        $lastMod = gmdate('D, d M Y H:i:s', time()) . ' GMT';

        // pieces’i da sakla (hedefli purge için)
        $pieces = json_decode($logicalKey, true);

        $data = [
            'status'        => $status,
            'content_type'  => $ct,
            'headers'       => $safeHeaders,
            'body'          => $body,
            'ts'            => time(),
            'etag'          => $etag,
            'last_modified' => $lastMod,
            'pieces'        => $pieces,
        ];

        if (@file_put_contents($file, json_encode($data, JSON_UNESCAPED_SLASHES)) !== false) {
            if (!headers_sent()) header('X-Cache-Store: wrote', false);
            self::log('STORE wrote: ' . $file);
        } else {
            if (!headers_sent()) header('X-Cache-Store: write-failed', false);
            self::log('STORE write FAILED: ' . $file);
        }
    }

    public static function purgeAll(array $cfg): void
    {
        $dir = rtrim($cfg['cache']['full_page']['dir'] ?? '', '/\\');
        if (!$dir || !is_dir($dir)) return;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            if ($f->isDir()) @rmdir($f->getRealPath());
            else @unlink($f->getRealPath());
        }
        self::log('PURGE ALL: ' . $dir);
    }

    /**
     * Basit hedefli purge:
     * storage/cache/html altındaki JSON’ları okuyup “pieces.path/lang/qs”
     * ile eşleşenleri siler.
     * paths: ['/en/', '/en/post?slug=xyz', '/en/tag/ai', '/sitemap.xml', ...]
     */
    public static function purgeByPaths(array $cfg, array $paths): void
    {
        $dir = rtrim($cfg['cache']['full_page']['dir'] ?? '', '/\\');
        if (!$dir || !is_dir($dir) || empty($paths)) return;

        $normalized = array_map(function ($u) {
            // sadece path + whitelisted query’leri normalize edelim
            $p = parse_url($u, PHP_URL_PATH) ?: '/';
            $q = parse_url($u, PHP_URL_QUERY) ?: '';
            parse_str($q, $arr);
            ksort($arr);
            return $p . ($arr ? ('?' . http_build_query($arr)) : '');
        }, $paths);
        $toMatch = array_flip($normalized);

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        $deleted = 0;
        foreach ($it as $f) {
            if (!$f->isFile()) continue;
            if (substr($f->getFilename(), -5) !== '.json') continue;
            $payload = @file_get_contents($f->getRealPath());
            if ($payload === false) continue;
            $data = @json_decode($payload, true);
            if (!is_array($data) || empty($data['pieces'])) continue;
            $pc = $data['pieces'];
            $p = $pc['path'] ?? '/';
            $qs = $pc['qs'] ?? [];
            ksort($qs);
            $key = $p . ($qs ? ('?' . http_build_query($qs)) : '');
            if (isset($toMatch[$key])) {
                @unlink($f->getRealPath());
                $deleted++;
            }
        }
        self::log('PURGE PATHS count=' . count($paths) . ' deleted=' . $deleted);
    }

    /** md5 ile güvenli, kısa dosya adı */
    private static function pathForKey(array $cfg, string $logicalKey): string
    {
        $dir = rtrim($cfg['cache']['full_page']['dir'] ?? (dirname(__DIR__, 2) . '/storage/cache/html'), '/\\');
        $hash = md5($logicalKey);
        return $dir . DIRECTORY_SEPARATOR . substr($hash, 0, 2) . DIRECTORY_SEPARATOR . $hash . '.json';
    }
}
