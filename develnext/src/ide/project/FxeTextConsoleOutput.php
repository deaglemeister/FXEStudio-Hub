<?php
namespace ide\project;

use php\gui\UXTextArea;
use php\lib\str;

/**
 * Простой вывод строк в TextArea (Debug / Build без полного BuildProgressForm).
 */
class FxeTextConsoleOutput implements ProjectConsoleOutput
{
    /** @var UXTextArea */
    protected $area;

    /** @var int */
    protected $maxLines = 5000;

    public function __construct(UXTextArea $area)
    {
        $this->area = $area;
    }

    public function addConsoleLine($line, $color = '#333333')
    {
        if (!$this->area || $this->area->isFree()) {
            return;
        }

        $line = str::replace($line, "\r\n", "\n");
        $line = str::replace($line, "\r", "\n");

        uiLater(function () use ($line) {
            if (!$this->area || $this->area->isFree()) {
                return;
            }

            $text = $this->area->text;

            if ($text) {
                $text .= "\n" . $line;
            } else {
                $text = $line;
            }

            $lines = str::split($text, "\n");

            if (sizeof($lines) > $this->maxLines) {
                $lines = array_slice($lines, -$this->maxLines);
                $text = str::join($lines, "\n");
            }

            $this->area->text = $text;
            $this->area->end();
        });
    }

    public function clear()
    {
        if ($this->area && !$this->area->isFree()) {
            $this->area->text = '';
        }
    }
}
