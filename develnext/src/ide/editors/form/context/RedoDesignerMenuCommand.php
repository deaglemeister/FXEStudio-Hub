<?php
namespace ide\editors\form\context;

use ide\editors\AbstractEditor;
use ide\editors\FormEditor;
use ide\editors\menu\AbstractMenuCommand;
use ide\Ide;
use php\gui\UXMenuItem;

class RedoDesignerMenuCommand extends AbstractMenuCommand
{
    public function getName()
    {
        return 'Вернуть';
    }

    public function getAccelerator()
    {
        return 'Ctrl+Y';
    }

    public function getIcon()
    {
        return 'icons/redo16.png';
    }

    public function onExecute($e = null, AbstractEditor $editor = null)
    {
        /** @var FormEditor $editor */
        $editor->redoDesigner();
    }

    public function onBeforeShow(UXMenuItem $item, AbstractEditor $editor = null)
    {
        /** @var FormEditor $editor */
        $item->disable = !$editor || !$editor->canRedoDesigner();
    }
}
