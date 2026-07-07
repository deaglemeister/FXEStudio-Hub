<?php
namespace ide\formats\form\elements;

use php\gui\UXBadge;
use php\gui\UXNode;

class BadgeFormElement extends LabeledFormElement
{
    public function getName()
    {
        return 'Плашка / бейдж';
    }

    public function getElementClass()
    {
        return UXBadge::class;
    }

    public function getIcon()
    {
        return 'icons/label16.png';
    }

    public function getIdPattern()
    {
        return "badge%s";
    }

    /**
     * @return UXNode
     */
    public function createElement()
    {
        $badge = new UXBadge('NEW');
        $badge->badgeType = 'new';
        $badge->textColor = '#ffffff';

        return $badge;
    }

    public function getDefaultSize()
    {
        return [48, 22];
    }

    public function isOrigin($any)
    {
        return $any instanceof UXBadge;
    }
}
