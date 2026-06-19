<?php 

namespace Keldagrim\Trait;

use Keldagrim\Config;

trait CanGenerateAppPath {
  public static function path(string $to): string {
    return Config::HOME_URL() . $to;
  }
}
