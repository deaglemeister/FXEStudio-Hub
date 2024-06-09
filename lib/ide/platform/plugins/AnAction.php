<?php

namespace platform\plugins;

use Closure;
use php\gui\UXMenuItem;

abstract class AnAction 
{
    abstract function getName(): string;
    abstract function onExecute();
    
    public function getIcon() { return null; }

    public function getCategory() { return 'file'; }

    public function resolveAction() : Closure
    {
        return function()
        {
            $this->onExecute();
        };
    }

    public function getAccelerator() { return null; }

    public function withBeforeSeparator() { return false; }
    public function withAfterSeparator() { return false; }


    protected $__menuItem;
    public function resolveMenuItem() : UXMenuItem
    {
        if(!$this->__menuItem)
        {
            $this->__menuItem = new UXMenuItem($this->getName());
            $this->__menuItem->graphic = $this->getIcon(); 
            $this->__menuItem->accelerator = $this->getAccelerator();

            $this->__menuItem->on('action', $this->resolveAction());
        }

        return $this->__menuItem;
    }
    
}
