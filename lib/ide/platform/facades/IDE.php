<?php
namespace platform\facades;

use platform\plugins\traits\Actions;

use ide\Ide as IdeIde;

class IDE
{
    public static function shutdown()
    {
        IdeIde::get()->getMainForm()->trigger('close', null);
    }

    public static function executeAction(string $actionClass)
    {
        PluginManager::forTrait(Actions::class, function($plugin) use ($actionClass) {
            $action = $plugin->getAction($actionClass);
            $action->onExecute();
        });
    }
}