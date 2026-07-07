<?php
namespace ide\editors\value;

use ide\editors\AbstractEditor;
use ide\editors\FormEditor;
use ide\Logger;
use ide\systems\LivePropertySync;
use ide\editors\form\BulkDesignHelper;
use ide\misc\EventHandlerBehaviour;
use ide\systems\FileSystem;
use php\gui\designer\UXDesignPropertyEditor;
use php\gui\framework\behaviour\custom\AbstractBehaviour;
use php\gui\framework\DataUtils;
use php\gui\text\UXFont;
use php\gui\UXApplication;
use php\gui\UXNode;
use php\gui\UXTableCell;
use php\lang\IllegalArgumentException;
use php\lang\JavaException;
use php\lib\arr;
use php\lib\str;
use php\util\Regex;
use php\xml\DomElement;

/**
 * Class ElementPropertyEditor
 * @package ide\editors\value
 */
abstract class ElementPropertyEditor extends UXDesignPropertyEditor
{
    use EventHandlerBehaviour;

    /**
     * @var UXNode
     */
    protected $content;

    /**
     * @var string
     */
    protected $tooltip;

    /**
     * @var callable
     */
    protected $getter;

    /**
     * @var callable
     */
    protected $setter;

    /**
     * @var ElementPropertyEditor[]
     */
    protected static $editors = [];

    /**
     * @var bool
     */
    protected $reindexOnUpdate;

    /**
     * ElementPropertyEditor constructor.
     *
     * @param callable $getter
     * @param callable $setter
     */
    public function __construct(callable $getter = null, callable $setter = null)
    {
        $this->getter = $getter;
        $this->setter = $setter;

        $this->content = $this->makeUi();
    }

    /**
     * @return string
     */
    abstract public function getCode();

    /**
     * @return UXNode
     */
    abstract public function makeUi();

    /**
     * ...
     */
    public function refreshDesign()
    {
        waitAsync(100, function () {
            Logger::debug("Refresh Design");

            $editor = FileSystem::getSelectedEditor();

            if ($editor instanceof FormEditor) {
                if ($this->reindexOnUpdate) {
                    $editor->reindex();
                }

                if ($editor->isBulkEditMode()) {
                    uiLater(function () use ($editor) {
                        foreach ($editor->getBulkEditEntries() as $entry) {
                            if (!BulkDesignHelper::entrySupportsProperty($entry, $this->code)) {
                                continue;
                            }

                            $editor->refreshNode($entry['node']);
                        }

                        $editor->getDesigner()->update();
                    });
                    return;
                }

                //$editor->getDesigner()->update();

                $target = $this->designProperties->target;

                if ($target instanceof UXNode) {
                    uiLater(function () use ($editor) {
                        $editor->refreshNode($this->designProperties->target);

                        $editor->getDesigner()->update();
                    });
                } else {
                    uiLater(function () use ($editor) {
                        $node = arr::first($editor->getDesigner()->getSelectedNodes());

                        if ($node) {
                            $editor->refreshNode($node);
                            $editor->getDesigner()->update();
                        }
                    });
                }
            }
        });
    }

    /**
     * @param $value
     * @param bool $noRefreshDesign
     */
    public function updateUi($value, $noRefreshDesign = false)
    {
        if (!$noRefreshDesign) {
            $this->refreshDesign();
        }
    }

    /**
     * @param DomElement $element
     *
     * @return ElementPropertyEditor
     */
    abstract public function unserialize(DomElement $element);

    /**
     * @param string $tooltip
     */
    public function setTooltip($tooltip)
    {
        $this->tooltip = "$tooltip";
        $this->applyFieldPlaceholder();
    }

    /**
     * @return string
     */
    protected function getFieldPlaceholder()
    {
        return (string) $this->name;
    }

    protected function applyFieldPlaceholder()
    {
    }

