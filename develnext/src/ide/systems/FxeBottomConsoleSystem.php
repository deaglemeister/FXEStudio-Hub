<?php
namespace ide\systems;

use ide\forms\MainForm;
use ide\editors\CodeEditor;
use ide\editors\FormEditor;
use ide\Ide;
use ide\project\ProjectConsoleOutput;
use ide\ui\FxeLogConsole;
use php\gui\layout\UXAnchorPane;
use php\gui\layout\UXHBox;
use php\gui\layout\UXVBox;
use php\gui\text\UXFont;
use php\gui\UXButton;
use php\gui\UXNode;
use php\gui\UXSplitPane;
use php\gui\UXTab;
use php\gui\UXTabPane;
use php\gui\UXTextArea;
use php\io\Stream;
use php\lib\fs;
use php\lib\str;
use timer\AccurateTimer;

/**
 * Общая нижняя консоль IDE (Терминал / Debug / Build) — под вкладками редакторов, везде одна.
 * Скрывается только вручную пользователем.
 */
class FxeBottomConsoleSystem
{
    const TAB_TERMINAL = 'terminal';
    const TAB_DEBUG = 'debug';
    const TAB_BUILD = 'build';

    /** @var bool */
    protected static $installed = false;

    /** @var MainForm|null */
    protected static $mainForm;

    /** @var UXSplitPane|null */
    protected static $workspaceSplit;

    /** @var UXAnchorPane|null */
    protected static $tabHost;

    /** @var UXAnchorPane|null */
    protected static $shell;

    /** @var UXAnchorPane|null */
    protected static $card;

    /** @var UXAnchorPane|null */
    protected static $debugHost;

    /** @var UXTabPane|null */
    protected static $tabPane;

    /** @var UXAnchorPane|UXVBox|null */
    protected static $terminalHost;

    /** @var UXAnchorPane|UXVBox|null */
    protected static $buildHost;

    /** @var FxeLogConsole|null */
    protected static $debugLogConsole;

    /** @var FxeLogConsole|ProjectConsoleOutput|null */
    protected static $debugOutput;

    /** @var string */
    protected static $activeTab = self::TAB_TERMINAL;

    /** @var bool */
    protected static $visible = false;

    /** @var AccurateTimer|null */
    protected static $debugTimer;

    /** @var int */
    protected static $debugTailPos = 0;

    /**
     * @param MainForm $form
     */
    public static function install(MainForm $form)
    {
        if (static::$installed) {
            static::$mainForm = $form;

            return;
        }

        static::$mainForm = $form;

        $workspace = ($form->splitTree && $form->splitTree->items->count > 1)
            ? $form->splitTree->items[1]
            : null;

        if (!$workspace) {
            return;
        }

        if ($workspace->data('--fxe-console-installed')) {
            static::$installed = true;

            return;
        }

        $tabArea = null;

        foreach ($workspace->children as $child) {
            $tabArea = $child;
            break;
        }

        if (!$tabArea && $form->fileTabPane) {
            $tabArea = $form->fileTabPane;

            while ($tabArea->parent && $tabArea->parent !== $workspace) {
                $tabArea = $tabArea->parent;
            }
        }

        if (!$tabArea) {
            return;
        }

        $workspace->children->clear();

        $tabHost = new UXAnchorPane();
        $tabHost->classes->add('fxe-workspace-pane');
        UXAnchorPane::setAnchor($tabArea, 0);
        $tabHost->add($tabArea);
        static::$tabHost = $tabHost;

        static::$workspaceSplit = new UXSplitPane();
        static::$workspaceSplit->orientation = 'VERTICAL';
        static::$workspaceSplit->id = 'fxeWorkspaceConsoleSplit';
        static::$workspaceSplit->classes->add('fxe-console-workspace-split');
        static::$workspaceSplit->items->add($tabHost);

        static::buildShell();
        static::$workspaceSplit->items->add(static::$shell);
        static::$shell->managed = false;
        static::$shell->visible = false;

        static::$workspaceSplit->observer('height')->addListener(function () {
            if (static::$visible) {
                FxeBottomConsoleSystem::persistConsoleHeight();
                FxeBottomConsoleSystem::refreshWorkspaceLayout();
            }
        });

        static::$workspaceSplit->observer('width')->addListener(function () {
            if (static::$visible) {
                FxeBottomConsoleSystem::persistConsoleHeight();
                FxeBottomConsoleSystem::refreshWorkspaceLayout();
            }
        });

        static::$shell->observer('height')->addListener(function () {
            if (static::$visible) {
                FxeBottomConsoleSystem::persistConsoleHeight();
                FxeBottomConsoleSystem::refreshWorkspaceLayout();
            }
        });

        UXAnchorPane::setAnchor(static::$workspaceSplit, 0);
        $workspace->add(static::$workspaceSplit);
        static::$workspaceSplit->dividerPositions = [1.0];
        $workspace->data('--fxe-console-installed', true);

        static::$installed = true;
        static::startDebugTail();

        uiLater(function () {
            FxeBottomConsoleSystem::reloadDebugLog();
        });

        if ((bool) Ide::get()->getUserConfigValue('ide.console.visible', false)) {
            uiLater(function () {
                FxeBottomConsoleSystem::show(self::TAB_TERMINAL);
                uiLater(function () {
                    FxeBottomConsoleSystem::applyHeight(0);
                });
            });
        }
    }

