<? 

namespace ide\forms\malboro;

use php\gui\framework\AbstractForm;
use php\gui\framework\Preloader;
use php\gui\layout\UXAnchorPane;
use php\gui\layout\UXHBox;
use php\gui\layout\UXVBox;
use php\gui\UXAlert;
use php\gui\UXApplication;
use php\gui\UXButton;
use php\gui\UXForm;
use php\gui\UXImage;
use php\gui\UXImageView;
use php\gui\UXLabel;
use php\gui\UXMenu;
use php\gui\UXMenuBar;
use php\gui\UXMenuItem;
use php\gui\UXNode;
use php\gui\UXScreen;
use php\gui\UXSplitPane;
use php\gui\UXTab;
use php\gui\UXTabPane;
use php\gui\UXTextArea;
use php\gui\UXTreeView;
use php\io\File;
use php\lang\System;
use php\lib\fs;
use php\lib\str;
use script\TimerScript;
use php\time\Timer;
use std;
use action\Animation;

use php\gui\UXControl;
use php\gui\layout\UXScrollPane;

use httpclient;
use php\io\IOException;
use php\framework\Logger;
use facade\Json;
use bundle\http\HttpClient;
use discord\rpc\UserObject;
use discord\rpc\__KDiscord;
use discord\rpc\KDiscord;
use discord\rpc\KDiscordTypes;
use gui\Ext4JphpWindows;
use httpclient;
use Exception;
use php\desktop\Runtime;
use std, gui, framework, app;

class DiscordRPC
{
 function RPC() 
 {
    try {
        if (!($this->ipc instanceof KDiscord)) {
            $this->ipc = $ipc = new KDiscord("1195984777266855997");
        } else {
            $ipc = $this->ipc;
            $this->ipc->connect();
        }
        
    } catch (Exception $e) {}
    
    // событие когда произойдет подключение к серверам дискорда
    $ipc->on(KDiscordTypes::EVENT_READY, function (UserObject $obj) {
        $this->label->text = $obj->getUsername();
       
        $http = new HttpClient();
        
        $url = 'https://cdn.discordapp.com/avatars/' . $obj->getId() . '/' . $obj->getAvatar() . '.png';
        $http->getAsync($url, [], function (HttpResponse $response) {
            $mem = new MemoryStream();
            $mem->write($response->body(), $response->contentLength());
            $mem->seek(0);
            
            uiLater(function () use ($mem) {
                $this->image->image = new UXImage($mem);
            });
        });
    });
    
    // если пользователь чтото обновит в профиле
    $ipc->on(KDiscordTypes::EVENT_CURRENT_USER_UPDATE, function (UserObject $user) {
        var_dump($user->getUsername());
    });
    
    // если произойдет ошибка
    $ipc->on(KDiscordTypes::EVENT_ERROR, function ($message) {
        Logger::error($message);
    });
    
    $ipc->setState("_____ඞ");
    $ipc->setDetails("Test discord package");
    $ipc->setLargeImage("big", "amazing");
    $ipc->setSmallImage("small");
    
    $ipc->addButton("Download", "https://youtu.be/dQw4w9WgXcQ");
    $ipc->addButton("Здесь могла быть ваша реклама", "https://google.com/");
    
    $ipc->updateActivity();
    
    waitAsync(5000, function () use ($ipc) {
        $ipc->setState("___ඞ__");
        $ipc->removeButton(KDiscordTypes::X_BUTTON_FIRST);
        $ipc->updateActivity();
    });
    
    waitAsync(10000, function () use ($ipc) {
        $ipc->setState("ඞ_____");
        $ipc->setParty(str::hash("mygame"), 1, 5);
        $ipc->setSecrets(str::hash("mygame"), str::hash("mygame1"), str::hash("mygame2"));
        $ipc->updateActivity();
    });
    

 }
}
