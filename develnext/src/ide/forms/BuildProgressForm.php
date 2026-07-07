<?php
namespace ide\forms;

use ide\commands\BuildProjectCommand;
use ide\forms\mixins\SavableFormMixin;
use ide\Ide;
use ide\Logger;
use ide\project\ProjectConsoleOutput;
use ide\systems\FileSystem;
use ide\ui\FxeLogConsole;
use php\gui\UXTextArea;
use php\gui\event\UXEvent;
use php\gui\event\UXMouseEvent;
use php\gui\event\UXWindowEvent;
use php\gui\framework\AbstractForm;
use php\gui\layout\UXHBox;
use php\gui\layout\UXVBox;
use php\gui\paint\UXColor;
use php\gui\text\UXFont;
use php\gui\UXApplication;
use php\gui\UXButton;
use php\gui\UXCheckbox;
use php\gui\UXClipboard;
use php\gui\UXDialog;
use php\gui\UXImageView;
use php\gui\UXLabel;
use php\gui\UXListView;
use php\io\IOException;
use php\io\Stream;
use php\lang\Process;
use php\lang\System;
use php\lang\Thread;
use php\lang\ThreadPool;
use php\lib\char;
use php\lib\fs;
use php\lib\str;
use php\util\Regex;
use php\util\Scanner;
use php\util\SharedQueue;

/**
 * @property UXImageView $icon
 * @property UXListView $consoleList
 * @property UXCheckbox $closeAfterDoneCheckbox
 * @property UXButton $closeButton
 * @property UXTextArea $consoleArea
 * @property UXHBox $bottomPane
 * @property UXLabel $message
 *
 * Class BuildProgressForm
 * @package ide\forms
 */
class BuildProgressForm extends AbstractIdeForm implements ProjectConsoleOutput
{
    use SavableFormMixin;

    /**
     * @var Process
     */
    protected $process;

    /**
     * @var bool
     */
    protected $processDone = false;

    /**
     * @var bool
     */
    protected $streamHasError = false;

    /** @var callable */
    protected $onExitProcess;

    /** @var callable */
    protected $stopProcedure;

    /** @var SharedQueue */
    protected $tasks;

    /** @var FxeLogConsole */
    protected $logConsole;

    /** @var callable */
    protected $onRestart;

    /** @var string */
    protected $logsDirectory;

    /** @var bool */
    protected $embeddedMode = false;

    /** @var UXButton|null */
    protected $embedCloseButton;

    /** @var UXCheckbox|null */
    protected $embedCloseAfterDoneCheckbox;

    /** @var UXTextArea|null */
    protected $embedConsoleArea;

    public function setEmbeddedMode($embedded = true)
    {
        $this->embeddedMode = (bool) $embedded;
    }

    protected function init()
    {
        $this->icon->image = ico('wait32')->image;

        $this->logConsole = new FxeLogConsole();

        if ($this->consoleArea) {
            $this->consoleArea->visible = false;
            $this->consoleArea->managed = false;
        }

        $this->embedCloseButton = $this->closeButton;
        $this->embedCloseAfterDoneCheckbox = $this->closeAfterDoneCheckbox;
        $this->embedConsoleArea = null;

        $this->message->on('click', function () {
            $text = $this->message->text;

            $patterns = [
                "Uncaught\\ ([a-z\\_0-9]+)\\: (.+)\\ in\\ (.+)\\ on\\ line\\ ([0-9]+)\\,\\ position\\ ([0-9]+)",
                "'(.+)'\\ with\\ message\\ '(.+?)'\\ in\\ (.+\\.php)\\ on\\ line\\ ([0-9]+)\\,\\ position\\ ([0-9]+)"
            ];

            foreach ($patterns as $pattern) {
                $regex = new Regex($pattern, 'i', $text);
                $one = $regex->one();

                if ($one) {
                    list(, $type, $message, $file, $line, $position) = $one;

                    if (str::startsWith($file, 'res://')) {
                        $file = Ide::project()->getSrcFile(str::sub($file, 6));
                    }

                    $editor = FileSystem::open($file);

                    if ($editor) {
                        $editor->sendMessage(['error' => ['line' => $line, 'position' => $position, 'message' => $message]]);
                    }

                    return;
                }
            }
        });
    }

    /** @var string */
    protected $consoleLogBuffer = '';

