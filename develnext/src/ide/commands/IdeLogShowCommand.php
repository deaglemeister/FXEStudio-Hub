<?php
namespace ide\commands;

use ide\editors\AbstractEditor;
use ide\Ide;
use ide\misc\AbstractCommand;
use ide\utils\FileUtils;
use ide\utils\UiUtils;
use php\gui\layout\UXHBox;
use php\gui\layout\UXVBox;
use php\gui\UXButton;
use php\gui\UXLabel;
use php\gui\UXRichTextArea;
use php\gui\UXTab;
use php\lib\str;

/**
 * Лог IDE — таб в fileTabPane. UXRichTextArea даёт и раскраску по уровням,
 * и обычное выделение/копирование текста мышью и Ctrl+A/Ctrl+C.
 */
class IdeLogShowCommand extends AbstractCommand
{
    const MAX_LINES = 3000;

    /** @var UXTab|null */
    protected $tab;

    /** @var UXRichTextArea|null */
    protected $textArea;

    public function getName()
    {
        return _('menu.help.diagnostic');
    }

    public function getCategory()
    {
        return 'help';
    }

    /**
     * Открыть (или сфокусировать) таб IDE Logging.
     */
    public static function openInTab()
    {
        $ide = Ide::get();
        $cmd = $ide->getRegisteredCommand(self::class);

        if (!$cmd instanceof self) {
            $cmd = new self();
        }

        $cmd->onExecute();
    }

    public function onExecute($e = null, AbstractEditor $editor = null)
    {
        $mainForm = Ide::get()->getMainForm();

        if (!$mainForm) {
            return;
        }

        $fileTabPane = $mainForm->{'fileTabPane'};

        if ($this->tab) {
            $fileTabPane->selectTab($this->tab);
            $this->refresh(true);
            return;
        }

        $this->textArea = new UXRichTextArea();
        $this->textArea->wrapText = false;
        $this->textArea->classes->add('fxe-log-text');
        $this->textArea->style = '-fx-font-family: "Consolas", "Courier New", monospace; -fx-font-size: 12px;';

        $toolbar = new UXHBox();
        $toolbar->spacing = 10;
        $toolbar->alignment = 'CENTER_LEFT';
        $toolbar->padding = [6, 12, 6, 12];
        $toolbar->classes->add('fxe-log-toolbar');

        $title = new UXLabel('ide.log');
        $title->classes->add('fxe-error-dialog-summary');
        $title->tooltipText = (string) Ide::get()->getLogFile();

        $spacer = new UXHBox();
        UXHBox::setHgrow($spacer, 'ALWAYS');

        $refreshBtn = new UXButton('Обновить');
        $refreshBtn->classes->add('fxe-error-dialog-btn-ghost');
        $refreshBtn->on('action', function () {
            $this->refresh(true);
        });

        $copyBtn = new UXButton('Копировать всё');
        $copyBtn->classes->add('fxe-error-dialog-btn-ghost');
        $copyBtn->on('action', function () {
            if ($this->textArea) {
                $this->textArea->selectAll();
            }
        });

        $toolbar->children->addAll([$title, $spacer, $copyBtn, $refreshBtn]);

        $content = new UXVBox();
        $content->spacing = 0;
        $content->style = UiUtils::fontSizeStyle();
        $content->classes->add('fxe-log-view');
        UXVBox::setVgrow($this->textArea, 'ALWAYS');
        $content->children->addAll([$toolbar, $this->textArea]);

        $tab = new UXTab();
        $tab->text = 'IDE Logging';
        $tab->graphic = Ide::get()->getImage('icons/diagnostic16.png', [16, 16]);
        $tab->content = $content;
        $tab->closable = true;
        $tab->style = UiUtils::fontSizeStyle();

        $tab->on('closeRequest', function () {
            $this->tab = null;
            $this->textArea = null;
        });

        $this->tab = $tab;

        $fileTabPane->tabs->add($tab);
        $fileTabPane->selectTab($tab);

        $this->refresh(true);
    }

    /**
     * @param bool $scrollToEnd
     */
    public function refresh($scrollToEnd = false)
    {
        if (!$this->textArea) {
            return;
        }

        $content = (string) FileUtils::get(Ide::get()->getLogFile());
        $lines = str::split(str::replace($content, "\r\n", "\n"), "\n");

        if (sizeof($lines) > self::MAX_LINES) {
            $lines = array_slice($lines, -self::MAX_LINES);
        }

        $num = 0;
        $maxNum = sizeof($lines);
        $padWidth = max(4, strlen((string) $maxNum));

        $area = $this->textArea;
        $area->clear();

        foreach ($lines as $line) {
            $num++;
            $numText = sprintf('%' . $padWidth . 'd  ', $num);

            $area->appendText($numText, '-fx-fill: #475569;');
            $area->appendText($line . "\n", '-fx-fill: ' . static::colorForLine($line) . ';');
        }

        if ($scrollToEnd) {
            uiLater(function () use ($area) {
                if ($this->textArea === $area) {
                    $area->caretPosition = strlen($area->text);
                }
            });
        }
    }

    public function getIcon()
    {
        return 'icons/diagnostic16.png';
    }

    public function isAlways()
    {
        return true;
    }

    /**
     * @param string $line
     * @return string
     */
    protected static function colorForLine($line)
    {
        if (str::startsWith($line, 'ERROR ')) {
            return '#ef4444';
        }

        if (str::startsWith($line, 'WARN ')) {
            return '#f59e0b';
        }

        if (str::startsWith($line, 'DEBUG ')) {
            return '#64748b';
        }

        if (!str::startsWith($line, 'INFO ')) {
            return '#94a3b8';
        }

        if (str::contains($line, '[ide.IdeConfiguration]') || str::contains($line, 'Config')) {
            return '#38bdf8';
        }

        if (str::contains($line, '[ide.editors.') || str::contains($line, 'Editor')) {
            return '#c4b5fd';
        }

        if (str::contains($line, '[ide.forms.') || str::contains($line, 'Form')) {
            return '#94a3b8';
        }

        if (str::contains($line, '[ide.commands.') || str::contains($line, 'Command')) {
            return '#fbbf24';
        }

        if (str::contains($line, '[ide.project.') || str::contains($line, 'Project')) {
            return '#4ade80';
        }

        if (str::contains($line, '[ide.systems.') || str::contains($line, 'systems.')) {
            return '#67e8f9';
        }

        return '#4ade80';
    }
}
