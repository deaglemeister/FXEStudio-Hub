<?php

namespace ide\editors;

use ide\editors\templates\FavoriteProjects;
use ide\editors\templates\NewProject;
use ide\editors\templates\other\WelcomeTabPane;
use ide\editors\templates\ProjectLibrary;
use ide\editors\templates\CloneRepository;
use kosogroup\liver\ui\components\UIxVBox;
use php\gui\UXImage;
use php\gui\UXImageView;
use php\gui\UXLabel;
use php\gui\UXTab;



class WelcomeEditor extends AbstractEditor
{
    protected $__tabPane;

    public function isCloseable()
    {
        return false;
    }

    public function getTitle()
    {
        return _('welcome.title');
    }

    public function isAutoClose()
    {
        return false;
    }

    public function load()
    {
        // nop.
    }

    public function save()
    {
        // nop.
    }

    public static function _makeUI($pdLeft, $pdBottom, $pdRight, $pdTop)
    {
        return (new WelcomeEditor())->makeUi($pdLeft, $pdBottom, $pdRight, $pdTop);


    }



    public function makeUi($pdLeft = 150, $pdBottom = 30, $pdRight= 150, $pdTop = 50)
    {
    



        $layout = (new UIxVBox([

            ($this->__tabPane = new WelcomeTabPane)

        ]))
            ->_setAlignment('TOP_CENTER')
            ->_setPaddingLeft($pdLeft)
            ->_setPaddingBottom($pdBottom)
            ->_setPaddingRight($pdRight)
            ->_setPaddingTop($pdTop);

        $tab = new UXTab;
        $tab->text = "Избранные проекты()";

        $content = (new FavoriteProjects())->makeFavorites($tab);
        $tab->content = $content;
        $this->__tabPane->addTab($tab);


        $tab = new UXTab;
        $tab->text = "Новый проект ()";
        $this->__tabPane->addTab($tab);
        //меняем местами,чтобы в data сохранилось
        $content = (new NewProject)->makeNewProject($tab);
        $tab->content = $content;

        $tab = new UXTab;
        $tab->text = "Демонстрационные проекты";
        
        $content = new UXLabel("Демонстрационные проекты");
        $this->__tabPane->addTab($tab);
        //меняем местами,чтобы в data сохранилось
        $tab->content = (new ProjectLibrary)->makeLibraly($tab);


        $tab = new UXTab;
        $tab->text = "Cклонировать с Git (Новое)";
        $this->__tabPane->addTab($tab);
        $content = (new CloneRepository())->makeClone();
        $tab->content = $content;

      #  $content = new UXLabel("Обучение для новичков");
        
       # $tab->content = $content;
        #$this->__tabPane->addTab($tab, ['isFake' => true]);
        return $layout;
    }



}