    protected static function buildShell()
    {
        static::$shell = new UXAnchorPane();
        static::$shell->id = 'fxeFormConsoleShell';
        static::$shell->classes->add('fxe-console-shell');

        static::$card = new UXAnchorPane();
        static::$card->classes->add('fxe-console-card');

        $closeBtn = new UXButton('✕');
        $closeBtn->tooltipText = 'Скрыть консоль';
        $closeBtn->classes->add('fxe-console-close');
        $closeBtn->on('action', function () {
            static::hide();
        });

        static::$tabPane = new UXTabPane();
        static::$tabPane->side = 'TOP';
        static::$tabPane->tabClosingPolicy = 'UNAVAILABLE';
        static::$tabPane->classes->add('fxe-form-console-tabs');

        static::$terminalHost = new UXVBox();
        static::$terminalHost->id = 'fxeConsoleTerminalHost';
        static::$terminalHost->classes->add('fxe-console-tab-host');
        static::$terminalHost->fillWidth = true;

        static::$debugHost = new UXAnchorPane();
        static::$debugHost->id = 'fxeConsoleDebugHost';
        static::$debugHost->classes->add('fxe-console-tab-host');

        static::$debugLogConsole = new FxeLogConsole();
        $debugUi = static::$debugLogConsole->getUi();
        static::$debugOutput = static::$debugLogConsole;
        UXAnchorPane::setAnchor($debugUi, 0);
        static::$debugHost->add($debugUi);

        static::$buildHost = new UXVBox();
        static::$buildHost->id = 'fxeConsoleBuildHost';
        static::$buildHost->classes->add('fxe-console-tab-host');
        static::$buildHost->fillWidth = true;

        $terminalTab = new UXTab();
        $terminalTab->text = 'Терминал';
        $terminalTab->content = static::$terminalHost;

        $debugTab = new UXTab();
        $debugTab->text = 'Debug';
        $debugTab->content = static::$debugHost;

        $buildTab = new UXTab();
        $buildTab->text = 'Build';
        $buildTab->content = static::$buildHost;

        static::$tabPane->tabs->addAll([$terminalTab, $debugTab, $buildTab]);

        static::$tabPane->on('change', function () {
            FxeBottomConsoleSystem::onTabChanged();
        });

        UXAnchorPane::setAnchor(static::$tabPane, 0);
        static::$card->add(static::$tabPane);
        static::$card->add($closeBtn);
        UXAnchorPane::setTopAnchor($closeBtn, 6);
        UXAnchorPane::setRightAnchor($closeBtn, 8);

        UXAnchorPane::setAnchor(static::$card, 0);
        static::$shell->add(static::$card);
    }

    /**
     * Публичный вход для обработчика вкладок.
     */
    public static function onTabChanged()
    {
        if (!static::$tabPane) {
            return;
        }

        $selected = static::$tabPane->selectedTab;

        if (!$selected) {
            return;
        }

        if ($selected->text === 'Терминал') {
            static::$activeTab = self::TAB_TERMINAL;
        } elseif ($selected->text === 'Debug') {
            static::$activeTab = self::TAB_DEBUG;
            static::reloadDebugLog();
        } else {
            static::$activeTab = self::TAB_BUILD;
        }
    }

    /**
     * @param string $id
     * @return UXTextArea
     */
    protected static function makeOutputArea($id)
    {
        $area = new UXTextArea();
        $area->id = $id;
        $area->editable = false;
        $area->wrapText = true;
        $area->font = UXFont::of('Consolas', 12);
        $area->classes->add('fxe-console-log-area');
        $area->prefRowCount = 10;

        return $area;
    }

