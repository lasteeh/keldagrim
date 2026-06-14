<?php

namespace Keldagrim;

use Keldagrim\Throwable\ErrorHandler;

class App {
  final public function __construct() {

    ob_start();
    session_start();

    ErrorHandler::init();    
    Config::init();

    Test::init();
  }

  final public function run(): void {
    echo "run"; 


    ob_end_flush();
  }
}
