<?php

namespace Keldagrim\Core;

use Keldagrim\Throwable\Exception\Model\ActiveRecordException;

abstract class ActiveRecord {
  protected static $CONNECTION = '';
  protected static $TABLE = '';
  protected static $PRIMARY_KEY = 'id';

  private array $schema = [];
  private array $attributes = [];
  private array $original = [];

  public function __construct(array $attributes = []) {
    static::connection();
    static::table_name();
    static::primary_key();

    $ref = new \ReflectionClass(static::class);
    foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
      if ($prop->isStatic()) continue;
      $this->schema[$prop->getName()] = true;
      unset($this->{$prop->getName()});
    }

    foreach ($attributes as $name => $value) {
      $this->assert_attribute($name);
      $this->attributes[$name] = $value;
    }
    $this->original = $this->attributes;
  }

  private function assert_attribute(string $name): void {
    if (!isset($this->schema[$name]))
      throw new ActiveRecordException(
        static::class . ": '{$name}' is not a declared attribute."
      );
  }

  public function __set(string $name, $value): void {
    $this->assert_attribute($name);
    $this->attributes[$name] = $value;
  }

  public function __get(string $name) {
    $this->assert_attribute($name);
    return array_key_exists($name, $this->attributes)
      ? $this->attributes[$name]
      : null;
  }

  public function __isset(string $name): bool {
    return isset($this->schema[$name]) && array_key_exists($name, $this->attributes);
  }

  public function old(string $name): mixed {
    $this->assert_attribute($name);
    return array_key_exists($name, $this->original) ? $this->original[$name] : null;
  }

  public function is_dirty(?string $name = null): bool {
    if ($name !== null) {
      $this->assert_attribute($name);
      return ($this->attributes[$name] ?? null) !== ($this->original[$name] ?? null);
    }
    return $this->attributes !== $this->original;
  }

  final public static function connection() {
    if (!is_string(static::$CONNECTION) || empty(static::$CONNECTION))
      throw new ActiveRecordException(static::class . ': Please assign a valid connection.');

    $connection = Config::database('connection.' . static::$CONNECTION);
    if (empty($connection))
      throw new ActiveRecordException(static::class . ': Connection not found in database config.');

    return static::$CONNECTION;
  }

  final public static function table_name(): string {
    if (!is_string(static::$TABLE))
      throw new ActiveRecordException(static::class . ': Invalid table name.');

    if (!empty(static::$TABLE)) return static::$TABLE;
    $short = substr(static::class, strrpos(static::class, '\\') + 1);
    $snake = strtolower(preg_replace(
      ['/([a-z\d])([A-Z])/', '/([A-Z]+)([A-Z][a-z])/'],
      ['$1_$2', '$1_$2'],
      $short
    ));
    return static::pluralize($snake); 
  }

  private static function pluralize(string $word): string {
    $last_letter = strtolower($word[strlen($word) - 1]);
    if ($last_letter === 'y') return substr($word, 0, -1) . 'ies';
    if ($last_letter === 's') return $word;
    return $word . 's';
  }

  final public static function primary_key(): false|array|string {
    $pk = static::$PRIMARY_KEY;

    if (is_string($pk)) {
      if (!property_exists(static::class, $pk))
        throw new ActiveRecordException(static::class . ': ' . $pk . ' is not a property.');
    }

    if (is_array($pk)) {
      foreach ($pk as $k) {
        if (!is_string($k) || empty($k) || !property_exists(static::class, $k))
        throw new ActiveRecordException(static::class . ': Unable to set an invalid property to primary key.');
      }
    }

    return static::$PRIMARY_KEY;
  }
}
