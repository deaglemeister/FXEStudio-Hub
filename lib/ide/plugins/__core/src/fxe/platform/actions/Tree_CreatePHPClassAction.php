<?php

namespace fxe\platform\actions;

use fxe\platform\plugins\AnAction;

class Tree_CreatePHPClassAction extends AnAction
{
    public function getName() : string
    {
        return "Create PHP Class";
    }

    public function getAccelerator()
    {
        return 'Ctrl + Shift + Insert';
    }

    public function onExecute()
    {
        
    }
}