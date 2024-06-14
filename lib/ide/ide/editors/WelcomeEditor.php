<?php
namespace ide\editors;

use Exception;
use ide\commands\NewProjectCommand;
use ide\commands\OpenProjectCommand;
use ide\Ide;
use php\gui\UXLoader;
use php\gui\UXNode;
use git\Git;
use git\GitAPIException;
use script\DirectoryChooserScript;
use php\gui\UXForm;
use php\gui\UXTextField;
use php\gui\UXLabel;
use php\gui\UXButton;
use php\gui\UXImageView;
use php\gui\UXImage;
use php\gui\layout\UXVBox;
use php\gui\layout\UXHBox;
use platform\facades\Toaster;
use platform\toaster\ToasterMessage;
use php\lang\Thread;


class WelcomeEditor extends AbstractEditor
{
    public function isCloseable()
    {
        return false;
    }

    public function getTitle()
    {
        return _('welcome.title');
    }


    public function getIcon()
    {
        return 'icons/homeFolder_dark.png';
    }

    public function isAutoClose()
    {
        return false;
    }

    public function load()
    {
        // nop.
    }

    public function save()
    {
        // nop.
    }

    /**
     * @return UXNode
     */
    public function makeUi()
    {
        $loader = new UXLoader();

        $layout = $loader->load('res://.forms/blocks/_Welcome.fxml');

        Ide::get()->getL10n()->translateNode($layout);

        $layout->lookup('#createProjectButton')->on('click', function () {
            Ide::get()->executeCommand(NewProjectCommand::class);
        });

        $layout->lookup('#openProjectButton')->on('click', function () {
            Ide::get()->executeCommand(OpenProjectCommand::class);
        });
        $layout->lookup('#cloneProjectFile')->on('click', function(){
            $formGit = new UXForm();
            $formGit->size = [400, 185];
            $formGit->title = 'Загрузка репозиториев';
            $formGit->modality = 'APPLICATION_MODAL';
            $formGit->resizable = false;
            $formGit->centerOnScreen();
            
            $layoutVbox = new UXVBox();
            $layoutVbox->padding = 10;
            $layoutVbox->spacing = 10;
            
            $label = new UXLabel('Загрузите свой репозиторий с Git.');
            $layoutVbox->add($label);
            
            $layoutUrlHbox = new UXHBox();
            $layoutUrlHbox->spacing = 10;
            
            $urlLabel = new UXLabel('URL:');
            $urlLabel->size = [51, 17];
            $layoutUrlHbox->add($urlLabel);
            
            $editRepository = new UXTextField();
            $editRepository->promptText = 'Введите ссылку на репозиторий';
            $editRepository->alignment = 'CENTER_LEFT'; // Центровка иконки по вертикали
            $editRepository->size = [300, 25];
            $editRepository->hgrow = 'ALWAYS'; // Растянуть текстовое поле по горизонтали
            $layoutUrlHbox->add($editRepository);
            
            $layoutVbox->add($layoutUrlHbox);
            
            $layoutDirectoryHBox = new UXHBox();
            $layoutDirectoryHBox->spacing = 10;
            
            $directoryLabel = new UXLabel('Directory:');
            $layoutDirectoryHBox->add($directoryLabel);
            
            $directoryField = new UXTextField();
            $directoryField->promptText = 'Выберите вашу директорию';
            $directoryField->alignment = 'CENTER_LEFT'; // Центровка иконки по вертикали
            $directoryField->size = [300, 25];
            $directoryField->hgrow = 'ALWAYS'; // Растянуть текстовое поле по горизонтали
            $layoutDirectoryHBox->add($directoryField);
            
            $iconHBox = new UXHBox(); // Обертка для иконки
            $iconHBox->alignment = 'CENTER'; // Центровка иконки по вертикали
            $iconHBox->paddingLeft = -45; // Сдвинуть иконку влево
            $folderIcon = new UXImageView(new UXImage('res://resources/expui/icons/sourceRoot_dark.png'));
            $folderIcon->size = [16, 16];
            $folderIcon->cursor = 'HAND';
            $folderIcon->on('click', function() use ($directoryField) {
                $chooser = new DirectoryChooserScript();
                $directory = $chooser->execute();
                if ($directory !== null) {
                    $directoryField->text = $directory;
                }
            });
            $iconHBox->add($folderIcon);
            
            $layoutDirectoryHBox->add($iconHBox);
            $layoutVbox->add($layoutDirectoryHBox);
            
            $cloneButton = new UXButton('Склонировать проект');
            $cloneButton->on('click', function() use ($editRepository, $directoryField, $formGit) {
                    Ide::get()->getMainForm()->showPreloader('Клонируем проект, подождите..'); // Показываем индикатор загрузки перед запуском потока
            
                (new Thread(function() use ($editRepository, $directoryField, $formGit) {
                    try {
                        $url = $editRepository->text;
                        $directory = $directoryField->text;
            
                        if (empty($url) || empty($directory)) {
                            var_dump("URL and directory must not be empty.");
                            $tm = new ToasterMessage();
                            $iconImage = new UXImage('res://resources/expui/icons/error.png');
                            $tm
                            ->setIcon($iconImage)
                            ->setTitle('Менеджер по работе с Git')
                            ->setDescription(_('URL и каталог не должны быть пустыми.'))
                            ->setClosable();
                            Toaster::show($tm);
                            return;
                        }
            
                        $git = new Git($directory);
            
                        // Проверяем, существует ли репозиторий в указанной директории
                        if (!$git->isExists()) {
                            // Инициализируем новый репозиторий, если он не существует
                            $git->init();
                        }
            
                        // Добавляем удаленный репозиторий
                        $git->remoteAdd('origin', $url);
            
                        // Выкачиваем данные из удаленного репозитория (все ветки)
                        $git->fetch(['remote' => 'origin']);
            
                        // Переключаемся на главную ветку (обычно это main, master или другая)
                        // Необходимо получить список доступных веток и выбрать актуальную
                        $branches = $git->branchList(['listMode' => 'REMOTE']);
                        $mainBranchFound = false;
                        foreach ($branches as $branch) {
                            if (strpos($branch['name'], 'origin/') !== false) {
                                $git->checkout([
                                    'name' => str_replace('origin/', '', $branch['name']),
                                    'createBranch' => true,
                                    'startPoint' => $branch['name']
                                ]);
                                $mainBranchFound = true;
                                break;
                            }
                        }
            
                        if (!$mainBranchFound) {
                            var_dump("No main branch found in the remote repository.");
                            $tm = new ToasterMessage();
                            $iconImage = new UXImage('res://resources/expui/icons/fileTypes/error.png');
                            $tm
                            ->setIcon($iconImage)
                            ->setTitle('Менеджер по работе с Git')
                            ->setDescription(_('В удаленном репозитории не найдена основная ветка'))
                            ->setClosable();
                            Toaster::show($tm);
                            return;
                        }
            
                        var_dump("Repository successfully cloned and branch switched.");
                        $tm = new ToasterMessage();
                        $iconImage = new UXImage('res://resources/expui/icons/fileTypes/info.png');
                        $tm
                        ->setIcon($iconImage)
                        ->setTitle('Менеджер по работе с Git')
                        ->setDescription(_('Ваш проект был успешно склонировал а так же добавлен в "Фомру открытие проекта", открыть этот проект?'))
                        ->setLink('Открыть проект', function() {})
                        ->setClosable();
                        Toaster::show($tm);
            
                        // Скрыть окно после успешного клонирования
                        #uiLater(function() use ($formGit) {
                         #   $formGit->hide();
                       # });
            
                    } catch (IOException $e) {
                        $tm = new ToasterMessage();
                        $iconImage = new UXImage('res://resources/expui/icons/fileTypes/error.png');
                        $tm
                        ->setIcon($iconImage)
                        ->setTitle('Менеджер по работе с Git')
                        ->setDescription(_('Ошибка ввода/вывода: ' . $e->getMessage()))
                        ->setClosable();
                        Toaster::show($tm);
                    } catch (GitAPIException $e) {
                        $tm = new ToasterMessage();
                        $iconImage = new UXImage('res://resources/expui/icons/fileTypes/error.png');
                        $tm
                        ->setIcon($iconImage)
                        ->setTitle('Менеджер по работе с Git')
                        ->setDescription(_('Ошибка Git API: ' . $e->getMessage()))
                        ->setClosable();
                        Toaster::show($tm);
                    } catch (Exception $e) {
                        Ide::get()->getMainForm()->hidePreloader();
                        $tm = new ToasterMessage();
                        $iconImage = new UXImage('res://resources/expui/icons/fileTypes/error.png');
                        $tm
                        ->setIcon($iconImage)
                        ->setTitle('Менеджер по работе с Git')
                        ->setDescription(_('Произошла ошибка: ' . $e->getMessage()))
                        ->setClosable();
                        Toaster::show($tm);
                    } finally {
                        // Скрыть индикатор загрузки в любом случае (по завершении потока)
                        uiLater(function () {
                            Ide::get()->getMainForm()->hidePreloader();
                        });
                    }
                }))->start();
            });
            
            $layoutVbox->add($cloneButton);
            $formGit->add($layoutVbox);
            $formGit->showAndWait();
            $formGit->hide();
        });

       # $layout->lookup('#openTg')->on('click', function () {
          #  browse('https://t.me/fxedition17');
       # });
       # $layout->lookup('#openSupportMoney')->on('click', function () {
        #    browse('https://www.donationalerts.com/r/fxedition');
      #  });
        #$layout->lookup('#github')->on('click', function () {
       #     browse('https://github.com/deaglemeister/FXEdition');
       # });
       # $layout->lookup('#listbundels')->on('click', function () {
        #    browse('https://github.com/deaglemeister/bundels');
        #});
        #$layout->lookup('#docymentss')->on('click', function () {
         #   browse('https://hub.develnext.org/wiki/');
       # });


        return $layout;
    }
}