<?php

namespace platform\actions;

use fxe\platform\facades\IDE;
use fxe\platform\facades\Toaster;
use fxe\platform\plugins\AnAction;
use fxe\platform\toaster\ToasterMessage;

class IDEShutdownAction extends AnAction
{
    public function getName() : string
    {
        return "Exit";
    }

    public function onExecute()
    {
        IDE::shutdown();
    }
}