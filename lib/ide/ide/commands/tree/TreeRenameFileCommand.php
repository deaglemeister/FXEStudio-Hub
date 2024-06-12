<?php
namespace ide\commands\tree;

use ide\editors\AbstractEditor;
use ide\editors\menu\AbstractMenuCommand;
use ide\forms\InputMessageBoxForm;
use ide\project\ProjectTree;
use php\gui\UXDialog;
use php\lib\fs;
use php\util\Regex;

class TreeRenameFileCommand extends AbstractMenuCommand
{
    protected $tree;

    public function __construct(ProjectTree $tree)
    {
        $this->tree = $tree;
    }

    public function getAccelerator()
    {
        return 'F2';
    }

    public function getIcon()
    {
        return '';
    }

    public function getName()
    {
        return _('Переименовать');
    }

    public function onExecute($e = null, AbstractEditor $editor = null)
    {
        $file = $this->tree->getSelectedFullPath();

        if (!$file) {
            UXDialog::showAndWait('Выберите файл или папку для переименования.', 'ERROR');
            return;
        }

        retry:
        $dialog = new InputMessageBoxForm('Переименование', 'Введите новое название:', '* Только валидное имя для файла');
        $dialog->setPattern(new Regex('[^\\?\\<\\>\\*\\:\\|\\"]{1,}', 'i'), 'Данное название некорректное');


        $dialog->showDialog();
        $name = $dialog->getResult();

        if ($name) {
            $parentDir = fs::parent($file);
            $newPath = fs::normalize("$parentDir/$name");

            if (fs::exists($newPath)) {
                UXDialog::showAndWait('Файл или папка с таким названием уже существует.', 'ERROR');
                goto retry;
            }

            try {
                fs::move($file, $newPath);

                // Обновление дерева проекта
                $this->tree->expandSelected();
            } catch (\Exception $ex) {
                UXDialog::showAndWait("Не удалось переименовать. Ошибка: {$ex->getMessage()}", 'ERROR');
            }
        }
    }

    public function onBeforeShow($item, AbstractEditor $editor = null)
    {
        parent::onBeforeShow($item, $editor);

        $file = $this->tree->getSelectedFullPath();
        $item->disable = !$file;
    }
}