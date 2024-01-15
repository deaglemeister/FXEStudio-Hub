<?php
namespace ide\commands;

use ide\editors\AbstractEditor;
use ide\editors\FormEditor;
use ide\editors\menu\AbstractMenuCommand;
use ide\formats\GuiFormFormat;
use ide\forms\BuildProgressForm;
use ide\forms\InputMessageBoxForm;
use ide\forms\MessageBoxForm;
use ide\Ide;
use ide\misc\AbstractCommand;
use ide\project\behaviours\GradleProjectBehaviour;
use ide\project\behaviours\GuiFrameworkProjectBehaviour;
use ide\project\ProjectTree;
use ide\systems\FileSystem;
use ide\utils\FileUtils;
use php\gui\UXDialog;
use php\lang\Process;
use php\lib\fs;
use php\lib\Str;
use php\time\Time;
use php\util\Regex;

class CreateFormProjectCommand extends AbstractMenuCommand
{
    /**
     * @var ProjectTree
     */
    protected $tree;

    /**
     * CreateFormProjectCommand constructor.
     * @param ProjectTree $tree
     */
    public function __construct(ProjectTree $tree = null)
    {
        $this->tree = $tree;
    }


    public function getName()
    {
        return 'Новая форма';
    }

    public function getIcon()
    {
        return 'icons/window16.png';
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
            /** @var GuiFrameworkProjectBehaviour $guiBehaviour */
            $guiBehaviour = $project->getBehaviour(GuiFrameworkProjectBehaviour::class);

            $format = $ide->getRegisteredFormat(GuiFormFormat::class);
            $name = $format->showCreateDialog();

            if ($name !== null) {
                $name = str::trim($name);

                if (!FileUtils::validate($name)) {
                    return;
                }

                if ($guiBehaviour->hasForm($name)) {
                    $dialog = new MessageBoxForm("Форма '$name' уже существует, хотите её пересоздать?", ['Нет, оставить', 'Да, пересоздать']);
                    if ($dialog->showDialog() && $dialog->getResultIndex() == 0) {
                        return;
                    }
                }

                $file = $guiBehaviour->createForm($name);

                /** @var FormEditor $editor */
                $editor = FileSystem::fetchEditor($file);
                $editor->getConfig()->set("form.title", $name);
                $editor->saveConfig();

                FileSystem::open($file);

                if (!$guiBehaviour->getMainForm() && sizeof($guiBehaviour->getFormEditors()) < 2) {
                    $dlg = new MessageBoxForm(
                        "У вашего проекта нет главной формы, хотите сделать форму '$name' главной?", ['Да, сделать главной', 'Нет']
                    );

                    if ($dlg->showDialog() && $dlg->getResultIndex() == 0) {
                        $guiBehaviour->setMainForm($name);
                        Ide::toast("Форма '$name' теперь главная в вашем проекте");
                    }
                }
            }
        }
    }
}