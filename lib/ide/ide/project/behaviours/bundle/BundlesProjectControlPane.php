<?php

namespace ide\project\behaviours\bundle;

use ide\forms\BundleDetailInfoForm;
use ide\forms\InputMessageBoxForm;
use ide\forms\MessageBoxForm;
use ide\Ide;
use ide\IdeConfiguration;
use ide\library\IdeLibraryBundleResource;
use ide\project\behaviours\BundleProjectBehaviour;
use ide\project\control\AbstractProjectControlPane;
use ide\project\Project;
use ide\systems\FileSystem;
use ide\systems\IdeSystem;
use ide\ui\FlowListViewDecorator;
use ide\ui\ImageBox;
use ide\ui\ImageExtendedBox;
use ide\utils\FileUtils;
use php\compress\ZipException;
use php\compress\ZipFile;
use php\gui\event\UXMouseEvent;
use php\gui\layout\UXHBox;
use php\gui\layout\UXVBox;
use php\gui\UXButton;
use php\gui\UXDialog;
use php\gui\UXFileChooser;
use php\gui\UXHyperlink;
use php\gui\UXLabel;
use php\gui\UXNode;
use php\gui\UXSeparator;
use php\gui\UXTextField;
use php\io\IOException;
use php\io\Stream;
use php\lang\Thread;
use php\lib\fs;
use php\lib\str;
use php\gui\event\UXMouseEvent;
use php\gui\layout\UXHBox;
use php\gui\layout\UXVBox;
use php\gui\UXButton;
use php\gui\UXImage;
use php\gui\UXLabel;
use php\gui\UXNode;
use php\lib\fs;
use php\lib\str;


use ide\forms\malboro\Modals;

use platform\facades\Toaster;
use platform\toaster\ToasterMessage;
use php\gui\UXImage;

use httpclient;
use php\io\IOException;

class BundlesProjectControlPane extends AbstractProjectControlPane
{

    /**
     * @var array
     */
    protected $groups = [
        'all' => 'Все',
        'game' => 'Игра',
        'network' => 'Интернет, сеть',
        'database' => 'Данные',
        'system' => 'Система',
        'other' => 'Другое',
    ];

    protected $groupIcons = [
        'all' => 'icons/all16.png',
        'game' => 'icons/gameMonitor16.png',
        'network' => 'icons/web16.png',
        'database' => 'icons/database16.png',
        'system' => 'icons/system16.png',
        'other' => 'icons/blocks16.png',
    ];

    /**
     * @var UXHyperlink[]
     */
    protected $groupLinks = [];

    /**
     * @var BundleProjectBehaviour
     */
    protected $behaviour;

    /**
     * @var FlowListViewDecorator
     */
    protected $availableBundleListPane;

    /**
     * @var FlowListViewDecorator
     */
    protected $projectBundleListPane;

    /**
     * BundlesProjectControlPane constructor.
     * @param BundleProjectBehaviour $behaviour
     */
    public function __construct(BundleProjectBehaviour $behaviour)
    {
        $this->behaviour = $behaviour;
    }

    public function getName()
    {
        return _('manager.packets');
    }

    public function getDescription()
    {
        return _('manager.packets1');
    }

    function getMenuCount()
    {
        $count = 0;

        foreach ($this->behaviour->getPublicBundleResources() as $resource) {
            if ($this->behaviour->hasBundleInAnyEnvironment($resource->getBundle())) {
                $count++;
            }
        }

        return $count;
    }

    public function getIcon()
    {
        return 'icons/pluginEx16.png';
    }

