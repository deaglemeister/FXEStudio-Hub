<?php
namespace ide\editors;

use ide\formats\ProjectFormat;
use ide\Ide;
use ide\Logger;
use ide\project\control\AbstractProjectControlPane;
use ide\ui\ListMenu;
use ide\utils\FileUtils;
use php\gui\framework\AbstractForm;
use php\gui\framework\EventBinder;
use php\gui\layout\UXAnchorPane;
use php\gui\layout\UXHBox;
use php\gui\layout\UXVBox;
use php\gui\UXApplication;
use php\gui\UXDesktop;
use php\gui\UXDialog;
use php\gui\UXLabel;
use php\gui\UXListCell;
use php\gui\UXListView;
use php\gui\UXLoader;
use php\gui\UXNode;
use php\gui\UXSeparator;
use php\io\File;
use php\io\ResourceStream;
use php\io\Stream;
use php\lib\arr;
use php\lib\reflect;
use php\lib\str;

/**
 * Class ProjectEditor
 * @package ide\editors
 *
 * @property ProjectFormat $format
 *
 */
class ProjectEditor extends AbstractEditor
{
    /**
     * @var bool
     */
    protected $init = false;

    /**
     * @var AbstractProjectControlPane[]
     */
    protected $controlPanes = [];

    /**
     * @var UXAnchorPane
     */
    protected $contentPane;

    /**
     * @var ListMenu
     */
    protected $menu;

    /**
     * ProjectEditor constructor.
     * @param string $file
     */
    public function __construct($file)
    {
        parent::__construct($file);
    }

    public function addControlPane(AbstractProjectControlPane $pane)
    {
        $this->controlPanes[reflect::typeOf($pane)] = $pane;

        $pane->on('updateCount', function () {
            $this->menu->refresh();
        });
    }

    public function getTitle()
    {
        return 'Проект';
    }

    public function getTabStyle()
    {
        return '-fx-padding: 1px 7px; -fx-font-weight: bold;';
    }

    public function isPrependTab()
    {
        return true;
    }

    public function isCloseable()
    {
        return false;
    }

    public function isDraggable()
    {
        return false;
    }

    public function refresh()
    {
        parent::refresh();

        if ($pane = $this->getOpenedPane()) {
            $pane->refresh();

            $index = $this->menu->selectedIndex;

            $this->menu->items->setAll($this->controlPanes);

            $this->menu->focusedIndex = $this->menu->selectedIndex = $index;
        }
    }

    public function open($param = null)
    {
        parent::open();

        /** @var ProjectFormat $format */
        $format = $this->getFormat();

        $this->controlPanes = [];

        foreach ($format->getControlPanes() as $pane) {
            $this->addControlPane($pane);
        }

        $opened = $this->getOpenedPane();
        $this->menu->items->setAll($this->controlPanes);

        $this->navigate(reflect::typeOf($opened));


        $this->menu->refresh();

        foreach ($this->controlPanes as $pane) {
            $pane->open();
        }

        if (!$this->getOpenedPane()) {
            $this->navigate(arr::firstKey($this->controlPanes));
        }
    }

    public function leave()
    {
        parent::leave();

        if ($pane = $this->menu->selectedItem) {
            $pane->leave();
        }
    }


    public function load()
    {
        foreach ($this->controlPanes as $pane) {
            $pane->load();
        }
    }

    public function save()
    {
        if ($pane = $this->getOpenedPane()) {
            $pane->save();
        }

        /*foreach ($this->controlPanes as $pane) {
            $pane->save();
        }*/
    }

    public function close($save = true)
    {
        parent::close($save);

        if ($pane = $this->getOpenedPane()) {
            $pane->close();
        }
    }

    public function hide()
    {
        if ($pane = $this->getOpenedPane()) {
            $pane->save();
        }
    }

    /**
     * @return AbstractProjectControlPane|null
     */
    public function getOpenedPane()
    {
        return $this->menu->selectedItem;
    }

    public function navigate($paneClass, $setMenu = true)
    {
        if ($pane = $this->controlPanes[$paneClass]) {
            if ($oldPane = $this->menu->selectedItem) {
                $oldPane->leave();
            }

            $ui = $pane->getUi();
            $pane->refresh();

            if ($this->contentPane->children[0] != $ui) {
                UXAnchorPane::setAnchor($ui, 0);
                $this->contentPane->children->setAll([$ui]);
            }

            if ($setMenu) {
                $this->menu->selectedIndex = $this->menu->items->indexOf($pane);
            }

            return $pane;
        }

        return null;
    }

    /**
     * @return UXNode
     */
    public function makeUi()
    {
        $this->controlPanes = [];

        foreach ($this->format->getControlPanes() as $pane) {
            $this->addControlPane($pane);
        }

        $pane = new UXAnchorPane();
        $pane->classes->add('fxe-project-content-host');

        return $this->contentPane = $pane;
    }

    public function makeLeftPaneUi()
    {
        $menu = new ListMenu();
        $menu->classes->add('fxe-project-nav-menu');
        $menu->items->setAll($this->controlPanes);
        $menu->maxWidth = 9999;
        UXVBox::setVgrow($menu, 'ALWAYS');

        $menu->on('action', function () use ($menu) {
            uiLater(function () use ($menu) {
                $this->navigate(reflect::typeOf($menu->selectedItem), false);
            });
        });

        $this->menu = $menu;

        $header = new UXHBox();
        $header->classes->add('fxe-panel-header');
        $header->alignment = 'CENTER_LEFT';
        $header->spacing = 8;

        $panelTitle = new UXLabel('Проект');
        $panelTitle->classes->add('fxe-panel-title');
        $header->children->add($panelTitle);

        $card = new UXVBox([$header, $menu]);
        $card->spacing = 0;
        $card->classes->addAll(['fxe-panel-card', 'fxe-project-nav-card']);
        $card->fillWidth = true;

        $shell = new UXVBox([$card]);
        $shell->classes->addAll(['fxe-panel-shell', 'fxe-project-nav-shell']);
        $shell->maxHeight = 99999;
        $shell->fillWidth = true;

        $host = new UXAnchorPane();
        $host->classes->add('fxe-project-nav-host');
        UXAnchorPane::setAnchor($shell, 0);
        $host->add($shell);

        return $host;
    }
}
