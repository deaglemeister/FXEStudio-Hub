<?php
namespace ide\editors\form;
use ide\Logger;
use ide\misc\EventHandlerBehaviour;
use ide\utils\UiUtils;
use php\gui\layout\UXAnchorPane;
use php\gui\layout\UXHBox;
use php\gui\layout\UXScrollPane;
use php\gui\layout\UXVBox;
use php\gui\UXApplication;
use php\gui\UXNode;
use php\gui\UXScreen;
use php\gui\UXTab;
use php\gui\UXTabPane;
use php\lib\Items;

/**
 * Class IdeTabPane
 * @package ide\editors\form
 */
class IdeTabPane
{
    use EventHandlerBehaviour;

    /**
     * @var UXVBox
     */
    protected $ui;

    /**
     * @var UXTabPane
     */
    protected $tabPane;

    /**
     * @var
     */
    protected $tabs = [];

    /**
     * Контент табов: code => UXNode (контент вынесен из TabPane, чтобы полоса табов
     * была сверху панели, а комбобокс — между табами и контентом).
     * @var UXNode[]
     */
    protected $contents = [];

    /**
     * @var UXVBox
     */
    protected $contentBox;

    /**
     * @var array
     */
    protected $hiddenTabs = [];

    /**
     * @var IdeBehaviourPane
     */
    protected $behaviourPane;

    /**
     * @var IdeEventListPane
     */
    protected $eventListPane;

    /**
     * @var IdePropertiesPane
     */
    protected $propertiesPane;

    /**
     * @var IdeObjectTreeList
     */
    protected $objectTreeList;

    /**
     * @var int
     */
    protected $selectedIndex = 0;

    public function __construct()
    {
    }

    public function clear()
    {
        $this->tabs = [];

        $this->tabPane->clear();
    }

    /**
     * @param UXNode $node
     * @param bool $prepend
     */
    public function addCustomNode(UXNode $node, $prepend = true)
    {
        $node->maxWidth = 9999;

        if ($prepend) {
            $this->ui->children->insert(0, $node);
        } else {
            $this->ui->children->insert($this->ui->children->count() - 1, $node);
        }
    }

    /**
     * @param $code
     */
    public function remove($code)
    {
        $tab = $this->tabs[$code];

        unset($this->tabs[$code]);

        $this->tabPane->tabs->remove($tab);
    }

    public function refresh()
    {
        $tabs = Items::toArray($this->tabPane->tabs);

        $this->tabPane->tabs->clear();
        $this->tabPane->tabs->addAll($tabs);

        $this->tabPane->selectedIndex = $this->selectedIndex;
    }

    /**
     * @param $code
     * @param string $name
     * @param UXNode $content
     * @param bool $withScroll
     * @return UXTab
     */
    public function tab($code, $name = 'Unknown', $content = null, $withScroll = true)
    {
        $tab = $this->tabs[$code];

        if (!$tab) {
            $tab = new UXTab();
            $tab->closable = false;
            $tab->text = $name;

            if ($withScroll && $content) {
                $scroll = new UXScrollPane($content);
                $scroll->fitToWidth = true;
                $scroll->classes->add('fxe-form-scroll');
                $this->contents[$code] = $scroll;
            } else if ($content) {
                $this->contents[$code] = $content;
            }

            $tab->on('change', function () {
                UXApplication::runLater(function () {
                    $this->selectedIndex = $this->tabPane->selectedIndex;
                    $this->syncContent();
                });
            });

            $tab->style = UiUtils::fontSizeStyle();

            $this->tabs[$code] = $tab;

            if ($this->tabPane) {
                $this->tabPane->tabs->add($tab);
                $this->syncContent();
            }
        }

        return $tab;
    }

    protected function syncContent()
    {
        if (!$this->contentBox || !$this->tabPane) {
            return;
        }

        $selected = $this->tabPane->selectedTab;
        $node = null;

        foreach ($this->tabs as $code => $tab) {
            if ($tab === $selected) {
                $node = $this->contents[$code];
                break;
            }
        }

        if ($node) {
            $node->maxHeight = 99999;
            UXVBox::setVgrow($node, 'ALWAYS');
            $this->contentBox->children->setAll([$node]);
        } else {
            $this->contentBox->children->clear();
        }
    }

    /**
     * @param string $tabCode
     */
    private function select($tabCode)
    {
        if ($tab = $this->tabs[$tabCode]) {
            $this->tabPane->selectedTab = $tab;
        }
    }

    public function selectEventList()
    {
        $this->select('eventList');
    }

    public function selectBehaviours()
    {
        $this->select('behaviours');
    }

    public function selectProperties()
    {
        $this->select('properties');
    }

    public function addObjectTreeList(IdeObjectTreeList $list)
    {
        $this->objectTreeList = $list;

        //$this->addCustomNode($list->makeUi());
    }

    public function hideEventListPane()
    {
        if ($tab = $this->tabs['eventList']) {
            $this->hiddenTabs['eventList'] = $tab;
        }

        $this->remove('eventList');
    }

