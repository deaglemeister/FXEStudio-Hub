<?php

namespace fxe\platform\editor;

use fxe\platform\support\traits\ActionHandler;

use php\gui\designer\UXDesigner;
use php\gui\UXNode;
use Closure;
use DateTime;
use Reflection;
use ReflectionClass;

class HistoryStash
{
    const EVENT_MOVE = "move";

    protected $__eventType;
    public $__snapshots = [];

    public $dateTime;

    public function __construct(array $nodes, string $eventType)
    {
        $this->__eventType = $eventType;
        //$this->dateTime = DateTime::
        
        foreach($nodes as $node)
            if($node)
                $this->__snapshots[]['UNDO'] = $this->_objectData($node);
    }

    public function append(array $nodes)
    {
        foreach($nodes as $node)
            if($node)
                $this->__snapshots[]['REDO'] = $this->_objectData($node);
    }

    private function _objectData(UXNode $node)
    {
        $data = [];

        foreach((new ReflectionClass($node))->getProperties() as $reflectionProperty)
        {
            $property = $reflectionProperty->name;

            $data[$property] = $node->$property;
        }

        $snapshot = json_decode(json_encode([
            'data' => $data
        ]));

        $snapshot->object = $node;
        return  $snapshot;
    }

    public function getEvent() : string
    {
        return $this->__eventType;
    }
}

class FormEditorHistoryManager
{
    use ActionHandler;
    
    protected $__designer = null;
    protected $__history = [];
    protected $__historyRedo = [];
    
    public function __construct()
    {
        $this->__resolveAction(HistoryStash::EVENT_MOVE, function(HistoryStash $historyStash, bool $undo = true)
        {
            $uxNodes = [];
            foreach($historyStash->__snapshots as $snapshot){
            {
                $snapshot = $snapshot[$undo ? 'UNDO' : 'REDO'];
                if(!$snapshot) continue;

                $uxNode = $snapshot->object;

                $uxNode->x = $snapshot->data->x;
                $uxNode->y = $snapshot->data->y;

                $uxNodes[] = $uxNode;
            }
        }
            if($this->__designer)
            {
                $this->__designer->unselectAll();

                foreach($uxNodes as $uxNode)
                    $this->__designer->selectNode($uxNode);
            }  
        });

    }

    public function setDesigner(UXDesigner $designer)
    {
        $this->__designer = $designer;
    }

    public function writeStash(HistoryStash $historyStash)
    {
        $this->__history[] = $historyStash;
    }

    public function undo()
    {
        if(($this->__history != null) && count($this->__history) > 0)
        {
            $historyStash = $this->__history[count($this->__history) - 1];

            $this->__handleAction($historyStash->getEvent(), $historyStash, true);
        
            $this->__historyRedo[] = $historyStash;
        
            unset($this->__history[count($this->__history) - 1]);
        }
    }

    public function redo()
    {
        if(($this->__historyRedo != null) && count($this->__historyRedo) > 0)
        {
            $historyStash = $this->__historyRedo[count($this->__historyRedo) - 1];

            $this->__handleAction($historyStash->getEvent(), $historyStash, false);
        
            $this->__history[] = $historyStash;
        
            unset($this->__historyRedo[count($this->__historyRedo) - 1]);
        }
    }


}
