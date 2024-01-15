<?php

use ide\editors\FormEditor;
use ide\formats\FormFormat;
use ide\Ide;
use ide\IdeClassLoader;
use ide\Logger;
use ide\systems\IdeSystem;
use php\gui\UXDialog;
use php\lang\System;
use script\storage\IniStorage;
use php\lib\fs;

use php\gui\event\UXKeyboardManager;
use php\gui\event\UXKeyEvent;
use php\gui\framework\Application;
use php\gui\JSException;
use php\gui\layout\UXAnchorPane;
use php\gui\UXAlert;
use php\gui\UXApplication;
use php\gui\UXButton;
use php\gui\UXDialog;
use php\gui\UXImage;
use php\gui\UXImageView;
use php\gui\UXMenu;
use php\gui\UXMenuItem;
use php\gui\UXSeparator;
use php\gui\UXTextArea;
use php\io\File;
use php\io\IOException;
use php\io\ResourceStream;
use php\io\Stream;
use php\lang\IllegalArgumentException;
use php\lang\Process;
use php\lang\System;
use php\lang\Thread;
use php\lang\ThreadPool;
use php\lib\arr;
use php\lib\fs;
use php\lib\Items;
use php\lib\reflect;
use php\lib\Str;
use php\time\Time;
use php\time\Timer;
use php\util\Configuration;
use php\util\Scanner;
use php\gui\UXNode;


$develnextCodeCache = !System::getProperty('develnext.noCodeCache');
$loader = new IdeClassLoader($develnextCodeCache, IdeSystem::getOwnLibVersion());
$loader->register(true);
IdeSystem::setLoader($loader);

if (!IdeSystem::isDevelopment()) {
    Logger::setLevel(Logger::LEVEL_INFO);
 
}


$THEME = file_get_contents('theme\style.ini');
$THEME = json_decode($THEME, true);

$THEME = $THEME['Style'];
$app = new Ide();
$app->addStyle("theme/$THEME");
$app->launch();
$app->form('NewSplashForm')->hide();

function _($code, ...$args) {
    static $l10n;

    if (!$l10n) {
        $l10n = Ide::get()->getL10n();
    }

    return $l10n->get($code, ...$args);
}

function dump($arg) {
    ob_start();
    //var_dump($arg);
    $str = ob_get_clean();
    UXDialog::showAndWait($str);
}

/**
 * @param $name
 * @return \php\gui\UXImageView
 */
function ico($name) {
    return Ide::get()->getImage("icons/$name.png");
}