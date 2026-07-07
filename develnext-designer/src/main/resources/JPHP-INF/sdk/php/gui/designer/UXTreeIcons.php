<?php
namespace php\gui\designer;

use php\gui\UXNode;

/**
 * IntelliJ-style SVG иконки для дерева проекта (tree/ui/tree).
 */
class UXTreeIcons
{
    /**
     * @param string $name имя SVG без суффикса _dark и расширения (php, module, uiForm, ...)
     * @return UXNode|null
     */
    public static function load($name)
    {
    }

    /**
     * @return UXNode
     */
    public static function folder()
    {
    }

    /**
     * @return UXNode
     */
    public static function folderOpen()
    {
    }

    /**
     * @param bool $dark
     */
    public static function setDarkTheme($dark)
    {
    }

    /**
     * Предзагрузка SVG на FX-потоке (не блокирует PHP).
     */
    public static function warmUp()
    {
    }
}