    protected function exportConsoleText()
    {
        if ($this->logConsole) {
            return $this->logConsole->getEditor()->getValue();
        }

        return $this->consoleLogBuffer;
    }

    protected function fxeLog($message, $color = '#94a3b8')
    {
        $this->addConsoleLine($message, $color);
    }

    public function beginFxeRun($projectName, $projectDir = null)
    {
        $this->logsDirectory = $projectDir ? "$projectDir/.fxe/logs" : null;
        $this->consoleLogBuffer = '';

        if ($this->logConsole) {
            $this->logConsole->clear();
        }

        $this->fxeLog('Подготовка запуска проекта...', '#3b82f6');
        $this->fxeLog("Запуск проекта: $projectName", '#3b82f6');
        $this->fxeLog('Проверка Java runtime...', '#8b5cf6');
        $this->fxeLog('Java: ' . System::getProperty('java.version'), '#22c55e');
        $this->fxeLog('Загрузка JPHP runtime...', '#06b6d4');

        if ($this->workTitle) {
            $this->workTitle->text = 'Терминал';
        }
    }

    public function setOnRestart(callable $callback)
    {
        $this->onRestart = $callback;
    }

    public function onFxeCompileDone($hintCommand, $rootDir)
    {
        $this->fxeLog('Компиляция завершена', '#22c55e');
        $this->fxeLog('> ' . $hintCommand, '#a855f7');
        $this->fxeLog('Корень: ' . $rootDir, '#64748b');
    }

    public function logRunStart()
    {
        $this->fxeLog('Runtime загружен', '#06b6d4');
        $this->fxeLog('Запуск MainForm.php', '#a855f7');
    }

    public function prepareEmbed()
    {
        $this->embeddedMode = true;

        if (!$this->logConsole) {
            $this->logConsole = new FxeLogConsole();
        }

        $this->embedCloseButton = $this->embedCloseButton ?: $this->closeButton;
        $this->embedCloseAfterDoneCheckbox = $this->embedCloseAfterDoneCheckbox ?: $this->closeAfterDoneCheckbox;
        $this->embedConsoleArea = null;

        if ($this->header) {
            $this->header->visible = false;
            $this->header->managed = false;
        }

        if ($this->progress) {
            $this->progress->visible = false;
            $this->progress->managed = false;
        }

        if ($this->consoleArea) {
            $this->consoleArea->visible = false;
            $this->consoleArea->managed = false;
        }

        if ($this->closeButton) {
            $this->closeButton->visible = false;
            $this->closeButton->managed = false;
        }

        if ($this->message) {
            $this->message->visible = false;
            $this->message->managed = false;
        }

        if ($this->content) {
            $this->content->classes->add('fxe-console-embed');
            $this->content->padding = [0, 0, 0, 0];
            $this->content->spacing = 0;
            $this->content->minHeight = 160;
            $this->content->prefHeight = 240;
            $this->content->alignment = 'TOP_LEFT';
            $this->content->fillWidth = true;
            UXVBox::setVgrow($this->content, 'ALWAYS');

            $this->content->children->clear();

            $logUi = $this->logConsole->getUi();
            UXVBox::setVgrow($logUi, 'ALWAYS');
            $this->content->children->add($logUi);

            if ($this->bottomPane) {
                $this->bottomPane->classes->add('fxe-console-run-footer');
                $this->bottomPane->alignment = 'CENTER_LEFT';
                $this->bottomPane->minHeight = 32;
                $this->bottomPane->prefHeight = 32;
                $this->bottomPane->maxHeight = 32;
                $this->bottomPane->padding = [4, 8, 8, 8];
                $this->bottomPane->spacing = 8;
                UXVBox::setVgrow($this->bottomPane, 'NEVER');
                $this->content->children->add($this->bottomPane);
            }
        }

        $checkbox = $this->embedCloseAfterDoneCheckbox ?: $this->closeAfterDoneCheckbox;
        if ($checkbox) {
            $checkbox->selected = (bool) Ide::get()->getUserConfigValue('builder.closeAfterDone', false);
        }

        $this->wireEmbeddedHandlers();
    }

