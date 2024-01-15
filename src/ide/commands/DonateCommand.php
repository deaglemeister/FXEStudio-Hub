<?php
namespace ide\commands;

use ide\editors\AbstractEditor;
use ide\misc\AbstractCommand;
use php\gui\UXApplication;
use php\gui\UXDesktop;
use ide\forms\SettingsForm;

class DonateCommand extends AbstractCommand
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
        return 'icons/settings16.png';
    }

    public function onExecute($e = null, AbstractEditor $editor = null)
    {
        UXApplication::runLater(function () use ($response) {
            $dialog = new SettingsForm();
            $dialog->tryShow(true);
        });
    }
}