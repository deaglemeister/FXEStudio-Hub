<?php
namespace ide\project\control;
use ide\forms\InputMessageBoxForm;
use ide\systems\FileSystem;
use php\gui\layout\UXHBox;
use php\gui\layout\UXScrollPane;
use php\gui\layout\UXVBox;
use php\gui\UXButton;
use php\gui\UXLabel;
use php\gui\UXNode;
use php\gui\UXTextField;
use ide\Ide;
use php\gui\UXApplication;
use php\io\File;
use ide\Logger;
use php\gui\UXDialog;
use ide\utils\FileUtils;
use php\gui\UXDesktop;
use php\util\Regex;
use php\lib\str;

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
     * @var UXVBox
     */
    protected $settingsHost;

    /**
     * @var UXVBox
     */
    protected $syncHost;

    /**
     * @var UXTextField
     */
    protected $projectNameField;

    /**
     * @var UXTextField
     */
    protected $projectPathField;

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
     * @return UXVBox|null
     */
    public function getSyncHost()
    {
        return $this->syncHost;
    }

    /**
     * @return UXNode
     */
    public function makeUi()
    {
        $this->content = new UXVBox();
        $this->content->classes->add('fxe-project-settings');
        $this->content->spacing = 16;
        $this->content->fillWidth = true;

        $this->content->children->add($this->buildMainInfoSection());

        $this->settingsHost = new UXVBox();
        $this->settingsHost->spacing = 16;
        $this->settingsHost->fillWidth = true;
        $this->content->children->add($this->settingsHost);

        $scroll = new UXScrollPane($this->content);
        $scroll->padding = 0;
        $scroll->fitToWidth = true;
        $scroll->classes->add('fxe-project-settings-scroll');

        return $scroll;
    }

    /**
     * @return UXVBox
     */
    protected function buildMainInfoSection()
    {
        $body = new UXHBox();
        $body->classes->add('fxe-project-main-info-body');
        $body->spacing = 24;
        $body->alignment = 'TOP_LEFT';
        $body->fillHeight = false;

        $left = new UXVBox();
        $left->spacing = 14;
        $left->fillWidth = true;
        UXHBox::setHgrow($left, 'ALWAYS');

        $nameLabel = new UXLabel('Имя проекта');
        $nameLabel->classes->add('fxe-project-field-label');

        $this->projectNameField = new UXTextField();
        $this->projectNameField->editable = false;
        $this->projectNameField->classes->addAll(['fxe-project-field-input', 'fxe-project-name-input']);

        $nameEdit = new UXButton();
        $nameEdit->classes->addAll(['fxe-project-field-icon-btn', 'icon-edit']);
        $nameEdit->on('action', [$this, 'doChangeProjectName']);

        $nameRow = new UXHBox([$nameEdit, $this->projectNameField]);
        $nameRow->classes->add('fxe-project-input-row');
        $nameRow->spacing = 0;
        $nameRow->alignment = 'CENTER_LEFT';
        UXHBox::setHgrow($this->projectNameField, 'ALWAYS');

        $pathLabel = new UXLabel('Путь к проекту');
        $pathLabel->classes->add('fxe-project-field-label');

        $this->projectPathField = new UXTextField();
        $this->projectPathField->editable = false;
        $this->projectPathField->classes->addAll(['fxe-project-field-input', 'fxe-project-path-input']);

        $openProjectDirButton = new UXButton('...');
        $openProjectDirButton->classes->add('fxe-project-path-btn');
        $openProjectDirButton->on('action', [$this, 'doOpenProjectDir']);

        $pathRow = new UXHBox([$this->projectPathField, $openProjectDirButton]);
        $pathRow->classes->add('fxe-project-input-row');
        $pathRow->spacing = 8;
        $pathRow->alignment = 'CENTER_LEFT';
        UXHBox::setHgrow($this->projectPathField, 'ALWAYS');

        $left->children->addAll([
            new UXVBox([$nameLabel, $nameRow], 6),
            new UXVBox([$pathLabel, $pathRow], 6),
        ]);

        $this->syncHost = new UXVBox();
        $this->syncHost->classes->add('fxe-project-sync-box');
        $this->syncHost->spacing = 8;
        $this->syncHost->fillWidth = false;

        $body->children->addAll([$left, $this->syncHost]);

        return $this->buildSection('Основные сведения о проекте', $body);
    }

    /**
     * @param string $titleText
     * @param UXNode $body
     * @return UXVBox
     */
    protected function buildSection($titleText, UXNode $body)
    {
        $section = new UXVBox();
        $section->spacing = 8;
        $section->fillWidth = true;

        $title = new UXLabel($titleText);
        $title->classes->add('fxe-project-section-title');
        $section->children->add($title);

        $card = new UXVBox();
        $card->classes->add('fxe-project-settings-card');
        $card->spacing = 0;
        $card->fillWidth = true;

        $cardBody = new UXVBox([$body]);
        $cardBody->classes->add('fxe-project-settings-card-body');
        $cardBody->fillWidth = true;
        $card->children->add($cardBody);

        $section->children->add($card);

        return $section;
    }

    /**
     * @param UXNode $node
     * @param bool $prepend
     * @return UXVBox
     */
    public function addSettingsPane(UXNode $node, $prepend = true)
    {
        Logger::debug("Add settings pane ...");

        $section = $this->wrapInSettingsSection($node);

        if ($prepend) {
            $this->settingsHost->children->insert(0, $section);
        } else {
            $this->settingsHost->children->add($section);
        }

        return $section;
    }

    /**
     * @param UXNode $node
     * @return UXVBox
     */
    protected function wrapInSettingsSection(UXNode $node)
    {
        if (property_exists($node, 'padding')) {
            $node->padding = 0;
        }

        $titleText = null;

        if ($node instanceof UXVBox && $node->children->count > 0) {
            $first = $node->children[0];

            if ($first instanceof UXLabel) {
                $titleText = str::trim(str::replace($first->text, ':', ''));
                $node->children->removeByIndex(0);
            }
        }

        return $this->buildSection($titleText ?: 'Настройки', $node);
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

            $this->projectNameField->text = $project->getName();
            $this->projectPathField->text = File::of($project->getRootDir());

            UXApplication::runLater(function () use ($project) {
                $project->trigger('updateSettings', $this);
            });
        }
    }

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
                    $this->projectNameField->text = $name;
                    Ide::get()->setOpenedProject(Ide::project());

                    FileSystem::open(Ide::project()->getMainProjectFile());
                }
            }
        }
    }

    public function doOpenProjectDir()
    {
        $desktop = new UXDesktop();
        $desktop->open(Ide::project()->getRootDir());
    }
}
