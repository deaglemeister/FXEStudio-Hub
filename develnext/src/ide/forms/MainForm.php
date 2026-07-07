<?php
namespace ide\forms;

use ide\commands\tree\TreeCreateDirectoryCommand;
use ide\commands\tree\TreeCreateFileCommand;
use ide\Ide;
use ide\IdeConfigurable;
use ide\IdeException;
use ide\Logger;
use ide\project\TreeIconResolver;
use ide\systems\FxeBottomConsoleSystem;
use ide\systems\FxeIndexingStatusSystem;
use ide\systems\FxeProjectTabsSystem;
use ide\systems\FileSystem;
use ide\ui\FxeToast;
use ide\utils\UiUtils;
use php\gui\designer\FxeMainWindowChrome;
use php\gui\designer\UXDirectoryTreeView;
use php\gui\dock\UXDockPane;
use php\gui\event\UXEvent;
use php\gui\event\UXKeyEvent;
use php\gui\layout\UXAnchorPane;
use php\gui\layout\UXHBox;
use php\gui\layout\UXVBox;
use php\gui\UXDndTabPane;
use php\gui\UXButton;
use php\gui\UXImage;
use php\gui\UXImageView;
use php\gui\UXLabel;
use php\gui\UXMenu;
use php\gui\UXMenuBar;
use php\gui\UXMenuButton;
use php\gui\UXMenuItem;
use php\gui\UXNode;
use php\gui\UXScreen;
use php\gui\UXSplitPane;
use php\gui\UXTabPane;
use php\gui\UXTreeView;
use php\lib\fs;
use php\lib\str;

/**
 * @property UXTabPane $fileTabPane
 * @property UXTabPane $projectTabs
 * @property UXVBox $properties
 * @property UXSplitPane $contentSplit
 * @property UXAnchorPane $directoryTree
 * @property UXTreeView $projectTree
 * @property UXHBox $headPane
 * @property UXHBox $headRightPane
 * @property UXVBox $contentVBox
 * @property UXSplitPane $splitTree
 *
 * @property UXDockPane $dockPane
 * @property UXHBox $statusPane
 * @property UXHBox $titleBarRow
 * @property UXHBox $titleBarTools
 * @property UXLabel $projectTitleLabel
 */
class MainForm extends AbstractIdeForm
{
    use IdeConfigurable;

    const MIN_WIDTH = 800;
    const MIN_HEIGHT = 500;

    /**
     * @var UXMenuBar
     */
    public $mainMenu;

    /** @var UXHBox */
    public $titleBarRow;

    /** @var UXHBox */
    public $titleBarTools;

    /** @var UXHBox */
    public $projectTabsBar;

    /** @var UXMenuButton */
    public $mainMenuButton;

    /** @var bool */
    protected $chromeInstalled = false;

    /**
     * MainForm constructor.
     */
    public function __construct()
    {
        parent::__construct();

        foreach ($this->contentVBox->children as $one) {
            if ($one instanceof UXMenuBar) {
                $this->mainMenu = $one;
                break;
            }
        }

        if (!$this->mainMenu) {
            throw new IdeException("Cannot find main menu on main form");
        }

        $this->buildTitleBarRow();
    }

