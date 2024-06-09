<? 

namespace ide\forms\malboro;

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
use php\gui\effect\UXGaussianBlurEffect;
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

use php\gui\UXControl;
use php\gui\layout\UXScrollPane;

use httpclient;
use php\io\IOException;
use php\framework\Logger;
use facade\Json;
use bundle\http\HttpClient;


class Modals
{
    public $Flag = false;
    #----------------------------------------------------------------------------------------------------------------------------------------------------
    # Функция модального диалога
    #----------------------------------------------------------------------------------------------------------------------------------------------------
    
    /* Функция модального диалога */
    function modal_dialog($form, $param, callable $callback = null) {
        if($this->flag){
            return;
        }
        $this->flag = true;
        # Создаем контейнер для открытия и просмотра поста
        $fullscreen_modal = new UXScrollPane;
        $fullscreen_modal->focusTraversable = false;
        $fullscreen_modal->id = "fullscreen_modal_container";
        $fullscreen_modal->fitToWidth = $param['fitToWidth'];
        $fullscreen_modal->fitToHeight =  $param['fitToHeight'];
        $fullscreen_modal->anchors = ['left' => 0, 'top' => 0, 'right' => 0, 'bottom' => 0];
        $fullscreen_modal->opacity = 0;
        $fullscreen_modal->classes->add('fullscreen-modal');
        
        # Если передан параметр прозрачности
        if ($param['opacity_overlay']) {
            $opacity = $param['opacity_overlay'];
        } else {
            $opacity = "40";
        }
        
        # Если передан параметр цвета
        if ($param['color_overlay']) {
            $fullscreen_modal->style = "-fx-background: ".$param['color_overlay'].$opacity;
        } else {
            $fullscreen_modal->style = "-fx-background: #777777".$opacity;
        }
        
        # Передан ли контент для overlay
        if ($param['content']) {
            
            $dialog_container = $param['content'];
            
            # Параметр растягивать контент по высоте
            if ($param['contentFitToHeight']) {
                UXVBox::setVgrow($dialog_container, 'ALWAYS');
            }
            
        } else {
        
            # Контейнер диалога
            $dialog_container = new UXVBox;
            $dialog_container->classes->add('dialog-container');
            $dialog_container->on('click', function($e) {
                $e->consume();
            });
            
            # Заголовок и сообщение
            $title = new UXLabel($param['title']);
            $title->classes->addAll(['title', 'font-bold']);
            $message = new UXLabel($param['message']);
            $message->classes->addAll(['message', 'font-gray']);
            
            # Контейнер кнопок
            $button_container = new UXHBox;
            $dialog_container->opacity = 0;
            $button_container->classes->add('button-container');
            
            # Добавляем кнопки в контейнер
            foreach ($param['buttons'] as $b) {
                $button = new UXButton;
                $button->text = $b['text'];
                $button->classes->add($b['style']);
                $button->on('action', function () use ($callback, $form, $param, $fullscreen_modal, $dialog_container, $b) {
                    if ($b['close']) {
                        $close_modal = ['modal' => $fullscreen_modal, 'content' => $dialog_container, 'blur' => $param['blur']];
                        $this->modal_close($close_modal);
                    } else {
                        $callback($b['text']);
                        $close_modal = ['modal' => $fullscreen_modal, 'content' => $dialog_container, 'blur' => $param['blur']];
                        $this->modal_close($close_modal);
                    }
                });
                $button_container->add($button);
            }
            
            # Собираем диалог
            $dialog_container->add($title);
            $dialog_container->add($message);
            $dialog_container->add($button_container);
            
        }
        
        # Добавляем действия для модального окна
        $fullscreen_modal->on('keyUp', function($e) use ($param, $fullscreen_modal, $dialog_container, $this) {
            if ($e->codeName == 'Esc') {
                $close_modal = ['modal' => $fullscreen_modal, 'content' => $dialog_container, 'blur' => $param['blur']];
                $this->modal_close($close_modal);
                
            }
        });
        $fullscreen_modal->on('click', function($e) use ($form, $param, $fullscreen_modal, $dialog_container, $this) {
            if ($e->button == 'PRIMARY') {
                if ($param['close_overlay']) {
                    $close_modal = ['modal' => $fullscreen_modal, 'content' => $dialog_container, 'blur' => $param['blur']];
                    
                    $this->modal_close($close_modal);
                    
                }
            }
        });
        
        # Контент скролл контейнера
        $fullscreen_modal_content = new UXVbox([$dialog_container]);
        $fullscreen_modal_content->anchors = ['left' => 0, 'top' => 0, 'right' => 0, 'bottom' => 0];
        $fullscreen_modal_content->classes->add('content');
        
        # Если передан параметр прозрачности
        if ($param['padding']) {
            $fullscreen_modal_content->padding = $param['padding'];
        } else {
            $fullscreen_modal_content->padding = [60,60,60,60];
        }
        
        $fullscreen_modal->content = $fullscreen_modal_content;
        $form->add($fullscreen_modal);
        
        # Открываем скроллпан на весь экран
        Animation::fadeIn($fullscreen_modal, 130, function () use ($fullscreen_modal, $dialog_container, $param, $callback) {
            $fullscreen_modal->requestFocus();
            if ($param['blur']) {
                if ($param['blur']->effects->count == 0) {
                    $param['blur']->effects->add(new UXGaussianBlurEffect(3));
                }
            }
            Animation::fadeIn($dialog_container, 130);
            if (!empty($callback)) {
                $param_open = ['open' => true,
                               'modal' => $fullscreen_modal,
                               'content' => $dialog_container,
                               'blur' => $param['blur']
                               ];
                $callback($param_open);
            }
        });
    }
    
    #----------------------------------------------------------------------------------------------------------------------------------------------------
    # Функция закрытия модального диалога
    #----------------------------------------------------------------------------------------------------------------------------------------------------
    
    /* Функция закрытия модального диалога */
    function modal_close($param) {
        $this->flag = false;
        Animation::fadeOut($param['content'], 130, function() use ($param) {
            if ($param['blur']) {
                $param['blur']->effects->clear();
            }
            Animation::fadeOut($param['modal'], 130, function() use ($param) {
                $param['modal']->free();
            });
        });
    
    }
}
