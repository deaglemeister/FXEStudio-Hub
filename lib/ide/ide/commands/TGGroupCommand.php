<?php
namespace ide\commands;

use ide\editors\AbstractEditor;
use ide\misc\AbstractCommand;
use php\gui\UXDesktop;

class TGGroupCommand extends AbstractCommand
{
    public function getName()
    {
        return _('menu.help.tg');
    }

    public function isAlways()
    {
        return true;
    }

    public function getCategory()
    {
        return '';
    }

    public function getIcon()
    {
        return 'icons/tg16.png';
    }

    public function onExecute($e = null, AbstractEditor $editor = null)
    {
        $desk = new UXDesktop();
        $desk->browse('https://t.me/fxedition17');
    }
}