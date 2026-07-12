<?php

namespace Keldagrim\Core;

use Keldagrim\Throwable\Exception\Controller\ActionControllerException;
use Keldagrim\Throwable\Exception\Controller\ActionNotFoundException;
use Keldagrim\Throwable\Exception\Controller\UnsafeRedirectException;
use Keldagrim\Core\Request;
use Keldagrim\Core\Response\HTMLResponse;
use Keldagrim\Core\Response\JSONResponse;
use Keldagrim\Core\Response\RedirectResponse;
use Keldagrim\Core\Trait\CanGenerateAppPath;

abstract class ActionController
{
  use CanGenerateAppPath;

  protected static array $skip_before_action = [];
  protected static array $before_action = [];
  protected static array $skip_after_action = [];
  protected static array $after_action = [];

  private Request $request;

  public function __construct(Request $request)
  {
    $this->request = $request;

    $this->setup_filter('before_action');
    $this->setup_filter('skip_before_action');
    $this->setup_filter('after_action');
    $this->setup_filter('skip_after_action');
  }

  final protected function request(): Request {
    return $this->request;
  }

  final public function execute(string $method): void
  {
    $class = static::class;

    if (!method_exists($this, $method))
      throw new ActionNotFoundException("Method [{$method}] not found on [{$class}]");

    $before_filters = static::$before_action;
    $skip_before_filters = static::$skip_before_action;

    foreach ($before_filters as $before_filter => $filter_options) {
      if (
        !$this->filter_should_skip($skip_before_filters, $before_filter, $method) &&
        $this->filter_should_apply($method, $filter_options)
      ) {
        if (!method_exists($this, $before_filter))
          throw new ActionControllerException("Method [{$before_filter}] not found on [{$class}]");

        $this->{$before_filter}();
      }
    }

    $response = $this->{$method}();

    $after_filters = static::$after_action;
    $skip_after_filters = static::$skip_after_action;

    foreach ($after_filters as $after_filter => $filter_options) {
      if (
        !$this->filter_should_skip($skip_after_filters, $after_filter, $method) &&
        $this->filter_should_apply($method, $filter_options)
      ) {
        if (!method_exists($this, $after_filter))
          throw new ActionControllerException("Method [{$after_filter}] not found on [{$class}]");

        $this->{$after_filter}();
      }
    }

    /* TODO: implement clear flash */
    /* $this->clear_flash(); */

    if (empty($response)) return;
    if ($response instanceof Response) {
      $response->send();
      return;
    } // wip if return $response->send() produce error and shutdown, FIX
  }

  private function filter_should_skip(array $skip_filters, string $filter, string $method): bool
  {
    if (!isset($skip_filters[$filter])) return false;

    $should_skip = $skip_filters[$filter] ?? null;
    if (!is_array($should_skip) || empty($should_skip)) return true;

    $should_skip_only = $should_skip['only'] ?? null;
    if (is_array($should_skip_only)) return in_array($method, $should_skip_only, true);

    $should_skip_except = $should_skip['except'] ?? null;
    if (is_array($should_skip_except)) return !in_array($method, $should_skip_except, true);

    return false;
  }

  private function filter_should_apply(string $method, array $options = []): bool
  {
    if (empty($options)) return true;

    $options_only = $options['only'] ?? null;
    if (is_array($options_only)) return in_array($method, $options_only, true);

    $options_except = $options['except'] ?? null;
    if (is_array($options_except)) return !in_array($method, $options_except, true);

    return false;
  }

  private function setup_filter(string $filter_name)
  {
    $all_filters = [];
    $class = static::class;

    while ($class && is_subclass_of($class, self::class, true)) {
      $all_filters = array_merge($class::$$filter_name, $all_filters);
      $class = get_parent_class($class);
    }

    $all_filters = array_merge(self::$$filter_name, $all_filters);

    $normalized_filters = [];
    foreach ($all_filters as $key => $value) {
      $normalized_filters[is_array($value) ? $key : $value] = is_array($value) ? $value : [];
    }

    static::$$filter_name = $normalized_filters;
  }

  final protected function params_permit(array $permit, array $input): array {
    $params = [];
    foreach ($permit as $field) {
      if (!isset($input[$field])) continue;
      $params[$field] = $input[$field];
    }
    return $params;
  }

  final protected function route_params(): array {
    return $this->request?->route_params ?? [];
  }

  final protected function route_param(string $name): ?string {
    return $this->route_params()[$name] ?? null;
  }

  final protected function html(
    string $view = '',
    array $with = [],
    int $status = 200,
    string $layout = '',
    array $flash = []
  ): HTMLResponse {
    $request_action = $this->request?->action ?? [];
    $request_controller = $request_action[0] ?? null;
    $request_method = $request_action[1] ?? null;

    if (
      empty($request_action) ||
      !is_array($request_action) ||
      count($request_action) !== 2 ||
      !is_subclass_of($request_controller, self::class) ||
      !method_exists($request_controller, $request_method)
    ) throw new ActionControllerException('Invalid request action. A valid view path must be provided.');

    if (empty($view)) {
      $controller_prefix = 'App\\Controllers\\';
      $controller_suffix = 'Controller';
      $view = (string) $request_controller;

      if (str_starts_with($view, $controller_prefix))
        $view = substr_replace($view, '', 0, strlen($controller_prefix));

      if (str_ends_with($view, $controller_suffix))
        $view = substr($view, 0, -strlen($controller_suffix));

      $view = $view . DIRECTORY_SEPARATOR . $request_method;
      $view = strtolower($view);
    }

    /* TODO: implement setting flashes  */
    /* if (!empty($flash)) { */
    /*   foreach ($flash as $type => $value) { */
    /*     $this->flash($type, $value); */
    /*   } */
    /* } */

    $action_view = new ActionView($view, $with, $layout, 'html');
    $html = $action_view->render();

    return new HTMLResponse($html, $status);
  }

  final protected function json(
    array $data,
    int $status = 200,
  ): JSONResponse {
    return new JSONResponse($data, $status);
  }

  final protected function redirect(
    string $path, 
    int $status = 302, 
    array $flash = [],
    bool $allow_external = false
  ): RedirectResponse {

    $full_path = '/';
    $has_scheme_or_authority = preg_match('~^(//|[a-z][a-z\d+.\-]*:)~i', $path) === 1;

    if (!$has_scheme_or_authority) {
      $full_path = rtrim(Config::HOME_URL(), '/') . '/' . ltrim($path, '/');
    } else {
      $target_host = parse_url($path, PHP_URL_HOST);
      $own_host = parse_url(Config::HOME_URL(), PHP_URL_HOST);

      if ($target_host !== null && strcasecmp($target_host, $own_host) !== 0) {
        if (!$allow_external) throw new UnsafeRedirectException(
          "Refuse to redirect to external host '{$target_host}'. " .
          "Pass allow_external: true if this is intentional." 
        );
      }

      $full_path = $path;
    }

    /* TODO: implement setting flashes  */
    /* if (!empty($flash)) { */
    /*   foreach ($flash as $type => $value) { */
    /*     $this->flash($type, $value); */
    /*   } */
    /* } */   
    
    return new RedirectResponse($full_path, $status);
  }
}
