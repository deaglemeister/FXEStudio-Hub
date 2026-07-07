<?php
namespace ide\systems;

use ide\action\ActionManager;
use ide\action\ActionScript;
use ide\commands\ExecuteProjectCommand;
use ide\editors\FormEditor;
use ide\editors\value\ElementPropertyEditor;
use ide\Ide;
use ide\project\Project;
use ide\utils\FileUtils;
use ide\utils\Json;
use ide\utils\PhpParser;
use php\gui\paint\UXColor;
use php\gui\UXNode;
use php\lib\fs;
use php\lib\str;
use php\time\Time;
use php\util\Scanner;

/**
 * Live-синхронизация свойств UI и обработчиков событий в запущенное dev-приложение.
 */
class LivePropertySync
{
    const SYNC_REL_PATH = '.debug/live-properties.json';

    protected static $enabled = true;
    protected static $seq = 0;
    protected static $commands = [];
    protected static $flushScheduled = false;

    protected static $syncProperties = [
        'text', 'visible', 'enabled', 'tooltipText', 'position', 'size',
        'font', 'textColor', 'fillColor', 'strokeColor', 'style', 'opacity',
    ];

    public static function isEnabled()
    {
        return static::$enabled && Ide::get()->getUserConfigValue('ide.livePropertySync', true);
    }

    public static function isProjectRunning()
    {
        $cmd = Ide::get()->getRegisteredCommand(ExecuteProjectCommand::class);

        return $cmd && $cmd->isRunning();
    }

    public static function reset(Project $project)
    {
        static::$seq = 0;
        static::$commands = [];
        static::$flushScheduled = false;

        $file = $project->getFile(static::SYNC_REL_PATH);
        $dir = fs::parent($file);

        if (!fs::isDir($dir)) {
            fs::makeDir($dir);
        }

        Json::toFile($file, ['seq' => 0, 'commands' => []]);
    }

    public static function onPropertyChanged(ElementPropertyEditor $editor, $value)
    {
        if (!static::isEnabled() || !static::isProjectRunning()) {
            return;
        }

        if (!\in_array($editor->code, static::$syncProperties, true)) {
            return;
        }

        $formEditor = FileSystem::getSelectedEditor();

        if (!$formEditor instanceof FormEditor) {
            return;
        }

        $target = $editor->designProperties->target;

        if (!$target instanceof UXNode) {
            return;
        }

        static::pushProperty(
            $formEditor,
            $target,
            $editor->code,
            $value,
            static::resolvePropertyType($editor->code)
        );
    }

    public static function onPropertyChangedForNode(FormEditor $formEditor, UXNode $node, ElementPropertyEditor $editor, $value)
    {
        if (!static::isEnabled() || !static::isProjectRunning()) {
            return;
        }

        if (!\in_array($editor->code, static::$syncProperties, true)) {
            return;
        }

        static::pushProperty(
            $formEditor,
            $node,
            $editor->code,
            $value,
            static::resolvePropertyType($editor->code)
        );
    }

    public static function onDesignerChanged(FormEditor $editor)
    {
        if (!static::isEnabled() || !static::isProjectRunning()) {
            return;
        }

        $node = $editor->getDesigner()->pickedNode;

        if (!$node || !$editor->getNodeId($node)) {
            return;
        }

        static::pushProperty($editor, $node, 'position', $node->position, 'position');
        static::pushProperty($editor, $node, 'size', $node->size, 'size');
    }

    public static function onFormSaved(FormEditor $editor)
    {
        if (!static::isEnabled() || !static::isProjectRunning()) {
            return;
        }

        $project = Ide::get()->getOpenedProject();

        if (!$project) {
            return;
        }

        static::compileFormActions($editor, $project);

        $payload = static::buildEventHandlers($editor);

        if (!$payload['handlers']) {
            return;
        }

        static::pushReloadEvents($project, $payload);
    }

    protected static function compileFormActions(FormEditor $editor, Project $project)
    {
        $phpFile = $editor->getFile();
        $axmlFile = $phpFile . '.axml';

        if (!fs::isFile($axmlFile)) {
            return;
        }

        $script = new ActionScript(null, ActionManager::get());
        $script->load($axmlFile);

        if ($script->isEmpty()) {
            return;
        }

        $srcDir = $project->getSrcFile('');
        $outDir = $project->getSrcFile('', true);
        $outFile = $outDir . '/' . FileUtils::relativePath($srcDir, $phpFile);

        fs::ensureParent($outFile);

        if (!fs::exists($outFile)) {
            FileUtils::copyFile($phpFile, $outFile);
        }

        $script->compile($outFile, null, true);
    }