    /**
     * @return UXNode
     */
    protected function makeUi()
    {
        $this->availableBundleListPane = new FlowListViewDecorator();
        $this->availableBundleListPane->setMultipleSelection(false);
        $this->availableBundleListPane->clearMenuCommands();

        $this->projectBundleListPane = new FlowListViewDecorator();
        $this->projectBundleListPane->setEmptyListText(_('fsfewkkgrkg'));
        $pane = $this->projectBundleListPane->getPane();

        $pane->minHeight = 212;
        $pane->height = 212;
        $pane->maxHeight = 212;

        $this->projectBundleListPane->on('remove', function (array $nodes) {
            foreach ($nodes as $node) {
                /** @var IdeLibraryBundleResource $resource */
                $resource = $node->data('resource');
                $this->behaviour->removeBundle($resource->getBundle());
            }

            $this->refresh();

            if ($editor = FileSystem::getSelectedEditor()) {
                $editor->open();
                $editor->refresh();
            }
        });

        $this->projectBundleListPane->on('append', function ($index, $indexes) {
            $this->projectBundleListPane->clearSelections();
            $node = $this->availableBundleListPane->getSelectionNode();

            /** @var IdeLibraryBundleResource $resource */
            $resource = $node->data('resource');

            Ide::get()->getMainForm()->showPreloader('Подождите, подключение пакета ...');

            Ide::async(function () use ($resource) {
                try {
                    $this->behaviour->addBundle(Project::ENV_ALL, $resource->getBundle());
                } finally {
                    uiLater(function () {
                        Ide::get()->getMainForm()->hidePreloader();
                    });
                }

                uiLater(function () use ($resource) {
                    $this->projectBundleListPane->add($this->makeItemUi($resource));

                    $this->availableBundleListPane->removeBySelections();

                    if ($editor = FileSystem::getSelectedEditor()) {
                        $editor->open();
                        $editor->refresh();
                    }
                    $tm = new ToasterMessage();
                    $iconImage = new UXImage('res://resources/expui/icons/fileTypes/succes.png');
                    $tm
                    ->setIcon($iconImage)
                    ->setTitle('Менеджер по работе с пакетами')
                    ->setDescription(_('Пакет расширения: ' .$resource->getName(). ' успешно подключен к вашему проекту.'))
                    ->setClosable();
                    Toaster::show($tm);
                });
            });
        });


        $label = new UXLabel(_('searchbtbgdplace3'));
        $label->classesString = 'ui-text';
        $label->font->bold = true;

        $label2 = new UXLabel(_('searchbtbgdplace4'));
        $label2->font->bold = true;
        $label2->classesString = 'ui-text';


        $vbox = new UXVBox([$label2, $pane, new UXSeparator(), $label, $this->makeActionPaneUi(), $this->availableBundleListPane->getPane()], 10);
        UXVBox::setVgrow($this->availableBundleListPane->getPane(), 'ALWAYS');
        UXVBox::setVgrow($vbox, 'ALWAYS');

        return $vbox;
    }


    private function makeActionPaneUi()
    {
        $box = new UXHBox([], 10);
        $box->alignment = 'CENTER_LEFT';
        $box->minHeight = 32;

        $searchField = new UXTextField();
        $searchField->classesString = 'custom-text-field';
        $searchField->promptText = _('searchbtbgdplace');
        $searchField->width = 220;
        $searchField->maxHeight = 999;

        $box->add($searchField);

        $searchBtn = new UXButton();
        $searchBtn->classesString = 'custom-text-field';
        $searchBtn->graphic = ico('flatSearch16');
        $searchBtn->maxHeight = 999;

        $searchAction = function () use ($searchField) {
            $this->availableBundleListPane->clear();

            Ide::async(function () use ($searchField) {
                $this->refresh('all', $searchField->text);
            });
        };

        $searchField->on('keyUp', $searchAction);
        $searchBtn->on('action', $searchAction);

      
        $box->add(new UXSeparator('VERTICAL'));

        $addToLibrary = new UXButton(_('searchbtbgdplace1'), ico('library16'));
        $addToLibrary->classesString = 'ui-button-create-project';
        $addToLibrary->maxHeight = 999;
        $addToLibrary->on('action', [$this, 'addBundleFile']);
        $box->add($addToLibrary);

        $addUrlToLibrary = new UXButton(_('searchbtbgdplace2'), ico('linkAdd16'));
        $addUrlToLibrary->classesString = 'ui-button-create-project';
        $addUrlToLibrary->maxHeight = 999;
        $addUrlToLibrary->on('action', [$this, 'addBundleUrl']);
        $box->add($addUrlToLibrary);


        return $box;
    }

    private function makeItemUi(IdeLibraryBundleResource $resource)
    {
        $item = new ImageBox(92, 52);
        $item->setImage(Ide::getImage($resource->getIcon())->image);
        $item->setTitle($resource->getName() . ' ' . $resource->getVersion());
        $item->setTooltip($resource->getDescription());
        $item->data('resource', $resource);

        $item->on('click', function (UXMouseEvent $e) use ($resource) {
            if ($e->clickCount >= 2) {
                $this->showBundleDialog($resource);
            }
        });

        return $item;
    }

