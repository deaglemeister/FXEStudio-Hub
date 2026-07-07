<?php
namespace ide\commands;

use ide\editors\AbstractEditor;
use ide\misc\AbstractCommand;
use ide\systems\FileSystem;
use ide\systems\LoadingManager;
use ide\systems\ProjectSystem;

/**
 * Class CloseProjectCommand
 * @package ide\commands
 */
class CloseProjectCommand extends AbstractProjectCommand
{
    public function getName()
    {
        return _('menu.project.close');
    }

    public function onExecute($e = null, AbstractEditor $editor = null)
    {
        LoadingManager::runMainFormUi('Сохранение и закрытие проекта ...', function () {
            ProjectSystem::close(true);
            FileSystem::open('~welcome');
        });
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