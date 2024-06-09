<?php
namespace fxe\platform\plugins\traits;

use fxe\platform\plugins\AnAction;

trait Actions
{
    abstract function getActions() : array;
    
    public function getAction(string $actionClass) : AnAction
    {
        foreach($this->getActions() as $action)
        {
            if($action instanceof $actionClass) return $action;
        }
    }
}