<?php

namespace Keldagrim\Core;

use Keldagrim\Throwable\Exception\Model\ActiveRecordException;

abstract class ActiveRecord {
  protected static $CONNECTION = '';
  protected static $TABLE = '';
  protected static $PRIMARY_KEY = 'id';

  protected static $skip_before_validate = [];
  protected static $before_validate = [];
  protected static $skip_after_validate = [];
  protected static $after_validate = [];

  protected static $validate = [];
  protected static $skip_validate = [];

  protected $validations = [];

  protected static $skip_before_update = [];
  protected static $before_update = [];
  protected static $skip_after_update = [];
  protected static $after_update = [];

  protected static $skip_before_save = [];
  protected static $before_save = [];
  protected static $skip_after_save = [];
  protected static $after_save = [];

  protected static $skip_before_create = [];
  protected static $before_create = [];
  protected static $skip_after_create = [];
  protected static $after_create = [];

  protected static $skip_before_destroy = [];
  protected static $before_destroy = [];
  protected static $skip_after_destroy = [];
  protected static $after_destroy = [];

  private array $schema = [];
  private array $attributes = [];
  private array $original = [];

  private static array $resolved_callbacks = [];
  private static array $schema_cache = [];

  private const CASTABLE_TYPES = ['int', 'float', 'string', 'bool'];

  public function __construct(array $attributes = []) {

    if (!isset(self::$schema_cache[static::class])) {

      static::connection();
      static::table_name();
      static::primary_key();

      $schema = [];
      $ref = new \ReflectionClass(static::class);
      foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
        if ($prop->isStatic()) continue; 

        if ($prop->isReadOnly())
          throw new ActiveRecordException(
            static::class . ": attribute '\${$prop->getName()}' must not be readonly."
          );

        $type = $prop->getType();

        if ($type === null) {
          $schema[$prop->getName()] = ['type' => null, 'nullable' => true];
          continue;
        }

        if (!$type instanceof \ReflectionNamedType || !in_array($type->getName(), self::CASTABLE_TYPES, true))
          throw new ActiveRecordException(
            static::class . ": attribute '\${$prop->getName()}' has unsupported type " .
            "'{$type}'. Use int, float, string, bool (optionally nullable), or no type."
          );

        $schema[$prop->getName()] = [
          'type' => $type->getName(),
          'nullable' => $type->allowsNull(),
        ];
      }

      self::$schema_cache[static::class] = $schema;
    }

    $this->schema = self::$schema_cache[static::class];

    foreach (array_keys($this->schema) as $name) unset($this->{$name});

