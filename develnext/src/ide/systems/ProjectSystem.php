<?php
namespace ide\systems;

use ide\forms\MainForm;
use ide\forms\BuildProgressForm;
use ide\forms\MessageBoxForm;
use ide\forms\OpenProjectForm;
use ide\Ide;
use ide\Logger;
use ide\project\AbstractProjectTemplate;
use ide\project\InvalidProjectFormatException;
use ide\project\Project;
use ide\project\ProjectConsoleOutput;
use ide\project\ProjectImporter;
use ide\ui\Notifications;
use ide\utils\FileUtils;
use php\gui\UXApplication;
use php\gui\UXButton;
use php\gui\UXDialog;
use php\gui\UXDirectoryChooser;
use php\io\File;
use php\io\IOException;
use php\lang\Invoker;
use php\lang\System;
use php\lang\Thread;
use php\lib\fs;
use php\lib\Items;
use php\lib\Str;
use script\TimerScript;

/**
 * Class ProjectSystem
 * @package ide\systems
 */
class ProjectSystem
{
    protected static function clear()
    {
        //WatcherSystem::clear();
        //WatcherSystem::clearListeners();
        Ide::get()->unregisterCommands();
    }

    /**
     * Compile full project.
     *
     * @param string $env
     * @param ProjectConsoleOutput $consoleOutput
     * @param string $hintCommand
     * @param callable $callback
     */
    static function compileAll($env, ProjectConsoleOutput $consoleOutput, $hintCommand, callable $callback)
    {
        $project = Ide::project();

        if (!$project) {
            return;
        }

        $th = new Thread(function () use ($project, $consoleOutput, $callback, $env, $hintCommand) {
            try {
                $uiLater = Invoker::of('uiLater');

                $logFunc = function () use ($consoleOutput, $uiLater) {
                    return function ($log) use ($consoleOutput, $uiLater) {
                        $uiLater(function () use ($log, $consoleOutput) {
                            $consoleOutput->addConsoleLine($log);
                        });
                    };
                };

                $project->preCompile($env, $logFunc());
                $project->compile($env, $logFunc());

                uiLater(function () use ($consoleOutput, $project, $hintCommand) {
                    if ($consoleOutput instanceof BuildProgressForm) {
                        $consoleOutput->onFxeCompileDone($hintCommand, $project->getRootDir());
                    } else {
                        $consoleOutput->addConsoleLine('> ' . $hintCommand, 'green');
                        $consoleOutput->addConsoleLine('   --> ' . $project->getRootDir() . ' ..', 'gray');
                    }
                });

                uiLater(function () use ($callback) {
                    $callback(true);
                });
            } catch (\Throwable $e) {
                uiLater(function () use ($consoleOutput, $e, $callback) {
                    $file = Ide::project() ? Ide::project()->getAbsoluteFile($e->getFile())->getRelativePath() : $e->getFile();

                    if ($consoleOutput instanceof BuildProgressForm) {
                        $consoleOutput->addConsoleLine("[ERROR] Cannot build project");
                        $consoleOutput->addConsoleLine("{$file}:{$e->getLine()} {$e->getMessage()}");
                    } else {
                        $consoleOutput->addConsoleLine("[ERROR] Cannot build project");
                        $consoleOutput->addConsoleLine("  -> {$file}");
                        $consoleOutput->addConsoleLine("  -> {$e->getMessage()}, on line {$e->getLine()}", 'red');
                    }

                    $callback(false);
                });
            }
        });
        $th->setName("ProjectSystem.compileAll #" . str::random());

        $th->start();
    }


