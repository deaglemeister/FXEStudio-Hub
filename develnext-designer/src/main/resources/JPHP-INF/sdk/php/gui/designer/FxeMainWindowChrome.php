<?php
namespace php\gui\designer;

use php\gui\UXForm;
use php\gui\UXNode;

/**
 * Прямой Java entry-point для MainForm titlebar.
 */
class FxeMainWindowChrome
{
    /**
     * @param UXForm $form
     * @param UXNode|null $titleBarRow
     */
    public static function apply(UXForm $form, UXNode $titleBarRow = null)
    {
    }

    /**
     * @param UXForm $form
     */
    public static function refreshHitTest(UXForm $form)
    {
    }

    /**
     * Тёмный нативный titlebar для диалогов (Windows).
     * @param UXForm $form
     */
    public static function applyDialog(UXForm $form)
    {
    }
}
