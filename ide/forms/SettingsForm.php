<?php
namespace ide\forms;

use gui;
use ide\Ide;
use ide\editors\FormEditor;
use ide\formats\FormFormat;
use ide\Ide;
use ide\IdeClassLoader;
use ide\Logger;
use ide\systems\IdeSystem;
use php\gui\UXDialog;
use php\lang\System;
use script\storage\IniStorage;
use php\lib\fs;
use ide\ui\Notifications;
use php\gui\event\UXEvent;
use php\gui\UXApplication;
use php\gui\UXButton;
use php\gui\UXDesktop;
use php\gui\UXLabel;
use php\gui\UXTextArea;
use php\lib\fs;
use std, gui, framework, app;
use ide\editors\AbstractEditor;
use ide\editors\menu\AbstractMenuCommand;
use ide\forms\MessageBoxForm;
use ide\Ide;
use ide\systems\FileSystem;
use ide\ui\FlowListViewDecorator;
use ide\ui\ImageBox;
use php\gui\event\UXMouseEvent;
use php\gui\layout\UXHBox;
use php\gui\layout\UXVBox;
use php\gui\UXButton;
use php\gui\UXDialog;
use php\gui\UXNode;
use php\gui\UXForm;
use php\gui\UXSeparator;
use script\storage\IniStorage;
use php\gui\GUI;
use php\gui\framework\AbstractForm;
use php\gui\event\UXEvent;
use php\io\File;
use php\gui\UXComboBox;
use php\gui\SelectedItem;
use php\gui\event\UXKeyboardManager;
use php\gui\event\UXKeyEvent;
use php\gui\framework\Application;
use php\gui\JSException;
use php\gui\layout\UXAnchorPane;
use php\gui\UXAlert;
use php\gui\UXApplication;
use php\gui\UXButton;
use php\gui\UXDialog;
use php\gui\UXImage;
use php\gui\UXImageView;
use php\gui\UXMenu;
use php\gui\UXMenuItem;
use php\gui\UXSeparator;
use php\gui\UXTextArea;
use php\io\File;
use php\io\IOException;
use php\io\ResourceStream;
use php\io\Stream;
use php\lang\IllegalArgumentException;
use php\lang\Process;
use php\lang\System;
use php\lang\Thread;
use php\lang\ThreadPool;
use php\lib\arr;
use php\lib\fs;
use php\lib\Items;
use php\lib\reflect;
use php\lib\Str;
use php\time\Time;
use php\time\Timer;
use php\util\Configuration;
use php\util\Scanner;
use php\gui\UXNode;

/**
 * Class UpdateAvailableForm
 * @package ide\forms
 *
 * @property UXTextArea $descriptionField
 * @property UXLabel $nameLabel
 *
 * @property UXButton $youtubeButton
 * @property UXButton $downloadButton
 */
class SettingsForm extends AbstractIdeForm
{




    /**
     * @event button.action 
     */
    function doButtonAction(UXEvent $e = null)
    {
            $selectedTheme = $this->combobox->value;
            file_put_contents('theme\style.ini', '');
            $selectedThemeWithExtension = $selectedTheme . '.css';
            $settings = [
                'Style' => $selectedThemeWithExtension,
            ];
            $data = json_encode($settings,true);
            file_put_contents('theme\style.ini',$data);
            Execute("DevelNext.exe");
            Exit();
    }


    /**
     * @event combobox.construct 
     */
    function doComboboxConstruct(UXEvent $e = null)
    {
        $folder = "theme";
        $files = scandir($folder);
        $this->combobox->items->clear();
        foreach ($files as $file) {
            if (strtolower(fs::ext($file)) === 'css') {
                $nameWithoutExtension = fs::nameNoExt($file);
                $this->combobox->items->add($nameWithoutExtension);
            }
        }
    }

    /**
     * @event show 
     */
    function doShow(UXWindowEvent $e = null)
    {    
        $THEME = file_get_contents('theme\style.ini');
        $THEME = json_decode($THEME, true);
        $THEME = $THEME['Style'];
        $this->combobox->value = $THEME;
    }

    /**
     * @event link3.click-Left 
     */
    function doLink3ClickLeft(UXMouseEvent $e = null)
    {
        browse('https://hub.develnext.org/wiki/');
    }

