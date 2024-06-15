
<?php

namespace kosogroup\liver\traits;

use Closure;

trait EventEmiter
{
    protected $__eventHandlers = [];
    protected $__eventEmitable = null;

    // eventHandlers => ['eventName'][] => handlers
 
    public function __eventEmit($eventName, $content)
    {
        if($this->__eventEmitable != null)
        {
            $this->__eventEmitable->__eventEmit($eventName, $content);
            return;
        }

        if(isset($this->__eventHandlers[$eventName]))
        {
            if(count($this->__eventHandlers[$eventName]) > 0)
                foreach($this->__eventHandlers[$eventName] as $eventHandler)
                    $eventHandler($content);
        }
        return $this;
    }

    public function eventHandle($eventName, Closure $closure)
    {
        $this->__eventHandlers[$eventName][] = $closure;
        return $this;
    }

    public function eventRedirect($eventEmitable)
    {
        $this->__eventEmitable = $eventEmitable;
        return $this;
    }


}