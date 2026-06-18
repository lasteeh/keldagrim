<?php 

namespace Keldagrim;

use Keldagrim\Throwable\Exception\Logic\ActionControllerException;
use Keldagrim\Request;

class ActionController {
  protected static array $skip_before_action = [];
  protected static array $before_action = [];
  protected static array $skip_after_action = [];
  protected static array $after_action = [];

  public function execute(string $method, Request $request): void {
    $class = static::class;

    if (!method_exists($this, $method)) 
      throw new ActionControllerException("Method [{$method}] not found on [{$class}]");

    $before_filters = static::$before_action;
    $skip_before_filters = static::$skip_before_action;

    /* foreach ($before_filters as $before_filter => $filter_options) { */
    /*   if ( */
    /*     !$this->filter_should_skip($skip_before_filters, $before_filter, $method) && */
    /*     $this->filter_should_apply($method, $filter_options) */
    /*   ) { */
    /*     if (!method_exists($this, $before_filter)) */
    /*       throw new ActionControllerException("Method [{$before_filter}] not found on [{$class}]"); */
    /**/
    /*     $this->{$before_filter}($request); */
    /*   } */
    /* } */

    $this->{$method}($request);   

    $after_filters = static::$after_action;
    $skip_after_filters = static::$skip_after_action;

    /* foreach ($after_filters as $after_filter => $filter_options) { */
    /*   if ( */
    /*     !$this->filter_should_skip($skip_after_filters, $after_filter, $method) && */
    /*     $this->filter_should_apply($method, $filter_options) */
    /*   ) { */
    /*     if (!method_exists($this, $after_filter)) */
    /*       throw new ActionControllerException("Method [{$after_filter}] not found on [{$class}]"); */
    /**/
    /*     $this->{$after_filter}($request); */
    /*   } */
    /* } */
  }
}
