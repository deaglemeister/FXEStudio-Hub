<?php

namespace ide\editors\templates;

use platform\facades\Toaster;
use platform\toaster\ToasterMessage;
use php\gui\UXImage;
use ide\editors\templates\other\ImageBoxOpenProject;
use ide\editors\templates\other\modalWelcome;
use ide\editors\templates\other\ProjectList;
use ide\editors\templates\other\TextFieldSearch;
use ide\Ide;
use ide\systems\ProjectSystem;
use ide\ui\Notifications;
use ide\utils\FileUtils;
use kosogroup\liver\ui\components\UIxVBox;
use php\gui\event\UXMouseEvent;
use php\gui\layout\UXVBox;
use php\gui\UXApplication;
use php\gui\UXContextMenu;
use php\gui\UXMenuItem;
use php\io\File;
use php\lang\Thread;
use php\lib\arr;
use php\lib\str;

class FavoriteProjects {
    
    public function makeFavorites($tab)
    {
        $allProject = [];
        $projectList = null;
        $layout = (new UIxVBox([
            (new TextFieldSearch)->makeTextFieldSearch($allProject,$projectList),
            ($projectList = new ProjectList)
        ]));

        UXVBox::setVgrow($projectList,'ALWAYS');

        $projectDirectory = File::of(Ide::get()->getUserConfigValue('projectDirectory'));
        $projects = [];

        foreach ($projectDirectory->findFiles() as $file) {
            if ($file->isDirectory()) {
                $project = arr::first($file->findFiles(function (File $directory, $name) {
                    return Str::endsWith($name, '.dnproject');
                }));

                if ($project) {
                    $projects[] = $project;
                }
            }
        }

        foreach ($projects as $project) {
            $imageBox = new ImageBoxOpenProject(20, 20, $project);
            $imageBox->data('dataProject', $project);
            $imageBox->on('click', function (UXMouseEvent $e) use ($project,$tab,$projectList) {
                if($e->button == 'PRIMARY'){
                    if(!$e->isDoubleClick()){
                        return;
                    }
                    UXApplication::runLater(function () use ($e, $project) {
                        $this->_doProjectListClick($e, $project);
                    });
                }elseif($e->button =='SECONDARY'){
                    $contextMenu = new UXContextMenu();
                    
                    $menuItemDelete = new UXMenuItem('Удалить'); 
                        
                    $menuItemDelete->on('action', function () use ($project,$tab,$projectList) {
                        $closure = function() use($project,$tab,$projectList){
                        

                            if ($project && $project->exists()) {
                                $directory = File::of($project)->getParent();
                                     $tm = new ToasterMessage();
                                    $iconImage = new UXImage('res://resources/expui/icons/fileTypes/succes.png');
                                    $tm
                                    ->setIcon($iconImage)
                                    ->setTitle('Менеджер по работе с проектами')
                                    ->setDescription(_('Ваш проект был успешно удалён.'))
                                    ->setClosable(2000);
                                    Toaster::show($tm);
                                if (Ide::project()
                                    && FileUtils::normalizeName(Ide::project()->getRootDir()) == FileUtils::normalizeName($directory)) {
                                    ProjectSystem::closeWithWelcome();
                                    
                                }
                                (new Thread(function()use($directory,$tab,$projectList){
                                    if (!FileUtils::deleteDirectory($directory)) {
                                        uiLater(function(){
                                        $tm = new ToasterMessage();
                                        $iconImage = new UXImage('res://resources/expui/icons/fileTypes/error.png');
                                        $tm
                                        ->setIcon($iconImage)
                                        ->setTitle('Менеджер по работе с проектами')
                                        ->setDescription(_('Папка проекта была не удалена полностью, возможно она занята другими программами.'))
                                        ->setClosable(2000);
                                        Toaster::show($tm);
                                    });
                                    }else{
                                        uiLater(function()use($tab,$projectList){
                                            $content = $this->makeFavorites($tab);
                                            $tab->content = null;
                                            $tab->content = $content;
                                            $tgb = $tab->data('nodeTgb');
                                            if($projectList->getCountChildren() <= 0){
                                                $count = 0;
                                            }else{ 
                                                $count = $projectList->getCountChildren() - 1;
                                            }

                                            $tgb->text = "Избранные проекты ({$count})";
                                            $tab->text = "Избранные проекты ({$count})";
                                           
                                        });
                                    }
                                    
                                }))->start();
                               
                            }
                       
                        };
                        (new modalWelcome())->showModal(['text'=>'Вы точно хотите удалить проект?','closure'=> $closure]);
                    });                                  
    
                    $contextMenu->items->add($menuItemDelete);   
                    $contextMenu->items->add(UXMenuItem::createSeparator()); 
                    if(!$e->target){
                        return;
                    }
                    $contextMenu->showByNode($e->target, $e->x, $e->y); 
                }
                
            });

            $projectList->addChildren($imageBox);
        }

        $allProject = $projectList->getArrayChildren();

        $tab->text = "Избранные проекты ({$projectList->getCountChildren()})";

        return $layout;
    }


    private function _doProjectListClick(UXMouseEvent $e, FILE $file)
    {
        if ($e->clickCount > 1) {
            if ($file && $file->exists()) {
                Ide::get()->getMainForm()->showPreloader(_('Открываем проект..'));
                waitAsync(100, function () use ($file) {
                    try {
                        if (ProjectSystem::open($file)) {
                            $tm = new ToasterMessage();
                            $iconImage = new UXImage('res://resources/expui/icons/fileTypes/succes.png');
                            $tm
                            ->setIcon($iconImage)
                            ->setTitle('Менеджер по работе с проектами')
                            ->setDescription(_('Ваш проект был успешно загружен.'))
                            ->setClosable(2000);
                            Toaster::show($tm);
                        }
                    } finally {
                        Ide::get()->getMainForm()->hidePreloader();
                    }
                });
            } else {
                $tm = new ToasterMessage();
                $iconImage = new UXImage('res://resources/expui/icons/fileTypes/error.png');
                $tm
                ->setIcon($iconImage)
                ->setTitle('Менеджер по работе с проектами')
                ->setDescription(_('Произошла ошибка открытие проекта.'))
                ->setClosable(2000);
                Toaster::show($tm);
            }
        }
    }
}