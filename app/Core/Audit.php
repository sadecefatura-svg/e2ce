<?php

namespace App\Core;

use PDO;

class Audit
{
    public static function logAuth(?int $userId, ?string $email, bool $ok, ?string $reason = null): void
    {
        try {
            $cfg = require dirname(__DIR__, 2) . '/config/config.php';
            $pdo = DB::conn($cfg['db']);

            $ip  = $_SERVER['REMOTE_ADDR']    ?? null;
            $ua  = $_SERVER['HTTP_USER_AGENT'] ?? null;

            $stmt = $pdo->prepare("INSERT INTO auth_log (user_id, email, ip, user_agent, ok, reason) VALUES (?,?,?,?,?,?)");
            $stmt->execute([
                $userId,
                $email,
                $ip,
                $ua ? substr($ua, 0, 255) : null,
                $ok ? 1 : 0,
                $reason ? substr($reason, 0, 100) : null // zaten kısa tutuyoruz ama yine de kırpalım
            ]);
        } catch (\Throwable $e) {
            // audit log düşmezse uygulamayı bozmayalım
            error_log('[AUDIT] logAuth failed: ' . $e->getMessage());
        }
    }
}
