<?php
namespace ide\editors\value;
use ide\utils\UiUtils;
use php\gui\UXButton;
use php\gui\UXChoiceBox;
use php\lib\Items;
use php\gui\layout\UXHBox;
use php\lib\Str;
use php\lib\String;
use php\xml\DomElement;

/**
 * Class EnumPropertyEditor
 * @package ide\editors\value
 */
class EnumPropertyEditor extends TextPropertyEditor
{
    /**
     * @var UXChoiceBox
     */
    protected $choiceBox;

    /**
     * @var array
     */
    protected $variants;

    /**
     * @var
     */
    protected $variantKeys;

    /**
     * @var UXButton
     */
    protected $dialogButton;

    /**
     * @var bool
     */
    private $editable;

    /**
     * @var bool
     */
    public $existsCustomValue;

    /**
     * @param array $variants
     * @param bool $editable
     */
    public function __construct(array $variants = [], $editable = false)
    {
        $this->setVariants($variants);
        $this->editable = $editable;

        parent::__construct();
    }

    /**
     * @param array $variants
     */
    public function setVariants($variants)
    {
        $this->variants = $variants;
        $this->variantKeys = Items::keys($variants);

        if ($this->choiceBox) {
            $this->choiceBox->items->clear();
            $this->choiceBox->items->addAll($this->variants);
        }
    }

    public function makeUi()
    {
        $this->choiceBox = new UXChoiceBox();
        $this->choiceBox->maxWidth = 300;
        $this->choiceBox->items->addAll($this->variants);
        UXHBox::setHgrow($this->choiceBox, 'ALWAYS');

        $this->choiceBox->style = "-fx-background-insets: 0; -fx-background-radius: 5; -fx-background-color: -fx-control-inner-background; -fx-padding: 0 6 0 6;";

        $this->choiceBox->on('action', function () {
            if ($this->existsCustomValue) {
                if ($this->choiceBox->selectedIndex == 0) {
                    $value = $this->choiceBox->items[0];
                } else {
                    $value = $this->variantKeys[$this->choiceBox->selectedIndex - 1];
                }
            } else {
                $value = $this->variantKeys[$this->choiceBox->selectedIndex];
            }

            $this->applyValue($value, false);
        });

        $ui = new UXHBox([$this->choiceBox]);

        if ($this->editable) {
            $this->makeDialogButtonUi();
            $ui->add($this->dialogButton);
        }

        $ui->spacing = 4;
        $ui->padding = [0, 2, 0, 0];

        return $ui;
    }

    public function setTooltip($tooltip)
    {
        parent::setTooltip($tooltip);

        if ($this->choiceBox) {
            $this->choiceBox->tooltip = null;
        }
    }

    public function getNormalizedValue($value)
    {
        if (Str::isNumber($value)) {
            if ($key = $this->variantKeys[$value]) {
                return $key;
            }
        }

        if (!$this->variants[$value] && !$this->editable) {
            return Items::firstKey($this->variants);
        }

        return $value;
    }

    /**
     * @param $value
     * @param bool $noRefreshDesign
     */
    public function updateUi($value, $noRefreshDesign = false)
    {
        parent::updateUi($value, $noRefreshDesign);

        $i = 0;
        $this->choiceBox->selectedIndex = -1;

        foreach ($this->variants as $code => $name) {
            if ("$value" == "$code") {
                $this->choiceBox->selectedIndex = $i;
                return;
            }

            $i++;
        }

        if ($this->editable) {
            $this->existsCustomValue = true;

            if (items::first($this->variants) == $this->choiceBox->items[0]) {
                $this->choiceBox->items->insert(0, $value);
            } elseif ($this->choiceBox->items->count < 1) {
                $this->choiceBox->items->add($value);
            } else {
                $this->choiceBox->items->removeByIndex(0);
                $this->choiceBox->items->insert(0, $value);
            }

            $this->choiceBox->selectedIndex = 0;
        }
    }

    public function getCode()
    {
        return 'enum';
    }

    /**
     * @param DomElement $element
     *
     * @return ElementPropertyEditor
     */
    public function unserialize(DomElement $element)
    {
        $variants = [];

        /** @var DomElement $el */
        foreach ($element->findAll('variants/variant') as $el) {
            $variants[$el->getAttribute('value')] = $el->getTextContent();
        }

        $editor = new static($variants, $element->getAttribute('editable'));
        return $editor;
    }
}