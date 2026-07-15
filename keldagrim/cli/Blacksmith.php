<?php

namespace Keldagrim\CLI;

use Keldagrim\Throwable\ErrorHandler;
use Keldagrim\Core\Config;
use Keldagrim\Throwable\Exception\CLI\ConsoleException;
use Keldagrim\CLI\OptionsParser;
use Keldagrim\Support\Path;
use Keldagrim\Support\FileSystem;

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
        if (empty($connection) || !preg_match('/^[a-zA-Z_-]+$/', $connection)) {
          StandardOutput::write('', 'blacksmith:');
          StandardOutput::write('', 'A valid "--connection" option is required.');
          exit(1);
        }       

        $db_connection = Config::database("connection.{$connection}");
        if ($db_connection === null) {
          StandardOutput::write('', 'blacksmith:');
          StandardOutput::write('', 'Database connection not found in database config file.');
          exit(1);
        }

        $date = new \DateTimeImmutable;
        $stamp = $date->format('YmdHisv');
        $migration_dir = Config::MIGRATION_DIR . DIRECTORY_SEPARATOR . $connection . DIRECTORY_SEPARATOR;
        $filename = $stamp . '_' . $file_name . '.sql';

        FileSystem::create_dir(Path::database($migration_dir));
        FileSystem::write_file(Path::database($migration_dir . $filename));
        break;
    }
  }
}
