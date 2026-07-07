<?php

namespace ide\systems;



use ide\forms\OpenProjectForm;

use ide\Ide;

use ide\project\Project;
use ide\project\TreeIconResolver;

use php\gui\event\UXMouseEvent;

use php\gui\layout\UXHBox;

use php\gui\UXButton;

use php\gui\UXImageView;

use php\gui\UXLabel;

use php\lib\fs;
use php\lib\str;



/**

 * Вкладки проектов в titlebar: «Все проекты», открытые проекты, «+».

 */

class FxeProjectTabsSystem

{

    /** @var bool */

    protected static $init = false;



    /** @var UXHBox|null */

    protected static $bar;



    /** @var array[] projectFile => ['title' => string, 'icon' => string] */

    protected static $openProjects = [];



    /** @var string 'welcome' или путь к .dnproject */

    protected static $activeKey = 'welcome';



    /** @var bool не удалять проект из списка вкладок при close (переключение) */

    protected static $switchingProject = false;



    /**

     * @param UXHBox $bar

     */

    public static function init(UXHBox $bar)

    {

        if (static::$init) {

            return;

        }



        static::$init = true;

        static::$bar = $bar;



        Ide::get()->bind('openProject', function (Project $project) {

            static::onProjectOpened($project);

        });



        Ide::get()->bind('afterCloseProject', function (Project $project) {

            static::onProjectClosed($project);

        });



        // Проект мог открыться в afterShow до init() — событие openProject уже прошло.

        if ($project = Ide::project()) {

            static::onProjectOpened($project);

        } else {

            static::$activeKey = 'welcome';

            static::rebuild();

        }

    }



    public static function showWelcome()

    {

        static::$switchingProject = true;



        try {

            if (Ide::project()) {

                ProjectSystem::close(false);

            }



            FileSystem::open('~welcome');

        } finally {

            static::$switchingProject = false;

        }



        static::$activeKey = 'welcome';

        static::rebuild();

    }



    /**

     * @param string $projectFile

     */

    public static function activateProject($projectFile)

    {

        if ($projectFile === static::$activeKey) {

            return;

        }



        if ($projectFile === 'welcome') {

            static::showWelcome();

            return;

        }



        $projectFile = fs::normalize($projectFile);



        if (Ide::project() && fs::normalize(Ide::project()->getProjectFile()) === $projectFile) {

            static::$activeKey = $projectFile;

            static::rebuild();

            return;

        }



        if (!isset(static::$openProjects[$projectFile])) {

            static::$openProjects[$projectFile] = [

                'title' => fs::nameNoExt($projectFile),

                'icon' => 'tree:projection',

            ];

        }



        static::$switchingProject = true;



        try {

            ProjectSystem::open($projectFile, true, true, false, true);

        } finally {

            static::$switchingProject = false;

        }



        static::$activeKey = $projectFile;

        static::rebuild();

    }



    /**

     * @param Project $project

     */

    public static function onProjectOpened(Project $project)

    {

        $file = fs::normalize($project->getProjectFile());

        static::$openProjects[$file] = [

            'title' => $project->getName(),

            'icon' => static::resolveProjectIcon($project),

        ];

        static::$activeKey = $file;

        static::rebuild();

    }



    /**

     * @param Project $project

     */

    public static function onProjectClosed(Project $project)

    {

        $file = fs::normalize($project->getProjectFile());



        if (!static::$switchingProject) {

            unset(static::$openProjects[$file]);

        }



        if (static::$activeKey === $file && !static::$switchingProject) {

            static::$activeKey = 'welcome';

        }



        static::rebuild();

    }



    /**

     * @param Project|null $project

     * @return string

     */

    protected static function resolveProjectIcon(Project $project = null)
    {
        return 'tree:projection';
    }



    /**

     * @param string $projectFile

     */

    public static function closeProjectTab($projectFile)

    {

        $projectFile = fs::normalize($projectFile);

        unset(static::$openProjects[$projectFile]);



        if (Ide::project() && fs::normalize(Ide::project()->getProjectFile()) === $projectFile) {

            static::showWelcome();

            return;

        }



        if (static::$activeKey === $projectFile) {

            static::$activeKey = 'welcome';

        }



        static::rebuild();

    }



    public static function refresh()
    {
        static::rebuild();
    }

    protected static function rebuild()

    {

        if (!static::$bar) {

            return;

        }



        static::$bar->children->clear();

        static::$bar->children->add(static::makeTab(

            'Все проекты',

            'icons/library16.png',

            static::$activeKey === 'welcome',

            function () {

                static::showWelcome();

            }

        ));



        foreach (static::$openProjects as $file => $info) {

            $title = is_array($info) ? $info['title'] : $info;

            $icon = is_array($info) ? $info['icon'] : 'icons/greenDocument16.png';



            static::$bar->children->add(static::makeTab(

                $title,

                $icon,

                static::$activeKey === $file,

                function () use ($file) {

                    static::activateProject($file);

                },

                function () use ($file) {

                    static::closeProjectTab($file);

                }

            ));

        }



        $add = new UXButton('+');

        $add->text = '+';

        $add->classes->add('fxe-project-tab-add');

        $add->tooltipText = 'Открыть проект';

        $add->on('action', function () {

            static $dialog = null;



            if (!$dialog) {

                $dialog = new OpenProjectForm();

                $dialog->owner = Ide::get()->getMainForm();

            }



            $dialog->showAndWait();

        });



        static::$bar->children->add($add);

    }



    /**

     * @param string $title

     * @param string $iconPath

     * @param bool $selected

     * @param callable $action

     * @param callable|null $closeAction

     * @return UXHBox

     */

    protected static function makeTab($title, $iconPath, $selected, callable $action, callable $closeAction = null)

    {

        $box = new UXHBox();

        $box->classes->add('fxe-project-tab');

        $box->alignment = 'CENTER_LEFT';

        $box->spacing = 6;



        if ($selected) {

            $box->classes->add('fxe-project-tab-selected');

        }



        if ($iconPath) {
            if (str::startsWith($iconPath, 'tree:')) {
                $treeIcon = TreeIconResolver::loadIcon(str::sub($iconPath, 5));
                if ($treeIcon) {
                    $treeIcon->classes->add('fxe-project-tab-icon');
                    $box->children->add($treeIcon);
                }
            } else {
                $icon = new UXImageView();
                $icon->image = Ide::get()->getImage($iconPath, [14, 14])->image;
                $icon->fitWidth = 14;
                $icon->fitHeight = 14;
                $icon->preserveRatio = true;
                $icon->classes->add('fxe-project-tab-icon');
                $box->children->add($icon);
            }
        }



        $label = new UXLabel($title);

        $label->classes->add('fxe-project-tab-label');

        $box->children->add($label);



        if ($closeAction) {
            $close = new UXButton('');
            $close->classes->add('fxe-project-tab-close');
            $close->tooltipText = 'Закрыть проект';
            $close->graphic = Ide::get()->getImage('icons/clear16.png', [10, 10]);
            $close->on('action', function () use ($closeAction) {
                $closeAction();
            });
            $box->children->add($close);
        }



        $box->on('click', function (UXMouseEvent $e) use ($action) {
            if ($e->button !== 'PRIMARY') {
                return;
            }

            $action();
        });



        return $box;

    }

}


