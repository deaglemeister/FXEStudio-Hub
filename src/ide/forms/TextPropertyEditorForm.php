<?php
namespace ide\forms;

use ide\forms\mixins\DialogFormMixin;
use ide\forms\mixins\SavableFormMixin;
use ide\Ide;
use ide\utils\UiUtils;
use php\gui\event\UXEvent;
use php\gui\framework\AbstractForm;
use php\gui\UXButton;
use php\gui\UXClipboard;
use php\gui\UXTextArea;
use php\gui\UXTooltip;

use php\gui\designer\UXDesigner;
use php\gui\designer\UXDirectoryTreeValue;
use php\gui\designer\UXDirectoryTreeView;
use php\gui\designer\UXFileDirectoryTreeSource;
use php\gui\dock\UXDockNode;
use php\gui\dock\UXDockPane;
use php\gui\event\UXEvent;
use php\gui\event\UXKeyboardManager;
use php\gui\event\UXKeyEvent;
use php\gui\event\UXMouseEvent;
use php\gui\framework\AbstractForm;
use php\gui\framework\Preloader;
use php\gui\layout\UXAnchorPane;
use php\gui\layout\UXHBox;
use php\gui\layout\UXVBox;
use php\gui\UXAlert;
use php\gui\UXApplication;
use php\gui\UXButton;
use php\gui\UXForm;
use php\gui\UXImage;
use php\gui\UXImageView;
use php\gui\UXLabel;
use php\gui\UXMenu;
use php\gui\UXMenuBar;
use php\gui\UXMenuItem;
use php\gui\UXNode;
use php\gui\UXScreen;
use php\gui\UXSplitPane;
use php\gui\UXTab;
use php\gui\UXTabPane;
use php\gui\UXTextArea;
use php\gui\UXTreeView;
use php\io\File;
use php\lang\System;
use php\lib\fs;
use php\lib\str;
use script\TimerScript;
use php\time\Timer;
use std;
use action\Animation;
use ide\forms\malboro\Toasts;
use httpclient;
use php\io\IOException;
use php\framework\Logger;
use facade\Json;
use bundle\http\HttpClient;

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
        $class = new Toasts;
        $class->showToast("Свойство", "$this->title скопировано", "#FF4F44");
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