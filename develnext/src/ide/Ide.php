<?php
namespace ide;

use ide\account\AccountManager;
use ide\account\ServiceManager;
use ide\bundle\AbstractBundle;
use ide\editors\AbstractEditor;
use ide\editors\value\ElementPropertyEditor;
use ide\formats\AbstractFormat;
use ide\formats\IdeFormatOwner;
use ide\formats\TextCodeFormat;
use ide\forms\MainForm;
use ide\forms\SplashForm;
use ide\l10n\L10n;
use ide\library\IdeLibrary;
use ide\misc\AbstractCommand;
use ide\misc\EventHandlerBehaviour;
use ide\project\AbstractProjectSupport;
use ide\project\AbstractProjectTemplate;
use ide\project\control\AbstractProjectControlPane;
use ide\project\Project;
use ide\project\TreeIconResolver;
use ide\protocol\AbstractProtocolHandler;
use ide\protocol\handlers\FileOpenProjectProtocolHandler;
use ide\systems\Cache;
use ide\systems\FileSystem;
use ide\systems\FxeAsyncManager;
use ide\systems\FxeProcessSystem;
use ide\systems\FxeLanguageServerClient;
use ide\systems\IdeSystem;
use ide\systems\ProjectSystem;
use ide\tool\IdeToolManager;
use ide\ui\LazyLoadingImage;
use ide\ui\FxeErrorDialog;
use ide\ui\Notifications;
use ide\utils\FileUtils;
use ide\utils\Json;
use php\gui\framework\Application;
use php\gui\JSException;
use php\gui\UXApplication;
use php\gui\layout\UXAnchorPane;
use php\gui\UXAlert;
use php\gui\UXButton;
use php\gui\UXCanvas;
use php\gui\UXImage;
use php\gui\UXImageView;
use php\gui\UXNode;
use php\gui\UXMenu;
use php\gui\UXMenuItem;
use php\gui\UXSeparator;
use php\gui\UXTextArea;
use php\gui\paint\UXColor;
use php\io\File;
use php\io\IOException;
use php\io\ResourceStream;
use php\io\Stream;
use php\lang\IllegalArgumentException;
use php\lang\Process;
use php\lang\System;
use php\lang\Thread;
use php\lang\ThreadPool;
use php\lib\arr;
use php\lib\fs;
use php\lib\reflect;
use php\lib\Str;
use php\time\Time;
use php\time\Timer;
use php\util\Regex;
use php\util\Scanner;
use timer\AccurateTimer;


/**
 * Class Ide
 * @package ide
 */
class Ide extends Application
{
    use EventHandlerBehaviour;
    use IdeFormatOwner {
        getRegisteredFormat as _getRegisteredFormat;
    }

    /** @var string */
    private $OS;

    /**
     * @var SplashForm
     */
    protected $splash;

    /**
     * @var AbstractProjectTemplate[]
     */
    protected $projectTemplates = [];

    /**
     * @var AbstractProjectSupport[]
     */
    protected $projectSupports = [];

    /**
     * @var AbstractExtension[]
     */
    protected $extensions = [];

    /**
     * @var AbstractCommand[]
     */
    protected $commands = [];

    /**
     * @var callable
     */
    protected $afterShow = [];

    /**
     * @var IdeConfiguration[]
     */
    protected $configurations = [];

    /**
     * @var Project
     */
    protected $openedProject = null;

    /**
     * @var AbstractProjectControlPane[]
     */
    protected $projectControlPanes = [];

    /**
     * @var AccountManager
     */
    protected $accountManager = null;

    /**
     * @var ServiceManager
     */
    protected $serviceManager = null;

    /**
     * @var IdeLibrary
     */
    protected $library;

    /**
     * @var IdeToolManager
     */
    protected $toolManager;

    /**
     * @var boolean
     */
    protected $idle = false;

    /**
     * @var L10n
     */
    protected $l10n;

    /**
     * @var IdeLanguage[]
     */
    protected $languages = [];

    /**
     * @var IdeLanguage
     */
    protected $language;

    /**
     * @var ThreadPool
     */
    private $asyncThreadPool;


    protected $disableOpenLastProject = false;

    /**
     * @var string
     */
    protected $mode = 'prod';

    /** @var bool */
    protected $mainFormShown = false;

    /** @var bool */
    protected $splashLoadingActive = false;

    /** @var string */
    protected $splashPendingLoadMessage = 'LOAD ...';

    /** @var string[] новые сообщения с прошлого опроса SplashForm, чтобы показывать их все, а не только последнее */
    protected $splashLoadHistory = [];

    /** @var string[] */
    protected static $splashLoadBuffer = [];

    /** @var UXImage[] */
    protected static $svgImageCache = [];

    public function __construct($configPath = null)
    {
        parent::__construct($configPath);

        $this->OS = IdeSystem::getOs();
        $this->mode = IdeSystem::getMode();

        $this->library = new IdeLibrary($this);
        $this->toolManager = new IdeToolManager();

        $this->asyncThreadPool = ThreadPool::createCached();
        FxeAsyncManager::init();
    }

    public function launch()
    {
        $mainFormClass = $this->mainFormClass;
        $splashFormClass = $this->splashFormClass;
        $showMainForm = $this->config->getBoolean('app.showMainForm') && $mainFormClass;

        $onStart = function () use ($mainFormClass, $splashFormClass, $showMainForm) {
            static::$instance = $this;

            $finishStart = function () {
                if (Stream::exists('res://.debug/bootstrap.php')) {
                    include 'res://.debug/bootstrap.php';
                }

                Logger::debug("Application start is done.");

                if ($oldSplash = UXApplication::getSplash()) {
                    if ($this->getConfig()->getBoolean('app.fx.splash.autoHide')) {
                        $oldSplash->hide();
                    }
                }
            };

            $finishLaunch = function () use ($mainFormClass, $showMainForm, $finishStart) {
                $this->endSplashLoading();

                if (!$this->mainForm && $mainFormClass) {
                    $this->mainForm = $this->getForm($mainFormClass);
                }

                $projectFile = $this->getUserConfigValue('lastProject');
                $autoOpenLast = !$this->disableOpenLastProject
                    && $projectFile
                    && File::of($projectFile)->exists();

                if ($this->splash && !$autoOpenLast) {
                    $this->splash->hide();
                }

                $deferMainForm = $autoOpenLast && $this->splash;

                if ($showMainForm && $this->mainForm && !$deferMainForm) {
                    $this->mainForm->show();
                }

                uiLater(function () use ($finishStart) {
                    $this->runAfterShowHandlers();
                    $finishStart();
                });
            };

            $beginUiStartup = function () use ($finishLaunch, $mainFormClass) {
                if ($this->appModule) {
                    $this->appModule->apply($this);
                }

                foreach ($this->modules as $module) {
                    $module->apply($this);
                }

                $this->launched = true;

                if ($mainFormClass) {
                    $this->mainForm = $this->getForm($mainFormClass);
                }

                $this->runStartupUiPhase($finishLaunch);
            };

            $beginBackgroundStartup = function () use ($beginUiStartup) {
                FxeAsyncManager::runParallel('Загрузка IDE ...', function () {
                    return $this->runStartupBackgroundPhase();
                }, function ($abort) use ($beginUiStartup) {
                    if ($abort) {
                        return;
                    }

                    FxeAsyncManager::runUi($beginUiStartup, 'Инициализация интерфейса ...');
                });
            };

            if ($splashFormClass) {
                $this->beginSplashLoading();
                $this->splash = $this->getForm($splashFormClass);

                if ($this->splash) {
                    Logger::info("Show splash screen ($splashFormClass)");

                    $this->splash->alwaysOnTop = true;
                    $this->splash->show();
                    $this->splash->toFront();

                    if ($oldSplash = UXApplication::getSplash()) {
                        $oldSplash->hide();
                    }

                    uiAfterPulse($beginBackgroundStartup, 16);
                    return;
                }
            }

            $beginBackgroundStartup();
        };

        UXApplication::launch($onStart);
    }

