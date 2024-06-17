<?php

namespace ide\editors\templates\other;

use platform\facades\Toaster;
use platform\toaster\ToasterMessage;
use php\gui\UXImage;
use ide\Ide;
use ide\library\IdeLibraryResource;
use ide\project\AbstractProjectTemplate;
use ide\systems\ProjectSystem;
use php\gui\UXButton;
use php\gui\UXLabel;
use php\gui\layout\UXScrollPane;
use php\gui\UXTextField;
use php\gui\UXToggleButton;
use kosogroup\liver\ui\components\UIxHBox;
use kosogroup\liver\ui\components\UIxVBox;
use php\gui\event\UXEvent;
use php\gui\framework\Application;
use php\gui\layout\UXHBox;
use php\gui\layout\UXVBox;
use php\gui\UXDirectoryChooser;
use php\gui\UXToggleGroup;
use php\gui\UXTooltip;
use php\io\File;
use php\lib\fs;
use php\util\Regex;

class NewProjectPatternList extends UXVBox{

    protected  $__TextFieldNameProject;
    protected  $__listPatternVbox;
    protected  $__labelPathFolder;
    protected  $__scrollPane;
    protected  $__textFieldPackageName;
    protected  $__pathFolder;
    protected  $__buttonCreateProject;
    protected  $__toggleGroup;
    protected  $__countPattern;
    protected  $__tab;

