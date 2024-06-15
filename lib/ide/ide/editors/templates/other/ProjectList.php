<?php

namespace ide\editors\templates\other;

use php\gui\layout\UXScrollPane;

use php\gui\layout\UXVBox;

class ProjectList extends UXVBox{

    protected  $__scrollPane;
    protected  $__layout;

    public function __construct()
    {
        parent::__construct(); 
        
        $this->__layout = new UXVBox();
        $this->__layout->alignment = 'TOP_LEFT';
        $this->__scrollPane = new UXScrollPane($this->__layout);
        $this->__scrollPane->classesString = 'dn-scroll-pane';
        UXVBox::setVgrow($this->__scrollPane,'ALWAYS');
        $this->__scrollPane->fitToWidth = true; 
        $this->__scrollPane->fitToHeight = true; 
    
        $this->add($this->__scrollPane);

    }

    public function addChildren($children)
    {
       
        $this->__layout->add($children);

    }

    public function clearChildren() 
    {
        $this->__layout->children->clear();
    }

    public function getCountChildren()
    {
        return $this->__layout->children->count();
    }

    public function getArrayChildren()
    {
        return $this->__layout->children->toArray();
    }
    public function addAllChildren(array $children)
    {
        $this->__layout->children->addAll($children);
    }
}