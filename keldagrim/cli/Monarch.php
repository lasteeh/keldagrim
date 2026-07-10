<?php

namespace Keldagrim\CLI;

use Keldagrim\Throwable\ErrorHandler;
use Keldagrim\Config;
use Keldagrim\Throwable\Exception\KeldagrimRuntimeException;
use Keldagrim\Throwable\Exception\KeldagrimInvalidArgumentException;
use Keldagrim\CLI\OptionsParser;
use ZipArchive;
use RecursiveDirectoryIterator;
use RecursiveCallbackFilterIterator;
use RecursiveIteratorIterator;

/* TODO: test update */
final class Monarch {
  public function __construct() {
    ErrorHandler::init();
    Config::init();

    if (PHP_SAPI !== 'cli')
      throw new KeldagrimRuntimeException('CLI environment required.');
  }

  public function run(array $args): void {
    $opts = new OptionsParser($args);
    $command = $opts->command();

    switch ($command) {
      case 'update':
        $download_link = $opts->get('url');
        if (empty($download_link)) {
          StandardOutput::write('', 'monarch:');
          StandardOutput::write('', 'A valid "--url" option is required');
          exit(1);
        }

        if (!filter_var($download_link, FILTER_VALIDATE_URL)) {
          StandardOutput::write('', 'monarch:'); 
          StandardOutput::write('', 'Invalid url.');
          exit(1);
        }

        $ch = curl_init($download_link);
        curl_setopt_array($ch, [
          CURLOPT_NOBODY => true,          // HEAD request equivalent
          CURLOPT_FOLLOWLOCATION => true, 
          CURLOPT_MAXREDIRS => 5,
          CURLOPT_TIMEOUT => 5,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_SSL_VERIFYPEER => true,
        ]);

        curl_exec($ch);
        $curl_errno = curl_errno($ch);
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        unset($ch);

        if ($curl_errno !== 0 || $status_code !== 200) {
          StandardOutput::write('', 'monarch:');
          StandardOutput::write('', 'Unable to reach url: ' . $download_link);
          exit(1);
        }

        if (!class_exists("ZipArchive")) {
          StandardOutPut::write('', 'monarch:');
          StandardOutPut::write('', 'ZipAarchive is not enabled.');
          StandardOutPut::write('', 'Please enable "extension=zip" in your php.ini file.');
          exit(1);
        }

        $confirmation = readline('This action cannot be undone. Please make sure to backup and commit all your files. Continue? Y/n: ');
        if (strtolower($confirmation) !== 'y') {
          StandardOutput::write('', 'Aborted.');
          exit;
        }

        StandardOutput::write('', 'Framework update starting...');

        $save_path = "__temp-keldagrim.zip";
        $extraction_path = "__temp-keldagrim" . DIRECTORY_SEPARATOR;

        if (is_dir($extraction_path)) {
          StandardOutput::write('', 'Clearing out temporary update directory...');
          $this->delete_dir($extraction_path); 
        }

        if (file_exists($save_path)) {
          StandardOutput::write('', 'Clearing existing update zip file...');
          $this->delete_file($save_path);
        }

        StandardOutput::write('', 'Downloading updates...');

        $file_content = file_get_contents($download_link);
        if ($file_content === false) {
          if (file_exists($save_path)) $this->delete_file($save_path);
          StandardOutput::write('', 'Failed to download update zip file.');
          exit(1);
        }

        file_put_contents($save_path, $file_content);
        StandardOutput::write('', 'Update zip file downloaded successfully.');

        $zip = new ZipArchive;
        if ($zip->open($save_path) === true) {
          StandardOutput::write('', 'Extracting files...');
          if ($zip->extractTo($extraction_path)) {
            $zip->close();
            StandardOutput::write('', 'Update zip files have been extracted successfully.');
          } else {
            $zip->close();
            if (is_dir($extraction_path)) $this->delete_dir($extraction_path);
            throw new KeldagrimRuntimeException('Failed to extract update zip files.');
          }
        } else {
          if (is_dir($extraction_path)) $this->delete_dir($extraction_path);
          if (file_exists($save_path)) $this->delete_file($save_path);
          throw new KeldagrimRuntimeException('Failed to open update zip file. ');
        }

        $version_filename = '.keldagrim-version';
        $exclude_directories = ['vendors'];
        $update_source = $this->find_file_dir($extraction_path, $version_filename, $exclude_directories);
        if (empty($update_source)) {
          StandardOutput::write('', 'Incorrect update files. Aborting...');
          StandardOutput::write('', 'Cleaning up temporary files...');
          if (is_dir($extraction_path)) $this->delete_dir($extraction_path);
          if (file_exists($save_path)) $this->delete_file($save_path);
          StandardOutput::write('', 'Update aborted.');
          exit(1);
        }
        
        $version_filepath = $update_source . $version_filename;
        if (!file_exists($version_filepath)) {
          StandardOutput::write('', 'Unable to find update version. Aborting...');
          StandardOutput::write('', 'Cleaning up temporary files...');
          if (is_dir($extraction_path)) $this->delete_dir($extraction_path);
          if (file_exists($save_path)) $this->delete_file($save_path);
          StandardOutput::write('', 'Update aborted.');
          exit(1);
        }       

        $version_content = file_get_contents($version_filepath);
        preg_match('/\b(\d+\.\d+\.\d+)\b/', $version_content, $matches);
        $downloaded_version = $matches[1] ?? null;
        if (empty($downloaded_version)) {
          StandardOutput::write('', 'Unable to find update version. Aborting...');
          StandardOutput::write('', 'Cleaning up temporary files...');
          if (is_dir($extraction_path)) $this->delete_dir($extraction_path);
          if (file_exists($save_path)) $this->delete_file($save_path);
          StandardOutput::write('', 'Update aborted.');
          exit(1);
        }

        $current_version_content = file_get_contents(Config::HOME_DIR() . DIRECTORY_SEPARATOR . $version_filename);
        preg_match('/\b(\d+\.\d+\.\d+)\b/', $current_version_content, $matches);
        $current_version = $matches[1] ?? '0.0.0';
        
        $confirmation = readline("Update {$current_version} to {$downloaded_version}. Continue? Y/n: ");
        if (strtolower($confirmation) !== 'y') {
          StandardOutput::write('', 'Aborting...');
          StandardOutput::write('', 'Cleaning up temporary files...');
          if (is_dir($extraction_path)) $this->delete_dir($extraction_path);
          if (file_exists($save_path)) $this->delete_file($save_path);
          StandardOutput::write('', 'Update aborted.');
          exit;
        }

        StandardOutput::write('', 'Updating project files...');
        $exclude = ['.gitignore'];
        $this->delete_dir(Config::HOME_DIR() . DIRECTORY_SEPARATOR . 'keldagrim');
        $this->copy_dir($update_source, Config::HOME_DIR(), $exclude);

        StandardOutput::write('', 'Cleaning up temporary files...');
        if (is_dir($extraction_path)) $this->delete_dir($extraction_path);
        if (file_exists($save_path)) $this->delete_file($save_path);

        StandardOutput::write('', 'Update completed successfully.');
        StandardOutput::write('', "v {$downloaded_version}");
        StandardOutput::write('', 'Please check that all files were properly updated.');

        break;
    }
  }

