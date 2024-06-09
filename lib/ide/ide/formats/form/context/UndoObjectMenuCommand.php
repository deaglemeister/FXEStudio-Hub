<?php
namespace ide\formats\form\context;

use ide\editors\AbstractEditor;
use ide\editors\FormEditor;
use ide\editors\menu\AbstractMenuCommand;
use ide\formats\AbstractFormFormat;
use ide\formats\form\AbstractFormElement;
use ide\Ide;
use ide\Logger;
use ide\forms\malboro\Toasts;
use php\gui\framework\DataUtils;
use php\gui\UXClipboard;
use php\gui\UXMenuItem;
use php\gui\UXNode;
use php\lib\items;
use php\lib\reflect;
use php\xml\XmlProcessor;

class UndoObjectMenuCommand extends AbstractMenuCommand
{
    private $list = array();
    private $toast;
    public function getName()
    {
        return _('Отменить действие');
    }
    function __construct()
    {
      $this->toast = new Toasts;
     
    }
    public function getAccelerator()
    {
        return 'Ctrl+Z';
    }

    public function addToEnd($value) {
        $this->list[] = $value;
    }

    public function withSeparator()
    {
        return true;
    }

    public function getIcon()
    {
        return 'icons/isUndo16.png';
    }

    public function onExecute($e = null, AbstractEditor $editor = null)
    {

        $this->removeLast($e,$editor);
    }
    public function removeLast($e,$editor) {
        if (!empty($this->list)) {
            $count = count($this->list);
            $lastElement = $this->list[$count - 1];
            $PasteMenuCommand = new PasteMenuCommand();
            $PasteMenuCommand->onExecute($e,$editor,true,$lastElement);
            array_pop($this->list);
        } else {
            $this->toast->showToast('Объектный менеджер', 'К сожалению, в данный момент список объектов пуст.', "#FF4F44");
        }
    }
    private function displayList() {
        var_dump("Текущий список: ");
        print_r($this->list);
    }

}
