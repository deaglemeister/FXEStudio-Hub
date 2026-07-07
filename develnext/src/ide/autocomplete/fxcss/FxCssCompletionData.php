<?php
namespace ide\autocomplete\fxcss;

/**
 * Данные автодополнения JavaFX CSS.
 */
class FxCssCompletionData
{
    public static function selectors()
    {
        return [
            '.root', '.button', '.label', '.text-field', '.text-area', '.password-field',
            '.check-box', '.radio-button', '.toggle-button', '.choice-box', '.combo-box',
            '.list-view', '.tree-view', '.table-view', '.tab-pane', '.tab', '.menu-bar',
            '.menu', '.menu-item', '.context-menu', '.scroll-pane', '.scroll-bar',
            '.slider', '.progress-bar', '.tooltip', '.split-pane', '.separator',
            '.hyperlink', '.accordion', '.titled-pane', '.spinner', '.date-picker',
            '.color-picker', '.list-cell', '.tree-cell', '.table-row-cell', '.table-cell',
            '.tab-header-area', '.tab-content-area', '.dialog-pane', '.alert',
        ];
    }

    public static function properties()
    {
        return [
            '-fx-background-color', '-fx-background-image', '-fx-background-insets',
            '-fx-background-radius', '-fx-border-color', '-fx-border-width',
            '-fx-border-radius', '-fx-border-style', '-fx-padding', '-fx-font-size',
            '-fx-font-family', '-fx-font-weight', '-fx-font-style', '-fx-text-fill',
            '-fx-text-alignment', '-fx-opacity', '-fx-cursor', '-fx-effect',
            '-fx-min-width', '-fx-min-height', '-fx-max-width', '-fx-max-height',
            '-fx-pref-width', '-fx-pref-height', '-fx-alignment', '-fx-spacing',
            '-fx-wrap-text', '-fx-underline', '-fx-fill', '-fx-stroke',
            '-fx-highlight-fill', '-fx-highlight-text-fill', '-fx-focus-color',
            '-fx-control-inner-background', '-fx-prompt-text-fill',
        ];
    }

    public static function colorValues()
    {
        return [
            'transparent', 'white', 'black', 'red', 'green', 'blue', 'yellow',
            'silver', 'gray', '#ffffff', '#000000', '#1e1e1e', '#252526',
        ];
    }
}
