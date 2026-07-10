<?php

namespace Keldagrim\Core;

use Closure;
use Keldagrim\Throwable\Exception\Logic\RouteException;

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
    $custom_request_path = $url_components['path'] ?? '';
    $server_request_uri = $_SERVER['REQUEST_URI'] ?? '';

    $request->uri = strpos($server_request_uri, $custom_request_path) === 0
      ? substr($server_request_uri, strlen($custom_request_path))
      : $custom_request_path;

    $request->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? '');

    $routes_map = Route::all([$request->method]);
    $routes = $routes_map[$request->method] ?? [];
    if (empty($routes))
      throw new RouteException("No route matches [{$request->method}] \"{$request->uri}\"");

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
      throw new RouteException("No route matches [{$request->method}] \"{$request->uri}\"");

    return $request;
  }
}
