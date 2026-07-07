<?php
namespace ide\formats\form\tags;

use ide\formats\form\AbstractFormElementTag;
use php\gui\UXIconButton;
use php\xml\DomElement;

class IconButtonFormElementTag extends AbstractFormElementTag
{
    public function getTagName()
    {
        return 'IconButton';
    }

    public function getElementClass()
    {
        return UXIconButton::class;
    }

    public function writeAttributes($node, DomElement $element)
    {
        /** @var UXIconButton $node */
        if ($node->borderRadius) {
            $element->setAttribute('borderRadius', $node->borderRadius);
        }
    }
}
