<?php
namespace ide\behaviour\spec;

use behaviour\custom\BlinkAnimationBehaviour;
use behaviour\custom\ChatterAnimationBehaviour;
use behaviour\custom\DraggingBehaviour;
use behaviour\custom\DraggingFormBehaviour;
use behaviour\custom\GameSceneBehaviour;
use ide\behaviour\AbstractBehaviourSpec;
use ide\formats\form\AbstractFormElement;
use ide\formats\form\elements\FormFormElement;
use ide\formats\form\elements\GamePaneFormElement;
use ide\formats\form\elements\PanelFormElement;
use ide\formats\form\elements\ScrollPaneFormElement;
use ide\formats\form\tags\GamePaneFormElementTag;
use ide\library\IdeLibraryScriptGeneratorResource;
use ide\scripts\AbstractScriptComponent;
use php\gui\UXNode;

class GameSceneBehaviourSpec extends AbstractBehaviourSpec
{
    /**
     * @return string
     */
    public function getName()
    {
        return 'Игровая сцена';
    }

    public function getGroup()
    {
        return self::GROUP_GAME;
    }

    public function getIcon()
    {
        return "icons/gameMonitor16.png";
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return 'Позволяет сделать объект полем для игровых объектов с эмуляцией физики';
    }

    /**
     * @return string
     */
    public function getType()
    {
        return GameSceneBehaviour::class;
    }

    /**
     * @param $target
     * @return bool
     */
    public function isAllowedFor($target)
    {
        return ($target instanceof FormFormElement)
                || ($target instanceof GamePaneFormElement);
    }

    public function getScriptGenerators()
    {
        return [
            new IdeLibraryScriptGeneratorResource('res://.dn/bundle/game2d/scriptGenerators/LoadSceneScriptGen'),
        ];
    }


}