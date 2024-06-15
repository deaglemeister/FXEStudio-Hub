<?php
namespace kosogroup\liver\ui\components\traits;

use php\gui\layout\UXHBox;
use php\gui\layout\UXVBox;
use php\lib\str;

trait Cascadable
{
    public function __call(string $method, array $arguments = [])
    {
       
        if(str::contains($method, 'set'))
        {    
            $explode = explode('set', $method);
            $property = array_pop($explode);
            $this->{str::lowerFirst($property)} = $arguments[0];
        }

        return $this;
    }

    public function deepSet($object, $property, $value)
    {
        $this->{$object}->{$property} = $value;
        return $this;
    }


    public function _setVgrow($value)
    {
        UXVBox::setVgrow($this, $value);
        return $this;
    }

    public function _setHgrow($value)
    {
        UXHBox::setHgrow($this, $value);
        return $this;
    }
}