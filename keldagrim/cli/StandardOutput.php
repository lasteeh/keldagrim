<?php

namespace Keldagrim\CLI;

use DateTimeImmutable;
use DateTimeInterface;

final class StandardOutput {
  private function __construct() {

  }

  public static function write(string $status, string $message): void {
    $input = $status === 'error' ? STDERR : STDOUT;
    $timestamp = empty($status) ? '' : self::timestamp() . ' [' . strtoupper($status) . '] ';

    fwrite(
      $input, 
      ($status === 'error' ? PHP_EOL : '') .
      $timestamp . $message . PHP_EOL
    );
  }

  private static function timestamp(): string {
    return (new DateTimeImmutable())->format(DateTimeInterface::ATOM);
  }
}
