<?php
namespace ide\systems;

use ide\editors\CodeEditor;
use ide\file\FileSystem;
use php\gui\designer\UXPhpCodeArea;
use php\gui\event\UXKeyEvent;
use php\gui\layout\UXVBox;
use php\gui\paint\UXColor;
use php\gui\UXColorPicker;
use php\gui\UXPopupWindow;
use php\lib\fs;
use php\lib\str;

/**
 * Анализ кода: синтаксис (Java) + семантика (fxe-language-server IPC).
 */
class FxeCodeAnalysisSystem
{
    /** @var bool */
    protected static $init = false;

    /** @var int */
    protected static $analyzeToken = 0;

    public static function init()
    {
        if (static::$init) {
            return;
        }

        static::$init = true;
        FxeProcessSystem::init();
        FxeLanguageServerClient::register();
        FxeIndexingStatusSystem::init();

        waitAsync(1500, function () {
            FxeLanguageServerIpc::ensureReader();
            FxeLanguageServerClient::indexProjectIfReady();
        });
    }

    public static function attachEditor(CodeEditor $editor)
    {
        if ($editor->getMode() !== 'php') {
            return;
        }

        static::init();

        $editor->on('update', function () use ($editor) {
            static::onEditorContentChanged($editor);
        });

        $editor->getTextArea()->on('keyDown', function (UXKeyEvent $e) use ($editor) {
            if (($e->controlDown && $e->codeName === 'B') || $e->codeName === 'F12') {
                static::goToDefinition($editor);
                $e->consume();
            }
        });

        $editor->getTextArea()->on('colorPick', function () use ($editor) {
            static::pickColor($editor);
        });

        static::onEditorContentChanged($editor);
    }

    /**
     * Открывает UXColorPicker рядом с кареткой и заменяет hex-литерал под выделением на выбранный цвет.
     *
     * @param CodeEditor $editor
     */
    public static function pickColor(CodeEditor $editor)
    {
        $area = $editor->getTextArea();
        $hex = (string) $area->selectedText;

        if ($hex === '') {
            return;
        }

        $end = (int) $area->caretPosition;
        $range = ['start' => $end - str::length($hex), 'end' => $end];

        $initial = UXColor::of('#ffffff');

        try {
            $initial = UXColor::of($hex);
        } catch (\Throwable $e) {
        }

        $colorPicker = new UXColorPicker($initial);

        $container = new UXVBox([$colorPicker]);
        $container->padding = 4;

        $popup = new UXPopupWindow();
        $popup->layout = $container;
        $popup->autoHide = true;
        $popup->hideOnEscape = true;

        $colorPicker->on('action', function () use ($colorPicker, $area, &$range) {
            $newHex = static::normalizeHex($colorPicker->value ? $colorPicker->value->getWebValue() : null);

            if ($newHex === null) {
                return;
            }

            $area->replaceText($range['start'], $range['end'], $newHex);
            $range['end'] = $range['start'] + str::length($newHex);
        });

        $bounds = $editor->getCaretBounds();
        $x = $bounds ? $bounds['x'] : 0;
        $y = $bounds ? $bounds['y'] + $bounds['height'] : 0;

        $popup->show($area->form, $x, $y);

        uiLater(function () use ($colorPicker) {
            $colorPicker->showPopup();
        });
    }

    /**
     * @param string|null $webValue
     * @return string|null
     */
    protected static function normalizeHex($webValue)
    {
        $webValue = (string) $webValue;

        if ($webValue === '') {
            return null;
        }

        if (str::startsWith($webValue, '0x')) {
            $webValue = '#' . str::sub($webValue, 2);
        } elseif (!str::startsWith($webValue, '#')) {
            $webValue = '#' . $webValue;
        }

        if (str::length($webValue) === 9 && str::upper(str::sub($webValue, 7, 9)) === 'FF') {
            $webValue = str::sub($webValue, 0, 7);
        }

        return $webValue;
    }

    protected static function onEditorContentChanged(CodeEditor $editor)
    {
        $area = $editor->getTextArea();

        if (!($area instanceof UXPhpCodeArea)) {
            return;
        }

        if (!FxeProcessSystem::getProcess('lsp')) {
            return;
        }

        FxeLanguageServerClient::setCurrentFile($editor->getFile());

        $token = ++static::$analyzeToken;

        waitAsync(1500, function () use ($editor, $token) {
            if ($token !== static::$analyzeToken) {
                return;
            }

            FxeLanguageServerClient::analyzeEditor($editor);
        });
    }

    public static function goToDefinition(CodeEditor $editor)
    {
        if (!FxeProcessSystem::getProcess('lsp')) {
            return;
        }

        $area = $editor->getTextArea();
        FxeLanguageServerClient::setCurrentFile($editor->getFile());

        FxeLanguageServerClient::requestDefinition(
            $editor->getValue(),
            $area->caretLine,
            $area->caretOffset,
            function ($result) use ($editor, $area) {
                if (!$result) {
                    return;
                }

                $parts = explode(':', $result, 3);
                if (sizeof($parts) < 3) {
                    return;
                }

                $file = fs::normalize($parts[0]);
                $line = max(0, (int)$parts[1] - 1);
                $column = max(0, (int)$parts[2]);

                uiLater(function () use ($editor, $area, $file, $line, $column) {
                    $current = fs::normalize($editor->getFile());

                    if ($file === $current) {
                        $area->caretLine = $line;
                        $area->caretOffset = $column;
                        return;
                    }

                    $opened = FileSystem::open($file);
                    if ($opened instanceof CodeEditor) {
                        $openedArea = $opened->getTextArea();
                        $openedArea->caretLine = $line;
                        $openedArea->caretOffset = $column;
                    }
                });
            }
        );
    }
}
