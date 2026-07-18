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

    $request->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? '');
    $request->uri = self::normalize_trailing_slash($uri, $request->method);

    $lock_directory = realpath(Config::HOME_DIR() . DIRECTORY_SEPARATOR . Config::PUBLIC_DIR);
    $static_file = realpath($lock_directory . DIRECTORY_SEPARATOR . $request->uri);
    if (
      !empty($static_file) &&
      is_file($static_file) &&
      str_starts_with($static_file, $lock_directory . DIRECTORY_SEPARATOR)
    ) {
      $ext = strtolower(pathinfo($static_file, PATHINFO_EXTENSION));
      if ($ext === 'php') {
        require $static_file;
        exit;
      }
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
        'woff2' => 'font/woff2',
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

      // Named groups only. An unmatched optional group that precedes a
      // matched group is reported by PCRE as "" — under compile()'s
      // [^/]+ groups an empty capture can only mean "did not
      // participate", so drop those too (route_param() then returns
      // null for omitted optionals in every position).
      $route_params = array_filter(
        $matches,
        fn($value, $key) => is_string($key) && $value !== '',
        ARRAY_FILTER_USE_BOTH
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

  /**
   * Applies the app.trailing_slash policy to a request URI:
   *
   * - 'trim'     (default) "/users/5/" is routed as "/users/5"
   * - 'redirect' respond 301 (GET/HEAD) or 308 (others) to the
   *              trimmed URI, preserving the query string
   * - 'strict'   leave the URI untouched; "/users/5/" only matches a
   *              route that explicitly ends in "/"
   *
   * The root URI "/" is never altered.
   */
  private static function normalize_trailing_slash(string $uri, string $method): string
  {
    if ($uri === '/' || !str_ends_with($uri, '/')) return $uri;

    $policy = Config::app('trailing_slash', 'trim');

    return match ($policy) {
      'strict' => $uri,
      'redirect' => self::redirect_to_canonical(rtrim($uri, '/'), $method),
      default => rtrim($uri, '/'),
    };
  }

  private static function redirect_to_canonical(string $canonical, string $method): never
  {
    $query = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY);
    $location = Config::HOME_URL() . $canonical . (empty($query) ? '' : '?' . $query);

    http_response_code(in_array($method, ['GET', 'HEAD'], true) ? 301 : 308);
    header('Location: ' . $location);
    exit;
  }
}
