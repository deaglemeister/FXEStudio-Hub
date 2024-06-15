<?php

namespace kosogroup\liver\traits;

use Closure;
use php\lang\Thread;

trait Threadable
{
    protected ?Thread $__thread = null;

    public abstract function __threadClosure() : Closure;

    protected function __threadRun()
    {
        $this->__thread = new Thread($this->__threadClosure());
        $this->__thread->start();
    }

    protected function __threadInterrupt()
    {
        $this->__thread->interrupt();
    }
}