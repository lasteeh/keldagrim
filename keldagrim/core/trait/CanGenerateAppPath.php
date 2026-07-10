<?php

namespace Keldagrim\Core\Trait;

use Keldagrim\Core\Config;

trait CanGenerateAppPath
{
  public static function path(string $to): string
  {
    return Config::HOME_URL() . $to;
  }
}
