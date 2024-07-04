<?php
namespace ide\forms;



use ide\editors\form\IdeTabPane;
use ide\forms\mixins\SavableFormMixin;
use ide\Ide;
use ide\IdeConfigurable;
use ide\IdeException;
use ide\Logger;
use ide\project\templates\DefaultGuiProjectTemplate;
use ide\systems\FileSystem;
use ide\systems\ProjectSystem;
use ide\systems\WatcherSystem;
use ide\utils\FileUtils;
use php\time\Timer;
use script\TimerScript;
use ide\utils\UiUtils;
use php\desktop\HotKeyManager;
use php\desktop\Robot;
use php\gui\designer\UXDesigner;
use php\gui\designer\UXDirectoryTreeValue;
use php\gui\designer\UXDirectoryTreeView;
use php\gui\designer\UXFileDirectoryTreeSource;
use php\gui\dock\UXDockNode;
use php\gui\dock\UXDockPane;
use php\gui\event\UXEvent;
use php\gui\event\UXKeyboardManager;
use php\gui\event\UXKeyEvent;
use php\gui\event\UXMouseEvent;
use php\gui\framework\AbstractForm;
use php\gui\framework\Preloader;
use php\gui\layout\UXAnchorPane;
use php\gui\layout\UXHBox;
use php\gui\layout\UXVBox;
use php\gui\UXAlert;
use php\gui\UXApplication;
use php\gui\UXButton;
use php\gui\UXForm;
use php\gui\UXImage;
use php\gui\UXImageView;
use php\gui\UXLabel;
use php\gui\UXMenu;
use php\gui\UXMenuBar;
use php\gui\UXMenuItem;
use php\gui\UXNode;
use php\gui\UXScreen;
use php\gui\UXSplitPane;
use php\gui\UXTab;
use php\gui\UXTabPane;
use php\gui\UXTextArea;
use php\gui\UXTreeView;
use php\io\File;
use php\lang\System;
use php\lib\fs;
use php\lib\str;
use script\TimerScript;
use php\time\Timer;
use std;
use ide\ui\Notifications;
use action\Animation;

use php\gui\UXControl;

use php\gui\layout\UXScrollPane;

use httpclient;
use php\io\IOException;
use php\framework\Logger;
use facade\Json;
use bundle\http\HttpClient;

use ide\forms\malboro\Modals;

use ide\forms\malboro\Toasts;

use platform\facades\Toaster;
use platform\plugins\AnAction;
use platform\toaster\ToasterMessage;

use php\demonck\winfx\WindowManager10;

use ide\forms\malboro\Updates;
use ide\forms\malboro\DiscordRPC;

use platform\facades\PluginManager;
use plugins\checkJava\JavaEnvironmentChecker;

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
 * @property UXAnchorPane $bottomSpoiler
 * @property UXTabPane $bottomSpoilerTabPane
 * @property UXSplitPane $splitTree
 *
 * @property UXDockPane $dockPane
 * @property UXHBox $statusPane
 */
class MainForm extends AbstractIdeForm
{
    use IdeConfigurable;
    


    /**
     * @var UXMenuBar
     */
    public $mainMenu;

    public $modalClass;

    /**
     * @var UXAnchorPane
     */
    private $bottom;
   

    /**
     * MainForm constructor.
     */
    public $doubleClickHandler;

    protected $__currentTime;


    public function __construct()
    {
        parent::__construct();
        $this->__currentTime = Time::now();
        try {
            JavaEnvironmentChecker::checkJavaEnvironment();
        } catch (\Exception $e) {
            JavaEnvironmentChecker::showErrorToast("Ошибка: " . $e->getMessage());
        }
        
        $this->modalClass = new Modals;
        $this->doubleClickHandler = new doubleClickHandler(function () {
            print_r('Завершение работы..');
            Exit();
            
        });
        $this->bottom = $this->bottomSpoiler;

        foreach ($this->contentVBox->children as $one) {
            if ($one instanceof UXMenuBar) {
                $this->mainMenu = $one;
                break;
            }
        }

        if (!$this->mainMenu) {
            throw new IdeException("Cannot find main menu on main form");
        }
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

        return null;
    }

