<?php
namespace ide\project;

use ide\utils\FileUtils;
use php\io\File;
use php\lib\str;
use php\util\Regex;

/**
 * Regex-парсер структуры PHP-файла для виртуального дерева проекта.
 */
class PhpStructureParser
{
    /** @var array */
    protected static $cache = [];

    /**
     * @param File|string $file
     * @return array
     */
    public static function parseFile($file)
    {
        if (!$file instanceof File) {
            $file = new File($file);
        }

        if (!$file->isFile()) {
            return static::emptyTree();
        }

        $cacheKey = $file->getPath() . '|' . $file->lastModified();

        if (isset(static::$cache[$cacheKey])) {
            return static::$cache[$cacheKey];
        }

        $content = FileUtils::get($file);

        if (!$content) {
            return static::$cache[$cacheKey] = static::emptyTree();
        }

        return static::$cache[$cacheKey] = static::parseContent($content);
    }

    /**
     * @param string $content
     * @return array
     */
    public static function parseContent($content)
    {
        $tree = static::emptyTree();

        $namespaceRegex = Regex::of('^\s*namespace\s+([^;]+);', Regex::MULTILINE)->with($content);
        if ($namespaceRegex->find()) {
            $tree['namespace'] = [
                'name' => trim($namespaceRegex->group(1)),
                'line' => static::lineOf($content, $namespaceRegex->start()),
            ];
        }

        static::matchAll('^\s*use\s+([^;]+);', $content, function ($match, $offset) use (&$tree, $content) {
            $tree['uses'][] = [
                'name' => trim($match[1]),
                'line' => static::lineOf($content, $offset),
            ];
        }, Regex::MULTILINE);

        static::matchAll(
            '\b(trait|interface|abstract\s+class|class)\s+([A-Za-z_][A-Za-z0-9_]*)[^{]*\{',
            $content,
            function ($match, $offset) use (&$tree, $content) {
                $kindRaw = str::lower(str::trim($match[1]));
                $kind = 'class';

                if (str::contains($kindRaw, 'interface')) {
                    $kind = 'interface';
                } elseif (str::contains($kindRaw, 'trait')) {
                    $kind = 'trait';
                } elseif (str::contains($kindRaw, 'abstract')) {
                    $kind = 'abstractClass';
                }

                $bracePos = str::pos($content, '{', $offset + str::length($match[0]) - 1);
                if ($bracePos < 0) {
                    return;
                }

                $body = static::extractBalancedBody($content, $bracePos);
                if ($body === null) {
                    return;
                }

                $type = [
                    'kind' => $kind,
                    'name' => $match[2],
                    'line' => static::lineOf($content, $offset),
                    'members' => [],
                ];

                static::parseMembers($content, $body['offset'], $body['text'], $type['members']);
                $tree['types'][] = $type;
            },
            Regex::MULTILINE
        );

        return $tree;
    }

    /**
     * @param string $pattern
     * @param string $content
     * @param callable $callback
     */
    public static function matchAll($pattern, $content, callable $callback, $flags = 0)
    {
        $regex = Regex::of($pattern, $flags)->with($content);

        while ($regex->find()) {
            $row = [];
            $row[0] = $regex->group(0);

            $groupCount = $regex->getGroupCount();
            for ($i = 1; $i <= $groupCount; $i++) {
                $row[$i] = $regex->group($i);
            }

            $callback($row, $regex->start());
        }
    }

    /**
     * @param string $content
     * @param int $bodyOffset
     * @param string $body
     * @param array $members
     */
    public static function parseMembers($content, $bodyOffset, $body, array &$members)
    {
        static::matchAll(
            '^\s*(?:public|protected|private)?\s*(?:static\s+)?(?:abstract\s+)?function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(([^)]*)\)',
            $body,
            function ($match, $offset) use ($content, $bodyOffset, &$members) {
                $members[] = [
                    'kind' => 'method',
                    'name' => $match[1] . '(' . trim($match[2]) . ')',
                    'line' => static::lineOf($content, $bodyOffset + $offset),
                ];
            },
            Regex::MULTILINE
        );

        static::matchAll(
            '^\s*(?:public|protected|private)\s+(?:static\s+)?(?:[\?\w\\\\]+(?:\[\])?\s+)?(\$[A-Za-z_][A-Za-z0-9_]*)',
            $body,
            function ($match, $offset) use ($content, $bodyOffset, &$members) {
                $members[] = [
                    'kind' => 'property',
                    'name' => $match[1],
                    'line' => static::lineOf($content, $bodyOffset + $offset),
                ];
            },
            Regex::MULTILINE
        );

        static::matchAll(
            '^\s*const\s+([A-Za-z_][A-Za-z0-9_]*)',
            $body,
            function ($match, $offset) use ($content, $bodyOffset, &$members) {
                $members[] = [
                    'kind' => 'constant',
                    'name' => $match[1],
                    'line' => static::lineOf($content, $bodyOffset + $offset),
                ];
            },
            Regex::MULTILINE
        );
    }

    /**
     * @param string $content
     * @param int $openBracePos
     * @return array|null
     */
    public static function extractBalancedBody($content, $openBracePos)
    {
        $len = str::length($content);
        $depth = 0;
        $i = $openBracePos;

        // Скан по позициям скобок через str::pos — без посимвольного цикла.
        while ($i < $len) {
            $open = str::pos($content, '{', $i);
            $close = str::pos($content, '}', $i);

            if ($close < 0) {
                return null;
            }

            if ($open >= 0 && $open < $close) {
                $depth++;
                $i = $open + 1;
            } else {
                $depth--;
                $i = $close + 1;

                if ($depth === 0) {
                    return [
                        'text' => str::sub($content, $openBracePos + 1, $close),
                        'offset' => $openBracePos + 1,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @param string $content
     * @param int $offset
     * @return int
     */
    public static function lineOf($content, $offset)
    {
        if ($offset <= 0) {
            return 1;
        }

        $head = str::sub($content, 0, $offset);
        $head = str::replace($head, "\r\n", "\n");

        return str::count($head, "\n") + 1;
    }

    /**
     * @return array
     */
    protected static function emptyTree()
    {
        return [
            'namespace' => null,
            'uses' => [],
            'types' => [],
        ];
    }
}
