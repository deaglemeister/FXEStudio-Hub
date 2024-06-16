<?php
namespace ide\forms;

use ide\editors\menu\ContextMenu;
use ide\editors\WelcomeEditor;
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
    }
    
    private function initializeContextMenu()
    {
    
    }
    private function handleDeleteCommand()
    {
    
    }

    private function showDeleteConfirmation(IdeLibraryResource $resource)
    {
   
    }

    private function configureIcon()
    {
    
    }

    private function configureModalityAndTitle()
    {
        $this->modality = 'APPLICATION_MODAL';
        $this->title = 'Создание нового проекта';
    }

    private function configurePathField()
    {
      
    }

    private function configureTemplateList()
    {
       
    }

    private function configureTemplateListCell(UXListCell $cell, $template)
    {
       
    }

    private function configureStringTemplateListCell(UXListCell $cell, $template)
    {
    
    }

    private function configureObjectTemplateListCell(UXListCell $cell, $template)
    {
       
    }

    private function configureTitleDescription(UXLabel $titleDescription, $template)
    {
    }

    private function getTemplateIcon($template)
    {
     
    }

    /**
     * @event show
     */
    public function doShow()
    {
        Ide::setFrame($this->layout);
        $this->layout = WelcomeEditor::_makeUI(0, 0, 0, 0);
    }

    /**
     * @event templateList.click-Right
     * @param UXMouseEvent $e
     */
    public function doContextMenu(UXMouseEvent $e)
    {
   
    }

    /**
     * @event pathButton.action
     */
    public function doChoosePath()
    {
      
    }

    /**
     * @event nameField.keyDown-Enter
     * @event createButton.action
     */
    public function doCreate()
    {
   
    }

    /**
     * @event cancelButton.click
     */
    public function doCancel()
    {
       
    }
}