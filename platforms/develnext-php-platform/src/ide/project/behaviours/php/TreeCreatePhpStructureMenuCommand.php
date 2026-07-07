<?php
namespace ide\project\behaviours\php;

use ide\editors\AbstractEditor;
use ide\editors\menu\AbstractMenuCommand;
use ide\formats\templates\PhpClassFileTemplate;
use ide\forms\InputMessageBoxForm;
use ide\Ide;
use ide\project\ProjectTree;
use ide\systems\FileSystem;
use ide\utils\FileUtils;
use php\gui\UXDialog;
use php\lib\fs;
use php\lib\str;
use php\util\Regex;

class TreeCreatePhpStructureMenuCommand extends AbstractMenuCommand
{
    /** @var ProjectTree */
    protected $tree;

    /** @var string */
    protected $structureType;

    /** @var string */
    protected $title;

    /** @var string */
    protected $icon;

    /** @var string|null */
    protected $accelerator;

    public function __construct(ProjectTree $tree, $title, $structureType, $icon, $accelerator = null)
    {
        $this->tree = $tree;
        $this->title = $title;
        $this->structureType = $structureType;
        $this->icon = $icon;
        $this->accelerator = $accelerator;
    }

    public function getIcon()
    {
        return $this->icon;
    }

    public function getName()
    {
        return $this->title;
    }

    public function getAccelerator()
    {
        return $this->accelerator;
    }

    public function onExecute($e = null, AbstractEditor $editor = null)
    {
        $file = $this->tree->getSelectedFullPath();

        $dialog = new InputMessageBoxForm(
            "Создание: {$this->title}",
            'Введите название (без расширения):',
            '* Только валидное имя для PHP-сущности'
        );
        $dialog->setPattern(new Regex('^[a-z\\_]{1}[a-z0-9\\_]{0,60}$', 'i'), 'Данное название некорректное');
        $dialog->showDialog();

        $name = $dialog->getResult();
        $project = Ide::project();

        if (!$name || !$project) {
            return;
        }

        $f = $file->isDirectory() ? "$file/$name" : "{$file->getParent()}/$name";

        if (fs::ext($f) !== 'php') {
            $f .= '.php';
        }

        $f = fs::normalize($f);

        if (fs::exists($f)) {
            UXDialog::showAndWait('Файл или папка с таким названием уже существует.', 'ERROR');
            $this->onExecute($e, $editor);
            return;
        }

        $template = new PhpClassFileTemplate(fs::nameNoExt($f), null);
        $template->setStructureType($this->structureType);

        $absoluteFile = $project->getAbsoluteFile($f);

        if ($absoluteFile->isInRootDir()) {
            $relativePath = $absoluteFile->getRelativePath($project->getSrcDirectory());

            if (!FileUtils::equalNames($relativePath, $absoluteFile)) {
                $namespace = fs::parent($relativePath);
                $namespace = str::replace($namespace, '/', '\\');

                if (Regex::match('^[a-z\\_]{1}[a-z\\_\\\\]{0,}$', $namespace, 'i')) {
                    $template->setNamespace($namespace);
                }
            }
        }

        $project->createFile($absoluteFile, $template);

        if (!fs::isFile($f)) {
            UXDialog::showAndWait("Невозможно создать файл с таким названием.\n -> $f", 'ERROR');
            return;
        }

        $this->tree->expandSelected();
        FileSystem::open($f);
    }

    public function onBeforeShow($item, AbstractEditor $editor = null)
    {
        parent::onBeforeShow($item, $editor);
        $item->disable = !$this->tree->hasSelectedPath() || !Ide::project();
    }
}
