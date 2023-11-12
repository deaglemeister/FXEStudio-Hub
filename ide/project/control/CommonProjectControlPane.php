<?php
namespace ide\project\control;
use ide\forms\InputMessageBoxForm;
use ide\systems\FileSystem;
use php\gui\layout\UXScrollPane;
use php\gui\UXNode;
use php\gui\UXLoader;
use php\gui\framework\AbstractForm;
use php\io\Stream;
use php\gui\framework\EventBinder;
use php\gui\layout\UXVBox;
use php\gui\UXLabel;
use php\gui\layout\UXAnchorPane;
use ide\Ide;
use php\gui\UXApplication;
use php\io\File;
use ide\Logger;
use php\gui\UXSeparator;
use php\gui\UXDialog;
use ide\utils\FileUtils;
use php\gui\UXDesktop;
use php\util\Regex;

/**
 * Class CommonProjectControlPane
 * @package ide\project\control
 */
class CommonProjectControlPane extends AbstractProjectControlPane
{
    /**
     * @var UXVBox
     */
    protected $content;

    /**
     * @var UXLabel
     */
    protected $projectNameLabel;

    /**
     * @var UXLabel
     */
    protected $projectDirLabel;

    /**
     * @var bool
     */
    protected $init = false;

    public function getName()
    {
        return "Проект";
    }

    public function getDescription()
    {
        return "Главные настройки";
    }

    public function getIcon()
    {
        return 'icons/myProject16.png';
    }

    /**
     * @return UXNode
     */
    public function makeUi()
    {
        $loader = new UXLoader();
        $ui = $loader->load(Stream::of(AbstractForm::DEFAULT_PATH . 'blocks/_ProjectTab.fxml'));
        $ui->maxWidth = 99999;

        $binder = new EventBinder($ui, $this);
        $binder->setLookup(function (UXNode $context, $id) {
            return $context->lookup("#$id");
        });

        $binder->load();

        $this->content = $ui->lookup('#content');
        $this->projectNameLabel = $ui->lookup('#projectNameLabel');
        $this->projectDirLabel = $ui->lookup('#projectDirLabel');

        $pane = new UXScrollPane($ui);
        $pane->padding = 0;
        $pane->fitToWidth = true;

        return $pane;
    }

    /**
     * @param UXNode $node
     * @param bool $prepend
     * @return UXVBox|UXNode
     */
    public function addSettingsPane(UXNode $node, $prepend = true)
    {
        Logger::debug("Add settings pane ...");

        if (property_exists($node, 'padding')) {
            $node->padding = 10;
            $pane = $node;
        } else {
            $pane = new UXVBox();
            $pane->add($node);
            $pane->padding = 10;
        }

        if ($prepend) {
            $this->content->children->insert(2, $pane);
            $this->content->children->insert(2, new UXSeparator());
        } else {
            $this->content->add($pane);
            $this->content->add(new UXSeparator());
        }

        return $pane;
    }

    /**
     * Refresh ui and pane.
     */
    public function refresh()
    {
        $project = Ide::project();

        if ($project) {
            if ($project && !$this->init) {
                $this->init = true;
                UXApplication::runLater(function () use ($project) {
                    $project->trigger('makeSettings', $this);
                });
            }

            $this->projectNameLabel->text = $project->getName();
            $this->projectDirLabel->text = File::of($project->getRootDir());

            UXApplication::runLater(function () use ($project) {
                $project->trigger('updateSettings', $this);
            });
        }
    }

    /**
     * @event changeNameButton.action
     */
    public function doChangeProjectName()
    {
        if (Ide::project()) {
            retry:
            $dialog = new InputMessageBoxForm('Переименование проекта', 'Введите новое название для проекта', '* Только валидное имя для файла');

            $dialog->setPattern(new Regex('[^\\?\\<\\>\\*\\:\\|\\"]{1,}', 'i'), 'Данное название некорректное');

            $dialog->showDialog();
            $name = $dialog->getResult();

            if ($name) {
                if (!FileUtils::validate($name)) {
                    return;
                }

                $success = Ide::project()->setName($name);

                if (!$success) {
                    UXDialog::showAndWait("Невозможно дать проекту введенное имя '$name', попробуйте другое.", 'ERROR');
                    goto retry;
                } else {
                    $this->projectNameLabel->text = $name;
                    Ide::get()->setOpenedProject(Ide::project());

                    FileSystem::open(Ide::project()->getMainProjectFile());
                }
            }
        }
    }

    /**
     * @event openProjectDirButton.action
     */
    public function doOpenProjectDir()
    {
        $desktop = new UXDesktop();
        $desktop->open(Ide::project()->getRootDir());
    }
}