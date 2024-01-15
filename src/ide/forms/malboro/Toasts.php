<?php
namespace ide\forms\malboro;

use ide\editors\form\IdeTabPane;
use ide\forms\mixins\SavableFormMixin;
use ide\Ide;
use ide\IdeConfigurable;
use ide\IdeException;
use ide\Logger;
use ide\project\templates\DefaultGuiProjectTemplate;
use ide\systems\FileSystem;
use ide\systems\ProjectSystem;
use ide\systems\WatcherSystem;
use ide\utils\FileUtils;
use ide\utils\UiUtils;
use php\desktop\HotKeyManager;
use php\desktop\Robot;
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

use php\gui\framework\Animation;
use php\gui\framework\Timer;

use php\gui\UXControl;

use php\gui\layout\UXScrollPane;

use httpclient;
use php\io\IOException;
use php\framework\Logger;
use facade\Json;
use bundle\http\HttpClient;

class Toasts
{
    function showToast($title, $description, $color) {
        static $toasts = [];
        $maxToasts = 3;
        
        if (count($toasts) >= $maxToasts) {
            $oldToast = array_pop($toasts);
            $oldToast->free();
        }
        
        $vbox = new UXVBox();
        $vbox->anchors = ['bottom' => 10, 'right' => 10];
        $vbox->toFront();
        $vbox->style = "-fx-padding: 10 10 10 10; -fx-border-radius: 7; -fx-background-radius: 7; -fx-cursor: hand; -fx-min-width: 150; -fx-max-width: 300; -fx-background-color: $color; -fx-spacing: 5; -fx-opacity: 0;";
        
        $fadeInDuration = 500;
        $fadeOutDuration = 500;
        $showDuration = 5500;
        
        $vbox->on('click', function () use ($vbox, $fadeOutDuration) {
            Animation::fadeOut($vbox, $fadeOutDuration, function () use ($vbox) {
                $vbox->free();
            });
        });
        
        $descriptionLabel = new UXLabel();
        $descriptionLabel->wrapText = true;
        $descriptionLabel->font->family = 'Arial';
        $descriptionLabel->style = "-fx-text-fill: white;";
        $descriptionLabel->text = $description;
        
        $titleLabel = new UXLabel();
        $titleLabel->wrapText = true;
        $titleLabel->style = "-fx-text-fill: white; -fx-font-weight: bold;";
        $titleLabel->font->family = 'Arial';
        $titleLabel->text = $title;
        
        $vbox->add($titleLabel);
        $vbox->add($descriptionLabel);
        
        Animation::fadeIn($vbox, $fadeInDuration);
        // Заменяем следующие две строки кода
        Timer::setTimeout(function () use ($vbox, $fadeOutDuration) {
            Animation::fadeOut($vbox, $fadeOutDuration, function () use ($vbox) {
                $vbox->free();
            });
        }, 10000); // Время задержки для исчезновения в миллисекундах
        
        app()->form('MainForm')->add($vbox);
        
        $toasts[] = $vbox;
        
        $totalToasts = count($toasts);
        $spacing = 10;
        for ($i = 0; $i < $totalToasts; $i++) {
            $toast = $toasts[$i];
            $toast->y = -($totalToasts - $i) * ($toast->height + $spacing);
        }
        
       
        }
}
    

