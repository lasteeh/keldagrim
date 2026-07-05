<?php

namespace Keldagrim\Response;

use Keldagrim\Response;

class HTMLResponse extends Response {
  protected string $html;

  public function send_body(): void {
    echo $this->html;
  }
}
