<?php
namespace ide\systems;

use ide\editors\FormEditor;
use ide\Ide;
use ide\project\behaviours\GuiFrameworkProjectBehaviour;
use ide\utils\FileUtils;
use php\gui\framework\DataUtils;
use php\gui\UXCustomNode;
use php\gui\UXNode;
use php\lib\fs;
use php\lib\str;
use php\util\Regex;

/**
 * Сбор списка мест, которые затронет переименование id компонента формы.
 */
class FormElementSafeRename
{
    /**
     * @param FormEditor $editor
     * @param UXNode $node
     * @param string $newId
     * @return array[] [ ['group' => string, 'file' => string, 'detail' => string], ... ]
     */
    public static function collect(FormEditor $editor, UXNode $node, $newId)
    {
        $oldId = $editor->getNodeId($node);

        if (!$oldId || $oldId == $newId) {
            return [];
        }

        $items = [];

        static::addFormItems($editor, $oldId, $newId, $items);
        static::addEventItems($editor, $oldId, $newId, $items);
        static::addBehaviourItems($editor, $oldId, $newId, $items);
        static::addCodeReferenceItems($editor, $oldId, $newId, $items);
        static::addCssItems($editor, $oldId, $newId, $items);
        static::addPrototypeItems($editor, $oldId, $newId, $items);
        static::addProjectReferenceItems($editor, $oldId, $newId, $items);

        return $items;
    }

    protected static function addFormItems(FormEditor $editor, $oldId, $newId, array &$items)
    {
        $formName = fs::name($editor->getFile());

        $items[] = [
            'group' => 'Форма',
            'file' => $formName,
            'detail' => "id компонента: $oldId → $newId",
        ];

        $fxmlFile = fs::pathNoExt($editor->getFile()) . '.fxml';

        if (fs::isFile($fxmlFile)) {
            $items[] = [
                'group' => 'Форма',
                'file' => fs::name($fxmlFile),
                'detail' => "fx:id / id: $oldId → $newId",
            ];
        }
    }

    protected static function addEventItems(FormEditor $editor, $oldId, $newId, array &$items)
    {
        $binds = $editor->getEventManager()->findBinds($oldId);

        if (!$binds) {
            return;
        }

        $phpFile = fs::name($editor->getFile());

        foreach ($binds as $eventCode => $bind) {
            $methodName = isset($bind['methodName']) ? $bind['methodName'] : '';
            $newMethodPrefix = 'do' . str::upperFirst($newId);

            $detail = "@event $oldId.$eventCode → $newId.$eventCode";

            if ($methodName) {
                $suffix = str::sub($methodName, str::length('do' . str::upperFirst($oldId)));
                $detail .= ", метод $methodName → $newMethodPrefix$suffix";
            }

            $items[] = [
                'group' => 'События',
                'file' => $phpFile,
                'detail' => $detail,
            ];
        }
    }

    protected static function addBehaviourItems(FormEditor $editor, $oldId, $newId, array &$items)
    {
        $behaviours = (array) $editor->getBehaviourManager()->getBehaviours($oldId);

        if (!$behaviours) {
            return;
        }

        $types = [];

        foreach ($behaviours as $type => $behaviour) {
            if ($behaviour) {
                $types[] = $type;
            }
        }

        if ($types) {
            $items[] = [
                'group' => 'Поведения',
                'file' => fs::name(fs::pathNoExt($editor->getFile()) . '.behaviour'),
                'detail' => "target id: $oldId → $newId (" . str::join($types, ', ') . ')',
            ];
        }
    }

    protected static function addCodeReferenceItems(FormEditor $editor, $oldId, $newId, array &$items)
    {
        $phpFile = $editor->getFile();

        if (!fs::isFile($phpFile)) {
            return;
        }

        $content = FileUtils::get($phpFile);
        $count = static::countPhpIdReferences($content, $oldId);

        if ($count > 0) {
            $items[] = [
                'group' => 'Код',
                'file' => fs::name($phpFile),
                'detail' => "\$this->$oldId и ссылки lookup: $count вхожд. → $newId",
            ];
        }

        $actionFile = fs::pathNoExt($phpFile) . '.php.axml';

        if (fs::isFile($actionFile)) {
            $actionContent = FileUtils::get($actionFile);
            $actionCount = static::countPlainReferences($actionContent, $oldId);

            if ($actionCount > 0) {
                $items[] = [
                    'group' => 'Код',
                    'file' => fs::name($actionFile),
                    'detail' => "ссылки на $oldId: $actionCount вхожд. → $newId",
                ];
            }
        }
    }

    protected static function addCssItems(FormEditor $editor, $oldId, $newId, array &$items)
    {
        $files = static::collectCssFiles($editor);

        foreach ($files as $file) {
            if (!fs::isFile($file)) {
                continue;
            }

            $count = static::countCssIdReferences(FileUtils::get($file), $oldId);

            if ($count > 0) {
                $items[] = [
                    'group' => 'CSS',
                    'file' => static::shortProjectPath($file),
                    'detail' => "#$oldId: $count вхожд. → #$newId",
                ];
            }
        }
    }

