<?php

namespace Keldagrim\Core;

use Keldagrim\Throwable\ErrorHandler;
use Keldagrim\Core\Request;
use Closure;
use Keldagrim\Throwable\Exception\Routing\RouteException;
use Keldagrim\Throwable\Exception\Controller\ActionNotFoundException;

final class App
{
  public function __construct()
  {

    ErrorHandler::init();
    Config::init();
  }

  public function run(): void
  {

    if (session_status() === PHP_SESSION_NONE) session_start();

    $request = Request::capture();
    $this->resolve($request);
  }

  private function resolve(Request $request): void
  {
    $lock_directory = realpath(Config::HOME_DIR() . DIRECTORY_SEPARATOR . Config::PUBLIC_DIR);
    $static_file = realpath($lock_directory . DIRECTORY_SEPARATOR . $request->uri);

    if (
      !empty($static_file) && 
      is_file($static_file) && 
      strpos($static_file, $lock_directory . DIRECTORY_SEPARATOR) === 0
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
      
    $action = $request->action;

    if ($action instanceof Closure) {
      $action($request);
      return;
    }

    if (is_array($action) && count($action) === 2) {
      [$class, $method] = $action;

      if (!class_exists($class))
        throw new ActionNotFoundException("Controller class [{$class}] not found");

      if (!method_exists($class, $method))
        throw new ActionNotFoundException("Method [{$method}] not found on [{$class}]");

      $controller = new $class($request);
      $controller->execute($method);

      return;
    }

    throw new RouteException("Invalid action for route [{$request->method}] \"{$request->uri}\"");
  }
}
