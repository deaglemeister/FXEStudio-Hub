<?php
namespace ide\project;

use ide\Logger;
use ide\utils\FileUtils;
use php\gui\designer\UXDirectoryTreeValue;
use php\gui\designer\UXTreeIcons;
use php\io\File;
use php\lib\fs;
use php\lib\str;
use php\util\Regex;

/**
 * IntelliJ-style иконки: дерево, табы, панель проекта.
 */
class TreeIconResolver
{
    protected static $extIcons = [
        'json' => 'json',
        'yaml' => 'yaml',
        'yml' => 'yaml',
        'js' => 'javaScript',
        'mjs' => 'javaScript',
        'cjs' => 'javaScript',
        'html' => 'html',
        'htm' => 'html',
        'xhtml' => 'xhtml',
        'java' => 'java',
        'class' => 'java',
        'dnproject' => 'projection',
        'debug' => 'debug',
        'jpg' => 'image',
        'jpeg' => 'image',
        'png' => 'image',
        'gif' => 'image',
        'ico' => 'image',
        'bmp' => 'image',
        'tif' => 'image',
        'tiff' => 'image',
        'webp' => 'image',
        'svg' => 'image',
        'psd' => 'image',
        'zip' => 'archive',
        'jar' => 'archive',
        'rar' => 'archive',
        '7z' => 'archive',
        'tar' => 'archive',
        'gz' => 'archive',
        'bz2' => 'archive',
        'war' => 'archive',
        'iso' => 'archive',
        'phb' => 'php',
        'css' => 'css',
        'less' => 'css',
        'scss' => 'css',
        'sass' => 'css',
        'xml' => 'xml',
        'fxml' => 'xml',
        'axml' => 'xml',
        'txt' => 'text',
        'log' => 'text',
        'rst' => 'text',
        'md' => 'markdown',
        'markdown' => 'markdown',
        'mdown' => 'markdown',
        'ini' => 'properties',
        'conf' => 'properties',
        'properties' => 'properties',
        'cfg' => 'properties',
        'sql' => 'sql',
        'sqlite' => 'binaryData',
        'db' => 'binaryData',
        'sh' => 'shell',
        'bat' => 'shell',
        'cmd' => 'shell',
        'ps1' => 'shell',
        'csv' => 'csv',
        'toml' => 'toml',
        'dockerfile' => 'docker',
        'gradle' => 'gradle',
        'kts' => 'gradle',
        'groovy' => 'groovy',
        'graphql' => 'graphql',
        'gql' => 'graphql',
        'patch' => 'patch',
        'diff' => 'patch',
        'cpp' => 'cpp',
        'cc' => 'cpp',
        'cxx' => 'cpp',
        'h' => 'cpp',
        'hpp' => 'cpp',
        'c' => 'cpp',
        'cs' => 'Csharp',
        'swift' => 'swiftLang',
        'tf' => 'terraform',
        'vue' => 'vue',
        'ipynb' => 'jupyter',
        'bin' => 'binaryData',
        'dat' => 'binaryData',
        'dll' => 'binaryData',
        'so' => 'binaryData',
        'exe' => 'binaryData',
        'ttf' => 'font',
        'otf' => 'font',
        'woff' => 'font',
        'woff2' => 'font',
        'schema' => 'jsonSchema',
    ];

    protected static $phpKindCache = [];

    /**
     * Предзагрузка часто используемых иконок (FX-поток, без блокировки).
     */
    public static function warmUp()
    {
        UXTreeIcons::warmUp();
    }

    /**
     * Каждый вызов возвращает НОВЫЙ узел: JavaFX Node может быть только
     * в одном месте сцены, кэшировать узлы нельзя (иконки будут пропадать).
     * Само изображение кэшируется на Java-стороне (TreeSvgIconLoader).
     *
     * @param string $name
     * @return \php\gui\UXNode|null
     */
    public static function loadIcon($name)
    {
        if (!$name) {
            return null;
        }

        $icon = UXTreeIcons::load($name);

        if ($icon === null && $name !== 'anyType') {
            $icon = UXTreeIcons::load('anyType');
        }

        return $icon;
    }

    /**
     * @param Project|null $project
     * @param string|File $path
     * @return \php\gui\UXNode|null
     */
    public static function loadForPath(Project $project = null, $path = null)
    {
        if (!$path) {
            return null;
        }

        if (!$path instanceof File) {
            $path = new File($path);
        }

        if (!$path->exists()) {
            return static::loadIcon('anyType');
        }

        if (!$project) {
            $project = \ide\Ide::project();
        }

        if (!$project) {
            return static::loadIcon('anyType');
        }

        return static::loadIcon(static::resolveIconName($project, $path->getPath(), $path));
    }

