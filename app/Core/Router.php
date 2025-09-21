<?php
namespace App\Core;

class Router {
  private array $routes = ['GET'=>[], 'POST'=>[]];

  public function get(string $path, callable $handler): void {
    $this->add('GET', $path, $handler);
  }
  public function post(string $path, callable $handler): void {
    $this->add('POST', $path, $handler);
  }

  private function add(string $method, string $path, callable $handler): void {
    $path = $this->normalize($path);
    $this->routes[$method][$path] = $handler;
    // trailing slash varyantını da eşle
    $alt = rtrim($path, '/'); if ($alt === '') $alt = '/';
    $this->routes[$method][$alt] = $handler;
  }

  private function normalize(string $path): string {
    if ($path === '' || $path[0] !== '/') $path = '/' . $path;
    if ($path !== '/') $path = rtrim($path, '/');
    return $path;
  }

  public function dispatch(string $method, string $path): void {
    $method = strtoupper($method);
    if ($method === 'HEAD') $method = 'GET';

    $path = parse_url($path, PHP_URL_PATH) ?? '/';
    $path = $this->normalize($path);

    $candidates = [$path];
    $alt = rtrim($path, '/'); if ($alt === '') $alt = '/';
    if (!in_array($alt, $candidates, true)) $candidates[] = $alt;
    $slash = $path === '/' ? '/' : $path . '/';
    if (!in_array($slash, $candidates, true)) $candidates[] = $slash;

    foreach ($candidates as $p) {
      if (isset($this->routes[$method][$p])) {
        ($this->routes[$method][$p])();
        return;
      }
    }
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "404 Not Found";
  }
}
