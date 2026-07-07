<?php
namespace ide\editors\panels;

use ide\editors\CodeEditor;
use ide\Logger;
use php\gui\designer\UXPhpCodeArea;
use php\gui\event\UXKeyEvent;
use php\gui\layout\UXHBox;
use php\gui\layout\UXVBox;
use php\gui\UXButton;
use php\gui\UXCheckbox;
use php\gui\UXLabel;
use php\gui\UXTextField;
use php\lib\char;
use php\lib\str;

/**
 * Встроенная (не модальная) панель поиска/замены в духе VS Code.
 *
 * Class FindReplaceBar
 * @package ide\editors\panels
 */
class FindReplaceBar
{
    /** @var CodeEditor */
    protected $editor;

    /** @var UXVBox */
    protected $ui;

    /** @var UXHBox */
    protected $replaceRow;

    /** @var UXTextField */
    protected $searchField;

    /** @var UXTextField */
    protected $replaceField;

    /** @var UXCheckbox */
    protected $caseCheckbox;

    /** @var UXCheckbox */
    protected $wholeWordCheckbox;

    /** @var UXLabel */
    protected $counterLabel;

    /** @var UXButton */
    protected $toggleReplaceButton;

    /**
     * @var array [[pos, len], ...]
     */
    protected $matches = [];

    /**
     * @var int
     */
    protected $currentMatchIndex = -1;

    /**
     * @var int
     */
    protected $searchToken = 0;

    /**
     * @var bool
     */
    protected $replaceMode = false;

    /**
     * FindReplaceBar constructor.
     * @param CodeEditor $editor
     */
    public function __construct(CodeEditor $editor)
    {
        $this->editor = $editor;
        $this->ui = $this->makeUi();

        $this->ui->visible = false;
        $this->ui->managed = false;
    }

    /**
     * @return UXVBox
     */
    public function getUi()
    {
        return $this->ui;
    }

    /**
     * @return bool
     */
    public function isOpen()
    {
        return (bool) $this->ui->visible;
    }

    protected function makeIconButton($text, $tooltip, callable $onClick)
    {
        $button = new UXButton($text);
        $button->classes->add('fxe-find-icon-button');
        $button->tooltipText = $tooltip;
        $button->on('click', $onClick);

        return $button;
    }

    protected function makeUi()
    {
        $box = new UXVBox();
        $box->classes->add('fxe-find-bar');
        $box->spacing = 3;
        $box->padding = 6;
        $box->maxWidth = 460;

        $searchRow = new UXHBox();
        $searchRow->classes->add('fxe-find-row');
        $searchRow->spacing = 4;

        $this->toggleReplaceButton = $toggle = $this->makeIconButton('⌵', 'Показать замену', function () {
            $this->setReplaceMode(!$this->replaceMode);
        });

        $this->searchField = $field = new UXTextField();
        $field->promptText = 'Найти';
        $field->classes->add('fxe-find-input');
        UXHBox::setHgrow($field, 'ALWAYS');

        $this->counterLabel = $counter = new UXLabel('Нет результатов');
        $counter->classes->add('fxe-find-counter');

        $this->caseCheckbox = $case = new UXCheckbox('Aa');
        $case->classes->add('fxe-find-toggle');
        $case->tooltipText = 'Учитывать регистр';

        $this->wholeWordCheckbox = $word = new UXCheckbox('"W"');
        $word->classes->add('fxe-find-toggle');
        $word->tooltipText = 'Слово целиком';

        $prevBtn = $this->makeIconButton('▲', 'Предыдущее совпадение (Shift+Enter)', function () {
            $this->gotoPrev();
        });
        $nextBtn = $this->makeIconButton('▼', 'Следующее совпадение (Enter)', function () {
            $this->gotoNext();
        });
        $closeBtn = $this->makeIconButton('✕', 'Закрыть (Esc)', function () {
            $this->close();
        });

        $searchRow->add($toggle);
        $searchRow->add($field);
        $searchRow->add($counter);
        $searchRow->add($case);
        $searchRow->add($word);
        $searchRow->add($prevBtn);
        $searchRow->add($nextBtn);
        $searchRow->add($closeBtn);

        $this->replaceRow = $replaceRow = new UXHBox();
        $replaceRow->classes->add('fxe-find-row');
        $replaceRow->spacing = 4;
        $replaceRow->visible = false;
        $replaceRow->managed = false;

        $this->replaceField = $replaceField = new UXTextField();
        $replaceField->promptText = 'Заменить';
        $replaceField->classes->add('fxe-find-input');
        UXHBox::setHgrow($replaceField, 'ALWAYS');

        $replaceBtn = new UXButton('Заменить');
        $replaceBtn->classes->add('fxe-find-text-button');
        $replaceBtn->on('click', function () {
            $this->replaceCurrent();
        });

        $replaceAllBtn = new UXButton('Заменить все');
        $replaceAllBtn->classes->add('fxe-find-text-button');
        $replaceAllBtn->on('click', function () {
            $this->replaceAll();
        });

        $replaceRow->add($replaceField);
        $replaceRow->add($replaceBtn);
        $replaceRow->add($replaceAllBtn);

        $box->add($searchRow);
        $box->add($replaceRow);

        $field->on('keyDown', function (UXKeyEvent $e) {
            switch ($e->codeName) {
                case 'Enter':
                    if ($e->shiftDown) {
                        $this->gotoPrev();
                    } else {
                        $this->gotoNext();
                    }
                    $e->consume();
                    break;

                case 'Esc':
                    $this->close();
                    $e->consume();
                    break;
            }
        });

        $field->on('keyUp', function (UXKeyEvent $e) {
            if ($e->codeName == 'Enter' || $e->codeName == 'Esc') {
                return;
            }

            $this->scheduleSearch();
        });

        $replaceField->on('keyDown', function (UXKeyEvent $e) {
            if ($e->codeName == 'Enter') {
                $this->replaceCurrent();
                $e->consume();
            } elseif ($e->codeName == 'Esc') {
                $this->close();
                $e->consume();
            }
        });

        $this->caseCheckbox->observer('selected')->addListener(function () {
            $this->search();
        });

        $this->wholeWordCheckbox->observer('selected')->addListener(function () {
            $this->search();
        });

        return $box;
    }