    private function makeExtendedItemUi(IdeLibraryBundleResource $resource)
    {
        $item = new ImageExtendedBox(42, 42);
        $item->style = '';
        $item->maxWidth = $item->minWidth = 350;
        $item->setImage(Ide::getImage($resource->getIcon())->image);
        $item->setTitle($resource->getName() . ' (' . $resource->getVersion() . ')');
        $item->setDescription($resource->getDescription(), '-fx-text-fill: gray');
        $item->setTooltip($resource->getDescription());
        
        $item->data('resource', $resource);

        $item->on('click', function (UXMouseEvent $e) use ($resource) {
            if ($e->clickCount >= 2) {
                $this->showBundleDialog($resource);
            }
        });

        return $item;
    }

    public function showBundleDialog(IdeLibraryBundleResource $resource)
    {
        $dialog = new BundleDetailInfoForm($this->behaviour);
        $dialog->onUpdate(function () {
            $this->refresh();
        });

        $dialog->setResult($resource);
        $dialog->showDialog();

        $this->refresh();
    }

    public function addBundle($file, callable $callback = null)
    {
        if (!$callback) $callback = function ($result) {};

        try {
            uiLater(function () {
                Ide::get()->getMainForm()->showPreloader('Подождите, установка пакета ...');
            });

            $zip = new ZipFile($file);

            try {
                if (!$zip->has('.resource')) {
                    uiLater(function () {
                        MessageBoxForm::warning('Поврежденный или некорректный пакет расширений, т.к. файл .resource отсутствует.');
                    });

                    $callback(false);

                    return false;
                }

                /** @var IdeConfiguration $config */
                $config = null;
                $exit = false;

                $zip->read('.resource', function ($stat, Stream $stream) use (&$exit, &$config) {
                    $config = new IdeConfiguration($stream, $stat['name']);

                    if (!$config->toArray()) {
                        uiLater(function () {
                            MessageBoxForm::warning('Поврежденный или некорректный пакет расширений, в конфиге .resource нет данных.');
                        });

                        $exit = true;
                        return;
                    }
                });

                if ($exit) {
                    $callback(false);
                    return false;
                }

                uiLater(function () use ($config, $zip, $file, $callback) {
                    $version = $config->get('version', '1.0');
                    $code = $config->get("name") . '~' . $version;

                    if ($oldResource = Ide::get()->getLibrary()->getResource('bundles', $config->get("name"))) {
                        if (!$oldResource->isEmbedded()) {
                            if (MessageBoxForm::confirm("Данный пакет уже установлен, заменить его новой версией ({$oldResource->getVersion()} -> {$version})?")) {
                                IdeSystem::getLoader()->invalidateByteCodeCache();
                                Ide::get()->getLibrary()->delete($oldResource);
                            } else {
                                Ide::get()->getMainForm()->hidePreloader();
                                $callback(false);
                                return;
                            }
                        }
                    }

                    (new Thread(function () use ($zip, $file, $config, $code, $callback) {
                        try {
                            $resource = Ide::get()->getLibrary()->makeResource('bundles', $code, true);
                            $path = fs::parent($resource->getPath()) . "/" . $code;

                            foreach ($zip->statAll() as $stat) {
                                $name = $stat['name'];

                                if ($name == '.resource') {
                                    fs::makeFile($resource->getPath() . ".resource");

                                    $zip->read($name, function ($_, Stream $stream) use ($resource) {
                                        fs::copy($stream, $resource->getPath() . ".resource");
                                    });
                                } else {
                                    if (str::startsWith($name, "bundle/")) {
                                        $to = $path . "/" . str::sub($name, 7);

                                        if ($stat['directory']) {
                                            fs::makeDir($to);
                                        } else {
                                            fs::ensureParent($to);
                                            try {
                                                $zip->read($name, function ($_, Stream $stream) use ($to) {
                                                    fs::copy($stream, $to);
                                                });
                                            } catch (IOException $e) {
                                                // jphp bug.
                                            }
                                        }
                                    }
                                }
                            }

                            Ide::get()->getLibrary()->updateCategory('bundles');

                            uiLater(function () use ($resource) {
                                Ide::get()->getMainForm()->hidePreloader();
                                $this->refresh();
                                        # Параметры модального окна
                                $modal = [
                                    'fitToWidth' => true, # Во всю длину
                                    'fitToHeight' => true, # Во всю ширину
                                    'blur' => $this->flowPane, # Объект для размытия
                                    'title' => 'Для корректного завершения установки пакета перезапустите FXEdition', # Заголовок окна
                                    'message' => 'Тогда не забудьте, сохранить ваш проект.', # Сообщение
                                    'close_overlay' => true, # Закрывать при клике мыши на overlay
                                    'buttons' => [['text' => 'Перезагрузить среду', 'style' => 'button-red'], ['text' => 'Отмена', 'style' => 'button-accent', 'close' => true]]
                                    ];
                                # Отображаем окно      
                                $modalClass = new Modals;
                                $MainFormZ = app()->form('MainForm');
                                $modalClass->modal_dialog(app()->form('MainForm'), $modal, function($e) use ($MainFormZ) {
                                    if ($e == 'Перезагрузить среду') {
                                        Execute("DevelNext.exe"); // Даем команду на запуск приложения
                                        Exit(); // Закрываем приложение
                                    }
                                });

                                /** @var IdeLibraryBundleResource $resource */
                                $resource = Ide::get()->getLibrary()->getResource('bundles', $resource->getUniqueId());

                                if ($resource && $resource->getBundle()) {
                                    if ($env = $this->behaviour->hasBundleInAnyEnvironment($resource->getBundle())) {
                                        $this->behaviour->removeBundle($resource->getBundle());
                                        $this->behaviour->addBundle($env, $resource->getBundle());
                                    }
                                }
                            });

                            $callback(false);
                        } finally {
                            ;
                        }
                    }))->start();
                });

                return true;
            } finally {

            }
        } catch (ZipException $e) {
            uiLater(function () {
                MessageBoxForm::warning('Поврежденный или некорректный файл ZIP пакета расширений');
                Ide::get()->getMainForm()->hidePreloader();
            });

            return false;
        }
    }

