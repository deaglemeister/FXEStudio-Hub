<?php
namespace ide\account;

use ide\account\api\AbstractService;
use ide\account\api\AccountService;
use ide\account\api\IconService;
use ide\account\api\FileService;
use ide\account\api\NoticeService;
use ide\account\api\ProfileService;
use ide\account\api\ProjectArchiveService;
use ide\account\api\ProjectService;
use ide\account\api\ServiceResponse;
use ide\Ide;
use ide\Logger;
use ide\misc\EventHandlerBehaviour;
use ide\ui\Notifications;
use ide\utils\Json;
use php\lang\IllegalArgumentException;
use php\lang\System;
use script\TimerScript;

/**
 * Class ServiceManager
 * @package ide\account
 */
class ServiceManager
{
    use EventHandlerBehaviour;

    protected $connectionOk = false;

    /**
     * @var string
     */
    /* protected $endpoint = 'https://api.develnext.org/'; */

    /**
     * @var array
     */
    protected $status = [
     /*    'private' => '',
        'public'  => '', */
    ];

    /**
     * @var AccountService
     */
    protected $accountService;

    /**
     * @var ProjectService
     */
    protected $profileService;

    /**
     * @var IdeService
     */
    protected $ideService;

    /**
     * @var ProjectService
     */
    protected $projectService;

    /**
     * @var ProjectArchiveService
     */
    protected $projectArchiveService;

    /**
     * @var NoticeService
     */
    protected $noticeService;

    /**
     * @var FileService
     */
    protected $fileService;

    /**
     * @var IconService
     */
    protected $iconService;

    /**
     * ServiceManager constructor.
     */
    public function __construct()
    {
          /* Пусто */
    }

    protected function changeStatus($status)
    {
          /* Пусто */
    }

    /**
     * @return string
     */
    public function getEndpoint()
    {
        if ($sysEndpoint = System::getProperty('develnext.endpoint')) {
            return $sysEndpoint;
        }

        return $this->endpoint;
    }

    public function updateStatus()
    {
            /* Пусто */
    }

    /**
     * @return bool
     */
    public function canPrivate()
    {
        return $this->status['private'] == 'ok';
    }

    /**
     * @return bool
     */
    public function canPublic()
    {
           /* Пусто */
    }

    /**
     * @return AccountService
     */
    public function account()
    {
         /* Пусто */
    }

    /**
     * @return FileService
     */
    public function file()
    {
        return $this->fileService;
    }

    /**
     * @return IdeService
     */
    public function ide()
    {
        return $this->ideService;
    }

    public function project()
    {
        return $this->projectService;
    }

    public function projectArchive()
    {
        return $this->projectArchiveService;
    }

    public function profile()
    {
          /* Пусто */
    }

    public function icon()
    {
         /* Пусто */
    }

    public function userAgent()
    {
        $ide = Ide::get();
    }

    public function shutdown()
    {
        $class = new \ReflectionClass($this);

        foreach ($class->getProperties() as $property) {
            $property->setAccessible(true);
            $value = $property->getValue($this);

            if ($value instanceof AbstractService) {
                unset($this->{$property->getName()});
            }
        }
    }
}