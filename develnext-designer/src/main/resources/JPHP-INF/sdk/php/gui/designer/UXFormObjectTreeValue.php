<?php
namespace php\gui\designer;

use php\gui\UXNode;

class UXFormObjectTreeValue
{
    /**
     * @var string
     */
    public $id;

    /**
     * @var string
     */
    public $typeName;

    /**
     * @var UXNode
     */
    public $icon;

    /**
     * @var bool
     */
    public $renameable = true;
}
