<?php
namespace ide\bundle\std;

use develnext\bundle\game2d\Game2DBundle;
use ide\bundle\AbstractJarBundle;
/**
 * Class JPHPGuiDesktopBundle
 * @deprecated
 * @package ide\bundle\std
 */
class JPHPGuiDesktopBundle extends AbstractJarBundle
{
    function getName()
    {
        return "JPHP UI Desktop";
    }

    public function useNewBundles()
    {
        return [
            UIDesktopBundle::class,
            Game2DBundle::class
        ];
    }
}