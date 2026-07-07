<?php
namespace ide\editors\value;

use ide\forms\TextPropertyEditorForm;
use ide\utils\UiUtils;
use php\gui\layout\UXHBox;
use php\gui\paint\UXColor;
use php\gui\UXButton;
use php\gui\UXColorPicker;
use php\gui\UXWindow;
use php\xml\DomElement;

class ColorPropertyEditor extends ElementPropertyEditor
{
    /**
     * @var UXColorPicker
     */
    protected $colorPicker;

    /**
     * @var UXButton
     */
    protected $dialogButton;

    public function getNormalizedValue($value)
    {
        if (!($value instanceof UXColor)) {
            $value = UXColor::of($value);
        }

        return $value;
    }

    public function getCssNormalizedValue($value)
    {
        /** @var UXColor $value */
        return $value instanceof UXColor ? $value->getWebValue() : (string) $value;
    }

    public function makeUi()
    {
        $this->colorPicker = new UXColorPicker();
        $this->colorPicker->padding = 0;
        $this->colorPicker->maxWidth = 9999;
        UXHBox::setHgrow($this->colorPicker, 'ALWAYS');
        $this->colorPicker->style = "-fx-background-insets: 0; -fx-background-radius: 0; -fx-font-size: 10px; ";

        $this->colorPicker->on('action', function () {
            $this->applyValue($this->colorPicker->value, false);

            uiLater(function () {
                $value = $this->getNormalizedValue($this->colorPicker->value);
                $this->updateUi($value);
            });
        });

        $this->dialogButton = UiUtils::makePropertyExpandButton(function () {
            $dialog = new TextPropertyEditorForm();

            $dialog->watch('focused', function (UXWindow $self, $prop, $old, $new) {
                if (!$new) {
                    $self->hide();
                }
            });

            $dialog->title = $this->name;
            $value = $this->getValue();

            if ($value instanceof UXColor) {
                $value = $value->getWebValue();
            }

            $dialog->setResult($value);

            if ($dialog->showDialog()) {
                $this->applyValue($dialog->getResult());
            }
        });

        $box = new UXHBox([$this->colorPicker, $this->dialogButton]);
        $box->spacing = 4;
        $box->padding = [0, 2, 0, 0];

        return $box;
    }

    public function setTooltip($tooltip)
    {
        parent::setTooltip($tooltip);

        if ($this->colorPicker) {
            $this->colorPicker->tooltip = null;
        }
    }


    public function applyValue($value, $updateUi = true)
    {
        parent::applyValue($value, $updateUi);
    }

    /**
     * @param $value
     * @param bool $noRefreshDesign
     */
    public function updateUi($value, $noRefreshDesign = false)
    {
        parent::updateUi($value, $noRefreshDesign);

        if ($value && !($value instanceof UXColor)) {
            $value = UXColor::of($value);
        }

        $this->colorPicker->value = $value;
    }

    public function getCode()
    {
        return 'color';
    }

    /**
     * @param DomElement $element
     *
     * @return ElementPropertyEditor
     */
    public function unserialize(DomElement $element)
    {
        return new static();
    }
}