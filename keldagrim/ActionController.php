<?php 

namespace Keldagrim;

use Keldagrim\Throwable\Exception\Logic\ActionControllerException;
use Keldagrim\Request;

abstract class ActionController {
  protected static array $skip_before_action = [];
  protected static array $before_action = [];
  protected static array $skip_after_action = [];
  protected static array $after_action = [];

  public function __construct() {
    $this->setup_filter('before_action');
    $this->setup_filter('skip_before_action');
    $this->setup_filter('after_action');
    $this->setup_filter('skip_after_action');  
  }

  public function execute(string $method, Request $request): void {
    $class = static::class;

    if (!method_exists($this, $method)) 
      throw new ActionControllerException("Method [{$method}] not found on [{$class}]");

    $before_filters = static::$before_action;
    $skip_before_filters = static::$skip_before_action;

    foreach ($before_filters as $before_filter => $filter_options) {
      if (
        !$this->filter_should_skip($skip_before_filters, $before_filter, $method) &&
        $this->filter_should_apply($method, $filter_options)
      ) {
        if (!method_exists($this, $before_filter))
          throw new ActionControllerException("Method [{$before_filter}] not found on [{$class}]");

        $this->{$before_filter}($request);
      }
    }

    $response = $this->{$method}($request);   

    $after_filters = static::$after_action;
    $skip_after_filters = static::$skip_after_action;

    foreach ($after_filters as $after_filter => $filter_options) {
      if (
        !$this->filter_should_skip($skip_after_filters, $after_filter, $method) &&
        $this->filter_should_apply($method, $filter_options)
      ) {
        if (!method_exists($this, $after_filter))
          throw new ActionControllerException("Method [{$after_filter}] not found on [{$class}]");

        $this->{$after_filter}($request);
      }
    }

    if (empty($response)) return;
    if ($response instanceof Response) { $response->send(); return; } // wip if return $response->send() produce error and shutdown, FIX
  }

  private function filter_should_skip(array $skip_filters, string $filter, string $method): bool {
    if (!isset($skip_filters[$filter])) return false;
    
    $should_skip = $skip_filters[$filter] ?? null;
    if (!is_array($should_skip) || empty($should_skip)) return true; 

    $should_skip_only = $should_skip['only'] ?? null;
    if (is_array($should_skip_only)) return in_array($method, $should_skip_only, true);
    
    $should_skip_except = $should_skip['except'] ?? null;
    if (is_array($should_skip_except)) return !in_array($method, $should_skip_except, true); 

    return false;
  }

  private function filter_should_apply(string $method, array $options = []): bool {
    if (empty($options)) return true;

    $options_only = $options['only'] ?? null;
    if (is_array($options_only)) return in_array($method, $options_only, true);

    $options_except = $options['except'] ?? null;
    if (is_array($options_except)) return !in_array($method, $options_except, true);

    return false;
  }

  final private function setup_filter(string $filter_name) {
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
}
