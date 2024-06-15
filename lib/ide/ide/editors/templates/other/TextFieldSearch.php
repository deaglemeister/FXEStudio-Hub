<?php

namespace ide\editors\templates\other;

use php\gui\UXImage;
use php\gui\UXImageView;
use php\lib\fs;
use ide\editors\templates\other\UXTextFieldIcon;
class TextFieldSearch extends UXTextFieldIcon{
    
    public function __construct()
    {
        $UXImageView = new UXImageView(new UXImage('res://resources/expui/icons/search_dark.png'));
        $UXImageView->size = [16,16];
        
        parent::__construct($UXImageView);
         
    }
    public function makeTextFieldSearch(&$allProject,&$projectList)
    {
        $this->size = [10, 10];
        $this->minWidth = '500';
        $this->height = '60';
        $this->maxHeight = 30;
        $this->UXTextField->promptText = 'Поиск избранных проектов...';
        $this->classesString = 'dn-search-project';
        $this->UXTextField->observer('text')->addListener(function ($oldtext, $newText) use (&$projectList,&$allProject) {
            $projectList->clearChildren();

            foreach ($allProject as $item) {
                if ($item->data('dataProject')) {
                    $projectName = fs::pathNoExt($item->data('dataProject')->getName());

                    
                
                    if (empty($newText)) {
                        $projectList->addAllChildren($allProject);
                        break;
                    }
                    if (stripos($projectName, $newText) === 0) {
                        $projectList->addChildren($item);
                    }
                }
            }
        });
        return $this;
            
    }

    
}

