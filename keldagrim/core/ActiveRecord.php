<?php

namespace Keldagrim\Core;

use Keldagrim\Throwable\Exception\Model\ActiveRecordException;

abstract class ActiveRecord {
  protected static $CONNECTION = '';
  protected static $TABLE = '';
  protected static $PRIMARY_KEY = 'id';

  /**
   * Attributes that exist on the model (validated, castable, dirty-tracked)
   * but are never written to or read from the database.
   * e.g. ['password_confirmation', 'terms_accepted']
   */
  protected static $TRANSIENT = [];

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
  private array $errors = [];
  private bool $exists = false;

  private static array $resolved_callbacks = [];
  private static array $schema_cache = [];

  private const CASTABLE_TYPES = ['int', 'float', 'string', 'bool'];
  public const BASE = '_base';

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
          $schema[$prop->getName()] = ['type' => null, 'nullable' => true, 'persisted' => true];
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
          'persisted' => true,
        ];
      }

      $transient = static::collect_list('TRANSIENT');
      foreach ($transient as $name) {
        if (!is_string($name) || !isset($schema[$name]))
          throw new ActiveRecordException(
            static::class . ": \$TRANSIENT entry " . var_export($name, true) .
            " is not a declared attribute."
          );
        $schema[$name]['persisted'] = false;
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

  final public function exists(): bool { return $this->exists; }

  final protected static function db(): \PDO {
    return Database::connect(static::$CONNECTION);
  }

  final protected static function hydrate(array $row): static {
    $instance = new static($row);
    $instance->exists = true;
    return $instance;
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

  final public static function persisted_attributes(): array {
    $schema = self::$schema_cache[static::class] 
      ?? throw new ActiveRecordException(static::class . ": schema not initialized; construct an instance first.");
    return array_keys(array_filter($schema, fn($meta) => $meta['persisted']));
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

  final public function add_error(string $message, string $attribute = self::BASE): void {
    $this->errors[$attribute][] = $message;
  }

  final public function errors(?string $attribute = null): array {
    if ($attribute !== null) return $this->errors[$attribute] ?? [];
    return $this->errors;
  }

  final public function has_errors(?string $attribute = null): bool {
    if ($attribute !== null) return !empty($this->errors[$attribute]);
    return $this->errors !== [];
  }

  final public function error_messages(): array {
    $out = [];
    foreach ($this->errors as $attribute => $messages) {
      foreach ($messages as $message)
        $out[] = $attribute === self::BASE ? $message : "{$attribute} {$message}";
    }
    return $out;
  }

  final protected function clear_errors(): void { $this->errors = []; }

  final public function validate(?array $only = null): bool {
    $this->clear_errors();

    foreach ($this->validations as $attribute => $rules) {
      if ($only !== null && !in_array($attribute, $only, true)) continue;
      $this->assert_attribute($attribute);
      foreach ($rules as $rule => $options) {
        $this->apply_rule($attribute, $rule, $options);
      }
    }

    if (!$this->run_callbacks('validate')) return false;
    return !$this->has_errors();
  }

  private function apply_rule(string $attribute, string $rule, mixed $options): void {
    $value = $this->attributes[$attribute] ?? null;
    $blank = $value === null || (is_string($value) && trim($value) === '');

    switch ($rule) {
      case 'presence':
        if ($options === true && $blank) $this->add_error("can't be blank", $attribute);
        break;

      case 'length':
        if ($blank) break;
        $len = mb_strlen((string) $value);
        if (isset($options['minimum']) && $len < $options['minimum'])
          $this->add_error("is too short (minimum {$options['minimum']} characters)", $attribute);
        if (isset($options['maximum']) && $len > $options['maximum'])
          $this->add_error("is too long (maximum {$options['maximum']} characters)", $attribute);
        break;

      case 'numericality':
        if ($blank) break;
        if (!is_numeric($value)) {
          $this->add_error('is not a number', $attribute);
          break;
        }
        if (($options['only_integer'] ?? false) && !preg_match('/^-?\d+$/', (string) $value))
          $this->add_error('must be an integer', $attribute);
        if (isset($options['greater_than']) && !($value > $options['greater_than']))
          $this->add_error("must be greater than {$options['greater_than']}", $attribute);
        if (isset($options['less_than']) && !($value < $options['less_than']))
          $this->add_error("must be less than {$options['less_than']}", $attribute);
        break;

      case 'format':
        if ($blank) break;
        if (!is_string($value) || !preg_match($options['with'], $value))
          $this->add_error($options['message'] ?? 'is invalid', $attribute);
        break;

      case 'confirmation':
        if ($options !== true || $blank) break;
        $confirm = "{$attribute}_confirmation";
        $confirm_value = isset($this->schema[$confirm]) ? ($this->attributes[$confirm] ?? null) : null;
        if ($value !== $confirm_value)
          $this->add_error("doesn't match confirmation", $attribute);
        break;

      case 'uniqueness':
        if ($options !== true || $blank) break;

        $match = static::find_by([$attribute => $value]);
        if ($match === null) break;

        $pk = static::primary_key();
        if ($this->exists && is_string($pk) 
            && ($this->original[$pk] ?? null) === ($match->attributes[$pk] ?? null))
          break;

        $this->add_error('has already been taken', $attribute);
        break;

      default:
        throw new ActiveRecordException(
          static::class . ": unknown validation rule '{$rule}' on '{$attribute}'."
        );     
    }
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

  final public static function find(int|string $id): ?static {
    $pk = static::primary_key();
    if (!is_string($pk))
      throw new ActiveRecordException(
        static::class . ": find() requires a single-column primary key. use find_by() instead."
      );
    return static::find_by([$pk => $id]);
  }

  final public static function find_by(array $conditions): ?static {
    new static();

    [$where, $binds] = static::build_where($conditions);
    $table = static::table_name();

    $columns = implode(', ', static::persisted_attributes());   
    $stmt = static::db()->prepare("SELECT {$columns} FROM {$table} {$where} LIMIT 1");
    $stmt->execute($binds);
    $row = $stmt->fetch(); 

    return $row === false ? null : static::hydrate($row);
  }

  /**
   * Build "WHERE a = :w0 AND b IS NULL" from ['a' => 1, 'b' => null].
   * Keys are validated against persisted attributes; values are parameterized.
   * @return array{string, array<string, mixed>}
   */
  private static function build_where(array $conditions): array {
    if ($conditions === [])
      throw new ActiveRecordException(
        static::class . ": find_by requires at least one condition"
      );

    $schema = self::$schema_cache[static::class];
    $parts = [];
    $binds = [];
    $i = 0;

    foreach ($conditions as $column => $value) {
      if (!is_string($column) || !isset($schema[$column]) || !$schema[$column]['persisted'])
        throw new ActiveRecordException(
          static::class . ': invalid condition column ' . var_export($column, true) . '.'
        );

      if ($value === null) { $parts[] = "{$column} IS NULL"; continue; }
      if (!is_scalar($value))
        throw new ActiveRecordException(
          static::class . ": condition '{$column}' must be scalar or null, got " . get_debug_type($value) . '.'
        );

      $placeholder = ':w' . $i++;
      $parts[] = "{$column} = {$placeholder}";
      $binds[$placeholder] = is_bool($value) ? (int) $value : $value;
    }

    return ['WHERE ' . implode(' AND ', $parts), $binds];
  }

  final public function save(): bool {
    if (!$this->run_callbacks('before_validate')) return false;
    if (!$this->validate()) return false;
    if (!$this->run_callbacks('after_validate')) return false;

    if (!$this->run_callbacks('before_save')) return false;

    $is_update = $this->exists;

    if ($is_update) {
      if (!$this->run_callbacks('before_update')) return false;
      if (!$this->perform_update()) return true;
      if (!$this->run_callbacks('after_update')) return false;
    } else {
      if (!$this->run_callbacks('before_create')) return false;
      $this->perform_insert();
      if (!$this->run_callbacks('after_create')) return false;
    }

    if (!$this->run_callbacks('after_save')) return false;
    return true;
  }

  private function perform_update(): bool {
    $pk = static::primary_key();
    if (!is_string($pk))
      throw new ActiveRecordException(
        static::class . ": save() on existing records require a single-column primary key."
      );

    $pk_value = $this->original[$pk]
      ?? throw new ActiveRecordException(static::class . ": cannot update without a '{$pk}' value.");
    
    $dirty = [];
    foreach (static::persisted_attributes() as $name) {
      if ($name === $pk) continue;
      if (($this->attributes[$name] ?? null) !== ($this->original[$name] ?? null))
        $dirty[$name] = $this->attributes[$name] ?? null;
    }
    if ($dirty === []) return false;

    $sets = [];
    $binds = [':pk' => $pk_value];
    $i = 0;

    foreach ($dirty as $column => $value) {
      $ph = ':s' . $i++;
      $sets[] = "{$column} = {$ph}";
      $binds[$ph] = is_bool($value) ? (int) $value : $value;
    }

    $table = static::table_name();
    $stmt = static::db()->prepare(
      "UPDATE {$table} SET " . implode(', ', $sets) . " WHERE {$pk} = :pk"
    );
    $stmt->execute($binds);

    $this->sync_saved();
    return true;
  }

  private function perform_insert(): void {
    $columns = [];
    $placeholders = [];
    $binds = [];
    $i = 0;
    
    foreach (static::persisted_attributes() as $name) {
      if (!array_key_exists($name, $this->attributes)) continue;
      $columns[] = $name;
      $ph = ':i' . $i++;
      $placeholders[] = $ph;
      $value = $this->attributes[$name];
      $binds[$ph] = is_bool($value) ? (int) $value : $value;
    } 

    if ($columns === []) 
      throw new ActiveRecordException(static::class . ": nothing to insert; no attribute values set.");

    $table = static::table_name();
    $stmt = static::db()->prepare(
      "INSERT INTO {$table} (" . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')'
    );

    $stmt->execute($binds);

    $pk = static::primary_key();
    if (is_string($pk) && !isset($this->attributes[$pk])) {
      $id = static::db()->lastInsertId();
      if ($id !== false && $id !== '' && $id !== '0')
        $this->attributes[$pk] = $this->cast_attribute($pk, $id);
    }
    
    $this->exists = true;
    $this->sync_saved();
  }

  private function sync_saved(): void {
    $this->original = $this->attributes;
  }

  final public function destroy(): bool {
    if (!$this->exists) { $this->add_error('Record does not exist.'); return false; }
    
    $pk = static::primary_key();
    if (!is_string($pk))
      throw new ActiveRecordException(
        static::class . ": destroy() requires a single-column primary key."
      );

    $pk_value = $this->original[$pk] 
      ?? throw new ActiveRecordException(static::class . ": cannot destroy without a '{$pk}' value.");
    
    if (!$this->run_callbacks('before_destroy')) return false;
    
    $table = static::table_name();
    $stmt = static::db()->prepare("DELETE FROM {$table} WHERE {$pk} = :pk");
    $stmt->execute([':pk' => $pk_value]);

    $this->exists = false;
    if (!$this->run_callbacks('after_destroy')) return false;
    return true;
  }

  final public static function fetch_by(
    array $conditions = [],
    array $order = [],
    ?int $limit = null,
    ?int $offset = null,
  ):array {
    new static();

    $where = '';
    $binds = [];
    if ($conditions !== [])
      [$where, $binds] = static::build_where($conditions);

    $sql = 'SELECT ' . implode(', ', static::persisted_attributes())
      . ' FROM ' . static::table_name()
      . ($where === '' ? '' : " {$where}")
      . static::build_order($order)
      . static::build_limit($limit, $offset);

    $stmt = static::db()->prepare($sql);
    $stmt->execute($binds);

    $records = [];
    while (($row = $stmt->fetch()) !== false) $records[] = static::hydrate($row);
    return $records;
  }

  final public static function all(array $order = []): array {
    return static::fetch_by([], $order);
  }

  final public static function count(array $conditions = []): int {
    new static();

    $where = '';
    $binds = [];
    
    if ($conditions !== [])
      [$where, $binds] = static::build_where($conditions);

    $stmt = static::db()->prepare(
      'SELECT COUNT(*) FROM ' . static::table_name() . ($where === '' ? '' : " {$where}")
    );
    $stmt->execute($binds);
    
    return (int) $stmt->fetchColumn();
  }

  private static function build_order(array $order): string {
    if ($order === []) return '';

    $schema = self::$schema_cache[static::class];
    $parts = [];

    foreach ($order as $column => $direction) {
      if (!is_string($column) || !isset($schema[$column]) || !$schema[$column]['persisted'])
        throw new ActiveRecordException(
          static::class . ': invalid order column ' . var_export($column, true) . '.'
       );

      $direction = strtolower((string) $direction);
      if ($direction !== 'asc' && $direction !== 'desc')
        throw new ActiveRecordException(
          static::class . ": order direction for '{$column}' must be 'asc' or 'desc'."
        );

      $parts[] = "{$column} " . strtoupper($direction);
    }
    
    return ' ORDER BY ' . implode(', ', $parts);
  }

  private static function build_limit(?int $limit, ?int $offset): string {
    if ($limit === null) {
      if ($offset !== null)
        throw new ActiveRecordException(static::class . ": offset requires a limit.");
      return '';
    }

    if ($limit < 1)
      throw new ActiveRecordException(static::class . ": limit must be at least 1.");

    if ($offset !== null && $offset < 0)
      throw new ActiveRecordException(static::class . ": offset must not be negative.");

    return ' LIMIT ' . $limit . ($offset !== null ? ' OFFSET ' . $offset : '');
  }
}
