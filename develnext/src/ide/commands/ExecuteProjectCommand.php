<?php
namespace ide\commands;

use facade\Async;
use framework\core\EventSignal;
use ide\commands\BuildProjectCommand;
use ide\editors\AbstractEditor;
use ide\forms\BuildProgressForm;
use ide\Ide;
use ide\Logger;
use ide\misc\AbstractCommand;
use ide\misc\EventHandlerBehaviour;
use ide\project\behaviours\RunBuildProjectBehaviour;
use ide\project\Project;
use ide\project\ProjectConsoleOutput;
use ide\systems\FileSystem;
use ide\systems\FxeTitlebarRunBuildSystem;
use ide\systems\LivePropertySync;
use ide\systems\ProjectSystem;
use ide\ui\FxeTitlebarIcons;
use ide\ui\Notifications;
use ide\utils\FileUtils;
use php\gui\event\UXEvent;
use php\gui\framework\ScriptEvent;
use php\gui\UXButton;
use php\gui\UXDialog;
use php\gui\UXRichTextArea;
use php\io\File;
use php\io\IOException;
use php\io\Stream;
use php\lang\IllegalStateException;
use php\lang\Process;
use php\lang\Thread;
use php\lang\ThreadPool;
use php\lib\arr;
use php\lib\fs;
use php\lib\number;
use php\lib\Str;
use php\time\Time;
use script\TimerScript;
use timer\AccurateTimer;

class ExecuteProjectCommand extends AbstractCommand
{
    /** @var BuildProgressForm */
    protected $processDialog;
    /** @var UXButton */
    protected $runToggleButton;

    /** @var bool */
    protected $running = false;

    /** @var Process */
    protected $process;

    /**
     * @var RunBuildProjectBehaviour
     */
    protected $behaviour;

    /**
     * @var EventSignal
     */
    public $onRun;

    /**
     * @var EventSignal
     */
    public $onStop;

    /**
     * @param RunBuildProjectBehaviour $behaviour
     */
    function __construct(RunBuildProjectBehaviour $behaviour)
    {
        parent::__construct();

        Ide::get()->on('closeProject', function () {
            if ($this->isRunning()) {
                $this->onStopExecute();
            }
        }, __CLASS__);

        Ide::get()->on('shutdown', function () {
            if ($this->isRunning()) {
                $this->forceStopExecute();
            }
        }, __CLASS__);

        $this->behaviour = $behaviour;
    }

    public function getName()
    {
        return 'Запустить проект';
    }

    public function getIcon()
    {
        return FxeTitlebarIcons::RUN;
    }

    public function getAccelerator()
    {
        return 'F9';
    }

    public function getCategory()
    {
        return 'run';
    }

    public function isTitleBarVisible()
    {
        return true;
    }

    public function makeUiForHead()
    {
        $this->runToggleButton = FxeTitlebarIcons::makeButton('Запустить (F9)', [$this, 'onToggleRun']);
        $this->runToggleButton->id = 'fxeTitlebarRunToggle';
        $this->runToggleButton->graphic = FxeTitlebarIcons::graphic(FxeTitlebarIcons::RUN);
        FxeTitlebarRunBuildSystem::bindRunButton($this->runToggleButton);
        $this->setRunningState(false);

        return [$this->runToggleButton];
    }

    public function onToggleRun(UXEvent $e = null, AbstractEditor $editor = null)
    {
        if ($this->isRunning()) {
            $this->onStopExecute($e);
        } else {
            $this->onExecute($e, $editor);
        }
    }

    /**
     * @param bool $running
     */
    public function setRunningState($running)
    {
        $this->running = (bool) $running;

        if (!$this->runToggleButton) {
            return;
        }

        if ($this->running) {
            $this->runToggleButton->graphic = FxeTitlebarIcons::graphic(FxeTitlebarIcons::STOP);
            $this->runToggleButton->tooltipText = 'Остановить (F9)';
        } else {
            $this->runToggleButton->graphic = FxeTitlebarIcons::graphic(FxeTitlebarIcons::RUN);
            $this->runToggleButton->tooltipText = 'Запустить (F9)';
        }

        FxeTitlebarRunBuildSystem::setRunActive($this->running);
    }

    public function isRunning()
    {
        return $this->running;
    }

    /**
     * Синхронная остановка при закрытии IDE (без waitAsync / прелоадера).
     */
    public function forceStopExecute()
    {
        $ide = Ide::get();
        $project = $ide->getOpenedProject();

        $this->setRunningState(false);
        $this->onStop->trigger();

        if (!$project) {
            $this->destroyProcessHandle();
            return;
        }

        $appPidFile = $project->getFile('application.pid');

        try {
            if ($appPidFile->exists()) {
                $pid = trim((string) fs::get($appPidFile));
                if ($pid !== '') {
                    if ($ide->isWindows()) {
                        `taskkill /PID $pid /f /T`;
                    } else {
                        `kill -9 $pid`;
                    }
                }
                $appPidFile->delete();
            }
        } catch (IOException $e) {
            Logger::exception('Cannot force stop process', $e);
        }

        $this->destroyProcessHandle();

        if ($this->processDialog) {
            $this->processDialog->hide();
        }
    }

