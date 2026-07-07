<?php

use php\io\Stream;

if (!function_exists('fxe_stdout_flush')) {
    function fxe_stdout_flush()
    {
        try {
            Stream::of('php://stdout')->flush();
        } catch (\Throwable $e) {
        }
    }
}

if (!function_exists('fxe_debug_write')) {
    function fxe_debug_write($text)
    {
        echo $text;
        fxe_stdout_flush();
    }
}

if (!function_exists('fxe_debug_error')) {
    function fxe_debug_error(\Throwable $e, $context = '')
    {
        echo "[ERROR] " . $e->getMessage() . "\n";

        $file = $e->getFile();
        $line = $e->getLine();

        if ($file) {
            echo "  → {$file}:{$line}\n";
        }

        if ($context) {
            echo "  код обработчика:\n";

            foreach (explode("\n", trim($context)) as $ctxLine) {
                echo "    {$ctxLine}\n";
            }
        }

        fxe_stdout_flush();
    }
}
