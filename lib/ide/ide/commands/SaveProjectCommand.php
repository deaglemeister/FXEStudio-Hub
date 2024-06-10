<?php
namespace ide\commands;

use ide\editors\AbstractEditor;
use ide\Ide;
use platform\facades\Toaster;
use platform\toaster\ToasterMessage;
use php\gui\UXImage;

/**
 * Class SaveProjectCommand
 * @package ide\commands
 */
class SaveProjectCommand extends AbstractProjectCommand
{
    public function getName()
    {
        return _('menu.project.save');
    }

    public function getIcon()
    {
        return 'icons/save16.png';
    }

    public function getAccelerator()
    {
        return 'Ctrl + S';
    }

    public function isAlways()
    {
        return true;
    }

    public function makeUiForHead()
    {
        return [$this->makeGlyphButton()];
    }

    public function onExecute($e = null, AbstractEditor $editor = null)
    {
        $project = Ide::get()->getOpenedProject();

        if ($project) {
            $project->save();
            $tm = new ToasterMessage();
            $iconImage = new UXImage('res://resources/expui/icons/fileTypes/succes.png');
            $tm
                ->setIcon($iconImage)
                ->setTitle('Менеджер по работе с проектами')
                ->setDescription(_('Ваш текущий проект был успешно сохранён.'))
                ->setLink('Сохранить ещё раз', function () {
                    $this->onExecute();
                })
                ->setClosable();
            Toaster::show($tm);
        }
    }
}