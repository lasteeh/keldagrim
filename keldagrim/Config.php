<?php

namespace Keldagrim;

use Keldagrim\Exception\Runtime\ConfigException;

class Config {
  private static ?Config $instance = null;

  public const FRAMEWORK_CORE_DIR = 'keldagrim/';
  public const CONFIG_DIR = 'config/';
  public const INDEX = '/public/index.php';
  
  private const APPLICATION_CONFIG_FILE = 'application.php';
  private const ROUTES_CONFIG_FILE = 'routes.php';

  private static string $HOME_DIR;
  private static string $HOME_URL;

  private static array $settings = [];

  private function __construct() {
    $this->set_home_dir(); 
    $this->load_env();
    $this->load_application_config();
    $this->load_other_config();
    $this->set_home_url();
    $this->load_routes();
  }

  public static function init(): void {
    if (self::$instance == null) self::$instance = new self();
  }

  private function set_home_dir(): void {
    $framework_core_dir = str_replace('/', DIRECTORY_SEPARATOR, self::FRAMEWORK_CORE_DIR);
    self::$HOME_DIR = str_replace($framework_core_dir, "", __DIR__); 
  }

  private function set_home_url(): void {
    $env_home_url = self::get('application.url', null);
    if (!empty($env_home_url)) {
      self::$HOME_URL = $env_home_url;
      return;
    } 

    $force_https = filter_var($_ENV['FORCE_HTTPS'] ?? false, FILTER_VALIDATE_BOOL);
    $request_scheme = ($force_https === true) ? 'https' : ($_SERVER['REQUEST_SCHEME'] ?? 'http');
    $http_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $home_path = str_replace(self::INDEX, '', ($_SERVER['SCRIPT_NAME'] ?? '')); 

    self::$HOME_URL = $request_scheme . '://' . $http_host . $home_path;
  }

  private function load_env(): void {
    $env_path = self::HOME_DIR() . DIRECTORY_SEPARATOR . '.env'; 

    if (class_exists('\Dotenv\Dotenv')) {
      try {
        $dotenv = \Dotenv\Dotenv::createImmutable(self::HOME_DIR());
        $dotenv->load();
      } catch (\Exception $e) {
        throw new ConfigException($e);
      }
    } else {
      if (!file_exists($env_path)) return;
      
      $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES); 
      if (empty($lines)) return;

      foreach ($lines as $line) {
        if (
          !is_string($line) || 
          empty(trim($line)) || 
          strpos($line, '#') === 0 ||
          strpos($line, '=') === false
        ) continue;
        
        [$key, $value] = explode('=', $line, 2);
        if (array_key_exists($key, $_ENV)) continue;        

        $_ENV[$key] = $value;
      }      
    }
  }

  private function load_application_config(): void {
    $application_config = self::HOME_DIR() . DIRECTORY_SEPARATOR . self::CONFIG_DIR .
      DIRECTORY_SEPARATOR . self::APPLICATION_CONFIG_FILE; 
    if (!file_exists($application_config)) throw new ConfigException('"application.php" is missing.');

    self::$settings[basename(self::APPLICATION_CONFIG_FILE, '.php')] = include($application_config);
  }

  private function load_other_config(): void {
    $config_files = glob(
      self::HOME_DIR() . DIRECTORY_SEPARATOR . self::CONFIG_DIR . DIRECTORY_SEPARATOR . '*.php'
    );
    if ($config_files === false) return;

    foreach ($config_files as $config_file) {
      $base_name = basename($config_file);
      $file_name = basename($config_file, '.php');

      if (
        $base_name === self::APPLICATION_CONFIG_FILE ||
        $base_name === self::ROUTES_CONFIG_FILE 
      ) continue;
     
      $file_content = include($config_file);
      if (!is_array($file_content)) continue;

      self::$settings[$file_name] = $file_content;
    }
  }

  private function load_routes(): void {
    $route_config = self::HOME_DIR() . DIRECTORY_SEPARATOR . self::CONFIG_DIR . 
      DIRECTORY_SEPARATOR . self::ROUTES_CONFIG_FILE; 
    if (!file_exists($route_config)) throw new ConfigException('"routes.php" is missing.');

    require_once($route_config); 
  }

  public static function get(string $key, mixed $default = null): mixed {
    $segments = explode('.', $key);
    $data = self::$settings;

    foreach ($segments as $segment) {
      if (!isset($data[$segment])) return $default;

      $data = $data[$segment];
    }

    return $data;
  }

  public static function HOME_DIR(): string {
    if (empty(self::$HOME_DIR))
      throw new ConfigException('HOME_DIR is empty or not configured.');

    return self::$HOME_DIR;
  }

  public static function HOME_URL(): string {
    if (empty(self::$HOME_URL)) 
      throw new ConfigException('HOME_URL is empty or not configured.');

    return self::$HOME_URL;
  }
}
