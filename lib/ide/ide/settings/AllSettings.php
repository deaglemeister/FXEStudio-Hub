<?php
namespace ide\settings;

class AllSettings extends AbstractSettings
{
    /**
     * @return string
     */
    public function getTitle()
    {
        return 'FXEdition';
    }

    /**
     * @return string
     */
    public function getCode()
    {
        return 'general';
    }

    public function isAlways()
    {
        return true;
    }
}