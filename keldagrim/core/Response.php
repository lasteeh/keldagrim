<?php

namespace Keldagrim\Core;

abstract class Response
{
  protected int $status_code = 200;
  protected array $headers = [];

  final public function send(): void
  {
    $this->send_headers();
    $this->send_body();
  }

  protected function send_headers(): void
  {
    if (headers_sent()) return;

    http_response_code($this->status_code);
    foreach ($this->headers as $name => $value) {
      header("{$name}: {$value}");
    }
  }

  abstract protected function send_body(): void;

  public function set_status_code(int $code): static
  {
    $this->status_code = $code;
    return $this;
  }

  public function set_header(string $name, string $value): static
  {
    $this->headers[$name] = $value;
    return $this;
  }
}
