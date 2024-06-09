<?php

namespace fxe\platform\actions;

use fxe\platform\plugins\AnAction;
use ide\forms\NewProjectForm;
use ide\Ide;
use php\gui\UXDialog;
use php\gui\UXFileChooser;
use php\lib\str;

class ProjectNewAction extends AnAction
{
    public function getName() : string
    {
        return _('menu.project.new');
    }

    public function getAccelerator()
    {
        return 'Ctrl + Alt + N';
    }

    public function onExecute()
    {
        $dialog = new NewProjectForm();
        $dialog->showDialog();
    }
}