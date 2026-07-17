<?php

namespace Keldagrim\Core;

use Keldagrim\Throwable\Exception\Database\DatabaseException;

final class Database {
  private static array $pool = [];

  private function __construct() {
  }
  
  public static function connect(string $name): \PDO {
    if (isset(self::$pool[$name])) return self::$pool[$name];

    $config = Config::database("connections.{$name}");
    if (empty($config))
      throw new DatabaseException("Connection '{$name}' not found in database config.");

    foreach (['driver', 'host', 'database', 'username'] as $key) {
      if (!isset($config[$key]) || $config[$key] === '')
        throw new DatabaseException("Connection '{$name}': missing '{$key}' in database config.");
    }

    $driver = $config['driver'];
    $host = $config['host'];
    $database = $config['database'];
    $username = $config['username'];
    $password = $config['password'] ?? '';
    $port = $config['port'] ?? null;
    $charset = $config['charset'] ?? 'utf8mb4';

    $dsn = match ($driver) {
      'mysql' => "mysql:host={$host};dbname={$database}"
              . (!empty($port) ? ";port={$port}" : '')
              . (';charset=' . $charset),
      'pgsql' => "pgsql:host={$host};dbname={$database}"
              . (!empty($port) ? ";port={$port}" : ''),
      default => throw new DatabaseException("Connection '{$name}': unsupported driver '{$driver}'."),
    };

    try {
      $pdo = new \PDO($dsn, $username, $password ?? '', [
        \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_EMULATE_PREPARES   => false,
      ]);
      return self::$pool[$name] = $pdo;
    } catch (\PDOException $e) {
      throw new DatabaseException("Connection '{$name}': " . $e->getMessage(), 0, $e);
    }
    
  }
}
