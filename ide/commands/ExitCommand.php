<?php
namespace ide\commands;

use ide\editors\AbstractEditor;
use ide\forms\MainForm;
use ide\Ide;
use ide\misc\AbstractCommand;
use ide\systems\ProjectSystem;
use php\lang\System;

/**
 * Class ExitCommand
 * @package ide\commands
 */
class ExitCommand extends AbstractCommand
{
    public function getName()
    {
        return _('menu.exit');
    }

    public function onExecute($e = null, AbstractEditor $editor = null)
    {
        Ide::get()->getMainForm()->trigger('close', $e);
    }

    public function withBeforeSeparator()
    {
        return true;
    }

    public function isAlways()
    {
        return true;
    }
}