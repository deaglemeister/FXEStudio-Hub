<?php

namespace platform\actions;

use fxe\platform\facades\Toaster;
use platform\plugins\AnAction;
use fxe\platform\toaster\ToasterMessage;
use ide\Ide;
use ide\systems\FileSystem;
use ide\systems\ProjectSystem;

class ProjectCloseAction extends AnAction
{
    public function getName() : string
    {
        return _('menu.project.close');
    }

    public function withBeforeSeparator()
    {
        return true;
    }

    public function onExecute()
    {
        ProjectSystem::close(false);
        FileSystem::open('~welcome');
    }
}