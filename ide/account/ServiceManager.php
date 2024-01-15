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
use EventHandlerBehaviour;

class ServiceManager
{
    use EventHandlerBehaviour;

    protected $connectionOk = false;

    protected $status = [];

    protected $accountService;

    protected $profileService;

    protected $ideService;

    protected $projectService;

    protected $projectArchiveService;

    protected $noticeService;

    protected $fileService;

    protected $iconService;

    public function __construct()
    {
        // Конструктор оставим пустым, если нет необходимости в дополнительной логике.
    }

    protected function changeStatus($status)
    {
        // Этот метод пока что оставим пустым.
    }

    public function getEndpoint()
    {
        return System::getProperty('develnext.endpoint') ?: $this->endpoint;
    }

    public function updateStatus()
    {
        // Пока что оставим метод обновления статуса пустым.
    }

    public function canPrivate()
    {
        return $this->status['private'] == 'ok';
    }

    public function canPublic()
    {
        // Пока что оставим метод для определения возможности публичных действий пустым.
    }

    public function account()
    {
        return $this->accountService;
    }

    public function file()
    {
        return $this->fileService;
    }

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
        // Пока что оставим метод для работы с профилем пустым.
    }

    public function icon()
    {
        // Пока что оставим метод для работы с иконками пустым.
    }

    public function userAgent()
    {
        // В этом методе нет реализации, возможно, он должен что-то возвращать.
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