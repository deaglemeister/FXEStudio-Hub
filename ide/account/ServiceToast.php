<?php
namespace ide\account;

use script\TimerScript;
use php\time\Timer;
use std;
use action\Animation;

use httpclient;
use php\io\IOException;
use php\framework\Logger;
use facade\Json;
use bundle\http\HttpClient;

class ServiceToast
{

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
        Logger::info($param['message']);
        Animation::fadeIn($this->content, 130, function () {
          $timer = Timer::after(9000, function () {
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