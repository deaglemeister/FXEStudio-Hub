<?php
namespace ide\commands;

use ide\editors\AbstractEditor;
use ide\editors\FormEditor;
use ide\editors\menu\AbstractMenuCommand;
use ide\formats\GuiFormFormat;
use ide\forms\MessageBoxForm;
use ide\Ide;
use ide\project\behaviours\GuiFrameworkProjectBehaviour;
use ide\project\ProjectTree;
use ide\systems\FileSystem;
use ide\utils\FileUtils;
use php\lib\Str;

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


    public function getAccelerator()
    {
        return 'F8';
    }

    public function getName()
    {
        return _('Новую форму'); //_('lang.name.f')
    }

    public function getIcon()
    {
        return 'icons/applicationRemote_dark.png';
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
                    $dialog = new MessageBoxForm(_('form.aready'), [_('btn.create.new.cancel'), _('btn.create.new.form')]);
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
                       _('form.no.form'), [_('form.btn.yes.form'),  _('form.btn.no.form')]
                    );

                    if ($dlg->showDialog() && $dlg->getResultIndex() == 0) {
                        $guiBehaviour->setMainForm($name);
                        Ide::toast(_('form.glavnaya'));
                    }
                }
            }
        }
    }
}