    /**
     * @param string|null $tab
     * @param UXNode|null $terminalContent
     */
    public static function show($tab = null, UXNode $terminalContent = null)
    {
        $form = static::$mainForm ?: Ide::get()->getMainForm();

        if (!$form) {
            return;
        }

        static::install($form);

        if (!static::$workspaceSplit || !static::$shell) {
            return;
        }

        if ($terminalContent) {
            static::embedTerminal($terminalContent);
        }

        if ($tab) {
            static::selectTab($tab);
        }

        static::$shell->managed = true;
        static::$shell->visible = true;
        static::$visible = true;

        Ide::get()->setUserConfigValue('ide.console.visible', true);
        static::applyHeight(0);

        uiLater(function () {
            FxeBottomConsoleSystem::applyHeight(0);
            FxeBottomConsoleSystem::refreshWorkspaceLayout();
        });
    }

    /**
     * Пересчёт layout редактора при изменении высоты нижней консоли.
     */
    public static function refreshWorkspaceLayout()
    {
        if (static::$workspaceSplit) {
            static::$workspaceSplit->requestLayout();
        }

        if (static::$tabHost) {
            static::$tabHost->requestLayout();
        }

        $editor = FileSystem::getSelectedEditor();

        if (!$editor) {
            return;
        }

        if ($editor instanceof CodeEditor) {
            $editor->refreshUi();
        } elseif ($editor instanceof FormEditor) {
            $editor->refreshLayout();
        } elseif (method_exists($editor, 'refreshUi')) {
            $editor->refreshUi();
        } else {
            $editor->refresh();
        }
    }

    /**
     * @param UXNode $content
     */
    public static function showTerminal(UXNode $content)
    {
        static::show(self::TAB_TERMINAL, $content);
    }

    /**
     * @param UXNode $content
     */
    public static function showBuild(UXNode $content)
    {
        static::embedBuild($content);
        static::show(self::TAB_BUILD);
    }

    public static function hide()
    {
        if (!static::$shell || !static::$workspaceSplit) {
            return;
        }

        static::persistConsoleHeight();

        static::$shell->managed = false;
        static::$shell->visible = false;
        static::$visible = false;

        static::$workspaceSplit->dividerPositions = [1.0];

        Ide::get()->setUserConfigValue('ide.console.visible', false);

        uiLater(function () {
            FxeBottomConsoleSystem::refreshWorkspaceLayout();
        });
    }

    /**
     * Сохранение высоты консоли (SplitPane не поддерживает observer на dividerPositions).
     */
    public static function persistConsoleHeight()
    {
        if (!static::$shell || !static::$visible || !static::$workspaceSplit) {
            return;
        }

        $height = (int) static::$shell->height;
        if ($height < 120) {
            return;
        }

        static::$shell->prefHeight = $height;
        Ide::get()->setUserConfigValue('ide.consoleHeight', $height);

        $positions = static::$workspaceSplit->dividerPositions;
        if ($positions && count($positions) > 0) {
            $divider = (float) $positions[0];
            if ($divider > 0.01 && $divider < 0.999) {
                Ide::get()->setUserConfigValue('ide.consoleDivider', $divider);
            }
        }
    }

    /**
     * Публичный вход для uiLater/closure — в JPHP protected из closure не вызывается.
     *
     * @param int $retry
     */
    public static function applyHeight($retry = 0)
    {
        if (!static::$workspaceSplit || !static::$shell) {
            return;
        }

        $height = (int) Ide::get()->getUserConfigValue('ide.consoleHeight', 300);
        static::$shell->prefHeight = $height;
        static::$shell->minHeight = 140;
        static::$shell->maxHeight = 2000000000;

        if (!static::$visible) {
            static::$shell->managed = false;
            static::$shell->visible = false;

            return;
        }

        static::$shell->managed = true;
        static::$shell->visible = true;

        $splitHeight = static::$workspaceSplit->height;

        if ($splitHeight > 80) {
            $savedDivider = Ide::get()->getUserConfigValue('ide.consoleDivider', null);

            if ($savedDivider !== null && $savedDivider !== '') {
                $divider = (float) $savedDivider;
                if ($divider > 0.01 && $divider < 0.999) {
                    static::$workspaceSplit->dividerPositions = [$divider];

                    uiLater(function () {
                        FxeBottomConsoleSystem::persistConsoleHeight();
                        FxeBottomConsoleSystem::refreshWorkspaceLayout();
                    });

                    return;
                }
            }

            $percent = min(0.5, max(0.15, $height / $splitHeight));
            static::$workspaceSplit->dividerPositions = [1 - $percent];

            uiLater(function () {
                FxeBottomConsoleSystem::persistConsoleHeight();
                FxeBottomConsoleSystem::refreshWorkspaceLayout();
            });

            return;
        }

        if ($retry < 10) {
            uiLater(function () use ($retry) {
                FxeBottomConsoleSystem::applyHeight($retry + 1);
            });
        }
    }