    public function wireEmbeddedHandlers()
    {
        if ($this->embedCloseButton) {
            $this->embedCloseButton->on('action', function () {
                $main = Ide::get()->getMainForm();

                if ($main) {
                    $main->hideBottom();
                }
            });
        }

        $checkbox = $this->embedCloseAfterDoneCheckbox ?: $this->closeAfterDoneCheckbox;
        if ($checkbox) {
            $checkbox->on('action', function () use ($checkbox) {
                Ide::get()->setUserConfigValue('builder.closeAfterDone', $checkbox->selected);
            });
        }
    }

    /**
     * Инициализация без show/hide формы — контент уже в нижней консоли IDE.
     */
    public function openForEmbed()
    {
        $checkbox = $this->embedCloseAfterDoneCheckbox ?: $this->closeAfterDoneCheckbox;

        if ($checkbox) {
            $checkbox->selected = (bool) Ide::get()->getUserConfigValue('builder.closeAfterDone', false);
        }

        if ($this->progress) {
            $this->progress->progress = -1;
        }
    }

    /**
     * @return \php\gui\UXNode|null
     */
    public function getEmbedContent()
    {
        return $this->content;
    }

    public function reduceHeader()
    {
        if (!$this->content) {
            return;
        }

        $this->content->padding = 5;
        $this->content->paddingBottom = 0;
        $this->content->spacing = 0;

        if ($this->header) {
            $this->header->spacing = 5;
        }

        if ($this->workTitle) {
            $this->workTitle->font = $this->workTitle->font->withSize(12)->withBold();
        }

        if ($this->workDescription) {
            $this->workDescription->free();
        }

        if ($this->icon) {
            $this->icon->preserveRatio = true;
            $this->icon->size = [16, 16];
        }

        if ($this->header) {
            $this->header->free();
        }
    }

    public function reduceFooter()
    {
        if ($this->embeddedMode) {
            if ($this->closeButton) {
                $this->closeButton->visible = false;
                $this->closeButton->managed = false;
            }

            if ($this->bottomPane) {
                $this->bottomPane->padding = [6, 0, 0, 0];
                $this->bottomPane->spacing = 8;
                $this->bottomPane->minHeight = 28;
                $this->bottomPane->prefHeight = 28;
                $this->bottomPane->maxHeight = 28;
            }

            return;
        }

        $closeBtn = $this->embedCloseButton ?: $this->closeButton;

        if ($this->bottomPane && $closeBtn) {
            $this->bottomPane->height = $closeBtn->height = 25;
            $closeBtn->padding = [0, 8];
            $this->bottomPane->padding = 0;
            $this->bottomPane->spacing = 10;
            $this->bottomPane->paddingLeft = 0;
        }
    }

    public function removeHeader()
    {
        $this->header->free();
    }

    public function removeProgressbar()
    {
        $this->progress->free();
    }

    /**
     * @param array $tasksOrProcesses
     */
    public function watchProcesses(array $tasksOrProcesses)
    {
        $tasks = new SharedQueue($tasksOrProcesses);

        $process = $tasks->poll();

        if ($process instanceof Process) {
            // nop
        } else if (is_callable($process)) {
            $process = $process();
        }

        $func = function ($exitCode) use ($tasks, &$func) {
            if ($exitCode == 0) {
                $process = $tasks->poll();

                if ($process instanceof Process) {
                    // nop.
                } else if (is_callable($process)) {
                    $process = $process();
                }

                if ($process) {
                    $this->watchProcess($process, $func);

                    return true;
                }
            }
        };

        $this->watchProcess($process, $func);
    }

    public function show(Process $process = null)
    {
        if ($process) {
            $this->watchProcess($process);
        }

        parent::show();
    }

    public function hide()
    {
        $checkbox = $this->embedCloseAfterDoneCheckbox ?: $this->closeAfterDoneCheckbox;

        if ($this->embeddedMode) {
            if ($checkbox) {
                Ide::get()->setUserConfigValue('builder.closeAfterDone', $checkbox->selected);
            }

            $main = Ide::get()->getMainForm();

            if ($main) {
                $main->hideBottom();
            }

            return;
        }

        parent::hide();

        if ($checkbox) {
            Ide::get()->setUserConfigValue('builder.closeAfterDone', $checkbox->selected);
        }
    }

    /**
     * @event closeAfterDoneCheckbox.click
     */
    public function doCloseAfterDoneCheckboxMouseDown()
    {
        uiLater(function () {
            if ($this->closeAfterDoneCheckbox) {
                Ide::get()->setUserConfigValue('builder.closeAfterDone', $this->closeAfterDoneCheckbox->selected);
            }
        });
    }

