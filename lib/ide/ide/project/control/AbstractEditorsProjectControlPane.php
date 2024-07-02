<?php
namespace ide\project\control;

use ide\editors\AbstractEditor;
use ide\editors\menu\AbstractMenuCommand;
use ide\forms\MessageBoxForm;
use ide\Ide;
use ide\systems\FileSystem;
use ide\ui\FlowListViewDecorator;
use ide\ui\ImageBox;
use php\gui\event\UXMouseEvent;
use php\gui\layout\UXHBox;
use php\gui\layout\UXVBox;
use php\gui\UXButton;
use php\gui\UXDialog;
use php\gui\UXNode;
use php\gui\UXSeparator;
use php\lib\fs;

class AbstractEditorsProjectControlPaneEditCommand extends AbstractMenuCommand
{
    /**
     * @var AbstractEditorsProjectControlPane
     */
    protected $pane;

    /**
     * AbstractEditorsProjectControlPaneEditCommand constructor.
     * @param AbstractEditorsProjectControlPane $pane
     */
    public function __construct(AbstractEditorsProjectControlPane $pane)
    {
        $this->pane = $pane;
    }

    public function getName()
    {
        return _('edit.btns.tree');
    }

    public function getIcon()
    {
        return 'icons/edit16.png';
    }

    public function onExecute($e = null, AbstractEditor $editor = null)
    {
        $this->pane->doEdit();
    }
}

class AbstractEditorsProjectControlPaneCloneCommand extends AbstractMenuCommand
{
    /**
     * @var AbstractEditorsProjectControlPane
     */
    protected $pane;

    /**
     * AbstractEditorsProjectControlPaneEditCommand constructor.
     * @param AbstractEditorsProjectControlPane $pane
     */
    public function __construct(AbstractEditorsProjectControlPane $pane)
    {
        $this->pane = $pane;
    }

    public function getName()
    {
        return _('edit.btns.tree2');
    }

    public function getIcon()
    {
        return 'icons/copy16.png';
    }

    public function onExecute($e = null, AbstractEditor $editor = null)
    {
        $this->pane->doClone();
    }
}

/**
 * Class AbstractEditorsProjectControlPane
 * @package ide\project\control
 */
abstract class AbstractEditorsProjectControlPane extends AbstractProjectControlPane
{
    /**
     * @var FlowListViewDecorator
     */
    protected $list;

    /**
     * @return mixed
     */
    abstract protected function doAdd();

    /**
     * @return mixed[]
     */
    abstract protected function getItems();

    /**
     * @return mixed
     */
    abstract protected function getBigIcon($item);


    public function doEdit()
    {
        if ($this->getSelectedItem() instanceof AbstractEditor) {
            FileSystem::open($this->getSelectedItem());
        }
    }

    public function doClone()
    {
        if ($this->getSelectedItem() instanceof AbstractEditor) {

            /** @var AbstractEditor $editor */
            $editor = $this->getSelectedItem();

            if ($editor->getFormat()->availableCreateDialog()) {
                $name = $editor->getFormat()->showCreateDialog();

                if ($name !== null) {
                    $ext = fs::ext($editor->getFile());
                    $newFile = fs::parent($editor->getFile()) . "/$name" . ($ext ? ".$ext" : "");

                    if (fs::exists($newFile))
                    {
                        UXDialog::showAndWait(_('edit.btns.tree3'), 'WARNING');
                        $this->doClone();
                        return;
                    }

                    $editor->getFormat()->duplicate($editor->getFile(), $newFile);
                    $this->refresh();
                }
            }
        }
    }

    /**
     * @return int
     */
    function getMenuCount()
    {
        return sizeof($this->getItems());
    }

    /**
     * @return mixed|null
     */
    public function getSelectedItem()
    {
        return $this->list->getSelectionNode() ? $this->list->getSelectionNode()->userData : null;
    }

    /**
     * @param AbstractEditor $item
     * @return UXNode
     */
    protected function makeItemUi($item)
    {
        $imageBox = new ImageBox(80, 48);
        $imageBox->userData = $item;
        $image = Ide::get()->getImage($this->getBigIcon($item));

        $imageBox->setImage($image ? $image->image : null);

        if (method_exists($item, 'getTitle')) {
            $imageBox->setTitle($item->getTitle());
        } else {
            $imageBox->setTitle($item->name);
        }

        return $imageBox;
    }

