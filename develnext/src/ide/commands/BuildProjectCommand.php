<?php
namespace ide\commands;

use ide\editors\AbstractEditor;
use ide\forms\BuildProjectForm;
use ide\Ide;
use ide\misc\AbstractCommand;
use ide\systems\FxeTitlebarRunBuildSystem;
use ide\ui\FxeTitlebarIcons;
use php\gui\UXButton;
use php\lang\IllegalArgumentException;

class BuildProjectCommand extends AbstractCommand
{
    /** @var array */
    protected $buildTypes = [];

    /** @var UXButton|null */
    protected $buildButton;

    /** @var bool */
    protected static $buildSessionActive = false;

    public function getName()
    {
        return 'Собрать проект';
    }

    public function getIcon()
    {
        return FxeTitlebarIcons::BUILD;
    }

    public function getCategory()
    {
        return 'run';
    }

    public function isTitleBarVisible()
    {
        return true;
    }

    public function makeUiForHead()
    {
        $this->buildButton = FxeTitlebarIcons::makeButton('Собрать проект', [$this, 'onExecute']);
        $this->buildButton->id = 'fxeTitlebarBuild';
        $this->buildButton->graphic = FxeTitlebarIcons::graphic(FxeTitlebarIcons::BUILD);
        FxeTitlebarRunBuildSystem::bindBuildButton($this->buildButton);

        return $this->buildButton;
    }

    /**
     * @return bool
     */
    public static function isBuilding()
    {
        return static::$buildSessionActive || FxeTitlebarRunBuildSystem::isBuildActive();
    }

    public static function notifyBuildStarted()
    {
        static::$buildSessionActive = true;
        FxeTitlebarRunBuildSystem::setBuildActive(true);
    }

    public static function notifyBuildFinished()
    {
        if (!static::$buildSessionActive && !FxeTitlebarRunBuildSystem::isBuildActive()) {
            return;
        }

        static::$buildSessionActive = false;
        FxeTitlebarRunBuildSystem::setBuildActive(false);
    }

    /**
     * Останавливает запущенное приложение (если есть) и блокирует Run.
     *
     * @param callable $builder
     */
    public static function withTitlebarBuild(callable $builder)
    {
        if (static::isBuilding()) {
            return;
        }

        $start = function () use ($builder) {
            static::notifyBuildStarted();

            try {
                $builder();
            } catch (\Throwable $e) {
                static::notifyBuildFinished();
                throw $e;
            }
        };

        /** @var ExecuteProjectCommand|null $execute */
        $execute = Ide::get()->getRegisteredCommand(ExecuteProjectCommand::class);

        if ($execute && $execute->isRunning()) {
            $execute->onStopExecute(null, $start);

            return;
        }

        $start();
    }

    public function onExecute($e = null, AbstractEditor $editor = null)
    {
        if (static::isBuilding()) {
            return;
        }

        /** @var ExecuteProjectCommand|null $execute */
        $execute = Ide::get()->getRegisteredCommand(ExecuteProjectCommand::class);

        if ($execute && $execute->isRunning()) {
            $execute->onStopExecute(null, function () use ($e, $editor) {
                $this->onExecute($e, $editor);
            });

            return;
        }

        $dialog = new BuildProjectForm();
        $dialog->setBuildTypes($this->buildTypes);
        $dialog->showAndWait();
    }

    public function register($any)
    {
        if ($any instanceof \ide\build\AbstractBuildType) {
            $this->buildTypes[get_class($any)] = $any;
        } else {
            throw new IllegalArgumentException();
        }
    }
}
