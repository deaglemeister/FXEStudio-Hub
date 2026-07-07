<?php
namespace ide\systems;

use ide\autocomplete\AutoCompleteItem;
use ide\autocomplete\MethodAutoCompleteItem;
use ide\autocomplete\PropertyAutoCompleteItem;
use ide\autocomplete\VariableAutoCompleteItem;
use ide\editors\CodeEditor;
use ide\Ide;
use ide\Logger;
use php\gui\designer\UXPhpCodeArea;
use php\lib\fs;
use php\lib\str;

/**
 * Клиент fxe-language-server: index, diagnostics, completion, definition.
 */
class FxeLanguageServerClient
{
    const HELPER = FxeProcessSystem::LANGUAGE_SERVER;

    /** @var string|null */
    protected static $indexedProject;

    /** @var bool */
    protected static $registered = false;

    /** @var string */
    protected static $currentFile = 'source.php';

    /** @var int */
    protected static $diagToken = 0;

    /** @var bool */
    protected static $indexInFlight = false;

    /** @var int */
    protected static $indexGeneration = 0;

    public static function init()
    {
        FxeProcessSystem::init();
    }

    public static function register()
    {
        if (static::$registered) {
            return;
        }

        static::$registered = true;
        static::init();
        FxeIndexingStatusSystem::init();

        Ide::get()->bind('openProject', function () {
            static::$indexedProject = null;
            static::indexProjectIfReady(0);
        });
    }

    public static function setCurrentFile($file)
    {
        if ($file) {
            static::$currentFile = fs::normalize($file);
        }
    }

    public static function indexProjectIfReady($retry = 0)
    {
        try {
            static::init();

            if (!FxeProcessSystem::getProcess('lsp')) {
                if ($retry < 40) {
                    waitAsync(250, function () use ($retry) {
                        static::indexProjectIfReady($retry + 1);
                    });
                } else {
                    Logger::warn('[FXE][LSP] Helper process not available');
                    FxeIndexingStatusSystem::onIndexDone(false);
                }

                return;
            }

            $project = Ide::project();
            if (!$project) {
                return;
            }

            $root = fs::normalize($project->getRootDir());
            if (!$root || static::$indexedProject === $root) {
                return;
            }

            static::startIndexForRoot($root, 0);
        } catch (\Exception $e) {
            Logger::warn('[FXE][LSP] index: ' . $e->getMessage());
            static::$indexInFlight = false;
            static::$indexedProject = null;
            FxeIndexingStatusSystem::onIndexDone(false);
        }
    }

    /**
     * @param string $root
     * @param int $attempt
     */
    protected static function startIndexForRoot($root, $attempt = 0)
    {
        $generation = ++static::$indexGeneration;

        static::$indexedProject = $root;
        static::$indexInFlight = true;
        Logger::info("[FXE][LSP] Index project: $root (attempt " . ($attempt + 1) . ')');

        FxeIndexingStatusSystem::onIndexStart();
        FxeLanguageServerIpc::ensureReader();

        waitAsync(45000, function () use ($root, $attempt, $generation) {
            if ($generation !== static::$indexGeneration || !static::$indexInFlight || static::$indexedProject !== $root) {
                return;
            }

            Logger::warn('[FXE][LSP] Index timeout for ' . $root);

            static::$indexInFlight = false;
            static::$indexedProject = null;

            if ($attempt < 2) {
                FxeProcessSystem::restartHelper('lsp');
                FxeLanguageServerIpc::resetReader();

                waitAsync(600, function () use ($root, $attempt) {
                    static::startIndexForRoot($root, $attempt + 1);
                });

                return;
            }

            FxeIndexingStatusSystem::onIndexDone(false);
        });

        FxeLanguageServerIpc::request('ping', [], function ($ok, $message) use ($root, $attempt, $generation) {
            if ($generation !== static::$indexGeneration) {
                return;
            }

            if (!$ok) {
                Logger::warn('[FXE][LSP] Ping failed before index');

                if ($attempt < 2) {
                    static::$indexInFlight = false;
                    static::$indexedProject = null;
                    FxeProcessSystem::restartHelper('lsp');
                    FxeLanguageServerIpc::resetReader();

                    waitAsync(600, function () use ($root, $attempt) {
                        static::startIndexForRoot($root, $attempt + 1);
                    });

                    return;
                }

                static::$indexInFlight = false;
                static::$indexedProject = null;
                FxeIndexingStatusSystem::onIndexDone(false);

                return;
            }

            FxeLanguageServerIpc::request('index', [
                'project' => $root
            ], function ($ok, $message) use ($root, $attempt, $generation) {
                if ($generation !== static::$indexGeneration || !static::$indexInFlight || static::$indexedProject !== $root) {
                    return;
                }

                static::$indexInFlight = false;

                if (!$ok && $attempt < 2) {
                    static::$indexedProject = null;
                    FxeProcessSystem::restartHelper('lsp');
                    FxeLanguageServerIpc::resetReader();

                    waitAsync(600, function () use ($root, $attempt) {
                        static::startIndexForRoot($root, $attempt + 1);
                    });

                    return;
                }

                FxeIndexingStatusSystem::onIndexDone($ok);

                if (!$ok) {
                    static::$indexedProject = null;
                    Logger::warn("[FXE][LSP] Index failed: $message");
                } else {
                    Logger::info("[FXE][LSP] Index done: $message");
                }
            });
        });
    }