    public function addBundleFile()
    {
        $dialog = new UXFileChooser();
        $dialog->extensionFilters = [['description' => 'Пакеты для DevelNext (*.dnbundle)', 'extensions' => ['*.dnbundle']]];

        if ($file = $dialog->showOpenDialog()) {
            $this->addBundle($file);
        }
    }

    public function addBundleUrl()
    {
        $dialog = new InputMessageBoxForm(_('searchbtbgdplace5'), _('searchbtbgdplace6'), _('searchbtbgdplace7'));

        if ($dialog->showDialog() && $dialog->getResult()) {
            $file = Ide::get()->createTempFile('.dnbundle');

            Ide::get()->getMainForm()->showPreloader('Подождите, загрузка пакета ...');

            Ide::async(function () use ($dialog, $file) {
                FileUtils::copyFile($dialog->getResult(), $file);

                if (!fs::exists($file)) {
                    uiLater(function () {
                        UXDialog::show('Ошибка загрузки пакета');
                    });
                    return;
                }

                uiLater(function () {
                    Ide::get()->getMainForm()->showPreloader('Подождите, установка пакета ...');
                });

                $this->addBundle($file, function () use ($file) {
                    if (!$file->delete()) {
                        $file->deleteOnExit();
                    }

                    uiLater(function () {
                        Ide::get()->getMainForm()->hidePreloader();
                    });
                });
            });
        }
    }

    /**
     * Refresh ui and pane.
     * @param string $groupCode
     * @param null $searchText
     */
    public function refresh($groupCode = 'all', $searchText = null)
    {
        uiLater(function () use ($groupCode) {
            foreach ($this->groupLinks as $code => $link) {
                $link->underline = $code == $groupCode;
            }

            $this->availableBundleListPane->clear();
            $this->projectBundleListPane->clear();
        });

        foreach ($this->behaviour->getPublicBundleResources() as $resource) {
            if ($resource->getGroup() == $groupCode || $groupCode == 'all') {
                // skip if bundle already in project.
                if ($this->behaviour->hasBundleInAnyEnvironment($resource->getBundle())) {
                    uiLater(function () use ($resource) {
                        $this->projectBundleListPane->add($this->makeItemUi($resource));
                    });
                } else {
                    if ($searchText) {
                        $string = $resource->getName() . ' ' . $resource->getDescription() . ' ' . $resource->getGroup() . ' ' . $this->groups[$resource->getGroup()];
                        $string = str::lower($string);

                        if (!str::contains($string, str::lower($searchText))) {
                            continue;
                        }
                    }

                    uiLater(function () use ($resource) {
                        $this->availableBundleListPane->add($this->makeExtendedItemUi($resource));
                    });
                }
            }
        }
    }
}