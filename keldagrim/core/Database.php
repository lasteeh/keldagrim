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

    $driver = $config['driver'] ?? null;
    $host = $config['host'] ?? null;
    $database = $config['database'] ?? null;
    $username = $config['username'] ?? null;
    $password = $config['password'] ?? null;

    try {
      $pdo = new \PDO("{$driver}:host={$host};dbname={$database}", $username, $password);
      $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
      return self::$pool[$name] = $pdo;
    } catch (\PDOException $e) {
      throw new DatabaseException($e->getMessage(), 0, $e);
    }
    
  }
}
