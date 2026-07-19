<?php

namespace Keldagrim\Core;

use Keldagrim\Throwable\Exception\View\ActionViewException;
use Keldagrim\Throwable\Exception\View\MissingTemplateException;
use Keldagrim\Throwable\Exception\View\UnsupportedFormatException;
use Keldagrim\Core\Trait\CanGenerateAppPath;

final class ActionView
{
  use CanGenerateAppPath;

  private const ALLOWED_FORMATS = ['html', 'xml', 'text', 'js', 'css', 'csv'];
  private string $format;

  private string $view;
  private string $layout;

  private string $content;
  private string $render;

  private array $yield;
  private array $local;
  private array $global;

  private string $trace;

  public function __construct(string $view = '', array $global = [], string $layout = '', string $format = 'html')
  {
    if (!empty($format)) $this->set_format($format);
    if (!empty($view)) $this->set_view($view);
    if (!empty($global)) $this->set_global($global);
    if (!empty($layout)) $this->set_layout($layout);
  }

  private function set_format(string $format): void
  {
    if (empty($format)) throw new ActionViewException('A format is required.');

    if (!in_array($format, self::ALLOWED_FORMATS, true))
      throw new UnsupportedFormatException("Unsupported format: {$format}");

    $this->format = $format;
  }

  private function set_view(string $view): void
  {
    if (empty($view)) throw new ActionViewException('A view file is required.');

    $view = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $view);
    $safe_view_file = $view . '.' . $this->format . '.php';

    $lock_directory = realpath(
      Config::HOME_DIR() . DIRECTORY_SEPARATOR .
        'app' . DIRECTORY_SEPARATOR .
        'views' . DIRECTORY_SEPARATOR
    );

    $view_file = realpath($lock_directory . DIRECTORY_SEPARATOR . $safe_view_file);
    if (empty($view_file) || strpos($view_file, $lock_directory) !== 0)
      throw new MissingTemplateException("View file does not exist: {$safe_view_file}");

    $this->view = $view_file;
  }

  private function set_global(array $global): void
  {
    $this->global = $global;
  }

  private function set_layout(string $layout): void
  {
    if (empty($layout)) throw new ActionViewException('A layout file is required.');

    $layout = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $layout);
    $safe_layout_file = $layout . '.' . $this->format . '.php';

    $lock_directory = realpath(
      Config::HOME_DIR() . DIRECTORY_SEPARATOR .
        'app' . DIRECTORY_SEPARATOR .
        'views' . DIRECTORY_SEPARATOR
    );

    $layout_file = realpath($lock_directory . DIRECTORY_SEPARATOR . $safe_layout_file);
    if (empty($layout_file) || strpos($layout_file, $lock_directory) !== 0)
      throw new MissingTemplateException("Layout file does not exist or is inaccessible: {$safe_layout_file}");

    $this->layout = $layout_file;
  }

  public function render(): string
  {
    if (empty($this->view)) return '';
    $this->trace = $this->view;

    // require, not require_once: templates produce output, so they must
    // execute on every render. require_once returns bare `true` on any
    // repeat include per process, silently yielding an empty body when the
    // same view or layout renders twice in one request (e.g. batch email,
    // or two pages sharing a layout). partial() already uses plain require.
    ob_start();
    require($this->view);
    $this->content = ob_get_clean();

    if (empty($this->layout)) return $this->content;
    $this->trace = $this->layout;

    ob_start();
    require($this->layout);
    $this->render = ob_get_clean();

    return $this->render;
  }

  private function content_for(string $name, string $value): void
  {
    $this->yield[$name] = $value;
  }

  private function yield(string $name = '', string $default = ''): string
  {
    if (empty($name)) return $this->content;
    return (!isset($this->yield[$name])) ? $default : $this->yield[$name];
  }

  private function global(): array
  {
    return empty($this->global) ? [] : $this->global;
  }

  private function partial(string $relative_path, array $local = [], string $format = 'html'): string
  {
    if (empty($relative_path)) throw new ActionViewException('A partial file is required.');

    $format = empty($format) ? $this->format : $format;
    if (!in_array($format, self::ALLOWED_FORMATS, true))
      throw new UnsupportedFormatException("Unsupported format: {$format}");

    $relative_path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative_path);
    $safe_relative_path = $relative_path . '.' . $format . '.php';

    $base_directory = dirname($this->trace);
    if (empty($base_directory))
      throw new ActionViewException('Base directory does not exist.');

    $lock_directory = realpath(
      Config::HOME_DIR() . DIRECTORY_SEPARATOR .
        'app' . DIRECTORY_SEPARATOR .
        'views' . DIRECTORY_SEPARATOR
    );
    $full_path = $base_directory . DIRECTORY_SEPARATOR . $safe_relative_path;
    $resolved_path = realpath($full_path);
    if (empty($resolved_path) || strpos($resolved_path, $lock_directory) !== 0)
      throw new MissingTemplateException("Partial file does not exist or is inaccessible: {$safe_relative_path}");

    if (!empty($local)) $this->set_local($local);

    ob_start();
    require($resolved_path);
    $partial_content = ob_get_clean();

    unset($this->local);
    return $partial_content;
  }

  private function set_local(array $local): void
  {
    $this->local = $local;
  }

  private function local(): array
  {
    return empty($this->local) ? [] : $this->local;
  }

  private function stylesheet(string $path, string $ext = 'css'): string {
    return rtrim(Config::HOME_URL(), '/') . '/assets/css/' . ltrim($path, '/') . '.' . $ext; 
  }

  private function script(string $path, string $ext = 'js'): string {
    return rtrim(Config::HOME_URL(), '/') . '/assets/js/' . ltrim($path, '/') . '.' . $ext;
  }

  private function flash(?string $type = null): array { return Flash::get($type); }
}
