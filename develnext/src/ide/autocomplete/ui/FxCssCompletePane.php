<?php
namespace ide\autocomplete\ui;

use ide\autocomplete\fxcss\FxCssCompletionData;
use php\gui\designer\UXAbstractCodeArea;
use php\gui\event\UXKeyEvent;
use php\gui\layout\UXHBox;
use php\gui\layout\UXVBox;
use php\gui\paint\UXColor;
use php\gui\UXLabel;
use php\gui\UXListCell;
use php\gui\UXListView;
use php\gui\UXPopupWindow;
use php\lib\char;
use php\lib\str;

/**
 * Автодополнение JavaFX CSS: только -fx- свойства и значения цветов после «:».
 */
class FxCssCompletePane
{
    /** @var UXAbstractCodeArea */
    protected $area;

    /** @var UXPopupWindow */
    protected $popup;

    /** @var UXListView */
    protected $list;

    /** @var bool */
    protected $shown = false;

    /** @var string|null */
    protected $lastPrefix;

    /** @var mixed */
    protected $showTimer;

    /** @var int */
    protected $showToken = 0;

    public function __construct(UXAbstractCodeArea $area)
    {
        $this->area = $area;
        $this->buildUi();
        $this->bindEvents();
    }

    protected function buildUi()
    {
        $popup = new UXPopupWindow();
        $popup->focusTraversable = false;

        $list = new UXListView();
        $list->classes->addAll(['hide-hor-scroll', 'dn-console-list', 'dn-autocomplete', 'fxe-css-complete-list']);
        $list->prefWidth = 280;
        $list->prefHeight = 200;
        $list->fixedCellSize = 22;
        $list->focusTraversable = false;
        $list->setCellFactory(function (UXListCell $cell, $item) {
            $text = (string) $item;
            $cell->text = $text;

            if ($this->isColorValue($text)) {
                $swatch = new UXLabel();
                $swatch->prefWidth = 14;
                $swatch->prefHeight = 14;
                $swatch->style = '-fx-background-color: ' . $this->toCssColor($text) . '; -fx-border-color: #515658; -fx-border-width: 1px;';

                $label = new UXLabel($text);
                $label->classes->add('fxe-css-complete-item-label');
                $cell->graphic = new UXHBox([$swatch, $label]);
            } else {
                $cell->graphic = null;
            }

            return $cell;
        });

        $box = new UXVBox([$list]);
        $box->classes->add('fxe-css-complete');
        $box->spacing = 0;
        $box->focusTraversable = false;
        $popup->layout = $box;

        $this->popup = $popup;
        $this->list = $list;
    }

    protected function bindEvents()
    {
        $this->area->observer('focused')->addListener(function ($_, $value) {
            if (!$value) {
                $this->hide();
            }
        });

        $this->area->on('keyDown', function (UXKeyEvent $e) {
            if (!$this->shown) {
                return;
            }

            switch ($e->codeName) {
                case 'Up':
                    $this->list->selectedIndex = max(0, $this->list->selectedIndex - 1);
                    $e->consume();
                    break;
                case 'Down':
                    $this->list->selectedIndex = min($this->list->items->size - 1, $this->list->selectedIndex + 1);
                    $e->consume();
                    break;
                case 'Enter':
                case 'Tab':
                    $this->pickSelected();
                    $e->consume();
                    break;
                case 'Esc':
                    $this->hide();
                    $e->consume();
                    break;
            }
        }, __CLASS__);

        $this->area->on('keyUp', function (UXKeyEvent $e) {
            if ($e->controlDown || $e->altDown) {
                return;
            }

            switch ($e->codeName) {
                case 'Up':
                case 'Down':
                case 'Enter':
                case 'Tab':
                case 'Esc':
                case 'Left':
                case 'Right':
                    return;
            }

            if ($this->isClosingChar($e)) {
                $this->hide();
                return;
            }

            $this->scheduleTryShow();
        }, __CLASS__);

        $this->list->on('click', function () {
            $this->pickSelected();
        });
    }

    protected function isClosingChar(UXKeyEvent $e)
    {
        $ch = $e->character;

        return $ch === '{' || $ch === '}' || $ch === ';' || $ch === "\n" || $ch === "\r";
    }

    protected function scheduleTryShow()
    {
        $token = ++$this->showToken;

        if ($this->showTimer) {
            $this->showTimer->free();
        }

        $this->showTimer = waitAsync(120, function () use ($token) {
            $this->showTimer = null;

            if ($token !== $this->showToken) {
                return;
            }

            $this->tryShow();
        });
    }

    protected function tryShow()
    {
        if (!$this->shouldOfferCompletion()) {
            $this->hide();
            return;
        }

        $prefix = $this->readPrefix();

        if ($prefix === '') {
            $this->hide();
            return;
        }

        $items = $this->suggest($prefix);

        if (!$items || $this->isExactComplete($prefix, $items)) {
            $this->hide();
            return;
        }

        if ($prefix === $this->lastPrefix && $this->shown) {
            return;
        }

        $this->lastPrefix = $prefix;
        $this->list->items->setAll($items);
        $this->list->selectedIndex = 0;
        $this->showAtCaret();
    }

    protected function shouldOfferCompletion()
    {
        $beforeCaret = $this->textBeforeCaret();

        if ($beforeCaret === '') {
            return false;
        }

        if ($this->isInPropertyValue($beforeCaret)) {
            return true;
        }

        return $this->isTypingFxProperty($beforeCaret);
    }

