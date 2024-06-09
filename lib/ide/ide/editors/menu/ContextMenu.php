<?php
namespace ide\editors\menu;

use action\Geometry;
use ide\editors\AbstractEditor;
use ide\Logger;
use ide\misc\AbstractCommand;
use ide\misc\EventHandlerBehaviour;
use ide\utils\UiUtils;
use php\desktop\Mouse;
use php\gui\event\UXKeyEvent;
use php\gui\event\UXMouseEvent;
use php\gui\UXContextMenu;
use php\gui\UXMenu;
use php\gui\UXMenuButton;
use php\gui\UXMenuItem;
use ide\Ide;
use php\gui\UXNode;
use php\gui\UXSplitMenuButton;
use php\lang\IllegalArgumentException;
use php\lib\str;

/**
 * Class ContextMenu
 * @package ide\editors\menu
 */
class ContextMenu
{
    use EventHandlerBehaviour;

    /**
     * @var UXContextMenu
     */
    protected $root;

    /**
     * @var AbstractEditor
     */
    protected $editor;

    /**
     * @var callable
     */
    protected $filter;

    /**
     * @var UXMenu[]
     */
    protected $groups = [];

    /**
     * @var string
     */
    protected $style;

    /**
     * @var string
     */
    protected $cssClass;

    /**
     * @param AbstractEditor $editor
     * @param array $commands
     */
    public function __construct(AbstractEditor $editor = null, array $commands = [])
    {
        $this->editor = $editor;
        $this->root = new UXContextMenu();
        $this->root->on('showing', [$this, 'doShowing']);

        foreach ($commands as $command) {
            if ($command == '-') {
                $this->root->items->add(UXMenuItem::createSeparator());
                continue;
            }

            if ($command instanceof AbstractMenuCommand) {
                $this->add($command);
            } else {
                $this->addCommand($command);
            }
        }
    }

    /**
     * @return string
     */
    public function getCssClass()
    {
        return $this->cssClass;
    }

    /**
     * @param string $cssClass
     */
    public function setCssClass($cssClass)
    {
        $this->cssClass = $cssClass;
        $this->getRoot()->classes->add($cssClass);
    }

    /**
     * @return string
     */
    public function getStyle()
    {
        return $this->style;
    }

    /**
     * @param string $style
     */
    public function setStyle($style)
    {
        $this->style = $style;
    }

    /**
     * @return callable
     */
    public function getFilter()
    {
        return $this->filter;
    }

    /**
     * @param callable $filter
     */
    public function setFilter(callable $filter)
    {
        $this->filter = $filter;
    }

    public function clear()
    {
        $this->root->items->clear();
    }

    public function addSeparator($group = null)
    {
        $menu = $this->root;

        if ($group) {
            $menu = $this->groups[$group];

            if (!$menu) {
                throw new IllegalArgumentException("Group $group not found");
            }
        }

        $menu->items->add(UXMenuItem::createSeparator());
    }

    public function addGroup($code, $title, $icon = null)
    {
        $menuItem = new UXMenu($title, Ide::get()->getImage($icon));
        $this->root->items->add($menuItem);

        $this->groups[$code] = $menuItem;
    }

    protected function isCursorInPopup()
    {
        return Geometry::hasPoint($this->root, Mouse::x(), Mouse::y()) || !$this->root->visible;
    }

    public function addCommand(AbstractCommand $command)
    {
        if ($command->withBeforeSeparator()) {
            $this->root->items->add(UXMenuItem::createSeparator());
        }

        $menuItem = $command->makeMenuItem();

        if ($this->cssClass) {
            $menuItem->classes->add("$this->cssClass-item");
        }

        if ($this->style) {
            $menuItem->style .= ';' . $this->style;
        }

        if ($menuItem instanceof UXMenuItem) {
            $menuItem->on('action', function ($e) use ($command, $menuItem) {
                $filter = $this->filter;

                if ($this->isCursorInPopup()) {
                    if (!$filter || $filter($command) || (!$menuItem->visible && !$menuItem->disable)) {
                        $command->onExecute($e, $this->editor);
                    }
                }
            });
        }

        $this->root->items->add($menuItem);

        if ($command->withAfterSeparator()) {
            $this->root->items->add(UXMenuItem::createSeparator());
        }
    }

