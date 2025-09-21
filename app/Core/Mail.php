<?php
namespace App\Core;

use Throwable;

class Mail {
  private static $mailer = null; // Symfony mailer instance (lazy)

  private static function cfg(): array {
    $file = dirname(__DIR__, 2) . '/config/config.php';
    $cfg  = is_file($file) ? (require $file) : [];
    return $cfg['mail'] ?? [];
  }

  /** Symfony Mailer hazır mı? */
  private static function symfonyAvailable(): bool {
    return class_exists(\Symfony\Component\Mailer\Mailer::class)
        && class_exists(\Symfony\Component\Mailer\Transport::class)
        && class_exists(\Symfony\Component\Mime\Email::class);
  }

  /** Lazy olarak Symfony Mailer kur */
  private static function symfony(): \Symfony\Component\Mailer\Mailer {
    if (self::$mailer) return self::$mailer;
    $cfg = self::cfg();
    $dsn = $cfg['dsn'] ?? 'smtp://localhost';
    $transport = \Symfony\Component\Mailer\Transport::fromDsn($dsn);
    // Timeout ayarı
    if (method_exists($transport, 'setTimeout') && isset($cfg['timeout'])) {
      $transport->setTimeout((int)$cfg['timeout']);
    }
    return self::$mailer = new \Symfony\Component\Mailer\Mailer($transport);
  }

  /**
   * HTML mail gönder. Metin alternatifi otomatik üretilir.
   * Hata alırsak (ve debug_log açıksa) storage/logs/mail.log’a yazarız.
   */
  public static function send(string $to, string $subject, string $html): void {
    $cfg   = self::cfg();
    $from  = $cfg['from']      ?? 'no-reply@localhost';
    $fname = $cfg['from_name'] ?? null;
    $reply = $cfg['reply_to']  ?? null;

    $textAlt = self::htmlToText($html);

    // Symfony varsa dene
    if (self::symfonyAvailable()) {
      try {
        $email = (new \Symfony\Component\Mime\Email())
          ->from($fname ? new \Symfony\Component\Mime\Address($from, $fname) : $from)
          ->to($to)
          ->subject($subject)
          ->text($textAlt)
          ->html($html);

        if ($reply) $email->replyTo($reply);

        self::symfony()->send($email);
        return; // başarı
      } catch (Throwable $e) {
        if (!($cfg['debug_log'] ?? true)) {
          // log kapalıysa hatayı yükseltmiyoruz; sessizce düşür.
        } else {
          self::logFallback($to, $subject, $html, '[SEND ERROR] '.$e->getMessage());
        }
        return;
      }
    }

    // Symfony yoksa (veya gönderim hatası varsa) dosyaya logla (fallback)
    self::logFallback($to, $subject, $html, '[FALLBACK LOG]');
  }

  /** Basit HTML→text dönüştürücü */
  private static function htmlToText(string $html): string {
    // <br> ve blokları satıra çevir
    $text = preg_replace('#<\s*br\s*/?>#i', "\n", $html);
    $text = preg_replace('#</\s*p\s*>#i', "\n\n", $text);
    // etiketleri sil
    $text = strip_tags($text);
    // whitespace düzelt
    $text = preg_replace('/\h+/', ' ', $text);
    $text = preg_replace('/\R{3,}/', "\n\n", $text);
    return trim($text);
  }

  /** storage/logs/mail.log’a yaz */
  private static function logFallback(string $to, string $subject, string $html, string $prefix): void {
    $dir = dirname(__DIR__, 2) . '/storage/logs';
    if (!is_dir($dir)) @mkdir($dir, 0777, true);
    $line = "----\n".date('Y-m-d H:i:s')." {$prefix}\nTO: {$to}\nSUBJECT: {$subject}\n\n{$html}\n\n";
    @file_put_contents($dir.'/mail.log', $line, FILE_APPEND);
    error_log("[MAIL] {$prefix} to={$to} subject={$subject}");
  }
}