    /**
     * @return bool true если запуск нужно прервать
     */
    protected function runStartupBackgroundPhase()
    {
        Logger::reset();
        Logger::info("Start IDE, mode = $this->mode, os = $this->OS, version = {$this->getVersion()}");
        Logger::debug(str::format("Commands Args = [%s]", str::join((array)$GLOBALS['argv'], ', ')));

        restore_exception_handler();

        set_exception_handler(function ($e) {
            static $showError;

            if ($e instanceof JSException) {
                echo $e->getTraceAsString();
                return;
            }

            Logger::exception($e->getMessage(), $e);

            if (!$showError) {
                $showError = true;
                $notify = Notifications::error(_('error.unknown.title'), _('error.unknown.message'));

                $notify->on('click', function () use ($e) {
                    \ide\commands\IdeLogShowCommand::openInTab();
                });

                $this->sendError($e);

                $notify->on('hide', function () use (&$showError) {
                    $showError = false;
                });
            }
        });

        if ($this->isDevelopment()) {
            restore_exception_handler();
        }

        $this->readLanguages();

        if ($this->handleArgs($GLOBALS['argv'])) {
            Logger::info("Protocol handler is shutdown ide ...");

            uiLater(function () {
                Timer::after('7s', function () {
                    $this->shutdown();
                });
            });

            return true;
        }

        FxeProcessSystem::startAll();
        FxeLanguageServerClient::register();

        $this->preloadStartupClasses();

        return false;
    }

    /**
     * Предзагрузка PHP-классов в фоне, чтобы не блокировать сплеш на UI-потоке.
     */
    protected function preloadStartupClasses()
    {
        $targets = [
            'ide\\forms\\MainForm',
            'ide\\editors\\CodeEditor',
            'ide\\editors\\FormEditor',
            'ide\\systems\\ProjectSystem',
            'ide\\autocomplete\\ui\\AutoCompletePane',
            'ide\\autocomplete\\php\\PhpAutoComplete',
        ];

        foreach ($targets as $class) {
            try {
                if (!class_exists($class, false)) {
                    Ide::notifySplashLoad('LOAD ' . $class);
                    class_exists($class, true);
                }
            } catch (\Throwable $e) {
                Logger::warn('Preload failed: ' . $class . ' — ' . $e->getMessage());
            }
        }
    }

    protected function runStartupUiPhase(callable $finishLaunch)
    {
        $this->setOpenedProject(null);

        self::async(function () use ($finishLaunch) {
            $this->serviceManager = new ServiceManager();

            $this->serviceManager->on('privateEnable', function () {
                $this->accountManager->updateAccount();
            });

            $this->serviceManager->on('privateDisable', function () {
            });

            $this->serviceManager->updateStatus();

            uiLater(function () use ($finishLaunch) {
                $this->accountManager = new AccountManager();

                self::async(function () use ($finishLaunch) {
                    $this->registerAllDeferred(function () use ($finishLaunch) {
                        uiLater(function () use ($finishLaunch) {
                            $this->finishRegisterAll();
                            $this->completeStartup($finishLaunch);
                        });
                    });
                });
            });
        });
    }

    protected function completeStartup(callable $finishLaunch)
    {
        foreach ($this->extensions as $extension) {
            $extension->onIdeStart();
        }

        $timer = new AccurateTimer(1000, function () {
            Ide::async(function () {
                $file = FileSystem::getSelected();

                foreach (FileSystem::getOpened() as $info) {
                    if (!fs::exists($info['file'])) {
                        uiLater(function () use ($info) {
                            $editor = FileSystem::getOpenedEditor($info['file']);
                            if ($editor && $editor->isAutoClose()) {
                                $editor->delete();
                            }
                        });
                    }
                }
            });
        });
        $timer->start();

        $this->splashFinishLoading();
        $this->trigger('start', []);

        uiAfterPulse($finishLaunch, 48);
    }

    public function beginSplashLoading()
    {
        $this->splashLoadingActive = true;
        $this->splashPendingLoadMessage = 'LOAD ...';
        $this->splashLoadHistory = [];
        $this->flushSplashLoadBuffer();
    }

    /**
     * @param string $message
     */
    public static function notifySplashLoad($message)
    {
        if (!$message) {
            return;
        }

        if (static::isCreated()) {
            $ide = static::get();

            if ($ide->splashLoadingActive) {
                $ide->splashLoadProgress($message);
                return;
            }
        }

        static::$splashLoadBuffer[] = $message;

        if (count(static::$splashLoadBuffer) > 300) {
            static::$splashLoadBuffer = array_slice(static::$splashLoadBuffer, -200);
        }
    }

    public function flushSplashLoadBuffer()
    {
        foreach (IdeClassLoader::drainSplashLoadBuffer() as $message) {
            $this->splashLoadProgress($message);
        }

        foreach (static::$splashLoadBuffer as $message) {
            $this->splashLoadProgress($message);
        }

        static::$splashLoadBuffer = [];
    }

    public function getSplashPendingLoadMessage()
    {
        return $this->splashPendingLoadMessage;
    }

    /**
     * Забрать все новые сообщения загрузки с прошлого вызова (для SplashForm,
     * чтобы показать все LOAD-строки, а не только последнюю на момент опроса).
     *
     * @return string[]
     */
    public function drainSplashLoadHistory()
    {
        $history = $this->splashLoadHistory;
        $this->splashLoadHistory = [];

        return $history;
    }

    public function endSplashLoading()
    {
        $this->splashLoadingActive = false;
    }

    public function splashFinishLoading()
    {
        $this->splashLoadingActive = false;
        $this->splashPendingLoadMessage = 'LOAD complete';
    }

    public function isSplashLoadingActive()
    {
        return $this->splashLoadingActive;
    }

    /**
     * @param string $message
     */
    public function splashLoadProgress($message)
    {
        if (!$this->splashLoadingActive || !$message) {
            return;
        }

        $this->splashPendingLoadMessage = $message;
        $this->splashLoadHistory[] = $message;

        if (count($this->splashLoadHistory) > 500) {
            $this->splashLoadHistory = array_slice($this->splashLoadHistory, -300);
        }
    }

    /**
     * @param string $text
     * @param float $progress 0..1
     */
    public function splashProgress($text, $progress)
    {
        if ($text) {
            $this->splashLoadProgress($text);
        }
    }

    /**
     * Запустить коллбэка в очереди потоков IDE.
     * callback — фоновый поток; after и error — только UI-поток (uiLater).
     *
     * @param callable $callback
     * @param callable|null $after
     * @param callable|null $error
     */
    public static function async(callable $callback, callable $after = null, callable $error = null)
    {
        FxeAsyncManager::runParallel(null, $callback, $after, $error);
    }

    public function isWindows()
    {
        return Str::contains($this->OS, 'win');
    }

    public function isLinux()
    {
        return Str::contains($this->OS, 'nix') || Str::contains($this->OS, 'nux') || Str::contains($this->OS, 'aix');
    }