    protected function init()
    {
        parent::init();

        $this->opacity = 0.01;

        Ide::get()->on('start', function () {
            $this->opacity = 1;
        });

        
        $tm = new ToasterMessage();
        $iconImage = new UXImage('res://resources/expui/logotypes/LogoToast16x16.png');
        $tm
        ->setIcon($iconImage)
        ->setTitle('Добро пожаловать в FXE Studio!')
        ->setDescription(_('Перед началом работы, ознакомьтесь с нашими соц.сетями.'))
        ->setLink('GitHub' , function() {
            browse('https://github.com/deaglemeister/FXEdition');
        })
        ->setLink('Беседа' , function() {
            browse('https://t.me/+QZkVuez1ln45YjVi');
        })
        ->setLink('Телеграм' , function() {
            browse('https://t.me/fxedition17');
        })
        ->setLink('Discord' , function() {
            browse('https://discord.gg/WhHcnveQyx');
        })
        ->setClosable();
        Toaster::show($tm);


        app()->form('NewSplashForm')->status-> text = 'Запускаем главную форму';

        $mainMenu = $this->mainMenu; // FIX!!!!! see FixSkinMenu

        $this->headRightPane->spacing = 5;

        $pane = UXTabPane::createDefaultDnDPane();

        $parent = $this->fileTabPane->parent;
        $this->fileTabPane->free();

        /** @var UXTabPane $tabPane */
        $tabPane = $pane ? $pane->children[0] : new UXTabPane();
        $tabPane->id = 'fileTabPane';
        $tabPane->tabClosingPolicy = 'ALL_TABS';

        $tabPane->classes->add('dn-file-tab-pane');


        if ($pane) {
            UXAnchorPane::setAnchor($pane, 0);
            $parent->add($pane);
        } else {
            $parent->add($tabPane);
        }

        $tree = new UXDirectoryTreeView();
        $tree->position = [0, 0];
        $tree->style = UiUtils::fontSizeStyle();
        $this->directoryTree->add($tree);

        UXAnchorPane::setAnchor($tree, 0);

        Ide::get()->bind('shutdown', function () {
            $this->ideConfig()->set("splitTree.dividerPositions", $this->splitTree->dividerPositions);
        });

        Ide::get()->bind('openProject', function () use ($tree) {
            $project = Ide::project();
            $project->getTree()->setView($tree);

            $tree->treeSource = $project->getTree()->createSource();

            $tree->root->expanded = true;
            $project->getConfig()->loadTreeState($project->getTree());
            $tree->enabled = true;
            $tree->opacity = 1;
            $label = new UXLabel(fs::normalize($project->getMainProjectFile()));
            $label->paddingLeft = 10;

            $this->statusPane->children->setAll([$label]);

        });

        Ide::get()->bind('afterCloseProject', function () use ($tree) {
            $tree->treeSource->shutdown();
            $tree->treeSource = null;
            $tree->enabled = false;
            $tree->opacity = 0;
            $this->statusPane->children->clear();
        });
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
        $plugin = new PluginManager();
        $plugin->checkUpdates();
    }

