<?php

namespace Keldagrim\Core\Trait;

use Keldagrim\Core\Config;

trait CanGenerateAppPath
{
  public static function path(string $to): string
  {
    return rtrim(Config::HOME_URL(), '/') . '/' . ltrim('/', $to);
  }
}
