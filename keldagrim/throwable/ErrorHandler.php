<?php

namespace Keldagrim\Throwable;

use Throwable;
use ErrorException;
use DateTimeImmutable;
use DateTimeInterface;
use Keldagrim\Config;

final class ErrorHandler {
  private function __construct() {

  }

  public static function init(): void {
    set_exception_handler([self::class, 'handle_exception']);
    set_error_handler([self::class, 'handle_error']);
    register_shutdown_function([self::class, 'handle_shutdown']);
  }

  public static function handle_exception(Throwable $e): void {
    self::process($e);
  }

  public static function handle_error(int $severity, string $message, string $file, string $line): bool {
    self::process(new ErrorException($message, 0, $severity, $file, $line));
    return true;
  }

  public static function handle_shutdown(): void {
    // wip
  }

  private static function process(Throwable $e): void {
    // log
    // report

    // render
    $is_cli = PHP_SAPI === 'cli';
    $is_debug = filter_var(
      Config::get(basename(Config::APP_CONFIG_FILE, '.php') . '.debug', true), 
      FILTER_VALIDATE_BOOL
    );

    ob_clean();

    if ($is_cli) {
      self::render_cli($e, $is_debug);
    } else {
      self::render_http($e, $is_debug);
    }
  }

  private static function is_fatal(int $type): bool {
    return in_array($type, [
      E_ERROR,
      E_PARSE,
      E_CORE_ERROR,
      E_COMPILE_ERROR,
      E_USER_ERROR,
    ], true);
  }

  private static function render_cli(Throwable $e, bool $is_debug) {
    fwrite(STDERR, '\n' . self::timestamp() . ' [ERROR] '. $e->getMessage() .' \n');
    
    if ($is_debug) {
      fwrite(STDERR, 'File: ' . $e->getFile() . '\n');
      fwrite(STDERR, 'Line: ' . $e->getLine() . '\n');
      fwrite(STDERR, 'Trace: ' . $e->getTraceAsString() . '\n');
    }

    fwrite(STDERR, '\n');
    exit(1);
  }

  private static function render_http(Throwable $e, bool $is_debug) {
    http_response_code(500);
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
            <h1>Something went wrong.</h1>
            <p>If you are the application owner, check the logs for more information.</p> 
          </body>
        </html>
      HTML;
    }

    echo $html;
  }

  private static function timestamp(): string {
    return (new DateTimeImmutable())->format(DateTimeInterface::ATOM);
  }
}