    public function isMac()
    {
        return Str::contains($this->OS, 'mac');
    }

    /**
     * @return IdeLibrary
     */
    public function getLibrary()
    {
        return $this->library;
    }

    /**
     * Менеджер тулов/утилит.
     *
     * @return IdeToolManager
     */
    public function getToolManager()
    {
        return $this->toolManager;
    }

    /**
     * Утилита для локализации.
     *
     * @return L10n
     */
    public function getL10n()
    {
        if (!$this->l10n && $this->language) {
            $language = $this->languages[$this->language->getAltLang()];
            $this->l10n = $this->language->getL10n($language ? $language->getL10n() : $language);
        }

        return $this->l10n;
    }

    /**
     * Текущий язык.
     *
     * @return IdeLanguage
     */
    public function getLanguage()
    {
        return $this->language;
    }

    /**
     * Списки доступных языков IDE.
     *
     * @return IdeLanguage[]
     */
    public function getLanguages()
    {
        return $this->languages;
    }

    /**
     * @param \Exception|\Error $e
     * @param string $context
     */
    public function sendError($e, $context = 'global')
    {
        if (Ide::service()->canPrivate() && Ide::accountManager()->isAuthorized()) {
            try {
                Ide::service()->ide()->sendErrorAsync($e, function () {

                });
            } catch (\Exception $e) {
                echo "Unable to send error, exception = {$e->getMessage()}\n";
            }
        }
    }

    public function makeEnvironment()
    {
        $env = System::getEnv();

        if ($this->getJrePath()) {
            $env['JAVA_HOME'] = $this->getJrePath();
        }

        if ($this->getGradlePath()) {
            $env['GRADLE_HOME'] = $this->getGradlePath();
        }

        if ($this->getApacheAntPath()) {
            $env['ANT_HOME'] = $this->getApacheAntPath();
        }

        return $env;
    }

    public function getInnoSetupProgram()
    {
        $innoPath = new File($this->getToolPath(), '/innoSetup/ISCC.exe');

        return $innoPath && $innoPath->exists() ? $innoPath->getCanonicalFile() : null;
    }

    public function getLaunch4JPath()
    {
        return fs::parent($this->getLaunch4JProgram());
    }

    public function getLaunch4JProgram()
    {
        if (Ide::get()->isWindows()) {
            $launch4jPath = new File($this->getToolPath(), '/Launch4j/launch4jc.exe');
        } else {
            $launch4jPath = new File($this->getToolPath(), '/Launch4jLinux/launch4j');
        }

        return $launch4jPath && $launch4jPath->exists() ? $launch4jPath->getCanonicalFile() : null;
    }

    public function getApacheAntProgram()
    {
        $antPath = $this->getApacheAntPath();

        if ($antPath) {
            return FileUtils::normalizeName("$antPath/bin/ant" . ($this->isWindows() ? '.bat' : ''));
        } else {
            return 'ant';
        }
    }

    public function getGradleProgram()
    {
        $gradlePath = $this->getGradlePath();

        if ($gradlePath) {
            return FileUtils::normalizeName("$gradlePath/bin/gradle" . ($this->isWindows() ? '.bat' : ''));
        } else {
            return 'gradle';
        }

        //throw new \Exception("Unable to find gradle bin");
    }

    /**
     * Вернуть путь к папке tools IDE.
     *
     * @return null|string
     */
    public function getToolPath()
    {
        $launcher = System::getProperty('develnext.launcher');

        switch ($launcher) {
            case 'root':
                $path = $this->getOwnFile('tools/');
                break;
            default:
                $path = $this->getOwnFile('../tools/');
        }

        if ($this->isDevelopment() && !$path->exists()) {
            $path = $this->getOwnFile('../develnext-tools/');
        }

        $file = $path && $path->exists() ? fs::abs($path) : null;

        //Logger::info("Detect tool path: '$file', mode = {$this->mode}, launcher = {$launcher}");

        return $file;
    }

    /**
     * Вернуть путь к apache ant тулу IDE.
     *
     * @return null|File
     */
    public function getApacheAntPath()
    {
        $antPath = new File($this->getToolPath(), '/apache-ant');

        if (!$antPath->exists()) {
            $antPath = System::getEnv()['ANT_HOME'];

            if ($antPath) {
                $antPath = File::of($antPath);
            }
        }

        return $antPath && $antPath->exists() ? $antPath->getCanonicalFile() : null;
    }

    /**
     * Вернуть путь к Gradle дистрибутиву.
     *
     * @deprecated не используется больше!
     * @return null|File
     */
    public function getGradlePath()
    {
        $gradlePath = new File($this->getToolPath(), '/gradle');

        if (!$gradlePath->exists()) {
            $gradlePath = System::getEnv()['GRADLE_HOME'];

            if ($gradlePath) {
                $gradlePath = File::of($gradlePath);
            }
        }

        return $gradlePath && $gradlePath->exists() ? $gradlePath->getCanonicalFile() : null;
    }

    /**
     * Вернуть путь к JRE среды (Java Runtime Environment).
     *
     * @return null|File
     */
    public function getJrePath()
    {
        $path = $this->getToolPath();

        if ($this->isWindows() || $this->isLinux()) {
            $jrePath = new File($path, '/jre');

            if ($this->isLinux() && (new File($path, '/jreLinux'))->isDirectory()) {
                $jrePath = new File($path, '/jreLinux');
            }
        } else {
            $jrePath = null;
        }

        if (!$jrePath || !$jrePath->exists()) {
            $jrePath = System::getEnv()['JAVA_HOME'];

            if ($jrePath) {
                $jrePath = File::of($jrePath);
            }
        }

        return $jrePath && $jrePath->exists() ? $jrePath->getCanonicalFile() : null;
    }

    /**
     * Dev режим работы IDE?
     *
     * @return bool
     */
    public function isDevelopment()
    {
        return IdeSystem::isDevelopment();
    }

    /**
     * Prod режим работы IDE?
     *
     * @return bool
     */
    public function isProduction()
    {
        return Str::equalsIgnoreCase($this->mode, 'prod');
    }

    /**
     * Вернуть splash форму IDE.
     *
     * @return SplashForm
     */
    public function getSplash()
    {
        return $this->splash;
    }

    /**
     * Задать заголовок главной формы IDE.
     *
     * @param $value
     */
    public function setTitle($value)
    {
        $title = $this->getName() . ' ' . $this->getVersion();

        if ($value) {
            $title = $value . ' - ' . $title;
        }

        $this->getMainForm()->title = $title;
    }

    protected function readLanguages()
    {
        $this->languages = [];

        $directory = IdeSystem::getOwnFile('languages');

        if (self::isDevelopment() && !fs::isDir($directory)) {
            $directory = IdeSystem::getOwnFile('misc/languages');
        }

        fs::scan($directory, function ($path) {
            if (fs::isDir($path)) {
                $code = fs::name($path);

                Logger::debug("Add ide language '$code', path = $path");

                $this->languages[$code] = new IdeLanguage($code, $path);
            }
        }, 1);

        Logger::info("Languages loaded: " . str::join(array_keys($this->languages), ', '));

        $ideLanguage = $this->getUserConfigValue('ide.language', System::getProperty('user.language'));

        if (!$this->languages[$ideLanguage]) {
            $ideLanguage = 'en';
        }

        $this->language = $this->languages[$ideLanguage];

        if ($this->language) {
            $this->language->load();

            if ($altLanguage = $this->languages[$this->language->getAltLang()]) {
                $altLanguage->load();
            }
        }

        $this->setUserConfigValue('ide.language', $ideLanguage);
    }

