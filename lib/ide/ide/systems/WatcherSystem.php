<?php
namespace ide\systems;

use ide\Ide;
use ide\Logger;
use ide\project\ProjectFile;
use ide\utils\FileUtils;
use php\gui\designer\FileSystemWatcher;
use php\gui\UXApplication;
use php\io\File;
use php\io\IOException;
use php\lang\IllegalStateException;
use php\lang\InterruptedException;
use php\lang\Thread;
use php\lib\str;

class WatcherSystem
{
    /** @var FileSystemWatcher[] */
    protected static $watchers = [];

    /** @var Thread[] */
    protected static $threads = [];
    
    /** @var callable[] */
    protected static $handlers = [];
    
    protected static $enabled = true;
    
    static function addListener(callable $handler)
    {
        static::$handlers[] = $handler;
    }
    static function trigger($event)
{
    if (static::$enabled) {
        UXApplication::runLater(function () use ($event) {
            $project = Ide::get()->getOpenedProject();

            $file = $project ? new ProjectFile($project, $event['context']) : null;

            foreach (static::$handlers as &$handler) {
                $handler($file, $event);
            }
        });
    }
}
static function addPath(string $path, bool $appendCreatedPath = false)
{
    Logger::info("Add path $path");

    if (isset(static::$watchers[$path])) {
        return false;
    }

    try {
        $watcher = new FileSystemWatcher($path);
    } catch (IOException $e) {
        return false;
    }

    static::$watchers[$path] = $watcher;

    $thread = new Thread(function () use ($watcher, $path, $appendCreatedPath) {
        while (true) {
            try {
                $key = $watcher->take();

                $events = $key->pollEvents();

                foreach ($events as $event) {
                    static::trigger($event);

                    if ($appendCreatedPath && File::of($event['context'])->isDirectory()) {
                        switch ($event['kind']) {
                            case 'ENTRY_CREATE':
                                static::addPath($event['context']);
                                break;

                            case 'ENTRY_DELETE':
                                static::removePath($event['context']);
                                break;
                        }
                    }
                }

                if (!$key->reset()) {
                    break;
                }
            } catch (InterruptedException $e) {
                break;
            } catch (IllegalStateException $e) {
                break;
            }
        }

        static::removePath($path, false);
    });
    $thread->setName("WatcherPath[$path] #" . str::random());

    static::$threads[$path] = $thread;

    $thread->start();

    return true;
}
static function shutdown()
{
    Logger::info("Start shutdown ...");

    foreach (static::$watchers as $watcher) {
        $watcher->close();
    }

    static::$watchers = [];
    static::$threads = [];

    Logger::info("Finish shutdown.");
}
}