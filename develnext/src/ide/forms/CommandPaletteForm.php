<?php
namespace ide\forms;

use ide\editors\CodeEditor;
use ide\Ide;
use ide\misc\AbstractCommand;
use ide\systems\FileSystem;
use php\gui\event\UXKeyEvent;
use php\gui\layout\UXVBox;
use php\gui\UXListCell;
use php\gui\UXListView;
use php\gui\UXPopupWindow;
use php\gui\UXScreen;
use php\gui\UXTextField;
use php\lib\str;

/**
 * VS Code-style командная палитра (F1).
 */
class CommandPaletteForm
{
    /** @var CommandPaletteForm|null */
    protected static $instance;

    /** @var UXPopupWindow */
    protected $popup;

    /** @var UXTextField */
    protected $field;

    /** @var UXListView */
    protected $list;

    /** @var AbstractCommand[] */
    protected $commands = [];

    public static function show()
    {
        if (static::$instance) {
            static::$instance->hide();
        }

        static::$instance = new static();
        static::$instance->display();
    }

    public function __construct()
    {
        $this->commands = $this->collectCommands();
        $this->buildUi();
    }

    /**
     * @return AbstractCommand[]
     */
    protected function collectCommands()
    {
        $result = [];

        foreach (Ide::get()->getCommands() as $command) {
            if ($command instanceof AbstractCommand) {
                $result[] = $command;
            }
        }

        $editor = FileSystem::getSelectedEditor();

        if ($editor instanceof CodeEditor) {
            foreach ($editor->getCommands() as $command) {
                if ($command instanceof AbstractCommand) {
                    $result[] = $command;
                }
            }
        }

        usort($result, function (AbstractCommand $a, AbstractCommand $b) {
            return str::compare($a->getName(), $b->getName());
        });

        return $result;
    }

    protected function buildUi()
    {
        $popup = new UXPopupWindow();
        $popup->classes->add('fxe-command-palette');

        $box = new UXVBox();
        $box->spacing = 0;

        $field = new UXTextField();
        $field->promptText = 'Введите команду...';
        $field->classes->add('fxe-command-palette-input');
        $this->field = $field;

        $list = new UXListView();
        $list->classes->add('fxe-command-palette-list');
        $list->items->setAll($this->commands);
        $list->setCellFactory(function (UXListCell $cell, $item) {
            $cell->text = $item instanceof AbstractCommand ? $item->getName() : (string) $item;

            return $cell;
        });
        $this->list = $list;

        $box->add($field);
        $box->add($list);
        $popup->add($box);
        $this->popup = $popup;

        $field->on('keyDown', function (UXKeyEvent $e) {
            if ($e->codeName === 'Escape') {
                $this->hide();
                $e->consume();
            } elseif ($e->codeName === 'Down') {
                $this->list->requestFocus();

                if ($this->list->items->size > 0) {
                    $this->list->selectedIndex = 0;
                }

                $e->consume();
            } elseif ($e->codeName === 'Enter') {
                $this->executeSelected();
                $e->consume();
            }
        });

        $field->observer('text')->addListener(function () {
            $this->applyFilter(str::lower((string) $this->field->text));
        });

        $list->on('keyDown', function (UXKeyEvent $e) {
            if ($e->codeName === 'Escape') {
                $this->hide();
                $e->consume();
            } elseif ($e->codeName === 'Enter') {
                $this->executeSelected();
                $e->consume();
            }
        });

        $list->on('click', function () {
            $this->executeSelected();
        });
    }

    protected function applyFilter($query)
    {
        if ($query === '') {
            $this->list->items->setAll($this->commands);
            return;
        }

        $filtered = [];

        foreach ($this->commands as $command) {
            $name = str::lower($command->getName());

            if (str::contains($name, $query)) {
                $filtered[] = $command;
            }
        }

        $this->list->items->setAll($filtered);

        if ($filtered) {
            $this->list->selectedIndex = 0;
        }
    }

    protected function executeSelected()
    {
        $item = $this->list->selectedItem;

        if (!($item instanceof AbstractCommand)) {
            return;
        }

        $editor = FileSystem::getSelectedEditor();
        $this->hide();

        uiLater(function () use ($item, $editor) {
            $item->onExecute(null, $editor);
        });
    }

    public function display()
    {
        $screen = UXScreen::getPrimary();
        $bounds = $screen->bounds;
        $w = 620;
        $h = 380;
        $x = $bounds['x'] + ($bounds['width'] - $w) / 2;
        $y = $bounds['y'] + $bounds['height'] * 0.22;

        $this->popup->width = $w;
        $this->popup->height = $h;
        $this->popup->show(Ide::get()->getMainForm(), $x, $y);

        uiLater(function () {
            $this->field->requestFocus();
            $this->field->selectAll();
        });
    }

    public function hide()
    {
        if ($this->popup) {
            $this->popup->hide();
        }

        static::$instance = null;
    }
}