    /**
     * @param bool|null $visible
     * @return bool
     */
    public static function toggle($visible = null)
    {
        if ($visible === null) {
            $visible = !static::isVisible();
        }

        if ($visible) {
            static::show(static::$activeTab ?: self::TAB_TERMINAL);
        } else {
            static::hide();
        }

        return $visible;
    }

    /**
     * @return bool
     */
    public static function isVisible()
    {
        return static::$visible && static::$shell && static::$shell->visible;
    }

    /**
     * @param string $tab
     */
    public static function selectTab($tab)
    {
        static::$activeTab = $tab;

        if (!static::$tabPane) {
            return;
        }

        $index = 0;

        switch ($tab) {
            case self::TAB_DEBUG:
                $index = 1;
                break;
            case self::TAB_BUILD:
                $index = 2;
                break;
        }

        if (static::$tabPane->tabs->count > $index) {
            static::$tabPane->selectTab(static::$tabPane->tabs[$index]);
        }
    }

    /**
     * @param UXAnchorPane|null $hostPane
     * @param UXNode $content
     */
    protected static function setHostContent($hostPane, UXNode $content)
    {
        if (!$hostPane) {
            return;
        }

        $hostPane->children->clear();
        $content->classes->add('fxe-console-embed');

        if ($hostPane instanceof UXVBox) {
            UXVBox::setVgrow($content, 'ALWAYS');
            $content->minHeight = 160;
            $content->prefHeight = 220;
            $hostPane->add($content);

            return;
        }

        if ($content instanceof UXVBox) {
            UXVBox::setVgrow($content, 'ALWAYS');
            $content->minHeight = 160;
            $content->prefHeight = 220;
        }

        UXAnchorPane::setAnchor($content, 0);
        $hostPane->add($content);
    }

    /**
     * @param UXNode $content
     */
    public static function embedTerminal(UXNode $content)
    {
        static::setHostContent(static::$terminalHost, $content);
    }

    /**
     * @param UXNode $content
     */
    public static function embedBuild(UXNode $content)
    {
        static::setHostContent(static::$buildHost, $content);
    }

    /**
     * @return ProjectConsoleOutput|null
     */
    public static function getDebugOutput()
    {
        return static::$debugOutput;
    }

    protected static function startDebugTail()
    {
        if (static::$debugTimer) {
            return;
        }

        static::$debugTimer = new AccurateTimer(500, function () {
            FxeBottomConsoleSystem::tickDebugLog();
        });
        static::$debugTimer->start();
    }

    /**
     * Публичный вход для AccurateTimer — в JPHP closure не видит protected-методы.
     *
     * @param bool $forceScroll
     */
    public static function tickDebugLog($forceScroll = false)
    {
        static::pollDebugLog($forceScroll);
    }

    public static function reloadDebugLog()
    {
        if (!static::$debugOutput) {
            return;
        }

        static::$debugTailPos = 0;
        if (static::$debugOutput instanceof FxeLogConsole) {
            static::$debugOutput->clear();
        } else if (static::$debugOutput) {
            static::$debugOutput->clear();
        }
        static::pollDebugLog(true);
    }

    /**
     * @param bool $forceScroll
     */
    protected static function pollDebugLog($forceScroll = false)
    {
        if (!Ide::isCreated() || !static::$debugOutput) {
            return;
        }

        $file = Ide::get()->getLogFile();

        if (!$file || !fs::isFile($file)) {
            return;
        }

        $size = fs::size($file);

        if ($size < static::$debugTailPos) {
            static::$debugTailPos = 0;
        }

        if ($size <= static::$debugTailPos) {
            return;
        }

        try {
            $stream = Stream::of($file, 'r');
            $stream->seek(static::$debugTailPos);
            $chunk = $stream->read($size - static::$debugTailPos);
            static::$debugTailPos = $size;

            if ($chunk) {
                foreach (str::split($chunk, "\n") as $line) {
                    if ($line !== '') {
                        static::$debugOutput->addConsoleLine($line);
                    }
                }
            }
        } catch (\Throwable $e) {
            // не критично
        }
    }
}
