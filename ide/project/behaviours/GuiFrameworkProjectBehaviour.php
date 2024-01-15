<?php

namespace ide\project\behaviours;

use develnext\bundle\game2d\Game2DBundle;
use ide\action\ActionManager;
use ide\bundle\std\UIDesktopBundle;
use ide\bundle\std\JPHPDesktopDebugBundle;
use ide\commands\CreateFormProjectCommand;
use ide\commands\CreateGameSpriteProjectCommand;
use ide\commands\CreateScriptModuleProjectCommand;
use ide\editors\AbstractEditor;
use ide\editors\common\FormListEditor;
use ide\editors\common\ObjectListEditorItem;
use ide\editors\FormEditor;
use ide\editors\GameSpriteEditor;
use ide\editors\menu\AbstractMenuCommand;
use ide\editors\menu\ContextMenu;
use ide\editors\ScriptModuleEditor;
use ide\entity\ProjectSkin;
use ide\formats\form\AbstractFormElement;
use ide\formats\form\FormEditorSettings;
use ide\formats\FxCssCodeFormat;
use ide\formats\GameSpriteFormat;
use ide\formats\GuiFormFormat;
use ide\formats\PhpCodeFormat;
use ide\formats\ProjectFormat;
use ide\formats\ScriptModuleFormat;
use ide\formats\sprite\IdeSpriteManager;
use ide\formats\templates\GuiApplicationConfFileTemplate;
use ide\formats\templates\GuiBootstrapFileTemplate;
use ide\formats\templates\GuiFormFileTemplate;
use ide\formats\templates\GuiLauncherConfFileTemplate;
use ide\formats\templates\PhpClassFileTemplate;
use ide\forms\ImagePropertyEditorForm;
use ide\forms\MessageBoxForm;
use ide\Ide;
use ide\IdeException;
use ide\library\IdeLibrarySkinResource;
use ide\Logger;
use ide\project\AbstractProjectBehaviour;
use ide\project\control\CommonProjectControlPane;
use ide\project\control\DesignProjectControlPane;
use ide\project\control\FormsProjectControlPane;
use ide\project\control\ModulesProjectControlPane;
use ide\project\control\SpritesProjectControlPane;
use ide\project\Project;
use ide\project\ProjectExporter;
use ide\project\ProjectFile;
use ide\project\ProjectIndexer;
use ide\project\ProjectModule;
use ide\project\ProjectTree;
use ide\systems\FileSystem;
use ide\utils\FileUtils;
use ide\utils\Json;
use php\compress\ZipException;
use php\compress\ZipFile;
use php\desktop\Runtime;
use php\gui\event\UXEvent;
use php\gui\framework\AbstractForm;
use php\gui\framework\AbstractModule;
use php\gui\layout\UXHBox;
use php\gui\layout\UXVBox;
use php\gui\UXApplication;
use php\gui\UXButton;
use php\gui\UXCheckbox;
use php\gui\UXLabel;
use php\gui\UXMenu;
use php\gui\UXMenuItem;
use php\gui\UXParent;
use php\gui\UXTextField;
use php\io\File;
use php\io\IOException;
use php\io\ResourceStream;
use php\lib\arr;
use php\lib\fs;
use php\lib\reflect;
use php\lib\str;
use php\util\Configuration;
use php\util\Regex;
use timer\AccurateTimer;

class GuiFrameworkProjectBehaviour_ProjectTreeMenuCommand extends AbstractMenuCommand
{
    /**
     * @var UXMenu
     */
    protected $menu;
    /**
     * @var GuiFrameworkProjectBehaviour
     */
    private $gui;

    /**
     * GuiFrameworkProjectBehaviour_ProjectTreeMenuCommand constructor.
     */
    public function __construct(GuiFrameworkProjectBehaviour $gui)
    {
        $this->menu = new UXMenu();
        $this->gui = $gui;
    }

    public function withBeforeSeparator()
    {
        return true;
    }

    public function makeMenuItem()
    {
        $menu = $this->menu;
        $menu->text = $this->getName();
        $menu->graphic = Ide::get()->getImage($this->getIcon());

        return $menu;
    }

    public function getIcon()
    {
        return 'icons/dirs16.png';
    }


    public function getName()
    {
        return "Весь проект";
    }

    public function onExecute($e = null, AbstractEditor $editor = null)
    {

    }

