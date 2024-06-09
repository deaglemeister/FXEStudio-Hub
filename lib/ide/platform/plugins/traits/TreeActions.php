<?php
namespace fxe\platform\plugins\traits;

trait TreeActions
{
    abstract function getTreeActions() : array;

    public function getTreeAction(string $actionClass) : AnAction
    {
        foreach($this->getTreeActions() as $action)
        {
            if($action instanceof $actionClass) return $action;
        }
    }
}