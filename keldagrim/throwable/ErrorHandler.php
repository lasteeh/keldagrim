<?php

namespace Keldagrim\Throwable;

use Throwable;
use ErrorException;
use Keldagrim\Core\Config;
use Keldagrim\CLI\StandardOutput;
use Keldagrim\Throwable\Exception\Routing\RouteNotFoundException;
use Keldagrim\Throwable\Exception\Controller\ActionNotFoundException;
use Keldagrim\Throwable\Exception\View\MissingTemplateException;

final class ErrorHandler
{
  private function __construct() {}

  public static function init(): void
  {
    ob_start();

    set_exception_handler([self::class, 'handle_exception']);
    set_error_handler([self::class, 'handle_error']);
    register_shutdown_function([self::class, 'handle_shutdown']);
  }

  public static function handle_exception(Throwable $e): void
  {
    self::process($e);
  }

  public static function handle_error(int $severity, string $message, string $file, string $line): bool
  {
    self::process(new ErrorException($message, 0, $severity, $file, $line));
    return true;
  }

  public static function handle_shutdown(): void
  {
    $error = error_get_last();
    if (empty($error) || !self::is_fatal($error['type'])) return;

    self::process(
      new ErrorException(
        $error['message'],
        0,
        $error['type'],
        $error['file'],
        $error['line'],
      )
    );
  }

  private static function process(Throwable $e): void
  {
    // log
    // report

    // render
    $is_cli = \PHP_SAPI === 'cli';
    $is_debug = filter_var(
      Config::get(basename(Config::APP_CONFIG_FILE, '.php') . '.debug', true),
      FILTER_VALIDATE_BOOL
    );

    while (ob_get_level() > 0) {
      ob_end_clean();
    }

    if ($is_cli) {
      self::render_cli($e, $is_debug);
    } else {
      self::render_http($e, $is_debug);
    }

    exit(1);
  }

  private static function http_status(Throwable $e): int
  {
    return match (true) {
      $e instanceof RouteNotFoundException,
      $e instanceof ActionNotFoundException,
      $e instanceof MissingTemplateException => 404,
      default => 500,
    };
  }

  private static function is_fatal(int $type): bool
  {
    return in_array($type, [
      E_ERROR,
      E_PARSE,
      E_CORE_ERROR,
      E_COMPILE_ERROR,
      E_USER_ERROR,
    ], true);
  }

  private static function render_cli(Throwable $e, bool $is_debug)
  {
    StandardOutput::write('error', $e->getMessage());

    if ($is_debug) {
      StandardOutput::write('',  $e->getFile() . '(' . $e->getLine() . ')');
      StandardOutput::write('', PHP_EOL);
      StandardOutput::write('', 'Trace: ' . PHP_EOL . $e->getTraceAsString() . PHP_EOL);
    }

    StandardOutput::write('', PHP_EOL);
  }

  private static function render_http(Throwable $e, bool $is_debug)
  {
    $status = self::http_status($e);

    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');

    $html = '';

    if ($is_debug) {
      $type = get_debug_type($e);
      $title = $e->getMessage();
      $trace = $e->getTraceAsString();

      $html = <<<HTML
        <!doctype html>
        <html>
          <head>
            <title>Error!</title>
            <style>
              * {
                padding: 0;
                margin: 0;
                box-sizing: border-box;
              }

              body {
                font-family:
                  system-ui,
                  -apple-system,
                  BlinkMacSystemFont,
                  "Segoe UI",
                  Roboto,
                  Oxygen,
                  Ubuntu,
                  Cantarell,
                  "Open Sans",
                  "Helvetica Neue",
                  sans-serif;

              }

              header {
                background-color: darkred;
                color: white;
                padding-block: 1rem;
                padding-inline: 1rem;

                & > p {
                  background-color: lightgray;
                  max-width: max-content;
                  padding-block: 0.25rem;
                  padding-inline: 0.75rem;
                  word-break: break-all;
                  color: black;
                }

                & > h1 {
                  line-height: 1;
                  font-size: clamp(1.25rem, 5vw, 2rem);
                  word-break: break-all;
                  margin-block: 1rem;
                }
              }

              pre {
                white-space: pre-wrap;
                word-wrap: break-word;
                overflow-wrap: break-word;
                background-color: #c5c6d0;
                padding-block: 1rem;
                padding-inline: 1rem;
                font-size: 1rem; 
                line-height: 2;
              }

              main {
                padding-block: 1rem;
                padding-inline: 1rem;
              }
            </style>
          </head>
          <body>
            
            <header>
              <p>$type</p>
              <h1>$title</h1>
            </header>

            <main>
              <pre>$trace</pre>
            </main>

          </body>
        </html>
      HTML;
    } else {
      $heading = $status === 404 ? 'Page not found.' : 'Something went wrong.';

      $html = <<<HTML
        <!doctype html>
        <html>
          <head>
            <title>Error!</title>
            <style>
              body {
                 font-family:
                  system-ui,
                  -apple-system,
                  BlinkMacSystemFont,
                  "Segoe UI",
                  Roboto,
                  Oxygen,
                  Ubuntu,
                  Cantarell,
                  "Open Sans",
                  "Helvetica Neue",
                  sans-serif;

               min-height: 100vh;
                align-content: center;
                width: min(768px, calc(100% - 2rem));
                margin-inline: auto;
                margin-block: 0;
              }

              h1 { line-height: 1; font-size: clamp(1.25rem, 5vw, 2rem); }
            </style>
          </head>
          <body>
            <h1>$heading</h1>
            <p>If you are the application owner, check the logs for more information.</p> 
          </body>
        </html>
      HTML;
    }

    echo $html;
  }
}