    /**
     * @param UXMenu|UXMenuItem $item
     * @param AbstractEditor|null $editor
     */
    public function onBeforeShow($item, AbstractEditor $editor = null)
    {
        $menu = $this->menu;
        $menu->items->clear();

        foreach ([$this->gui->getFormEditors(), $this->gui->getModuleEditors(), $this->gui->getSpriteEditors()] as $i => $editors) {
            if ($i > 0 && $editors) {
                $menu->items->add(UXMenuItem::createSeparator());
            }

            /** @var AbstractEditor[] $editors */
            foreach ($editors as $editor) {
                $menuItem = new UXMenuItem($editor->getTitle(), Ide::get()->getImage($editor->getIcon()));
                $menu->items->add($menuItem);

                if (FileSystem::isOpened($editor->getFile())) {
                    $menuItem->style = '-fx-text-fill: blue;';
                }

                $menuItem->on('action', function () use ($editor) {
                    FileSystem::open($editor->getFile());
                });
            }
        }
    }

}

/**
 * Class GuiFrameworkProjectBehaviour
 * @package ide\project\behaviours
 */
class GuiFrameworkProjectBehaviour extends AbstractProjectBehaviour
{
    const GAME_DIRECTORY = 'src/.game';

    /** @var string */
    protected $mainForm = '';

    /**
     * @var ActionManager
     */
    protected $actionManager;

    /**
     * @var IdeSpriteManager
     */
    protected $spriteManager;

    /**
     * @var FormListEditor
     */
    protected $settingsMainFormCombobox;

    /**
     * @var Configuration
     */
    protected $applicationConfig;

    /**
     * @var UXTextField
     */
    protected $uiUuidInput;

    /**
     * @var string app.uuid from application.conf
     */
    protected $appUuid;

    /**
     * @var File
     */
    protected $ideStylesheetFile;

    /**
     * @var int
     */
    protected $ideStylesheetFileTime;

    /**
     * @var AccurateTimer
     */
    protected $ideStylesheetTimer;

    /**
     * @var array
     */
    protected $splashData = [];

    /**
     * @return int
     */
    public function getPriority()
    {
        return self::PRIORITY_LIBRARY;
    }

    /**
     * ...
     */
    public function inject()
    {
        $this->applicationConfig = new Configuration();

        $this->project->on('recover', [$this, 'doRecover']);
        $this->project->on('create', [$this, 'doCreate']);
        $this->project->on('createEditor', [$this, 'doCreateEditor']);
        $this->project->on('open', [$this, 'doOpen']);
        $this->project->on('save', [$this, 'doSave']);
        $this->project->on('close', [$this, 'doClose']);
        $this->project->on('preCompile', [$this, 'doPreCompile']);
        $this->project->on('compile', [$this, 'doCompile']);
        $this->project->on('export', [$this, 'doExport']);
        $this->project->on('reindex', [$this, 'doReindex']);
        $this->project->on('update', [$this, 'doUpdate']);
        $this->project->on('makeSettings', [$this, 'doMakeSettings']);
        $this->project->on('updateSettings', [$this, 'doUpdateSettings']);

        $this->project->registerFormat($projectFormat = new ProjectFormat());
        $this->project->registerFormat(new GuiFormFormat());
        $this->project->registerFormat(new ScriptModuleFormat());
        $this->project->registerFormat(new GameSpriteFormat());
        $this->project->registerFormat(new FxCssCodeFormat());

        $projectFormat->addControlPanes([
            new CommonProjectControlPane(),
            new DesignProjectControlPane(),

            new FormsProjectControlPane(),
            new ModulesProjectControlPane(),
        ]);

        $addMenu = new ContextMenu();

        FileSystem::setClickOnAddTab(function (UXEvent $e) use ($addMenu) {
            $addMenu->show($e->sender);
        });

        $addMenu->add(new CreateFormProjectCommand());
        $addMenu->add(new CreateScriptModuleProjectCommand());
        $addMenu->add(new CreateGameSpriteProjectCommand());
        $addMenu->add(new GuiFrameworkProjectBehaviour_ProjectTreeMenuCommand($this));

        Ide::get()->registerSettings(new FormEditorSettings());

        $this->actionManager = ActionManager::get();
        $this->spriteManager = new IdeSpriteManager($this->project);

        $this->ideStylesheetFile = $this->project->getIdeCacheFile('.theme/style-ide.css');

        $this->registerTreeMenu();
    }

    public function makeApplicationConf()
    {
        $this->project->createFile('src/.system/application.conf', new GuiApplicationConfFileTemplate($this->project));
    }

    protected function registerTreeMenu()
    {
        $tree = $this->project->getTree();
        $menu = $tree->getContextMenu();

        $menu->addSeparator('new');
        $menu->add(new CreateFormProjectCommand($tree), 'new');
        $menu->add(new CreateScriptModuleProjectCommand(), 'new');
        $menu->add(new CreateGameSpriteProjectCommand(), 'new');
    }

