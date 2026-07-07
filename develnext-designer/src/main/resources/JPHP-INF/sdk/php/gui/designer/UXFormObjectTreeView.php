<?php
namespace php\gui\designer;

use php\gui\UXNode;
use php\gui\UXTreeItem;
use php\gui\UXTreeView;

/**
 * Дерево объектов формы (иерархия, тип, inline rename F2).
 */
class UXFormObjectTreeView extends UXTreeView
{
    /**
     * @param string $id
     * @param string $typeName
     * @param UXNode|null $icon
     * @return UXTreeItem
     */
    public function createRootItem($id, $typeName, $icon = null)
    {
    }

    /**
     * @param string $id
     * @param string $typeName
     * @param UXNode|null $icon
     * @return UXTreeItem
     */
    public function createNodeItem($id, $typeName, $icon = null)
    {
    }

    /**
     * @param string $id
     * @param string $typeName
     * @param UXNode|null $icon
     * @param bool $renameable
     * @return UXTreeItem
     */
    public function createItem($id, $typeName, $icon = null, $renameable = true)
    {
    }

    /**
     * @param callable $callback function ($oldId, $newId): bool
     */
    public function onRename(callable $callback)
    {
    }

    /**
     * @param callable $callback function ($targetId)
     */
    public function onSelectionChanged(callable $callback)
    {
    }

    public function editFocused()
    {
    }
}
