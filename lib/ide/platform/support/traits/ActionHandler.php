<?php

namespace platform\support\traits;

use Closure;

trait ActionHandler
{
    protected $__actions = [];

    protected function __resolveAction($action, Closure $closure)
    {
        $this->__actions[$action] = $closure;
    }

    protected function __handleAction($action, ...$actionData)
    {
        $this->__actions[$action](...$actionData);
    }

}