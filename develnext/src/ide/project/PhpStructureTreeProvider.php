<?php
namespace ide\project;

use ide\editors\CodeEditor;
use ide\editors\FormEditor;
use ide\systems\FileSystem;
use php\gui\designer\UXDirectoryTreeValue;
use php\gui\designer\UXFileDirectoryTreeSource;
use php\io\File;
use php\lib\fs;
use php\lib\str;

/**
 * Виртуальные подузлы PHP-структуры в дереве проекта.
 */
class PhpStructureTreeProvider
{
    const VIRTUAL_PREFIX = '$v$';

    /**
     * @param UXFileDirectoryTreeSource $source
     * @param Project $project
     */
    public static function register(UXFileDirectoryTreeSource $source, Project $project)
    {
        $source->setStructureProvider(
            function ($path) use ($project) {
                return static::listChildPaths($project, $path);
            },
            function ($path) use ($project) {
                return static::createVirtualValue($project, $path);
            },
            function ($path) {
                return static::isVirtualPath($path);
            }
        );
    }

    /**
     * @param ProjectTree $tree
     * @param Project $project
     */
    public static function registerOpenHandler(ProjectTree $tree, Project $project)
    {
        // Без типизации аргумента: ProjectTree может передать и строку, и File.
        $tree->addOpenHandler(function ($file) use ($tree, $project) {
            $path = $tree->getSelectedPath();

            if (!static::isVirtualPath($path)) {
                return false;
            }

            return static::openVirtual($project, $path);
        });
    }

    /**
     * @param string $path
     * @return bool
     */
    public static function isVirtualPath($path)
    {
        return $path && str::startsWith($path, static::VIRTUAL_PREFIX);
    }

    /**
     * @param Project $project
     * @param string $path
     * @return string[]
     */
    public static function listChildPaths(Project $project, $path)
    {
        if (static::isVirtualPath($path)) {
            $meta = static::decodePath($path);

            if (!$meta) {
                return [];
            }

            return static::listMetaChildren($project, $meta);
        }

        if (!$path || fs::ext($path) !== 'php') {
            return [];
        }

        $file = $project->getFile($path);

        if (!$file || !$file->isFile()) {
            return [];
        }

        $tree = PhpStructureParser::parseFile($file);
        $result = [];

        // Только классы/интерфейсы/трейты (namespace и use — шум, как в PhpStorm).
        foreach ($tree['types'] as $index => $type) {
            $result[] = static::encodePath($path, $type['kind'], $type['name'], $type['line'], $index);
        }

        return $result;
    }

    /**
     * @param Project $project
     * @param array $meta
     * @return string[]
     */
    protected static function listMetaChildren(Project $project, array $meta)
    {
        if (!isset($meta['file'], $meta['kind'])) {
            return [];
        }

        $kind = $meta['kind'];

        if (!in_array($kind, ['class', 'abstractClass', 'interface', 'trait'], true)) {
            return [];
        }

        $file = $project->getFile($meta['file']);

        if (!$file || !$file->isFile()) {
            return [];
        }

        $tree = PhpStructureParser::parseFile($file);
        $typeIndex = isset($meta['typeIndex']) ? (int) $meta['typeIndex'] : 0;

        if (!isset($tree['types'][$typeIndex])) {
            return [];
        }

        $type = $tree['types'][$typeIndex];
        $result = [];

        foreach ($type['members'] as $memberIndex => $member) {
            $result[] = static::encodePath(
                $meta['file'],
                $member['kind'],
                $member['name'],
                $member['line'],
                $typeIndex,
                $memberIndex
            );
        }

        return $result;
    }

    /**
     * @param Project $project
     * @param string $path
     * @return UXDirectoryTreeValue|null
     */
    public static function createVirtualValue(Project $project, $path)
    {
        $meta = static::decodePath($path);

        if (!$meta) {
            return null;
        }

        $kind = $meta['kind'];
        $name = $meta['name'];
        $line = isset($meta['line']) ? (int) $meta['line'] : 1;
        $display = static::displayName($kind, $name);
        $iconName = static::iconName($kind, $name, $project, $meta['file']);
        $icon = TreeIconResolver::loadIcon($iconName);
        $expandable = in_array($kind, ['class', 'abstractClass', 'interface', 'trait'], true);

        return new UXDirectoryTreeValue(
            $path,
            $display,
            $display,
            $icon,
            null,
            $expandable
        );
    }

