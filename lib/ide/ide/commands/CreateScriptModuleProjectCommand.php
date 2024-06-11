<?php
namespace ide\commands;

use Dialog;
use Files;
use ide\editors\AbstractEditor;
use ide\editors\menu\AbstractMenuCommand;
use ide\formats\ScriptModuleFormat;
use ide\forms\BuildProgressForm;
use ide\forms\InputMessageBoxForm;
use ide\forms\MessageBoxForm;
use ide\Ide;
use ide\misc\AbstractCommand;
use ide\project\behaviours\GradleProjectBehaviour;
use ide\project\behaviours\GuiFrameworkProjectBehaviour;
use ide\systems\FileSystem;
use ide\utils\FileUtils;
use php\gui\UXDialog;
use php\io\File;
use php\lang\Process;
use php\lib\Str;
use php\time\Time;
use php\util\Regex;

class CreateScriptModuleProjectCommand extends AbstractMenuCommand
{
    public function getName()
    {
        return _('Новый модуль');
    }

    public function getIcon()
    {
        return 'icons/webModuleGroup_dark.png';
    }

    public function getCategory()
    {
        return 'create';
    }

    public function onExecute($e = null, AbstractEditor $editor = null)
    {
        $ide = Ide::get();
        $project = $ide->getOpenedProject();

        if ($project) {

            $name = $ide->getRegisteredFormat(ScriptModuleFormat::class)->showCreateDialog();

            if ($name !== null) {
                $name = str::trim($name);

                if (!FileUtils::validate($name)) {
                    return null;
                }

                /** @var GuiFrameworkProjectBehaviour $guiBehaviour */
                $guiBehaviour = $project->getBehaviour(GuiFrameworkProjectBehaviour::class);

                if ($guiBehaviour->hasModule($name)) {
                    $dialog = new MessageBoxForm(_('module.text'), [_('btn.create.new.cancel'), _('btn.create.new.form')]);
                    if ($dialog->showDialog() && $dialog->getResultIndex() == 0) {
                        return null;
                    }
                }

                $file = $guiBehaviour->createModule($name);
                FileSystem::open($file);

                return $name;
            }
        }
    }
}