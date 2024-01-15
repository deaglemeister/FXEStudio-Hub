<? 

namespace ide\forms\malboro;

use ide\editors\form\IdeTabPane;
use ide\forms\mixins\SavableFormMixin;
use ide\Ide;
use ide\IdeConfigurable;
use ide\IdeException;
use ide\Logger;
use ide\project\templates\DefaultGuiProjectTemplate;
use ide\systems\FileSystem;
use ide\systems\ProjectSystem;
use ide\systems\WatcherSystem;
use ide\utils\FileUtils;
use ide\utils\UiUtils;
use php\desktop\HotKeyManager;
use php\desktop\Robot;
use php\gui\designer\UXDesigner;
use php\gui\designer\UXDirectoryTreeValue;
use php\gui\designer\UXDirectoryTreeView;
use php\gui\designer\UXFileDirectoryTreeSource;
use php\gui\dock\UXDockNode;
use php\gui\dock\UXDockPane;
use php\gui\event\UXEvent;
use php\gui\event\UXKeyboardManager;
use php\gui\event\UXKeyEvent;
use php\gui\event\UXMouseEvent;
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

use ide\forms\malboro\Modals;

use ide\forms\malboro\Toasts;

class Updates
{
    public function checkUpdates()
    {
        $version = "1.8.0"; // Новая версия программы (может быть получена с сервера)
        $latestVersion = "1.8.0"; // Актуальная версия на текущий момент (старая версия программы и текущая)
        $url = "https://fdrgn.ru/apps_fx/cloud/dist/version.txt";
        $infoupdate = "https://fdrgn.ru/apps_fx/cloud/dist/infoupdate.txt";
        $file_contents = file_get_contents($infoupdate);
        // Получение текущей версии с сервера
        $current_version = @file_get_contents($url);

        $class = new Toasts;
        if ($current_version === false) {
          // Ошибка при получении информации с сервера
          $class->showToast("Нет доступа", "Нет доступа к серверу", "#FF4F44");
        } else {
          $current_version = trim($current_version); // Удаление возможных пробелов или символов новой строки
          if ($version == $current_version) {
            // Программа использует актуальную версию
            $class->showToast("Актуальная версия", "У вас установленна последняя версия", "#0077FF");
          } else {
            $project = Ide::get()->getOpenedProject();
            $modal = [
              'fitToWidth' => true, # Во всю длину
              'fitToHeight' => true, # Во всю ширину
              'blur' => app()->form('MainForm')->flowPane, # Объект для размытия
              'title' => 'Доступна новая версия FXEdition V' . $version, # Заголовок окна 
              'message' => $file_contents, # Сообщение (список обновлений)
              'color_overlay' => "#ff5252",
              'close_overlay' => true, # Закрывать при клике мыши на overlay
              'buttons' => [
                ['text' => 'Скачать новую версию', 'style' => 'button-red'],
                ['text' => 'Отмена', 'style' => 'button-accent', 'close' => true]
              ]
            ];
            $modalClass = new Modals;
            $MainFormZ = app()->form('MainForm');
            $modalClass->modal_dialog(app()->form('MainForm'), $modal, function($e) use ($MainFormZ) {
              if ($e == 'Скачать новую версию') {
                browse('https://github.com/deaglemeister/FXEdition/releases');
              }
            });
          }
        }
    }
}