    /**
     * @return UXNode
     */
    protected function makeUi()
    {
        $pane = new FlowListViewDecorator();
        $pane->setEmptyListText($this->getName() . _(' список пуст'));
        $pane->setMultipleSelection(true);

        $pane->addMenuCommand(new AbstractEditorsProjectControlPaneEditCommand($this));
        $pane->addMenuCommand(new AbstractEditorsProjectControlPaneCloneCommand($this));
      

        $pane->on('beforeRemove', function (array $nodes) {
            foreach ($nodes as $node) {
                if ($node->userData instanceof AbstractEditor) {
                    $editor = $node->userData;
                    $format = $editor->getFormat();

                    $file = $editor->getFile();

                    if (!MessageBoxForm::confirmDelete($editor->getTitle(), $this->ui)) {
                        return true;
                    }

                    FileSystem::close($file);

                    if ($editor->delete($file) === false) {
                        return true;
                    }

                    waitAsync(3000, function () use ($editor, $file) {
                        $editor->delete($file);

                        $this->trigger('updateCount');

                        if (Ide::project()) {
                            Ide::project()->trigger('updateSettings');
                        }
                    });

                    if (fs::exists($file)) {
                        UXDialog::show(_('edit.btns.tree5'), 'ERROR');
                        return true;
                    } else {
                        if ($project = Ide::project()) {
                            $project->update();
                        }
                    }
                }
            }

            return false;
        });

        $this->list = $pane;


        $pane = $pane->getPane();
      #  $pane->classes->add('ui-topBar');
        UXVBox::setVgrow($pane, 'ALWAYS');


        $ui = new UXVBox([$this->makeActionsUi()]);

        foreach ($this->makeAdditionalUi() as $one) {
            $ui->add($one);
        }

        $ui->add($pane);
        $ui->spacing = 10;
       # $ui->classes->add('ui-topBar');
        return $ui;
    }

    /**
     * @return array
     */
    protected function makeAdditionalUi()
    {
        return [];
    }

    protected function makeActionsUi()
    {
        $addButton = new UXButton(_('edit.btns.tree6'));
        $addButton->classes->add('icon-plus');
        $addButton->font = $addButton->font->withBold();
        $addButton->maxHeight = 999;
        $addButton->classes->add('fxe-button');
        $addButton->on('action', function () {
            $this->doAdd();
            $this->trigger('updateCount');
        });

        $editButton = new UXButton(_('edit.btns.tree7'));
        $editButton->classes->add('icon-edit');
        $editButton->maxHeight = 999;
        $editButton->enabled = false;
        $editButton->classes->add('fxe-button');
        $editButton->on('action', function () {
            $this->doEdit();
        });

        $cloneButton = new UXButton(_('edit.btns.tree8'));
        $cloneButton->classes->add('icon-copy');
        $cloneButton->maxHeight = 999;
        $cloneButton->enabled = false;
        $cloneButton->classes->add('fxe-button');
        $cloneButton->on('action', function () {
            $this->doClone();
        });

        $delButton = new UXButton();
        $delButton->classes->add('icon-trash2');
        $delButton->maxHeight = 999;
        $delButton->enabled = false;
        $delButton->classes->add('fxe-button');
        $delButton->text = _('edit.btns.tree9');

        $delButton->on('action', function () {
            $this->list->removeBySelections();
        });

        $this->list->on('select', function ($nodes) use ($delButton, $editButton, $cloneButton) {
            $delButton->enabled = !!$nodes;
            $editButton->enabled = sizeof($nodes) == 1;
            $cloneButton->enabled = sizeof($nodes) == 1;
        });

        $ui = new UXHBox([$addButton, new UXSeparator('VERTICAL'), $editButton, $cloneButton, $delButton]);
        $ui->spacing = 5;
        $ui->minHeight = 30;
        #$ui->classes->add('ui-topBar');

        return $ui;
    }

    /**
     * Refresh ui and pane.
     */
    public function refresh()
    {
        $node = $this->list->getSelectionNode();

        $selectedEditor = $node ? $node->userData : null;

        $this->list->clear();

        foreach ($this->getItems() as $editor) {
            $imageBox = $this->makeItemUi($editor);

            $imageBox->on('click', function (UXMouseEvent $e) use ($editor) {
                if ($e->clickCount > 1) {
                    FileSystem::open($editor->getFile());
                }
            });

            $this->list->add($imageBox);

            if ($editor->equals($selectedEditor)) {
                $this->list->setSelectionNodes([$imageBox]);
            }
        }
    }
}