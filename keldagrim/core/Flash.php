<?php

namespace Keldagrim\Core;

use Keldagrim\Throwable\Exception\Session\FlashException;

final class Flash {
  private const KEY = '_keldagrim_flash';

  private function __construct() {}

  public static function set(string $type, string $message): void {
    self::ensure();
    $_SESSION[self::KEY]['new'][$type][] = $message;
  }

  public static function now(string $type, string $message): void {
    self::ensure();
    $_SESSION[self::KEY]['current'][$type][] = $message;
  }

  public static function get(?string $type = null): array {
    $current = $_SESSION[self::KEY]['current'] ?? [];
    if ($type !== null) return $current[$type] ?? [];
    return $current;
  }

  public static function has(?string $type = null): bool {
    return self::get($type) !== [];
  }

  public static function sweep(): void {
    $new = $_SESSION[self::KEY]['new'] ?? [];
    if ($new === []) { unset($_SESSION[self::KEY]); return; }
    $_SESSION[self::KEY] = ['current' => $new, 'new' => []];
  }

  private static function ensure(): void {
    if (session_status() !== PHP_SESSION_ACTIVE)
      throw new FlashException(
        'Flash requires an active session. Start the session before setting flash messages.'
      );
    $_SESSION[self::KEY] ??= ['current' => [], 'new' => []];
  }
}
