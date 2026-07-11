<?php

namespace Keldagrim\Core\Response;

use Keldagrim\Core\Response;

class JSONResponse extends Response 
{
  protected array $data;

  public function __construct(
    array $data,
    int $status = 200,
    array $headers = [
      'Content-Type' => 'application/json',
      'Cache-Control' => 'no-store',
    ],
  ) {
    $this->data = $data;   
    $this->set_status_code($status);

    if (!empty($headers)) {
      foreach ($headers as $name => $value) {
        $this->set_header($name, $value);
      }
    }
  }

  public function send_body(): void {
    echo json_encode($this->data, JSON_UNESCAPED_SLASHES);
  }
}
