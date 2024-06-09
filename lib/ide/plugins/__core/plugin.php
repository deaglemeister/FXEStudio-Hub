<?php

use platform\actions\IDESandboxAction;
use platform\actions\IDEShutdownAction;
use platform\plugins\FXEPlugin;
use platform\plugins\traits\Actions;

use platform\actions\PreferenceOpenAction;
use platform\actions\ProjectCloseAction;
use platform\actions\ProjectExportAction;
use platform\actions\ProjectNewAction;
use platform\actions\ProjectOpenAction;
use platform\actions\ProjectSaveAction;
use platform\actions\Tree_CreateFileAction;
use platform\actions\Tree_CreatePHPClassAction;
use platform\actions\Tree_CreatePHPFileAction;
use platform\actions\Tree_EditFileAction;
use platform\actions\Tree_RemoveFileAction;
use platform\actions\Tree_RevealInFileExplorerAction;
use platform\plugins\traits\IDEMenuBarReConstructor;
use platform\plugins\traits\TreeActions;
use php\gui\UXMenu;
use php\gui\UXMenuBar;

return new class extends FXEPlugin
{
    public function getName(): string
    {
        return 'DevelNext FXE Core';
    }
    public function getDescription(): string
    {
        return '';
    }
    public function getVersion(): float
    {
        return 1.0;
    }
    public function getAuthor(): string 
    {
        return 'who-am-i';
    }

    use Actions;
    public function getActions(): array
    {
        return [
            new ProjectNewAction,
            new ProjectOpenAction,
            new ProjectSaveAction,
            new ProjectExportAction,

            new ProjectCloseAction,

            new PreferenceOpenAction,

            new IDEShutdownAction,


            new IDESandboxAction,
        ];
    }

    use TreeActions;
    public function getTreeActions(): array
    {
        return [
            new Tree_CreateFileAction,
            new Tree_CreatePHPFileAction,

            new Tree_CreatePHPClassAction,

            new Tree_EditFileAction,
            new Tree_RemoveFileAction,

            new Tree_RevealInFileExplorerAction,
        ];
    }


    use IDEMenuBarReConstructor;
    public function reConstructIDEMenuBar(UXMenuBar $menuBar) : void
    {
        $menuBar->menus->clear();

        $menuBar->menus->add($this->reConstructIDEMenuBar_newMenu("File"));
        $menuBar->menus->add($this->reConstructIDEMenuBar_newMenu("Edit"));
        $menuBar->menus->add($this->reConstructIDEMenuBar_newMenu("View"));
        $menuBar->menus->add($this->reConstructIDEMenuBar_newMenu("Go"));
        $menuBar->menus->add($this->reConstructIDEMenuBar_newMenu("Run"));
        $menuBar->menus->add($this->reConstructIDEMenuBar_newMenu("Terminal"));
        $menuBar->menus->add($this->reConstructIDEMenuBar_newMenu("Window"));
        $menuBar->menus->add($this->reConstructIDEMenuBar_newMenu("Help"));

    }

    private function reConstructIDEMenuBar_newMenu($name) : UXMenu
    {
        $menu = new UXMenu($name);
        $menu->id = 'menu' . $name;
        return $menu;
    }


};