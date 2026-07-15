<?php

namespace Keldagrim\Support;

use Keldagrim\Throwable\Exception\FileSystem\FileSystemException;
use Keldagrim\Core\Config;
use Keldagrim\CLI\StandardOutput;
use RecursiveDirectoryIterator;
use RecursiveCallbackFilterIterator;
use RecursiveIteratorIterator;

final class FileSystem {
  private function __construct() {}

  public static function delete_file(string $path): void
  {
    if (!is_link($path) && !is_file($path))
      throw new FileSystemException('File deletion failed. Invalid file path: ' . $path);

    if (!unlink($path)) {
      $last_error = error_get_last();
      $error_message = !empty($last_error) ? $last_error['message'] : 'Unkown system error?';
      throw new FileSystemException("Failed to delete \"{$path}\": {$error_message}");
    }
  }

  public static function delete_dir(string $path): void
  {
    if (!is_dir($path))
      throw new FileSystemException('Directory deletion failed. Directory does not exist: ' . $path);

    $files = scandir($path);
    if ($files === false)
      throw new FileSystemException('Failed to scan directory: ' . $path);

    $files = array_diff($files, ['.', '..']);
    foreach ($files as $file) {
      $file_path = $path . DIRECTORY_SEPARATOR . $file;

      if (is_link($file_path) || is_file($file_path)) {
        self::delete_file($file_path);
      } elseif (is_dir($file_path)) {
        self::delete_dir($file_path);
      }
    }

    if (!rmdir($path)) {
      $last_error = error_get_last();
      $error_message = !empty($last_error) ? $last_error['message'] : 'Unkown OS-level error?';
      throw new FileSystemException("Directory \"{$path}\" deletion failed: {$error_message}");
    }
  }

  public static function copy_dir(string $source, string $destination, array $exclude = []): void
  {
    if (!is_dir($source))
      throw new FileSystemException('Unable to find directory: ' . $source);
    if (!is_dir($destination)) mkdir($destination, 0755, true);

    $directory = opendir($source);
    while (($file = readdir($directory)) !== false) {
      if ($file === '.' || $file === '..') continue;

      $source_file = $source . DIRECTORY_SEPARATOR . $file;
      $destination_file = $destination . DIRECTORY_SEPARATOR . $file;

      $relative_path = ltrim(str_replace(Config::HOME_DIR(), '', $destination_file), DIRECTORY_SEPARATOR);
      if (in_array($relative_path, $exclude)) continue;

      if (is_dir($source_file)) {
        self::copy_dir($source_file, $destination_file, $exclude);
      } else {
        if (!copy($source_file, $destination_file)) {
          $last_error = error_get_last();
          $error_message = !empty($last_error) ? $last_error['message'] : 'Unknown system error?';
          throw new FileSystemException("Failed to copy \"{$source_file}\": {$error_message}");
        }
      }
    }

    closedir($directory);
  }

  public static function find_file_dir(string $directory, string $filename, array $exclude = []): ?string
  {
    $directory_iterator = new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS);
    $filter_iterator = new RecursiveCallbackFilterIterator(
      $directory_iterator,
      function ($file, $key, $iterator) use ($exclude) {
        if ($file->isDir() && in_array($file->getFilename(), $exclude))
          return false;

        return true;
      }
    );

    $iterator = new RecursiveIteratorIterator($filter_iterator);
    foreach ($iterator as $file) {
      if ($file->getFilename() !== $filename) continue;
      return dirname($file->getPathname()) . DIRECTORY_SEPARATOR;
    }

    return null;
  }

  public static function sync_delete_removed_files(string $source, string $destination, array $exclude = []): void
  {
    if (!is_dir($destination)) return;

    $directory = opendir($destination);
    while (($file = readdir($directory)) !== false) {
      if ($file === '.' || $file === '..') continue;

      $destination_file = $destination . DIRECTORY_SEPARATOR . $file;
      $source_file = $source . DIRECTORY_SEPARATOR . $file;

      $relative_path = ltrim(str_replace(Config::HOME_DIR(), '', $destination_file), DIRECTORY_SEPARATOR);
      if (in_array($relative_path, $exclude)) continue;

      if (is_dir($destination_file)) {
        if (!is_dir($source_file)) {
          StandardOutput::write('', 'Removing obsolete directory: ' . $relative_path);
          self::delete_dir($destination_file);
        } else {
          self::sync_delete_removed_files($source_file, $destination_file, $exclude);
        }
      } else {
        if (!file_exists($source_file)) {
          StandardOutput::write('', 'Removing obsolete file:' . $relative_path);
          self::delete_file($destination_file);
        }
      }
    }
  }

  public static function write_file(string $path, string $content = ''): void {
    $safe_path = $path;
    $app_root = Path::root();

    if (str_starts_with($path, $app_root)) {
      $safe_path = substr($path, strlen($app_root));
      $safe_path = ltrim($safe_path, DIRECTORY_SEPARATOR);
      $safe_path = DIRECTORY_SEPARATOR . $safe_path;
    }

    if (is_file($path)) throw new FileSystemException("File already exists: {$safe_path}");

    $file = fopen($path, 'w');
    fwrite($file, $content);
    fclose($file);

    if (!is_file($path)) throw new FileSystemException("Failed to create file: {$safe_path}");
    StandardOutput::write('', "File created successfully: {$safe_path}");
  }

  public static function create_dir(string $path): void {
    $safe_path = $path;
    $app_root = Path::root();

    if (str_starts_with($path, $app_root)) {
      $safe_path = substr($path, strlen($app_root));
      $safe_path = ltrim($safe_path, DIRECTORY_SEPARATOR);
      $safe_path = DIRECTORY_SEPARATOR . $safe_path;
    }

    if (!is_dir($path)) {
      if (!mkdir($path, 0777, true))
        throw new FileSystemException("Failed to create directory: {$safe_path}");

      StandardOutput::write('', "Directory created successfully: {$safe_path}");
    }
  }
}