    protected function buildTitleBarRow()
    {
        if ($this->titleBarRow) {
            return;
        }

        $this->contentVBox->children->remove($this->mainMenu);

        $logo = new UXImageView();
        $logo->image = new UXImage('res://.data/img/DevelNextIco.png');
        $logo->fitWidth = 16;
        $logo->fitHeight = 16;
        $logo->preserveRatio = true;
        $logo->classes->add('fxe-main-logo');

        $this->projectTabsBar = new UXHBox();
        $this->projectTabsBar->id = 'projectTabsBar';
        $this->projectTabsBar->classes->add('fxe-project-tabs-bar');
        $this->projectTabsBar->spacing = 4;
        $this->projectTabsBar->alignment = 'CENTER_LEFT';
        $this->projectTabsBar->prefHeight = 34;
        $this->projectTabsBar->minHeight = 34;
        $this->projectTabsBar->maxHeight = 34;

        $titleBarSpacer = new UXHBox();
        UXHBox::setHgrow($titleBarSpacer, 'ALWAYS');

        $this->titleBarTools = new UXHBox();
        $this->titleBarTools->id = 'titleBarTools';
        $this->titleBarTools->classes->add('fxe-main-titlebar-tools');
        $this->titleBarTools->alignment = 'CENTER_RIGHT';
        $this->titleBarTools->spacing = 4;

        $this->mainMenu->visible = false;
        $this->mainMenu->managed = false;
        $this->mainMenu->prefHeight = 0;
        $this->mainMenu->maxHeight = 0;

        $this->mainMenuButton = new UXMenuButton('');
        $this->mainMenuButton->id = 'mainMenuButton';
        $this->mainMenuButton->classes->add('fxe-main-menu-button');
        $this->mainMenuButton->tooltipText = 'Меню';
        $this->mainMenuButton->text = '▾';

        $this->titleBarRow = new UXHBox([
            $logo,
            $this->projectTabsBar,
            $titleBarSpacer,
            $this->titleBarTools,
            $this->mainMenuButton,
        ]);
        $this->titleBarRow->id = 'titleBarRow';
        $this->titleBarRow->classes->add('fxe-main-titlebar');
        $this->titleBarRow->alignment = 'CENTER_LEFT';
        $this->titleBarRow->spacing = 8;
        $this->titleBarRow->prefHeight = 34;
        $this->titleBarRow->minHeight = 34;
        $this->titleBarRow->maxHeight = 34;
        $this->titleBarRow->maxWidth = 1.7976931348623157E308;

        UXHBox::setHgrow($this->projectTabsBar, 'NEVER');
        UXHBox::setHgrow($this->titleBarTools, 'NEVER');
        UXHBox::setHgrow($this->mainMenuButton, 'NEVER');

        $this->headPane->managed = false;
        $this->headPane->visible = false;
        $this->headPane->prefHeight = 0;
        $this->headPane->minHeight = 0;
        $this->headPane->maxHeight = 0;

        $this->contentVBox->children->insert(0, $this->titleBarRow);
        // Скрытый контейнер: сюда defineMenuGroup добавляет пункты, sync переносит их в MenuButton.
        $this->contentVBox->children->insert(1, $this->mainMenu);
    }

    protected function syncMainMenuButton()
    {
        if (!$this->mainMenuButton || !$this->mainMenu) {
            return;
        }

        $pending = [];

        /** @var UXMenu $menu */
        foreach ($this->mainMenu->menus as $menu) {
            if (!$menu->visible || $menu->id === 'menuCreate') {
                continue;
            }

            $pending[] = $menu;
        }

        foreach ($pending as $menu) {
            $this->mainMenu->menus->remove($menu);

            $exists = false;

            foreach ($this->mainMenuButton->items as $item) {
                if ($item === $menu) {
                    $exists = true;
                    break;
                }
            }

            if (!$exists) {
                $this->mainMenuButton->items->add($menu);
            }
        }
    }

    protected function updateWindowTitle()
    {
        $title = 'FXE Studio';
        $project = Ide::project();

        if ($project) {
            $title = $project->getName() . ' — FXE Studio';
        }

        $this->title = $title;
    }

    /**
     * @param $string
     * @return null|UXMenu
     */
    public function findSubMenu($string)
    {
        /** @var UXMenu $one */
        foreach ($this->mainMenu->menus as $one) {
            if ($one->id == $string) {
                return $one;
            }
        }

        if ($this->mainMenuButton) {
            foreach ($this->mainMenuButton->items as $one) {
                if ($one instanceof UXMenu && $one->id == $string) {
                    return $one;
                }
            }
        }

        return null;
    }