    /**
     * Вернуть именнованный конфиг из системной папки IDE.
     *
     * @param string $name
     * @return IdeConfiguration
     */
    public function getUserConfig($name)
    {
        $name = FileUtils::normalizeName($name);

        if ($config = $this->configurations[$name]) {
            return $config;
        }

        try {
            $config = new IdeConfiguration($this->getFile("$name.conf"));
        } catch (IOException $e) {
            // ...
        }

        return $this->configurations[$name] = $config;
    }

    /**
     * Вернуть значение глобального конфига, из ide.conf.
     *
     * @param string $key
     * @param mixed $def
     *
     * @return string
     */
    public function getUserConfigValue($key, $def = null)
    {
        return $this->getUserConfig('ide')->get($key, $def);
    }

    /**
     * Вернуть значение глобального конфига в виде массива, из ide.conf.
     *
     * @param string $key
     * @param mixed $def
     *
     * @return array
     */
    public function getUserConfigArrayValue($key, $def = [])
    {
        if ($this->getUserConfig('ide')->has($key)) {
            return $this->getUserConfig('ide')->getArray($key, $def);
        } else {
            return $def;
        }
    }

    /**
     * Задать глобальную настройку для IDE, запишет в конфиг ide.conf.
     *
     * @param $key
     * @param $value
     */
    public function setUserConfigValue($key, $value)
    {
        $this->getUserConfig('ide')->set($key, $value);
    }

    /**
     * Вернуть файл из папки, где находится сама IDE.
     *
     * @param string $path
     *
     * @return File
     */
    public static function getOwnFile($path)
    {
        $homePath = System::getProperty('develnext.path', "./");

        $home = $homePath;

        return File::of("$home/$path");
    }

    /**
     * Вернуть файл из системной папки IDE.
     *
     * @param string $path
     *
     * @return File
     */
    public function getFile($path)
    {
        return IdeSystem::getFile($path,
            $this->isSnapshotVersion() ? ".{$this->getVersionHash()}.SNAPSHOT" : ""
        );
    }

    /**
     * Вернуть файлы из директории, отвечающие формату.
     *
     * @param AbstractFormat|string $format
     * @param string $directory
     * @return \string[]
     * @throws IllegalArgumentException
     */
    public function getFilesOfFormat($format, $directory)
    {
        if (is_string($format)) {
            $format = $this->getRegisteredFormat($format);
        }

        if (!$format) {
            throw new IllegalArgumentException("Format is invalid");
        }

        $files = [];

        fs::scan($directory, function ($filename) use ($format, &$files) {
            if ($format->isValid($filename)) {
                $files[] = $filename;
            }
        });

        return $files;
    }

    /**
     * Вернуть текущий лог файл IDE.
     *
     * @return File
     */
    public function getLogFile()
    {
        $uuid = $this->getInstanceId();

        return $this->getFile("log/ide-$uuid.log");
    }

    /**
     * Очистить специализированную папку IDE от логов и кэша.
     */
    public function cleanup()
    {
        (new Thread(function() {
            Logger::info("Clean IDE files...");

            fs::scan($this->getFile("log/"), function ($logfile) {
                if (fs::time($logfile) < Time::millis() - 1000 * 60 * 60 * 3) { // 3 hours.
                    fs::delete($logfile);
                }
            });

            fs::scan($this->getFile("cache/"), function ($file) {
                if (fs::time($file) < Time::millis() - 1000 * 60 * 60 * 24 * 30) { // 30 days.
                    fs::delete($file);
                }
            });
        }))->start();
    }

    /**
     * Создать временный файл с специализированной папке IDE.
     *
     * @param string $suffix
     * @return File
     */
    public function createTempFile($suffix = '')
    {
        $tempDir = $this->getFile('tmp');

        if (!fs::isDir($tempDir)) {
            if (fs::exists($tempDir)) {
                fs::delete($tempDir);
            }
        }

        $tempDir->mkdirs();

        $file = File::createTemp(Str::random(5), Str::random(10) . $suffix, $tempDir);
        $file->deleteOnExit();
        return $file;
    }


    /**
     * @param string $suffix
     * @return File
     */
    public function createTempDirectory(string $suffix)
    {
        $tempDir = $this->getFile("tmp/$suffix");

        if (!fs::isDir($tempDir)) {
            if (fs::exists($tempDir)) {
                fs::delete($tempDir);
            }
        }

        $tempDir->mkdirs();

        return $tempDir;
    }

    /**
     * Вернуть список всех зарегистрированных шаблонов проекта.
     *
     * @return AbstractProjectTemplate[]
     */
    public function getProjectTemplates()
    {
        return $this->projectTemplates;
    }

    /**
     * @return AbstractProjectSupport[]
     */
    public function getProjectSupports(): array
    {
        return $this->projectSupports;
    }

    /**
     * Зарегистрировать шаблон проекта.
     *
     * @param AbstractProjectTemplate $template
     */
    public function registerProjectTemplate(AbstractProjectTemplate $template)
    {
        $class = get_class($template);

        if (isset($this->projectTemplates[$class])) {
            return;
        }

        $this->projectTemplates[$class] = $template;
    }

    /**
     * Отменить регистрацию одной команды по ее имени класса.
     *
     * @param $commandClass
     * @param bool $ignoreAlways
     */
    public function unregisterCommand($commandClass, $ignoreAlways = true)
    {
        /** @var MainForm $mainForm */
        $mainForm = $this->getMainForm();

        $data = $this->commands[$commandClass];

        if (!$data) {
            return;
        }

        /** @var AbstractCommand $command */
        $command = $data['command'];

        if (!$ignoreAlways && $command->isAlways()) {
            return;
        }

        if ($data['headUi']) {
            if (is_array($data['headUi'])) {
                foreach ($data['headUi'] as $ui) {
                    $mainForm->getTitleBarToolsPane()->remove($ui);
                }
            } else {
                $mainForm->getTitleBarToolsPane()->remove($data['headUi']);
            }
        }

        if ($data['headRightUi']) {
            if (is_array($data['headRightUi'])) {
                foreach ($data['headRightUi'] as $ui) {
                    $mainForm->getTitleBarToolsPane()->remove($ui);
                }
            } else {
                $mainForm->getTitleBarToolsPane()->remove($data['headRightUi']);
            }
        }

        if ($data['menuItem']) {
            /** @var UXMenu $menu */
            $menu = $mainForm->findSubMenu('menu' . Str::upperFirst($command->getCategory()));

            if ($menu instanceof UXMenu) {
                foreach ($data['menuItem'] as $el) {
                    $menu->items->remove($el);
                }
            }
        }

        unset($this->commands[$commandClass]);
    }

    /**
     * Отменить регистрацию всех команд.
     */
    public function unregisterCommands()
    {
        /** @var MainForm $mainForm */
        $mainForm = $this->getMainForm();

        if (!$mainForm) {
            return;
        }

        foreach ($this->commands as $code => $data) {
            $this->unregisterCommand($code, false);
        }
    }

    /**
     * Выполнить зарегистрированную команду по названию ее класса.
     *
     * @param $commandClass
     */
    public function executeCommand($commandClass)
    {
        Logger::info("Execute command - $commandClass");

        $command = $this->getRegisteredCommand($commandClass);

        if ($command) {
            $command->onExecute();
        } else {
            throw new \InvalidArgumentException("Unable to execute $commandClass command, it is not registered");
        }
    }

