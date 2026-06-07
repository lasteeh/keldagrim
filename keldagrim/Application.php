<?php

namespace Keldagrim;

class Application {
  final public function __construct() {
    Config::init();

    var_dump(Config::HOME_DIR());
    var_dump(Config::HOME_URL());
  }

  final public function run(): void {
    echo "run"; 
  }
}
