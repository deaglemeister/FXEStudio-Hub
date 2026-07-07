<?php
namespace ide\formats\form\elements;

use ide\formats\form\AbstractFormElement;
use php\gui\UXNode;
use php\gui\UXSearchBox;

class SearchBoxFormElement extends AbstractFormElement
{
    public function getName()
    {
        return 'Поиск';
    }

    public function getElementClass()
    {
        return UXSearchBox::class;
    }

    public function getIcon()
    {
        return 'icons/textField16.png';
    }

    public function getIdPattern()
    {
        return "searchBox%s";
    }

    /**
     * @return UXNode
     */
    public function createElement()
    {
        $box = new UXSearchBox();
        $box->promptText = 'Поиск...';
        $box->borderRadius = 6;

        return $box;
    }

    public function getDefaultSize()
    {
        return [200, 36];
    }

    public function isOrigin($any)
    {
        return $any instanceof UXSearchBox;
    }
}
