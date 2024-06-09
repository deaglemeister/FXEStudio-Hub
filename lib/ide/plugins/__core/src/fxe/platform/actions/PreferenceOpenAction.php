<?php

namespace platform\actions;

use fxe\platform\facades\Toaster;
use platform\plugins\AnAction;
use fxe\platform\toaster\ToasterMessage;

class PreferenceOpenAction extends AnAction
{
    public function getName() : string
    {
        return "Preferences";
    }

    public function withBeforeSeparator()
    {
        return true;
    }

    public function withAfterSeparator()
    {
        return true;
    }

    public function onExecute()
    {
        
    }
}