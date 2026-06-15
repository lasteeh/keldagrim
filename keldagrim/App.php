<?php

namespace Keldagrim;

use Keldagrim\Throwable\ErrorHandler;
use Keldagrim\Request;
use Closure;
use Keldagrim\Throwable\Exception\Logic\RouteException;

final class App {
  public function __construct() {

    ErrorHandler::init();    
    Config::init();

  }

  public function run(): void {

    if (session_status() === PHP_SESSION_NONE) session_start();

    $request = Request::capture();
    $this->resolve($request);

  }

  private function resolve(Request $request): void {
    $action = $request->action;

    if ($action instanceof Closure) { $action(); return; }    

    if (is_array($action) && count($action) === 2) {
      [$class, $method] = $action;

      if (!class_exists($class))
        throw new RouteException("Controller class [{$class}] not found");

      if (!method_exists($class, $method))
        throw new RouteException("Method [{$method}] not found on [{$class}]");

      $controller = new $class($request);
      $controller->execute($method);

      return;
    }

    throw new RouteException("Invalid action for route [{$request->method}] \"{$request->uri}\"");
  }
}
