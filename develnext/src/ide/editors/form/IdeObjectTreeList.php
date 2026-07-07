<?php
namespace ide\editors\form;

use Dialog;
use ide\editors\common\ObjectListEditorItem;
use ide\editors\form\context\ObjectTreeRenameMenuCommand;
use ide\editors\menu\ContextMenu;
use ide\formats\form\AbstractFormElement;
use ide\Ide;
use ide\misc\EventHandlerBehaviour;
use ide\utils\UiUtils;
use php\gui\designer\UXFormObjectTreeView;
use php\gui\layout\UXHBox;
use php\gui\UXButton;
use php\gui\UXComboBox;
use php\gui\UXLabel;
use php\gui\UXListCell;

/**
 * Выбор объекта формы: меню + combobox. Дерево скрыто, используется как модель.
 */
class IdeObjectTreeList
{
    use EventHandlerBehaviour;

    /** @var UXFormObjectTreeView */
    protected $ui;

    /** @var UXComboBox */
    protected $selectorCombo;

    /** @var string[] */
    protected $selectorIds = [];

    /** @var bool */
    protected $selectorLock = false;

    /** @var ObjectListEditorItem */
    protected $emptyItem;

    /** @var callable */
    protected $traverseFunc;

    /** @var int */
    protected $levelOffset = 0;

    /** @var ContextMenu */
    protected $contextMenu;

    /** @var callable|null */
    protected $renameHandler;

    /** @var array */
    protected $itemById = [];

    /** @var array */
    protected $iconById = [];

    public function __construct(ContextMenu $contextMenu = null)
    {
        $this->contextMenu = $contextMenu;
    }

    public function setTraverseFunc(callable $traverseFunc)
    {
        $this->traverseFunc = $traverseFunc;
    }

    public function setEmptyItem(ObjectListEditorItem $emptyItem)
    {
        $this->emptyItem = $emptyItem;
    }

    public function setLevelOffset($levelOffset)
    {
        $this->levelOffset = $levelOffset;
    }

    public function setRenameHandler(callable $handler)
    {
        $this->renameHandler = $handler;
    }

    public function setSelected($targetId)
    {
        if (!$this->ui || !$this->ui->root) {
            return;
        }

        $this->lockHandles();

        if ($targetId && isset($this->itemById[$targetId])) {
            $this->ui->selectedItems = [$this->itemById[$targetId]];
            $this->ui->scrollTo($this->itemById[$targetId]);
        } else {
            $this->ui->selectedItems = [$this->ui->root];
        }

        $this->syncSelectorFromTree($targetId);
        $this->unlockHandles();
    }

    public function update($selectedTargetId = null)
    {
        if (!$this->ui) {
            return;
        }

        if (!$selectedTargetId && $this->ui->selectedItems) {
            $selected = $this->ui->selectedItems[0];

            if ($selected && $selected->value) {
                $selectedTargetId = $selected->value->id;
            }
        }

        $expandedIds = $this->collectExpandedIds();

        $this->lockHandles();
        $this->itemById = [];
        $this->iconById = [];

        $root = $this->ui->createRootItem(
            $this->emptyItem ? $this->emptyItem->text : '',
            '',
            $this->emptyItem ? $this->emptyItem->graphic : null
        );
        $root->expanded = true;

        $lastAtLevel = [];
        $lastAtLevel[$this->levelOffset - 1] = $root;

        if ($this->traverseFunc) {
            $func = $this->traverseFunc;

            $func(function ($target, $targetId, AbstractFormElement $element = null, $level) use (&$lastAtLevel, $root) {
                if (!$targetId) {
                    return;
                }

                $treeLevel = $level + $this->levelOffset;
                $parentLevel = $treeLevel - 1;
                $parent = isset($lastAtLevel[$parentLevel]) ? $lastAtLevel[$parentLevel] : $root;

                $icon = $element ? Ide::get()->getImage($element->getIcon(), [16, 16]) : null;
                $typeName = $element ? $element->getName() : '';

                $treeItem = $this->ui->createNodeItem($targetId, $typeName, $icon);
                $treeItem->expanded = true;

                $parent->children->add($treeItem);
                $lastAtLevel[$treeLevel] = $treeItem;
                $this->itemById[$targetId] = $treeItem;
                $this->iconById[$targetId] = $element ? $element->getIcon() : null;
            });
        }

        $this->ui->root = $root;
        $this->restoreExpandedIds($expandedIds);

        if ($selectedTargetId && isset($this->itemById[$selectedTargetId])) {
            $this->ui->selectedItems = [$this->itemById[$selectedTargetId]];
            $this->ui->scrollTo($this->itemById[$selectedTargetId]);
        } else {
            $this->ui->selectedItems = [$root];
            $selectedTargetId = null;
        }

        $this->rebuildSelector($selectedTargetId);
        $this->unlockHandles();
    }

