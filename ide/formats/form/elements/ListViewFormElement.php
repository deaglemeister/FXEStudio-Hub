<?php
namespace ide\formats\form\elements;

use ide\formats\form\AbstractFormElement;
use ide\library\IdeLibraryScriptGeneratorResource;
use php\gui\UXListView;
use php\gui\UXNode;

/**
 * Class ListViewFormElement
 * @package ide\formats\form
 */
class ListViewFormElement extends AbstractFormElement
{
    public function getName()
    {
        return 'Список';
    }

    public function getElementClass()
    {
        return UXListView::class;
    }

    public function getIcon()
    {
        return 'icons/listbox16.png';
    }

    public function getIdPattern()
    {
        return "listView%s";
    }

    public function getScriptGenerators()
    {
        return [
            new IdeLibraryScriptGeneratorResource('res://.dn/bundle/uiDesktop/scriptgen/AddNewItemListViewScriptGen'),
            new IdeLibraryScriptGeneratorResource('res://.dn/bundle/uiDesktop/scriptgen/AddFirstNewItemListViewScriptGen'),
            new IdeLibraryScriptGeneratorResource('res://.dn/bundle/uiDesktop/scriptgen/SearchItemsListViewScriptGen'),
            new IdeLibraryScriptGeneratorResource('res://.dn/bundle/uiDesktop/scriptgen/ClearItemsListScriptGen'),
            new IdeLibraryScriptGeneratorResource('res://.dn/bundle/uiDesktop/scriptgen/SetCellFactoryListViewScriptGen'),
        ];
    }


    /**
     * @return UXNode
     */
    public function createElement()
    {
        $button = new UXListView();
        return $button;
    }

    public function getDefaultSize()
    {
        return [150, 100];
    }

    public function isOrigin($any)
    {
        return $any instanceof UXListView;
    }
}
