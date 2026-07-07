<?php
namespace ide\ui;

use ide\project\TreeIconResolver;
use php\gui\UXButton;

/**
 * IntelliJ-style иконки titlebar (Run / Stop / Build) — тот же загрузчик, что у дерева.
 */
class FxeTitlebarIcons
{
    const RUN = 'threadRunning';
    const STOP = 'stop';
    const BUILD = 'build';
    const NEW_TAB = 'openNewTab';

    /**
     * @param string $name имя IntelliJ-иконки (threadRunning, stop, build, openNewTab)
     * @param array|null $size не используется — размер фиксирован Java-лоадером (16px)
     * @return \php\gui\UXNode|null
     */
    public static function graphic($name, $size = [18, 18])
    {
        $treeName = static::resolveTreeIconName($name);
        if (!$treeName) {
            return null;
        }

        $node = TreeIconResolver::loadIcon($treeName);
        if ($node instanceof \php\gui\UXImageView && $size) {
            $node->fitWidth = $size[0];
            $node->fitHeight = $size[1];
            $node->preserveRatio = true;
        }

        return $node;
    }

    /**
     * @param string $icon
     * @return string|null
     */
    protected static function resolveTreeIconName($icon)
    {
        static $map = [
            self::RUN => 'threadRunning',
            self::STOP => 'stop',
            self::BUILD => 'build',
            self::NEW_TAB => 'openNewTab',
            'titlebar/threadRunning_dark.svg' => 'threadRunning',
            'titlebar/stop_dark.svg' => 'stop',
            'titlebar/build_dark.svg' => 'build',
            'titlebar/openNewTab_dark.svg' => 'openNewTab',
        ];

        if (isset($map[$icon])) {
            return $map[$icon];
        }

        if (strpos($icon, '/') === false && strpos($icon, '.') === false) {
            return $icon;
        }

        return null;
    }

    /**
     * @param string $tooltip
     * @param callable $action
     * @return UXButton
     */
    public static function makeButton($tooltip, callable $action)
    {
        $button = new UXButton();
        $button->classes->add('fxe-titlebar-tool-btn');
        $button->tooltipText = $tooltip;
        $button->padding = [3, 3];
        $button->minWidth = 28;
        $button->prefWidth = 28;
        $button->maxWidth = 28;
        $button->minHeight = 28;
        $button->prefHeight = 28;
        $button->maxHeight = 28;
        $button->on('action', $action);

        return $button;
    }
}
