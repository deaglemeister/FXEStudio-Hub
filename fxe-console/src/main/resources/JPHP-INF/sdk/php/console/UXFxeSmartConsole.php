<?php
namespace php\console;

/**
 * FXE Smart Console — умный логгер запуска проекта.
 * @package php\console
 */
class UXFxeSmartConsole
{
    /** @var callable */
    public $onEvent;

    public function info($message) {}
    public function ok($message) {}
    public function success($message) {}
    public function warn($message) {}
    public function error($message) {}
    public function debug($message) {}
    public function javaLog($message) {}
    public function jphp($message) {}
    public function bundle($message) {}
    public function run($message) {}
    public function build($message) {}
    public function fix($message) {}
    public function processBuildLine($line) {}
    public function processAppLine($line, $stderr = false) {}
    public function markStart() {}
    public function finish($exitCode, $hasError = false) {}
    public function clear() {}
    public function exportText() {}
    public function setShowDebug($value) {}
    public function isShowDebug() {}
    public function setFilter($mode) {}
}