    /**
     * @param Project $project
     * @param string $path
     * @return bool
     */
    public static function openVirtual(Project $project, $path)
    {
        $meta = static::decodePath($path);

        if (!$meta || !isset($meta['file'])) {
            return false;
        }

        $file = $project->getFile($meta['file']);

        if (!$file || !$file->isFile()) {
            return false;
        }

        $line = isset($meta['line']) ? (int) $meta['line'] : 1;
        $editor = FileSystem::open($file);

        if ($editor instanceof CodeEditor) {
            $editor->jumpToLine(max(1, $line));
        } elseif ($editor instanceof FormEditor) {
            // Форма/модуль: сразу исходный код на нужной строке, а не конструктор.
            uiLater(function () use ($editor, $line) {
                try {
                    $editor->switchToSource();

                    $codeEditor = $editor->getCodeEditor();

                    if ($codeEditor instanceof CodeEditor) {
                        $codeEditor->jumpToLine(max(1, $line));
                    }
                } catch (\Throwable $e) {
                    // конструктор останется активным — не критично
                }
            });
        }

        return true;
    }

    /**
     * @param string $filePath
     * @param string $kind
     * @param string $name
     * @param int $line
     * @param int|null $typeIndex
     * @param int|null $memberIndex
     * @return string
     */
    public static function encodePath($filePath, $kind, $name, $line, $typeIndex = null, $memberIndex = null)
    {
        $payload = [
            'file' => str::replace($filePath, '\\', '/'),
            'kind' => $kind,
            'name' => $name,
            'line' => (int) $line,
        ];

        if ($typeIndex !== null) {
            $payload['typeIndex'] = (int) $typeIndex;
        }

        if ($memberIndex !== null) {
            $payload['memberIndex'] = (int) $memberIndex;
        }

        return static::VIRTUAL_PREFIX . base64_encode(json_encode($payload));
    }

    /**
     * @param string $path
     * @return array|null
     */
    public static function decodePath($path)
    {
        if (!static::isVirtualPath($path)) {
            return null;
        }

        $json = base64_decode(str::sub($path, str::length(static::VIRTUAL_PREFIX)));

        if (!$json) {
            return null;
        }

        $data = json_decode($json, true);

        return is_array($data) ? $data : null;
    }

    /**
     * @param string $kind
     * @param string $name
     * @return string
     */
    protected static function displayName($kind, $name)
    {
        switch ($kind) {
            case 'namespace':
                return 'namespace ' . $name;
            case 'use':
                return 'use ' . $name;
            case 'abstractClass':
                return 'abstract class ' . $name;
            case 'class':
                return 'class ' . $name;
            case 'interface':
                return 'interface ' . $name;
            case 'trait':
                return 'trait ' . $name;
            case 'property':
            case 'staticProperty':
                return $name;
            case 'method':
            case 'staticMethod':
            case 'abstractMethod':
                return $name;
            case 'constant':
                return 'const ' . $name;
            default:
                return $name;
        }
    }

    /**
     * @param string $kind
     * @param string $name
     * @param Project $project
     * @param string $filePath
     * @return string
     */
    protected static function iconName($kind, $name, Project $project, $filePath)
    {
        switch ($kind) {
            case 'namespace':
                return 'properties';
            case 'use':
                return 'module';
            case 'interface':
                return 'interface';
            case 'trait':
                return 'trait';
            case 'abstractClass':
                return 'abstractClass';
            case 'class':
                return 'class';
            case 'property':
            case 'staticProperty':
                return 'properties';
            case 'constant':
                return 'properties';
            case 'method':
            case 'staticMethod':
            case 'abstractMethod':
                return 'php';
            default:
                return 'anyType';
        }
    }
}
