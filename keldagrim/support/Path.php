<?php

namespace Keldagrim\Support;

use Keldagrim\Core\Config;
use Keldagrim\Throwable\Exception\Path\PathException;

final class Path {
  private function __construct() {}

  public static function root(string $path = ''): string {
    return self::resolve(
      Config::HOME_DIR(),
      $path
    );
  }

  public static function database(string $path = ''): string {
    return self::resolve(
      Config::HOME_DIR() . DIRECTORY_SEPARATOR . Config::DATABASE_DIR,
      $path
    );
  }

  public static function config(string $path = ''): string {
    return self::resolve(
      Config::HOME_DIR() . DIRECTORY_SEPARATOR . Config::CONFIG_DIR,
      $path
    );
  }

  private static function resolve(string $base, string $path): string {
    $base = self::normalize($base);
    $full = self::normalize($base . DIRECTORY_SEPARATOR . $path);

    self::assert_inside($base, $full);
    return $full;
  }

  private static function normalize(string $path): string {
    $path = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path);
    $prefix = '';

    if (preg_match('/^[A-Za-z]:/', $path, $matches)) {
        $prefix = $matches[0];
        $path = substr($path, strlen($prefix));
    }

    $absolute = str_starts_with($path, DIRECTORY_SEPARATOR);
    $parts = [];

    foreach (explode(DIRECTORY_SEPARATOR, $path) as $part) {
      if ($part === '' || $part === '.') continue;
      if ($part === '..') { array_pop($parts); continue; }

      $parts[] = $part;
    }

    $normalized = implode(DIRECTORY_SEPARATOR, $parts);
    if ($absolute) $normalized = DIRECTORY_SEPARATOR . $normalized;
    if ($prefix !== '') $normalized = $prefix . $normalized;

    return rtrim($normalized, DIRECTORY_SEPARATOR);
  }

  private static function assert_inside(string $base, string $path): void {
    $base = rtrim($base, DIRECTORY_SEPARATOR);
    if ($path !== $base && !str_starts_with($path, $base . DIRECTORY_SEPARATOR))
      throw new PathException("Path is outside allowed directory: {$path}");
  }
}
