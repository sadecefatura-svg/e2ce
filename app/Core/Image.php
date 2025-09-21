<?php
namespace App\Core;

class Image {
  public static function dimensions(string $path): array {
    $size = @getimagesize($path);
    if (!$size) return [null, null];
    return [(int)$size[0], (int)$size[1]];
  }

  public static function makeWebp(string $src, string $dst, int $maxWidth = 1600, int $quality = 82): bool {
    // Imagick first (if available)
    if (class_exists(\Imagick::class)) {
      try {
        $im = new \Imagick($src);
        $w = $im->getImageWidth(); $h = $im->getImageHeight();
        if ($maxWidth > 0 && $w > $maxWidth) {
          $newH = (int)round(($maxWidth / $w) * $h);
          $im->resizeImage($maxWidth, $newH, \Imagick::FILTER_LANCZOS, 1);
        }
        $im->setImageFormat('webp');
        $im->setImageCompressionQuality($quality);
        $ok = $im->writeImage($dst);
        $im->clear(); $im->destroy();
        return (bool)$ok;
      } catch (\Throwable $e) {
        // fallback to GD
      }
    }
    // GD fallback
    $info = @getimagesize($src);
    if (!$info) return false;
    $mime = $info['mime'] ?? '';
    switch ($mime) {
      case 'image/jpeg': $img = @imagecreatefromjpeg($src); break;
      case 'image/png':  $img = @imagecreatefrompng($src);  break;
      case 'image/gif':  $img = @imagecreatefromgif($src);  break;
      case 'image/webp': $img = @imagecreatefromwebp($src); break;
      default: return false;
    }
    if (!$img) return false;
    $w = imagesx($img); $h = imagesy($img);
    if ($maxWidth > 0 && $w > $maxWidth) {
      $newW = $maxWidth; $newH = (int)round(($maxWidth / $w) * $h);
      $dstImg = imagecreatetruecolor($newW, $newH);
      imagecopyresampled($dstImg, $img, 0,0,0,0, $newW, $newH, $w, $h);
      imagedestroy($img);
      $img = $dstImg;
    }
    $ok = @imagewebp($img, $dst, $quality);
    imagedestroy($img);
    return (bool)$ok;
  }
}
