<?php

namespace Keldagrim\Core;

use Keldagrim\Throwable\Exception\Database\DatabaseException;

final class Database {
  private \PDO $pdo;

  private function __construct(array $config) {
    $driver = $config['driver'] ?? null;
    $host = $config['host'] ?? null;
    $database = $config['database'] ?? null;
    $username = $config['username'] ?? null;
    $password = $config['password'] ?? null;

    try {
      $pdo = new \PDO("{$driver}:host={$host};dbname={$database}", $username, $password);
      $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
      $this->pdo = $pdo;
    } catch (\PDOException $e) {
      throw new DatabaseException($e->getMessage(), 0, $e);
    }
  }
  
  public static function connect(array $config): \PDO {
    $db = new self($config);
    return $db->pdo;
  }
}