    protected function init()
    {
        parent::init();

        $this->opacity = 0.01;

        Ide::get()->on('start', function () {
            $this->opacity = 1;
            $this->syncMainMenuButton();
            uiLater(function () {
                FxeProjectTabsSystem::init($this->projectTabsBar);
            });
        });

        $mainMenu = $this->mainMenu; // FIX!!!!! see FixSkinMenu

        $pane = new UXDndTabPane();

        $parent = $this->fileTabPane->parent;
        $this->fileTabPane->free();

        /** @var UXTabPane $tabPane */
        $tabPane = $pane ?: new UXTabPane();
        $tabPane->id = 'fileTabPane';
        $tabPane->tabClosingPolicy = 'ALL_TABS';
        $tabPane->classes->add('dn-file-tab-pane');

        // todo fix bug
        /*$tabPane->on('keyDown', $keyDown = function (UXKeyEvent $e) {
            if ($e->controlDown && $e->codeName == 'Tab') {
                $e->consume();
                FileSystem::openNext();
            }
        });*/

        if ($pane) {
            UXAnchorPane::setAnchor($pane, 0);
            $pane->classes->add('fxe-workspace-pane');
            $parent->add($pane);

            // fix bug
            // $pane->on('keyDown', $keyDown);
        } else {
            $parent->classes->add('fxe-workspace-pane');
            $parent->add($tabPane);
        }

        $this->splitTree->classes->add('fxe-workspace-split');
        $this->fileTabPane->classes->add('fxe-workspace-tabs');
        if ($this->splitTree->parent) {
            $this->splitTree->parent->classes->add('fxe-workspace-host');
        }

        $this->directoryTree->classes->add('fxe-panel-host');

        $shell = new UXVBox();
        $shell->classes->add('fxe-panel-shell');
        $shell->maxHeight = 99999;

        $card = new UXVBox();
        $card->spacing = 0;
        $card->classes->add('fxe-panel-card');

        $panelTitle = new UXLabel('Проект');
        $panelTitle->classes->add('fxe-panel-title');

        $tree = new UXDirectoryTreeView();
        $tree->editable = true;
        $tree->style = UiUtils::fontSizeStyle();
        $tree->classes->add('fxe-panel-tree');

        $createFileButton = new UXButton();
        $createFileButton->text = '';
        $createFileButton->classes->add('fxe-panel-header-action');

        $createDirectoryButton = new UXButton();
        $createDirectoryButton->text = '';
        $createDirectoryButton->classes->add('fxe-panel-header-action');

        uiLater(function () use ($createFileButton, $createDirectoryButton) {
            $createFileButton->graphic = TreeIconResolver::loadIcon('addFile');
            $createDirectoryButton->graphic = TreeIconResolver::loadIcon('newFolder');
        });

        $ensureProjectTreeSelection = function () use ($tree) {
            $project = Ide::project();
            if (!$project) {
                return null;
            }

            $projectTree = $project->getTree();
            if (!$projectTree->hasSelectedPath()) {
                if ($tree->root) {
                    $tree->focusedItem = $tree->root;
                    $tree->selectedItems = [$tree->root];
                }
            }

            if (!$projectTree->hasSelectedPath()) {
                Ide::toast('Выберите папку или файл в дереве проекта');
                return null;
            }

            return $projectTree;
        };

        $createFileButton->on('click', function () use ($ensureProjectTreeSelection) {
            if ($projectTree = $ensureProjectTreeSelection()) {
                (new TreeCreateFileCommand($projectTree))->onExecute();
            }
        });

        $createDirectoryButton->on('click', function () use ($ensureProjectTreeSelection) {
            if ($projectTree = $ensureProjectTreeSelection()) {
                (new TreeCreateDirectoryCommand($projectTree))->onExecute();
            }
        });

        $headerSpacer = new UXHBox();
        UXHBox::setHgrow($headerSpacer, 'ALWAYS');

        $panelHeader = new UXHBox([$panelTitle, $headerSpacer, $createFileButton, $createDirectoryButton]);
        $panelHeader->classes->add('fxe-panel-header');
        $panelHeader->alignment = 'CENTER_LEFT';
        $panelHeader->spacing = 4;
        $card->children->add($panelHeader);

        $card->children->add($tree);
        UXVBox::setVgrow($tree, 'ALWAYS');

        $shell->children->add($card);
        UXVBox::setVgrow($card, 'ALWAYS');

        $this->directoryTree->add($shell);
        UXAnchorPane::setAnchor($shell, 0);

        Ide::get()->bind('shutdown', function () {
            $this->ideConfig()->set("splitTree.dividerPositions", $this->splitTree->dividerPositions);
            FileSystem::saveEditorSplitConfig();
            FxeBottomConsoleSystem::persistConsoleHeight();

            $windowKey = get_class($this);
            Ide::get()->setUserConfigValue($windowKey . '.maximized', $this->maximized);

            if (!$this->maximized && !$this->iconified) {
                Ide::get()->setUserConfigValue($windowKey . '.width', $this->width);
                Ide::get()->setUserConfigValue($windowKey . '.height', $this->height);
                Ide::get()->setUserConfigValue($windowKey . '.x', $this->x);
                Ide::get()->setUserConfigValue($windowKey . '.y', $this->y);
            }
        });

        FileSystem::loadEditorSplitConfig();

        Ide::get()->bind('openProject', function () use ($tree, $panelTitle) {
            $project = Ide::project();
            $panelTitle->text = $project->getName();
            TreeIconResolver::warmUp();

            $project->getTree()->setView($tree);

            $tree->treeSource = $project->getTree()->createSource();

            $tree->root->expanded = true;
            $project->getConfig()->loadTreeState($project->getTree());

            $this->updateWindowTitle();
        });

        Ide::get()->bind('afterCloseProject', function () use ($tree, $panelTitle) {
            $panelTitle->text = 'Проект';
            if ($tree->treeSource) {
                $tree->treeSource->shutdown();
                $tree->treeSource = null;
            }

            $this->updateWindowTitle();
        });

        $this->on('keyDown', function (UXKeyEvent $e) {
            if ($e->controlDown && str::upper($e->codeName) === 'W') {
                $e->consume();
                FileSystem::closeSelectedTab();
            }
        });

        FxeBottomConsoleSystem::install($this);
        FxeIndexingStatusSystem::install($this);
    }

