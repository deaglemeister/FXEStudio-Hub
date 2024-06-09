<?php
namespace ide\forms;

use php\gui\UXImageViewWrapper;
use ide\Ide;
use ide\Logger;
use ide\systems\SplashTipSystem;
use php\gui\event\UXEvent;
use php\gui\framework\AbstractForm;
use php\gui\layout\UXAnchorPane;
use php\gui\layout\UXHBox;
use php\gui\layout\UXVBox;
use php\gui\UXApplication;
use php\gui\UXImage;
use php\gui\UXImageArea;
use php\gui\UXImageView;
use php\gui\UXLabel;
use php\io\IOException;
use php\io\Stream;

use php\lang\Thread;
use php\lang\ThreadPool;
use php\lib\str;
use script\storage\IniStorage;

use php\gui;
use std, gui, framework, app;

use php\time\Time;

class NewSplashForm extends AbstractIdeForm
{

    public $Logger = false;
    public $LoggerConsole = false;

    protected function init()
    {
        
    $greetings = [
    0 => 'Доброе утро, о гуру кода и воина без пальцев!',
    1 => 'Привет, виртуоз программирования и чемпион по нажатию клавиш!',
    2 => 'Салют, цифровой магистр и хозяин невидимых кнопок!',
    3 => 'Рад видеть, создатель виртуальных миров и чемпион по Ctrl+C/Ctrl+V!',
    4 => 'Привет, о великий виртуальный архитектор и лорд клавиш!',
    5 => 'Доброго дня, сущность из кода и дракон без пальцев!',
    6 => 'Здравствуй, о великий создатель кода и чародей стилуса!',
    7 => 'Привет, творец цифровой реальности и чемпион по магии кода!',
    8 => 'Добрый вечер, маг кода и алгоритмов, владыка багов!',
    9 => 'Привет, великий гуру программирования и смельчак без пальцев!',
    10 => 'Добрейший кодер, доброе утро, создатель виртуальных сказок!',
    11 => 'Приветствую, сущность из бинарного мира и владыка экранных кнопок!',
    12 => 'Доброе утро, о волшебник алгоритмов и магистр невидимых клавиш!',
    13 => 'Привет, создатель виртуальной реальности и ковен программистов!',
    14 => 'Добрейшего дня, кодовый маг и шутник над байтами!',
    15 => 'Здравствуй, о великий создатель кода и главный пальчиководитель!',
    16 => 'Привет, алгоритмический гений и главный шаман кодовых структур!',
    17 => 'Добрый вечер, о программист великолепия и арбитр кривых функций!',
    18 => 'Рад видеть, создатель байтового волшебства и король электронного царства!',
    19 => 'Привет, архитектор байтов и битов, главный кузнец кода!',
    20 => 'Доброе утро, мастер кодирования и паладин программирования!',
    21 => 'Привет, волшебник кода и алгоритмов, поборник багов и смешных комментариев!',
    22 => 'Добрый день, создатель цифровых чудес и вождь битовых племен!',
    23 => 'Салют, кодовый магистр и владыка электронного сокровища!',
    24 => 'Приветствую, архитектор цифровых реальностей и лорд виртуального кода!',
    25 => 'Доброе утро, создатель алгоритмических сказок и хозяин волшебного терминала!',
    26 => 'Привет, о мудрый создатель программ и капитан космических кодов!',
    27 => 'Рад видеть, творец битового волшебства и волшебник виртуальной арифметики!',
    28 => 'Добрейшего вечера, создатель виртуальных миров и хранитель цифровых тайн!',
    29 => 'Привет, о гуру кода и байтов, повелитель всех экранных джойстиков!',
    30 => 'Доброе утро, виртуальный маг и чародей клавишных сочетаний!',
    31 => 'Салют, создатель байтового искусства и великий архитектор электронного замка!',
    32 => 'Привет, о великий мастер программирования и чемпион по созданию электронных чудес!',
    33 => 'Доброго дня, архитектор цифрового волшебства и лучник бинарного леса!',
    34 => 'Привет, создатель байтовых чудес и цифровой кузнец!',
    35 => 'Доброе утро, цифровой волшебник и алхимик кода!',
    36 => 'Привет, создатель виртуальных чудес и великий капитан космического кода!',
    37 => 'Добрый вечер, магистр алгоритмов и волшебник электронной магии!',
    38 => 'Приветствую, создатель цифрового волшебства и великий архитектор электронного сновидения!',
    39 => 'Доброе утро, великий архитектор кода и владыка клавишных заклинаний!',
    40 => 'Салют, цифровой волшебник и маг программирования!',
    41 => 'Рад видеть, создатель байтовых сказок и архимаг кода!',
    42 => 'Привет, о мудрый создатель программ и главный палач багов!',
    43 => 'Что то написал',
    ];
    $time = Time::now()->toString('HH:mm');
    $hour = (int)explode(':', $time)[0]; // Получаем время
    $random = rand(0,43);
        if ($hour >= 0 && $hour < 6) {// с 00:00 до 6:00
            $this->greetings->text = $greetings[$random];
        } elseif ($hour >= 6 && $hour < 12) {// с 6:00 до 12:00
            $this->greetings->text = $greetings[$random];
    
        } elseif ($hour >= 12 && $hour < 18) {// с 12:00 до 18:00
            $this->greetings->text = $greetings[$random];
    
        } else {                            // с 18:00 до 23:59
            $this->greetings->text = $greetings[$random];
        }
        $this->status-> text = 'Hide Splash Form';
        $this->centerOnScreen();
        $this->waitAndHide(); // Обновленное название функции
    }
    