    protected static function addPrototypeItems(FormEditor $editor, $oldId, $newId, array &$items)
    {
        $gui = GuiFrameworkProjectBehaviour::get();

        if (!$gui) {
            return;
        }

        $factoryName = $editor->getTitle();
        $oldType = "$factoryName.$oldId";
        $newType = "$factoryName.$newId";

        foreach ($gui->getFormEditors() as $formEditor) {
            if ($editor === $formEditor) {
                continue;
            }

            $count = static::countPrototypeReferences($formEditor, $oldType);

            if ($count > 0) {
                $items[] = [
                    'group' => 'Прототипы',
                    'file' => fs::name($formEditor->getFile()),
                    'detail' => "прототип $oldType → $newType ($count шт.)",
                ];
            }
        }
    }

    protected static function addProjectReferenceItems(FormEditor $editor, $oldId, $newId, array &$items)
    {
        $project = Ide::project();

        if (!$project) {
            return;
        }

        $currentPhp = fs::pathNoExt($editor->getFile()) . '.php';

        $project->eachSrcFile(function ($file) use ($oldId, $newId, $currentPhp, &$items) {
            $path = $file->getPath();

            if (!str::endsWith($path, '.php')) {
                return;
            }

            if (fs::abs($path) == fs::abs($currentPhp)) {
                return;
            }

            if (!fs::isFile($path)) {
                return;
            }

            $content = FileUtils::get($path);
            $count = static::countPhpIdReferences($content, $oldId);

            if ($count > 0) {
                $items[] = [
                    'group' => 'Ссылки',
                    'file' => $file->getRelativePath(),
                    'detail' => "упоминания $oldId: $count вхожд. → $newId",
                ];
            }
        });
    }

    /**
     * @param FormEditor $editor
     * @return string[]
     */
    public static function collectCssFiles(FormEditor $editor)
    {
        $files = [];

        foreach ($editor->getStylesheets() as $sheet) {
            if ($sheet && !in_array($sheet, $files, true)) {
                $files[] = $sheet;
            }
        }

        $project = Ide::project();

        if (!$project) {
            return $files;
        }

        $themeFiles = [
            $project->getSrcFile('.theme/style.fx.css'),
            $project->getSrcFile('.theme/skin.css'),
        ];

        foreach ($themeFiles as $file) {
            if ($file && fs::isFile($file) && !in_array($file, $files, true)) {
                $files[] = $file;
            }
        }

        $skinDir = $project->getSrcFile('.theme/skin');

        if (fs::isDir($skinDir)) {
            fs::scan($skinDir, function ($filename) use (&$files) {
                if (str::endsWith($filename, '.css')) {
                    $files[] = $filename;
                }
            });
        }

        return $files;
    }

    public static function countPhpIdReferences($content, $id)
    {
        $pattern = '(\$this->)' . Regex::quote($id) . '(\b)';
        $lookupPattern = '(lookup\([\'\"])#?' . Regex::quote($id) . '([\'\"])';

        return static::countRegexMatches($pattern, $content)
            + static::countRegexMatches($lookupPattern, $content);
    }

    public static function countPlainReferences($content, $id)
    {
        return static::countRegexMatches('\b' . Regex::quote($id) . '\b', $content);
    }

    public static function countCssIdReferences($content, $id)
    {
        return static::countRegexMatches('#' . Regex::quote($id) . '(\b|[^a-zA-Z0-9_\-])', $content);
    }

    protected static function countRegexMatches($pattern, $content)
    {
        $regex = Regex::of($pattern)->with($content);
        $count = 0;

        while ($regex->find()) {
            $count++;
        }

        return $count;
    }

    protected static function countPrototypeReferences(FormEditor $formEditor, $oldType)
    {
        if (!$formEditor->getLayout()) {
            return 0;
        }

        $count = 0;

        DataUtils::scanAll($formEditor->getLayout(), function ($data, $node) use ($oldType, &$count) {
            if ($node instanceof UXCustomNode) {
                if ($node->get('type') == $oldType) {
                    $count++;
                }
            } else {
                $factoryId = $node->data('-factory-id');

                if ($factoryId && $factoryId == $oldType) {
                    $count++;
                }
            }
        });

        return $count;
    }

    public static function replacePhpIdReferences($content, $oldId, $newId)
    {
        $pattern = '(\$this->)' . Regex::quote($oldId) . '(\b)';
        $content = Regex::of($pattern)->with($content)->replace('$1' . $newId . '$2');

        $lookupPattern = '(lookup\([\'\"])#?' . Regex::quote($oldId) . '([\'\"])';
        $content = Regex::of($lookupPattern)->with($content)->replace('$1#' . $newId . '$2');

        return $content;
    }

    public static function replaceCssIdReferences($content, $oldId, $newId)
    {
        $pattern = '#' . Regex::quote($oldId) . '(\b|[^a-zA-Z0-9_\-])';

        return Regex::of($pattern)->with($content)->replace('#' . $newId . '$1');
    }

    protected static function shortProjectPath($file)
    {
        $project = Ide::project();

        if ($project) {
            $src = $project->getSrcDirectory();

            if ($src && str::startsWith($file, $src)) {
                return str::sub($file, str::length($src) + 1);
            }
        }

        return fs::name($file);
    }
}
