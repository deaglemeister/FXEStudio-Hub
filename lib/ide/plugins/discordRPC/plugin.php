<?php

use platform\plugins\FXEPlugin;
use platform\plugins\traits\EditorEvents;
use ide\editors\AbstractEditor;
use mafujo\jphp\discord\DiscordRPC;
use mafujo\jphp\discord\rp\RPBuilder;
use php\lib\fs;
use php\time\Time;

return new class extends FXEPlugin
{

    public function getName(): string
    {
        return 'Discord RPC';
    }
    public function getDescription(): string
    {
        return '';
    }
    public function getVersion(): float
    {
        return 1.0;
    }
    public function getAuthor(): string
    {
        return 'Demonck';
    }

    
    protected DiscordRPC $__discordRPC;
    protected Time $__currentTime;

    public function __construct()
    {
        $this->__currentTime = Time::now();
        $this->__discordRPC = new DiscordRPC(1226500033784975442);

        try {
            $this->__discordRPC->connect();
            $this->time = Time::now()->getTime();
            $rp = (new RPBuilder)
                ->setStartTimestamp($this->__currentTime->getTime())
                ->setDetails("DevelNext")
                ->setState("FXEdition")
                ->build();

            $this->__discordRPC->sendRichPresence($rp);
        } catch (Exception $exception) {
        }
    }

    use EditorEvents;

    public function handleRequestFocus(AbstractEditor $editor)
    {

        try {
            $rp = (new RPBuilder)
                ->setStartTimestamp($this->__currentTime->getTime())
                ->setDetails("Редактирует файл")
                ->setState(fs::name($editor->getFile()))
                ->setLargeImage('application_icon', "sdfag")
                ->setSmallImage('application_icon', "sdfag")
                ->build();
        } catch (Exception $exception) {
        }

        $this->__discordRPC->sendRichPresence($rp);
    }
};
