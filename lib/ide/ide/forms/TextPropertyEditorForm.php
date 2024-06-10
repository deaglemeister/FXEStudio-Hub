<?php
namespace ide\forms;

use ide\forms\mixins\DialogFormMixin;
use ide\utils\UiUtils;
use php\gui\event\UXEvent;
use php\gui\UXButton;
use php\gui\UXClipboard;
use php\gui\UXTextArea;
use php\gui\event\UXEvent;
use php\gui\UXButton;
use php\gui\UXTextArea;
use platform\facades\Toaster;
use platform\toaster\ToasterMessage;
use php\gui\UXImage;


/**
 * Class TextPropertyEditorForm
 * @package ide\forms
 *
 * @property UXTextArea $textArea
 * @property UXButton $applyButton
 * @property UXButton $cancelButton
 */
class TextPropertyEditorForm extends AbstractIdeForm
{
    use DialogFormMixin;
    //use SavableFormMixin;
    public $toasts;

    protected function init()
    {
        parent::init();

        UiUtils::setUiHidingOnUnfocus($this);
    }

    /**
     * @event copyButton.action
     */
    public function actionCopy(UXEvent $e)
    {
        UXClipboard::setText($this->textArea->text);
        $this->hide();
        $tm = new ToasterMessage();
        $iconImage = new UXImage('res://resources/expui/icons/fileTypes/font_dark.png');
        $tm
        ->setIcon($iconImage)
        ->setTitle('Менеджер свойства текста')
        ->setDescription(_('Ваш текст был скопирован в буфер обмена.'))
        ->setClosable();
        Toaster::show($tm);
    }

    /**
     * @event show
     */
    public function actionOpen()
    {
        $this->textArea->text = $this->getResult();
        $this->textArea->requestFocus();
    }

    /**
     * @event applyButton.action
     */
    public function actionApply()
    {
        $this->setResult($this->textArea->text);
        $this->hide();
    }

    /**
     * @event cancelButton.action
     */
    public function actionCancel()
    {
        $this->setResult(null);
        $this->hide();
    }

}