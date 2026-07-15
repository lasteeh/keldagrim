<?php

namespace Keldagrim\CLI;

use Keldagrim\Throwable\ErrorHandler;
use Keldagrim\Core\Config;
use Keldagrim\Throwable\Exception\CLI\ConsoleException;
use Keldagrim\CLI\OptionsParser;
use Keldagrim\Support\Path;
use Keldagrim\Support\FileSystem;
use Keldagrim\Core\Database;

final class Mason {
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
      case 'migrate':
        $errors = [];
        $connection = $opts->get('connection');

        if (empty($connection)) 
          $errors[] = 'A valid "--connection" option is required.';

        $valid_connection = [];
        if (!empty($connection)) {
          $valid_connection = Config::database("connection.{$connection}");
          if (empty($valid_connection))
            $errors[] = 'Database connection is not found or empty in database config file.';
        }

        if (!empty($errors)) {
          StandardOutput::write('', 'mason:migrate'); 
          foreach ($errors as $error) StandardOutput::write('', $error); 
          exit(1);
        }

        /* connect to db */
        $migrations_table = 'keldagrim_migrations';
        $db = Database::connect($valid_connection);
        $driver = $db->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'pgsql') {
          $sql = <<<SQL
            CREATE TABLE IF NOT EXISTS {$migrations_table} (
              id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
              migration VARCHAR(255) NOT NULL UNIQUE,
              batch INT NOT NULL,
              executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
          SQL;
        } else { /* mysql covers MariaDB too — pdo_mysql reports 'mysql' for both */
          $sql = <<<SQL
            CREATE TABLE IF NOT EXISTS {$migrations_table} (
              id INT AUTO_INCREMENT PRIMARY KEY,
              migration VARCHAR(255) NOT NULL UNIQUE,
              batch INT NOT NULL,
              executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
          SQL;
        }
        $db->exec($sql);

        /* fetch executed migrations */
        $sql = <<<SQL
                SELECT migration FROM $migrations_table;
              SQL;
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        
        $executed = [];
        foreach ($rows as $migration) $executed[$migration] = true;

        $stmt = $db->query("SELECT COALESCE(MAX(batch), 0) + 1 FROM {$migrations_table}");
        $batch = (int) $stmt->fetchColumn();

        /* locate migration dir */
        $migration_path = DIRECTORY_SEPARATOR . "migrations" . DIRECTORY_SEPARATOR . $connection;
        $migration_dir = Path::database($migration_path);
        if (!is_dir($migration_dir))
          throw new ConsoleException('Migration directory not found: ' . $migration_path);

        $files = glob($migration_dir . DIRECTORY_SEPARATOR . '*.sql');
        if ($files === false) 
          throw new ConsoleException("Failed fetching migration files from {$migration_path}");

        if (is_array($files) && empty($files)) {
          StandardOutput::write('','No migration files found.');
          exit;
        }

        sort($files, SORT_STRING);

        if ($driver !== 'pgsql')
          StandardOutput::write('', 
            'Note: MySQL/MariaDB auto-commits DDL statements (CREATE/ALTER/DROP). ' . 
            'If a migration fails partway through, statements that already ran cannot be rolled back automatically.'
          );

        $confirmation = readline('This action cannot be undone. Continue migration? y/N: ');
        if (!is_string($confirmation) || strtolower($confirmation) !== 'y') {
          StandardOutput::write('','Migration aborted.');
          exit;
        }

        foreach ($files as $file) {
          $name = basename($file);
          if (isset($executed[$name])) continue;

          $sql = file_get_contents($file);
          if ($sql === false) throw new ConsoleException("Cannot read migration: {$name}");

          try {
            $db->beginTransaction();

            $db->exec($sql);
            $stmt = $db->prepare("INSERT INTO {$migrations_table} (migration, batch) VALUES (?,?)");
            $stmt->execute([$name, $batch]);

            if ($db->inTransaction()) $db->commit();
            StandardOutput::write('',"Migrated: {$name}");

          } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw new ConsoleException("Migration {$name} failed: " . $e->getMessage(), 0, $e);
          }
        }

        break;
    }
  }
}
