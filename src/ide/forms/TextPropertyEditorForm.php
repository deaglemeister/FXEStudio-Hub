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
        
        $this->toasts_container();
        $this->show_tc(['message' => "Свойство $this->title скопировано", 'color' => 'blue']);
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
    public function show_tc($param) {
          
        # Контейнер
        $this->content = new UXVBox;
        $this->content->classes->add('toast');
        $this->content->opacity = 0;
        $this->content->on('click', function() {
            $this->content->free();
        });
        
        # Вставляем в окно
        $mainForm = app()->form("MainForm")->toasts->toFront();
        $mainForm = app()->form("MainForm")->toasts->add($this->content);
        
        # Заголовок
        if ($param['title']) {
            $title = new UXLabel($param['title']);
            $title->classes->addAll(['title', 'font-bold']);
            $this->content->add($title);
        }
        
        # Сообщение
        if ($param['message']) {
            $message = new UXLabel($param['message']);
            $message->wrapText = true;
            $message->textAlignment = "LEFT";
            $message->classes->addAll(['message']);
            $this->content->add($message);
        }
        
        # Цвет
        if ($param['color']) {
            $this->content->classes->add($param['color']);
        }
        
        # Показываем toast
        #Logger::info($param['message']);
        Animation::fadeIn($this->content, 130, function () {
          $timer = Timer::after(5000, function () {
              Animation::fadeOut($this->content, 130, function() {
                  $this->content->free();
              });
          });
      });
        
    }

   public function toasts_container() {
        $this->toasts = new UXVBox;
        $this->toasts->classes->add('toasts');
        $this->toasts->anchors = ['bottom' => 10, 'right' => 10];
        $mainForm = app()->form("MainForm")->add($this->toasts);
    }
}