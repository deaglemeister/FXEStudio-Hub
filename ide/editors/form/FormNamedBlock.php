<?php
namespace ide\editors\form;

use ide\Ide;
use php\gui\effect\UXColorAdjustEffect;
use php\gui\event\UXEvent;
use php\gui\layout\UXAnchorPane;
use php\gui\UXApplication;
use php\gui\UXImageView;
use php\gui\UXLabel;
use php\lib\str;
use script\TimerScript;

/**
 * Class FormNamedBlock
 * @package ide\editors\form
 */
class FormNamedBlock extends UXAnchorPane
{
    /**
     * @var string
     */
    protected $title;

    /**
     * @var mixed
     */
    protected $icon;

    /**
     * @var UXImageView
     */
    protected $iconNode;

    /**
     * @var UXLabel
     */
    protected $label;

    /**
     * FormNamedBlock constructor.
     * @param $title
     * @param $icon
     */
    public function __construct($title, $icon)
    {
        parent::__construct();

        $this->maxSize = $this->minSize = $this->size = [32, 32];
        $this->style = '-fx-border-width: 1px; -fx-border-color: gray; -fx-background-color: #DCDCDC; -fx-border-radius: 3px; cursor: hand;';

        $label = new UXLabel($title);
        $label->id = 'title';
        $label->padding = [2, 5];
        $label->style = '-fx-background-color: #DCDCDC; -fx-border-color: silver; cursor: hand; -fx-border-radius: 2px; -fx-text-fill: black;';
       // $label->mouseTransparent = true;

        $this->label = $label;
        //$this->add($label);

        $this->setIcon($icon);
        $this->setTitle($title);

        $hUpdater = function ($old, $new) {
            $this->updateLabelX();
            if ($new < 0) { $this->x = 0; }
        };

        $vUpdater = function ($old, $new) use ($label) {
            $this->updateLabelY();
            if ($new < 0) { $this->y = 0; }
        };

        $this->observer('layoutX')->addListener($hUpdater);

        $this->observer('layoutY')->addListener($vUpdater);

        $this->observer('parent')->addListener(function ($old, $new) use ($label, $vUpdater, $hUpdater) {
            if ($new) {
                $new->add($label);

                $v = function () {
                    $this->updateLabelY();
                    $this->updateLabelX();
                };
                uiLater($v);
                waitAsync(100, $v); // fix bug!
                waitAsync(1000, $v); // fix bug!
            } else {
                uiLater(function () use ($label) {
                    $label->free();
                });
            }
        });

        $mouseUp = function () {
            UXApplication::runLater(function () {
                $this->parent->requestFocus();
                $this->updateLabelX();
                $this->updateLabelY();
            });
        };

        $label->on('click', $mouseUp);
        $this->on('click', $mouseUp);
    }

    protected function updateLabelX()
    {
        $centerX = $this->x + $this->width / 2;

        $width = $this->label->font->calculateTextWidth($this->label->text) + 5 * 2;
        $this->label->x = round($centerX - $width / 2);
    }

    protected function updateLabelY()
    {
        $this->label->y = $this->y + $this->height + 7;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param string $title
     */
    public function setTitle($title)
    {
        $this->title = $title;

        /** @var UXLabel $label */
        $label = $this->label;
        $label->text = $title;

        $this->updateLabelX();
        $this->updateLabelY();
    }

    /**
     * @return mixed
     */
    public function getIcon()
    {
        return $this->icon;
    }

    /**
     * @param mixed $icon
     */
    public function setIcon($icon)
    {
        $this->icon = $icon;

        $old = $this->lookup("#icon");

        if ($old) {
            $this->remove($old);
        }

        /** @var UXImageView $icon */
        $icon = Ide::get()->getImage($icon);

        if ($icon) {
            $icon->id = 'icon';
            $icon->style = 'cursor: hand;';
            $icon->mouseTransparent = true;
            $icon->position = [$this->minWidth / 2 - $icon->width / 2, $this->minHeight / 2 - $icon->height / 2];

            $icon->on('mouseUp', function () {
                $this->parent->requestFocus();
            });
            $this->add($icon);
        }

        $this->iconNode = $icon;

        $this->updateLabelY();
        $this->updateLabelX();
    }

    public function setInvalid($value)
    {
        if ($value) {
            $effect = new UXColorAdjustEffect();
            $effect->saturation = -1;
            $this->iconNode->effects->add($effect);
            $this->label->style = str::replace($this->label->style, '-fx-text-fill: black', '-fx-text-fill: red');
        } else {
            $this->iconNode->effects->clear();
            $this->label->style = str::replace($this->label->style, '-fx-text-fill: red', '-fx-text-fill: black');
        }
    }
}