<?php

namespace Keldagrim\CLI;

// we build customr cli parser
// to follow format php <script_name> <command> <--option=value>
final class OptionsParser
{
  private ?string $command = null;
  private array $options = [];

  public function __construct(array $args)
  {
    $this->command = $args[1] ?? null;

    foreach (array_slice($args, 2) as $arg) {
      if (!preg_match('/^--([^=]+)=(.*)$/', $arg, $matches)) continue;

      $this->options[$matches[1]] = $matches[2];
    }
  }

  public function command(): ?string
  {
    return $this->command;
  }

  public function has(string $key): bool
  {
    return isset($this->options[$key]);
  }

  public function get(string $key, ?string $default = null): ?string
  {
    return $this->options[$key] ?? $default;
  }
}