    static public function checkDirectory($path)
    {
        Logger::debug("Check directory: $path");

        if (File::of($path)->find()) {
            $path = File::of($path);

            $msg = new MessageBoxForm("Папка '$path' для проекта должна быть пустой, хотите очистить её, чтобы продолжить?", [
                'Да, очистить и продолжить',
                'Нет, выбрать другую',
                'Отмена'
            ]);

            if ($msg->showDialog()) {
                switch ($msg->getResultIndex()) {
                    case 0:
                        FileUtils::deleteDirectory($path);
                        break;
                    case 1:
                        $dialog = new UXDirectoryChooser();
                        $dialog->initialDirectory = $path;

                        if ($file = $dialog->showDialog()) {
                            return $file;
                        } else {
                            return null;
                        }

                        break;
                    case 2:
                        return null;
                }
            }
        }

        return $path;
    }

    static function import($file, $projectDir = null, $newName = null, callable $afterOpen = null)
    {
        Logger::info("Start import project: file = $file, projectDir = $projectDir");

        ProjectSystem::close();

        if (!$projectDir) {
            $projectDir = FileUtils::stripExtension($file);
        }

        if (!($projectDir = self::checkDirectory($projectDir))) {
            ProjectSystem::closeWithWelcome();
            return;
        }

        FileUtils::deleteDirectory($projectDir);

        $importer = new ProjectImporter($file);

        LoadingManager::runMainFormChained(
            'Подготовка импорта ...',
            function () use ($importer, $projectDir, $newName) {
                return ['importer' => $importer, 'projectDir' => $projectDir, 'newName' => $newName];
            },
            'Распаковка архива ...',
            function ($ctx) {
                $ctx['importer']->extract($ctx['projectDir']);

                $files = File::of($ctx['projectDir'])->findFiles(function (File $dir, $name) {
                    return (Str::endsWith($name, '.dnproject'));
                });

                if (!$files) {
                    return null;
                }

                $file = File::of(Items::first($files));

                if ($ctx['newName']) {
                    $target = File::of($file->getParent() . "/{$ctx['newName']}.dnproject");
                    $file->renameTo($target);
                    $file = $target;
                }

                return $file->getPath();
            },
            'Открытие проекта ...',
            function ($projectFile) use ($afterOpen) {
                if (!$projectFile) {
                    UXDialog::show('В архиве не обнаружен файл проекта', 'ERROR');
                    return;
                }

                ProjectSystem::open($projectFile, true, false, false, false);
                Ide::get()->getMainForm()->toast("Проект был успешно импортирован из архива", 3000);
                Logger::info("Finish importing project.");

                if ($afterOpen) {
                    $afterOpen();
                }
            },
            function (\Throwable $e) {
                self::close(false);
                Notifications::error("Ошибка открытия проекта", "Возможно к папке проекта нет доступа или нет места на диске");
            }
        );
    }

    /**
     * @param AbstractProjectTemplate $template
     * @param string $path
     * @param string $package
     */
    static function create(AbstractProjectTemplate $template, $path, $package = 'app')
    {
        static::clear();
        $parent = File::of($path)->getParent();

        if (!($parent = self::checkDirectory($parent))) {
            FileSystem::open('~welcome');
            return;
        }

        $path = $parent . "/" . File::of($parent)->getName();

        try {
            $project = Project::createForFile($path);
            $project->setTemplate($template);
            $project->setPackageName($package);

            LoadingManager::runMainFormStages([
                [
                    // register()/inject() поведений создаёт UI-объекты (ContextMenu и т.п.), нужен FX-поток.
                    'label' => 'Создание проекта ...',
                    'work' => function () use ($template, $project) {
                        $template->makeProject($project);
                        Ide::get()->setOpenedProject($project);
                        return $project;
                    },
                ],
                [
                    // Создание файлов и восстановление — I/O, можно в фоне.
                    'label' => 'Инициализация файлов ...',
                    'background' => true,
                    'work' => function (Project $project) {
                        $project->create();
                        $project->recover();
                        return $project;
                    },
                ],
                [
                    'label' => 'Открытие интерфейса ...',
                    'work' => function (Project $project) {
                        static::finishCreateProject($project);
                        return $project;
                    },
                ],
            ], function (\Throwable $e) {
                Logger::exception("Unable to create project", $e);
                ProjectSystem::close(false);
                Notifications::error("Ошибка создания проекта", "Возможно к папке проекта нет доступа или нет места на диске");
            });
        } catch (\Exception $e) {
            Logger::exception("Unable to create project", $e);
            ProjectSystem::close(false);
            Notifications::error("Ошибка создания проекта", "Возможно к папке проекта нет доступа или нет места на диске");
        }
    }