    public function __construct($tab)
    {
        parent::__construct();

       
        
        $this->__tab = $tab;

        $this->add((new UIxVBox([
            (new UIxHBox([
                ($this->__TextFieldNameProject = new UXTextField),
                ($this->__textFieldPackageName = new UXTextField),
                
            ])),

          
            ($this->__scrollPane = new UXScrollPane(
                (new UIxHBox([
                    
                    ($this->__listPatternVbox = (new UIxVBox([($labelPattern = new UXLabel('Выберите шаблон для создания проекта:'))])))->_setHgrow('ALWAYS')
                    
                    ->_setVgrow('ALWAYS')
                    ->_setSpacing(10)
                    ->_setPaddingTop(10)
                    ->_setPaddingLeft(7)
                    ->_setPaddingRight(7)
                   # ->_setClassesString('ui-project-list-welcome')
                ]))

            )),
            (new UIxHBox([
                ($this->__labelPathFolder = new UXLabel('Текущая папка: ' . '~\\'.fs::name(File::of(Ide::get()->getUserConfigValue('projectDirectory'))))),
                (new UIxHBox())->_setHgrow('ALWAYS'),
                ($this->__buttonCreateProject = new UXButton('Создать новый проект'))
                

            ]))->_setPaddingBottom(3)
            ->_setPaddingBottom(6)
           
            
            

        ]))->_setVgrow('ALWAYS')
            ->_setPaddingLeft(8)
            ->_setPaddingRight(8)
            ->_setMinWidth(10)
            ->_setPaddingBottom(10)
        );
        $this->__scrollPane->classesString = 'ui-project-list-welcome';
        
        $this->__buttonCreateProject->classesString = 'ui-button-create-project';

        $labelPattern->classesString = 'ui-text';
  
        $this->__labelPathFolder->classesString = 'ui-text-secondary';

        $this->__TextFieldNameProject->classesString = 'custom-text-field-welcome-create';
        $this->__textFieldPackageName->classesString = 'custom-text-field-welcome-namespace';

        $this->__textFieldPackageName->text  = 'app';
        $this->__textFieldPackageName->promptText = 'namespace';
        $this->__pathFolder = Ide::get()->getUserConfigValue('projectDirectory');

        $this->__labelPathFolder->on('click',function(){
            $path = (new UXDirectoryChooser())->execute();
        
            if ($path !== null) {
                $this->__labelPathFolder->text = 'Текущая папка: ' . $path;
        
                $this->__pathFolder = $path;
                Ide::get()->setUserConfigValue('projectDirectory', $path);
            }
        });
        UXHBox::setHgrow($this->__TextFieldNameProject,'ALWAYS');
        UXVBox::setVgrow($this->__scrollPane,'ALWAYS');
        $this->__scrollPane->fitToWidth = true; 
        $this->__scrollPane->fitToHeight = true; 

        $this->__toggleGroup = new UXToggleGroup;


        $this->__TextFieldNameProject->promptText = 'Введите название для вашего проекта';
        
        $this->__buttonCreateProject->minWidth = '200';
        $this->__buttonCreateProject->font->bold;
        $this->__buttonCreateProject->cursor = 'HAND';
        $this->__buttonCreateProject->height = '20';
        $this->__buttonCreateProject->on('action',function(){
           

            $template = $this->__toggleGroup->selected;

            if(!$template or !is_object($template)){
                $tm = new ToasterMessage();
                $iconImage = new UXImage('res://resources/expui/icons/fileTypes/warning.png');
                $tm
                    ->setIcon($iconImage)
                    ->setTitle('Менеджер по работе с проектами')
                    ->setDescription(_('project.new.alert.select.template'))
                    ->setClosable(3000, true);
                    Toaster::show($tm);
                return;
            }

            $template = $template->data('template');

            if(!$template or !is_object($template)){
                $tm = new ToasterMessage();
                $iconImage = new UXImage('res://resources/expui/icons/fileTypes/warning.png');
                $tm
                    ->setIcon($iconImage)
                    ->setTitle('Менеджер по работе с проектами')
                    ->setDescription(_('project.new.alert.select.template'))
                    ->setClosable(3000, true);
                    Toaster::show($tm);
                    return;
            }

    
            $path = $this->__pathFolder;
           
            $name  = $this->__TextFieldNameProject->text;

            $package =  $this->__textFieldPackageName->text;
    
            if (!$name) {
                $this->__TextFieldNameProject->classesString = 'ui-error-btn';
                $tm = new ToasterMessage();
                $iconImage = new UXImage('res://resources/expui/icons/fileTypes/error.png');
                $tm
                    ->setIcon($iconImage)
                    ->setTitle('Менеджер по работе с проектами')
                    ->setDescription(_('project.new.error.name.required'))
                    ->setClosable(3000, true);
                    Toaster::show($tm);
                    return;
            }else {
                 $this->__TextFieldNameProject->classesString = 'custom-text-field-welcome-create';
            }
    
            if (!fs::valid($name)) {
                $this->__TextFieldNameProject->classesString = 'custom-text-field-welcome-create';
                $tm = new ToasterMessage();
                $iconImage = new UXImage('res://resources/expui/icons/fileTypes/error.png');
                $tm
                    ->setIcon($iconImage)
                    ->setTitle('Менеджер по работе с проектами')
                    ->setDescription(_('project.new.error.name.required'))
                    ->setClosable(3000, true);
                    Toaster::show($tm);
                    return;
            }
    
           
    
            $regex = new Regex('^[a-z\\_]{2,15}$');
    
            if (!$regex->test($package)) {

                $this->__TextFieldNameProject->classesString = 'ui-error-btn';

                $tm = new ToasterMessage();
                $iconImage = new UXImage('res://resources/expui/icons/fileTypes/error.png');
                $tm
                    ->setIcon($iconImage)
                    ->setTitle(_('project.new.error.package.invalid') . "\n* " . _('project.new.error.package.invalid.description'))
                    ->setDescription(_('project.new.error.name.required'))
                    ->setClosable(3000, true);
                    Toaster::show($tm);
                    return;
            }
    
            if ($template instanceof IdeLibraryResource) {
                ProjectSystem::import($template->getPath(), "$path/$name", $name);
    
                //$this->hide();
            } else {
                //$this->hide();
                $filename = File::of("$path/$name/$name.dnproject");
                ProjectSystem::close(false);
    
                uiLater(function () use ($template, $filename, $package) {
                    Application::get()->getMainForm()->showPreloader('Создание проекта ...');
                    try {

                            ProjectSystem::create($template, $filename, $package);

                    } finally {
                        Application::get()->getMainForm()->hidePreloader();
                    }
                });
            }
        });


    }
        public function addPattern(AbstractProjectTemplate $template)
        {
            
            $tgb = new UXToggleButton($template->getName());
            $tgb->classesString = 'ui-tgb-new-project';
            $tgb->padding = 10;
            $tgb->graphic = $template instanceof AbstractProjectTemplate ? Ide::get()->getImage($template->getIcon32()) : ico('programEx32');
            $tooltip = new UXTooltip();
            $tooltip->text = $template->getDescription();
            
            $tgb->tooltip= $tooltip;
            $tgb->data('template',$template);
            $tgb->alignment = 'CENTER_LEFT';
            UXVBox::setVgrow($tgb,'ALWAYS');
            $tgb->maxWidth = 2000;
            $tgb->height = 32;
            $tgb->toggleGroup = $this->__toggleGroup;
            $tgb->on('action', function(UXEvent $e)  {
                if (!$e->target->selected) {
                    $e->target->selected = true;
                }
            });
            
            $this->__listPatternVbox->add($tgb);
            
            $tgb = $this->__tab->data('nodeTgb');

            $count = $this->__listPatternVbox->children->count();
            

            $tgb->text = "Новый проект ({$count})";
            $this->__tab->text = "Новый проект ({$count})";
        }
        }