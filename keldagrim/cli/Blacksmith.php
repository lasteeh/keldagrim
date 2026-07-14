<?php

namespace Keldagrim\CLI;

use Keldagrim\Throwable\ErrorHandler;
use Keldagrim\Core\Config;
use Keldagrim\Throwable\Exception\CLI\ConsoleException;
use Keldagrim\CLI\OptionsParser;
use Keldagrim\Support\Paths;

final class Blacksmith {
  public function __construct() {
    ErrorHandler::init();
    Config::init();

    if (\PHP_SAPI !== 'cli')
      throw new ConsoleException('CLI environment required.');
  }

  public function run(array $args): void {
    $opts = new OptionsParser($args);
    $command = $opts->command();

    switch ($command) {
      case 'forge':
        $this->forge($opts);
        break;
    }
  }

  private function forge(OptionsParser $opts): void {
    $type = $opts->get('type');
    $valid_types = [
      'migration',
      'controller',
      'model',
      'view',
    ];

    if (empty($type) || !in_array($type, $valid_types, true)) {
      StandardOutput::write('', 'blacksmith:');
      StandardOutput::write('', 'A valid "--type" option is required.');
      exit(1);
    }

    $file_name = $opts->get('name');
    if (empty($file_name)) {
      StandardOutput::write('', 'blacksmith:');
      StandardOutput::write('', 'A valid "--name" option is required.');
      exit(1);
    }

    if (!preg_match('/^[a-zA-Z_]+$/', $file_name)) {
      StandardOutput::write('', 'blacksmith:');
      StandardOutput::write('', 'Invalid "--name". Only letters and underscores are allowed.');
      exit(1);
    }
    
    switch ($type) {
      case 'migration':

        $connection = $opts->get('connection');
        if (empty($connection) || !preg_match('/^[a-zA-Z_]+$/', $connection)) {
          StandardOutput::write('', 'blacksmith:');
          StandardOutput::write('', 'A valid "--connection" option is required.');
          exit(1);
        }       

        /* TODO: check Config::database("connection.{$connection}"); */

        $date = new \DateTimeImmutable;
        $stamp = $date->format('YmdHisv');
        $filename = Paths::database(
          Config::MIGRATION_DIR . DIRECTORY_SEPARATOR . $connection . DIRECTORY_SEPARATOR .
          $stamp . '_' . $file_name . '.sql'
        );

var_dump($filename);
        break;
    }
  }
}
