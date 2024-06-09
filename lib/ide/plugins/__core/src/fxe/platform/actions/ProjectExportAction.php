<?php

namespace fxe\platform\actions;

use fxe\platform\plugins\AnAction;
use ide\Ide;
use php\gui\UXDialog;
use php\gui\UXFileChooser;
use php\lib\str;

class ProjectExportAction extends AnAction
{
    public function getName() : string
    {
        return _('menu.project.save.as.archive');
    }

    public function getAccelerator()
    {
        return 'Ctrl + Shift + S';
    }

    public function onExecute()
    {
        $project = Ide::get()->getOpenedProject();

        if ($project) {
            $dialog = new UXFileChooser();
            $dialog->initialFileName = $project->getName() . ".zip";
            $dialog->extensionFilters = [['extensions' => ['*.zip'], 'description' => 'Zip Архив с проектом']];

            if ($file = $dialog->showSaveDialog()) {
                if (!str::endsWith($file, '.zip')) {
                    $file .= '.zip';
                }

                $project->export($file);

                Ide::toast(_('toast.project.save.zip.done'));
            }
        } else {
            UXDialog::show(_('alert.project.export.fail'));
        }
    }
}