    /**
     * @return array
     */
    public function getSplashData()
    {
        return $this->splashData;
    }

    /**
     * @return string
     */
    public function getAppUuid()
    {
        return $this->appUuid;
    }

    /**
     * @param string $appUuid
     */
    public function setAppUuid($appUuid, $trigger = true)
    {
        $this->appUuid = $appUuid;
        $this->makeApplicationConf();

        if ($trigger) {
            $this->project->trigger('updateSettings');
        }
    }

    public function getMainForm()
    {
        return $this->mainForm;
    }

    public function isMainForm(FormEditor $editor)
    {
        return $this->getMainForm() == $editor->getTitle();
    }

    public function setMainForm($form)
    {
        //Logger::info("Set main form, old = $this->mainForm, new = $form");
        $this->mainForm = $form;

        $this->makeApplicationConf();
    }

    /**
     * @return IdeSpriteManager
     */
    public function getSpriteManager()
    {
        return $this->spriteManager;
    }

    public function doClose()
    {
        if ($this->ideStylesheetTimer) {
            $this->ideStylesheetTimer->stop();
        }

        $this->actionManager->free();
        $this->spriteManager->free();

        // Clear all styles for MainForm.
        if ($form = Ide::get()->getMainForm()) {
            $path = "file:///" . str::replace($this->ideStylesheetFile, "\\", "/");
            $form->removeStylesheet($path);
        }
    }

    public function doCreate()
    {
        $this->setAppUuid(str::uuid());
    }

    public function doCreateEditor(AbstractEditor $editor)
    {
        if (reflect::typeOf($editor) === FormEditor::class) {
            $this->applyStylesheetToEditor($editor);
        }
    }

    public function doUpdate()
    {
        if ($this->spriteManager) {
            $this->spriteManager->reloadAll();
        }
    }

    /**
     * @var UXLabel
     */
    protected $uiSplashLabel;

    /**
     * @var UXCheckbox
     */
    protected $uiSplashOnTop;

    /**
     * @var UXCheckbox
     */
    protected $uiSplashAutoHide;

    public function doUpdateSettings(CommonProjectControlPane $editor = null)
    {
        if ($this->uiSplashLabel) {
            $this->uiSplashLabel->text = $this->splashData['src'] ?: '(Нет изображения)';
        }

        if ($this->uiSplashOnTop) {
            $this->uiSplashOnTop->selected = (bool)$this->splashData['alwaysOnTop'];
        }

        if ($this->uiSplashAutoHide) {
            $this->uiSplashAutoHide->selected = (bool)$this->splashData['autoHide'];
        }
    }

    public function doMakeSettings(CommonProjectControlPane $editor)
    {
        $title = new UXLabel('Заставка (Splash):');
        $title->font = $title->font->withBold();

        $label = new UXLabel('(Нет изображения)');
        $label->textColor = 'gray';
        $button = new UXButton('Выбрать');
        $button->classes->add('icon-open');

        $this->uiSplashLabel = $label;

        $button->on('action', function () use ($label) {
            $dialog = new ImagePropertyEditorForm();

            if ($dialog->showDialog()) {
                $this->splashData['src'] = $dialog->getResult() ? "/{$dialog->getResult()}" : null;
                $label->text = $this->splashData['src'] ?: '(Нет изображения)';

                $this->saveLauncherConfig();
            }
        });

        $UXHBox = new UXHBox([$button, $label], 10);
        $UXHBox->alignment = 'CENTER_LEFT';

        $this->uiSplashOnTop = $fxSplashOnTop = new UXCheckbox('Заставка всегда поверх окон');
        $this->uiSplashAutoHide = $fxSplashAutoHide = new UXCheckbox('Автоматически скрывать заставку после старта');
        $fxSplashAutoHide->tooltipText = 'Чтобы скрыть заставку через код используйте app()->hideSplash()';

        $fxSplashOnTop->on('mouseUp', function () {
            $this->splashData['alwaysOnTop'] = $this->uiSplashOnTop->selected;
            $this->saveLauncherConfig();
        });

        $fxSplashAutoHide->on('mouseUp', function () {
            $this->splashData['autoHide'] = $this->uiSplashAutoHide->selected;
            $this->saveLauncherConfig();
        });

        $wrap = new UXVBox([$title, $UXHBox, $fxSplashOnTop, $fxSplashAutoHide], 5);

        $editor->addSettingsPane($wrap);
    }

    public function doReindex(ProjectIndexer $indexer)
    {
        foreach ($this->getFormEditors() as $editor) {
            $editor->reindex();
        }

        foreach ($this->getModuleEditors() as $editor) {
            $editor->reindex();
        }
    }

