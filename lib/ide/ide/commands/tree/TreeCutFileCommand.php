<?php
namespace ide\commands\tree;

use ide\editors\AbstractEditor;
use ide\editors\menu\AbstractMenuCommand;
use ide\forms\MessageBoxForm;
use ide\Ide;
use ide\project\ProjectTree;
use ide\systems\FileSystem;
use ide\utils\FileUtils;
use php\gui\UXDesktop;
use php\gui\UXDialog;
use php\lang\Process;
use php\lib\fs;
use platform\facades\Toaster;
use platform\toaster\ToasterMessage;
use ide\editors\FormEditor;
use php\gui\UXMenuItem;
use php\lib\items;
use php\gui\UXImage;

class TreeCutFileCommand extends AbstractMenuCommand
{
    protected $tree;
    protected $pinned = false;

    public function __construct(ProjectTree $tree)
    {
        $this->tree = $tree;
    }

    public function getIcon()
    {
        return 'icons/folder_dark.png';
    }

    public function getName()
    {
        return $this->pinned ? _('Открепить папку') : _('Закрепить папку');
    }

    public function onExecute($e = null, AbstractEditor $editor = null)
    {
        $folder = $this->tree->getSelectedFullPath();

        if ($folder && fs::isDir($folder)) {
            if ($this->pinned) {
                $this->unpinFolder($folder);
                $message = _('Папка успешно откреплена.');
            } else {
                $this->pinFolder($folder);
                $message = _('Папка успешно закреплена.');
            }

            $this->pinned = !$this->pinned;

            $tm = new ToasterMessage();
            $iconImage = new UXImage('res://resources/expui/icons/fileTypes/' . ($this->pinned ? 'succes' : 'info') . '.png');
            $tm
                ->setIcon($iconImage)
                ->setTitle(_('Менеджер по работе с деревом'))
                ->setDescription($message)
                ->setClosable(3000, true);
            Toaster::show($tm);
        } else {
            UXDialog::showAndWait(_('Выберите папку для закрепления'), 'INFO');
        }
    }

    public function onBeforeShow($item, AbstractEditor $editor = null)
    {
        parent::onBeforeShow($item, $editor);

        $folder = $this->tree->getSelectedFullPath();

        if ($folder && fs::isDir($folder)) {
            $item->disable = false;
            $item->text = $this->getName();
        } else {
            $item->disable = true;
            $item->text = $this->getName();
        }
    }

    protected function pinFolder($folder)
    {
        if (!isset($_SESSION['pinned_folders'])) {
            $_SESSION['pinned_folders'] = [];
        }

        if (!in_array($folder, $_SESSION['pinned_folders'])) {
            $_SESSION['pinned_folders'][] = $folder;
        }
    }

    protected function unpinFolder($folder)
    {
        if (isset($_SESSION['pinned_folders']) && ($key = array_search($folder, $_SESSION['pinned_folders'])) !== false) {
            unset($_SESSION['pinned_folders'][$key]);
        }
    }
}