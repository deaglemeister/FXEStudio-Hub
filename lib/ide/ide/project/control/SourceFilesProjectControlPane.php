<?php
namespace ide\project\control;

use php\gui\UXNode;

/**
 * Class SourceFilesProjectControlPane
 * @package ide\project\control
 */
class SourceFilesProjectControlPane extends AbstractProjectControlPane
{
    /**
     * @return string
     */
    public function getName()
    {
        return _('file.project');
    }

    /**
     * @return string
     */
    public function getIcon()
    {
        return 'icons/dirs16.png';
    }


    public function getDescription()
    {
        return _('project.description');
    }

    /**
     * @return UXNode
     */
    protected function makeUi()
    {

    }

    /**
     * Refresh ui and pane.
     */
    public function refresh()
    {

    }
}