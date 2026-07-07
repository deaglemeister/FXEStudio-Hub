<?php
namespace ide\systems;

use php\gui\UXButton;

/**
 * Взаимная блокировка Run/Stop и Build в titlebar.
 */
class FxeTitlebarRunBuildSystem
{
    /** @var bool */
    protected static $runActive = false;

    /** @var bool */
    protected static $buildActive = false;

    /** @var UXButton|null */
    protected static $runButton;

    /** @var UXButton|null */
    protected static $buildButton;

    /**
     * @param UXButton $button
     */
    public static function bindRunButton(UXButton $button)
    {
        static::$runButton = $button;
        static::sync();
    }

    /**
     * @param UXButton $button
     */
    public static function bindBuildButton(UXButton $button)
    {
        static::$buildButton = $button;
        static::sync();
    }

    /**
     * @param bool $active
     */
    public static function setRunActive($active)
    {
        static::$runActive = (bool) $active;
        static::sync();
    }

    /**
     * @param bool $active
     */
    public static function setBuildActive($active)
    {
        static::$buildActive = (bool) $active;
        static::sync();
    }

    /**
     * @return bool
     */
    public static function isRunActive()
    {
        return static::$runActive;
    }

    /**
     * @return bool
     */
    public static function isBuildActive()
    {
        return static::$buildActive;
    }

    protected static function sync()
    {
        if (static::$runButton) {
            static::$runButton->enabled = !static::$buildActive;
        }

        if (static::$buildButton) {
            static::$buildButton->enabled = !static::$runActive && !static::$buildActive;
        }
    }
}