    /**
     * @param $value
     *
     * @return mixed
     */
    public function getNormalizedValue($value)
    {
        return $value;
    }

    public function getCssNormalizedValue($value)
    {
        return $value;
    }

    /**
     * @param UXTableCell $cell
     * @param bool $empty
     *
     * @return mixed
     */
    public function update(UXTableCell $cell, $empty)
    {
        $cell->graphic = $this->content;
        $this->updateUi($this->getNormalizedValue($this->getValue()), true);
    }

    public function setAsFormConfigProperty($defaultValue, $realCode = null)
    {
        $this->setter = function (ElementPropertyEditor $editor, $value) use ($realCode) {
            $target = $this->designProperties->target;

            if ($target->userData instanceof FormEditor) {
                $target->userData->getConfig()->set($editor->code, $this->getNormalizedValue($value));

                if ($realCode) {
                    $target->{$realCode} = $value;
                }

                $this->trigger('change');
            }
        };

        $this->getter = function (ElementPropertyEditor $editor) use ($defaultValue) {
            $target = $this->designProperties->target;

            if ($target->userData instanceof FormEditor) {
                $value = $target->userData->getConfig()->get($editor->code, $defaultValue);

                return $value;
            }

            return '';
        };
    }

    public function setAsSyntheticProperty($realCode = null, $native = false)
    {
        $this->setter = function (ElementPropertyEditor $editor, $value) use ($realCode, $native) {
            $target = $this->designProperties->target;

            $target->data($editor->code, $value);
            $this->trigger('change');
        };

        $this->getter = function (ElementPropertyEditor $editor) use ($native) {
            $target = $this->designProperties->target;

            return $target->data($editor->code);
        };

        return $this;
    }

    /**
     * @param null $realCode
     * @param bool $native
     * @return $this
     */
    public function setAsVirtualProperty($realCode = null, $native = false)
    {
        $this->setter = function (ElementPropertyEditor $editor, $value) use ($realCode, $native) {
            $target = $this->designProperties->target;

            if ($target instanceof UXNode) {
                $data = DataUtils::get($target);

                if ($data && !$native) {
                    $data->set($editor->code, $value);
                } else {
                    $target->data($editor->code, $value);
                }

                if ($realCode) {
                    $target->{$realCode} = $value;
                }

                $this->trigger('change');
            }
        };

        $this->getter = function (ElementPropertyEditor $editor) use ($native) {
            $target = $this->designProperties->target;

            if ($target instanceof UXNode) {
                $data = DataUtils::get($target);

                if ($data && !$native) {
                    return $data->get($editor->code);
                } else {
                    return $target->data($editor->code);
                }
            } else if ($target instanceof AbstractEditor) {

            }

            return '';
        };

        return $this;
    }

    /**
     * @param callable $getter
     * @return $this
     */
    public function setGetter(callable $getter)
    {
        $this->getter = $getter;
        return $this;
    }

    /**
     * @param callable $setter
     * @return $this
     */
    public function setSetter(callable $setter)
    {
        $this->setter = $setter;
        return $this;
    }

    /**
     * @param null $realCode
     * @return $this
     */
    public function setAsCssProperty($realCode = null)
    {
        $this->setter = function (ElementPropertyEditor $editor, $value) use ($realCode) {
            $target = $this->designProperties->target;

            $target->css($editor->code, $value ? $editor->getCssNormalizedValue($value) : null);

            /** @var ElementPropertyEditor $styleEditor */
            if ($styleEditor = $this->designProperties->getEditorByCode('style')) {
                $styleEditor->updateUi($target->style);
            }

            $this->trigger('change');
        };

        $this->getter = function (ElementPropertyEditor $editor) {
            $target = $this->designProperties->target;

            return $target->css($editor->code);
        };

        return $this;
    }

