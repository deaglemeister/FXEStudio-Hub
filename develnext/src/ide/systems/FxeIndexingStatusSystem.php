<?php
namespace ide\systems;

use ide\forms\MainForm;
use ide\Ide;
use ide\project\ProjectTree;
use php\gui\UXLabel;
use php\lib\fs;
use timer\AccurateTimer;

/**
 * Нижний статус-бар IDE в реальном времени.
 *
 * Ready | Project: name | PHP: x/JPHP | LSP: Running | Index: Done | Theme: Dark
 */
class FxeIndexingStatusSystem
{
    const STATE_READY = 'ready';
    const STATE_INDEXING = 'indexing';
    const STATE_ERROR = 'error';

    /** @var bool */
    protected static $init = false;

    /** @var UXLabel|null */
    protected static $primaryLabel;

    /** @var UXLabel|null */
    protected static $infoLabel;

    /** @var string */
    protected static $state = self::STATE_READY;

    /** @var int */
    protected static $indexPercent = 0;

    /** @var string */
    protected static $indexMessage = '';

    /** @var bool */
    protected static $lspConnected = false;

    /** @var bool */
    protected static $indexDone = false;

    /** @var string */
    protected static $lspStatus = 'Starting';

    /** @var mixed */
    protected static $rescanTimer;

    /** @var AccurateTimer|null */
    protected static $refreshTimer;

    public static function install(MainForm $form)
    {
        static::init();

        $pane = $form->statusPane;

        if (!$pane) {
            return;
        }

        if ($pane->data('--fxe-status-installed')) {
            static::render();
            return;
        }

        $pane->visible = true;
        $pane->managed = true;
        $pane->spacing = 0;
        $pane->classes->add('fxe-status-shell');
        $pane->minHeight = $pane->prefHeight = $pane->maxHeight = 54;
        $pane->data('--fxe-status-installed', true);

        static::$primaryLabel = new UXLabel('Ready');
        static::$primaryLabel->classes->add('fxe-status-primary');

        static::$infoLabel = new UXLabel('');
        static::$infoLabel->classes->add('fxe-status-muted');
        static::$infoLabel->maxWidth = 99999;

        $spacer = new \php\gui\layout\UXHBox();
        \php\gui\layout\UXHBox::setHgrow($spacer, 'ALWAYS');
        \php\gui\layout\UXHBox::setHgrow(static::$infoLabel, 'NEVER');

        $card = new \php\gui\layout\UXHBox();
        $card->classes->add('fxe-status-card');
        $card->alignment = 'CENTER_LEFT';
        $card->spacing = 10;
        \php\gui\layout\UXHBox::setHgrow($card, 'ALWAYS');
        $card->children->setAll([
            static::$primaryLabel,
            $spacer,
            static::$infoLabel
        ]);

        $pane->children->setAll([$card]);

        static::startRefreshTimer();
        static::render();
    }

    public static function init()
    {
        if (static::$init) {
            return;
        }

        static::$init = true;

        Ide::get()->bind('openProject', function () {
            static::onOpenProject();
        });

        Ide::get()->bind('afterCloseProject', function () {
            static::onCloseProject();
        });

        WatcherSystem::addListener(function ($file, $event) {
            if (!$file) {
                return;
            }

            $path = fs::normalize($file->getPath());

            if (ProjectTree::isRelevantProjectPath($path)) {
                static::scheduleRescan();
            }
        });
    }

    protected static function startRefreshTimer()
    {
        if (static::$refreshTimer) {
            return;
        }

        static::$refreshTimer = new AccurateTimer(500, function () {
            FxeIndexingStatusSystem::tickStatusBar();
        });
        static::$refreshTimer->start();
    }

    /**
     * Публичный вход для AccurateTimer — в JPHP closure не видит protected-методы.
     */
    public static function tickStatusBar()
    {
        static::pollLspStatus();
        static::render();
    }

    protected static function pollLspStatus()
    {
        $process = FxeProcessSystem::getProcess('lsp');

        if (!$process) {
            static::$lspConnected = false;
            static::$lspStatus = FxeProcessSystem::isStarted() ? 'Disconnected' : 'Starting';

            return;
        }

        static::$lspConnected = true;
        static::$lspStatus = 'Running';

        if (static::$state === self::STATE_ERROR && static::$indexDone) {
            static::$state = self::STATE_READY;
            static::$indexMessage = '';
        }
    }

    public static function onOpenProject()
    {
        static::$state = self::STATE_READY;
        static::$indexPercent = 0;
        static::$indexMessage = '';
        static::$indexDone = false;
        static::$lspStatus = 'Starting';

        static::render();

        uiLater(function () {
            static::scanProject();
        });
    }

