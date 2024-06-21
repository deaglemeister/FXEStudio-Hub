<?php

namespace ide\editors\templates\other;

use php\gui\UXLabel;
use php\gui\UXToggleButton;
use kosogroup\liver\ui\components\UIxHBox;
use kosogroup\liver\ui\components\UIxImageView;
use kosogroup\liver\ui\components\UIxVBox;
use php\gui\event\UXEvent;
use php\gui\event\UXKeyEvent;
use php\gui\framework\Application;
use php\gui\layout\UXHBox;
use php\gui\layout\UXVBox;
use php\gui\UXImage;
use php\gui\UXTab;
use php\gui\UXTabPane;
use php\gui\UXToggleGroup;
use php\lib\str;

class WelcomeTabPane extends UXVBox
{
    protected  $__tabPane;
    protected  $__vboxTogglePane;
    protected  $__toggleGroup;
    protected  $__toggleButtons;
    protected  $__titlePane;
    protected  $__layout;
    protected  $__titleLabel;
    
    public function __construct()
    {
        parent::__construct();
        $this->style = '-fx-effect: dropshadow(gaussian, black, 10, 0, 0, 0); -fx-border-raduius:10 10 0 0 ; -fx-background-radius: 10 10 0 0;';
        $this->__layout = new UXHBox;
        $this->__layout->spacing = 0;
        $this->__layout->classesString = 'ui-tab-pane-welcome-hbox';
        

        $this->__titlePane = new UXHBox; // шапка 
        $this->__titlePane->alignment = 'CENTER';
        $this->__titlePane->classesString = 'dn-welcome-heder';
        $this->__titlePane->height = 32;
        $this->__titlePane->width = 900;
        $this->__titlePane->minHeight = 32;
        $this->__titlePane->minWidth = 500;
        $this->add($this->__titlePane);
        $name = Application::get()->getName();
        $this->__titleLabel = new UXLabel(); //текст шапки
        $this->__titleLabel->text = "Добро пожаловать в {$name}!";
        $this->__titleLabel->font->bold = "Добро пожаловать в {$name}!";
        $this->__titleLabel->classes->add('h1');
        $this->__titleLabel->classesString = 'ui-text';
        $this->__titleLabel->autoSize = true;    
        $this->__titlePane->add($this->__titleLabel);
        
        $pane = ((new UIxHBox([

            (new UIxImageView(new UXImage('res://resources/expui/logotypes/LogoWelcome.png')))
            ->_setSize([32,32]),
            (new UIxVBox([
                ($name = new UXLabel(Application::get()->getName())),
                    
                ($version =  new UXLabel(Application::get()->getVersion()))
                    
            ]))
        ]))
        ->_setSpacing(10)
        ->_setClassesString('dn-welcome-logotype')
        ->_setPaddingLeft(18)
        ->_setPaddingTop(18)
        ->_setPaddingRight(10)
        ->_setPaddingBottom(15)
        ->_setAlignment('CENTER_LEFT'));
        
        $name->classesString ='dn-mainTitleName';
      
        $version->classesString ='dn-mainTitleVersion';

        $this->__toggleGroup = new UXToggleGroup;
        $this->classesString = 'dn-anchor-pane';
        $this->__vboxTogglePane = new UXVBox;
        $this->__vboxTogglePane->classesString = 'dn-anchor-pane-welcome';// панель где кнопки
        $this->__vboxTogglePane->add($pane );
        $this->__vboxTogglePane->maxWidth = 350;
        $this->__vboxTogglePane->minHeight = 300;
        $this->__vboxTogglePane->minWidth = 200;
        $this->__vboxTogglePane->spacing = 5;
        $this->__vboxTogglePane->paddingLeft = 10;
        $this->__vboxTogglePane->paddingRight = 10;
        $this->__vboxTogglePane->setVgrow($this,'ALWAYS');
        $this->__tabPane = new UXTabPane;
        $this->__tabPane->on('keyDown', function (UXKeyEvent $uxKeyEvent)
        {
            if($uxKeyEvent->controlDown && str::lower($uxKeyEvent->codeName) == 'tab')
                {
                    $this->__tabPane->selectPreviousTab();
                    
                }
        });
        $this->__tabPane->classesString = 'dn-toggle-tab-pane'; // контент при клике меняется
        UXHBox::setHgrow($this->__tabPane,'ALWAYS');
        UXHBox::setHgrow($this->__vboxTogglePane,'ALWAYS');
        UXVBox::setVgrow($this->__layout, 'ALWAYS');
        $this->__layout->add($this->__vboxTogglePane);
        $this->__layout->add($this->__tabPane);
        $this->add($this->__layout);
        
    }
    
    public function addTab(UXTab $tab,array $retarget = ['isFake'=> false])
    {
       
        $this->__tabPane->tabs->add($tab);
        $tgb = new UXToggleButton($tab->text);// сама кнопка
        $tab->data('nodeTgb',$tgb);
        $tgb->alignment = 'CENTER_LEFT';
        UXVBox::setVgrow($tgb,'ALWAYS');
        $tgb->maxWidth = 1000;
        $tgb->height = 32;
        $tgb->classesString = 'dn-tg-button-welcome';
        $tgb->toggleGroup = $this->__toggleGroup;
        if(!$retarget['isFake']){
            $tgb->on('action', function(UXEvent $e) use ($tab) {
                if (!$e->target->selected) {
                    $e->target->selected = true;
                }
                $this->__tabPane->selectedTab = $tab;
            });
            
            $this->__vboxTogglePane->add($tgb);
    
            $this->__toggleButtons[$tab] = $tgb;
        }else{
            $tgb->on('action', function(UXEvent $e) use ($tab) {
                if ($e->target->selected) {
                    $e->target->selected = false;
                }
               //browse(Application::get()->getUrlSite()); TO-DO
               
            });

            $this->__vboxTogglePane->add($tgb);

        }
        
    }
}