    protected function lockHandles()
    {
        $this->selectorLock = true;
    }

    protected function unlockHandles()
    {
        $this->selectorLock = false;
    }

    protected function collectExpandedIds()
    {
        $result = [];

        foreach ($this->itemById as $id => $item) {
            if ($item->expanded) {
                $result[$id] = true;
            }
        }

        return $result;
    }

    protected function restoreExpandedIds(array $expandedIds)
    {
        foreach ($expandedIds as $id => $one) {
            if (isset($this->itemById[$id])) {
                $this->itemById[$id]->expanded = true;
            }
        }

        if ($this->ui->root) {
            $this->ui->root->expanded = true;
        }
    }

    protected function rebuildSelector($selectedTargetId = null)
    {
        if (!$this->selectorCombo) {
            return;
        }

        $items = [];

        $rootLabel = $this->emptyItem ? $this->emptyItem->text : 'Форма';
        $rootGraphic = $this->emptyItem ? $this->emptyItem->graphic : null;
        $items[] = new ObjectListEditorItem($rootLabel, $rootGraphic, null, 0);

        $ids = [null];

        foreach ($this->itemById as $id => $item) {
            $typeName = $item->value ? $item->value->typeName : '';
            $graphic = isset($this->iconById[$id]) ? $this->iconById[$id] : null;

            $editorItem = new ObjectListEditorItem($id, $graphic, $id, 1);
            $editorItem->hint = $typeName;
            $items[] = $editorItem;
            $ids[] = $id;
        }

        $this->selectorIds = $ids;
        $this->selectorCombo->items->clear();
        $this->selectorCombo->items->addAll($items);

        $index = 0;
        if ($selectedTargetId) {
            $found = array_search($selectedTargetId, $ids, true);
            if ($found !== false) {
                $index = $found;
            }
        }

        $this->selectorCombo->selectedIndex = $index;
    }

    protected function renderSelectorCell(UXListCell $cell, ObjectListEditorItem $item)
    {
        $cell->text = null;
        $cell->graphic = null;

        $text = $item->hint ? $item->text . ' — ' . $item->hint : $item->text;
        $label = new UXLabel($text);

        if ($item->graphic) {
            $label->graphic = Ide::get()->getImage($item->graphic, [16, 16]);
        }

        $label->paddingLeft = $item->level * 10;
        $cell->graphic = $label;
    }

    protected function syncSelectorFromTree($targetId)
    {
        if (!$this->selectorCombo || !$this->selectorIds) {
            return;
        }

        $index = 0;
        if ($targetId) {
            $found = array_search($targetId, $this->selectorIds, true);
            if ($found !== false) {
                $index = $found;
            }
        }

        $this->selectorCombo->selectedIndex = $index;
    }

