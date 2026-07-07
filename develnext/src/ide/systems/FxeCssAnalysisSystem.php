<?php
namespace ide\systems;

use ide\editors\CodeEditor;
use php\gui\designer\UXAbstractCodeArea;
use php\gui\layout\UXVBox;
use php\gui\paint\UXColor;
use php\gui\UXColorPicker;
use php\gui\UXPopupWindow;
use php\lib\str;

/**
 * Анализ и color picker для CSS / FX CSS редакторов.
 */
class FxeCssAnalysisSystem
{
    public static function attachEditor(CodeEditor $editor)
    {
        $mode = $editor->getMode();

        if ($mode !== 'fxcss' && $mode !== 'css') {
            return;
        }

        if (!($editor->getTextArea() instanceof UXAbstractCodeArea)) {
            return;
        }

        $editor->getTextArea()->on('colorPick', function () use ($editor) {
            uiLater(function () use ($editor) {
                static::pickColor($editor);
            });
        });
    }

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

        $colorPicker->on('action', function () use ($colorPicker, $area, &$range, $popup) {
            $newHex = static::normalizeHex($colorPicker->value ? $colorPicker->value->getWebValue() : null);

            if ($newHex === null) {
                return;
            }

            $area->replaceText($range['start'], $range['end'], $newHex);
            $range['end'] = $range['start'] + str::length($newHex);
            $popup->hide();
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
}
