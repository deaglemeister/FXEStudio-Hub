<?php

use platform\facades\PluginManager;
use ide\Ide;
use ide\IdeClassLoader;
use ide\Logger;
use ide\systems\IdeSystem;
use php\gui\text\UXFont;
use php\gui\UXDialog;
use php\io\ResourceStream;
use php\lang\System;


$fontStream = new ResourceStream("application/fonts/JetBrainsMono-Regular.ttf");
UXFont::load($fontStream, 14);

PluginManager::resolvePlugins("./plugins");


$develnextCodeCache = !System::getProperty('develnext.noCodeCache');
$loader = new IdeClassLoader($develnextCodeCache, IdeSystem::getOwnLibVersion());
$loader->register(true);
IdeSystem::setLoader($loader);

if (!IdeSystem::isDevelopment()) {
    Logger::setLevel(Logger::LEVEL_INFO);
}


$THEME = file_get_contents('application\app.conf');
$THEME = json_decode($THEME, true);

$THEME = $THEME['app.style'];
$app = new Ide();
$app->addStyle("application/$THEME");
$app->launch();
$app->form('NewSplashForm')->hide();

function _($code, ...$args)
{
    static $l10n;

    if (!$l10n) {
        $l10n = Ide::get()->getL10n();
    }

    return $l10n->get($code, ...$args);
}

function dump($arg)
{
    ob_start();
    $str = ob_get_clean();
    UXDialog::showAndWait($str);
}

/**
 * @param $name
 * @return \php\gui\UXImageView
 */
function ico($name)
{
    return Ide::get()->getImage("icons/$name.png");
}
