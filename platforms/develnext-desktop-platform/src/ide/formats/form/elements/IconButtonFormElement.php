<?php
namespace ide\formats\form\elements;

use ide\formats\form\AbstractFormElement;
use php\gui\UXIconButton;
use php\gui\UXNode;

class IconButtonFormElement extends LabeledFormElement
{
    public function getName()
    {
        return 'Иконка-кнопка';
    }

    public function getElementClass()
    {
        return UXIconButton::class;
    }

    public function getIcon()
    {
        return 'icons/flatButton16.png';
    }

    public function getIdPattern()
    {
        return "iconButton%s";
    }

    /**
     * @return UXNode
     */
    public function createElement()
    {
        $button = new UXIconButton();
        $button->text = 'Кнопка';
        $button->contentDisplay = 'LEFT';
        $button->borderRadius = 6;
        $button->minWidth = 100;
        $button->minHeight = 36;
        $button->prefWidth = 120;
        $button->prefHeight = 36;

        return $button;
    }

    public function getDefaultSize()
    {
        return [120, 36];
    }

    public function isOrigin($any)
    {
        return $any instanceof UXIconButton;
    }
}
