<?php

namespace platform\types;

use php\gui\UXImage;
use php\io\File;

abstract class FileType
{
    abstract function validate(File $file) : bool;
    abstract function getIcon() : UXImage;

    


}