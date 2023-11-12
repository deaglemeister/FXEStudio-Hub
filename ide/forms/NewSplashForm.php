<?php
namespace ide\forms;

use ide\Ide;
use ide\Logger;
use ide\systems\SplashTipSystem;
use php\gui\event\UXEvent;
use php\gui\framework\AbstractForm;
use php\gui\layout\UXAnchorPane;
use php\gui\layout\UXHBox;
use php\gui\layout\UXVBox;
use php\gui\UXApplication;
use php\gui\UXImage;
use php\gui\UXImageArea;
use php\gui\UXImageView;
use php\gui\UXLabel;
use php\io\IOException;
use php\io\Stream;

use php\lang\Thread;
use php\lang\ThreadPool;
use php\lib\str;
use script\storage\IniStorage;

use php\gui\;
use std, gui, framework, app;

use php\time\Time;

class NewSplashForm extends AbstractIdeForm
{

 protected function init()
    {
       Logger::debug("Init form ...");
       $this->centerOnScreen();
       $this->waiter(); //ЧТООО ФУНКЦИЯ ИЗ 17????
    }
    
 public function waiter(int $timeoutMs = 1000){
        waitAsync($timeoutMs, function() use ($timeoutMs){
            if (is_object($this->_app->getMainForm()) && $this->_app->getMainForm()->visible) {
                $this->hide();
            } elseif($this->visible) {
                $this->waiter($timeoutMs);
            }
        });
 }
}