    public function doExport(ProjectExporter $exporter)
    {
        $exporter->addDirectory($this->project->getFile('src/'));
        $exporter->removeFile($this->project->getFile('src/.debug'));
    }

    public function doPreCompile($env, callable $log = null)
    {
        $withSourceMap = $env == Project::ENV_DEV;

        $this->actionManager->compile($this->project->getSrcFile(''), $this->project->getSrcFile('', true), function ($filename) use ($log) {
            $name = $this->project->getAbsoluteFile($filename)->getRelativePath();

            if ($log) {
                $log(':apply actions "' . $name . '"');
            }
        }, $withSourceMap);

        $this->saveLauncherConfig();

        if (!PhpProjectBehaviour::get()) {
            $this->saveBootstrapScript();
        }
    }

    public function doCompile($environment, callable $log = null)
    {
    }

    /**
     * @return ProjectFile|File
     */
    public function getModuleDirectory()
    {
        return $this->project->getFile("src/{$this->project->getPackageName()}/modules");
    }

    /**
     * @param $fullClass
     * @return string
     */
    public function getModuleShortClass($fullClass)
    {
        $prefix = "{$this->project->getPackageName()}\\modules\\";

        if (str::startsWith($fullClass, $prefix)) {
            return str::sub($fullClass, str::length($prefix));
        }

        return $fullClass;
    }

    /**
     * @param $fullClass
     * @return bool
     */
    public function isModuleSingleton($fullClass)
    {
        if ($fullClass == $this->getAppModuleClass()) {
            return true;
        }

        $fullClass = fs::normalize($fullClass);

        $metaFile = $this->project->getSrcFile("$fullClass.module");

        if ($metaFile->isFile()) {
            if ($meta = Json::fromFile($metaFile)) {
                return (bool)$meta['props']['singleton'];
            }
        }

        return false;
    }

    /**
     * @return string
     */
    public function getAppModuleClass()
    {
        return "{$this->project->getPackageName()}\\modules\\AppModule";
    }

    /**
     * @return array
     */
    public function getModuleClasses()
    {
        $files = $this->getModuleFiles();

        $classes = [];

        foreach ($files as $file) {
            $item = FileUtils::relativePath($this->project->getFile('src'), $file);
            $classes[] = str::replace(fs::pathNoExt($item), '/', '\\');
        }

        return $classes;
    }

    /**
     * @return string[]
     */
    public function getModuleFiles()
    {
        return Ide::get()->getFilesOfFormat(ScriptModuleFormat::class, $this->getModuleDirectory());
    }

    public function doSave()
    {
        //$this->saveBootstrapScript();
    }

    public function doOpen()
    {
        $tree = $this->project->getTree();
        $tree->addIgnoreExtensions([
            'behaviour', 'axml', 'module', 'fxml'
        ]);
        $tree->addIgnorePaths([
            'application.pid', 'build.gradle', 'settings.gradle', 'build.xml',
            'src/.forms', 'src/.scripts', 'src/.system', 'src/.debug', 'src/JPHP-INF'
        ]);

        $tree->addIgnorePaths([
            "{$this->project->getSrcDirectory()}/.theme/skin"
        ]);

        $tree->addIgnoreFilter(function ($file) {
            if (fs::ext($file) == 'conf') {
                if (fs::isFile(fs::pathNoExt($file) . '.fxml')) {
                    return true;
                }
            }

            return false;
        });


        /** @var GradleProjectBehaviour $gradleBehavior */
        $gradleBehavior = $this->project->getBehaviour(GradleProjectBehaviour::class);

        $buildConfig = $gradleBehavior->getConfig();

        $buildConfig->addPlugin('application');
        $buildConfig->setDefine('mainClassName', '"org.develnext.jphp.ext.javafx.FXLauncher"');
        $buildConfig->addSourceSet('main.resources.srcDirs', 'src');

        $buildConfig->setDefine('jar.archiveName', '"dn-compiled-module.jar"');

        $this->updateSpriteManager();

        try {
            $this->applicationConfig->load($this->project->getFile('src/.system/application.conf'));
        } catch (IOException $e) {
            Logger::warn("Unable to load application.conf, {$e->getMessage()}");
        }

        $this->mainForm = $this->applicationConfig->get('app.mainForm', '');
        $this->appUuid = $this->applicationConfig->get('app.uuid', str::uuid());

        $this->loadLauncherConfig();
        $this->reloadStylesheetIfModified();

        $this->ideStylesheetTimer = new AccurateTimer(100, [$this, 'reloadStylesheetIfModified']);
        $this->ideStylesheetTimer->start();
    }