    public static function onCloseProject()
    {
        static::$state = self::STATE_READY;
        static::$indexPercent = 0;
        static::$indexMessage = '';
        static::$indexDone = false;
        static::render();
    }

    public static function onIndexStart()
    {
        static::$state = self::STATE_INDEXING;
        static::$indexPercent = 0;
        static::$indexMessage = 'Indexing project...';
        static::$indexDone = false;
        static::render();
    }

    /**
     * @param int $percent
     * @param string $message
     */
    public static function onIndexProgress($percent, $message = '')
    {
        static::$state = self::STATE_INDEXING;
        static::$indexPercent = max(0, min(100, (int) $percent));
        static::$indexMessage = $message ?: 'Indexing project...';
        static::render();
    }

    public static function onIndexDone($ok)
    {
        static::$indexDone = (bool) $ok;
        static::$indexPercent = $ok ? 100 : static::$indexPercent;
        static::$state = $ok ? self::STATE_READY : self::STATE_ERROR;
        static::$indexMessage = $ok ? '' : 'Index failed';
        static::render();

        uiLater(function () {
            FxeIndexingStatusSystem::render();
        });

        static::scanProject();
    }

    public static function onLspDisconnected($message = 'LSP disconnected')
    {
        static::$lspConnected = false;
        static::$lspStatus = 'Disconnected';
        static::$state = self::STATE_ERROR;
        static::$indexMessage = $message;
        static::render();
    }

    public static function setIndexStatus($text)
    {
        if (static::$state === self::STATE_INDEXING) {
            static::$indexMessage = $text;
            static::render();
        }
    }

    public static function scheduleRescan()
    {
        if (static::$rescanTimer) {
            static::$rescanTimer->free();
        }

        static::$rescanTimer = waitAsync(400, function () {
            static::$rescanTimer = null;
            static::scanProject();
        });
    }

    public static function scanProject()
    {
        static::render();
    }

    protected static function getProjectName()
    {
        $project = Ide::project();

        return $project ? $project->getName() : '—';
    }

    protected static function getPhpLabel()
    {
        $php = defined('PHP_VERSION') ? PHP_VERSION : '8.x';
        $jphp = defined('JPHP_VERSION') ? JPHP_VERSION : 'JPHP';

        return $php . '/' . $jphp;
    }

    protected static function getThemeLabel()
    {
        return 'Dark';
    }

    protected static function getIndexLabel()
    {
        if (static::$state === self::STATE_INDEXING) {
            return static::$indexPercent . '%';
        }

        if (static::$indexDone) {
            return 'Done';
        }

        if (static::$state === self::STATE_ERROR && !static::$indexDone) {
            return 'Error';
        }

        return '—';
    }

    /**
     * Публичный вход для uiLater/AccurateTimer.
     */
    public static function render()
    {
        if (!static::$primaryLabel || !static::$infoLabel) {
            return;
        }

        if (static::$state === self::STATE_INDEXING) {
            static::$primaryLabel->text = static::$indexMessage . ' ' . static::$indexPercent . '%';
            static::$primaryLabel->style = '-fx-text-fill: derive(-fx-text-base-color, 0%); -fx-font-weight: bold;';
        } elseif (static::$state === self::STATE_ERROR && !static::$lspConnected) {
            static::$primaryLabel->text = static::$indexMessage ?: 'LSP disconnected';
            static::$primaryLabel->style = '-fx-text-fill: #ef4444; -fx-font-weight: bold;';
        } elseif (static::$state === self::STATE_ERROR) {
            static::$primaryLabel->text = static::$indexMessage ?: 'Error';
            static::$primaryLabel->style = '-fx-text-fill: #ef4444; -fx-font-weight: bold;';
        } else {
            static::$primaryLabel->text = 'Ready';
            static::$primaryLabel->style = '-fx-text-fill: derive(-fx-text-base-color, 0%);';
        }

        $lsp = static::$lspStatus;

        if (!static::$lspConnected && FxeProcessSystem::isStarted()) {
            $lsp = 'Disconnected';
        } elseif (!FxeProcessSystem::isStarted()) {
            $lsp = 'Starting';
        }

        $parts = [
            'Project: ' . static::getProjectName(),
            'PHP: ' . static::getPhpLabel(),
            'LSP: ' . $lsp,
            'Index: ' . static::getIndexLabel(),
            'Theme: ' . static::getThemeLabel(),
        ];

        static::$infoLabel->text = implode('   |   ', $parts);

        if (!static::$lspConnected && FxeProcessSystem::isStarted() && static::$state !== self::STATE_INDEXING) {
            static::$infoLabel->style = '-fx-text-fill: #f87171;';
        } else {
            static::$infoLabel->style = '';
            static::$infoLabel->classes->add('fxe-status-muted');
        }
    }
}
