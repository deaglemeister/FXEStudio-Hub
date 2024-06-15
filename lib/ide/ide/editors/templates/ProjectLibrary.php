<?php

namespace ide\editors\templates;

use ide\editors\templates\other\ImageBoxOpenProject;
use ide\editors\templates\other\ProjectList;
use ide\editors\templates\other\TextFieldSearch;
use ide\Ide;
use ide\systems\ProjectSystem;
use kosogroup\liver\ui\components\UIxVBox;
use php\gui\event\UXMouseEvent;
use php\gui\layout\UXVBox;
use php\gui\UXApplication;

class ProjectLibrary {

    public function makeLibraly($tab)
    {
        $allProject = [];
        $projectList = null;
        $layout = (new UIxVBox([
            (new TextFieldSearch)->makeTextFieldSearch($allProject,$projectList),
            ($projectList = new ProjectList)
        ]));

        UXVBox::setVgrow($projectList,'ALWAYS');

       
        $libraryResources = Ide::get()->getLibrary()->getResources('projects');
        foreach ($libraryResources as $resource) {
            $imageBox = new ImageBoxOpenProject(20, 20, $resource);
            $imageBox->data('dataProject', $resource);
            $imageBox->on('click', function (UXMouseEvent $e) use ($resource) {
                UXApplication::runLater(function () use ($resource) {
                    $this->_openLibralyProject($resource);
                    });
                });
               
            $projectList->addChildren($imageBox);
               
                
            }
       
        $allProject = $projectList->getArrayChildren();
        $tgb = $tab->data('nodeTgb');

        $count = $projectList->getCountChildren();
            

        $tgb->text = "Демонстрационные проекты ({$count})";
        $tab->text = "Демонстрационные проекты ({$count})";

        return $layout;
    }

    private function _openLibralyProject($resource)
    {

        

        try {
                ProjectSystem::import($resource->getPath());
                    
            } finally {
                    // to-do
                }
            
        }
    
}