<?php
namespace ide\forms;

use ide\editors\menu\ContextMenu;
use ide\forms\mixins\DialogFormMixin;
use ide\forms\mixins\SavableFormMixin;
use ide\Ide;
use ide\library\IdeLibraryResource;
use ide\misc\AbstractCommand;
use ide\project\AbstractProjectTemplate;
use ide\systems\ProjectSystem;
use ide\utils\UiUtils;
use php\gui\event\UXMouseEvent;
use php\gui\framework\AbstractForm;
use php\gui\layout\UXHBox;
use php\gui\layout\UXVBox;
use php\gui\paint\UXColor;
use php\gui\UXApplication;
use php\gui\UXDialog;
use php\gui\UXDirectoryChooser;
use php\gui\UXFileChooser;
use php\gui\UXImageView;
use php\gui\UXLabel;
use php\gui\UXListCell;
use php\gui\UXListView;
use php\gui\UXTextField;
use php\io\File;
use php\lib\fs;
use php\lib\Items;
use php\lib\Str;
use php\util\Regex;

/**
 *
 * @property UXImageView $icon
 * @property UXListView $templateList
 * @property UXTextField $pathField
 * @property UXTextField $nameField
 * @property UXTextField $packageField
 *
 * Class NewProjectForm
 * @package ide\forms
 */
class NewProjectForm extends AbstractIdeForm
{
    
    use DialogFormMixin;
    use SavableFormMixin;

    /** @var AbstractProjectTemplate[] */
    protected $templates;

    /** @var UXFileChooser */
    protected $directoryChooser;

    /**
     * @var ContextMenu
     */
    protected $contextMenu;

    public function init()
    {
        parent::init();

        $this->initializeContextMenu();
        
        $this->directoryChooser = new UXDirectoryChooser();
        
        $this->configureIcon();
        $this->configureModalityAndTitle();
        $this->configurePathField();
        
        $this->configureTemplateList();
    }
    private function initializeContextMenu()
{
    $this->contextMenu = new ContextMenu();

    $this->contextMenu->addCommand(
        AbstractCommand::make('Удалить', 'icons/delete16.png', function () {
            $this->handleDeleteCommand();
        })
    );
}

    private function handleDeleteCommand()
    {
        $resource = Items::first($this->templateList->selectedItems);

        if ($resource instanceof IdeLibraryResource) {
            $this->showDeleteConfirmation($resource);
        }
    }

    private function showDeleteConfirmation(IdeLibraryResource $resource)
    {
        $msg = new MessageBoxForm("Вы уверены, что хотите удалить проект {$resource->getName()} из библиотеки?", ['Да, удалить', 'Нет'], $this);

        if ($msg->showDialog() && $msg->getResultIndex() == 0) {
            Ide::get()->getLibrary()->delete($resource);
            $this->doShow();
        }
    }

    private function configureIcon()
    {
        $this->icon->image = Ide::get()->getImage('icons/newproect16.png')->image;
    }

    private function configureModalityAndTitle()
    {
        $this->modality = 'APPLICATION_MODAL';
        $this->title = 'Создание нового проекта';
    }

    private function configurePathField()
    {
        $projectDir = Ide::get()->getUserConfigValue('projectDirectory');
        $this->pathField->text = $projectDir;
    }

    private function configureTemplateList()
    {
        $this->templateList->setCellFactory(function (UXListCell $cell, $template = null) {
            $this->configureTemplateListCell($cell, $template);
        });
    }

    private function configureTemplateListCell(UXListCell $cell, $template)
    {
        if ($template) {
            if (is_string($template)) {
                $this->configureStringTemplateListCell($cell, $template);
            } else {
                $this->configureObjectTemplateListCell($cell, $template);
            }
        }
    }

    private function configureStringTemplateListCell(UXListCell $cell, $template)
    {
        $cell->text = $template . ":";
        $cell->textColor = UXColor::of('gray');
        $cell->padding = [5, 10];
        $cell->paddingTop = 10;
        $cell->style = '-fx-font-style: italic;';
        $cell->graphic = null;
    }

