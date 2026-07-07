<?php

use php\gui\framework\Application;
use php\gui\UXApplication;
use php\io\Stream;
use php\lang\System;
use php\lib\fs;

require __DIR__ . '/fxe-debug-io.php';

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

set_exception_handler(function (\Throwable $e) {
    static $depth = 0;

    if ($depth > 0) {
        throw $e;
    }

    $depth++;

    try {
        fxe_debug_error($e);
    } finally {
        $depth--;
    }
});

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }

    fxe_debug_write("[WARN] {$message}\n  → {$file}:{$line}\n");

    return true;
});

if (Stream::exists('res://.debug/live-property-sync.php')) {
    include 'res://.debug/live-property-sync.php';
}