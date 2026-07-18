<?php

namespace Keldagrim\Core;

use Keldagrim\Throwable\Exception\Routing\RouteException;
use Keldagrim\Core\Config;

final class Route
{
  public readonly string $path;
  public readonly string $url;
  public readonly string $name;
  public readonly array $params;

  private static array $routes = [];
  private static array $routes_by_name = [];

  private function __construct(string $path, string $name, array $params = [])
  {
    $this->path = $path;
    $this->name = $name;
    $this->params = $params;
    $this->url = Config::HOME_URL() . $path;
  }

  /**
   * Returns all registered routes grouped by HTTP method.
   *
   * If no methods are provided, returns the full route registry.
   * Otherwise filters and sorts routes by priority score per method.
   *
   * Example:
   *   Route::all()
   *   Route::all(['GET', 'POST'])
   *
   * @param array $methods Optional list of HTTP methods to filter by
   * @return array Array of registered route definitions grouped by method
   */
  public static function all(array $methods = []): array
  {
    if (empty($methods)) return self::$routes;

    $routes = [];
    foreach ($methods as $method) {
      if (!isset(self::$routes[$method])) continue;

      usort(self::$routes[$method], fn($a, $b) => $b['score'] <=> $a['score']);
      $routes[$method] = self::$routes[$method];
    }

    return $routes;
  }

  /**
   * Returns the resolved path for a named route.
   *
   * This replaces all route parameters (:id, :slug?, :path*) with the
   * provided values and returns the final URI path string.
   *
   * Example:
   *   Route::path('user.show', ['id' => 5])
   *   => "/users/5"
   *
   * @param string $name Route name registered in the router
   * @param array $route_params Key-value pairs for route parameters
   * @return string Fully resolved path (no domain, no scheme)
   * @throws RouteException If route is not defined or required parameters are missing
   */
  public static function path(string $name, array $route_params = []): string
  {
    return (Route::fetch($name, $route_params))->path;
  }

  /**
   * Returns the full URL for a named route.
   *
   * This is identical to `Route::path()` but intended to represent a complete URL
   * (depending on how `$url` is constructed internally, e.g. including base URL).
   *
   * Example:
   *   Route::url('user.show', ['id' => 5])
   *   => "https://example.com/users/5"
   *
   * @param string $name Route name registered in the router
   * @param array $route_params Key-value pairs for route parameters
   * @return string Fully resolved URL
   * @throws RouteException If route is not defined or required parameters are missing
   */
  public static function url(string $name, array $route_params = []): string
  {
    return (Route::fetch($name, $route_params))->url;
  }

  /**
   * Resolves a named route into a Route instance with parameters applied.
   *
   * This performs:
   * - Route lookup by name
   * - Validation of required parameters
   * - Replacement of :params, :optional?, and :wildcard* tokens
   * - Normalization of the resulting path
   * - Validation for unresolved tokens
   *
   * Example:
   *   Route::fetch('user.show', ['id' => 5])
   *   => Route { path: "/users/5" }
   *
   * Parameter rules:
   * - :param   => required
   * - :param?  => optional (removed if not provided)
   * - :param*  => wildcard (can contain slashes)
   *
   * @param string $name Route name registered in the router
   * @param array $route_params Key-value pairs for route parameters
   * @return self Resolved Route instance
   * @throws RouteException If route is not found, required params are missing,
   *                        or unresolved parameters remain after generation
   */
  public static function fetch(string $name, array $route_params = []): self
  {
    if (!isset(self::$routes_by_name[$name])) throw new RouteException("Route [{$name}] not defined");

    $route = self::$routes_by_name[$name] ?? null;
    if (empty($route)) throw new RouteException("Route metadata missing for [{$name}]");

    foreach ($route['params'] as $param => $type) {
      if ($type === 'required' && !array_key_exists($param, $route_params))
        throw new RouteException("Missing required route parameter: {$param}");
    }

    $path = preg_replace_callback(
      '/:(\w+)([?*])?/',
      static function ($matches) use ($route_params) {
        $param = $matches[1];
        $modifier = $matches[2] ?? null;

        if (!array_key_exists($param, $route_params))
          return $modifier === '?' ? '' : $matches[0];

        $value = (string) $route_params[$param];

        return $modifier === '*' ? $value : rawurlencode($value);
      },
      $route['path']
    );

    $path = preg_replace('#//+#', '/', $path);
    if ($path !== '/') $path = rtrim($path, '/');

    if (preg_match('/:\w+([?*])?/', $path))
      throw new RouteException("Unresolved route parameters in generated path [{$path}]");

    return new self($path, $name, $route_params);
  }

