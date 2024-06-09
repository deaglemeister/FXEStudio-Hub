<?php
namespace ide\commands;

use ide\editors\AbstractEditor;
use ide\misc\AbstractCommand;
use php\gui\UXDesktop;

class SettingsCommand extends AbstractCommand
{
    public function getName()
    {
        return _('menu.settings');
    }

    public function isAlways()
    {
        return true;
    }

    public function getCategory()
    {
        return 'help';
    }

    public function getIcon()
    {
        return 'icons/donat16.png';
    }

    public function onExecute($e = null, AbstractEditor $editor = null)
    {
        $desk = new UXDesktop();
        $desk->browse('https://www.donationalerts.com/r/fxedition');
    }
}