<?php

namespace ide\editors\templates\other;

use php\gui\UXLabel;
use php\gui\layout\UXHBox;
use php\gui\layout\UXVBox;
use php\gui\UXImage;
use php\gui\UXImageArea;
use php\lib\fs;

class ImageBoxOpenProject extends UXHBox
{
    /**
     * @var UXImageArea
     */
    protected $imageArea;

    /**
     * @var UXLabelEx
     */
    protected $titleLabel;
    protected $project;
    protected  $_clickSelected = '#43454a';
    protected  $_clickHover = '#43454a';
    /**
     * ImageBox constructor.
     * @param int $width
     * @param int $height
     */
    public function __construct($width, $height, $project)
    {

        parent::__construct();


        $this->padding = 4;
        $this->spacing = 4;

        $this->on("mouseEnter", function () {
            $this->backgroundColor = $this->_clickHover;
        });

        $this->on("mouseExit", function () {
            $this->backgroundColor = 'transparent';
        });

        $this->on('click', function () {
            $this->backgroundColor = $this->_clickSelected;
        });
        $this->project = $project;

        $iconUsersPath = fs::parent($project->getPath()) . '\.dn\ide\project\behaviours\FXECustomIcon.png';

        if (fs::exists($iconUsersPath)) {
            $this->_createImage($width, $height, true, $iconUsersPath);
        } else {
            $this->_createImage($width, $height);
        }

        $this->alignment = 'TOP_LEFT';

        $vbox = new UXVBox();


        $vbox->alignment = 'TOP_LEFT';

        $nameLabel = new UXLabel();
        $nameLabel->tooltipText = fs::nameNoExt($project->getName());
        $nameLabel->size = [500, 10];
        $nameLabel->textAlignment = 'LEFT';
        $nameLabel->alignment = 'TOP_LEFT';
        $nameLabel->text = fs::pathNoExt($project->getName());
        $nameLabel->font->bold = fs::pathNoExt($project->getName());
        $nameLabel->paddingLeft = 5;
        $nameLabel->classesString = 'ui-text';

        $pathLabel = new UXLabel();
        $pathLabel->textAlignment = 'LEFT';
        $pathLabel->size = [500, 10];
        $pathLabel->alignment = 'TOP_LEFT';
        $pathLabel->paddingTop = 5;
        $pathLabel->text = '~\\' . $project->getPath();
        $pathLabel->classesString = 'pathTextSilver';
        $pathLabel->paddingLeft = 5;

        $vbox->add($nameLabel);
        $vbox->add($pathLabel);
        $this->add($vbox);
        UXHBox::setHgrow($vbox, 'ALWAYS');
        $this->titleLabel = $nameLabel;
    }


    private function _createImage($width, $height, bool $mode = false, $iconUsersPath = null)
    {
        if ($mode) {
            $this->imageArea = $item = new UXImageArea();
            $item->size = [$width, $height];
            $item->centered = true;
            $item->stretch = true;
            $item->smartStretch = true;
            $item->proportional = true;
            $item->image = new UXImage($iconUsersPath);
            $this->add($item);
        } else {
            $item = new UXLabel();
            $item->size = [24, 24];

            $item->text = substr(fs::pathNoExt($this->project->getName()), 0, 1);
            $colorForInputLetter = $this->_getColor($item->text);
            $item->style = "-fx-background-color: linear-gradient(to top left, {$colorForInputLetter['primary']}, {$colorForInputLetter['secondary']}); -fx-background-radius: 4;";
            $item->alignment = 'CENTER';
            $item->font->bold = $this->_getContrastColor($colorForInputLetter['primary']);

            $item->textColor = $this->_getContrastColor($colorForInputLetter['primary']);
            $this->add($item);
        }
    }

    private function _getContrastColor($hexColor)
    {

        $r = hexdec(substr($hexColor, 1, 2));
        $g = hexdec(substr($hexColor, 3, 2));
        $b = hexdec(substr($hexColor, 5, 2));


        $brightness = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;


        return ($brightness > 125) ? "#000000" : "#ffffff";
    }

    private function _getColor($letter)
    {
        $letter = strtolower($letter);

        if (array_key_exists($letter, $this->_colors)) {
            return $this->_colors[$letter];
        } else {
            return ['primary' => ' #4d84e4 ', 'secondary' => '#5154e1'];
        }
    }


    private $_colors = [
        'a' => [
            'primary' => '#4ac587',
            'secondary' => '#f9c623'
        ],
        'b' => [
            'primary' => '#e7eee4',
            'secondary' => '#08085d'
        ],
        'c' => [
            'primary' => '#e6fddb',
            'secondary' => '#3745cc'
        ],
        'd' => [
            'primary' => '#5c14ef',
            'secondary' => '#7bb133'
        ],
        'e' => [
            'primary' => '#0061FFB8',
            'secondary' => '#66B3FFB8'
        ],
        'f' => [
            'primary' => '#FF00F0B8',
            'secondary' => '#7500FFB8'
        ],
        'g' => [
            'primary' => '#82b2e8',
            'secondary' => '#4331b6'
        ],
        'h' => [
            'primary' => '#0996f7',
            'secondary' => '#96ffd3'
        ],
        'i' => [
            'primary' => '#8ac689',
            'secondary' => '#e7ef74'
        ],
        'j' => [
            'primary' => '#e63148',
            'secondary' => '#a74a04'
        ],
        'k' => [
            'primary' => '#dcb4fc',
            'secondary' => '#c09c33'
        ],
        'l' => [
            'primary' => '#eb1027',
            'secondary' => '#ef0157'
        ],
        'm' => [
            'primary' => '#a911af',
            'secondary' => '#115021'
        ],
        'n' => [
            'primary' => '#968e31',
            'secondary' => '#fd6c47'
        ],
        'o' => [
            'primary' => '#c979c8',
            'secondary' => '#e14877'
        ],
        'p' => [
            'primary' => '#64dde6',
            'secondary' => '#22f2af'
        ],
        'q' => [
            'primary' => '#c6dc0a',
            'secondary' => '#0e2ea3'
        ],
        'r' => [
            'primary' => '#50c117',
            'secondary' => '#403ba4'
        ],
        's' => [
            'primary' => '#e2115a',
            'secondary' => '#a6266e'
        ],
        't' => [
            'primary' => '#f20edc',
            'secondary' => '#753876'
        ],
        'u' => [
            'primary' => '#7c89fc',
            'secondary' => '#fa85a0'
        ],
        'v' => [
            'primary' => '#1a1743',
            'secondary' => '#23c85f'
        ],
        'w' => [
            'primary' => '#04af2d',
            'secondary' => '#71ff2b'
        ],
        'x' => [
            'primary' => '#aaaefa',
            'secondary' => '#84b9c6'
        ],
        'y' => [
            'primary' => '#b9b905',
            'secondary' => '#339b16'
        ],
        'z' => [
            'primary' => '#3c8f51',
            'secondary' => '#4059d7'
        ]
    ];
}