    public function showEventListPane()
    {
        if ($this->tabs['eventList']) {
            return;
        }

        unset($this->hiddenTabs['eventList']);

        $content = $this->contents['eventList'];

        if ($this->eventListPane) {
            $this->tab('eventList', 'События', $content ?: $this->eventListPane->makeUi(), false);
        }
    }

    public function addEventListPane(IdeEventListPane $pane)
    {
        $tab = $this->tab('eventList', 'События', $pane->makeUi(), false);

        $pane->setHintNode($tab);
        $this->eventListPane = $pane;

        $handler = function () {
            $this->trigger('change', [$this->eventListPane->getTargetId()]);
        };

        $pane->on('add', $handler, __CLASS__);
        $pane->on('remove', $handler, __CLASS__);
    }

    public function hideBehaviourPane()
    {
        if ($tab = $this->tabs['behaviours']) {
            $this->hiddenTabs['behaviours'] = $tab;
        }

        $this->remove('behaviours');
    }

    public function showBehaviourPane()
    {
        if ($this->tabs['behaviours']) {
            return;
        }

        unset($this->hiddenTabs['behaviours']);

        $scroll = $this->contents['behaviours'];
        $inner = $scroll instanceof UXScrollPane ? $scroll->content : null;

        if ($this->behaviourPane) {
            $this->tab('behaviours', 'Поведения', $inner ?: $this->behaviourPane->makeUi(''));
        }
    }

    public function addBehaviourPane(IdeBehaviourPane $pane)
    {
        $tab = $this->tab('behaviours', 'Поведения', $pane->makeUi(''));

        $pane->setHintNode($tab);
        $this->behaviourPane = $pane;

        $handler = function () {
            $this->trigger('change', [$this->behaviourPane->getTargetId()]);
        };

        $pane->on('add', $handler, __CLASS__);
        $pane->on('edit', $handler, __CLASS__);
        $pane->on('remove', $handler, __CLASS__);
    }

    public function removePropertiesPane()
    {
        $this->remove('properties');
    }

    public function showPropertiesPane()
    {
        if ($this->propertiesPane) {
            $this->tab('properties', 'Свойства', $this->propertiesPane->makeUi());
        }
    }

    public function addPropertiesPane(IdePropertiesPane $pane)
    {
        $this->tab('properties', 'Свойства', $pane->makeUi());

        $this->propertiesPane = $pane;

        $handler = function () {
            if ($this->eventListPane) {
                $this->trigger('change', [$this->eventListPane->getTargetId()]);
            }
        };

        $pane->on('change', $handler, __CLASS__);
    }

    public function update($targetId, $target = null)
    {
        $this->updateBehaviours($targetId);
        $this->updateEventList($targetId);

        $this->updateProperties($target);

        $this->updateObjectTreeList($targetId);
    }

    public function updateObjectTreeList($targetId)
    {
        if ($this->objectTreeList) {
            $this->objectTreeList->setSelected($targetId);
        }
    }

    public function refreshObjectTreeList($targetId = null)
    {
        if ($this->objectTreeList) {
            $this->objectTreeList->update($targetId);
        }
    }

    public function updateBehaviours($targetId)
    {
        if ($this->behaviourPane) {
            if ($this->tabs['behaviours']) {
                $scroll = $this->contents['behaviours'];

                if ($scroll instanceof UXScrollPane && $scroll->content) {
                    $this->behaviourPane->makeUi($targetId, $scroll->content);
                }
            }
        }
    }

    public function updateEventList($targetId)
    {
        if ($this->eventListPane) {
            $this->eventListPane->update($targetId);
        }
    }

    public function updateProperties($target, array $properties = null)
    {
        if ($this->propertiesPane) {
            $this->propertiesPane->update($target, $properties);
        }
    }

    public function setPropertiesNode(UXNode $node)
    {
        if ($this->propertiesPane) {
            $this->propertiesPane->setOnlyNode($node);
        }
    }

    public function makeUi()
    {
        $shell = new UXVBox();
        $shell->classes->addAll(['fxe-form-sidebar-shell', 'fxe-panel-shell']);
        $shell->maxHeight = 99999;

        $box = new UXVBox();
        $box->spacing = 0;
        $box->classes->addAll(['fxe-form-sidebar', 'fxe-panel-card']);

        // Полоса табов — сверху панели, без собственного контента.
        $ui = new UXTabPane();
        $ui->classes->add('fxe-form-sidebar-tabs');
        $ui->tabClosingPolicy = 'UNAVAILABLE';
        $ui->tabs->setAll($this->tabs);
        $ui->minHeight = 34;
        $ui->prefHeight = 34;
        $ui->maxHeight = 34;

        $this->tabPane = $ui;
        $ui->selectedIndex = 0;
        $box->children->add($ui);

        if ($this->objectTreeList) {
            $box->children->add($this->objectTreeList->makeHeaderUi());
        }

        $this->contentBox = new UXVBox();
        $this->contentBox->spacing = 0;
        $this->contentBox->classes->add('fxe-form-sidebar-content');
        UXVBox::setVgrow($this->contentBox, 'ALWAYS');
        $box->children->add($this->contentBox);

        UXVBox::setVgrow($box, 'ALWAYS');
        $shell->children->add($box);

        $this->ui = $shell;

        $this->syncContent();

        return $shell;
    }
}