    public function doRecover()
    {
        if (!$this->project->hasBehaviour(GradleProjectBehavior::class)) {
            $this->project->register(new GradleProjectBehaviour());
        }

        if (!$this->project->hasBehaviour(BundleProjectBehaviour::class)) {
            $this->project->register(new BundleProjectBehaviour());
        }

        $bundle = BundleProjectBehaviour::get();

        if ($bundle) {
            if (!$this->project->getIdeFile("bundles/")->findFiles()) { // for old project formats
                $bundle->addBundle(Project::ENV_ALL, UIDesktopBundle::class, false);
                $bundle->addBundle(Project::ENV_ALL, Game2DBundle::class);
            } else {
                $bundle->addBundle(Project::ENV_ALL, UIDesktopBundle::class, false);
            }

            $bundle->addBundle(Project::ENV_DEV, JPHPDesktopDebugBundle::class, false);
        }

        $this->_recoverDirectories();

        $this->project->defineFile('src/.system/application.conf', new GuiApplicationConfFileTemplate($this->project));

        // Set config for prototype forms.
        foreach ($this->getFormEditors() as $editor) {
            $usagePrototypes = $editor->getPrototypeUsageList();

            foreach ($usagePrototypes as $factoryId => $ids) {
                $formEditor = $this->getFormEditor($factoryId);

                if (!$formEditor) {
                    Logger::warn("Cannot find form editor for factory '$factoryId'.");
                    continue;
                }

                if ($formEditor && !$formEditor->getConfig()->get('form.withPrototypes')) {
                    $formEditor->getConfig()->set('form.withPrototypes', true);
                    $formEditor->saveConfig();
                }
            }
        }
    }

    public function reloadStylesheetIfModified()
    {
        if (!$this->ideStylesheetFileTime) {
            $this->reloadStylesheet();
            return;
        }

        $styleFile = $this->project->getSrcFile('.theme/style.fx.css');

        if (!$styleFile->exists() || fs::time($styleFile) != $this->ideStylesheetFileTime) {
            $this->reloadStylesheet();
        }
    }

    private function saveStylesheet()
    {
        $styleFile = $this->project->getSrcFile('.theme/style.fx.css');

        if (!fs::exists($styleFile)) {
            fs::delete($this->ideStylesheetFile);
        }
    }

    private function applyStylesheetToEditor(AbstractEditor $editor)
    {
        $styleFile = $this->project->getSrcFile('.theme/style.fx.css');

        /** @var File[] $skinFiles */
        $skinFiles = fs::scan($this->project->getSrcFile('.theme/skin/'), [
            'extensions' => ['css'], 'excludeDirs' => true
        ], 1);

        $path = $styleFile->toUrl();

        $stylesheets = $editor->getStylesheets();
        foreach ($stylesheets as $stylesheet) {
            $editor->removeStylesheet($stylesheet);
        }

        $resource = new ResourceStream('/ide/formats/form/FormEditor.css');
        $editor->removeStylesheet($resource->toExternalForm());
        $editor->removeStylesheet($path);
        $editor->addStylesheet($resource->toExternalForm());

        $guiStyles = $this->project->fetchNamedList('guiStyles');
        foreach ($guiStyles as $resPath => $filePath) {
            $editor->addStylesheet($filePath);
        }

        /*foreach ($stylesheets as $stylesheet) {
            if (str::contains($stylesheet, '/skin/')) {
                $editor->removeStylesheet($stylesheet);
            }
        }*/

        foreach ($skinFiles as $file) {
            $editor->addStylesheet($file->toUrl());
        }

        if (fs::isFile($styleFile)) {
            $editor->addStylesheet($path);
        }
    }

    public function reloadStylesheet()
    {
        if (!UXApplication::isUiThread()) {
            uiLater(function () {
                $this->reloadStylesheet();
            });
            return;
        }

        Logger::info("Reload stylesheet");

        $this->saveStylesheet();

        foreach (FileSystem::getOpenedEditors() as $editor) {
            $this->applyStylesheetToEditor($editor);
        }

        $this->ideStylesheetFileTime = fs::time($this->project->getSrcFile('.theme/style.fx.css'));
    }

    public function saveLauncherConfig()
    {
        $template = new GuiLauncherConfFileTemplate();
        $template->setFxSplashAlwaysOnTop($this->splashData['alwaysOnTop']);

        if ($this->splashData['src']) {
            $template->setFxSplash($this->splashData['src']);
        }

        $this->project->defineFile($this->project->getSrcDirectory() . '/JPHP-INF/launcher.conf', $template, true);
        $this->makeApplicationConf();
    }

