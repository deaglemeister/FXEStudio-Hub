<?php
namespace ide\commands\tree;

use ide\editors\AbstractEditor;
use ide\editors\menu\AbstractMenuCommand;
use ide\project\ProjectTree;

class TreeRenameFileCommand extends AbstractMenuCommand
{
    /**
     * @var ProjectTree
     */
    protected $tree;

    public function __construct(ProjectTree $tree)
    {
        $this->tree = $tree;
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
        $view = $this->tree->getView();

        if ($view && $this->tree->canModifySelectedPath()) {
            $view->editFocused();
        }
    }

    public function onBeforeShow($item, AbstractEditor $editor = null)
    {
        parent::onBeforeShow($item, $editor);

        $item->disable = !$this->tree->canModifySelectedPath();
    }
}
