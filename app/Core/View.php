<?php

namespace App\Core;

final class View
{
  public static function render(string $view, array $data = []): void
  {
    $viewsRoot = dirname(__DIR__) . '/Views';
    $viewFile  = $viewsRoot . '/' . $view . '.php';
    if (!is_file($viewFile)) {
      http_response_code(500);
      error_log('[View] missing: ' . $viewFile);
      echo 'View not found';
      return;
    }

    // data -> değişkenler
    extract($data, EXTR_SKIP);

    // İçeriği üret
    ob_start();
    include $viewFile;
    $content = ob_get_clean();

    // AMP veya mweb gibi tam sayfalar: layout yok
    $isFullPage = str_ends_with($view, '.amp') || str_ends_with($view, '.mobile');

    // Zorla kapatma için: ['layout' => false] gelebilir
    $layout = $data['layout'] ?? 'layout';
    if ($layout === false || $isFullPage) {
      echo $content;
      return;
    }

    // Varsayılan layout
    $layoutFile = $viewsRoot . '/' . $layout . '.php';
    if (is_file($layoutFile)) {
      include $layoutFile; // $content burada kullanılıyor
    } else {
      // Layout yoksa en azından içerik çıksın
      error_log('[View] layout missing: ' . $layoutFile);
      echo $content;
    }
  }
}
