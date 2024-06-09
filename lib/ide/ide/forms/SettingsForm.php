<?php
namespace ide\forms;

use ide\forms\SettingsForm;
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
use ide\forms\malboro\Toasts;

use ide\forms\malboro\Updates;

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
    public $path_to_git = '';
    public $path_to_rep = '';
    public $name_to_rep = '';
    
    /**
     * @event show 
     */
    function doShow(UXWindowEvent $e = null)
    {    
        $THEME = file_get_contents('theme\style.ini');
        $THEME = json_decode($THEME, true);
        $THEME = $THEME['Style'];
        $this->combobox->value = $THEME;
        $this->username->text = System::getProperty('user.name');
    }


    /**
     * @event edit.globalKeyUp-Enter 
     */
    function doEditGlobalKeyUpEnter(UXKeyEvent $e = null)
    {    
        $command = $this->edit->text;
        
        switch($command) {
            case "load NewProjectForm":
                $this->log->text .= "\n- $command\nФорма открытия проекта загружена";
                $this->edit->text = "";             
                app()->showForm('NewProjectForm');
                break;
            case "clear":
                $this->log->text = "\n- $command\nКонсоль очищена";
                $this->edit->text = "";    
                break;
            case "true check_update":
                $this->log->text = "\n- $command\nПросмотр версий активирована во вкладке Обновления";
                $this->edit->text = "";    
                app()->form('SettingsForm')->check_last_update->show();
                break;
            case "load CatalogProjects":
            $this->edit->text = "";  
            $path = fs::abs('./').'\library\projects';
            open($path);
            break;
            case "fxreload":
            $this->edit->text = "";
            Execute("DevelNext.exe");
            Exit();
            break;    
            case "help":
                $this->log->text .= "\n- $command\n`load NewProjectForm` - Открыть форму проекта
                \n`clear` - Очистить консоль
                \n`load ActionArgumentsDialog` - Открыть форму
                \n`load CatalogProjects` - Открыть папку с демо-проектами
                \n`fxreload` - Перезапустить среду";
                $this->edit->text = "";                
                break;
            default: 
                $this->log->text .= "\n- $command\nНезвестная комманда";
                break;
        }
    }

    /**
     * @event button6.action 
     */
    function doButton6Action(UXEvent $e = null)
    {    
         $path = fs::abs('./').'\theme';
         open($path);
    }

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
     * @event path_img_splash.construct 
     */
    function doPath_img_splashConstruct(UXEvent $e = null)
    {
         
         $settings = file_get_contents('tools\\Settings\\Settings.ini');
            $settings = json_decode($settings,true);
            $PathImageCallbake = $settings['SplashImage'];
            if($PathImageCallbake == null){
                $this->path_img_splash->text = _('select.folder.image') ;
        }else{
                $this->path_img_splash->text =fs::abs('./')."/".$PathImageCallbake  ;
            }
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
           $this->comboboxAlt->value = _('OnSelect');
        }else{
            $this->comboboxAlt->value = _('OffSelect');
            $this->numberField->enabled = false;
        }
    }

    /**
     * @event comboboxAlt.action 
     */
    function doComboboxAltAction(UXEvent $e = null)
    {
        $this->button3->enabled=true;
        if ($this->comboboxAlt->value == _('OnSelect') or $this->comboboxAlt->value == _('OnCombo')){
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
     * @event button3.action 
     */
    function doButton3Action(UXEvent $e = null)
    {
        global $path,$pathImage;
        if ($this->comboboxAlt->value == _('OnCombo') or $this->comboboxAlt->value == _('OnSelect')) {
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
     * @event button7.action 
     */
    function doButton7Action(UXEvent $e = null)
    {    
         $path = fs::abs('./').'\tools\Settings\AllImagesSplash';
         open($path);
    }


    /**
     * @event link4.action 
     */
    function doLink4Action(UXEvent $e = null)
    {
        browse('https://github.com/deaglemeister/FXEdition');
    }

    /**
     * @event link5.action 
     */
    function doLink5Action(UXEvent $e = null)
    {
        browse('https://t.me/+QZkVuez1ln45YjVi');
    }

    /**
     * @event link6.action 
     */
    function doLink6Action(UXEvent $e = null)
    {
        browse('https://t.me/fxedition17');
    }

    /**
     * @event link8.action 
     */
    function doLink8Action(UXEvent $e = null)
    {
        browse('https://www.donationalerts.com/r/deaglemelster');
    }

    /**
     * @event link9.action 
     */
    function doLink9Action(UXEvent $e = null)
    {
        browse('https://github.com/TsSaltan/develnext-17');
    }

    /**
     * @event button8.action 
     */
    function doButton8Action(UXEvent $e = null)
    {    
        Execute("DevelNext.exe");
        Exit();
    }

    /**
     * @event button4.action 
     */
    function doButton4Action(UXEvent $e = null)
    {    
      $class = new Toasts;
      $class->showToast(_('tcclear'), _('memorteclear'), "#FF4F44");
      System::gc();
    }

    /**
     * @event button5.action 
     */
    function doButton5Action(UXEvent $e = null)
    {    
        $class = new Updates;
        $class->checkUpdates();
    }

    /**
     * @event button10.action 
     */
    function doButton10Action(UXEvent $e = null)
    {    
        fs::clean('.\library\projects');
        $class = new Toasts;
        $class->showToast(_('deletefilesf'), _('dewldae'), "#FF4F44");
    }

    /**
     * @event button11.action 
     */
    function doButton11Action(UXEvent $e = null)
    {    
         $path = fs::abs('./').'\library\projects';
         open($path);
    }

    /**
     * @event button9.action 
     */
    function doButton9Action(UXEvent $e = null)
    {
        $path = fs::abs('./').'\library\bundles';
        open($path);
    }

    /**
     * @event button12.action 
     */
    function doButton12Action(UXEvent $e = null)
    {
        fs::clean('.\library\bundles');
        $class = new Toasts;
        $class->showToast(_('deletefilesf'), _('dewldae2'), "#FF4F44");
    }

    /**
     * @event button13.action 
     */
    function doButton13Action(UXEvent $e = null)
    {
        Execute("DevelNext-debug.exe");
        Exit();
    }

    /**
     * @event button14.action 
     */
    function doButton14Action(UXEvent $e = null)
    {
        $path = fs::abs('./projects');
        $path_to_git = fs::abs('./Git/bin/git.exe');       
        
        $github_repo = $this->repo->text;
        $github_repo = parse_url($this->repo->text);   
        
        
        
        $folder_choose = new UXDirectoryChooser;
        $folder_choose->initialDirectory = fs::abs('./projects');
        if ($folder_choose->execute()){
            $path = $folder_choose->execute()->getAbsolutePath()."/".explode("/", trim($github_repo['path'], "/"))[count(explode("/", trim($github_repo['path'], "/")))-1];
        }
        
                
        $process = new Process(array_merge([$path_to_git], ['clone',$this->repo->text,$path]));
        $process = $process->start();
        $thread = new Thread(function() use ($process)
        {
            $process->getOutput()
                ->eachLine(function($line) {
                    Logger::info($line);
                });
        
            $process->getError()
                ->eachLine(function($line) {
                    Logger::info($line);
                });
            switch ($process->getExitValue()){
                case (0):
                    Logger::info("Конец");
                    break;
                default:
                    Logger::error("Какие-то приколы");
                    break;
                        
            }
        });
        $thread->start();
    }

    /**
     * @event button15.action 
     */
    function doButton15Action(UXEvent $e = null)
    {
        $path = fs::abs('./projects');    
        $path_to_git = fs::abs('./Git/bin/git.exe');  
        if (!$this->path_to_rep){       
            
            $folder_choose = new UXDirectoryChooser;
            $folder_choose->initialDirectory = fs::abs('./projects');
            if ($folder_choose->execute()){
                $this->name_to_rep = $folder_choose->execute()->getName();
                $this->path_to_rep = $folder_choose->execute()->getAbsolutePath();
            }
        }
        $arg = [
            '--work-tree='.$this->path_to_rep,
            '--git-dir='.$this->path_to_rep.'/.git',
            'status'        
        ];
        
        $this->status->text = '';   
        $this->buttonAlt->text = $this->name_to_rep;
        var_dump(implode(' ',array_merge([$path_to_git], $arg)));
        $process = new Process(array_merge([$path_to_git], $arg));
        $process = $process->start();
        $thread = new Thread(function() use ($process)
        {
                
            $process->getInput()
                ->eachLine(function($line) {
                    Logger::info($line);
                    uiLater(function () use($line){
                        $this->status->text .= "\n".$line;
                    });
                });
        
            $process->getError()
                ->eachLine(function($line) {
                    Logger::info($line);
                    uiLater(function () use($line){
                        $this->status->text .= "\n".$line;
                    });
                });
            switch ($process->getExitValue()){
                case (0):
                    Logger::info("Конец");
                    break;
                default:
                    Logger::error("Какие-то приколы");
                    break;
                        
            }
        });
        $thread->start();
    }

    /**
     * @event button16.action 
     */
    function doButton16Action(UXEvent $e = null)
    {
        $path = fs::abs('./projects');
        $path_to_git = fs::abs('./Git/bin/git.exe');      
        
        $arg = [
            '--work-tree='.$this->path_to_rep,
            '--git-dir='.$this->path_to_rep.'/.git',
            'add',
            '.'
        ];
        
        $this->status->text = '';   
        $this->buttonAlt->text = $this->name_to_rep;
        var_dump(implode(' ',array_merge([$path_to_git], $arg)));
        $process = new Process(array_merge([$path_to_git], $arg));
        $process = $process->start();
        $thread = new Thread(function() use ($process)
        {
                
            $process->getInput()
                ->eachLine(function($line) {
                    Logger::info($line);
                    uiLater(function () use($line){
                        $this->status->text .= "\n".$line;
                    });
                });
        
            $process->getError()
                ->eachLine(function($line) {
                    Logger::info($line);
                    uiLater(function () use($line){
                        $this->status->text .= "\n".$line;
                    });
                });
            switch ($process->getExitValue()){
                case (0):
                    Logger::info("Конец");
                    break;
                default:
                    Logger::error("Какие-то приколы");
                    break;
                        
            }
        });
        $thread->start();
    }

    /**
     * @event button17.action 
     */
    function doButton17Action(UXEvent $e = null)
    {
        $path = fs::abs('./projects');
        $path_to_git = fs::abs('./Git/bin/git.exe');      
        
        
        $arg = [
            '--work-tree='.$this->path_to_rep,
            '--git-dir='.$this->path_to_rep.'/.git',
            'commit',
            '-am',
            '"'.$this->edit->text.'"'        
        ];
        
        $this->status->text = '';   
        $this->buttonAlt->text = $this->name_to_rep;
        var_dump(implode(' ',array_merge([$path_to_git], $arg)));
        $process = new Process(array_merge([$path_to_git], $arg));
        $process = $process->start();
        $thread = new Thread(function() use ($process)
        {
                
            $process->getInput()
                ->eachLine(function($line) {
                    Logger::info($line);
                    uiLater(function () use($line){
                        $this->status->text .= "\n".$line;
                    });
                });
        
            $process->getError()
                ->eachLine(function($line) {
                    Logger::info($line);
                    uiLater(function () use($line){
                        $this->status->text .= "\n".$line;
                    });
                });
            switch ($process->getExitValue()){
                case (0):
                    Logger::info("Конец");
                    break;
                default:
                    Logger::error("Какие-то приколы");
                    break;
                        
            }
        });
        $thread->start();
    }

    /**
     * @event button18.action 
     */
    function doButton18Action(UXEvent $e = null)
    {
        $path = fs::abs('./projects');
        $path_to_git = fs::abs('./Git/bin/git.exe');      
        
        $arg = [
            '--work-tree='.$this->path_to_rep,
            '--git-dir='.$this->path_to_rep.'/.git',
            'push'        
        ];
        
        $this->status->text = '';   
        $this->buttonAlt->text = $this->name_to_rep;
        var_dump(implode(' ',array_merge([$path_to_git], $arg)));
        $process = new Process(array_merge([$path_to_git], $arg));
        $process = $process->start();
        $thread = new Thread(function() use ($process)
        {
                
            $process->getInput()
                ->eachLine(function($line) {
                    Logger::info($line);
                    uiLater(function () use($line){
                        $this->status->text .= "\n".$line;
                    });
                });
        
            $process->getError()
                ->eachLine(function($line) {
                    Logger::info($line);
                    uiLater(function () use($line){
                        $this->status->text .= "\n".$line;
                    });
                });
            switch ($process->getExitValue()){
                case (0):
                    Logger::info("Конец");
                    break;
                default:
                    Logger::error("Какие-то приколы");
                    break;
                        
            }
        });
        $thread->start();
    }

    /**
     * @event button19.action 
     */
    function doButton19Action(UXEvent $e = null)
    {
        $path = fs::abs('./').'\library\bundles';
        open($path);
    }

    /**
     * @event checkbox.click-Left 
     */
    function doCheckboxClickLeft(UXMouseEvent $e = null)
    {    
        
    }

    /**
     * @event combobox3.action 
     */
    function doCombobox3Action(UXEvent $e = null)
    {    
         $this->button3->enabled=true;
        if ($this->combobox3->value == _('OnSelect') or $this->comboboxAlt->value == _('OnCombo')){
            $this->combobox3->enabled = true;
        }else{
            $this->combobox3->enabled = false;
        }
    }

    /**
     * @event combobox4.action 
     */
    function doCombobox4Action(UXEvent $e = null)
    {    
        $this->button3->enabled=true;
        if ($this->combobox3->value == _('OnSelect') or $this->comboboxAlt->value == _('OnCombo')){
            $this->combobox3->enabled = true;
        }else{
            $this->combobox3->enabled = false;
        
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