    protected function renameNode($oldId, $newId)
    {
        if (!$this->renameHandler || !$oldId || !$newId || $oldId == $newId) {
            return false;
        }

        $result = call_user_func($this->renameHandler, $oldId, $newId);

        if ($result) {
            switch ($result) {
                case 'cancelled':
                    $this->update($oldId);
                    return false;
                case 'invalid':
                    Dialog::error("'$newId' - не подходящее название для id, используйте только английские буквы, цифры и символ подчеркивания");
                    break;
                case 'busy':
                    Dialog::error("Элемент с id = '$newId' или свойство с таким названием уже существует, придумайте другой id");
                    break;
                default:
                    Dialog::error("Не удалось переименовать '$oldId' в '$newId'");
                    break;
            }

            $this->update($oldId);
            return false;
        }

        $this->update($newId);
        return true;
    }

    public function canRenameFocused()
    {
        $item = $this->getFocusedRenameItem();

        return $item && $item->value && $item->value->renameable;
    }

    public function startRenameFocused()
    {
        $item = $this->getFocusedRenameItem();

        if ($item && $item->value && $item->value->renameable) {
            uiLater(function () {
                $this->ui->editFocused();
            });
        }
    }

    protected function getFocusedRenameItem()
    {
        if (!$this->ui) {
            return null;
        }

        if ($this->ui->focusedItem) {
            return $this->ui->focusedItem;
        }

        if ($this->ui->selectedItems) {
            return $this->ui->selectedItems[0];
        }

        return null;
    }

    protected function initTree()
    {
        if ($this->ui) {
            return;
        }

        $ui = new UXFormObjectTreeView();
        $ui->editable = true;
        $ui->style = UiUtils::fontSizeStyle();
        $ui->visible = false;
        $ui->managed = false;
        $ui->prefHeight = 0;
        $ui->minHeight = 0;
        $ui->maxHeight = 0;

        $ui->onSelectionChanged(function ($targetId) {
            if ($this->selectorLock) {
                return;
            }

            $this->syncSelectorFromTree($targetId);
            $this->trigger('change', [$targetId ? $targetId : null]);
        });

        $ui->onRename(function ($oldId, $newId) {
            return $this->renameNode($oldId, $newId);
        });

        $treeContextMenu = new ContextMenu();
        $treeContextMenu->add(new ObjectTreeRenameMenuCommand($this));
        $treeContextMenu->linkTo($ui);

        $this->ui = $ui;
    }

    public function makeHeaderUi()
    {
        $this->initTree();

        $this->selectorCombo = new UXComboBox();
        $this->selectorCombo->maxWidth = 9999;
        $this->selectorCombo->classes->add('fxe-form-object-selector');
        $this->selectorCombo->visibleRowCount = 16;
        UXHBox::setHgrow($this->selectorCombo, 'ALWAYS');

        $render = [$this, 'renderSelectorCell'];
        $this->selectorCombo->onCellRender($render);
        $this->selectorCombo->onButtonRender($render);

        $this->selectorCombo->on('action', function () {
            if ($this->selectorLock) {
                return;
            }

            $index = $this->selectorCombo->selectedIndex;
            $targetId = isset($this->selectorIds[$index]) ? $this->selectorIds[$index] : null;

            $this->lockHandles();

            if ($targetId && isset($this->itemById[$targetId])) {
                $this->ui->selectedItems = [$this->itemById[$targetId]];
            } else {
                $this->ui->selectedItems = [$this->ui->root];
            }

            $this->unlockHandles();
            $this->trigger('change', [$targetId]);
        });

        if ($this->contextMenu) {
            $this->selectorCombo->on('click', function ($e) {
                if ($e->button == 'SECONDARY') {
                    $this->contextMenu->show($this->selectorCombo);
                }
            });
        }

        $header = new UXHBox([$this->selectorCombo], 6);
        $header->classes->add('fxe-form-sidebar-header');
        $header->alignment = 'CENTER_LEFT';

        return $header;
    }

    public function makeUi()
    {
        return $this->makeHeaderUi();
    }
}
