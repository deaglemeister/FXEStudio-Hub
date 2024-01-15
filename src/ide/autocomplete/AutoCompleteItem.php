<?php
namespace ide\autocomplete;
use php\gui\UXImage;
use php\gui\UXImageArea;

/**
 * Class AutoCompleteItem
 * @package ide\autocomplete
 */
abstract class AutoCompleteItem
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $description;

    /**
     * @var string
     */
    protected $content;

    /**
     * @var string|UXImage|UXImageArea
     */
    protected $icon;

    /**
     * @var null
     */
    protected $insert = null;

    /**
     * @var string
     */
    private $style;

    /**
     * @param $name
     * @param string $description
     * @param null $insert
     * @param null $icon
     * @param null $style
     */
    public function __construct($name, $description = '', $insert = null, $icon = null, $style = null)
    {
        $this->name = $name;
        $this->description = $description;
        $this->icon = $icon;

        $this->insert = $insert !== null ? $insert : $name;
        $this->style = $style;
    }

    public function getInsert()
    {
        return $this->insert;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @return \php\gui\UXImage|\php\gui\UXImageArea|string
     */
    public function getIcon()
    {
        return $this->icon;
    }

    /**
     * @return string
     */
    public function getStyle()
    {
        return $this->style;
    }

    public function getDefaultIcon()
    {
        return null;
    }

    /**
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * @param string $content
     */
    public function setContent($content)
    {
        $this->content = $content;
    }
}