    /**
     * @param string $fileName
     * @param bool $showDialogAlreadyOpened
     * @param bool $showMigrationDialog
     * @param bool $showWindowKind
     * @return Project|null
     */
    static function open($fileName, $showDialogAlreadyOpened = true, $showMigrationDialog = true, $showWindowKind = false, $showPreloader = true)
    {
        Logger::info("Start opening project: $fileName");

        if (!$showPreloader) {
            return static::doOpen($fileName, $showDialogAlreadyOpened, $showMigrationDialog, $showWindowKind);
        }

        $mainForm = Ide::get()->getMainForm();

        if (!$mainForm) {
            return static::doOpen($fileName, $showDialogAlreadyOpened, $showMigrationDialog, $showWindowKind);
        }

        LoadingManager::runMainFormStages(
            static::buildOpenStages($fileName, $showDialogAlreadyOpened, $showMigrationDialog, $showWindowKind),
            [static::class, 'handleOpenFailure']
        );

        return null;
    }

    /**
     * Открытие проекта при старте IDE — прогресс на Splash, без прелоадера MainForm.
     *
     * @param string $fileName
     * @param bool $showDialogAlreadyOpened
     * @param bool $showMigrationDialog
     * @param bool $showWindowKind
     */
    static function openOnSplash($fileName, $showDialogAlreadyOpened = true, $showMigrationDialog = true, $showWindowKind = false)
    {
        Logger::info("Start opening project on splash: $fileName");

        $splash = Ide::get()->getSplash();

        if (!$splash) {
            static::open($fileName, $showDialogAlreadyOpened, $showMigrationDialog, $showWindowKind, true);

            return;
        }

        LoadingManager::runStages(
            $splash,
            static::buildOpenStages($fileName, $showDialogAlreadyOpened, $showMigrationDialog, $showWindowKind, 'Подготавливаем интерфейс проекта ...'),
            [static::class, 'handleOpenFailure']
        );
    }

    /**
     * @param string $fileName
     * @param bool $showDialogAlreadyOpened
     * @param bool $showMigrationDialog
     * @param bool $showWindowKind
     * @param string $firstLabel
     * @return array
     */
    protected static function buildOpenStages($fileName, $showDialogAlreadyOpened, $showMigrationDialog, $showWindowKind, $firstLabel = 'Открытие проекта ...')
    {
        return [
            [
                'label' => $firstLabel,
                'abortOnNull' => true,
                'work' => function () use ($fileName, $showDialogAlreadyOpened, $showMigrationDialog, $showWindowKind) {
                    return static::prepareOpen($fileName, $showDialogAlreadyOpened, $showMigrationDialog, $showWindowKind);
                },
            ],
            [
                'label' => 'Загрузка проекта ...',
                'background' => true,
                'work' => function ($prepared) {
                    /** @var Project $project */
                    $project = $prepared['project'];
                    $project->loadBackground();

                    return $project;
                },
            ],
            [
                'label' => 'Инициализация проекта ...',
                'work' => function (Project $project) {
                    $project->loadUi();

                    return $project;
                },
            ],
            [
                'label' => 'Восстановление файлов ...',
                'background' => true,
                'work' => function (Project $project) {
                    $project->recover();

                    return $project;
                },
            ],
            [
                'label' => 'Открытие интерфейса ...',
                'work' => function (Project $project) {
                    return static::finishOpen($project);
                },
            ],
        ];
    }

