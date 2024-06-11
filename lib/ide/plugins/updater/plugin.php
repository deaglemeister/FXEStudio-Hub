<?php

use platform\plugins\FXEPlugin;
use platform\plugins\traits\EditorEvents;
use ide\editors\AbstractEditor;
use php\time\Time;
use platform\facades\PluginManager;

return new class  extends FXEPlugin
{
    public function getName(): string
    {
        return 'Updater FXE Studio';
    }

    public function getDescription(): string
    {
        return 'Универсальный апдейтер для обновление студии';
    }

    public function getVersion(): float
    {
        return 1.0;
    }

    public function getAuthor(): string
    {
        return 'FXE Studio';
    }

    protected Time $__currentTime;

    public function __construct()
    {
        $this->__currentTime = Time::now();
        $plugin = new PluginManager();
        $plugin->checkUpdates();
    }

    use EditorEvents;

    public function handleRequestFocus(AbstractEditor $editor)
    {

    }

};

class UpdateManager
{
    public static function Updater()
    {
        $plugin = new PluginManager();
        $plugin->checkUpdates();
    }
}