    public function loadLauncherConfig()
    {
        $config = new Configuration();

        $file = $this->project->getSrcFile('JPHP-INF/launcher.conf');

        if ($file->isFile()) {
            try {
                $config->load($file);

                $this->splashData['src'] = $config->get('fx.splash');
                $this->splashData['alwaysOnTop'] = $config->getBoolean('fx.splash.alwaysOnTop');
                $this->splashData['autoHide'] = $this->applicationConfig->getBoolean('app.fx.splash.autoHide', true);
            } catch (IOException $e) {
                Logger::warn("Unable to load {$file}, {$e->getMessage()}");
            }
        }
    }

    public function saveBootstrapScript(array $dirs = [], $encoded = false)
    {
        $template = new GuiBootstrapFileTemplate();

        $code = "";

        Logger::debug("Save bootstrap script ...");

        if ($this->project->getSrcGeneratedDirectory()) {
            $dirs[] = $this->project->getSrcFile('.inc', true);
        }

        if ($this->project->getSrcDirectory()) {
            $dirs[] = $this->project->getSrcFile('.inc', false);
        }

        $incFiles = [];

        foreach ($dirs as $dir) {
            fs::scan($dir, function ($filename) use (&$code, $dir, $encoded, &$incFiles) {
                $ext = fs::ext($filename);

                if (in_array($ext, ['php', 'phb'])) {
                    $file = $this->project->getAbsoluteFile($filename);

                    if ($encoded && $ext == 'php') {
                        $file = fs::pathNoExt($file) . '.phb';
                    }

                    $incFile = FileUtils::relativePath($dir, $file);

                    if (!$incFiles[$incFile]) {
                        $incFiles[$incFile] = true;

                        $code .= "include 'res://.inc/" . $incFile . "'; \n";

                        Logger::debug("Add '{$incFile}' to bootstrap script.");
                    }
                }
            });
        }

        $moduleClasses = [];

        foreach ($this->getModuleClasses() as $class) {
            if ($this->isModuleSingleton($class)) {
                $moduleClasses[] = $class;
            }
        }

        $code .= "\n\$app->loadModules(" . var_export($moduleClasses, true) . ');';

        $guiStyles = $this->project->fetchNamedList('guiStyles');
        foreach ($guiStyles as $resPath => $filePath) {
            $code .= "\n\$app->addStyle('$resPath');";
        }

        /** @var File[] $skinFiles */
        $skinFiles = fs::scan($this->project->getSrcFile('.theme/skin/'), [
            'extensions' => ['css'], 'excludeDirs' => true
        ], 1);

        foreach ($skinFiles as $skinFile) {
            $name = str::replace($skinFile->getName(), ' ', '%20');
            $code .= "\n\$app->addStyle('/.theme/skin/{$name}');";
        }

        $code .= "\n\$app->addStyle('/.theme/style.fx.css');";

        $template->setInnerCode($code);

        $this->project->defineFile('src/JPHP-INF/.bootstrap', $template, true);
    }

    /**
     * Удалить скин программы.
     */
    public function clearSkin()
    {
        $skinDir = $this->project->getSrcFile('.theme/skin');
        fs::clean($skinDir);
        fs::delete($skinDir);
    }

    /**
     * Конвертирует скин в тему проекта.
     */
    public function convertSkinToTheme()
    {
        if ($this->getCurrentSkin()) {
            FileUtils::copyDirectory(
                $this->project->getSrcFile('.theme/skin'),
                $this->project->getSrcFile('.theme')
            );

            fs::delete($this->project->getSrcFile('.theme/style.fx.css'));
            fs::rename($this->project->getSrcFile('.theme/skin.css'), 'style.fx.css');

            $this->clearSkin();
        }
    }

    /**
     * Применить скин к программе.
     * @param ProjectSkin $skin
     */
    public function applySkin(ProjectSkin $skin)
    {
        if ($skin->hasAnyScope(GuiFrameworkProjectBehaviour::class, 'gui')) {
            $skinDir = $this->project->getSrcFile('.theme/skin');
            fs::clean($skinDir);
            fs::makeDir($skinDir);

            try {
                $skin->unpack($skinDir);
                $this->reloadStylesheet();
            } catch (ZipException $e) {
                uiLaterAndWait(function () use ($e) {
                    MessageBoxForm::warning(
                        "Ошибка установки скина, невозможно распаковать архив с файлами скина.\n\n -> {$e->getMessage()}"
                    );
                });
            }
        } else {
            uiLaterAndWait(function () {
                MessageBoxForm::warning('Данный скин невозможно применить к проекту данного типа.');
            });
        }
    }