    public function watchProcess(Process $process, callable $onExit = null)
    {
        $thread = new Thread(function () use ($process, $onExit) {
            $this->doProgress($process, $onExit);
        });
        $thread->setName('thread-build-process-' . str::random());
        $thread->start();
    }

    /**
     * @param callable $onExitProcess
     */
    public function setOnExitProcess($onExitProcess)
    {
        $this->onExitProcess = $onExitProcess;
    }

    /**
     * @param callable $stopProcedure
     */
    public function setStopProcedure($stopProcedure)
    {
        $this->stopProcedure = $stopProcedure;
    }

    /**
     * @event show
     */
    public function doOpen()
    {
        if ($this->progress) {
            $this->progress->progress = -1;
        }

        if ($this->closeAfterDoneCheckbox) {
            $this->closeAfterDoneCheckbox->selected = Ide::get()->getUserConfigValue('builder.closeAfterDone', false);
        }
    }

    /**
     * @event close
     * @event closeButton.action
     *
     * @param UXEvent $e
     */
    public function doClose(UXEvent $e)
    {
        if (!$this->processDone) {
            if ($this->stopProcedure) {
                $stopProcedure = $this->stopProcedure;

                if (!$stopProcedure()) {
                    $e->consume();
                    return;
                }
            } else {
                UXDialog::show('Дождитесь сборки для закрытия прогресса.');
                $e->consume();

                return;
            }
        }

        $this->hide();
    }

    /**
     * @param $line
     * @param string $color
     */
    public function addConsoleLine($line, $color = '#333333')
    {
        $this->addConsoleText("$line\n", $color);
    }

    /**
     * @return UXTextArea|null
     */
    protected function getConsoleArea()
    {
        return null;
    }

    public function addConsoleText($text, $color = null)
    {
        $this->consoleLogBuffer .= $text;

        if (!$color) {
            $color = '#d4d4d4';
        }

        if (str::startsWith($text, "[ERROR] ") || str::startsWith($text, "Fatal error: ")) {
            $color = '#D8000C';
        }

        if (str::startsWith($text, "[WARN] ") || str::startsWith($text, "[WARNING] ")) {
            $color = '#9F6000';
        }

        if (str::startsWith($text, "[INFO] ")) {
            $color = '#00529B';
        }

        if (str::startsWith($text, "[DEBUG] ") || ($text && $text[0] == ':')) {
            $color = '#5c5c5c';
        }

        if ($this->logConsole) {
            $this->logConsole->addConsoleText($text, $color);

            return;
        }

        if ($this->consoleList) {
            $this->consoleList->items->add([$text, $color]);

            $index = $this->consoleList->items->count() - 1;

            $this->consoleList->selectedIndexes = [$index];
            $this->consoleList->focusedIndex = $index;
            $this->consoleList->scrollTo($index);
        }
    }

    /**
     * @param \Exception $e
     */
    public function stopWithException(\Exception $e)
    {
        $this->processDone = true;
        $this->addConsoleLine($e->getMessage(), '#ef4444');

        if (BuildProjectCommand::isBuilding()) {
            BuildProjectCommand::notifyBuildFinished();
        }

        if ($this->progress) {
            $this->progress->progress = 100;
        }
    }

    public function stopWithError()
    {
        $this->processDone = true;
        $this->addConsoleLine('');
        $this->addConsoleLine('Сборка проекта не удалась', '#ef4444');
        $this->addConsoleLine('Исправьте ошибки компиляции и запустите снова', '#f59e0b');

        if (BuildProjectCommand::isBuilding()) {
            BuildProjectCommand::notifyBuildFinished();
        }

        if ($this->progress) {
            $this->progress->progress = 100;
        }
    }

