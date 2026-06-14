<?php

namespace Keldagrim;

use Keldagrim\Throwable\ErrorHandler;

final class App {
  public function __construct() {

    ErrorHandler::init();    
    Config::init();

  }

  public function run(): void {

    session_start();

  }
}
