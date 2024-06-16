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
        
       $this->paddingLeft = 3;
        
        $this->UXImageView = $image;
        $this->UXTextField = new UXTextField;
        $this->UXTextField->width = 700;
        $this->UXTextField->paddingBottom = 5; 
       # $this->UXTextField->paddingLeft = 32;
        $this->UXTextField->classesString = 'custom-text-field-welcome';
        $this->add($this->UXImageView);
        $this->add($this->UXTextField);

        UXHBox::setHgrow($this->UXTextField, 'ALWAYS');
        
        
        
    }
}