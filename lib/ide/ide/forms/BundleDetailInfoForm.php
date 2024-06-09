<?php
namespace ide\forms;

use ide\bundle\AbstractBundle;
use ide\forms\mixins\DialogFormMixin;
use ide\forms\mixins\SavableFormMixin;
use ide\Ide;
use ide\IdeConfiguration;
use ide\library\IdeLibraryBundleResource;
use ide\project\behaviours\BundleProjectBehaviour;
use ide\project\Project;
use ide\ui\ListMenu;
use php\gui\layout\UXAnchorPane;
use php\gui\layout\UXHBox;
use php\gui\layout\UXVBox;
use php\gui\UXButton;
use php\gui\UXCheckbox;
use php\gui\UXDialog;
use php\gui\UXFileChooser;
use php\gui\UXImageView;
use php\gui\UXLabel;
use php\gui\UXListCell;
use php\gui\UXListView;
use php\gui\UXTab;
use php\gui\UXTabPane;
use php\gui\UXWebView;
use php\lang\Thread;
use php\lib\fs;
use php\lib\reflect;
use php\lib\str;
use ide\forms\malboro\Toasts;

/**
 * Class BundleCheckListForm
 * @package ide\forms
 *
 * @property UXTabPane $tabs
 * @property UXAnchorPane $content
 * @property UXImageView $iconImage
 * @property UXLabel $titleLabel
 * @property UXLabel $descriptionLabel
 * @property UXListView $list
 * @property UXImageView $icon
 * @property UXHBox $excludePane
 * @property UXButton $addButton
 * @property UXLabel $installedLabel
 * @property UXWebView $fullDescription
 */
class BundleDetailInfoForm extends AbstractIdeForm
{
    use DialogFormMixin;
    use SavableFormMixin;

    /**
     * @var IdeLibraryBundleResource
     */
    protected $displayResource;

    /**
     * @var BundleProjectBehaviour
     */
    private $behaviour;

    /**
     * @var UXCheckbox[]
     */
    protected $checkboxes = [];

    /**
     * @var ListMenu[]
     */
    protected $tabLists = [];

    /**
     * @var callable
     */
    protected $updateHandler;

    public function __construct(BundleProjectBehaviour $behaviour)
    {
        parent::__construct();
        $this->behaviour = $behaviour;
    }

    protected function init()
    {
        parent::init();
    }

    /**
     * @param callable $callback
     */
    public function onUpdate(callable $callback)
    {
        $this->updateHandler = $callback;
    }

    /**
     * @event showing
     */
    public function doShowing()
    {
        $this->checkboxes = [];

        if ($this->getResult() instanceof IdeLibraryBundleResource) {
            $this->display($this->getResult());
        } else {
            $this->display(null);
        }
    }

    /**
     * @event cancelButton.action
     */
    public function doCancel()
    {
        $this->setResult(null);
        $this->hide();
    }

    /**
     * @event addButton.action
     */
    public function doInstall()
    {
        if ($this->displayResource) {
            $this->showPreloader('Подождите, подключение пакета ...');

            Ide::async(function () {
                try {
                    $this->behaviour->addBundle(Project::ENV_ALL, $this->displayResource->getBundle());
                } finally {
                    uiLater(function () {
                        $this->hidePreloader();
                    });
                }

                uiLater(function () {
                    $this->update();
                });
            });
        }
    }

    public function update()
    {
        $displayResource = $this->displayResource;

        $this->display($displayResource);

        if ($this->updateHandler) {
            call_user_func($this->updateHandler);
        }
    }

    /**
     * @event removeButton.action
     */
    public function doUninstall()
    {
        if ($this->displayResource) {
           // if (MessageBoxForm::confirmDelete('пакет расширения ' . $this->displayResource->getName())) {
                $this->behaviour->removeBundle($this->displayResource->getBundle());
                $class = new Toasts;
                $class->showToast("Пакеты", "Пакет расширения отключен от проекта", "#FF4F44");
                $this->update();
            //}
        }
    }

    /**
     * @event deleteBundle.action
     */
    public function deleteBundle()
    {
        if (MessageBoxForm::confirmDelete('Пакет расширений ' . $this->displayResource->getName(), $this)) {
            Ide::get()->getLibrary()->delete($this->displayResource);
            $this->hide();
        }
    }

    public function display(IdeLibraryBundleResource $resource = null)
    {
        $this->displayResource = $resource;
    
        if ($resource) {
            $this->updateLabels($resource);
            $this->updateIcon($resource);
            $this->updateFullDescription($resource);
            $this->updateButtons($resource);
            $this->updateVisibility();
        } else {
            $this->content->hide();
        }
    }
    
    protected function updateLabels(IdeLibraryBundleResource $resource)
    {
        $this->titleLabel->text = $resource->getName() . ' (' . $resource->getVersion() . ')';
        $this->descriptionLabel->text = $resource->getDescription();
    }
    
    protected function updateIcon(IdeLibraryBundleResource $resource)
    {
        $icon = Ide::get()->getImage($resource->getIcon());
        $this->iconImage->image = $icon ? $icon->image : null;
    }
    
    protected function updateFullDescription(IdeLibraryBundleResource $resource)
    {
        $description = $resource->getFullDescription() ?: '<span style="color:gray">Информации о содержимом нет.</span>';
        $description = "<style>* {line-height: 19px;} h3 { margin: 0; padding: 0; padding-bottom: 5px; } i { font-style: italic !important; } ul { margin: 0; padding: 0; padding-left: 0; margin-left: 10px; } li {  color: gray; }</style><h3>Пакет содержит</h3> $description";
        $description .= "<br><h3>Свойства</h3>
                        <div>Автор пакета: {$resource->getAuthor()}<br>
                        Версия: {$resource->getVersion()}<br>
                        Класс: <code>" . reflect::typeOf($resource->getBundle()) . "</code></div>";
        $description = "<div style='font: 12px Tahoma;'>$description</div>";
        $this->fullDescription->engine->loadContent($description, 'text/html');
    }
    
    protected function updateButtons(IdeLibraryBundleResource $resource)
    {
        $installed = $this->behaviour->hasBundleInAnyEnvironment($resource->getBundle());
    
        if ($installed) {
            $this->hideAddButton();
            $this->showInstalledLabelAndExcludePane();
        } else {
            $this->showAddButton();
            $this->hideInstalledLabelAndExcludePane();
        }
    
        $this->deleteBundle->managed = ($this->deleteBundle->visible = !$resource->isEmbedded() && !$installed);
    }
    
        protected function updateVisibility()
        {
            $this->content->show();
        }
        
        protected function hideAddButton()
        {
            $this->addButton->hide();
            $this->addButton->managed = false;
        }
        
        protected function showAddButton()
        {
            $this->addButton->show();
            $this->addButton->managed = true;
        }
        
        protected function showInstalledLabelAndExcludePane()
        {
            $this->installedLabel->show();
            $this->installedLabel->managed = true;
        
            $this->excludePane->show();
            $this->excludePane->managed = true;
        }
        
        protected function hideInstalledLabelAndExcludePane()
        {
            $this->installedLabel->hide();
            $this->installedLabel->managed = false;
        
            $this->excludePane->hide();
            $this->excludePane->managed = false;
        }
}