    /**
     * @param bool $replaceMode
     */
    public function setReplaceMode($replaceMode)
    {
        $this->replaceMode = (bool) $replaceMode;
        $this->replaceRow->visible = $this->replaceMode;
        $this->replaceRow->managed = $this->replaceMode;
        $this->toggleReplaceButton->text = $this->replaceMode ? '⌃' : '⌵';
    }

    /**
     * @param bool $replaceMode
     */
    public function open($replaceMode = false)
    {
        $this->setReplaceMode($replaceMode);

        $selected = $this->editor->getTextArea()->selectedText;

        if ($selected && str::pos($selected, "\n") < 0) {
            $this->searchField->text = $selected;
        }

        $this->ui->visible = true;
        $this->ui->managed = true;

        $this->search();

        $this->searchField->requestFocus();
        $this->searchField->selectAll();

        if ($this->replaceMode) {
            $this->replaceField->text = '';
        }
    }

    public function close()
    {
        $this->ui->visible = false;
        $this->ui->managed = false;

        $this->clearHighlight();

        $this->editor->getTextArea()->requestFocus();
    }

    protected function isWordChar($char)
    {
        if ($char === '' || $char === null) {
            return false;
        }

        return $char === '_' || $char === '$' || char::isLetterOrDigit($char);
    }

    protected function isWholeWordMatch($haystack, $pos, $len, $haystackLen)
    {
        if ($pos > 0 && $this->isWordChar(str::sub($haystack, $pos - 1, $pos))) {
            return false;
        }

        $end = $pos + $len;

        if ($end < $haystackLen && $this->isWordChar(str::sub($haystack, $end, $end + 1))) {
            return false;
        }

        return true;
    }

    protected function computeMatches($needle)
    {
        $this->matches = [];

        $needle = (string) $needle;

        if ($needle === '') {
            return;
        }

        $haystack = $this->editor->getTextArea()->text;
        $haystackLen = str::length($haystack);
        $needleLen = str::length($needle);
        $caseSensitive = $this->caseCheckbox->selected;
        $wholeWord = $this->wholeWordCheckbox->selected;

        $from = 0;

        while ($from <= $haystackLen) {
            $pos = $caseSensitive
                ? str::pos($haystack, $needle, $from)
                : str::posIgnoreCase($haystack, $needle, $from);

            if ($pos < 0) {
                break;
            }

            if (!$wholeWord || $this->isWholeWordMatch($haystack, $pos, $needleLen, $haystackLen)) {
                $this->matches[] = [$pos, $needleLen];
            }

            $from = $pos + ($needleLen > 0 ? $needleLen : 1);
        }
    }

