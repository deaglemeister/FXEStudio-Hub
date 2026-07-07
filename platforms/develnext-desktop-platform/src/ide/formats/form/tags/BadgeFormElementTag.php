<?php
namespace ide\formats\form\tags;

use ide\formats\form\AbstractFormElementTag;
use php\gui\UXBadge;
use php\xml\DomElement;

class BadgeFormElementTag extends AbstractFormElementTag
{
    public function getTagName()
    {
        return 'Badge';
    }

    public function getElementClass()
    {
        return UXBadge::class;
    }

    public function writeAttributes($node, DomElement $element)
    {
        /** @var UXBadge $node */
        if ($node->badgeType) {
            $element->setAttribute('badgeType', $node->badgeType);
        }

        if ($node->borderRadius) {
            $element->setAttribute('borderRadius', $node->borderRadius);
        }

        if ($node->backgroundColor) {
            $element->setAttribute('backgroundColor', $node->backgroundColor->getWebValue());
        }
    }
}