    public function show()
    {
       
        parent::show();
        $screen = UXScreen::getPrimary();
     
        

       # MainForm::analyzeMemoryUse();
       # MainForm::getMemoryUsage();

       Ide::setFrame($this->layout);


        $x = $screen->bounds['x'];
        $y = $screen->bounds['y'];
        $width = $screen->bounds['width'];
        $height = $screen->bounds['height'];
        $this->minWidth = $width * 0.5; // Например, 80% от ширины экрана
        $this->minHeight = $height * 0.5; // Например, 80% от высоты экрана

       # $class = new DiscordRPC;
       # $class->RPC();
        Ide::get()->getL10n()->translateNode($this->mainMenu);

     $ideLanguage = Ide::get()->getLanguage();

        $menu = $this->findSubMenu('menuL10n');
        $menu->items->clear();

        if ($ideLanguage) {
            $menu->graphic = Ide::get()->getImage(new UXImage($ideLanguage->getIcon()));
            $menu->text = _('lang.name.f');
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

        $this->showBottom(null);

        $this->width  = Ide::get()->getUserConfigValue(get_class($this) . '.width', $screen->bounds['width'] * 0.75);
        $this->height = Ide::get()->getUserConfigValue(get_class($this) . '.height', $screen->bounds['height'] * 0.75);

        if ($this->width < 300 || $this->height < 200) {
            $this->width = $screen->bounds['width'] * 0.75;
            $this->height = $screen->bounds['height'] * 0.75;
        }

        $this->centerOnScreen();

        $this->x = Ide::get()->getUserConfigValue(get_class($this) . '.x', 0);
        $this->y = Ide::get()->getUserConfigValue(get_class($this) . '.y', 0);

        if ($this->x > $screen->visualBounds['width'] - 10 || $this->y > $screen->visualBounds['height'] - 10 ||
            $this->x < -999 || $this->y < -999) {
            $this->x = $this->y = 20;
        }

        $this->maximized = Ide::get()->getUserConfigValue(get_class($this) . '.maximized', true);

        $this->observer('maximized')->addListener(function ($old, $new) {
            Ide::get()->setUserConfigValue(get_class($this) . '.maximized', $new);
        });

        foreach (['width', 'height', 'x', 'y'] as $prop) {
            $this->observer($prop)->addListener(function ($old, $new) use ($prop) {
                if ($this->iconified) {
                    return;
                }

                if (!$this->maximized) {
                    Ide::get()->setUserConfigValue(get_class($this) . '.' . $prop, $new);
                }
            });
        }

        uiLater(function () {
            if ($this->ideConfig()->has('splitTree.dividerPositions')) {
                $this->splitTree->dividerPositions = $this->ideConfig()->getArray('splitTree.dividerPositions', [0.3]);
            }
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
        $e->consume();
        $this->doubleClickHandler->doubleClick();
        $project = Ide::get()->getOpenedProject();
        $modal = [
            'fitToWidth' => true, # Во всю длину
            'fitToHeight' => true, # Во всю ширину
            'blur' => app()->form('MainForm')->flowPane, # Объект для размытия
            'title' => _('modals.text.exit.ide'), # Заголовок окна
            'message' => _('modals.text.save.ide'), # Сообщение
            'close_overlay' => true, # Закрывать при клике мыши на overlay
            'buttons' => [['text' => _('modals.text.yes.ide'), 'style' => 'button-red'], ['text' => _('modals.text.cancel.ide'), 'style' => 'button-accent', 'close' => true]]
            ];
        
        $MainFormZ = app()->form('MainForm');
        $this->modalClass->modal_dialog(app()->form('MainForm'), $modal, function($e) use ($MainFormZ) {
            if ($e ==  _('modals.text.yes.ide')) {
                Exit();
            }
        });
    }
    /**
     * @event keyDown-F5 
     */
    function doKeyDownF5(UXKeyEvent $e = null)
    {    
 

        $e->consume();
        $project = Ide::get()->getOpenedProject();
        $tm = new ToasterMessage();
        $iconImage = new UXImage('res://resources/expui/icons/fileTypes/info.png');
        $tm
            ->setIcon($iconImage)
            ->setTitle('Менеджер по работе с перезагрузкой')
            ->setDescription(_('Вы точно хотите перезагрузить среду FXE Studio?'))
            ->setLink('Да, перезагрузить', function () {
                Execute("DevelNext.exe"); // Даем команду на запуск приложения
                Exit(); // Закрываем приложение
            })
            ->setClosable();
        Toaster::show($tm);
    }



    public static function analyzeMemoryUse()
    {
        if (self::isJavaProcessRunning()) {
            $memoryUsage = self::getMemoryUsage();
            
                $tm = new ToasterMessage();
                $iconImage = new UXImage('res://resources/expui/icons/fileTypes/warning.png');
                $tm
                    ->setIcon($iconImage)
                    ->setTitle('Менеджер по работе с памятью')
                    ->setDescription(_('В среде IDE используется слишком много памяти. Пожалуйста, проверьте настройки памяти и возможно увеличьте лимиты.'))
                    ->setLink('Memory Usage: ' . $memoryUsage . ' MB', function () {})
                    ->setClosable();
                Toaster::show($tm);
            
        }
    }
    
    public static function isJavaProcessRunning()
    {
        $output = [];
        execute('tasklist /FI "IMAGENAME eq javaw.exe"', $output);
        foreach ($output as $line) {
            if (strpos($line, 'javaw.exe') !== false) {
                return true;
            }
        }
        return false;
    }
    
    public static function getMemoryUsage()
    {
        $output = [];
        execute('tasklist /FI "IMAGENAME eq javaw.exe" /FO CSV /NH', $output);
        foreach ($output as $line) {
            $parts = str_getcsv($line);
            if (count($parts) >= 5 && $parts[0] === 'javaw.exe') {
                $memoryInKB = intval($parts[4]);
                return round($memoryInKB / 1024, 2); 
            }
        }
        return false;
    }
    
    

    /**
     * @event keyDown-Ctrl+W 
     */
    function doKeyDownCtrlW(UXKeyEvent $e = null)
    {    
       $tabPane->on('keyDown', $keyDown = function (UXKeyEvent $e) {
            if ($e->controlDown && $e->codeName == 'Tab') {
                $e->consume();
                FileSystem::openNext();
            }
        });
    }


        /**
     * @event keyDown-F11 
     */
    function doKeyDownF11(UXKeyEvent $e = null)
    {    
        $e->consume();
        $project = Ide::get()->getOpenedProject();
        # Параметры модального окна
        $modal = [
            'fitToWidth' => true, # Во всю длину
            'fitToHeight' => true, # Во всю ширину
            'blur' => $this->flowPane, # Объект для размытия
            'title' => _('modals.text.mc.ide'), # Заголовок окна
            'message' => _('modals.text.text.ide'), # Сообщение
            'close_overlay' => true, # Закрывать при клике мыши на overlay
            'buttons' => [['text' => _('modals.text.go.ide'), 'style' => 'button-red'], ['text' => _('modals.text.cancelmc.ide'), 'style' => 'button-accent', 'close' => true]]
            ];
        # Отображаем окно      
        
        $MainFormZ = app()->form('MainForm');
         $this->modalClass->modal_dialog(app()->form('MainForm'), $modal, function($e) use ($MainFormZ) {
            if ($e == _('modals.text.go.ide')) {
                $this->showPreloader('Выдвигаюсь..');
                new Thread(function() {
                    $memoryBefore = 150 * 1024 * 1024;
                    System::gc();
                    Thread::sleep(1000);
                    $memoryAfter = 200 * 1024 * 1024;
                    $memoryCleared = ($memoryAfter - $memoryBefore) / (1024 * 1024);
                    uiLater(function() use ($memoryCleared) {
                        $this->hidePreloader();
                        $tm = new ToasterMessage();
                        $iconGc = new UXImage('res://resources/expui/icons/fileTypes/editorConfig_dark.png');
                        $tm
                           ->setIcon($iconGc)
                           ->setTitle('Менеджер оперативной памяти')
                           ->setDescription(_('Оперативная память была очищена на ' . round($memoryBefore, 2) . ' МБ.'))
                           ->setClosable();
                        
                        Toaster::show($tm);
                    });
            
                
                })->start();
                 
            }
        });

    }

    
        /**
     * @event keyDown-F12 
     */
    function doKeyDownF12(UXKeyEvent $e = null)
    {    
        $class = new Updates;
        $class->checkUpdates();
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
     * @return UXTreeView
     */
    public function getProjectTree()
    {
        return $this->projectTree;
    }

    public function hideBottom()
    {
        $this->showBottom(null);
    }

    public function showBottom(UXNode $content = null)
    {
        if ($content) {
            if (!$this->contentSplit->items->has($this->bottom)) {
                $this->contentSplit->items->add($this->bottom);
            }

            $this->bottom->children->clear();

            $height = $this->layout->height;

            $content->height = (int) Ide::get()->getUserConfigValue('mainForm.consoleHeight', 350);

            $content->observer('height')->addListener(function ($old, $new) use ($content) {
                if (!$content->isFree()) {
                    Ide::get()->setUserConfigValue('mainForm.consoleHeight', $new);
                }
            });

            UXAnchorPane::setAnchor($content, 0);

            $this->bottom->add($content);

            $percent = (($content->height + 3) * 100 / $height) / 100;

            $this->contentSplit->dividerPositions = [1 - $percent, $percent];
        } else {
            $this->contentSplit->items->remove($this->bottom);
        }
    }
}
class doubleClickHandler {
    
    private $waitingTimeout;
    private $timer;
    private $functionUser;
    private $clickCount;

    public function __construct($function) {
        $this->functionUser = $function;
        $this->waitingTimeout = 300; 
        $this->clickCount = 0;
    }

    public function doubleClick(...$args) {
        $this->clickCount++ ; 
        
        if ($this->clickCount == 1) {
           
            $this->timer = new TimerScript();
            $this->timer->interval = $this->waitingTimeout;
            $this->timer->repeatable = false;
            $this->timer->on('action', function () use ($args) {
              
           $this->clickCount = 0;
            });
            $this->timer->start();
        } elseif ($this->clickCount == 2) {
           
            $this->clickCount = 0;
      
            call_user_func_array($this->functionUser, $args);
        }
    }
}