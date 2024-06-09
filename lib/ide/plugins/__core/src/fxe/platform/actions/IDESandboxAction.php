<?php

namespace platform\actions;


use fxe\platform\plugins\AnAction;
use ide\editors\CodeEditor;
use ide\Ide;
use ide\systems\IdeSystem;
use php\gui\layout\UXVBox;

class IDESandboxAction extends AnAction
{
    public function getName() : string
    {
        return "Open Sandbox";
    }

    public function getAccelerator()
    {
        return 'F10';
    }

    public function getCategory()
    {
        return 'run';
    }

    public function onExecute()
    {
        $ui = new UXVBox();
        $ui->spacing = 5;
        $ui->padding = 5;

        $file = IdeSystem::getFile("sandbox.php");
        $editor = new CodeEditor($file, 'php');
        $editor->setEmbedded(true);
        $editor->setSourceFile(false);
        $editor->registerDefaultCommands();
        $editor->loadContentToArea();

        if (!$editor->getValue()) {
            $editor->setValue("<?\n");
        }

        $editor->on('update', function () use ($editor) {
            $editor->save();
        });

        

        $textArea = $editor->makeUi();
        UXVBox::setVgrow($textArea, 'ALWAYS');

        $ui->add($textArea);

        Ide::get()->getMainForm()->showBottom($ui);
    }
}