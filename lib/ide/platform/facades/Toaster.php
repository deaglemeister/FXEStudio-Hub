<?php
namespace platform\facades;

use platform\toaster\ToasterMessage;
use php\lang\Thread;
use php\lang\ThreadPool;

class Toaster
{
    protected static $__instance;
    public static function getInstance()
    {
        if(!static::$__instance) static::$__instance = new static;
        return static::$__instance;
    }
    
    public static function show(ToasterMessage $toasterMessage)
    {
        static::getInstance()->__show($toasterMessage);
    }
    
    protected $__threadPool;

    public function __construct()
    {
        $this->__threadPool = ThreadPool::createFixed(3);
    }

    protected function __show(ToasterMessage $toasterMessage)
    {
        $this->__threadPool->execute(function() use ($toasterMessage)
        {
            uiLater(function() use ($toasterMessage) {
                $mainForm = app()->getForm("MainForm");
                $mainForm->toasterContainer->add($toasterMessage->build());
            });
            
            while ($toasterMessage->canClose) Thread::sleep(1000);
       });
    }
}