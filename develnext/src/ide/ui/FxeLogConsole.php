<?php
namespace ide\ui;

use ide\editors\CodeEditor;
use ide\project\ProjectConsoleOutput;
use php\gui\layout\UXVBox;
use php\lib\str;

/**
 * Вывод лога в нижней консоли IDE на базе CodeEditor (read-only).
 */
class FxeLogConsole implements ProjectConsoleOutput
{
    /** @var CodeEditor */
    protected $editor;

    /** @var UXVBox */
    protected $ui;

    /** @var int */
    protected $maxLines = 8000;

    /** @var int */
    protected $lineCount = 0;

    /** @var array[] */
    protected $pending = [];

    /** @var bool */
    protected $flushScheduled = false;

    /** @var int */
    protected $generation = 0;

    public function __construct()
    {
        $this->editor = new CodeEditor(null, 'log', []);
        $this->editor->setEmbedded(true);
        $this->editor->setReadOnly(true);
        $this->editor->setHideReadOnlyBar(true);
        $this->ui = $this->editor->makeUi();
        $this->ui->classes->add('fxe-log-console');
        $this->ui->fillWidth = true;

        $area = $this->editor->getTextArea();
        if ($area && property_exists($area, 'showGutter')) {
            $area->showGutter = false;
        }
    }

    /**
     * @return UXVBox
     */
    public function getUi()
    {
        return $this->ui;
    }

    /**
     * @return CodeEditor
     */
    public function getEditor()
    {
        return $this->editor;
    }

    public function addConsoleLine($line, $color = '#333333')
    {
        $line = str::replace($line, "\r\n", "\n");
        $line = str::replace($line, "\r", "\n");

        $this->addConsoleText($line . "\n", $color);
    }

    public function addConsoleText($text, $color = null)
    {
        if (!$text) {
            return;
        }

        $this->pending[] = [
            'text' => $text,
            'style' => $this->resolveLogStyle($text, $color),
        ];

        if (!$this->flushScheduled) {
            $this->flushScheduled = true;

            uiLater(function () {
                $this->flushPending();
            });
        }
    }

    public function clear()
    {
        $this->generation++;
        $this->pending = [];
        $this->flushScheduled = false;

        uiLater(function () {
            $this->editor->setValue('');
            $this->lineCount = 0;
        });
    }

    protected function flushPending()
    {
        $this->flushScheduled = false;

        if (!$this->pending) {
            return;
        }

        $gen = $this->generation;
        $batch = $this->pending;
        $this->pending = [];

        $area = $this->editor->getTextArea();

        if (!$area) {
            return;
        }

        foreach ($batch as $item) {
            if ($gen !== $this->generation) {
                return;
            }

            try {
                $area->appendText($item['text'], $item['style']);
                $this->lineCount += str::length($item['text']) - str::length(str::replace($item['text'], "\n", ''));
            } catch (\Throwable $e) {
                // пропускаем битый чанк, не роняем IDE
            }
        }

        if ($gen !== $this->generation) {
            return;
        }

        $this->trimIfNeeded($area);

        try {
            $area->end();
        } catch (\Throwable $e) {
        }

        if ($this->pending) {
            $this->flushScheduled = true;

            uiLater(function () {
                $this->flushPending();
            });
        }
    }

    /**
     * @param string $text
     * @param string|null $color
     * @return string
     */
    protected function resolveLogStyle($text, $color = null)
    {
        static $colorMap = [
            '#3b82f6' => 'log-c-blue',
            '#8b5cf6' => 'log-c-violet',
            '#22c55e' => 'log-c-green',
            '#06b6d4' => 'log-c-cyan',
            '#a855f7' => 'log-c-purple',
            '#64748b' => 'log-c-muted',
            '#ef4444' => 'log-c-red',
            '#f59e0b' => 'log-c-orange',
            '#94a3b8' => 'log-c-muted',
            '#D8000C' => 'log-c-red',
            '#9F6000' => 'log-c-warn',
            '#00529B' => 'log-c-info',
            '#5c5c5c' => 'log-c-debug',
            'red' => 'log-c-red',
            'gray' => 'log-c-muted',
        ];

        if ($color && isset($colorMap[$color]) && $colorMap[$color]) {
            return $colorMap[$color];
        }

        return $this->resolveLogStyleFromLine($text);
    }

    /**
     * @param string $text
     * @return string
     */
    protected function resolveLogStyleFromLine($text)
    {
        $line = str::trim(str::replace($text, "\n", ''));

        if (str::startsWith($line, '[ERROR]') || str::startsWith($line, 'ERROR [') || str::startsWith($line, 'Fatal error')) {
            return 'log-c-red';
        }

        if (str::startsWith($line, '[WARN]') || str::startsWith($line, '[WARNING]') || str::startsWith($line, 'WARN [')) {
            return 'log-c-warn';
        }

        if (str::startsWith($line, '[INFO]') || str::startsWith($line, 'INFO [') || str::startsWith($line, '[LIVE]')) {
            return 'log-c-info';
        }

        if (str::startsWith($line, '[DEBUG]') || str::startsWith($line, 'DEBUG [')) {
            return 'log-c-debug';
        }

        if (str::startsWith($line, '[TRACE]') || str::startsWith($line, 'TRACE [')) {
            return 'log-c-trace';
        }

        if ($line && $line[0] === ':') {
            return 'log-c-debug';
        }

        return 'log-c-default';
    }

    /**
     * @param \php\gui\designer\UXAbstractCodeArea $area
     */
    protected function trimIfNeeded($area)
    {
        if ($this->lineCount <= $this->maxLines) {
            return;
        }

        $removeLines = $this->lineCount - $this->maxLines;
        $text = $area->text;
        $pos = 0;

        for ($i = 0; $i < $removeLines; $i++) {
            $next = str::pos($text, "\n", $pos);

            if ($next === false) {
                $pos = str::length($text);
                break;
            }

            $pos = $next + 1;
        }

        if ($pos > 0 && $pos <= str::length($text)) {
            $area->deleteText(0, $pos);
            $this->lineCount -= $removeLines;
        }
    }
}
