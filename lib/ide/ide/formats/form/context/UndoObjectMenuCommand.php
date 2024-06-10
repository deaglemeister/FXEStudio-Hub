<?php
namespace ide\formats\form\context;

use ide\editors\AbstractEditor;
use ide\editors\menu\AbstractMenuCommand;
use platform\facades\Toaster;
use platform\toaster\ToasterMessage;
use php\gui\UXImage;

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
            $tm = new ToasterMessage();
            $iconImage = new UXImage('res://resources/expui/icons/virtualFolder_dark.png');
            $tm
            ->setIcon($iconImage)
            ->setTitle('Объектный менеджер конструктора')
            ->setDescription(_('К сожалению, в данный момент ваш список объектов пуст.'))
            ->setLink('Больше информации об объектах' , function() {
                browse('https://fxe-documents.gitbook.io/api-docs/');
            })
            ->setClosable();
            Toaster::show($tm);
        }
    }
    private function displayList() {
        var_dump("Текущий список: ");
        print_r($this->list);
    }

}
