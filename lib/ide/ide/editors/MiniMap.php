<?php

namespace ide\editors;
use Files;
use ide\autocomplete\php\PhpAutoComplete;
use ide\autocomplete\ui\AutoCompletePane;
use ide\editors\menu\ContextMenu;
use ide\forms\AbstractIdeForm;
use ide\forms\CodeEditorSettingsForm;
use ide\forms\FindTextDialogForm;
use ide\forms\MessageBoxForm;
use ide\forms\ReplaceTextDialogForm;
use ide\Ide;
use ide\Logger;
use ide\misc\AbstractCommand;
use ide\misc\EventHandlerBehaviour;
use ide\project\behaviours\PhpProjectBehaviour;
use ide\scripts\AbstractScriptComponent;
use ide\systems\FileSystem;
use ide\utils\FileUtils;
use php\gui\layout\UXPane;
use ide\utils\Json;
use ide\utils\UiUtils;
use php\format\JsonProcessor;
use php\gui\designer\UXAbstractCodeArea;
use php\gui\designer\UXCodeAreaScrollPane;
use php\gui\designer\UXCssCodeArea;
use php\gui\designer\UXFxCssCodeArea;
use php\gui\designer\UXJavaScriptCodeArea;
use php\gui\designer\UXPhpCodeArea;
use php\gui\designer\UXSyntaxAutoCompletion;
use php\gui\designer\UXSyntaxTextArea;
use php\gui\designer\UXTextCodeArea;
use php\gui\event\UXKeyEvent;
use php\gui\event\UXScrollEvent;
use php\gui\layout\UXAnchorPane;
use php\gui\layout\UXHBox;
use php\gui\layout\UXScrollPane;
use php\gui\UXTextArea;
use php\gui\layout\UXVBox;
use php\gui\text\UXFont;
use php\gui\UXApplication;
use php\gui\UXCheckbox;
use php\gui\UXClipboard;
use php\gui\UXContextMenu;
use php\gui\UXDesktop;
use php\gui\UXDialog;
use php\gui\UXForm;
use php\gui\UXLabel;
use php\gui\UXListView;
use php\gui\UXMenuItem;
use php\gui\UXNode;
use php\gui\UXPopupWindow;
use php\gui\UXTooltip;
use php\gui\UXWebEngine;
use php\gui\UXWebView;
use php\io\File;
use php\io\IOException;
use php\io\ResourceStream;
use php\io\Stream;
use php\lang\IllegalArgumentException;
use php\lang\IllegalStateException;
use php\lang\JavaException;
use php\lib\Char;
use php\lib\fs;
use php\lib\Items;
use php\lib\Mirror;
use php\lib\Str;
use php\net\URLConnection;
use php\time\Time;
use php\util\Scanner;
use php\time\Timer;
use script\TimerScript;
use script\TimerScript;
use php\gui\UXKeyEvent;

class MiniMap {
    private $textArea;
    private $miniMapArea;
    public  $miniMapText;
    private $updateTimer;
    public $NotSkipped;

    public function __construct($textAreaID) 
    {
        $this->textArea = $textAreaID;
        
        $this->NotSkipped = new NotSkipped(function(){
          
            $this->miniMapText->text = $this->textArea->text;
            
           
        });

        $this->textArea->on('keyUp', function (UXKeyEvent $e){
            $this->NotSkipped->debounce('key');
        });

        $this->textArea->on('keyDown', function (UXKeyEvent $e){
            $this->NotSkipped->debounce('key');
        });

        $this->miniMapArea = new UXScrollPane();
        $this->miniMapArea->fitToHeight = true;
        $this->miniMapArea->vbarPolicy = 'NEVER';
        $this->miniMapArea->x = 300;
        $this->miniMapArea->y = 900;
        $this->miniMapArea->hbarPolicy = 'NEVER';



        $this->miniMapText = new UXPhpCodeArea();
        $this->miniMapText->editable = false;
        $this->miniMapText->wrapText = true;
        $this->miniMapText->minHeight = 0;
        $this->miniMapText->minWidth = 0;
        $this->miniMapText->size = [200, 700];

        // Помещаем MiniMapText непосредственно в ScrollPane
        $this->miniMapArea->content = $this->miniMapText;

    }

    public function getMiniMapArea() {
        return $this->miniMapArea;
    }

    public function getMiniMapText(){
        return $this->miniMapText;
    }
}

class NotSkipped {
    
    private $waitingTimeout;
    private $timer;
    private $functionUser;

    public function __construct($function) {
        $this->functionUser = $function;
        $this->waitingTimeout = 5000;
    }

    public function debounce(...$args) {
        if ($this->timer) {
            // Если таймер уже был установлен, отменяем его
            $this->timer->stop();
        }

        // Устанавливаем новый таймер
        $this->timer = new TimerScript();
        $this->timer->interval = $this->waitingTimeout;
        $this->timer->repeatable = false;
        $this->timer->on('action', function () use ($args) {
            // Вызываем функцию пользователя
            call_user_func_array($this->functionUser, $args);
        });
        $this->timer->start();
    }
}




