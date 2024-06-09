<?php
namespace ide\formats\form\context;

use ide\editors\AbstractEditor;
use ide\editors\FormEditor;
use ide\editors\menu\AbstractMenuCommand;
use php\gui\UXMenuItem;
use php\lib\items;

/**
 * Class CutMenuCommand
 * @package ide\formats\form\context
 */
class CutMenuCommand extends AbstractMenuCommand
{
    public function getName()
    {
        return _('contextmenu2');
    }

    public function getAccelerator()
    {
        return 'Ctrl+X';
    }
    function __construct($UndoObjectMenuCommand = null)
    {
        $this->UndoObjectMenuCommand = $UndoObjectMenuCommand;
      
    }
    public function getIcon()
    {
        return 'icons/cut16.png';
    }

    public function onExecute($e = null, AbstractEditor $editor = null)
    {
        $copyCommand = new CopyMenuCommand();
        $deleteCommand = new DeleteMenuCommand();
       
        if($this->UndoObjectMenuCommand != null){
            $xmlCut = $copyCommand->onExecute($e, $editor, false,true);
            $this->UndoObjectMenuCommand->addToEnd($xmlCut);
        }
        $copyCommand->onExecute($e, $editor, false,false);
        $deleteCommand->onExecute($e, $editor,false);
       


        
    }

    public function onBeforeShow(UXMenuItem $item, AbstractEditor $editor = null)
    {
        /** @var FormEditor $editor */
        $item->disable = !items::first($editor->getDesigner()->getSelectedNodes());
    }
}