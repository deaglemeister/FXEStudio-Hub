<?php

namespace ide\editors\templates\other;

use php\gui\UXButton;
use php\gui\UXLabel;
use php\gui\event\UXEvent;
use php\gui\event\UXMouseEvent;
use php\gui\framework\Application;
use php\gui\layout\UXAnchorPane;
use php\gui\layout\UXHBox;
use php\gui\layout\UXVBox;

class modalWelcome extends UXVBox{

    protected  $__executeAfter;
    protected   $__title;
    protected   $__layout;
    
    public  function __construct()
    {
        parent::__construct();
        UXAnchorPane::setAnchor($this,0);
        $this->on('click',function(UXMouseEvent $e){

            if($e->isDoubleClick()){
                $this->free();
            }
        });

        $this->classesString = 'blur-modal';
        $this->alignment = 'CENTER';
        $this->spacing = 4;

        $this->__title = new UXLabel(); 
        $this->__title->font->size = 14;
        $this->add($this->__title);

        $this->__layout = new UXHBox();
        $this->__layout->alignment = 'CENTER';
        $this->__layout->spacing = 4; 
        $this->add($this->__layout);

        $okButton = new UXButton('Удалить');
        $okButton->classesString = 'dn-button-blue';
        $okButton->on('action',function(){
            call_user_func($this->__executeAfter);
            $this->free();
        });
        $this->__layout->add($okButton);

        $cancelButton = new UXButton('Отмена');
        $cancelButton->on('action',function(UXEvent $e){
            $this->free();
        });
        $this->__layout->add($cancelButton);

        
    }
    public  function showModal(array $setting)
    {
        $this->__title->text = $setting['text'];
        $this->__executeAfter = $setting['closure'];
        $this->toFront();
        Application::get()->getMainForm()->layout->add($this);
    }

}