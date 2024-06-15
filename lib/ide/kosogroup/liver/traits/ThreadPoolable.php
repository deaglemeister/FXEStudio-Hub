<?php

namespace kosogroup\liver\traits;

use Closure;

use php\lang\ThreadPool;

trait ThreadPoolable
{

    protected $__threadPoolCount = 6;

    protected ?ThreadPool $__threadPool = null;

    protected function __threadPoolExecute(Closure $closure)
    {
        if($this->__threadPool == null)
            $this->__threadPool = ThreadPool::createFixed($this->__threadPoolCount);

        $this->__threadPool->execute($closure);
    }



}