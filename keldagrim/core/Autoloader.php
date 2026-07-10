<?php

class Autoloader {
  private const FRAMEWORK_NAME = 'keldagrim';

  private function __construct() {}

	public static function init() {
		spl_autoload_register([__CLASS__, 'autoload']);
	}

	public static function autoload (string $fqcn) {	
    $classname = str_replace("\\", DIRECTORY_SEPARATOR, $fqcn);
    $last_slash_pos = strrpos($classname, DIRECTORY_SEPARATOR); 
    if ($last_slash_pos === false) return;

    $namespace = substr($classname, 0, $last_slash_pos + 1);
    $filename = str_replace($namespace, "", $classname);

    $framework_core_directory = DIRECTORY_SEPARATOR . self::FRAMEWORK_NAME;
    $framework_core_directory_pos = strrpos(__DIR__, $framework_core_directory);
    $app_directory = ($framework_core_directory_pos !== false) 
      ? substr(__DIR__, 0, $framework_core_directory_pos) 
      : __DIR__; 

    $file_path = $app_directory . DIRECTORY_SEPARATOR . strtolower($namespace) . $filename . ".php";    
    $file_path = preg_replace('/\/+/', DIRECTORY_SEPARATOR, $file_path);

    if (!file_exists($file_path)) return;
    require_once($file_path);
	}
}