    public function applyValue($value, $updateUi = true, $noRefreshDesign = false)
    {
        $value = $this->getNormalizedValue($value);

        $formEditor = FileSystem::getSelectedEditor();

        if ($formEditor instanceof FormEditor && $formEditor->isBulkEditMode()) {
            $applied = false;

            foreach ($formEditor->getBulkEditEntries() as $entry) {
                if (!BulkDesignHelper::entrySupportsProperty($entry, $this->code)) {
                    continue;
                }

                if ($this->applyValueToTarget($entry['target'], $value)) {
                    $applied = true;
                    LivePropertySync::onPropertyChangedForNode($formEditor, $entry['node'], $this, $value);
                }
            }

            if ($applied) {
                $this->designProperties->triggerChange();

                if ($updateUi) {
                    $this->updateUi($value, $noRefreshDesign);
                }
            }

            return;
        }

        try {
            if (!$this->setter) {
                $this->designProperties->target->{$this->code} = $value;
                $this->trigger('change');
            } else {
                $setter = $this->setter;
                $setter($this, $value);
            }

            $this->designProperties->triggerChange();

            if ($updateUi) {
                $this->updateUi($value, $noRefreshDesign);
            }

            LivePropertySync::onPropertyChanged($this, $value);
        } catch (IllegalArgumentException $e) {
            ;
        } catch (JavaException $e) {
            if (!$e->isIllegalArgumentException()) {
                throw $e;
            }
        }
    }

    protected function applyValueToTarget($target, $value)
    {
        try {
            if (!$this->setter) {
                $target->{$this->code} = $value;
                $this->trigger('change');
            } else {
                $oldTarget = $this->designProperties->target;
                $this->designProperties->target = $target;

                $setter = $this->setter;
                $setter($this, $value);

                $this->designProperties->target = $oldTarget;
            }

            return true;
        } catch (IllegalArgumentException $e) {
            ;
        } catch (JavaException $e) {
            if (!$e->isIllegalArgumentException()) {
                throw $e;
            }
        }

        return false;
    }

    protected function readValueFromTarget($target)
    {
        try {
            if (!$this->getter) {
                return $target->{$this->code};
            }

            $oldTarget = $this->designProperties->target;
            $this->designProperties->target = $target;

            $getter = $this->getter;
            $value = $getter($this);

            $this->designProperties->target = $oldTarget;

            return $value;
        } catch (IllegalArgumentException $e) {
            ;
        } catch (JavaException $e) {
            if (!$e->isIllegalArgumentException()) {
                throw $e;
            }
        }

        return null;
    }

    public function getValue()
    {
        $formEditor = FileSystem::getSelectedEditor();

        if ($formEditor instanceof FormEditor && $formEditor->isBulkEditMode()) {
            $values = [];

            foreach ($formEditor->getBulkEditEntries() as $entry) {
                if (!BulkDesignHelper::entrySupportsProperty($entry, $this->code)) {
                    continue;
                }

                $values[] = $this->readValueFromTarget($entry['target']);
            }

            if (!$values) {
                return null;
            }

            $first = $values[0];

            foreach ($values as $one) {
                if ($one != $first) {
                    return null;
                }
            }

            return $first;
        }

        try {
            if (!$this->getter) {
                $value = $this->designProperties->target->{$this->code};
                return $value;
            } else {
                $getter = $this->getter;

                return $getter($this);
            }
        } catch (IllegalArgumentException $e) {
            ;
        } catch (JavaException $e) {
            if (!$e->isIllegalArgumentException()) {
                throw $e;
            }
        }
    }

    /**
     * @param ElementPropertyEditor $editor
     */
    public static function register(ElementPropertyEditor $editor)
    {
        static::$editors[$editor->getCode()] = $editor;
    }

    /**
     * @param $code
     *
     * @return ElementPropertyEditor
     * @throws \Exception
     */
    public static function getByCode($code)
    {
        $editor = static::$editors[$code];

        if (!$editor) {
            throw new \Exception("Unable to find the '$code' editor");
        }

        return $editor;
    }
}