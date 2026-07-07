<?php
namespace ide\editors\menu;

use ide\editors\AbstractEditor;
use ide\Ide;
use ide\misc\AbstractCommand;
use php\gui\UXMenu;
use php\gui\UXMenuItem;
use php\gui\UXNode;
use php\lang\IllegalStateException;

abstract class AbstractMenuCommand extends AbstractCommand
{
    /**
     * @var ContextMenu
     */
    protected $contextMenu;

    /**
     * @param ContextMenu $contextMenu
     */
    public function setContextMenu($contextMenu)
    {
        $this->contextMenu = $contextMenu;
    }

    public function getIcon()
    {
        return null;
    }

    public function getAccelerator()
    {
        return null;
    }

    public function isHidden()
    {
        return false;
    }

    public function withSeparator()
    {
        return false;
    }

    public function makeMenuItem()
    {
        $item = new UXMenuItem($this->getName());
        $icon = $this->getIcon();

        if ($icon) {
            if ($icon instanceof UXNode) {
                $item->graphic = $icon;
            } elseif (is_string($icon)) {
                try {
                    $graphic = Ide::get()->getImage($icon, [16, 16]);

                    if ($graphic) {
                        $item->graphic = $graphic;
                    }
                } catch (\Throwable $e) {
                }
            }
        }

        $item->accelerator = $this->getAccelerator();
        $item->on('action', $this->makeAction());

        return $item;
    }

    public function makeUiForHead()
    {
        return $this->makeGlyphButton();
    }

    /**
     * @param UXMenuItem|UXMenu $item
     * @param AbstractEditor|null $editor
     */
    public function onBeforeShow($item, AbstractEditor $editor = null)
    {
        // nop.
    }
}