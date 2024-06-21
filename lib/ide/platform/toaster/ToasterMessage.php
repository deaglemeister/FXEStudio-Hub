<?php
namespace platform\toaster;

use action\Animation;
use php\gui\layout\UXHBox;
use php\gui\layout\UXVBox;
use php\gui\paint\UXColor;
use php\gui\UXButton;
use php\gui\UXFlatButton;
use php\gui\UXHyperlink;
use php\gui\UXImage;
use php\gui\UXImageView;
use php\gui\UXLabelEx;
use php\gui\UXNode;
use php\io\MemoryStream;
use Closure;

class ToasterMessage
{
    protected $__icon;
    public function setIcon(UXImage $icon)
    {
        $this->__icon = new UXImageView($icon);
        return $this;
    }
    
    protected $__title;
    public function setTitle(string $title)
    {
        $this->__title = new UXLabelEx($title);
        $this->__title->classes->add('h1');
        $this->__title->wrapText = true;
        return $this;
    }
    
    protected $__description;
    public function setDescription(string $description)
    {
        $this->__description = new UXLabelEx($description);
        $this->__description->classes->add('h2');
        $this->__description->wrapText = true;
        return $this;
    }
    
    protected $__closableTime = false;
    protected $__closableButton = true;
    
    public function setClosable($time = false, bool $button = true, Closure $closableClosure = null)
    {
        $this->__closableTime = $time;
        $this->__closableButton = $button;
        
        return $this;
    }
    
    
    private function _createCloseButton()
    {
        $close = $this->_b64image("iVBORw0KGgoAAAANSUhEUgAAABAAAAAQBAMAAADt3eJSAAAAIVBMVEUAAACqrrunq7umrr2nrL2prb2orr6prr2orb2prb3////p0lRRAAAACnRSTlMAPEBCl+nu7u/vGITVLQAAAAFiS0dECmjQ9FYAAAA2SURBVAjXY2DAA1gUGBiYHIAMxmAGBlEDkJCoAYgNFoIIMDCIT4YqF2tmQJWCKYZrhxuIEwAAlQIFpotHoMUAAAAASUVORK5CYII=");
                
        $button = new UXFlatButton;

        $button->graphic = new UXImageView($close);
        $button->alignment = "CENTER";
        
        $button->minWidth = $button->width = 16;
        $button->minHeight = $button->height = 16;
                
        $button->color = UXColor::of("#27282E");
        $button->hoverColor = UXColor::of("#3D3E43");
        $button->clickColor = UXColor::of("#303137");
        
        $button->on('action', function() { $this->_close(); } );
        
        return $button;
    }
    
    private function _b64image($base64string)
    {
        $memStream = new MemoryStream;
        $memStream->write(base64_decode($base64string));
        $memStream->seek(0);
        
        return new UXImage($memStream);
    }
    
    protected $__buttons = [];
    public function setButton(string $name, Closure $closure)
    {
        $button = new UXButton($name);
        $button->on('action', $closure);
        $this->__buttons[] = $button;
        return $this;
    }
    
    protected $__links = [];
    public function setLink(string $name, Closure $closure)
    {
        $link = new UXHyperlink($name);
        $link->on('action', $closure);
        $this->__links[] = $link;
        return $this;
    }
    
    protected $__uxNode;
    public function build()
    {
        $toastFrame = new UXVBox([]);
        $toastFrame->classes->add("idea-toast-frame");
        
        $hFrame = new UXHBox([]);
        $toastFrame->add($hFrame);
        $hFrame->classes->add("content-box");
        
        if($this->__icon) $hFrame->add($this->__icon);
        
        $vFrame = new UXVBox([]);
        $hFrame->add($vFrame);
        $vFrame->classes->add("content-box");
        
        if($this->__title) $vFrame->add($this->__title);
        if($this->__description) $vFrame->add($this->__description);
        
        if($this->__buttons || $this->__links)
        {
            $hToolBar = new UXHBox([]);
            $vFrame->add($hToolBar);
            $hToolBar->classes->add("button-bar");
            $hToolBar->alignment = "CENTER_LEFT";
            
            $hToolBar->children->addAll($this->__buttons);
            $hToolBar->children->addAll($this->__links);
        }
        
        if(!$this->__closableTime || $this->__closableButton)
        {
            $hFrame->add($this->_createCloseButton());
        }
        
        waitAsync($this->__closableTime ?: 25000, function () {
            if ($this->canClose) {
                $this->_close();
            }
        });
        
        $this->__uxNode = $toastFrame;
        return $toastFrame;
    }
    
    public $canClose = true;
    private function _close()
    {
        Animation::fadeOut($this->__uxNode, $this->__closableTime ?: 250, function () {
            $this->__uxNode->free();
            $this->canClose = false;
        });
    }
}
