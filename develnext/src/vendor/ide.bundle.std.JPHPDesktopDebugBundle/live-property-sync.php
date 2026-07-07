<?php

use php\format\JsonProcessor;
use php\format\ProcessorException;
use php\gui\framework\Application;
use php\gui\framework\DataUtils;
use php\gui\UXNodeWrapper;
use php\io\Stream;
use php\lib\fs;
use php\lib\reflect;
use php\lib\str;
use timer\AccurateTimer;

if (!defined('DEVELNEXT_PROJECT_DEBUG')) {
    return;
}

class LivePropertyReceiver
{
    private static $lastMtime = 0;
    private static $lastSeq = 0;
    private static $timer;

    public static function start()
    {
        if (self::$timer) {
            return;
        }

        self::$timer = new AccurateTimer(80, function () {
            self::poll();
        });
        self::$timer->start();
    }

    private static function poll()
    {
        $file = './.debug/live-properties.json';

        if (!fs::isFile($file)) {
            return;
        }

        $mtime = fs::time($file);

        if ($mtime <= self::$lastMtime) {
            return;
        }

        self::$lastMtime = $mtime;

        try {
            $json = new JsonProcessor(JsonProcessor::DESERIALIZE_AS_ARRAYS);
            $payload = $json->parse(Stream::of($file));
        } catch (ProcessorException $e) {
            return;
        }

        if (!$payload || !is_array($payload['commands'] ?? null)) {
            return;
        }

        foreach ($payload['commands'] as $command) {
            $seq = (int) ($command['seq'] ?? 0);

            if ($seq <= self::$lastSeq) {
                continue;
            }

            self::apply($command);
            self::$lastSeq = $seq;
        }
    }

    private static function apply(array $command)
    {
        $kind = $command['kind'] ?? 'property';

        if ($kind === 'reload-events') {
            self::applyReloadEvents($command);
            return;
        }

        self::applyProperty($command);
    }

    private static function applyProperty(array $command)
    {
        $app = Application::get();

        if (!$app) {
            return;
        }

        $formName = $command['form'] ?? '';
        $nodeId = $command['nodeId'] ?? '';
        $property = $command['property'] ?? '';
        $value = $command['value'] ?? null;
        $type = $command['type'] ?? 'native';

        if (!$formName || !$nodeId || !$property) {
            return;
        }

        $form = self::resolveForm($app, $formName);

        if (!$form || !$form->layout) {
            return;
        }

        $node = $form->layout->lookup("#$nodeId");

        if (!$node) {
            $node = self::lookupNode($form, $nodeId);
        }

        if (!$node) {
            return;
        }

        self::applyToNode($node, $property, $value, $type);
    }

    private static function applyReloadEvents(array $command)
    {
        $app = Application::get();

        if (!$app) {
            return;
        }

        $formName = $command['form'] ?? '';
        $handlers = $command['handlers'] ?? [];

        if (!$formName || !$handlers) {
            return;
        }

        $form = self::resolveForm($app, $formName);

        if (!$form) {
            return;
        }

        $count = 0;

        foreach ($handlers as $handler) {
            if (self::bindHotHandler($form, $handler['event'] ?? '', $handler['code'] ?? '')) {
                $count++;
            }
        }

        if ($count) {
            echo "[LIVE] events: {$formName} ({$count})\n";
        }
    }

    private static function bindHotHandler($form, $event, $code)
    {
        $code = trim($code);

        if (!$event || !$code) {
            return false;
        }

        $parts = explode('.', $event);
        $eventName = array_pop($parts);
        $nodeId = implode('.', $parts);

        if ($nodeId) {
            $node = $form->{$nodeId};

            if (!$node && $form->layout) {
                $node = $form->layout->lookup("#$nodeId");
            }
        } else {
            $node = $form;
        }

        if (!$node) {
            return false;
        }

        $node->off($eventName, 'general');
        $node->off($eventName, 'live-hot');

        $handler = function ($e) use ($form, $code) {
            try {
                $__form = $form;
                eval($code);
                fxe_stdout_flush();
            } catch (\Throwable $ex) {
                fxe_debug_error($ex, $code);
            }
        };

        $node->on($eventName, $handler, 'live-hot');

        return true;
    }

    private static function lookupNode($form, $nodeId)
    {
        if (isset($form->{$nodeId})) {
            return $form->{$nodeId};
        }

        return null;
    }

    private static function resolveForm(Application $app, $formName)
    {
        try {
            $form = $app->getForm($formName);

            if ($form) {
                return $form;
            }
        } catch (\Exception $e) {
        }

        $main = $app->getMainForm();

        if ($main) {
            $short = reflect::shortName(get_class($main));

            if (str::equalsIgnoreCase($short, $formName)) {
                return $main;
            }
        }

        return null;
    }

    private static function applyToNode($node, $property, $value, $type)
    {
        switch ($type) {
            case 'virtual':
                $data = DataUtils::get($node);

                if ($data) {
                    $data->set($property, $value);
                    UXNodeWrapper::get($node)->applyData($data);
                } else {
                    $node->{$property} = $value;
                }
                break;

            case 'position':
                $node->position = self::toArray($value);
                break;

            case 'size':
                $node->size = self::toArray($value);
                break;

            default:
                $node->{$property} = $value;
        }
    }

    private static function toArray($value)
    {
        if (is_array($value)) {
            return $value;
        }

        return (array) $value;
    }
}

LivePropertyReceiver::start();