    /**
     * Вернуть список всех зарегистрированных команд IDE (для командной палитры и т.п.).
     *
     * @return AbstractCommand[]
     */
    public function getCommands()
    {
        $result = [];

        foreach ($this->commands as $data) {
            if ($data['command'] instanceof AbstractCommand) {
                $result[] = $data['command'];
            }
        }

        return $result;
    }

    /**
     * Вернуть команду по ее классу.
     *
     * @param string $commandClass class or uid
     * @return AbstractCommand|null
     */
    public function getRegisteredCommand($commandClass)
    {
        $command = $this->commands[$commandClass];

        if ($command) {
            /** @var AbstractCommand $command */
            $command = $command['command'];
            return $command;
        }

        return null;
    }

    /**
     * Зарегистрировать IDE команду.
     *
     * @param AbstractCommand $command
     * @param null $category
     */
    public function registerCommand(AbstractCommand $command, $category = null)
    {
        $this->unregisterCommand($command->getUniqueId());

        $data = [
            'command' => $command,
        ];

        $category = $category ?: $command->getCategory();
        $menuItem = $command->makeMenuItem();

        if ($menuItem) {
            $data['menuItem'] = $menuItem;

            $this->afterShow(function () use ($menuItem, $command, &$data, $category) {
                /** @var MainForm $mainForm */
                $mainForm = $this->getMainForm();

                /** @var UXMenu $menu */
                $menu = $mainForm->findSubMenu('menu' . Str::upperFirst($category));

                if ($menu instanceof UXMenu) {
                    $items = [];

                    if ($command->withBeforeSeparator()) {
                        /** @var UXMenuItem $last */
                        $last = $menu->items->last();

                        if ($last && $last->isSeparator()) {
                            // do nothing...
                        } else {
                            $items[] = UXMenuItem::createSeparator();
                        }
                    }

                    $items[] = $menuItem;

                    if ($command->withAfterSeparator()) {
                        $items[] = UXMenuItem::createSeparator();
                    }

                    foreach ($items as $el) {
                        $menu->items->add($el);
                    }

                    $data['menuItem'] = $items;
                }


            });
        }

        $headUi = $command->isTitleBarVisible() ? $command->makeUiForHead() : null;

        if ($headUi) {
            $data['headUi'] = $headUi;

            $this->afterShow(function () use ($headUi) {
                /** @var MainForm $mainForm */
                $mainForm = $this->getMainForm();

                if (!is_array($headUi)) {
                    $headUi = [$headUi];
                }

                foreach ($headUi as $ui) {
                    if ($ui instanceof UXButton) {
                        $ui->maxHeight = 9999;
                    } else if ($ui instanceof UXSeparator) {
                        $ui->paddingLeft = 3;
                        $ui->paddingRight = 1;
                    }

                    if ($ui->id) {
                        foreach ($mainForm->getTitleBarToolsPane()->children as $child) {
                            if ($child->id === $ui->id) {
                                $mainForm->getTitleBarToolsPane()->remove($child);
                            }
                        }
                    }

                    $mainForm->getTitleBarToolsPane()->add($ui);
                }
            });
        }

        $headRightUi = $command->isTitleBarVisible() ? $command->makeUiForRightHead() : null;

        if ($headRightUi) {
            $data['headRightUi'] = $headRightUi;

            $this->afterShow(function () use ($headRightUi) {
                /** @var MainForm $mainForm */
                $mainForm = $this->getMainForm();

                if (!is_array($headRightUi)) {
                    $headRightUi = [$headRightUi];
                }

                foreach ($headRightUi as $ui) {
                    if ($ui instanceof UXButton) {
                        $ui->maxHeight = 999;
                    } else if ($ui instanceof UXSeparator) {
                        $ui->paddingLeft = 3;
                        $ui->paddingRight = 1;
                    }

                    $mainForm->getTitleBarToolsPane()->add($ui);
                }
            });
        }

        $this->commands[$command->getUniqueId()] = $data;
    }

    /**
     * Вернуть изображение из ресурсов IDE /.data/img/
     *
     * @param string $path
     *
     * @param array $size
     * @param bool $cache
     * @return UXImageView
     */
    public static function getImage($path, array $size = null, $cache = true)
    {
        if ($path === null) {
            return null;
        }

        if ($path instanceof UXImage) {
            $image = $path;
        } elseif ($path instanceof UXImageView) {
            if ($size) {
                $image = $path->image;

                if ($image == null) {
                    return null;
                }
            } else {
                return $path;
            }
        } elseif ($path instanceof UXNode) {
            return $path;
        } elseif ($path instanceof Stream) {
            $image = new UXImage($path);
        } elseif ($path instanceof LazyLoadingImage) {
            $image = $path->getImage();
        } elseif (!is_string($path)) {
            return null;
        } else {
            if (strpos($path, '/') === false && strpos($path, '.') === false) {
                $treeGraphic = TreeIconResolver::loadIcon($path);
                if ($treeGraphic) {
                    if ($size && $treeGraphic instanceof UXImageView) {
                        $treeGraphic->size = $size;
                        $treeGraphic->preserveRatio = true;
                    }

                    return $treeGraphic;
                }
            }

            $resourcePath = "res://.data/img/" . $path;

            if (Str::endsWith(Str::lower($path), '.svg')) {
                $image = static::getSvgImage($resourcePath, $cache);
            } else {
                if ($cache) {
                    $image = Cache::getResourceImage($resourcePath);
                } else {
                    $image = new UXImage($resourcePath);
                }
            }
        }

        if ($image == null) {
            return null;
        }

        if ($image instanceof LazyLoadingImage) {
            $result = new UXImageView();

            if ($size) {
                $result->size = $size;
                $result->preserveRatio = true;
            }

            $loader = $image;

            uiLater(function () use ($result, $loader) {
                try {
                    $loaded = $loader->getImage();

                    if ($loaded instanceof UXImage) {
                        $result->image = $loaded;
                    }
                } catch (\Throwable $e) {
                }
            });

            return $result;
        }

        if (!($image instanceof UXImage)) {
            return null;
        }

        $result = new UXImageView();
        $result->image = $image;

        if ($size) {
            $result->size = $size;
            $result->preserveRatio = true;
        }

        return $result;
    }

    /**
     * Рендерит простые SVG (path/ellipse) в UXImage.
     *
     * @param string $resourcePath
     * @param bool $cache
     * @return UXImage|null
     */
    protected static function getSvgImage($resourcePath, $cache = true)
    {
        if ($cache && isset(static::$svgImageCache[$resourcePath])) {
            return static::$svgImageCache[$resourcePath];
        }

        try {
            $svg = FileUtils::get($resourcePath);
            if (!$svg) {
                return null;
            }

            $size = static::parseSvgSize($svg);
            $canvas = new UXCanvas();
            $canvas->width = $size[0];
            $canvas->height = $size[1];

            $gc = $canvas->gc();
            static::drawSvgPaths($gc, $svg);
            static::drawSvgEllipses($gc, $svg);

            $image = $canvas->snapshot();
            if ($cache && $image) {
                static::$svgImageCache[$resourcePath] = $image;
            }

            return $image;
        } catch (\Throwable $e) {
            Logger::warn('SVG icon render failed: ' . $resourcePath . ' — ' . $e->getMessage());
            return null;
        }
    }

