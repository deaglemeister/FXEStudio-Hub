<?php

namespace plugins\checkJava;

use php\lib\str;
use php\lib\fs;
use php\io\Stream;
use php\io\IOException;
use platform\facades\Toaster;
use platform\toaster\ToasterMessage;
use php\lang\Process;
use php\lang\System;
use php\gui\UXImage;
use php\gui\UXImageView;

class JavaEnvironmentChecker
{
    public static function checkJavaEnvironment()
    {
        $javaHome = getenv('JAVA_HOME');
        $path = getenv('PATH');

        // Проверка наличия JAVA_HOME
        if ($javaHome && !self::checkJavaVersionInJavaHome($javaHome)) {
            self::showErrorToast("Переменная JAVA_HOME и PATH указывает на некорректную версию Java. Убедитесь, что эти переменные удалены.");
            return false;
        }

        if ($path && !self::checkJavaVersionInPath($path)) {
            self::showSuccessToast("Переменные окружения JAVA_HOME и PATH не установлены. Всё хорошо.");
            return false;
        }
    }

    private static function checkJavaVersionInJavaHome($javaHome)
    {
        $javaExecutable = fs::normalize($javaHome . '/bin/java');
        if (!fs::exists($javaExecutable)) {
           #self::showErrorToast("Не найден исполняемый файл Java в JAVA_HOME.");
            return false;
        }

        $output = self::getJavaVersionOutput($javaExecutable);
        return self::isJava8($output);
    }

    private static function checkJavaVersionInPath($path)
    {
        $paths = explode(PATH_SEPARATOR, $path);
        foreach ($paths as $p) {
            $javaExecutable = fs::normalize($p . '/java');
            if (fs::exists($javaExecutable)) {
                $output = self::getJavaVersionOutput($javaExecutable);
                if (self::isJava8($output)) {
                    return true;
                }
            }
        }
        return false;
    }

    private static function getJavaVersionOutput($javaExecutable)
    {
        try {
            $process = new Process([$javaExecutable, '-version']);
            $process->redirectErrorStream(true);  // Объединяем stdout и stderr
            $process->start();

            $output = Stream::of($process->getInput())->readFully();
            $process->waitFor();

            return $output;
        } catch (IOException $e) {
            self::showErrorToast("Ошибка при выполнении команды $javaExecutable -version: " . $e->getMessage());
            return "";
        }
    }

    private static function isJava8($output)
    {
        return str::contains($output, 'version "1.8.0') || str::contains($output, 'version "8');
    }

    public static function showErrorToast($message)
    {
        $tm = new ToasterMessage();
        $iconImage = new UXImage('res://resources/expui/icons/fileTypes/warning.png');
        $tm
            ->setIcon($iconImage)
            ->setTitle('Предупреждение: студия может работать некорректно.')
            ->setDescription($message)
            ->setClosable(25000, true);

        Toaster::show($tm);
    }

    public static function showSuccessToast($message)
    {
        $tm = new ToasterMessage();
        $iconImage = new UXImage('res://resources/expui/icons/fileTypes/info.png');
        $tm
            ->setIcon($iconImage)
            ->setTitle('Информация:')
            ->setDescription($message)
            ->setClosable(25000, true);

        Toaster::show($tm);
    }
}
