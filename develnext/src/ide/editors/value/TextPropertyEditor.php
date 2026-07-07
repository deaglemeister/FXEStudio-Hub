<?php
namespace ide\editors\value;

use ide\forms\TextPropertyEditorForm;
use ide\utils\UiUtils;
use php\gui\layout\UXHBox;
use php\gui\UXButton;
use php\gui\UXWindow;
use php\xml\DomElement;

/**
 * Class TextPropertyEditor
 * @package ide\editors\value
 */
class TextPropertyEditor extends SimpleTextPropertyEditor
{
    /**
     * @var TextPropertyEditorForm
     */
    protected $editorForm;
    /**
     * @var UXButton
     */
    protected $dialogButton;

    protected function getEditorForm()
    {
        if (!$this->editorForm) {
            $this->editorForm = new TextPropertyEditorForm();
        }

        return $this->editorForm;
    }

    protected function makeDialogButtonUi()
    {
        $this->dialogButton = UiUtils::makePropertyExpandButton(function () {
            $this->showDialog();
        });
    }

    public function makeUi()
    {
        parent::makeUi();

        $this->makeDialogButtonUi();

        $box = new UXHBox([$this->textField, $this->dialogButton]);
        $box->spacing = 4;
        $box->padding = [0, 2, 0, 0];

        return $box;
    }

    public function getCode()
    {
        return 'text';
    }

    public function showDialog($x = null, $y = null)
    {
        $dialog = $this->getEditorForm();

        if ($dialog->visible) {
            $dialog->hide();
        }

        $dialog->title = $this->name;
        $dialog->setResult($this->getNormalizedValue($this->getValue()));

        if ($dialog->showDialog($x, $y)) {
            $this->applyValue($dialog->getResult());
        }
    }
}