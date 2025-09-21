<?php

namespace App\Core;

use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

/**
 * Symfony RateLimiter (token_bucket) — dosya sistemi cache ile.
 * - $capacity  : kova kapasitesi (burst)
 * - $perMinute : dakikadaki dolum miktarı
 */
class RateLimiter
{
    public static function consume(string $key, int $capacity, int $perMinute): bool
    {
        $capacity  = max(1, (int)$capacity);
        $perMinute = max(1, (int)$perMinute);

        $cacheDir = dirname(__DIR__, 2) . '/storage/cache/ratelimiter';
        if (!is_dir($cacheDir)) @mkdir($cacheDir, 0777, true);

        // Dosya tabanlı PSR-6 cache
        $cache   = new FilesystemAdapter(namespace: 'rl', defaultLifetime: 0, directory: $cacheDir);
        $storage = new CacheStorage($cache);

        // DİKKAT: 'limit' = kova kapasitesi; 'rate.amount' = dakikadaki dolum
        $factory = new RateLimiterFactory([
            'id'     => $key,
            'policy' => 'token_bucket',
            'limit'  => $capacity,                           // kova büyüklüğü
            'rate'   => ['interval' => '1 minute', 'amount' => $perMinute], // dolum hızı
            // bazı sürümlerde 'interval' doğrudan kök opsiyon olarak da desteklenir,
            // biz yeni tarza uygun 'rate' dizisini kullanıyoruz.
        ], $storage);

        $limiter = $factory->create($key);
        $limit   = $limiter->consume(1); // 1 jeton harca

        return $limit->isAccepted();
    }
}
