<?php

namespace platform\plugins;

abstract class FXEPlugin 
{
    abstract public function getName(): string;
    abstract public function getDescription(): string;
    abstract public function getVersion(): float;
    abstract public function getAuthor(): string;




    
    public function getIcon(): string 
    {
        return "icons/plugin32.png";
    }

    public function isCorePlugin(): bool 
    {
        return false;
    }

    public function getDependencies(): array
    {
        return [];
    }
}
