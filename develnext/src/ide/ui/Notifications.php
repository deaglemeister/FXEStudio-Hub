<?php
namespace ide\ui;

use ide\Ide;
use php\lib\fs;

class Notifications
{
    /**
     * @param string $title
     * @param string $message
     * @param string $type NOTICE|ERROR|WARNING|SUCCESS|INFORMATION
     * @param array $options actionText, actionHandler, linkText, linkHandler, timeout
     * @return FxeToastHandle
     */
    static function show($title, $message, $type = 'NOTICE', array $options = [])
    {
        $options['type'] = FxeToast::mapLegacyType($type);

        if (!isset($options['timeout'])) {
            $options['timeout'] = 8000;
        }

        return FxeToast::show($title, $message, $options);
    }

    static function attachException(FxeToastHandle $notify, \Exception $e)
    {
        $notify->on('click', function () use ($e) {
            \ide\commands\IdeLogShowCommand::openInTab();
        });
    }

    static function error($title, $message, array $options = [])
    {
        if ($message == "Validation") {
            return self::show($title, "Введите корректные данные", "ERROR", $options);
        }

        return self::show($title, $message, 'ERROR', $options);
    }

    static function warning($title, $message, array $options = [])
    {
        return self::show($title, $message, 'WARNING', $options);
    }

    static function success($title, $message, array $options = [])
    {
        return self::show($title, $message, 'SUCCESS', $options);
    }

    static function showAccountWelcome()
    {
        static::show('Приветствие', 'Добро пожаловать в социальную сеть DevelNext для разработчиков', 'INFORMATION');
    }

    static function showAccountUnavailable()
    {
        static::show('Аккаунт недоступен', 'Работа с аккаунтом временно недоступна, приносим свои извинения.', 'WARNING');
    }

    public static function showAccountAuthWelcome(array $data)
    {
        static::show('Добро пожаловать', 'Приветствуем тебя, ' . $data['login'] . ".");
    }

    public static function showException(\Exception $e)
    {
        return static::show('Произошла ошибка', $e->getMessage(), 'ERROR');
    }

    public static function showAccountAuthorizationExpired()
    {
        static::show('Данные входа устарели', 'Вам необходимо снова зайти под своим пользователем, т.к. данных предыдущего входа устарели.', 'WARNING');
    }

    public static function showExecuteUnableStop()
    {
        static::show('Проблемы с запуском', 'Мы не смогли корректно остановить программу, возможно она еще запущена.', 'WARNING');
    }

    public static function showInvalidValidation()
    {
        static::error('Ошибка валидации', 'Введите все необходимые данные корректно, не пропуская обязательные поля!');
    }

    public static function errorDeleteFile($file)
    {
        static::error('Ошибка удаления', "Файл '$file' невозможно удалить в данный момент, возможно он занят другой программой.");
    }

    public static function errorWriteFile($file, \Exception $e = null)
    {
        $file = fs::name($file);

        if ($e) {
            $notify = static::error('Ошибка записи', "Файл '$file' недоступен для записи, нажмите сюда для подробностей");
            static::attachException($notify, $e);
        } else {
            static::error('Ошибка записи', "Файл '$file' недоступен для записи");
        }
    }

    public static function errorCopyFile($file)
    {
        static::error('Ошибка копирования', "Файл '$file' невозможно скопировать в данный момент, возможно недоступен файл или целевая папка.");
    }

    public static function warningFileOccurs($file)
    {
        $project = Ide::project();

        if ($project) {
            $file = $project->getAbsoluteFile($file);
            $file = $file->getRelativePath();
        }

        static::warning('Поврежденный файл', "$file поврежден, возможно некоторые данные утеряны.");
    }

    public static function showProjectIsDeleted()
    {
        Notifications::show('Проект удален', 'Ваш проект был успешно удален из общего доступа, при желании вы можете снова им поделиться.', 'SUCCESS');
    }

    public static function showProjectIsDeletedFail()
    {
        Notifications::error('Ошибка удаления', 'Мы не смогли удалить ваш проект, возможно сервис временно недоступен, попробуйте позже.');
    }
}
