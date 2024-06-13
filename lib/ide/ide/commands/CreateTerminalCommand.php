<?php
namespace ide\commands;

use ide\editors\AbstractEditor;
use ide\editors\menu\AbstractMenuCommand;
use ide\systems\FileSystem;

class CreateTerminalCommand extends AbstractMenuCommand
{
    public function getName() {
        return _("Терминал");
    }

    public function getIcon() {
        return "icons/applicationRemote_dark.png";
    }

    public function getCategory() {
        return 'run';
    }

    public function isAlways() {
        return true;
    }

    public function onExecute($e = null, AbstractEditor $editor = null) {
        static $pty = -1; $pty++;

        FileSystem::open("pty://{$pty}");
    }
}