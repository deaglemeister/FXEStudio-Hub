<?php

namespace ide\editors\templates\other;

use php\gui\event\UXKeyEvent;
use php\gui\layout\UXHBox;
use php\gui\UXTextField;

class UXTextFieldIcon extends UXHBox
{
    public $UXTextField;
    
    public $UXImageView;
    
    public function __construct($image)
    {
            
        parent::__construct();
        
        $this->spacing = -696;
        
        $this->UXImageView = $image;
        $this->UXTextField = new UXTextField;
        $this->UXTextField->width = 700;
        $this->UXTextField->paddingLeft = 30;
        $this->UXTextField->classesString = 'custom-text-field-welcome';
        $this->add($this->UXTextField);
        $this->add($this->UXImageView);
        
        
        
        
    }
}