<?php
namespace ide\systems;

use ide\forms\SplashForm;
use ide\Ide;
use ide\ui\Notifications;

/**
 * Менеджер прелоадера: даёт UI кадр на отрисовку, тяжёлую работу — в фон, UI — через uiLater.
 * Старые задачи не могут скрыть прелоader новой (task id).
 */
class LoadingManager
{
    /** @var int */
    protected static $currentId = 0;

    /** @var array */
    protected static $active = [];

    /**
     * @param object|null $form AbstractForm|MainForm
     * @param string $text
     * @param callable $work фоновый поток, без UI
     * @param callable|null $done UI-поток
     * @param callable|null $fail UI-поток
     */
    public static function run($form, $text, callable $work, callable $done = null, callable $fail = null)
    {
        $id = static::register($form, $text);

        uiLater(function () use ($form, $text, $id, $work, $done, $fail) {
            static::show($form, $text);

            waitAsync(50, function () use ($form, $id, $work, $done, $fail) {
                FxeAsyncManager::runSerial(null, $work, function ($result) use ($form, $id, $done) {
                    try {
                        if (!static::isCurrent($form, $id)) {
                            return;
                        }

                        if ($done) {
                            $done($result);
                        }
                    } finally {
                        if (static::isCurrent($form, $id)) {
                            static::finish($form, $id);
                        }
                    }
                }, function (\Throwable $e) use ($form, $id, $fail) {
                    try {
                        if (!static::isCurrent($form, $id)) {
                            return;
                        }

                        if ($fail) {
                            $fail($e);
                        } else {
                            Notifications::error('Ошибка', $e->getMessage());
                        }
                    } finally {
                        if (static::isCurrent($form, $id)) {
                            static::finish($form, $id);
                        }
                    }
                });
            });
        });
    }

    /**
     * Тяжёлая работа на UI-потоке (диалоги, дерево), но прелоader успевает отрисоваться.
     *
     * @param object|null $form
     * @param string $text
     * @param callable $uiWork UI-поток
     * @param callable|null $fail UI-поток
     */
    public static function runUi($form, $text, callable $uiWork, callable $fail = null)
    {
        $id = static::register($form, $text);

        uiLater(function () use ($form, $text, $id, $uiWork, $fail) {
            static::show($form, $text);

            waitAsync(50, function () use ($form, $id, $uiWork, $fail) {
                uiLater(function () use ($form, $id, $uiWork, $fail) {
                    if (!static::isCurrent($form, $id)) {
                        return;
                    }

                    try {
                        $uiWork();
                    } catch (\Throwable $e) {
                        if ($fail) {
                            $fail($e);
                        } else {
                            throw $e;
                        }
                    } finally {
                        if (static::isCurrent($form, $id)) {
                            static::finish($form, $id);
                        }
                    }
                });
            });
        });
    }

    public static function runMainForm($text, callable $work, callable $done = null, callable $fail = null)
    {
        static::run(Ide::get()->getMainForm(), $text, $work, $done, $fail);
    }

    public static function runMainFormUi($text, callable $uiWork, callable $fail = null)
    {
        static::runUi(Ide::get()->getMainForm(), $text, $uiWork, $fail);
    }

    /**
     * Цепочка UI -> фон (serial) -> UI с одним прелоадером.
     *
     * @param object|null $form
     * @param string $prepareLabel
     * @param callable $prepare UI, вернуть null/false чтобы отменить
     * @param string $workLabel
     * @param callable $work фон
     * @param string $finishLabel
     * @param callable $finish UI
     * @param callable|null $fail UI
     */
    public static function runChained($form, $prepareLabel, callable $prepare, $workLabel, callable $work, $finishLabel, callable $finish, callable $fail = null)
    {
        $id = static::register($form, $prepareLabel);

        uiLater(function () use ($form, $id, $prepareLabel, $prepare, $workLabel, $work, $finishLabel, $finish, $fail) {
            static::show($form, $prepareLabel);

            waitAsync(100, function () use ($form, $id, $prepare, $workLabel, $work, $finishLabel, $finish, $fail) {
                uiLater(function () use ($form, $id, $prepare, $workLabel, $work, $finishLabel, $finish, $fail) {
                    if (!static::isCurrent($form, $id)) {
                        return;
                    }

                    try {
                        $ctx = $prepare();

                        if ($ctx === null || $ctx === false) {
                            static::finish($form, $id);
                            return;
                        }

                        static::show($form, $workLabel);

                        waitAsync(100, function () use ($form, $id, $ctx, $work, $finishLabel, $finish, $fail) {
                            FxeAsyncManager::runSerial(null, function () use ($work, $ctx) {
                                return $work($ctx);
                            }, function ($result) use ($form, $id, $finishLabel, $finish, $fail) {
                                if (!static::isCurrent($form, $id)) {
                                    return;
                                }

                                static::show($form, $finishLabel);

                                waitAsync(50, function () use ($form, $id, $result, $finish, $fail) {
                                    uiLater(function () use ($form, $id, $result, $finish, $fail) {
                                        if (!static::isCurrent($form, $id)) {
                                            return;
                                        }

                                        try {
                                            $finish($result);
                                        } catch (\Throwable $e) {
                                            if ($fail) {
                                                $fail($e);
                                            } else {
                                                throw $e;
                                            }
                                        } finally {
                                            static::finish($form, $id);
                                        }
                                    });
                                });
                            }, function (\Throwable $e) use ($form, $id, $fail) {
                                static::finish($form, $id);

                                uiLater(function () use ($fail, $e) {
                                    if ($fail) {
                                        $fail($e);
                                    }
                                });
                            });
                        });
                    } catch (\Throwable $e) {
                        static::finish($form, $id);

                        if ($fail) {
                            $fail($e);
                        }
                    }
                });
            });
        });
    }

