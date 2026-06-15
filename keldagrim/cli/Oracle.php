<?php

namespace Keldagrim\CLI;

use Keldagrim\Throwable\ErrorHandler;
use Keldagrim\Config;
use Keldagrim\Throwable\Exception\KeldagrimRuntimeException;
use Keldagrim\CLI\StandardOutput;
use Keldagrim\CLI\OptionsParser;

final class Oracle {
  public function __construct() {
    ErrorHandler::init();
    Config::init();

    if (PHP_SAPI !== 'cli') 
      throw new KeldagrimRuntimeException('CLI environment required.');
  }

  public function run(array $args): void {
    $opts = new OptionsParser($args);
    $command = $opts->command();

    switch ($command) {
      case 'server':
        $host = $opts->get('host', 'localhost');
        $port = $opts->get('port', '6543');
        $server_dir = 
          Config::HOME_DIR() . DIRECTORY_SEPARATOR . Config::PUBLIC_DIR; 
        $server_file = 
          Config::HOME_DIR() . DIRECTORY_SEPARATOR . 
          Config::FRAMEWORK_CORE_DIR . DIRECTORY_SEPARATOR .
          Config::SERVER_CONFIG_FILE;

        StandardOutput::write(
          'info', 
          "Starting the Oracle server on http://{$host}:{$port}..."
        );
        StandardOutput::write('info', 'Press Ctrl+C to stop');

        passthru("php -S {$host}:{$port} -t {$server_dir} {$server_file}");
        break;
    }
  }
}
