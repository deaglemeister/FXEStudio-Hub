<?php

use php\gui\framework\Application;
use php\gui\UXApplication;

$app = Application::get();

$mainForm = $app->getMainForm();

if ($mainForm) {
    UXApplication::runLater(function () use ($mainForm) {
        if (!$mainForm->alwaysOnTop) {
            $mainForm->alwaysOnTop = true;
            $mainForm->alwaysOnTop = false;
        }

        $mainForm->requestFocus();
    });
}