    public function installWindowChrome()
    {
        if ($this->chromeInstalled || !$this->titleBarRow) {
            return;
        }

        try {
            FxeMainWindowChrome::apply($this, $this->titleBarRow);
            $this->chromeInstalled = true;
            Logger::info('FxeMainWindowChrome applied');
        } catch (\Throwable $e) {
            Logger::exception('FxeMainWindowChrome apply failed', $e);
        }
    }

    /**
     * @param string $id
     * @param string $text
     * @param bool $prepend
     * @return UXMenu
     * @throws IdeException
     */
    public function defineMenuGroup($id, $text, $prepend = false)
    {
        $id = str::upperFirst($id);

        $menu = null;

        foreach ($this->mainMenu->menus as $one) {
            if ($one->id == "menu$id") {
                $menu = $one;
                break;
            }
        }

        if ($menu == null) {
            $menu = new UXMenu($text);
            $menu->id = "menu$id";

            if ($prepend) {
                $this->mainMenu->menus->insert(0, $menu);
            } else {
                $this->mainMenu->menus->add($menu);
            }
        } else {
            $menu->text = $text;
        }

        return $menu;
    }

    /**
     * @event showing
     */
    public function doShowing()
    {
        Ide::get()->getL10n()->translateNode($this->mainMenu);

        if ($this->mainMenuButton) {
            Ide::get()->getL10n()->translateNode($this->mainMenuButton);
        }

        $this->syncMainMenuButton();
    }

    public function show()
    {
        $this->updateWindowTitle();

        parent::show();
        Logger::info("Show main form ...");

        $ideLanguage = Ide::get()->getLanguage();

        $menu = $this->findSubMenu('menuL10n');
        $menu->items->clear();

        if ($ideLanguage) {
            $menu->graphic = Ide::get()->getImage(new UXImage($ideLanguage->getIcon()));
            $menu->text = 'Language';
        }

        foreach (Ide::get()->getLanguages() as $language) {
            $item = new UXMenuItem($language->getTitle(), Ide::get()->getImage(new UXImage($language->getIcon())));

            if ($language->getTitle() != $language->getTitleEn()) {
                $item->text .= ' (' . $language->getTitleEn() . ')';
            }

            //$item->enabled = !$ideLanguage || $language->getCode() != $ideLanguage->getCode();

            $item->on('action', function () use ($language, $item, $menu) {
                $msg = new MessageBoxForm($language->getRestartMessage(), [$language->getRestartYes(), $language->getRestartNo()]);
                $msg->makeWarning();
                $msg->showDialog();

                $menu->graphic = Ide::get()->getImage(new UXImage($language->getIcon()));
                Ide::get()->setUserConfigValue('ide.language', $language->getCode());

                if ($msg->getResultIndex() == 0) {
                    Ide::get()->restart();
                }
            });

            $menu->items->add($item);
        }

        $screen = UXScreen::getPrimary();
        $windowKey = get_class($this);
        $ideUserConfig = Ide::get()->getUserConfig('ide');
        $windowConfigured = $ideUserConfig->has($windowKey . '.maximized')
            || $ideUserConfig->has($windowKey . '.width');

        $this->minWidth = self::MIN_WIDTH;
        $this->minHeight = self::MIN_HEIGHT;

        if (!$windowConfigured) {
            $this->maximized = true;
            $this->centerOnScreen();
            Ide::get()->setUserConfigValue($windowKey . '.maximized', true);
        } else {
            $this->width = max(self::MIN_WIDTH, Ide::get()->getUserConfigValue($windowKey . '.width', $screen->bounds['width'] * 0.75));
            $this->height = max(self::MIN_HEIGHT, Ide::get()->getUserConfigValue($windowKey . '.height', $screen->bounds['height'] * 0.75));

            if ($this->width < self::MIN_WIDTH || $this->height < self::MIN_HEIGHT) {
                $this->width = max(self::MIN_WIDTH, $screen->bounds['width'] * 0.75);
                $this->height = max(self::MIN_HEIGHT, $screen->bounds['height'] * 0.75);
            }

            $this->centerOnScreen();

            $this->x = Ide::get()->getUserConfigValue($windowKey . '.x', 0);
            $this->y = Ide::get()->getUserConfigValue($windowKey . '.y', 0);

            if ($this->x > $screen->visualBounds['width'] - 10 || $this->y > $screen->visualBounds['height'] - 10 ||
                $this->x < -999 || $this->y < -999) {
                $this->x = $this->y = 50;
            }

            $this->maximized = (bool) Ide::get()->getUserConfigValue($windowKey . '.maximized', false);
        }

        $this->observer('maximized')->addListener(function ($old, $new) {
            Ide::get()->setUserConfigValue(get_class($this) . '.maximized', (bool) $new);
        });

        /*$this->contentSplitPane->items[0]->observer('width')->addListener(function ($old, $new) {
            Ide::get()->setUserConfigValue(get_class($this) . '.dividerPositions', $new);
        });*/

        foreach (['width', 'height', 'x', 'y'] as $prop) {
            $this->observer($prop)->addListener(function ($old, $new) use ($prop) {
                if ($this->iconified) {
                    return;
                }

                if (!$this->maximized) {
                    Ide::get()->setUserConfigValue(get_class($this) . '.' . $prop, $new);
                }

                //Ide::get()->setUserConfigValue(get_class($this) . '.dividerPositions', $this->contentSplitPane->dividerPositions);
            });
        }

        uiLater(function () {
            if ($this->ideConfig()->has('splitTree.dividerPositions')) {
                $this->splitTree->dividerPositions = $this->ideConfig()->getArray('splitTree.dividerPositions', [0.3]);
            }

            $this->installWindowChrome();
            uiLater(function () {
                FxeMainWindowChrome::refreshHitTest($this);
                FxeProjectTabsSystem::refresh();
            });
        });
    }