    /**
     * @param \Throwable $e
     */
    public static function handleOpenFailure(\Throwable $e)
    {
        if ($splash = Ide::get()->getSplash()) {
            $splash->hide();
        }

        if ($e instanceof InvalidProjectFormatException) {
            ProjectSystem::closeWithWelcome(false);
            Notifications::error('Поврежденный проект', 'Проект невозможно открыть, он поврежден или создан в новой версии IDE.');

            return;
        }

        Logger::exception("Unable to open project", $e);
        ProjectSystem::closeWithWelcome(false);
        Notifications::error("Ошибка открытия проекта", "Возможно к папке проекта нет доступа или нет места на диске");
    }

    /**
     * UI-этап открытия: проверки, диалоги, закрытие текущего проекта.
     * Тяжёлая часть (load/recover) выполняется отдельно в фоне.
     *
     * @param string $fileName
     * @param bool $showDialogAlreadyOpened
     * @param bool $showMigrationDialog
     * @param bool $showWindowKind
     * @return array|null
     * @throws InvalidProjectFormatException
     */
    public static function prepareOpen($fileName, $showDialogAlreadyOpened = true, $showMigrationDialog = true, $showWindowKind = false)
    {
        $file = File::of($fileName);
        $mainForm = Ide::get()->getMainForm();
        $project = new Project($file->getParent(), fs::nameNoExt($file));

        if ($project->isOpenedInOtherIde()) {
            if ($showDialogAlreadyOpened) {
                $msg = new MessageBoxForm('Данный проект уже открыт в другом экземпляре среды!', ['ОК, открыть другой проект']);
                $msg->showDialog();
            }

            if (!Ide::project()) {
                FileSystem::open('~welcome');
            }

            return null;
        }

        if (Ide::project() && $showWindowKind) {
            $msg = new MessageBoxForm("Проект можно открыть в отдельном окне, не закрывая текущий.\nВ каком окне открыть проект?", ['В текущем окне', 'В новом окне', 'Отмена']);
            $msg->showDialog();

            switch ($msg->getResultIndex()) {
                case 1:
                    Ide::get()->startNew(["$fileName"]);
                    // next... ->
                case 2:
                    return null;
            }
        }

        static::clear();
        static::close(false);

        $prVersion = $project->getConfig()->getIdeVersion();

        if (!Ide::get()->isSameVersionIgnorePatch($prVersion) && $project->getConfig()->getIdeVersionHash() > Ide::get()->getVersionHash()) {
            $msg = new MessageBoxForm("Проект '{$project->getName()}' создан в более новой версии DevelNext ($prVersion), вы точно хотите его открыть?", [
                'Нет', 'Да, открыть проект'
            ]);

            $msg->makeWarning();
            $msg->showWarningDialog();

            if ($msg->getResultIndex() == 0) {
                FileSystem::open('~welcome');
                return null;
            }
        }

        if ($showMigrationDialog) {
            if ($project->getConfig()->getTemplate()) {
                if ($project->getConfig()->getTemplate()->isProjectWillMigrate($project)) {
                    $msg = new MessageBoxForm("Проект '{$project->getName()}' будет сконвертирован в новый формат, который не поддерживается предыдущими версиями, продолжить?", [
                        'Открыть проект', 'Отмена'
                    ]);
                    $msg->showWarningDialog();

                    if ($msg->getResultIndex() == 1) {
                        FileSystem::open('~welcome');

                        uiLater(function () {
                            $dialog = new OpenProjectForm();
                            $dialog->showDialog();
                        });

                        return null;
                    }
                }
            }
        }

        Ide::get()->setOpenedProject($project);
        return ['project' => $project, 'mainForm' => $mainForm];
    }