    protected function highlightAll()
    {
        $textArea = $this->editor->getTextArea();

        if (!($textArea instanceof UXPhpCodeArea)) {
            return;
        }

        if (!$this->matches) {
            $textArea->applyFindMatches('');
            return;
        }

        $parts = [];

        foreach ($this->matches as $match) {
            list($pos, $len) = $match;
            $parts[] = $pos . ':' . ($pos + $len);
        }

        $textArea->applyFindMatches(str::join($parts, '|'));
    }

    protected function clearHighlight()
    {
        $textArea = $this->editor->getTextArea();

        if ($textArea instanceof UXPhpCodeArea) {
            $textArea->applyFindMatches('');
        }
    }

    protected function updateCounter()
    {
        $count = count($this->matches);

        if ($count === 0) {
            $this->counterLabel->text = 'Нет результатов';
        } else {
            $this->counterLabel->text = ($this->currentMatchIndex + 1) . ' из ' . $count;
        }
    }

    protected function selectCurrent()
    {
        if ($this->currentMatchIndex < 0 || $this->currentMatchIndex >= count($this->matches)) {
            return;
        }

        list($pos, $len) = $this->matches[$this->currentMatchIndex];

        $textArea = $this->editor->getTextArea();
        $textArea->caretPosition = $pos;
        $textArea->select($pos, $pos + $len);
    }

    /**
     * Пересчитать совпадения и обновить подсветку/счётчик.
     */
    public function search()
    {
        $this->scheduleSearch(true);
    }

    protected function scheduleSearch($immediate = false)
    {
        $token = ++$this->searchToken;

        if ($immediate) {
            $this->runSearch($token);
            return;
        }

        waitAsync(200, function () use ($token) {
            if ($token !== $this->searchToken) {
                return;
            }

            $this->runSearch($token);
        });
    }

    protected function runSearch($token)
    {
        if ($token !== $this->searchToken) {
            return;
        }

        $this->computeMatches($this->searchField->text);
        $this->currentMatchIndex = $this->matches ? 0 : -1;

        $this->highlightAll();
        $this->selectCurrent();
        $this->updateCounter();
    }

    public function gotoNext()
    {
        if (!$this->matches) {
            $this->search();

            if (!$this->matches) {
                return;
            }
        }

        $this->currentMatchIndex = ($this->currentMatchIndex + 1) % count($this->matches);
        $this->selectCurrent();
        $this->updateCounter();
    }

    public function gotoPrev()
    {
        if (!$this->matches) {
            $this->search();

            if (!$this->matches) {
                return;
            }
        }

        $this->currentMatchIndex--;

        if ($this->currentMatchIndex < 0) {
            $this->currentMatchIndex = count($this->matches) - 1;
        }

        $this->selectCurrent();
        $this->updateCounter();
    }

    public function replaceCurrent()
    {
        if (!$this->matches || $this->currentMatchIndex < 0) {
            return;
        }

        list($pos, $len) = $this->matches[$this->currentMatchIndex];
        $newText = $this->replaceField->text;

        $textArea = $this->editor->getTextArea();
        $textArea->select($pos, $pos + $len);
        $textArea->selectedText = $newText;

        Logger::debug("FindReplaceBar: replace at $pos, len $len -> " . str::length($newText));

        $this->search();
    }

    public function replaceAll()
    {
        $needle = $this->searchField->text;

        if ($needle === '') {
            return;
        }

        $this->computeMatches($needle);

        if (!$this->matches) {
            return;
        }

        $newText = $this->replaceField->text;
        $textArea = $this->editor->getTextArea();
        $scrollPane = $this->editor->getTextAreaScrollPane();

        $result = '';
        $haystack = $textArea->text;
        $lastEnd = 0;

        foreach ($this->matches as $match) {
            list($pos, $len) = $match;
            $result .= str::sub($haystack, $lastEnd, $pos);
            $result .= $newText;
            $lastEnd = $pos + $len;
        }

        $result .= str::sub($haystack, $lastEnd, str::length($haystack));

        $count = count($this->matches);

        $scrollX = $scrollPane ? $scrollPane->scrollX : null;
        $scrollY = $scrollPane ? $scrollPane->scrollY : null;

        $textArea->text = $result;

        if ($scrollPane) {
            $scrollPane->scrollX = $scrollX;
            $scrollPane->scrollY = $scrollY;
        }

        Logger::debug("FindReplaceBar: replace all, $count occurrence(s)");

        $this->search();
    }
}