    /**
     * @param Process $process
     * @param callable $onExit
     *
     */
    public function doProgress(Process $process, callable $onExit = null)
    {
        $self = $this;
        $this->process = $process;
        $this->processDone = false;
        $this->streamHasError = false;

        (new Thread(function () use ($process, $self) {
            $self->pumpProcessStream($process->getError(), true);
        }))->start();

        (new Thread(function () use ($process, $self) {
            $self->pumpProcessStream($process->getInput(), false);
        }))->start();

        while ($process->getExitValue() === null) {
            Thread::sleep(50);
        }

        Thread::sleep(250);
        $this->processDone = true;
        Thread::sleep(150);

        $exitValue = $process->getExitValue();
        $hasError = $this->streamHasError;
        $this->processDone = true;

        uiLater(function () use ($exitValue, $hasError) {
            if ($exitValue == 0 && !$hasError) {
                $this->fxeLog('Проект успешно запущен', '#22c55e');

                if ($this->embeddedMode) {
                    $main = Ide::get()->getMainForm();

                    if ($main) {
                        $main->showBottom($this->getEmbedContent());
                    }
                }

                if ($this->logConsole) {
                    $area = $this->logConsole->getEditor()->getTextArea();

                    if ($area) {
                        $area->end();
                    }
                }
            } else if ($exitValue != 0) {
                $this->addConsoleLine('');
                $this->addConsoleLine("Завершено с кодом: $exitValue", '#ef4444');
            }
        });

        UXApplication::runLater(function() {
            if ($this->progress) {
                $this->progress->progress = 1;
            }
        });

        $func = function() use ($self, $exitValue, $onExit, $hasError) {
            if (BuildProjectCommand::isBuilding()) {
                BuildProjectCommand::notifyBuildFinished();
            }

            if ($exitValue) {
                $self->addConsoleLine('');
                $self->addConsoleLine('(!) Ошибка запуска, что-то пошло не так', 'red');
                $self->addConsoleLine('   --> возможно ошибка в вашей программе или ошибка IDE...', 'gray');
                $self->addConsoleLine('');
            }

            if ($onExit) {
                $nextProcess = $onExit($exitValue, $hasError);

                if ($nextProcess) {
                    return;
                }
            }

            if (!$exitValue && !$hasError) {
                $checkbox = $self->embedCloseAfterDoneCheckbox ?: $self->closeAfterDoneCheckbox;

                if ($checkbox && $checkbox->selected) {
                    $self->hide();
                }
            }

            $onExitProcess = $this->onExitProcess;

            if ($onExitProcess) {
                $onExitProcess($exitValue, $hasError);

                $checkbox = $self->embedCloseAfterDoneCheckbox ?: $self->closeAfterDoneCheckbox;

                if ($checkbox) {
                    Ide::get()->setUserConfigValue('builder.closeAfterDone', $checkbox->selected);
                }
            }
        };

        UXApplication::runLater($func);
    }

    /**
     * @param \php\io\Stream $stream
     * @param bool $isError
     */
    public function pumpProcessStream($stream, $isError = false)
    {
        $buffer = '';

        while (true) {
            if ($this->processDone && $stream->eof()) {
                break;
            }

            if ($stream->eof()) {
                if ($this->process && $this->process->getExitValue() !== null) {
                    Thread::sleep(80);

                    if ($stream->eof()) {
                        break;
                    }
                }

                Thread::sleep(30);
                continue;
            }

            try {
                $chunk = $stream->read(512);
            } catch (\Exception $e) {
                break;
            }

            if ($chunk === null || $chunk === '') {
                Thread::sleep(30);
                continue;
            }

            $buffer .= $chunk;

            while (($nl = str::pos($buffer, "\n")) !== false) {
                $line = str::sub($buffer, 0, $nl);
                $buffer = str::sub($buffer, $nl + 1);
                $this->emitProcessLine($line, $isError);
            }

            if ($buffer !== '') {
                $partial = $buffer;
                $buffer = '';
                $this->emitProcessText($partial, $isError);
            }
        }

        if (str::length($buffer) > 0) {
            $this->emitProcessLine($buffer, $isError);
        }
    }

    /**
     * @param string $line
     * @param bool $isError
     */
    public function emitProcessLine($line, $isError = false)
    {
        if ($isError) {
            $this->streamHasError = true;
        }

        $color = $isError ? '#ef4444' : '#d4d4d4';

        uiLater(function () use ($line, $color) {
            $this->addConsoleLine($line, $color);
        });
    }

    /**
     * @param string $text
     * @param bool $isError
     */
    public function emitProcessText($text, $isError = false)
    {
        if ($isError) {
            $this->streamHasError = true;
        }

        $color = $isError ? '#ef4444' : '#d4d4d4';

        uiLater(function () use ($text, $color) {
            $this->addConsoleText($text, $color);
        });
    }
}