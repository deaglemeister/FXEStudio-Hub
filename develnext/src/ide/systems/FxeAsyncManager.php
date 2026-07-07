<?php
namespace ide\systems;

use ide\Ide;
use ide\Logger;
use php\lang\ThreadPool;

/**
 * Единый менеджер фоновых задач IDE: один serial-поток для тяжёлых операций
 * и пул воркеров для параллельной работы. Результат всегда возвращается в UI через uiLater.
 */
class FxeAsyncManager
{
    /** @var ThreadPool|null */
    protected static $serialPool;

    /** @var ThreadPool|null */
    protected static $workerPool;

    /** @var bool */
    protected static $init = false;

    public static function init()
    {
        if (static::$init) {
            return;
        }

        static::$init = true;
        static::$serialPool = ThreadPool::createSingle();
        static::$workerPool = ThreadPool::createFixed(2);
    }

    /**
     * Очередь из одного потока: открытие/закрытие проекта, сборка, остановка.
     *
     * @param string|null $label
     * @param callable $background
     * @param callable|null $onUi
     * @param callable|null $onError
     */
    public static function runSerial($label, callable $background, $onUi = null, $onError = null)
    {
        static::init();
        static::run(static::$serialPool, $label, $background, $onUi, $onError);
    }

    /**
     * Параллельные фоновые задачи (индексация, IO, старт IDE).
     *
     * @param string|null $label
     * @param callable $background
     * @param callable|null $onUi
     * @param callable|null $onError
     */
    public static function runParallel($label, callable $background, $onUi = null, $onError = null)
    {
        static::init();
        static::run(static::$workerPool, $label, $background, $onUi, $onError);
    }

    /**
     * Отложить выполнение на UI-потоке (аналог uiLater с меткой для сплеша).
     *
     * @param callable $callback
     * @param string|null $label
     */
    public static function runUi(callable $callback, $label = null)
    {
        if ($label) {
            Ide::notifySplashLoad($label);
        }

        uiLater($callback);
    }

    /**
     * @param ThreadPool $pool
     * @param string|null $label
     * @param callable $background
     * @param callable|null $onUi
     * @param callable|null $onError
     */
    protected static function run($pool, $label, callable $background, $onUi = null, $onError = null)
    {
        static::init();

        if ($label) {
            Ide::notifySplashLoad($label);
        }

        if (!$pool || $pool->isShutdown() || $pool->isTerminated()) {
            return;
        }

        $pool->execute(function () use ($background, $onUi, $onError, $label) {
            try {
                $result = $background();

                if ($onUi) {
                    uiLater(function () use ($onUi, $result) {
                        $onUi($result);
                    });
                }
            } catch (\Throwable $e) {
                Logger::exception($e->getMessage(), $e);

                if ($onError) {
                    uiLater(function () use ($onError, $e) {
                        $onError($e);
                    });
                }
            }
        });
    }

    public static function shutdown()
    {
        if (static::$serialPool) {
            static::$serialPool->shutdown();
        }

        if (static::$workerPool) {
            static::$workerPool->shutdown();
        }

        static::$init = false;
        static::$serialPool = null;
        static::$workerPool = null;
    }
}