    /**
     * @return ProjectSkin
     */
    public function getCurrentSkin(): ?ProjectSkin
    {
       $skinDir = $this->project->getSrcFile('.theme/skin');

       if (!fs::isDir($skinDir)) return null;
       if (!fs::isFile("$skinDir/skin.json")) return null;
       if (!fs::isFile("$skinDir/skin.css")) return null;

       try {
           $skin = ProjectSkin::createFromDir($skinDir);
           return $skin;
       } catch (IOException $e) {
           Logger::warn("Unable to read skin information, {$e->getMessage()}");
           return null;
       }
    }

    public function updateSpriteManager()
    {
        $this->spriteManager->reloadAll();
    }

    public function hasModule($name)
    {
        return $this->project->getFile("src/{$this->project->getPackageName()}/modules/$name.php")->isFile();
    }

    /**
     * @param $name
     * @param bool $cache
     * @return ScriptModuleEditor|null
     */
    public function getModuleEditor($name, $cache = false)
    {
        return FileSystem::fetchEditor($this->project->getFile("src/{$this->project->getPackageName()}/modules/$name.php"), $cache);
    }

    public function createModule($name)
    {
        if ($this->hasModule($name)) {
            $editor = $this->getModuleEditor($name);
            $editor->delete(true);
        }

        Logger::info("Creating module '$name' ...");

        $template = new PhpClassFileTemplate($name, 'AbstractModule');
        $template->setNamespace("{$this->project->getPackageName()}\\modules");


        $php = PhpProjectBehaviour::get();

        if ($php && $php->getImportType() == 'package') {
            $template->setImports([
                "std, gui, framework, {$this->project->getPackageName()}"
            ]);
        } else {
            $template->setImports([
                AbstractModule::class
            ]);
        }

        $file = $this->project->createFile("src/{$this->project->getPackageName()}/modules/$name.php", $template);

        Json::toFile(
            $this->project->getFile("src/{$this->project->getPackageName()}/modules/$name.module"), ['props' => [], 'components' => []]
        );

        if (!$file->exists()) {
            $file->applyTemplate($template);
            $file->updateTemplate(true);
        }

        Logger::info("Finish creating module '$name'");

        $this->project->save();

        return $file;
    }

    /**
     * @return ScriptModuleEditor[]
     */
    public function getModuleEditors()
    {
        $editors = [];

        foreach ($this->getModuleFiles() as $file) {
            $editor = FileSystem::fetchEditor($file, true);
            $editors[FileUtils::hashName($file)] = $editor;
        }

        return $editors;
    }

    /**
     * @return GameSpriteEditor[]
     */
    public function getSpriteEditors()
    {
        $editors = [];

        foreach ($this->spriteManager->getSprites() as $spec) {
            $file = $spec->schemaFile;
            $editor = FileSystem::fetchEditor($file, true);

            if ($editor) {
                $editors[FileUtils::hashName($spec->file)] = $editor;
            } else {
                Logger::error("Unable to find sprite editor for $file");
            }
        }

        return $editors;
    }

    public function getFormDirectory()
    {
        return $this->project->getFile("src/{$this->project->getPackageName()}/forms");
    }

    public function getFormFiles()
    {
        return Ide::get()->getFilesOfFormat(GuiFormFormat::class, $this->getFormDirectory());
    }

    /**
     * @return \ide\editors\FormEditor[]
     * @throws IdeException
     */
    public function getFormEditors()
    {
        $editors = [];

        foreach ($this->getFormFiles() as $filename) {
            $editor = FileSystem::fetchEditor($filename, true);

            if ($editor) {
                if (!($editor instanceof FormEditor)) {
                    throw new IdeException("Invalid format for -> $filename");
                }

                $editors[FileUtils::hashName($filename)] = $editor;
            }
        }

        return $editors;
    }

    /**
     * @param $moduleName
     * @return FormEditor[]
     */
    public function getFormEditorsOfModule($moduleName)
    {
        $formEditors = $this->getFormEditors();

        $result = [];

        foreach ($formEditors as $formEditor) {
            $modules = $formEditor->getModules();

            if ($modules[$moduleName]) {
                $result[FileUtils::hashName($formEditor->getFile())] = $formEditor;
            }
        }

        return $result;
    }

    public function createSprite($name)
    {
        Logger::info("Creating game sprite '$name' ...");

        $file = $this->spriteManager->createSprite($name);

        Logger::info("Finish creating game sprite '$name'");

        return $file;
    }

