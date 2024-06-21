<?php

namespace ide\forms;

use Exception;
use php\lib\fs;
use php\gui\event\UXEvent;
use php\gui\UXApplication;
use php\gui\event\UXMouseEvent;
use php\gui\text\UXFont;
use php\io\ResourceStream;


class SettingsForm extends AbstractIdeForm
{

    /**
     * @event ThemeIde.construct 
     */
    function doThemeIdeConstruct(UXEvent $e = null)
    {
        $folder = "application";
        $files = scandir($folder);
        $this->ThemeIde->items->clear();
        foreach ($files as $file) {
            if (strtolower(fs::ext($file)) === 'css') {
                $nameWithoutExtension = fs::nameNoExt($file);
                $this->ThemeIde->items->add($nameWithoutExtension);
            }
        }
    }

/**
 * @event SaveButton.click-Left 
 */
/**
 * @event SaveButton.click-Left 
 */
function doSaveButtonClickLeft(UXMouseEvent $e = null)
{
    $selectedTheme = $this->ThemeIde->value;
    $selectedFont = $this->customFont->value;

    // Проверяем наличие расширений .css и .ttf
    if (strpos($selectedTheme, '.css') !== false) {
        // Если содержит .css, оставляем как есть
        $selectedThemeWithoutExtension = $selectedTheme;
    } else {
        // Иначе добавляем .css
        $selectedThemeWithoutExtension = $selectedTheme . '.css';
    }

    if (strpos($selectedFont, '.ttf') !== false) {
        // Если содержит .ttf, оставляем как есть
        $selectedFontWithoutExtension = $selectedFont;
    } else {
        // Иначе добавляем .ttf
        $selectedFontWithoutExtension = $selectedFont . '.ttf';
    }

    $themeDirectory = 'application';
    $themeFile = 'app.conf';
    $themePath = $themeDirectory . DIRECTORY_SEPARATOR . $themeFile;

    // Проверка существования директории
    if (!is_dir($themeDirectory)) {
        mkdir($themeDirectory, 0777, true);
    }

    // Формирование данных для записи
    $appSplah = 'Mysplash';
    $appSplahValue = 'Выключено';
    $appSize = '13';
    $settings = [
        'app.style' => $selectedThemeWithoutExtension,
        'app.splash' => $appSplah,
        'app.splashValue' => $appSplahValue,
        'app.font' => $selectedFontWithoutExtension,
        'app.size' => $appSize,
    ];
    $data = json_encode($settings, JSON_PRETTY_PRINT);

    // Запись данных в файл
    try {
        file_put_contents($themePath, $data);
    } catch (Exception $e) {
        echo "Ошибка при записи файла: ", $e->getMessage();
        exit();
    }

    // Применение шрифта
    $fontStream = new ResourceStream("application\\fonts\\$selectedFontWithoutExtension");
    UXFont::load($fontStream, 14);  // Пример загрузки шрифта размером 14

    // Выполнение приложения DevelNext
    $executablePath = 'DevelNext.exe';
    if (file_exists($executablePath)) {
        Execute($executablePath);
    } else {
        echo "Файл $executablePath не найден.";
        exit();
    }
    exit();
}



/**
 * @event show 
 */
function doShow()
{    
    // Загрузка темы из конфигурационного файла
    $THEME = file_get_contents('application\app.conf');
    $THEME = json_decode($THEME, true);
    $THEME = $THEME['app.style'];
    $this->ThemeIde->value = $THEME;



    $appFont = file_get_contents('application\app.conf');
    $appFont = json_decode($appFont, true);
    $appFont = $appFont['app.font'];
    $this->customFont->value = $appFont;
}

    /**
     * @event customFont.construct 
     */
    function doCustomFontConstruct(UXEvent $e = null)
    {    
        $folder = "application/fonts/";
        $files = scandir($folder);
        $this->customFont->items->clear();
        foreach ($files as $file) {
            if (strtolower(fs::ext($file)) === 'ttf') {
                $nameWithoutExtension2 = fs::nameNoExt($file);
                $this->customFont->items->add($nameWithoutExtension2);
            }
        }
    }











    protected function init()
    {
        parent::init();
    }

    /**
     * @param $data
     * @param bool $always
     * @return bool
     */
    public function tryShow($always = false)
    {
        UXApplication::runLater(function () {
            $this->showAndWait();
        });

        return true;
    }
}
