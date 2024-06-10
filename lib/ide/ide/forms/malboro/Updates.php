<? 

namespace ide\forms\malboro;


use ide\Ide;
use ide\forms\malboro\Modals;
use ide\forms\malboro\Toasts;
use platform\facades\Toaster;
use platform\toaster\ToasterMessage;
use php\gui\UXImage;

class Updates
{
    public function checkUpdates()
    {
  

      $version = "3.2.0"; // Новая версия программы (может быть получена с сервера)
      $latestVersion = "3.1.1"; // Актуальная версия на текущий момент (старая версия программы и текущая)
      $url = "https://fdrgn.ru/version.txt";
      $infoupdate = "https://fdrgn.ru/infoupdate.txt";
      
      // Получение информации об обновлении с сервера
      $file_contents = file_get_contents($infoupdate);
      
      // Получение текущей версии с сервера
      $current_version = file_get_contents($url);
      
      $class = new Toasts;
      
      if ($current_version === false) {
          // Ошибка при получении информации с сервера
          $tm = new ToasterMessage();
          $iconImage = new UXImage('res://resources/expui/icons/fileTypes/Error.png');
          $tm
              ->setIcon($iconImage)
              ->setTitle('Менеджер по работе с обновлениями')
              ->setDescription(_('Соединение с сервером не возможно установить.'))
              ->setLink('Повторить попытку', function () {
                  $this->checkUpdates();
              })
              ->setClosable();
          Toaster::show($tm);
      } else {
          $current_version = trim($current_version); // Удаление возможных пробелов или символов новой строки
      
          if ($version == $current_version) {
              // Программа использует актуальную версию
              $class->showToast(_('modals.actual.version'), _('modals.last.version'), "#0077FF");
          } else {
              $project = Ide::get()->getOpenedProject();
              $modal = [
                  'fitToWidth' => true, // Во всю длину
                  'fitToHeight' => true, // Во всю ширину
                  'blur' => app()->form('MainForm')->flowPane, // Объект для размытия
                  'title' => _('modals.new.version') . $version, // Заголовок окна 
                  'message' => $file_contents, // Сообщение (список обновлений)
                  'color_overlay' => "#ff5252",
                  'close_overlay' => true, // Закрывать при клике мыши на overlay
                  'buttons' => [
                      ['text' => _('modals.downoload.new'), 'style' => 'button-red'],
                      ['text' => _('modals.cancel.updater'), 'style' => 'button-accent', 'close' => true]
                  ]
              ];
              $modalClass = new Modals;
              $MainFormZ = app()->form('MainForm');
              $modalClass->modal_dialog(app()->form('MainForm'), $modal, function ($e) use ($MainFormZ) {
                  if ($e == _('modals.downoload.new')) {
                      browse('https://github.com/deaglemeister/FXEdition/releases');
                  }
              });
          }
      }

    
    }
}
