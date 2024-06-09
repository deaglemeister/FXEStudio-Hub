<?php

namespace fxe\platform\plugins\traits;

use php\gui\UXMenuBar;

trait IDEMenuBarReConstructor
{
    abstract function reConstructIDEMenuBar(UXMenuBar $menuBar) : void;
}