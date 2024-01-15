<?php
namespace ide\editors;

use ide\commands\NewProjectCommand;
use ide\commands\OpenProjectCommand;
use ide\forms\OpenProjectForm;
use ide\Ide;
use php\gui\UXLoader;
use php\gui\UXNode;

class WelcomeEditor extends AbstractEditor
{
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

    /**
     * @return UXNode
     */
    public function makeUi()
    {
        $loader = new UXLoader();

        $layout = $loader->load('res://.forms/blocks/_Welcome.fxml');

        Ide::get()->getL10n()->translateNode($layout);

        $layout->lookup('#createProjectButton')->on('click', function () {
            Ide::get()->executeCommand(NewProjectCommand::class);
        });

        $layout->lookup('#openProjectButton')->on('click', function () {
            Ide::get()->executeCommand(OpenProjectCommand::class);
        });
        $layout->lookup('#openTg')->on('click', function () {
            browse('https://t.me/fxedition17');
        });
        $layout->lookup('#openSupportMoney')->on('click', function () {
            browse('https://www.donationalerts.com/r/fxedition');
        });
        $layout->lookup('#github')->on('click', function () {
            browse('https://github.com/deaglemeister/FXEdition');
        });
        $layout->lookup('#listbundels')->on('click', function () {
            browse('https://github.com/deaglemeister/bundels');
        });
        $layout->lookup('#docymentss')->on('click', function () {
            browse('https://hub.develnext.org/wiki/');
        });


        return $layout;
    }
}