  /**
   * Registers a GET route.
   *
   * This route will respond to GET and HEAD requests.
   *
   * Example:
   *   Route::get('/users/:id', [UserController::class, 'show'], 'user.show');
   *
   * @param string $path Route URI pattern
   * @param array|callable $action Controller action or callable handler
   * @param string $name Optional route name for URL generation
   * @return void
   */
  public static function get(string $path, array|callable $action, string $name = ''): void
  {
    self::add_route(['GET', 'HEAD'], $path, $action, $name);
  }

  /**
   * Registers a POST route.
   *
   * Example:
   *   Route::post('/users', [UserController::class, 'store'], 'user.store');
   *
   * @param string $path Route URI pattern
   * @param array|callable $action Controller action or callable handler
   * @param string $name Optional route name for URL generation
   * @return void
   */
  public static function post(string $path, array|callable $action, string $name = ''): void
  {
    self::add_route('POST', $path, $action, $name);
  }

  /**
   * Registers a PUT route.
   *
   * @param string $path Route URI pattern
   * @param array|callable $action Controller action or callable handler
   * @param string $name Optional route name
   * @return void
   */
  public static function put(string $path, array|callable $action, string $name = ''): void
  {
    self::add_route('PUT', $path, $action, $name);
  }

  /**
   * Registers a PATCH route.
   *
   * @param string $path Route URI pattern
   * @param array|callable $action Controller action or callable handler
   * @param string $name Optional route name
   * @return void
   */
  public static function patch(string $path, array|callable $action, string $name = ''): void
  {
    self::add_route('PATCH', $path, $action, $name);
  }

  /**
   * Registers a DELETE route.
   *
   * @param string $path Route URI pattern
   * @param array|callable $action Controller action or callable handler
   * @param string $name Optional route name
   * @return void
   */
  public static function delete(string $path, array|callable $action, string $name = ''): void
  {
    self::add_route('DELETE', $path, $action, $name);
  }

  private static function add_route(array|string $methods, string $path, array|callable $action, string $name = ''): void
  {
    $pattern = self::compile($path);
    $score = self::priority_score($path);
    $params = self::extract_params($path);

    if (is_array($methods)) {
      foreach ($methods as $method) {
        self::$routes[$method][] = [
          'path' => $path,
          'action' => $action,
          'pattern' => $pattern,
          'score' => $score,
          'params' => $params,
        ];
      }
    } else {
      self::$routes[$methods][] = [
        'path' => $path,
        'action' => $action,
        'pattern' => $pattern,
        'score' => $score,
        'params' => $params,
      ];
    }

    if (empty($name)) return;
    self::$routes_by_name[$name] = [
      'path' => $path,
      'params' => $params,
    ];
  }

  private static function compile(string $path): string
  {
    $pattern = $path;

    // wildcard: :slug* including slashes
    $pattern = preg_replace(
      '/\:(\w+)\*/',
      '(?P<$1>.+)',
      $pattern
    );

    // optional: :id? excluding slashes; the preceding slash is folded
    // into the group so /reset/:token? also matches /reset (what
    // path() generates when the param is omitted)
    $pattern = preg_replace(
      '#/\:(\w+)\?#',
      '(?:/(?P<$1>[^/]+))?',
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

  private static function priority_score(string $path): int
  {
    $score = 0;

    $segments = explode('/', trim($path, '/'));
    foreach ($segments as $segment) {
      if ($segment === '') continue;
      if (str_ends_with($segment, '*')) {
        $score += 1;
        continue;
      }
      if (str_ends_with($segment, '?')) {
        $score += 5;
        continue;
      }
      if (str_starts_with($segment, ':')) {
        $score += 10;
        continue;
      }
      $score += 20;
    }

    return $score;
  }

  private static function extract_params(string $path): array
  {
    // Same token grammar as fetch(): a greedy \w+ consumes the full name,
    // then the optional ([?*]) captures the modifier. A single pass means
    // the name can never be split by backtracking (the old three-pass
    // version let :token? also register a phantom required param "toke").
    preg_match_all('/:(\w+)([?*])?/', $path, $matches, PREG_SET_ORDER);

    $params = [];
    foreach ($matches as $match) {
      $params[$match[1]] = match ($match[2] ?? '') {
        '?' => 'optional',
        '*' => 'wildcard',
        default => 'required',
      };
    }

    return $params;
  }
}