    protected static function buildEventHandlers(FormEditor $editor)
    {
        $phpFile = $editor->getFile();
        $className = fs::nameNoExt($phpFile);
        $formName = $className;
        $parser = PhpParser::ofFile($phpFile);
        $eventMethods = static::parseEventMethods($parser->getContent());
        $handlers = [];

        foreach ($eventMethods as $methodName => $eventId) {
            $code = '';

            $actions = $editor->getActionEditor()->findMethod($className, $methodName);

            if ($actions) {
                $script = new ActionScript(null, ActionManager::get());
                $code = $script->compileActions($className, $methodName, $actions);
            }

            if (!str::trim($code)) {
                $code = $parser->getCodeOfMethod($className, $methodName);
            }

            if (!str::trim($code)) {
                continue;
            }

            $handlers[] = [
                'event' => $eventId,
                'code' => str::replace($code, '$this', '$__form'),
            ];
        }

        return [
            'form' => $formName,
            'handlers' => $handlers,
        ];
    }

    protected static function parseEventMethods($content)
    {
        $events = [];
        $pendingEvent = null;
        $scanner = new Scanner($content);

        while ($scanner->hasNextLine()) {
            $line = str::trim($scanner->nextLine());

            if (str::startsWith($line, '@event ')) {
                $pendingEvent = str::trim(str::sub($line, 7));
                continue;
            }

            if ($pendingEvent && str::startsWith($line, 'function ')) {
                $parts = str::split($line, ' ', 3);
                $methodName = isset($parts[1]) ? str::trim($parts[1]) : '';

                if (str::endsWith($methodName, '(')) {
                    $methodName = str::sub($methodName, 0, -1);
                }

                if ($methodName) {
                    $events[$methodName] = $pendingEvent;
                }

                $pendingEvent = null;
            }
        }

        return $events;
    }

    protected static function pushReloadEvents(Project $project, array $payload)
    {
        static::$commands = \array_values(\array_filter(static::$commands, function ($command) use ($payload) {
            return ($command['kind'] ?? 'property') !== 'reload-events'
                || ($command['form'] ?? '') !== $payload['form'];
        }));

        static::$commands[] = [
            'kind' => 'reload-events',
            'seq' => ++static::$seq,
            'ts' => Time::millis(),
            'form' => $payload['form'],
            'handlers' => $payload['handlers'],
        ];

        static::scheduleFlush($project);
    }

    protected static function resolvePropertyType($code)
    {
        switch ($code) {
            case 'visible':
            case 'enabled':
            case 'tooltipText':
                return 'virtual';
            case 'position':
                return 'position';
            case 'size':
                return 'size';
            default:
                return 'native';
        }
    }

    protected static function pushProperty(FormEditor $editor, UXNode $node, $property, $value, $type)
    {
        $project = Ide::get()->getOpenedProject();

        if (!$project) {
            return;
        }

        $nodeId = $editor->getNodeId($node);

        if (!$nodeId) {
            return;
        }

        $formName = fs::name(fs::pathNoExt($editor->getFxmlFile()));

        static::$commands[] = [
            'kind' => 'property',
            'seq' => ++static::$seq,
            'ts' => Time::millis(),
            'form' => $formName,
            'nodeId' => $nodeId,
            'property' => $property,
            'value' => static::normalizeValue($value),
            'type' => $type,
        ];

        static::scheduleFlush($project);
    }

    protected static function normalizeValue($value)
    {
        if ($value instanceof UXColor) {
            return (string) $value;
        }

        if (\is_object($value) && \method_exists($value, 'toArray')) {
            return $value->toArray();
        }

        return $value;
    }

    protected static function scheduleFlush(Project $project)
    {
        if (static::$flushScheduled) {
            return;
        }

        static::$flushScheduled = true;

        waitAsync(60, function () use ($project) {
            static::$flushScheduled = false;
            static::flush($project);
        });
    }

    public static function flush(Project $project)
    {
        if (!static::$commands) {
            return;
        }

        $file = $project->getFile(static::SYNC_REL_PATH);
        $dir = fs::parent($file);

        if (!fs::isDir($dir)) {
            fs::makeDir($dir);
        }

        Json::toFile($file, [
            'seq' => static::$seq,
            'commands' => static::$commands,
        ]);

        static::$commands = [];
    }
}