    /**
     * @param string $svg
     * @return array [width, height]
     */
    protected static function parseSvgSize($svg)
    {
        $viewBox = Regex::of('viewBox\\s*=\\s*"([^"]+)"', Regex::CASE_INSENSITIVE, $svg)->one();
        if ($viewBox) {
            $parts = Regex::split('\\s+', trim($viewBox[1]));
            if (count($parts) === 4) {
                return [max(1.0, (float) $parts[2]), max(1.0, (float) $parts[3])];
            }
        }

        $width = 16.0;
        $height = 16.0;

        $widthMatch = Regex::of('width\\s*=\\s*"([0-9.]+)', Regex::CASE_INSENSITIVE, $svg)->one();
        if ($widthMatch) {
            $width = max(1.0, (float) $widthMatch[1]);
        }

        $heightMatch = Regex::of('height\\s*=\\s*"([0-9.]+)', Regex::CASE_INSENSITIVE, $svg)->one();
        if ($heightMatch) {
            $height = max(1.0, (float) $heightMatch[1]);
        }

        return [$width, $height];
    }

    /**
     * @param string $pattern
     * @param string $svg
     * @param int $group
     * @return string[]
     */
    protected static function svgFindAll($pattern, $svg, $group = 1)
    {
        $result = [];
        $all = Regex::of($pattern, Regex::CASE_INSENSITIVE | Regex::DOTALL, $svg)->all();

        if (!$all) {
            return $result;
        }

        foreach ($all as $groups) {
            if (isset($groups[$group]) && $groups[$group] !== '') {
                $result[] = $groups[$group];
            }
        }

        return $result;
    }

    /**
     * @param \php\gui\UXGraphicsContext $gc
     * @param string $svg
     */
    protected static function drawSvgPaths($gc, $svg)
    {
        foreach (static::svgFindAll('<path\\s+([^>]+?)/?>', $svg) as $rawAttrs) {
            $attrs = static::parseSvgAttributes($rawAttrs);
            $d = $attrs['d'] ?? null;
            if (!$d) {
                continue;
            }

            $gc->beginPath();
            $gc->appendSVGPath($d);

            $fillColor = static::toSvgColor($attrs['fill'] ?? null);
            $strokeColor = static::toSvgColor($attrs['stroke'] ?? null);

            $fillRule = Str::lower($attrs['fill-rule'] ?? '');
            $gc->fillRule = $fillRule === 'evenodd' ? 'EVEN_ODD' : 'NON_ZERO';

            if ($fillColor) {
                $gc->fill = $fillColor;
                $gc->fill();
            }

            if ($strokeColor) {
                $gc->stroke = $strokeColor;
                if (isset($attrs['stroke-width']) && $attrs['stroke-width'] !== '') {
                    $gc->lineWidth = (float) $attrs['stroke-width'];
                }

                $lineCap = Str::upper($attrs['stroke-linecap'] ?? '');
                if (in_array($lineCap, ['ROUND', 'BUTT', 'SQUARE'], true)) {
                    $gc->lineCap = $lineCap;
                }

                $gc->stroke();
            }
        }
    }

    /**
     * @param \php\gui\UXGraphicsContext $gc
     * @param string $svg
     */
    protected static function drawSvgEllipses($gc, $svg)
    {
        foreach (static::svgFindAll('<ellipse\\s+([^>]+?)/?>', $svg) as $rawAttrs) {
            $attrs = static::parseSvgAttributes($rawAttrs);
            $fillColor = static::toSvgColor($attrs['fill'] ?? null);
            if (!$fillColor) {
                continue;
            }

            $cx = isset($attrs['cx']) ? (float) $attrs['cx'] : 0.0;
            $cy = isset($attrs['cy']) ? (float) $attrs['cy'] : 0.0;
            $rx = isset($attrs['rx']) ? (float) $attrs['rx'] : 0.0;
            $ry = isset($attrs['ry']) ? (float) $attrs['ry'] : 0.0;
            if ($rx <= 0 || $ry <= 0) {
                continue;
            }

            $ellipsePath = sprintf(
                'M %F %F A %F %F 0 1 0 %F %F A %F %F 0 1 0 %F %F Z',
                $cx - $rx, $cy, $rx, $ry, $cx + $rx, $cy, $rx, $ry, $cx - $rx, $cy
            );

            $gc->beginPath();
            $gc->appendSVGPath($ellipsePath);
            $gc->fill = $fillColor;
            $gc->fillRule = 'NON_ZERO';
            $gc->fill();
        }
    }

    /**
     * @param string $rawAttrs
     * @return array
     */
    protected static function parseSvgAttributes($rawAttrs)
    {
        $result = [];
        $pairs = Regex::of('([\\w:-]+)\\s*=\\s*"([^"]*)"', 0, $rawAttrs)->all();

        if ($pairs) {
            foreach ($pairs as $pair) {
                if (isset($pair[1], $pair[2])) {
                    $result[Str::lower($pair[1])] = $pair[2];
                }
            }
        }

        return $result;
    }

