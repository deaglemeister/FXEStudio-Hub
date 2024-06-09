<?php

namespace fxe\platform\actions;

use fxe\platform\plugins\AnAction;

class Tree_CreateFileAction extends AnAction
{
    public function getName() : string
    {
        return "Create File";
    }

    public function getAccelerator()
    {
        return 'Ctrl + Shift + N';
    }

    public function onExecute()
    {
        
    }
}