    /**
     * @param Project $project
     * @param string $path
     * @param File $file
     * @return UXDirectoryTreeValue|null
     */
    public static function createValue(Project $project, $path, File $file)
    {
        $displayName = $file->getName();

        try {
            $iconName = static::resolveIconName($project, $path, $file);
        } catch (\Throwable $e) {
            Logger::warn('Tree icon resolve failed: ' . $file->getName() . ' — ' . $e->getMessage());
            $iconName = 'anyType';
        }

        $icon = null;

        try {
            $icon = static::loadIcon($iconName);
        } catch (\Throwable $e) {
            Logger::warn('Tree icon load failed: ' . $iconName . ' — ' . $e->getMessage());
        }

        if ($file->isDirectory()) {
            if ($icon === null) {
                $icon = static::loadIcon('folder');
            }

            $expandIcon = static::resolveExpandIcon($iconName);

            return new UXDirectoryTreeValue(
                $path,
                $displayName,
                $displayName,
                $icon,
                $expandIcon,
                true
            );
        }

        if ($icon === null) {
            $icon = static::loadIcon('anyType');
        }

        return new UXDirectoryTreeValue(
            $path,
            $displayName,
            $displayName,
            $icon,
            null,
            false
        );
    }

    /**
     * @param Project $project
     * @param string $path
     * @param File $file
     * @return string
     */
    public static function resolveIconName(Project $project, $path, File $file)
    {
        $name = $file->getName();

        if ($file->isDirectory()) {
            if (str::equalsIgnoreCase($name, $project->getName())) {
                return 'projection';
            }

            static $folderIcons = [
                '.debug' => 'debug',
                'modules' => 'module',
                'module' => 'module',
                'src' => 'ideaProject',
                'app' => 'application',
                'assets' => 'image',
                'images' => 'image',
                'public' => 'folder',
                'audio' => 'binaryData',
                '.data' => 'config',
                'data' => 'config',
                'config' => 'config',
                'forms' => 'uiForm',
                'form' => 'uiForm',
                '.theme' => 'css',
                'theme' => 'css',
                'themes' => 'css',
                'styles' => 'css',
                'img' => 'image',
                'imgs' => 'image',
                'image' => 'image',
                'images' => 'image',
                'assets' => 'image',
                'icons' => 'image',
                'fonts' => 'font',
                'font' => 'font',
                'sql' => 'sql',
                'db' => 'sql',
                'database' => 'sql',
                'scripts' => 'shell',
                'bin' => 'shell',
                'vendor' => 'archive',
                'libs' => 'archive',
                'lib' => 'archive',
                'sounds' => 'binaryData',
                'audio' => 'binaryData',
                'music' => 'binaryData',
                'video' => 'binaryData',
                'docs' => 'markdown',
                'doc' => 'markdown',
                'tests' => 'debug',
                'test' => 'debug',
            ];

            $lower = str::lower($name);

            if (isset($folderIcons[$lower])) {
                return $folderIcons[$lower];
            }

            return 'folder';
        }

        if (str::equalsIgnoreCase($name, '.gitignore')) {
            return 'gitignore';
        }

        if (str::equalsIgnoreCase($name, '.editorconfig')) {
            return 'editorConfig';
        }

        if (str::equalsIgnoreCase($name, 'dockerfile')) {
            return 'docker';
        }

        $ext = fs::ext($file);

        if ($ext === 'php') {
            return static::detectPhpKind($file);
        }

        if (isset(static::$extIcons[$ext])) {
            return static::$extIcons[$ext];
        }

        return 'anyType';
    }

    /**
     * @param string $iconName
     * @return \php\gui\UXNode|null
     */
    protected static function resolveExpandIcon($iconName)
    {
        // Только обычная папка меняет иконку при раскрытии.
        // Спец-папки (.debug, src, app, forms и т.д.) сохраняют свою иконку.
        if ($iconName !== 'folder') {
            return null;
        }

        return static::loadIcon('open');
    }

    /**
     * @param File $file
     * @return string
     */
    protected static function detectPhpKind(File $file)
    {
        $cacheKey = $file->getPath() . '|' . $file->lastModified();

        if (isset(static::$phpKindCache[$cacheKey])) {
            return static::$phpKindCache[$cacheKey];
        }

        try {
            $head = FileUtils::getHead($file, 8192);
        } catch (\Throwable $e) {
            Logger::warn('PHP icon detect failed: ' . $file->getName() . ' — ' . $e->getMessage());

            return static::$phpKindCache[$cacheKey] = 'php';
        }

        if (!$head) {
            return static::$phpKindCache[$cacheKey] = 'php';
        }

        if (Regex::match('~\btrait\s+[A-Za-z_][A-Za-z0-9_]*~', $head)) {
            return static::$phpKindCache[$cacheKey] = 'trait';
        }

        if (Regex::match('~\binterface\s+[A-Za-z_][A-Za-z0-9_]*~', $head)) {
            return static::$phpKindCache[$cacheKey] = 'interface';
        }

        if (Regex::match('~extends\s+(\\\\)?(\w+\\\\)*AbstractForm\b~', $head)) {
            return static::$phpKindCache[$cacheKey] = 'uiForm';
        }

        if (Regex::match('~extends\s+(\\\\)?(\w+\\\\)*AbstractModule\b~', $head)) {
            return static::$phpKindCache[$cacheKey] = 'module';
        }

        if (Regex::match('~\babstract\s+class\s+[A-Za-z_][A-Za-z0-9_]*~', $head)) {
            return static::$phpKindCache[$cacheKey] = 'abstractClass';
        }

        if (Regex::match('~\bclass\s+[A-Za-z_][A-Za-z0-9_]*~', $head)) {
            return static::$phpKindCache[$cacheKey] = 'class';
        }

        return static::$phpKindCache[$cacheKey] = 'php';
    }
}
