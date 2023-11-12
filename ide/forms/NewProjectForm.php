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

        $this->contextMenu = new ContextMenu();

        $this->contextMenu->addCommand(AbstractCommand::make('Удалить', 'icons/delete16.png', function () {
            $resource = Items::first($this->templateList->selectedItems);

            if ($resource instanceof IdeLibraryResource) {
                $msg = new MessageBoxForm("Вы уверены, что хотите удалить проект {$resource->getName()} из библиотеки?", ['Да, удалить', 'Нет'], $this);

                if ($msg->showDialog() && $msg->getResultIndex() == 0) {
                    Ide::get()->getLibrary()->delete($resource);
                    $this->doShow();
                }
            }
        }));

        $this->directoryChooser = new UXDirectoryChooser();

        $this->icon->image = Ide::get()->getImage('icons/newproect16.png')->image;
        $width = 16; // Новая ширина
        $height = 16; // Новая высота
        $this->modality = 'APPLICATION_MODAL';
        $this->title = 'Cоздание нового проекта';

        $this->pathField->text = $projectDir = Ide::get()->getUserConfigValue('projectDirectory');

        $this->templateList->setCellFactory(function (UXListCell $cell, $template = null) {
            if ($template) {
                if (is_string($template)) {
                    $cell->text = $template . ":";
                    $cell->textColor = UXColor::of('gray');
                    $cell->padding = [5, 10];
                    $cell->paddingTop = 10;
                    $cell->style = '-fx-font-style: italic;';
                    $cell->graphic = null;
                } else {
                    $titleName = new UXLabel($template->getName());
                    $titleName->style = '-fx-font-weight: bold;'.UiUtils::fontSizeStyle();

                    $titleDescription = new UXLabel($template->getDescription());
                    $titleDescription->style = '-fx-text-fill: gray;'.UiUtils::fontSizeStyle();

                    if (!$titleDescription->text && $template instanceof IdeLibraryResource) {
                        $titleDescription->text = 'Шаблонный проект без описания';
                    }

                    $title = new UXVBox([$titleName, $titleDescription]);
                    $title->spacing = 0;

                    $line = new UXHBox([$template instanceof AbstractProjectTemplate ? Ide::get()->getImage($template->getIcon32()) : ico('programEx32'), $title]);
                    $line->spacing = 7;
                    $line->padding = 5;

                    $cell->text = null;
                    $cell->graphic = $line;
                    $cell->style = '';
                }
            }
        });
    }

    /**
     * @event show
     */
    public function doShow()
    {
        $templates = Ide::get()->getProjectTemplates();
        $this->templates = Items::toArray($templates);

        $this->templateList->items->clear();

        foreach ($templates as $template) {
            $this->templateList->items->add($template);
        }

        /*$libraryResources = Ide::get()->getLibrary()->getResources('projects');

        if ($libraryResources) {
            $this->templateList->items->add('Библиотека проектов');
        }

        $this->templateList->items->addAll($libraryResources);  */

        if ($templates) {
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