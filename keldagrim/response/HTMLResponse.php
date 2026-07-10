<?php

namespace Keldagrim\Response;

use Keldagrim\Response;

class HTMLResponse extends Response {
  protected string $html;

  public function __construct(
    string $html, 
    int $status = 200, 
    array $headers = ['Content-Type' => 'text/html; charset=utf-8']
  ) {
    $this->html = $html;
    $this->set_status_code($status);

    if (!empty($headers)) {
      foreach ($headers as $name => $value) {
        $this->set_header($name, $value);
      }
    }
  }

  public function send_body(): void {
    echo $this->html;
  }
}
