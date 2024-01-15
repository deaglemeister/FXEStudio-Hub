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
use php\gui\framework\DataUtils;
use php\gui\layout\UXHBox;
use php\gui\UXApplication;
use php\gui\UXButton;
use php\gui\UXComboBox;
use php\gui\UXDatePicker;
use php\gui\UXNode;
use php\gui\UXTableCell;
use php\gui\UXTextField;


class DatePickerFormElement extends AbstractFormElement
{
    public function getName()
    {
        return 'Поле для даты';
    }

    public function getElementClass()
    {
        return UXDatePicker::class;
    }

    public function getIcon()
    {
        return 'icons/calendar16.png';
    }

    public function getIdPattern()
    {
        return "dateEdit%s";
    }

    public function getGroup()
    {
        return 'Дополнительно';
    }

    /**
     * @return UXNode
     */
    public function createElement()
    {
        $button = new UXDatePicker();
        $button->format = 'dd.MM.yyyy';

        $button->value = '01.01.2000';

        return $button;
    }

    public function registerNode(UXNode $node)
    {
        if ($node->parent) { // fix bug.
            /** @var UXDatePicker $node */
            $data = DataUtils::get($node);
            $format = $data->get('format');
            $value = $data->get('value');

            UXApplication::runLater(function () use ($format, $value, $node) {
                if ($format) {
                    $node->format = $format;
                }

                if ($value) {
                    $node->value = $value;
                }
            });
        }
    }


    public function getDefaultSize()
    {
        return [150, 35];
    }

    public function isOrigin($any)
    {
        return $any instanceof UXDatePicker;
    }

    public function resetStyle(UXNode $node, UXNode $baseNode)
    {
        parent::resetStyle($node, $baseNode);

        /** @var UXTextField $node */
        /** @var UXTextField $baseNode */
        $node->font = $baseNode->font;
    }
}
