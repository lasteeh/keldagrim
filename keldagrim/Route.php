<?php

namespace Keldagrim;

final class Route {
  private static array $routes = [];

  public static function fetch_all(array $methods = []): array {
    if (empty($methods)) return self::$routes;

    $routes = [];
    foreach ($methods as $method) {
      if (!isset(self::$routes[$method])) continue;
      
      usort(self::$routes[$method], fn($a, $b) => $b['score'] <=> $a['score']);
      $routes[$method] = self::$routes[$method];
    }

    return $routes;
  }

  public static function get(string $path, array|callable $action): void {
    self::add_route(['GET','HEAD'], $path, $action);
  }

  private static function add_route(array|string $methods, string $path, array|callable $action): void {
    if (is_array($methods)) {
      foreach($methods as $method) {
        self::$routes[$method] [] = [
          'path' => $path,
          'action' => $action,
          'pattern' => self::compile($path),
          'score' => self::priority_score($path),
        ];
     }
    } else {
      self::$routes[$methods][] = [
          'path' => $path,
          'action' => $action,
          'pattern' => self::compile($path),
          'score' => self::priority_score($path),
        ];
    }
  }

  private static function compile(string $path): string {
    $pattern = $path;

    // wildcard: :slug* including slashes
    $pattern = preg_replace(
      '/\:(\w+)\*/',
      '(?P<$1>.+)',
      $pattern
    );

    // optional: :id? excluding slashes
    $pattern = preg_replace(
      '/\:(\w+)\?/',
      '(?P<$1>[^/]*)?',
      $pattern
    );

    // required: :id excluding slashes
    $pattern = preg_replace(
      '/\:(\w+)/',
      '(?P<$1>[^/]+)',
      $pattern
    );

    return '#^' . $pattern . '$#';
  }

  private static function priority_score(string $path): int {
    $score = 0;

    $segments = explode('/', trim($path, '/'));
    foreach ($segments as $segment) {
      if ($segment === '') continue;
      if (str_ends_with($segment, '*')) { $score += 1; continue; }
      if (str_ends_with($segment, '?')) { $score += 5; continue; }
      if (str_starts_with($segment, ':')) { $score += 10; continue; }
      $score += 20;
    }

    return $score;
  } 
}