    private function configureObjectTemplateListCell(UXListCell $cell, $template)
    {
        $titleName = new UXLabel($template->getName());
        $titleName->style = '-fx-font-weight: bold;' . UiUtils::fontSizeStyle();

        $titleDescription = new UXLabel($template->getDescription());
        $titleDescription->style = '-fx-text-fill: gray;' . UiUtils::fontSizeStyle();

        $this->configureTitleDescription($titleDescription, $template);

        $title = new UXVBox([$titleName, $titleDescription]);
        $title->spacing = 0;

        $line = new UXHBox([$this->getTemplateIcon($template), $title]);
        $line->spacing = 7;
        $line->padding = 5;

        $cell->text = null;
        $cell->graphic = $line;
        $cell->style = '';
    }

    private function configureTitleDescription(UXLabel $titleDescription, $template)
    {
        if (!$titleDescription->text && $template instanceof IdeLibraryResource) {
            $titleDescription->text = 'Шаблонный проект без описания';
        }
    }

    private function getTemplateIcon($template)
    {
        return $template instanceof AbstractProjectTemplate ? Ide::get()->getImage($template->getIcon32()) : ico('programEx32');
    }

    /**
     * @event show
     */
    public function doShow()
    {
        $this->templates = Items::toArray(Ide::get()->getProjectTemplates());
        $this->templateList->items->setAll($this->templates ?: []);
        
        if ($this->templates) {
            $this->templateList->selectedIndexes = [0];
        }
        
        $this->nameField->requestFocus();
    }

    /**
     * @event templateList.click-Right
     * @param UXMouseEvent $e
     */
    public function doContextMenu(UXMouseEvent $e)
    {
        $resource = Items::first($this->templateList->selectedItems);

        if ($resource instanceof IdeLibraryResource) {
            $this->contextMenu->getRoot()->show($this, $e->screenX, $e->screenY);
        }
    }

    /**
     * @event pathButton.action
     */
    public function doChoosePath()
    {
        $path = $this->directoryChooser->execute();

        if ($path !== null) {
            $this->pathField->text = $path;

            Ide::get()->setUserConfigValue('projectDirectory', $path);
        }
    }

    /**
     * @event nameField.keyDown-Enter
     * @event createButton.action
     */
    public function doCreate()
    {
        $template = Items::first($this->templateList->selectedItems);

        if (!$template || !is_object($template)) {
            UXDialog::show(_('project.new.alert.select.template'));
            return;
        }

        $path = File::of($this->pathField->text);

        if (!$path->isDirectory()) {
            if (!$path->mkdirs()) {
                UXDialog::show(_('project.new.error.create.project.directory'), 'ERROR');
                return;
            }
        }

        $name = str::trim($this->nameField->text);

        if (!$name) {
            UXDialog::show(_('project.new.error.name.required'), 'ERROR');
            return;
        }

        if (!fs::valid($name)) {
            UXDialog::show(_('project.new.error.name.invalid') . " \n\n$name", 'ERROR');
            return;
        }

        $package = str::trim($this->packageField->text);

        $regex = new Regex('^[a-z\\_]{2,15}$');

        if (!$regex->test($package)) {
            UXDialog::show(_('project.new.error.package.invalid') . "\n* " . _('project.new.error.package.invalid.description'), 'ERROR');
            return;
        }

        if ($template instanceof IdeLibraryResource) {
            ProjectSystem::import($template->getPath(), "$path/$name", $name);

            $this->hide();
        } else {
            $this->hide();
            $filename = File::of("$path/$name/$name.dnproject");

            /*if (!$filename->createNewFile(true)) {
                UXDialog::show("Невозможно создать файл проекта по выбранному пути\n -> $filename", 'ERROR');
                return;
            }*/

            ProjectSystem::close(false);

            uiLater(function () use ($template, $filename, $package) {
                app()->getMainForm()->showPreloader('Создание проекта ...');
                try {
                    ProjectSystem::create($template, $filename, $package);
                } finally {
                    app()->getMainForm()->hidePreloader();
                }
            });
        }
    }

    /**
     * @event cancelButton.click
     */
    public function doCancel()
    {
        $this->hide();
    }
}