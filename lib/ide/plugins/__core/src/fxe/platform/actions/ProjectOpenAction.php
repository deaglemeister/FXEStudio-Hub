<?php

namespace fxe\platform\actions;

use fxe\platform\plugins\AnAction;
use ide\forms\NewProjectForm;
use ide\forms\OpenProjectForm;
use ide\Ide;
use php\gui\UXDialog;
use php\gui\UXFileChooser;
use php\lib\str;

class ProjectOpenAction extends AnAction
{
    public function getName() : string
    {
        return _('menu.project.open');
    }

    public function getAccelerator()
    {
        return 'Ctrl + Alt + O';
    }

    public function onExecute()
    {
        $dialog = new NewProjectForm();
        $dialog->owner = Ide::get()->getMainForm();
        
        $dialog->showAndWait();
    }
}