  private function delete_file(string $path): void {
    if (!is_link($path) && !is_file($path))
      throw new KeldagrimInvalidArgumentException('File deletion failed. Invalid file path: ' . $path);

    if (!unlink($path)) {
      $last_error = error_get_last();
      $error_message = !empty($last_error) ? $last_error['message'] : 'Unkown system error?';
      throw new KeldagrimRuntimeException("Failed to delete \"{$path}\": {$error_message}"); 
    }
  }

  private function delete_dir(string $path): void {
    if (!is_dir($path)) 
      throw new KeldagrimInvalidArgumentException('Directory deletion failed. Directory does not exist: ' . $path);

    $files = scandir($path);
    if ($files === false)
      throw new KeldagrimRuntimeException('Failed to scan directory: ' . $path);

    $files = array_diff($files, ['.', '..']);
    foreach ($files as $file) {
      $file_path = $path . DIRECTORY_SEPARATOR . $file;

      if (is_link($file_path) || is_file($file_path)) {
        $this->delete_file($file_path);
      } elseif (is_dir($file_path)) {
        $this->delete_dir($file_path);
      }
    }

    if (!rmdir($path)) {
      $last_error = error_get_last();
      $error_message = !empty($last_error) ? $last_error['message'] : 'Unkown OS-level error?';
      throw new KeldagrimRuntimeException("Directory \"{$path}\" deletion failed: {$error_message}");
    }
  }

  private function copy_dir(string $source, string $destination, array $exclude = []): void {
    if (!is_dir($source))
      throw new KeldagrimInvalidArgumentException('Unable to find directory: ' . $source);
    if (!is_dir($destination)) mkdir($destination, 0755, true);

    $directory = opendir($source);
    while (($file = readdir($directory)) !== false) {
      if ($file === '.' || $file === '..') continue;

      $source_file = $source . DIRECTORY_SEPARATOR . $file;
      $destination_file = $destination . DIRECTORY_SEPARATOR . $file;

      $relative_path = ltrim(str_replace(Config::HOME_DIR(), '', $destination_file), DIRECTORY_SEPARATOR);
      if (in_array($relative_path, $exclude)) continue;

      if (is_dir($source_file)) {
        $this->copy_dir($source_file, $destination_file, $exclude);
      } else {
        if (!copy($source_file, $destination_file)) {
          $last_error = error_get_last();
          $error_message = !empty($last_error) ? $last_error['message'] : 'Unknown system error?';
          throw new KeldagrimRuntimeException("Failed to copy \"{$source_file}\": {$error_message}");
        }
      }
    }

    closedir($directory);
  }

  private function find_file_dir(string $directory, string $filename, array $exclude = []): ?string {
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
}
