<?php
namespace ide\editors\form\context;

use ide\editors\form\IdeObjectTreeList;
use ide\editors\menu\AbstractMenuCommand;
use ide\editors\AbstractEditor;

class ObjectTreeRenameMenuCommand extends AbstractMenuCommand
{
    /**
     * @var IdeObjectTreeList
     */
    protected $treeList;

    public function __construct(IdeObjectTreeList $treeList)
    {
        $this->treeList = $treeList;
    }

    public function getIcon()
    {
        return 'icons/edit16.png';
    }

    public function getName()
    {
        return 'Переименовать';
    }

    public function getAccelerator()
    {
        return 'F2';
    }

    public function onExecute($e = null, AbstractEditor $editor = null)
    {
        $this->treeList->startRenameFocused();
    }

    public function onBeforeShow($item, AbstractEditor $editor = null)
    {
        parent::onBeforeShow($item, $editor);

        $item->disable = !$this->treeList->canRenameFocused();
    }
}