    public function waitAndHide(int $timeoutMs = 1)
    {
        $settings = json_decode(file_get_contents('tools\Settings\Settings.ini'), true);
    
        $image = new UXImageArea();
        $image->autoSize = false;
        $image->centered = false;
        $image->stretch = true;
        $image->proportional = false;
        $image->mosaic = false;
        $image->width = 744;
        $image->height = 346;
        if ($settings['Splash'] == true){
            $path = $settings['SplashImage'];
            if($path == null or fs::isFile($path) == false){
                $this->HideNOWAIT(1);
                pre('Для корректной работы загрузочного экрана выберите изображение в настройках или отключите загрузочный экран. ' .$path);
            }else{
                $image->image = new UXImage($path);
                $this->add($image);
                $this->greetings->toFront();
                $this->panel->toFront();
                $this->panelstatus->toFront();
                $this->status->toFront();
                $time = $settings['Splash Time'];
                $milliseconds = $time * 1000;
                $this->HideNOWAIT($milliseconds);
            }
        }else{
            $this->HideNOWAIT(1);
        }
    }
    function HideNOWAIT($timeoutMs){
        waitAsync($timeoutMs, function () use ($timeoutMs) {
            $mainForm = $this->_app->getMainForm();
            if ($mainForm instanceof UXForm && $mainForm->visible) {
                $this->hide();
                return;
            }
            if ($this->visible) {
                $this->waitAndHide($timeoutMs);
                $this->setVisible(false);
            }
            });
    }

    public $string;
    public $Path;
    public $NameFolder;
    public $NameLog;


    public function WriteLog($string){ // пишем в лог

        $Time = str_replace(' ', '-', Time::now()->toString('yyyy-MM-dd HH:mm:ss'));
        $string = "[$Time]: $string \r\n";
        Stream::putContents($this->Path."\\".$this->NameLog.".log", $string, "a+");
    }

    public function CreateLog($string, $appdata){// создаем лог и папку под него 
        $this->NameFolder = str_replace(':', '~', str_replace(' ', '-', Time::now()->toString('yyyy-MM-dd HH:mm')));
        $this->NameLog = Time::millis(); 
        $this->Path = $appdata ."\\". $this->NameFolder; 
        if ($this->LoggerConsole == true) {var_dump($this->Path); }
        
        $this->string = $string;
        fs::makeDir($this->Path);
        $this->WriteLog($string);
    }

    public function CheckAndCreatFolder($string){    // создаем папку в AppData если ее нет и выполняет все функции.
        $appdata=fs::abs('./'."\\logs");
        if (fs::isDir($appdata)) {
            $this->CreateLog($string, $appdata);
        } else {
            fs::makeDir($appdata);
            $this->CreateLog($string, $appdata);
        }
    }

    
}
