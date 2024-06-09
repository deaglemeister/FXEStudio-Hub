<?php

namespace platform\actions;

use platform\facades\Toaster;
use platform\plugins\AnAction;
use platform\toaster\ToasterMessage;
use ide\Ide;

class ProjectSaveAction extends AnAction
{
    public function getName() : string
    {
        return _('menu.project.save');
    }

    public function getAccelerator()
    {
        return 'Ctrl + S';
    }


    public function onExecute()
    {
          $project = Ide::get()->getOpenedProject();

        if ($project) {
            $project->save();

            $tm = new ToasterMessage();
            $tm->setTitle(_('toast.project.save.done'))
                ->setClosable();

            Toaster::show($tm);
        }
    }
}