    public function hasForm($name)
    {
        return $this->project->getFile("src/{$this->project->getPackageName()}/forms/$name.php")->isFile();
    }

    /**
     * @param $name
     * @return FormEditor|null
     */
    public function getFormEditor($name)
    {
        return $this->hasForm($name) ?
            FileSystem::fetchEditor($this->project->getFile("src/{$this->project->getPackageName()}/forms/$name.php"), true)
            : null;
    }

    public function createForm($name, $namespace = null)
    {
        if ($this->hasForm($name)) {
            $editor = $this->getFormEditor($name);
            $editor->delete(true);
        }

        Logger::info("Creating form '$name' ...");

        $namespace = $namespace ?: "{$this->project->getPackageName()}\\forms";

        $file = $this->project->getSrcFile(str::replace($namespace, '\\', '/') . "/$name");

        $this->project->createFile($this->project->getAbsoluteFile("$file.fxml"), new GuiFormFileTemplate());

        $template = new PhpClassFileTemplate($name, 'AbstractForm');

        $template->setNamespace($namespace);

        $php = PhpProjectBehaviour::get();

        if ($php && $php->getImportType() == 'package') {
            $template->setImports([
                "std, gui, framework, {$this->project->getPackageName()}"
            ]);
        } else {
            $template->setImports([
                AbstractForm::class
            ]);
        }

        $sources = $this->project->createFile($this->project->getAbsoluteFile("$file.php"), $template);
        $sources->applyTemplate($template);
        $sources->updateTemplate(true);

        Logger::info("Finish creating form '$name'");

        $this->project->save();

        return $sources;
    }

    /**
     * @param $id
     * @return array [element => AbstractFormElement, behaviours => [[value, spec], ...]]
     */
    public function getPrototype($id)
    {
        list($group, $id) = str::split($id, '.', 2);

        if ($editor = $this->getFormEditor($group)) {
            $result = [];

            $objects = $this->getObjectList($editor);

            foreach ($objects as $one) {
                if ($one->text == $id) {
                    $result['version'] = $one->version;
                    $result['element'] = $one->element;
                    break;
                }
            }

            $result['behaviours'] = [];

            foreach ($editor->getBehaviourManager()->getBehaviours($id) as $one) {
                $result['behaviours'][] = [
                    'value' => $one,
                    'spec' => $editor->getBehaviourManager()->getBehaviourSpec($one),
                ];
            }

            return $result;
        }

        return null;
    }

    /**
     * @param AbstractEditor|null $contextEditor
     * @return array
     */
    public function getAllPrototypes(AbstractEditor $contextEditor = null)
    {
        $elements = [];

        foreach ($this->getFormEditors() as $editor) {
            if ($contextEditor && FileUtils::hashName($contextEditor->getFile()) == FileUtils::hashName($editor->getFile())) {
                continue;
            }

            if ($editor->getConfig()->get('form.withPrototypes')) {
                foreach ($editor->getObjectList() as $it) {
                    if ($it->element && $it->element->canBePrototype()) {
                        $it->group = $editor->getTitle();
                        $it->value = "{$it->getGroup()}.{$it->value}";
                        $elements[] = $it;
                    }
                }
            }
        }

        return $elements;
    }

    /**
     * @param $fileName
     * @return ObjectListEditorItem[]
     */
    public function getObjectList($fileName)
    {
        $result = [];
        $project = $this->project;

        $index = $project->getIndexer()->get($this->project->getAbsoluteFile($fileName), '_objects');

        foreach ((array)$index as $it) {
            /** @var AbstractFormElement $element */
            $element = class_exists($it['type']) ? new $it['type']() : null;

            $result[] = $item = new ObjectListEditorItem(
                $it['id'], null
            );

            $item->hint = $element ? $element->getName() : '';
            $item->element = $element;
            $item->version = (int)$it['version'];
            $item->rawType = $it['type'];

            if ($element) {
                if ($graphic = $element->getCustomPreviewImage((array)$it['data'])) {
                    $item->graphic = $graphic;
                } else {
                    $item->graphic = $element->getIcon();
                }
            }
        }

        return $result;
    }

    protected function _recoverDirectories()
    {
        $this->project->makeDirectory('src/');
        $this->project->makeDirectory('src/.data');
        $this->project->makeDirectory('src/.data/img');
        $this->project->makeDirectory('src/.system');
        $this->project->makeDirectory('src/JPHP-INF');

        $this->project->makeDirectory("src/{$this->project->getPackageName()}");
        $this->project->makeDirectory("src/{$this->project->getPackageName()}/forms");
        $this->project->makeDirectory("src/{$this->project->getPackageName()}/modules");
    }
}