    /**
     * @param CodeEditor $editor
     */
    public static function analyzeEditor(CodeEditor $editor)
    {
        if (!($editor->getTextArea() instanceof UXPhpCodeArea)) {
            return;
        }

        static::setCurrentFile($editor->getFile());

        $content = $editor->getValue();
        $file = static::$currentFile;
        $token = ++static::$diagToken;

        FxeLanguageServerIpc::request('diagnostics', [
            'content' => $content,
            'file' => $file
        ], function ($ok, $message) use ($editor, $token) {
            if (!$ok || $token !== static::$diagToken) {
                return;
            }

            $area = $editor->getTextArea();
            if ($area instanceof UXPhpCodeArea) {
                $area->applyLspDiagnostics($message);
            }
        });
    }

    /**
     * @param string $content
     * @param int $line 0-based caret line
     * @param int $column 0-based caret offset in line
     * @param callable $callback function(array $items)
     */
    public static function requestCompletions($content, $line, $column, callable $callback)
    {
        FxeLanguageServerIpc::request('complete', [
            'content' => $content,
            'file' => static::$currentFile,
            'line' => (string)($line + 1),
            'column' => (string)$column
        ], function ($ok, $message) use ($callback) {
            if (!$ok) {
                $callback([]);
                return;
            }
            $callback(static::parseCompletionItems($message));
        });
    }

    /**
     * @param string $payload
     * @return AutoCompleteItem[]
     */
    public static function parseCompletionItems($payload)
    {
        $result = [];

        if (!$payload) {
            return $result;
        }

        foreach (explode('|', $payload) as $part) {
            if ($part === '') {
                continue;
            }

            $fields = static::splitEscaped($part, ':', 4);
            if (sizeof($fields) < 4) {
                continue;
            }

            $label = static::unescape($fields[0]);
            $kind = static::unescape($fields[1]);
            $detail = static::unescape($fields[2]);
            $doc = static::unescape($fields[3]);

            switch ($kind) {
                case 'method':
                    $result[] = new MethodAutoCompleteItem($label, $doc, $label . '()', 'icons/method16.png');
                    break;
                case 'property':
                    $result[] = new PropertyAutoCompleteItem($label, $doc, '$' . $label, 'icons/field16.png');
                    break;
                case 'class':
                    $result[] = new AutoCompleteItem($label, $detail, $label, 'icons/class16.png');
                    break;
                default:
                    $result[] = new VariableAutoCompleteItem($label, $doc, '$' . $label);
                    break;
            }
        }

        return $result;
    }

    /**
     * @param array $items AutoCompleteItem[]
     * @param AutoCompleteItem[] $lspItems
     * @return AutoCompleteItem[]
     */
    public static function mergeCompletionItems(array $items, array $lspItems)
    {
        if (!$lspItems) {
            return $items;
        }

        $names = [];
        foreach ($items as $item) {
            $names[str::lower($item->getName())] = true;
        }

        foreach ($lspItems as $lspItem) {
            $key = str::lower($lspItem->getName());
            if (!isset($names[$key])) {
                $items[] = $lspItem;
                $names[$key] = true;
            }
        }

        return $items;
    }

    public static function requestDefinition($content, $line, $column, callable $callback)
    {
        FxeLanguageServerIpc::request('definition', [
            'content' => $content,
            'file' => static::$currentFile,
            'line' => (string)($line + 1),
            'column' => (string)$column
        ], function ($ok, $message) use ($callback) {
            $callback($ok ? $message : '');
        });
    }

    public static function isAvailable()
    {
        return FxeProcessSystem::isAvailable(static::HELPER);
    }

    protected static function splitEscaped($text, $delimiter, $maxParts = 0)
    {
        $parts = [];
        $current = '';
        $len = str::length($text);

        for ($i = 0; $i < $len; $i++) {
            $ch = str::sub($text, $i, $i + 1);

            if ($ch === '\\' && $i + 1 < $len) {
                $i++;
                $current .= str::sub($text, $i, $i + 1);
                continue;
            }

            if ($ch === $delimiter) {
                $parts[] = $current;
                $current = '';

                if ($maxParts > 0 && sizeof($parts) >= $maxParts - 1) {
                    $current = str::sub($text, $i + 1);
                    break;
                }

                continue;
            }

            $current .= $ch;
        }

        $parts[] = $current;

        return $parts;
    }

    protected static function unescape($text)
    {
        $out = '';
        $len = str::length($text);

        for ($i = 0; $i < $len; $i++) {
            $ch = str::sub($text, $i, 1);
            if ($ch === '\\' && $i + 1 < $len) {
                $i++;
                $out .= str::sub($text, $i, 1);
            } else {
                $out .= $ch;
            }
        }

        return $out;
    }
}