    public static function runMainFormChained($prepareLabel, callable $prepare, $workLabel, callable $work, $finishLabel, callable $finish, callable $fail = null)
    {
        static::runChained(Ide::get()->getMainForm(), $prepareLabel, $prepare, $workLabel, $work, $finishLabel, $finish, $fail);
    }

    /**
     * Произвольная цепочка UI/фон-этапов с одним прелоадером на всё.
     * Каждый этап получает результат предыдущего ($carry) и возвращает следующий.
     *
     * @param object|null $form
     * @param array $stages [ ['label' => string, 'work' => callable($carry), 'background' => bool, 'abortOnNull' => bool], ... ]
     * @param callable|null $fail UI, вызывается при исключении на любом этапе
     */
    public static function runStages($form, array $stages, callable $fail = null)
    {
        if (!$stages) {
            return;
        }

        $id = static::register($form, $stages[0]['label']);

        uiLater(function () use ($form, $id, $stages, $fail) {
            static::runStage($form, $id, $stages, 0, null, $fail);
        });
    }

    /**
     * @param object|null $form
     * @param array $stages
     * @param callable|null $fail
     */
    public static function runMainFormStages(array $stages, callable $fail = null)
    {
        static::runStages(Ide::get()->getMainForm(), $stages, $fail);
    }

    public static function runStage($form, $id, array $stages, $index, $carry, $fail)
    {
        if (!static::isCurrent($form, $id)) {
            return;
        }

        if ($index >= sizeof($stages)) {
            static::finish($form, $id);
            return;
        }

        $stage = $stages[$index];
        static::show($form, $stage['label']);

        waitAsync(50, function () use ($form, $id, $stages, $index, $carry, $fail, $stage) {
            if (!static::isCurrent($form, $id)) {
                return;
            }

            $onError = function (\Throwable $e) use ($form, $id, $fail) {
                static::finish($form, $id);

                if ($fail) {
                    uiLater(function () use ($fail, $e) {
                        $fail($e);
                    });
                }
            };

            $onStageDone = function ($result) use ($form, $id, $stages, $index, $fail, $stage) {
                if (!empty($stage['abortOnNull']) && $result === null) {
                    static::finish($form, $id);
                    return;
                }

                $nextIndex = $index + 1;

                if ($nextIndex >= sizeof($stages)) {
                    uiLater(function () use ($form, $id) {
                        static::finish($form, $id);
                    });
                    return;
                }

                static::runStage($form, $id, $stages, $nextIndex, $result, $fail);
            };

            if (!empty($stage['background'])) {
                FxeAsyncManager::runSerial(null, function () use ($stage, $carry) {
                    return $stage['work']($carry);
                }, $onStageDone, $onError);
            } else {
                uiLater(function () use ($form, $id, $carry, $stage, $onStageDone, $onError) {
                    if (!static::isCurrent($form, $id)) {
                        return;
                    }

                    try {
                        $onStageDone($stage['work']($carry));
                    } catch (\Throwable $e) {
                        $onError($e);
                    }
                });
            }
        });
    }

    /**
     * @param object|null $form
     */
    public static function hide($form)
    {
        $key = static::key($form);

        if (isset(static::$active[$key])) {
            static::finish($form, static::$active[$key]['id']);
            return;
        }

        static::hidePreloader($form);
    }

    /**
     * @param object|null $form
     * @return bool
     */
    public static function isActive($form)
    {
        return isset(static::$active[static::key($form)]);
    }

    /**
     * @param object|null $form
     * @param string $text
     * @return int
     */
    protected static function register($form, $text)
    {
        $id = ++static::$currentId;
        static::$active[static::key($form)] = ['id' => $id, 'text' => $text];

        return $id;
    }

    /**
     * @param object|null $form
     * @param int $id
     */
    public static function finish($form, $id)
    {
        if (!static::isCurrent($form, $id)) {
            return;
        }

        unset(static::$active[static::key($form)]);

        if ($form instanceof SplashForm) {
            $form->hide();

            $mainForm = Ide::get()->getMainForm();

            if ($mainForm && Ide::get()->getConfig()->getBoolean('app.showMainForm') && !$mainForm->visible) {
                $mainForm->show();
                $mainForm->toFront();
            }

            return;
        }

        static::hidePreloader($form);
    }

    /**
     * @param object|null $form
     * @param int $id
     * @return bool
     */
    public static function isCurrent($form, $id)
    {
        $key = static::key($form);

        return isset(static::$active[$key]) && static::$active[$key]['id'] === $id;
    }

    /**
     * @param object|null $form
     * @param string $text
     */
    public static function show($form, $text)
    {
        if ($form instanceof SplashForm) {
            $form->setLoadingText($text);

            return;
        }

        if ($form && method_exists($form, 'showPreloader')) {
            $form->showPreloader($text);
        }
    }

    /**
     * @param object|null $form
     */
    public static function hidePreloader($form)
    {
        if ($form instanceof SplashForm) {
            return;
        }

        if ($form && method_exists($form, 'hidePreloader')) {
            $form->hidePreloader();
        }

        uiLater(function () {
            \ide\ui\FxeToast::flushPending();
        });
    }

    /**
     * @param object|null $form
     * @return string
     */
    protected static function key($form)
    {
        return is_object($form) ? spl_object_hash($form) : 'global';
    }
}