    /**
     * @event linkAlt.click-Left 
     */
    function doLinkAltClickLeft(UXMouseEvent $e = null)
    {
        browse('https://github.com/deaglemeister/FXEdition');
    }

    /**
     * @event link.click-Left 
     */
    function doLinkClickLeft(UXMouseEvent $e = null)
    {
        browse('https://t.me/fxedition17');
    }



    /**
     * @event button3.action 
     */
    function doButton3Action(UXEvent $e = null)
    {    
        global $path,$pathImage;
        if ($this->comboboxAlt->value == 'Включить' or $this->comboboxAlt->value == 'Включено') {
            $Time = $this->numberField->value;
            if($path != null){
            fs::copy($path, $pathImage);
            $settings = [        
                'Splash' => true,
                'SplashImage'  => $pathImage,    
                'Splash Time' => $Time,        
            ];
            }else{
                $settings = [        
                    'Splash' => true,
                    'SplashImage'  => null,    
                    'Splash Time' => $Time,        
                ];  
            }
        } else {
            $settings = [        
                'Splash' => false,  
                'Splash Time' => false,  
            ];
        }
        
        $settings = json_encode($settings);
        file_put_contents("tools\\Settings\\Settings.ini", $settings);
        Execute("DevelNext.exe");
        Exit();
        
    }
      
    

    /**
     * @event comboboxAlt.construct 
     */
    function doComboboxAltConstruct(UXEvent $e = null)
    {
        $settings = file_get_contents('tools\Settings\Settings.ini');
        $settings = json_decode($settings,true);
       
        if ($settings['Splash'] == true){
            $this->numberField->enabled = true;
            $this->numberField->value = $settings['Splash Time'];
           $this->comboboxAlt->value = 'Включено';
        }else{
            $this->comboboxAlt->value = 'Выключено';
            $this->numberField->enabled = false;
        }
    }

    /**
     * @event comboboxAlt.action 
     */
    function doComboboxAltAction(UXEvent $e = null)
    {    
        $this->button3->enabled=true;
        if ($this->comboboxAlt->value == 'Включено' or $this->comboboxAlt->value == 'Включить'){
            $this->numberField->enabled = true;
        }else{
            $this->numberField->enabled = false;
        }
        
    }

    /**
     * @event numberField.mouseUp-Left 
     */
    function doNumberFieldMouseUpLeft(UXMouseEvent $e = null)
    {    
         $this->button3->enabled=true;
    }

    /**
     * @event buttonAlt.click-Left 
     */
    function doButtonAltClickLeft(UXMouseEvent $e = null)
    {
            global $path,$pathImage;
        
            $settings = file_get_contents('tools\\Settings\\Settings.ini');
            $settings = json_decode($settings,true);
            $PathImageCallbake = $settings['SplashImage'];
            if($PathImageCallbake == null){
                
                $path = new UXFileChooser();
                $path = $path->showOpenDialog();
                $path = $path->getPath();
                $pathImage = 'tools\\Settings\\SplashImage\\Splash.'.fs::ext($path);
                $this->button3->enabled = true;
            
        }else{
               
                $path = new UXFileChooser();
                $path = $path->showOpenDialog();
                $path = $path->getPath();
                $pathImage = 'tools\\Settings\\SplashImage\\Splash.'.fs::ext($path);
                $this->button3->enabled = true;   
                unlink($PathImageCallbake);  
            }
            
    }

    /**
     * @event link4.action 
     */
    function doLink4Action(UXEvent $e = null)
    {    
       
         $path = fs::abs('./').'\theme';
         //pre($path); показать путь к папке
         open($path);
        
    }

    /**
     * @event path_img_splash.construct 
     */
    function doPath_img_splashConstruct(UXEvent $e = null)
    {    
         
         $settings = file_get_contents('tools\\Settings\\Settings.ini');
            $settings = json_decode($settings,true);
            $PathImageCallbake = $settings['SplashImage'];
            if($PathImageCallbake == null){
                $this->path_img_splash->text = 'Укажите путь к картинке!' ;
        }else{
                $this->path_img_splash->text =fs::abs('./')."/".$PathImageCallbake  ;
            }
    }


    protected function init()
    {
        parent::init();

    }
 
    /**
     * @param $data
     * @param bool $always
     * @return bool
     */
    public function tryShow($always = false)
    {
        UXApplication::runLater(function () {
            $this->showAndWait();
        });

        return true;
    }
}
