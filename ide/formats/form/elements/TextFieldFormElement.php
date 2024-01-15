<?php
namespace ide\formats\form\elements;

use ide\formats\form\AbstractFormElement;
use php\gui\designer\UXDesignProperties;
use php\gui\UXNode;
use php\gui\UXTextField;

/**
 * Class TextFieldFormElement
 * @package ide\formats\form
 */
class TextFieldFormElement extends AbstractFormElement
{
    /**
     * @return string
     */
    public function getName()
    {
        return 'Поле ввода';
    }

    public function getElementClass()
    {
        return UXTextField::class;
    }

    public function getIcon()
    {
        return 'icons/textField16.png';
    }

    public function getIdPattern()
    {
        return "edit%s";
    }

    /**
     * @return UXNode
     */
    public function createElement()
    {
        $element = new UXTextField();
        return $element;
    }

    public function getDefaultSize()
    {
        return [150, 35];
    }

    public function isOrigin($any)
    {
        return get_class($any) == UXTextField::class;
    }

    public function resetStyle(UXNode $node, UXNode $baseNode)
    {
        parent::resetStyle($node, $baseNode);

        /** @var UXTextField $node */
        /** @var UXTextField $baseNode */
        $node->font = $baseNode->font;
    }


}