    /**
     * UI-финал открытия после фоновой загрузки.
     *
     * @param Project $project
     * @return Project
     */
    public static function finishOpen(Project $project)
    {
        if (!FileSystem::getOpened()) {
            FileSystem::open($project->getMainProjectFile());
        }

        $project->open();
        Ide::get()->trigger('openProject', [$project]);

        Logger::info("Finish opening project.");

        uiLater(function () {
            $mainForm = Ide::get()->getMainForm();

            if ($mainForm) {
                LoadingManager::hide($mainForm);

                if (!$mainForm->visible && Ide::get()->getConfig()->getBoolean('app.showMainForm')) {
                    $mainForm->show();
                    $mainForm->toFront();
                }
            }
        });

        return $project;
    }

    /**
     * @param string $fileName
     * @param bool $showDialogAlreadyOpened
     * @param bool $showMigrationDialog
     * @param bool $showWindowKind
     * @return Project|null
     */
    static function doOpen($fileName, $showDialogAlreadyOpened = true, $showMigrationDialog = true, $showWindowKind = false)
    {
        try {
            $prepared = static::prepareOpen($fileName, $showDialogAlreadyOpened, $showMigrationDialog, $showWindowKind);

            if (!$prepared) {
                return null;
            }

            /** @var Project $project */
            $project = $prepared['project'];
            $project->load();
            $project->recover();

            return static::finishOpen($project);
        } catch (IOException $e) {
            ProjectSystem::close(false);
            Logger::exception("Unable to open project", $e);
            Notifications::error("Ошибка открытия проекта", "Возможно к папке проекта нет доступа или нет места на диске");
        } catch (InvalidProjectFormatException $e) {
            ProjectSystem::closeWithWelcome(false);
            Notifications::error('Поврежденный проект', 'Проект "' . fs::nameNoExt($fileName) . '" невозможно открыть, он поврежден или создан в новой версии DevelNext.');
        }

        return null;
    }

    /**
     * UI-финал создания проекта (после фоновой инициализации файлов).
     *
     * @param Project $project
     */
    public static function finishCreateProject(Project $project)
    {
        $package = $project->getPackageName();
        $mainFormFile = $project->getFile("src/{$package}/forms/MainForm.php");

        $project->open();
        Ide::get()->trigger('openProject', [$project]);

        $editor = FileSystem::fetchEditor($mainFormFile, false);

        if ($editor instanceof \ide\editors\FormEditor) {
            $editor->getConfig()->set('title', 'MainForm');
            $editor->addModule('MainModule');
            $editor->saveConfig();
        }

        static::save();

        uiLater(function () {
            $mainForm = Ide::get()->getMainForm();

            if ($mainForm) {
                LoadingManager::hide($mainForm);
            }
        });
    }

    /**
     * ...
     */
    static function saveOnlyRequired()
    {
        if ($editor = FileSystem::getSelectedEditor()) {
            $editor->save();
        }
    }

    /**
     * @throws \Exception
     */
    static function save()
    {
        $project = Ide::get()->getOpenedProject();

        if (!$project) {
            throw new \Exception("Project is not opened");
        }

        $project->save();
    }

    static function closeWithWelcome($save = true)
    {
        self::close($save);
        FileSystem::open('~welcome');
    }

    /**
     * Закрывает проект с открытми файлами проекта.
     * @param bool $saveAll
     */
    static function close($saveAll = true)
    {
        $project = Ide::get()->getOpenedProject();

        ProjectSystem::saveOnlyRequired();

        if ($project) {
            Ide::get()->trigger('closeProject', [$project]);
        }

        if ($project) {
            $project->close(true);
        }

        foreach (FileSystem::getOpened() as $hash => $info) {
            //if ($project && $project->isContainsFile($info['file'])) {
            FileSystem::close($info['file'], $saveAll);
            //}
        }

        FileSystem::closeAllTabs();

        Cache::clear();

        static::clear();


        Ide::get()->setOpenedProject(null);

        if ($project) {
            Ide::get()->trigger('afterCloseProject', [$project]);

            $project->free();
        }

        FileSystem::clearCache();
        System::gc();
    }
}