    protected function destroyProcessHandle()
    {
        if ($this->process instanceof Process) {
            try {
                $this->process->destroy(true);
            } catch (\Exception $e) {
                Logger::warn('Cannot destroy run process: ' . $e->getMessage());
            }
        }

        $this->process = null;
    }

    public function onStopExecute(UXEvent $e = null, callable $callback = null)
    {
        $this->onStop->trigger();

        $ide = Ide::get();
        $project = $ide->getOpenedProject();

        $this->setRunningState(false);

        $appPidFile = $project->getFile("application.pid");

        $mainForm = Ide::get()->getMainForm();
        $mainForm->showPreloader('Подождите, останавливаем программу ...');

        $proc = function () use ($appPidFile, $ide, $mainForm, $callback) {
            try {
                $pid = fs::get($appPidFile);

                if ($pid) {
                    if ($ide->isWindows()) {
                        $result = `taskkill /PID $pid /f`;
                    } else {
                        $result = `kill -9 $pid`;
                    }

                    if (!$result) {
                        Notifications::showExecuteUnableStop();
                    }
                } else {
                    $this->destroyProcessHandle();
                    Notifications::showExecuteUnableStop();
                }
            } catch (IOException $e) {
                Logger::exception('Cannot stop process', $e);
                Notifications::showExecuteUnableStop();
            } finally {
                $this->setRunningState(false);

                if ($this->processDialog) {
                    $this->processDialog->hide();
                }
            }

            $appPidFile->delete();

            $this->destroyProcessHandle();

            $mainForm->hidePreloader();

            if ($callback) {
                $callback();
            }
        };

        if ($appPidFile->exists()) {
            waitAsync(48, $proc);
        } else {
            $time = 0;

            $timer = new AccurateTimer(100, function () use ($appPidFile, $proc, &$time) {
                $time += 100;

                if ($appPidFile->exists() || $time > 1000 * 25) {
                    waitAsync(48, $proc);
                    return true;
                }

                return false;
            });
            $timer->start();
        }
    }

    protected function createExecuteProcess(Project $project): Process
    {
        $classPaths = flow($this->behaviour->getSourceDirectories(), $this->behaviour->getProfileModules(['jar']))
            ->toArray();

        $args = [
            'java',
            '-cp',
            str::join($classPaths, File::PATH_SEPARATOR),
            '-XX:+UseG1GC', '-Xms128M', '-Xmx512m', '-Dfile.encoding=UTF-8', '-Djphp.trace=true',
            'org.develnext.jphp.ext.javafx.FXLauncher'
        ];

        Logger::debug("Run -> " . str::join($args, ' '));

        return new Process(
            $args,
            $project->getRootDir(),
            Ide::get()->makeEnvironment()
        );
    }

    public function onExecute($e = null, AbstractEditor $editor = null)
    {
        if (BuildProjectCommand::isBuilding()) {
            return;
        }

        if ($this->isRunning()) {
            $this->onStopExecute(null);
            return;
        }

        $this->onRun->trigger();

        $ide = Ide::get();
        $project = $ide->getOpenedProject();

        $appPidFile = $project->getFile("application.pid");
        $appPidFile->delete();

        $project->trigger('execute');

        if ($project) {
            FileSystem::saveAll();

            $this->processDialog = $dialog = new BuildProgressForm();
            //$dialog->removeHeader();
            $dialog->reduceHeader();
            $dialog->reduceFooter();
            $dialog->removeProgressbar();
            $dialog->prepareEmbed();
            $dialog->openForEmbed();

            Ide::get()->getMainForm()->showBottom($dialog->getEmbedContent());

            $this->setRunningState(true);

            $dialog->beginFxeRun($project->getName(), $project->getRootDir());
            $dialog->setOnRestart(function () use ($project, $ide) {
                $this->onStopExecute(null, function () use ($project, $ide) {
                    $this->onExecute();
                });
            });

            LivePropertySync::reset($project);
            ProjectSystem::compileAll(Project::ENV_DEV, $dialog, 'Run project ...', function ($success) use ($dialog, $project, $ide) {
                if (!$success) {
                    $dialog->stopWithError();
                    $this->setRunningState(false);

                    return;
                }

                try {
                    $this->process = $this->createExecuteProcess($project);

                    $dialog->logRunStart();

                    $this->process = $this->process->start();
                    $dialog->watchProcess($this->process);

                    $dialog->setStopProcedure(function () use ($dialog) {
                        $this->onStopExecute();
                    });

                    $dialog->setOnExitProcess(function ($exitValue, $hasError) use ($dialog) {
                        $this->setRunningState(false);
                    });
                } catch (IOException $e) {
                    $this->setRunningState(false);

                    if (!$dialog->visible) {
                        $dialog->openForEmbed();
                    }

                    $dialog->stopWithException($e);
                }
            });
        } else {
            $this->process = null;
            UXDialog::show('Ошибка запуска', 'ERROR');
        }
    }
}