    foreach ($attributes as $name => $value) {
      $this->assert_attribute($name);
      $this->attributes[$name] = $this->cast_attribute($name, $value);
    }
    $this->original = $this->attributes;
  }

  private function cast_attribute(string $name, mixed $value): mixed {
    ['type' => $type, 'nullable' => $nullable] = $this->schema[$name];

    if ($type === null) return $value; // untyped: pass through

    if ($value === null) {
      if ($nullable) return null;
      throw new ActiveRecordException(
        static::class . ": '{$name}' ({$type}) cannot be null."
      );
    }

    switch ($type) {
      case 'int':
        if (is_int($value)) return $value;
        if (is_float($value) && $value === floor($value) && !is_infinite($value)) return (int) $value;
        if (is_string($value) && preg_match('/^-?\d+$/', trim($value))) return (int) trim($value);
        break;

      case 'float':
        if (is_float($value)) return $value;
        if (is_int($value)) return (float) $value;
        if (is_string($value) && is_numeric(trim($value))) return (float) trim($value);
        break;

      case 'string':
        if (is_string($value)) return $value;
        if (is_int($value) || is_float($value)) return (string) $value;
        if ($value instanceof \Stringable) return (string) $value;
        break;

      case 'bool':
        if (is_bool($value)) return $value;
        if ($value === 1 || $value === '1') return true;   // MySQL tinyint / PG 't'-style handled below
        if ($value === 0 || $value === '0') return false;
        if ($value === 't' || $value === 'true') return true;   // pdo_pgsql boolean columns
        if ($value === 'f' || $value === 'false') return false;
        break;
    }

    throw new ActiveRecordException(
      static::class . ": cannot cast " . get_debug_type($value) .
      " " . var_export(is_scalar($value) ? $value : null, true) .
      " to {$type} for '{$name}'."
    );
  }

  private function assert_attribute(string $name): void {
    if (!isset($this->schema[$name]))
      throw new ActiveRecordException(
        static::class . ": '{$name}' is not a declared attribute."
      );
  }

  public function __set(string $name, $value): void {
    $this->assert_attribute($name);
    $this->attributes[$name] = $this->cast_attribute($name, $value);
  }

  public function __get(string $name) {
    $this->assert_attribute($name);
    if (array_key_exists($name, $this->attributes)) return $this->attributes[$name];

    if ($this->schema[$name]['type'] !== null && !$this->schema[$name]['nullable'])
      throw new ActiveRecordException(
        static::class . ": '{$name}' has no value and is not nullable."
      );

    return null;
  }

  public function __isset(string $name): bool {
    return isset($this->schema[$name]) && array_key_exists($name, $this->attributes);
  }

  public function __unset(string $name): void {
    $this->assert_attribute($name);
    unset($this->attributes[$name]);
  }

  final public function old(string $name): mixed {
    $this->assert_attribute($name);
    return array_key_exists($name, $this->original) ? $this->original[$name] : null;
  }

  final public function is_dirty(?string $name = null): bool {
    if ($name !== null) {
      $this->assert_attribute($name);
      return ($this->attributes[$name] ?? null) !== ($this->original[$name] ?? null);
    }
    return $this->attributes !== $this->original;
  }

  final protected function run_callbacks(string $event): bool {
    foreach (static::resolve_callbacks($event) as $callback)
      if ($this->{$callback}() === false) return false;
    
    return true;
  }

  private static function resolve_callbacks(string $event): array {
    $key = static::class . ':' .$event;
    if (isset(self::$resolved_callbacks[$key])) return self::$resolved_callbacks[$key];

    $list = self::collect_list($event);
    $skips = self::collect_list('skip_'.$event);

    $list = array_values(array_unique(
      array_filter($list, fn($cb) => !in_array($cb, $skips, true))
    ));

    foreach ($list as $cb) {
      if (!is_string($cb) || !method_exists(static::class, $cb))
        throw new ActiveRecordException(
          static::class . ": invalid {$event} callback " . var_export($cb, true) . '.'
        );

      $method = new \ReflectionMethod(static::class, $cb);
      if ($method->isPrivate() && $method->getDeclaringClass()->getName() !== self::class)
        throw new ActiveRecordException(
          static::class . ": callback '{$cb}' must be protected or public."
        );
      if ($method->isStatic())
        throw new ActiveRecordException(
          static::class . ": callback '{$cb}' must not be static."
        );
    }

    return self::$resolved_callbacks[$key] = $list;
  }

  private static function collect_list(string $property): array {
    $chain = [];
    for ($class = static::class; $class !== false; $class = get_parent_class($class)) {
      if (!property_exists($class, $property)) continue;
      $declared_in = (new \ReflectionProperty($class, $property))->getDeclaringClass()->getName();
      if ($declared_in === $class) $chain[] = $class::$$property;
    }
    $chain = array_reverse($chain);
    return $chain === [] ? [] : array_merge(...$chain);
  }

  final public static function connection() {
    if (!is_string(static::$CONNECTION) || empty(static::$CONNECTION))
      throw new ActiveRecordException(static::class . ': Please assign a valid connection.');

    $connection = Config::database('connections.' . static::$CONNECTION);
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
    if (empty($word)) return $word;

    $vowels = ['a', 'e', 'i', 'o', 'u'];
    $es_consonants = ['x', 'z'];

    $ref = strtolower($word);
    $len = strlen($ref);
    $last = $ref[$len - 1];
    $second = $len >= 2 ? $ref[$len - 2] : '';

    if ($last === 's') return $word;

    if (in_array($last, $es_consonants)) return $word . 'es';

    if ($last === 'h' && ($second === 'c' || $second === 's'))
      return $word . 'es';

    if ($last === 'y' && !in_array($second, $vowels, true))
      return substr($word, 0, -1) . 'ies';

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