    protected function isTypingFxProperty($beforeCaret)
    {
        $fxPos = $this->lastPos($beforeCaret, '-fx-');

        if ($fxPos < 0) {
            return false;
        }

        $fragment = str::sub($beforeCaret, $fxPos);

        if (str::contains($fragment, ':') || str::contains($fragment, ';')) {
            return false;
        }

        return true;
    }

    protected function isInPropertyValue($beforeCaret)
    {
        $colonPos = $this->lastPos($beforeCaret, ':');

        if ($colonPos < 0) {
            return false;
        }

        $beforeColon = str::sub($beforeCaret, 0, $colonPos);

        if ($this->lastPos($beforeColon, '-fx-') < 0) {
            return false;
        }

        $afterColon = str::sub($beforeCaret, $colonPos + 1);

        if ($afterColon === '' || str::contains($afterColon, ';')) {
            return false;
        }

        return true;
    }

    protected function readPrefix()
    {
        $text = $this->area->text;
        $pos = (int) $this->area->caretPosition;
        $beforeCaret = $this->textBeforeCaret();
        $start = $pos;

        if ($this->isInPropertyValue($beforeCaret)) {
            while ($start > 0) {
                $ch = $text[$start - 1];

                if ($this->isValuePrefixChar($ch)) {
                    $start--;
                } else {
                    break;
                }
            }

            return str::sub($text, $start, $pos - $start);
        }

        $fxPos = $this->lastPos($beforeCaret, '-fx-');

        if ($fxPos >= 0) {
            $lineStart = $pos - str::length($beforeCaret);
            $start = $lineStart + $fxPos;

            return str::sub($text, $start, $pos - $start);
        }

        while ($start > 0) {
            $ch = $text[$start - 1];

            if ($this->isPropertyPrefixChar($ch)) {
                $start--;
            } else {
                break;
            }
        }

        return str::sub($text, $start, $pos - $start);
    }

    protected function isPropertyPrefixChar($ch)
    {
        if ($ch === '' || $ch === null) {
            return false;
        }

        return char::isLetterOrDigit($ch) || $ch === '-';
    }

    protected function isValuePrefixChar($ch)
    {
        if ($ch === '' || $ch === null) {
            return false;
        }

        return char::isLetterOrDigit($ch) || $ch === '#' || $ch === '-';
    }

    protected function suggest($prefix)
    {
        $lower = str::lower($prefix);
        $beforeCaret = $this->textBeforeCaret();

        if ($this->isInPropertyValue($beforeCaret)) {
            return $this->filter(FxCssCompletionData::colorValues(), $lower);
        }

        if ($this->isTypingFxProperty($beforeCaret)) {
            return $this->filter(FxCssCompletionData::properties(), $lower);
        }

        return [];
    }

    protected function filter(array $items, $lower)
    {
        $result = [];

        foreach ($items as $item) {
            if (str::startsWith(str::lower($item), $lower)) {
                $result[] = $item;
            }
        }

        return $result;
    }

    protected function isExactComplete($prefix, array $items)
    {
        if ($prefix === '') {
            return false;
        }

        $lower = str::lower($prefix);
        $exact = false;
        $hasExtension = false;

        foreach ($items as $item) {
            $itemLower = str::lower($item);

            if ($itemLower === $lower) {
                $exact = true;
            } elseif (str::startsWith($itemLower, $lower) && str::length($itemLower) > str::length($lower)) {
                $hasExtension = true;
            }
        }

        return $exact && !$hasExtension;
    }

    protected function showAtCaret()
    {
        $bounds = $this->area->caretBounds;

        if (!$bounds) {
            return;
        }

        $x = $bounds['x'] + $bounds['width'];
        $y = $bounds['y'] + $bounds['height'];

        $this->popup->show($this->area->form, $x, $y);
        $this->shown = true;
    }

    protected function pickSelected()
    {
        $value = $this->list->selectedItem;

        if (!$value) {
            $this->hide();
            return;
        }

        $prefix = $this->readPrefix();
        $pos = (int) $this->area->caretPosition;
        $start = $pos - str::length($prefix);

        $insert = (string) $value;

        if (!str::endsWith($insert, ':') && str::startsWith($insert, '-fx-')) {
            $insert .= ': ';
        }

        $this->area->replaceText($start, $pos, $insert);
        $this->area->caretPosition = $start + str::length($insert);
        $this->hide();
    }

    public function hide()
    {
        if ($this->shown) {
            $this->popup->hide();
        }

        $this->shown = false;
        $this->lastPrefix = null;
    }

    protected function textBeforeCaret()
    {
        $line = $this->area->getParagraph((int) $this->area->caretLine);
        $lineText = $line ? (string) $line['text'] : '';

        return str::sub($lineText, 0, (int) $this->area->caretOffset);
    }

    protected function lastPos($haystack, $needle)
    {
        $pos = -1;
        $from = 0;

        while (($found = str::pos($haystack, $needle, $from)) >= 0) {
            $pos = $found;
            $from = $found + 1;
        }

        return $pos;
    }

    protected function isColorValue($text)
    {
        $lower = str::lower((string) $text);

        if (str::startsWith($lower, '#')) {
            return true;
        }

        return in_array($lower, ['transparent', 'white', 'black', 'red', 'green', 'blue', 'yellow', 'silver', 'gray', 'grey'], true);
    }

    protected function toCssColor($text)
    {
        $text = str::lower((string) $text);

        if (str::startsWith($text, '#')) {
            return $text;
        }

        return $text;
    }
}
