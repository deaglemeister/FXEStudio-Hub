<?php
namespace ide\commands\tree;

use ide\editors\AbstractEditor;
use ide\editors\menu\AbstractMenuCommand;
use ide\forms\InputMessageBoxForm;
use ide\project\ProjectTree;
use ide\systems\FileSystem;
use ide\utils\FileUtils;
use php\gui\UXDialog;
use php\lib\fs;
use php\util\Regex;

class TreeCreateExtensionFileMenuCommand extends AbstractMenuCommand
{
    /** @var ProjectTree */
    protected $tree;

    /** @var string */
    protected $extension;

    /** @var string */
    protected $title;

    /** @var string */
    protected $icon;

    /** @var string */
    protected $defaultContent;

    public function __construct(ProjectTree $tree, $title, $extension, $icon, $defaultContent = '')
    {
        $this->tree = $tree;
        $this->title = $title;
        $this->extension = $extension;
        $this->icon = $icon;
        $this->defaultContent = $defaultContent;
    }

    public function getIcon()
    {
        return $this->icon;
    }

    public function getName()
    {
        return $this->title;
    }

    public function onExecute($e = null, AbstractEditor $editor = null)
    {
        $file = $this->tree->getSelectedFullPath();

        $dialog = new InputMessageBoxForm(
            "Создание файла {$this->extension}",
            'Введите название файла (без расширения):',
            '* Только валидное имя для файла'
        );
        $dialog->setPattern(new Regex('[^\\?\\<\\>\\*\\:\\|\\"]{1,}', 'i'), 'Данное название некорректное');
        $dialog->showDialog();

        $name = $dialog->getResult();

        if (!$name) {
            return;
        }

        if (fs::ext($name) !== $this->extension) {
            $name .= '.' . $this->extension;
        }

        $target = $file->isDirectory() ? "$file/$name" : "{$file->getParent()}/$name";
        $target = fs::normalize($target);

        if (fs::exists($target)) {
            UXDialog::showAndWait('Файл или папка с таким названием уже существует.', 'ERROR');
            $this->onExecute($e, $editor);
            return;
        }

        FileUtils::put($target, $this->defaultContent);

        if (!fs::isFile($target)) {
            UXDialog::showAndWait("Невозможно создать файл с таким названием.\n -> $target", 'ERROR');
            return;
        }

        $this->tree->expandSelected();
        FileSystem::open($target);
    }

    public function onBeforeShow($item, AbstractEditor $editor = null)
    {
        parent::onBeforeShow($item, $editor);
        $item->disable = !$this->tree->hasSelectedPath();
    }
}