    public function add(AbstractMenuCommand $command, $group = null)
    {
        $command->setContextMenu($this);

        $menuItem = $command->makeMenuItem();

        if ($menuItem instanceof UXMenuItem) {
            $menuItem->accelerator = $command->getAccelerator();
            $menuItem->userData = $command;
        }

        if ($this->cssClass) {
            $menuItem->classes->add("$this->cssClass-item");
        }

        if ($this->style) {
            $menuItem->style .= ';' . $this->style;
        }

        if ($command->isHidden()) {
            $menuItem->visible = false;
        }

        if ($menuItem instanceof UXMenuItem) {
            $menuItem->on('action', function ($e) use ($command, $menuItem, $group) {
                $filter = $this->filter;

                if ($this->isCursorInPopup() || $group) {
                    if (!$filter || $filter($command) || (!$menuItem->visible && !$menuItem->disable)) {
                        $command->onExecute($e, $this->editor);
                    }
                }
            });
        }

        $menu = $this->root;

        if ($group) {
            $menu = $this->groups[$group];

            if (!$menu) {
                throw new IllegalArgumentException("Group $group not found");
            }
        }

        if ($command->withBeforeSeparator()) {
            $menu->items->add(UXMenuItem::createSeparator());
        }

        $menu->items->add($menuItem);

        if ($command->withSeparator() || $command->withAfterSeparator()) {
            $menu->items->add(UXMenuItem::createSeparator());
        }
    }

    /**
     * @return \php\gui\UXContextMenu
     */
    public function getRoot()
    {
        return $this->root;
    }

    public function doShowing()
    {
        $this->trigger('showing');

        foreach ($this->root->items as $item) {
            if ($item && $item->userData instanceof AbstractMenuCommand) {
                $item->userData->onBeforeShow($item, $this->editor);
            }
        }

        foreach ($this->groups as $menu) {
            foreach ($menu->items as $item) {
                if ($item && $item->userData instanceof AbstractMenuCommand) {
                    $item->userData->onBeforeShow($item, $this->editor);
                }
            }
        }

        uiLater(function () {
            $this->trigger('show');
        });
    }

    public function show(UXNode $node)
    {
        if ($this->root->visible) {
            $this->root->hide();
        }

        uiLater(function () use ($node) {
            $this->root->show($node->form, Mouse::x(), Mouse::y());
        });
    }

    /**
     * Link context menu to node.
     * @param UXNode $node
     */
    public function linkTo(UXNode $node)
    {
        $handle = function (UXMouseEvent $e) use ($node) {
            if ($e->button == 'SECONDARY') {
                $this->show($node);
            }
        };

        $node->on('keyUp', function (UXKeyEvent $e) {
            foreach ($this->root->items as $item) {
                if ($item && $item->userData instanceof AbstractCommand) {
                    if ($e->matches($item->userData->getAccelerator())) {
                        $item->userData->onExecute($e, $this->editor);
                        break;
                    }
                }
            }

            foreach ($this->groups as $menu) {
                foreach ($menu->items as $item) {
                    if ($item && $item->userData && $e->matches($item->userData->getAccelerator())) {
                        $item->userData->onExecute($e, $this->editor);
                        break;
                    }
                }
            }
        }, __CLASS__);


        $node->on('click', $handle, __CLASS__ . '#contextMenu');
    }

    /**
     * @param string $text
     * @param mixed $icon
     * @param callable|null $onClick
     * @return UXMenuButton
     */
    public function makeButton(string $text = '', UXNode $icon = null, callable $onClick = null)
    {
        $button = new UXSplitMenuButton($text, $icon);

        /** @var UXMenuItem $item */
        foreach ($this->getRoot()->items as $item) {
            if (!$item) {
                $button->items->add(UXMenuItem::createSeparator());
            } else {
                $button->items->add($item);
            }
        }

        $button->maxHeight = 999;
        $button->style = UiUtils::fontSizeStyle() . "; ";

        $button->observer('showing')->addListener(function ($_, $value) use ($button) {
            if ($value) {
                $this->trigger('showing');

                foreach ($button->items as $item) {
                    if ($item && $item->userData instanceof AbstractMenuCommand) {
                        $item->userData->onBeforeShow($item, $this->editor);
                    }
                }

                uiLater(function () { $this->trigger('show'); });
            }
        });

        if ($onClick) {
            $button->on('action', $onClick);
        }

        return $button;
    }
}