<?php

namespace Keldagrim\Core;

use Closure;
use Keldagrim\Throwable\Exception\Routing\RouteNotFoundException;

final class Request
{
  public readonly string $uri;
  public readonly string $method;
  public readonly Closure|array|null $action;
  public readonly array $route_params;

  private function __construct() {}

  public static function capture(): self
  {
    $request = new self;

    $url_components = parse_url(Config::HOME_URL());
    $base_path = rtrim($url_components['path'] ?? '', '/');

    $server_request_uri = $_SERVER['REQUEST_URI'] ?? '';
    $path = parse_url($server_request_uri, PHP_URL_PATH) ?? '';

    if ($base_path === '' || $path === $base_path) {
      $uri = ($base_path === '') ? $path : '/';
    } elseif (str_starts_with($path, $base_path . '/')) {
      $uri = substr($path, strlen($base_path));
    } else {
      throw new RouteNotFoundException("Request \"{$path}\" is outside the application base path");
    }

    $uri = rawurldecode($uri);
    if ($uri === '') $uri = '/';

    $request->uri = $uri;
    $request->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? '');

    $lock_directory = realpath(Config::HOME_DIR() . DIRECTORY_SEPARATOR . Config::PUBLIC_DIR);
    $static_file = realpath($lock_directory . DIRECTORY_SEPARATOR . $request->uri);

    if (
      !empty($static_file) && 
      is_file($static_file) && 
      str_starts_with($static_file, $lock_directory . DIRECTORY_SEPARATOR)
    ) {
      $ext = strtolower(pathinfo($static_file, PATHINFO_EXTENSION));
      if ($ext === 'php') { require $static_file; exit; }

      $mimes = [
        'css'  => 'text/css',
        'js'   => 'application/javascript',
        'json' => 'application/json',
        'html' => 'text/html',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
        'webp' => 'image/webp',
        'ico'  => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2'=> 'font/woff2',
        'txt'  => 'text/plain',
        'pdf'  => 'application/pdf',
      ];

      header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
      header('Content-Length: ' . filesize($static_file));
      readfile($static_file);
      exit;
    }

    $routes_map = Route::all([$request->method]);
    $routes = $routes_map[$request->method] ?? [];
    if (empty($routes))
      throw new RouteNotFoundException("No route matches [{$request->method}] \"{$request->uri}\"");

    $match_found = false;
    foreach ($routes as $route) {
      if ($match_found) break;

      $pattern = $route['pattern'] ?? '';
      if (!preg_match($pattern, $request->uri, $matches)) continue;

      $route_params = array_filter(
        $matches,
        fn($key) => is_string($key),
        ARRAY_FILTER_USE_KEY
      );

      $request->action = $route['action'] ?? null;
      $request->route_params = $route_params;

      $match_found = true;
      break;
    }

    if (empty($match_found))
      throw new RouteNotFoundException("No route matches [{$request->method}] \"{$request->uri}\"");

    return $request;
  }
}
