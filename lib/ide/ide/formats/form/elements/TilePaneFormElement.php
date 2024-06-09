<?php
namespace ide\formats\form\elements;

use ide\editors\value\BooleanPropertyEditor;
use ide\editors\value\ColorPropertyEditor;
use ide\editors\value\FontPropertyEditor;
use ide\editors\value\IntegerPropertyEditor;
use ide\editors\value\PositionPropertyEditor;
use ide\editors\value\SimpleTextPropertyEditor;
use ide\editors\value\TextPropertyEditor;
use ide\formats\form\AbstractFormElement;
use php\gui\designer\UXDesignProperties;
use php\gui\designer\UXDesignPropertyEditor;
use php\gui\layout\UXAnchorPane;
use php\gui\layout\UXFlowPane;
use php\gui\layout\UXHBox;
use php\gui\layout\UXTilePane;
use php\gui\layout\UXVBox;
use php\gui\UXButton;
use php\gui\UXNode;
use php\gui\UXTableCell;
use php\gui\UXTextField;
use php\gui\UXTitledPane;

class TilePaneFormElement extends AbstractFormElement
{
    public function getGroup()
    {
        return 'Панели';
    }

    public function getElementClass()
    {
        return UXTilePane::class;
    }

    public function getName()
    {
        return 'Плиточный слой';
    }

    public function getIcon()
    {
        return 'icons/tilePane16.png';
    }

    public function getIdPattern()
    {
        return "tilePane%s";
    }

    public function isLayout()
    {
        return true;
    }

    public function addToLayout($self, $node, $screenX, $screenY)
    {
        /** @var UXHBox $self */
        $node->position = $self->screenToLocal($screenX, $screenY);
        $self->add($node);
    }

    public function getLayoutChildren($layout)
    {
        return $layout->children;
    }

    /**
     * @return UXNode
     */
    public function createElement()
    {
        $pane = new UXTilePane();
        $pane->hgap = 5;
        $pane->vgap = 5;
        $pane->prefColumns = 5;
        $pane->prefRows = 5;

        return $pane;
    }

    public function getDefaultSize()
    {
        return [250, 250];
    }

    public function isOrigin($any)
    {
        return $any instanceof UXTilePane;
    }
}