    /**
     * @event close
     *
     * @param UXEvent $e
     *
     * @throws \Exception
     * @throws \php\io\IOException
     */
    public function doClose(UXEvent $e = null)
    {
        Logger::info("Close main form ...");

        $project = Ide::get()->getOpenedProject();

        if ($project) {
            $dialog = new MessageBoxForm(_('exit.project.message', $project->getName()), [
                'yes' => _('exit.project.yes'),
                'no'  => _('exit.project.no'),
                'abort' => _('exit.project.abort')
            ]);
            $dialog->title = _('exit.project.title');

            if ($dialog->showDialog()) {
                $result = $dialog->getResult();

                if ($result == 'yes') {
                    Logger::info("Remember the last project = yes!");

                    Ide::get()->setUserConfigValue('lastProject', $project->getProjectFile());
                } elseif ($result == 'abort') {
                    if ($e) {
                        $e->consume();
                    }
                    return;
                } else {
                    Logger::info("Cancel closing main form.");
                    Ide::get()->setUserConfigValue('lastProject', null);
                }

                Ide::get()->shutdown();
            } else {
                if ($e) {
                    $e->consume();
                }
            }
        } else {
            Ide::get()->setUserConfigValue('lastProject', null);

            $dialog = new MessageBoxForm(_('exit.message'), [_('exit.yes'), _('exit.no')]);
            if ($dialog->showDialog() && $dialog->getResultIndex() == 0) {
                $this->hide();

                Ide::get()->shutdown();
            } else {
                if ($e) {
                    $e->consume();
                }
            }
        }
    }

    /**
     * @return UXHBox
     */
    public function getHeadPane()
    {
        return $this->headPane;
    }

    /**
     * @return UXHBox
     */
    public function getHeadRightPane()
    {
        return $this->headRightPane;
    }

    /**
     * @return UXHBox
     */
    public function getTitleBarToolsPane()
    {
        return $this->titleBarTools;
    }

    /**
     * @return UXTreeView
     */
    public function getProjectTree()
    {
        return $this->projectTree;
    }

    public function hideBottom()
    {
        FxeBottomConsoleSystem::hide();
    }

    public function showBottom(UXNode $content = null)
    {
        if ($content === null) {
            $this->hideBottom();
            return;
        }

        FxeBottomConsoleSystem::showTerminal($content);
    }

    /**
     * @param bool|null $visible
     * @return bool
     */
    public function toggleBottomConsole($visible = null)
    {
        return FxeBottomConsoleSystem::toggle($visible);
    }

    /**
     * @return bool
     */
    public function isBottomConsoleVisible()
    {
        return FxeBottomConsoleSystem::isVisible();
    }

    /**
     * @param string $message
     * @param int $timeout
     */
    public function toast($message, $timeout = 0)
    {
        FxeToast::message($message, $timeout);
    }
}