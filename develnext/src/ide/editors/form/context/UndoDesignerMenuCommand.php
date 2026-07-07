<?php
namespace ide\editors\form\context;

use ide\editors\AbstractEditor;
use ide\editors\FormEditor;
use ide\editors\menu\AbstractMenuCommand;
use ide\Ide;
use php\gui\UXMenuItem;

class UndoDesignerMenuCommand extends AbstractMenuCommand
{
    public function getName()
    {
        return 'Отменить';
    }

    public function getAccelerator()
    {
        return 'Ctrl+Z';
    }

    public function getIcon()
    {
        return 'icons/undo16.png';
    }

    public function withSeparator()
    {
        return true;
    }

    public function onExecute($e = null, AbstractEditor $editor = null)
    {
        /** @var FormEditor $editor */
        $editor->undoDesigner();
    }

    public function onBeforeShow(UXMenuItem $item, AbstractEditor $editor = null)
    {
        /** @var FormEditor $editor */
        $item->disable = !$editor || !$editor->canUndoDesigner();
    }
}
