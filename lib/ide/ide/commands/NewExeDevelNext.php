<?php
namespace ide\commands;

use ide\editors\AbstractEditor;
use ide\forms\NewProjectForm;
use ide\misc\AbstractCommand;

/**
 * Class NewProjectCommand
 * @package ide\commands
 */
class NewExeDevelNext extends AbstractCommand
{
    public function getName()
    {
        return ('Открыть новое окно');
    }

    public function getIcon()
    {
       
    }

    public function getAccelerator()
    {
        return 'Ctrl + Alt + T';
    }

    public function isAlways()
    {
        return true;
    }

    public function makeUiForHead()
    {
       # return $this->makeGlyphButton();
    }

    public function onExecute($e = null, AbstractEditor $editor = null)
    {
        Execute("DevelNext.exe");
    }
}