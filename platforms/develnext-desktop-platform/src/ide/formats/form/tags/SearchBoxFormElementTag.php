<?php
namespace ide\formats\form\tags;

use ide\formats\form\AbstractFormElementTag;
use php\gui\UXSearchBox;
use php\xml\DomElement;

class SearchBoxFormElementTag extends AbstractFormElementTag
{
    public function getTagName()
    {
        return 'SearchBox';
    }

    public function getElementClass()
    {
        return UXSearchBox::class;
    }

    public function writeAttributes($node, DomElement $element)
    {
        /** @var UXSearchBox $node */
        $element->setAttribute('text', self::escapeText($node->text));
        $element->setAttribute('promptText', self::escapeText($node->promptText));

        if ($node->borderRadius) {
            $element->setAttribute('borderRadius', $node->borderRadius);
        }
    }
}
