<?php
namespace php\gui\designer;

use php\gui\UXControl;
use php\gui\UXPopupWindow;
use php\io\File;

/**
 */
class UXFileDirectoryTreeSource extends UXAbstractDirectoryTreeSource
{
    /**
     * @var bool
     */
    public $showHidden = false;

    /**
     * UXAbstractDirectoryTreeSource constructor.
     * @param string $directory
     */
    public function __construct($directory)
    {
    }

    /**
     * @return File
     */
    public function getDirectory()
    {
    }

    /**
     * @param callable $callback (File $file)
     */
    public function addFileFilter(callable $callback)
    {
    }

    /**
     * @param callable $callback (string $path, File $file): UXDirectoryTreeValue
     */
    public function addValueCreator(callable $callback)
    {
    }

    /**
     * @param callable $listChildren (string $path): string[]
     * @param callable $createValue (string $path): UXDirectoryTreeValue|null
     * @param callable $isVirtual (string $path): bool
     */
    public function setStructureProvider(callable $listChildren, callable $createValue, callable $isVirtual)
    {
    }
}