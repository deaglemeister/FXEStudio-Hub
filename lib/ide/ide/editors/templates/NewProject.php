<?php

namespace ide\editors\templates;

use ide\editors\templates\other\NewProjectPatternList;
use ide\Ide;

class NewProject{
    
    public function makeNewProject($tab)
    {
         $layout = new NewProjectPatternList($tab);
        
        foreach(Ide::get()->getProjectTemplates() as $template)
        {
            $layout->addPattern($template);
        }
         return $layout;
    }
}