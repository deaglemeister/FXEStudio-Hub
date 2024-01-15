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
                $tab->content = new UXScrollPane($content);
                $tab->content->fitToWidth = true;
            } else {
                $tab->content = $content;
            }

            $tab->on('change', function () {
                UXApplication::runLater(function () {
                    $this->selectedIndex = $this->tabPane->selectedIndex;
                });
            });

            $tab->style = UiUtils::fontSizeStyle();

            $this->tabs[$code] = $tab;

            if ($this->tabPane) {
                $this->tabPane->tabs->add($tab);
            }
        }

        return $tab;
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
        $content = null;

        if ($this->tabs['eventList']) {
            return;
        }

        if ($tab = $this->hiddenTabs['eventList']) {
            $content = $tab->content;
            unset($this->hiddenTabs['eventList']);
        }

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
        $content = null;

        if ($this->tabs['behaviours']) {
            return;
        }

        if ($tab = $this->hiddenTabs['behaviours']) {
            $content = $tab->content;
            unset($this->hiddenTabs['behaviours']);
        }

        if ($this->behaviourPane) {
            $this->tab('behaviours', 'Поведения', $content->content ?: $this->behaviourPane->makeUi(''));
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
                $tab = $this->tab('behaviours');

                if ($tab && $tab->content) {
                    $this->behaviourPane->makeUi($targetId, $tab->content->content);
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
        $ui = new UXTabPane();
        UXAnchorPane::setAnchor($ui, 0);

        $ui->tabs->setAll($this->tabs);

        $box = new UXVBox();
        $box->spacing = 2;
        $box->add($ui);

        $box->maxHeight = 99999;

        $this->tabPane = $ui;
        UXVBox::setVgrow($ui, 'ALWAYS');
        UXAnchorPane::setAnchor($box, 0);

        $this->ui = $box;

        if ($this->objectTreeList) {
            $node = $this->objectTreeList->makeUi();
            UXVBox::setMargin($node, [2, 3, 0, 3]);

            $this->addCustomNode($node);
        }

        return $box;
    }
}