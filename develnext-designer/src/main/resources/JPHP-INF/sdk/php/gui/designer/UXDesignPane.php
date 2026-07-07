<?php
namespace php\gui\designer;

use php\gui\layout\UXAnchorPane;
use php\gui\layout\UXScrollPane;

/**
 * Class UXDesignPane
 * @package php\gui\designer
 */
class UXDesignPane extends UXAnchorPane
{
    /**
     * @var float
     */
    public $zoom = 1.0;

    /**
     * @var int
     */
    public $borderWidth = 8;

    /**
     * @var int
     */
    public $snapSize = 8;

    /**
     * @var string
     */
    public $borderColor = 'gray';

    /**
     * @readonly
     * @var bool
     */
    public $editing;

    /**
     * @param callable|null $handle
     */
    public function onResize(callable $handle)
    {
    }

    /**
     * @param UXScrollPane|null $scrollPane
     */
    public function enableWheelZoom($scrollPane = null)
    {
    }

    /**
     * @param UXScrollPane|null $scrollPane
     */
    public function enableMiddleMousePan($scrollPane = null)
    {
    }

    /**
     * @param callable|null $handle
     */
    public function onZoomChanged(callable $handle = null)
    {
    }
}