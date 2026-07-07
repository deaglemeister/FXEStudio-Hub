<?php
namespace ide\systems;

use ide\Logger;
use php\lang\Process;
use php\lang\System;
use php\lang\Thread;
use php\lib\fs;

/**
 * Helper-процессы FXE Studio.
 * Запуск в фоне; ошибки только в лог (не в UI / не из closure).
 */
class FxeProcessSystem
{
    const RUNNER = 'fxe-runner.jar';
    const INDEXER = 'fxe-indexer.jar';
    const BUILDER = 'fxe-builder.jar';
    const ANALYZER = 'fxe-analyzer.jar';
    const LANGUAGE_SERVER = 'fxe-language-server.jar';

    /** @var bool */
    protected static $init = false;

    /** @var bool */
    protected static $started = false;

    /** @var string */
    protected static $helpersDir;

    /** @var Process[] */
    protected static $processes = [];

    /** @var string[] */
    protected static $helpers = [
        'lsp' => self::LANGUAGE_SERVER,
        'runner' => self::RUNNER,
        'indexer' => self::INDEXER,
        'builder' => self::BUILDER,
    ];

    public static function init()
    {
        if (static::$init) {
            return;
        }

        static::$init = true;
        static::$helpersDir = fs::abs('lib/helpers');
    }

    public static function startAll()
    {
        static::init();

        if (static::$started) {
            return;
        }

        static::$started = true;

        $thread = new Thread(function () {
            try {
                FxeProcessSystem::bootstrapHelpers();
            } catch (\Exception $e) {
                Logger::warn("[FXE][IDE] Helper bootstrap: " . $e->getMessage());
            }
        });
        $thread->setName('fxe-process-bootstrap');
        $thread->start();
    }

    /**
     * Публичный вход для Thread — в JPHP closure не видит protected-методы класса.
     */
    public static function bootstrapHelpers()
    {
        Logger::debug("[FXE][IDE] Starting helper processes ...");

        foreach (static::$helpers as $name => $jarName) {
            static::startHelper($name, $jarName);
        }

        Logger::info("[FXE][IDE] Helper bootstrap done, running: " . sizeof(static::$processes));
    }

    public static function stopAll()
    {
        foreach (static::$processes as $name => $process) {
            try {
                if ($process) {
                    $process->destroy(true);
                    Logger::debug("[FXE][IDE] Stopped helper: $name");
                }
            } catch (\Exception $e) {
                Logger::warn("[FXE][IDE] Stop helper $name: " . $e->getMessage());
            }
        }

        static::$processes = [];
        static::$started = false;
    }

    public static function startHelper($name, $jarName)
    {
        $jarPath = static::getHelperJar($jarName);

        if (!$jarPath) {
            static::logMissing($jarName);
            return;
        }

        try {
            $java = static::getJavaExecutable();
            $process = new Process([
                $java,
                '-Dfile.encoding=UTF-8',
                '-Dfxe.helper=' . $name,
                '-jar',
                $jarPath
            ], fs::parent($jarPath));

            $process = $process->start();

            static::$processes[$name] = $process;

            Logger::debug("[FXE][IDE] Started helper: $name ($jarName)");
        } catch (\Exception $e) {
            Logger::warn("[FXE][IDE] Cannot start $name: " . $e->getMessage());
        }
    }

    public static function getJavaExecutable()
    {
        $home = System::getProperty('java.home');
        $java = $home . '/bin/java';

        if (fs::isFile($java . '.exe')) {
            return $java . '.exe';
        }

        return $java;
    }

    public static function getHelperJar($jarName)
    {
        static::init();

        $path = static::$helpersDir . '/' . $jarName;

        return fs::isFile($path) ? $path : null;
    }

    public static function isAvailable($jarName)
    {
        return static::getHelperJar($jarName) !== null;
    }

    /**
     * @param string $name
     * @return Process|null
     */
    public static function getProcess($name)
    {
        return isset(static::$processes[$name]) ? static::$processes[$name] : null;
    }

    public static function restartHelper($name)
    {
        if (isset(static::$processes[$name])) {
            try {
                static::$processes[$name]->destroy(true);
            } catch (\Exception $e) {
                Logger::warn("[FXE][IDE] Restart destroy $name: " . $e->getMessage());
            }

            unset(static::$processes[$name]);
        }

        if (isset(static::$helpers[$name])) {
            static::startHelper($name, static::$helpers[$name]);
        }
    }

    public static function isStarted()
    {
        return static::$started;
    }

    public static function logMissing($jarName)
    {
        Logger::warn("[FXE][IDE] Helper not found: $jarName (lib/helpers/)");
    }
}
