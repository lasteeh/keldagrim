<?php

namespace Keldagrim\Core\Response;

use Keldagrim\Core\Response;

class RedirectResponse extends Response {
  protected string $url;

  public function __construct(
    string $url,
    int $status = 302,
    array $headers = [],
  ) {
    $this->url = $url;
    $this->set_status_code($status);

    if (!empty($headers)) {
      foreach ($headers as $name => $value) {
        $this->set_header($name, $value);
      }
    }

    $this->set_header('Location', $this->url);
  }

  public function send_body(): void {

  }
}