    /**
     * @param string|null $value
     * @return UXColor|null
     */
    protected static function toSvgColor($value)
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '' || Str::lower($value) === 'none') {
            return null;
        }

        try {
            return UXColor::of($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Вернуть зарегистрированный формат по его классу.
     * В приоритете форматы, зарегистирированные в проекте, а уже затем - глобальные форматы.
     *
     * @param $class
     * @return AbstractFormat
     */
    public function getRegisteredFormat($class)
    {
        if ($project = $this->getOpenedProject()) {
            if ($format = $project->getRegisteredFormat($class)) {
               return $format;
            }
        }

        return $this->_getRegisteredFormat($class);
    }

    /**
     * Найти формат для редактирования файла/пути.
     *
     * @param $path
     *
     * @return AbstractFormat|null
     */
    public function getFormat($path)
    {
        if ($project = $this->getOpenedProject()) {
            /** @var AbstractFormat $format */
            foreach (arr::reverse($project->getRegisteredFormats()) as $format) {
                if ($format->isValid($path)) {
                    return $format;
                }
            }
        }

        /** @var AbstractFormat $format */
        foreach (arr::reverse($this->formats) as $format) {
            if ($format->isValid($path)) {
                return $format;
            }
        }

        if ($this->isTextEditableFile($path)) {
            return $this->getRegisteredFormat(TextCodeFormat::class);
        }

        return null;
    }

    /**
     * @param string|\php\io\File $path
     * @return bool
     */
    protected function isTextEditableFile($path)
    {
        if (!fs::isFile($path)) {
            return false;
        }

        static $binaryExts = [
            'png' => 1, 'jpg' => 1, 'jpeg' => 1, 'bmp' => 1, 'gif' => 1, 'ico' => 1,
            'wav' => 1, 'ogg' => 1, 'wave' => 1, 'mp3' => 1, 'aif' => 1, 'aiff' => 1,
            'zip' => 1, 'rar' => 1, '7z' => 1, 'mp4' => 1, 'flv' => 1,
            'exe' => 1, 'dll' => 1, 'phb' => 1, 'ttf' => 1, 'woff' => 1, 'woff2' => 1, 'eot' => 1,
            'pdf' => 1, 'dnproject' => 1,
        ];

        return !isset($binaryExts[fs::ext($path)]);
    }

    /**
     * Вернуть текущий открытый проект.
     *
     * @return Project
     */
    public function getOpenedProject()
    {
        return $this->openedProject;
    }

    /**
     * Задать открытый проект.
     *
     * @param Project $openedProject
     */
    public function setOpenedProject($openedProject = null)
    {
        $this->openedProject = $openedProject;

        if ($openedProject) {
            $this->setTitle($openedProject->getName() . " - [" . $openedProject->getRootDir() . "]");
        } else {
            $this->setTitle(null);
        }
    }

    /**
     * Создать редактор для редактирования файла, формат по-умолчанию определяется автоматически,
     * с помощью ранее зарегистрированных редакторов.
     *
     * @param $path
     *
     * @param array $options
     * @param string $format
     * @return AbstractEditor
     */
    public function createEditor($path, array $options = [], $format = null)
    {
        $format = $format ? $this->getRegisteredFormat($format) : $this->getFormat($path);

        if ($format) {
            $editor = $format->createEditor($path, $options);

            if ($editor) {
                $editor->setFormat($format);
            }

            if ($project = Ide::project()) {
                if (!str::startsWith(FileUtils::hashName($path), FileUtils::hashName($project->getRootDir()))) {
                    $editor->setReadOnly(true);
                }

                $project->trigger('createEditor', $editor);
            }

            return $editor;
        }

        return null;
    }

    /**
     * @return AccountManager
     */
    public function getAccountManager()
    {
        return $this->accountManager;
    }

    /**
     * @param string|AbstractProjectSupport $support
     * @throws IdeException
     */
    public function registerProjectSupport($support)
    {
        if (is_string($support)) {
            $support = new $support();
        }

        if (isset($this->projectSupports[reflect::typeOf($support)])) {
            return;
        }


        Logger::debug("Register IDE project support " . reflect::typeOf($support));


        if (!($support instanceof AbstractProjectSupport)) {
            throw new IdeException("Unable to add project support " . reflect::typeOf($support) . ", is not correct type");
        }

        $this->projectSupports[reflect::typeOf($support)] = $support;
        $support->onRegisterInIDE();
    }

    /**
     * Зарегистрировать расширение IDE (по названию класса или его экземпляру).
     *
     * @param string|AbstractExtension $extension
     * @throws IdeException
     */
    public function registerExtension($extension)
    {
        if (is_string($extension)) {
            $extension = new $extension();
        }

        if (isset($this->extensions[reflect::typeOf($extension)])) {
            return;
        }

        Logger::debug("Register IDE extension " . reflect::typeOf($extension));

        if (!($extension instanceof AbstractExtension)) {
            throw new IdeException("Unable to add extension " . reflect::typeOf($extension) . ", is not correct type");
        }

        $this->extensions[reflect::typeOf($extension)] = $extension;

        foreach ((array) $extension->getDependencies() as $class) {
            $dep = new $class();

            if ($dep instanceof AbstractBundle) {
                IdeSystem::getLoader()->addClassPath($dep->getVendorDirectory());
            } else {
                $this->registerExtension($extension);
            }
        }

        $extension->onRegister();
    }

    public function registerAll()
    {
        $this->cleanup();

        foreach ($this->getInternalList('.dn/extensions') as $extension) {
            $this->registerExtension($extension);
        }

        foreach ($this->getInternalList('.dn/propertyValueEditors') as $valueEditor) {
            $valueEditor = new $valueEditor();
            ElementPropertyEditor::register($valueEditor);
        }

        foreach ($this->getInternalList('.dn/formats') as $format) {
            $this->registerFormat(new $format());
        }

        foreach ($this->getInternalList('.dn/projectTemplates') as $projectTemplate) {
            $this->registerProjectTemplate(new $projectTemplate());
        }

        $mainCommands = $this->getInternalList('.dn/mainCommands');
        $commands = [];

        foreach ($mainCommands as $commandClass) {
            $commands[] = new $commandClass();
        }

        $commands = arr::sort($commands, function (AbstractCommand $a, AbstractCommand $b) {
            if ($a->getPriority() == $b->getPriority()) {
                return 0;
            }
            if ($a->getPriority() < $b->getPriority()) {
                return 1;
            }
            return -1;
        });

        foreach ($commands as $command) {
            $this->registerCommand($command);
        }

        $this->finishRegisterAll();
    }

    /**
     * @param callable $onDone
     */
    public function registerAllDeferred(callable $onDone)
    {
        $phases = [
            function () {
                $this->cleanup();

                foreach ($this->getInternalList('.dn/extensions') as $extension) {
                    $this->registerExtension($extension);
                }
            },
            function () {
                foreach ($this->getInternalList('.dn/propertyValueEditors') as $valueEditor) {
                    $valueEditor = new $valueEditor();
                    ElementPropertyEditor::register($valueEditor);
                }
            },
            function () {
                foreach ($this->getInternalList('.dn/formats') as $format) {
                    $this->registerFormat(new $format());
                }
            },
            function () {
                foreach ($this->getInternalList('.dn/projectTemplates') as $projectTemplate) {
                    $this->registerProjectTemplate(new $projectTemplate());
                }
            },
            function () {
                $mainCommands = $this->getInternalList('.dn/mainCommands');
                $commands = [];

                foreach ($mainCommands as $commandClass) {
                    $commands[] = new $commandClass();
                }

                $commands = arr::sort($commands, function (AbstractCommand $a, AbstractCommand $b) {
                    if ($a->getPriority() == $b->getPriority()) {
                        return 0;
                    }
                    if ($a->getPriority() < $b->getPriority()) {
                        return 1;
                    }
                    return -1;
                });

                foreach ($commands as $command) {
                    $this->registerCommand($command);
                }
            },
        ];

        $runPhase = function ($index) use (&$runPhase, $phases, $onDone) {
            if ($index >= count($phases)) {
                $onDone();
                return;
            }

            $phases[$index]();

            waitAsync(8, function () use (&$runPhase, $index, $onDone) {
                $runPhase($index + 1);
            });
        };

        $runPhase(0);
    }

    protected function finishRegisterAll()
    {
        $this->library->update();

        /** @var AccurateTimer $inactiveTimer */
        $inactiveTimer = new AccurateTimer(3 * 60 * 1000, function () {
            $this->idle = true;
            Logger::info("IDE is sleeping, idle mode ...");
            $this->trigger('idleOn');
        });
        $inactiveTimer->start();

        $this->getMainForm()->addEventFilter('mouseMove', function () use (&$inactiveTimer) {
            if ($inactiveTimer) {
                $inactiveTimer->reset();
            }

            if ($this->idle) {
                Logger::info("IDE awake, idle mode = off ...");
                $this->trigger('idleOff');
            }

            $this->idle = false;
        });

        $ideConfig = $this->getUserConfig('ide');

        $defaultProjectDir = File::of(System::getProperty('user.home') . '/FXEStudioProjects');

        if (!fs::isDir($ideConfig->get('projectDirectory'))) {
            $ideConfig->set('projectDirectory', "$defaultProjectDir/");
        }

        if ($this->isSnapshotVersion()) {
            $ideConfig->set('projectDirectory', "$defaultProjectDir.{$this->getVersionHash()}.SNAPSHOT");
        }

        $this->afterShow(function () {
            $projectFile = $this->getUserConfigValue('lastProject');

            FileSystem::open('~welcome');

            if (!$this->disableOpenLastProject && $projectFile && File::of($projectFile)->exists()) {
                ProjectSystem::openOnSplash($projectFile, false, false, false);
            }
        });
    }

    /**
     * Добавить коллбэка, выполняющийся после старта и показа IDE.
     * Если IDE уже была показана, коллбэк будет выполнен немедленно.
     *
     * @param callable $handle
     */
    public function afterShow(callable $handle)
    {
        if ($this->mainFormShown) {
            $handle();
        } else {
            $this->afterShow[] = $handle;
        }
    }

    protected function runAfterShowHandlers()
    {
        if ($this->mainFormShown) {
            return;
        }

        $this->mainFormShown = true;

        foreach ($this->afterShow as $handle) {
            $handle();
        }

        $this->afterShow = [];
    }

    /**
     * Находится ли IDE в спящем режиме (т.е. не используется).
     * @return boolean
     */
    public function isIdle()
    {
        return $this->idle;
    }

    /**
     * Вернуть главную форму.
     * @return MainForm
     */
    public function getMainForm()
    {
        return parent::getMainForm();
    }


    /**
     * @return Ide
     * @throws \Exception
     */
    public static function get()
    {
        return parent::get();
    }

    /**
     * Вернуть открытый проект.
     * @return Project
     */
    public static function project()
    {
        return self::get()->getOpenedProject();
    }

    /**
     * @return AccountManager
     */
    public static function accountManager()
    {
        return Ide::get()->getAccountManager();
    }

    /**
     * @return ServiceManager
     */
    public static function service()
    {
        return Ide::get()->serviceManager;
    }

    /**
     * Показать toast сообщение на главной форме IDE.
     *
     * @param $text
     * @param int $timeout
     */
    public static function toast($text, $timeout = 0)
    {
        \ide\ui\FxeToast::message($text, $timeout);
    }

    /**
     * Завершить работу IDE.
     */
    public function shutdown()
    {
        $this->shutdown = true;

        $done = false;

        $shutdownTh = (new Thread(function () use (&$done) {
            sleep(40);

            while (!$done) {
                sleep(1);
            }

            Logger::warn("System halt 0\n");
            System::halt(0);
        }));

        $shutdownTh->setName("DevelNext Shutdown");
        $shutdownTh->start();

        Logger::info("Start IDE shutdown ...");

        FxeProcessSystem::stopAll();

        $this->trigger(__FUNCTION__);

        (new Thread(function () {
            Logger::info("Shutdown asyncThreadPool");
            $this->asyncThreadPool->shutdown();
            FxeAsyncManager::shutdown();
        }))->start();

        foreach ($this->extensions as $extension) {
            try {
                Logger::info("Shutdown IDE extension " . get_class($extension) . ' ...');
                $extension->onIdeShutdown();
            } catch (\Exception $e) {
                Logger::exception("Unable to shutdown IDE extension " . get_class($extension), $e);
            }
        }

        $project = $this->getOpenedProject();

        $this->mainForm->hide();

        foreach ($this->configurations as $name => $config) {
            if ($config->isAutoSave()) {
                $config->saveFile();
            }
        }

        if ($project) {
            FileSystem::getSelectedEditor()->save();
            ProjectSystem::close(false);
        }

        //WatcherSystem::shutdown();

        Logger::info("Finish IDE shutdown");

        try {
            Logger::shutdown();
            parent::shutdown();
        } catch (\Exception $e) {
            //System::halt(0);
        }

        $done = true;
    }

    /**
     * @param $resourceName
     * @return array
     */
    public function getInternalList($resourceName)
    {
        static $cache;

        if ($result = $cache[$resourceName]) {
            return $result;
        }

        $resources = ResourceStream::getResources($resourceName);

        $result = [];

        if (!$resources) {
            Logger::warn("Internal list '$resourceName' is empty");
        }

        foreach ($resources as $resource) {
            $scanner = new Scanner($resource, 'UTF-8');

            while ($scanner->hasNextLine()) {
                $line = Str::trim($scanner->nextLine());

                if ($line && !str::startsWith($line, '#')) {
                    $result[] = $line;
                }
            }
        }

        return $cache[$resourceName] = $result;
    }

    /**
     * @param $argv
     * @return bool
     */
    protected function handleArgs($argv)
    {
        $arg = $argv[1];

        if (str::startsWith($arg, 'develnext://')) {
            $arg = str::sub($arg, str::length('develnext://'));

            $protocolHandlers = $this->getInternalList('.dn/protocolHandlers');

            foreach ($protocolHandlers as $protocolHandler) {
                /** @var AbstractProtocolHandler $protocolHandler */
                $protocolHandler = new $protocolHandler();

                if ($protocolHandler->isValid($arg)) {
                    if ($protocolHandler->handle($arg)) {
                        return true;
                    }
                }
            }
        } else {
            if (fs::isFile($arg)) {
                $defProtocolHandler = new FileOpenProjectProtocolHandler();
                if ($defProtocolHandler->handle($arg)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Отключить открытие последнего редактируемого проекта.
     * Можно применять при старте IDE, чтобы отменить загрузку предыдущего проекта.
     */
    public function disableOpenLastProject()
    {
        $this->disableOpenLastProject = true;
    }

    /**
     * Сравнить версию с версией IDE.
     * @param $otherVersion
     * @return bool
     */
    public function isSameVersionIgnorePatch($otherVersion)
    {
        $version = IdeSystem::getVersionInfo($this->getVersion());
        $otherVersion = IdeSystem::getVersionInfo($otherVersion);

        $result = $otherVersion['type'] === $version['type'] && $otherVersion['major'] == $version['major']
            && $otherVersion['minor'] === $version['minor'];

        Logger::debug("isSameVersionIgnorePatch(): " . Json::encode($version) . " with " . Json::encode($otherVersion)
            . " is " . ($result ? 'true' : 'false'));

        return $result;
    }

    /**
     * Запустить новый экземпляр ide.
     * @param array $args
     */
    public function startNew(array $args = [])
    {
        $jrePath = $this->getJrePath();

        $javaBin = 'java';

        if ($jrePath) {
            $javaBin = "$jrePath/bin/$javaBin";
        }

        $args = flow([$javaBin, '-jar', 'DevelNext.jar'])->append($args)->toArray();

        $process = new Process($args, IdeSystem::getOwnFile(''), $this->makeEnvironment());
        $process->start();

        Ide::toast('Запуск DevelNext, подождите ...');
    }

    /**
     * Restart IDE, запустить рестарт IDE, работает только в production режиме.
     */
    public function restart()
    {
        $this->startNew();

        if (Ide::project()) {
            Ide::get()->setUserConfigValue('lastProject', Ide::project()->getProjectFile());
        }

        $this->shutdown();
    }

    /**
     * @param string $name
     * @param array $libPaths
     * @return null|File
     */
    public function findLibFile($name, array $libPaths = [])
    {
        /** @var File[] $libPaths */
        $libPaths[] = $this->getOwnFile('lib/');

        if ($this->isDevelopment()) {
            $ownFile = $this->getOwnFile('build/install/develnext/lib');
            $libPaths[] = $ownFile;
        }

        $regex = Regex::of('(\.[0-9]+|\-[0-9]+)');

        $name = $regex->with($name)->replace('');

        foreach ($libPaths as $libPath) {
            foreach ($libPath->findFiles() as $file) {
                $filename = $regex->with($file->getName())->replace('');

                if (str::endsWith($filename, '.jar') || str::endsWith($filename, '-SNAPSHOT.jar')) {
                    $filename = str::sub($filename, 0, Str::length($filename) - 4);

                    if (str::endsWith($filename, '-SNAPSHOT')) {
                        $filename = Str::sub($filename, 0, Str::length($filename) - 9);
                    }

                    if ($filename == $name) {
                        return $file;
                    }
                }
            }
        }

        return null;
    }
}