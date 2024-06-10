<?php
namespace ide\protocol\handlers;

use ide\account\api\ServiceResponse;
use ide\forms\SharedProjectDetailForm;
use ide\Ide;
use ide\Logger;
use ide\protocol\AbstractProtocolHandler;
use ide\ui\Notifications;
use php\lib\str;
use platform\facades\Toaster;
use platform\toaster\ToasterMessage;
use php\gui\UXImage;

/**
 * Class OpenProjectProtocolHandler
 * @package ide\protocol\handlers
 */
class OpenProjectProtocolHandler extends AbstractProtocolHandler
{
    /**
     * @param string $query
     * @return bool
     */
    public function isValid($query)
    {
        return str::startsWith($query, 'project:');
    }

    /**
     * @param $query
     * @return bool
     */
    public function handle($query)
    {
        $uid = str::sub($query, str::length('project:'));

        if (str::endsWith($uid, '/')) {
            $uid = str::sub($uid, 0, str::length($uid) - 1);
        }

        if ($uid) {
            Ide::get()->disableOpenLastProject();

            Ide::get()->bind('start', function () use ($uid) {
                uiLater(function () use ($uid) {
                    Ide::service()->projectArchive()->getAsync($uid, function (ServiceResponse $response) use ($uid) {
                        if ($response->isSuccess()) {
                            uiLater(function () use ($response) {
                                $tm = new ToasterMessage();
                                $iconImage = new UXImage('res://resources/expui/icons/fileTypes/succes.png');
                                $tm
                                    ->setIcon($iconImage)
                                    ->setTitle('Менеджер по работе с проектами')
                                    ->setDescription(_('Мы обнаружили ссылку на общедоступный проект, вы можете его открыть.'))
                                    ->setClosable();
                                Toaster::show($tm);
                                $dialog = new SharedProjectDetailForm($response->result('uid'));
                                $dialog->showAndWait();
                            });
                        } else {
                            $tm = new ToasterMessage();
                            $iconImage = new UXImage('res://resources/expui/icons/fileTypes/warning.png');
                            $tm
                                ->setIcon($iconImage)
                                ->setTitle('Менеджер по работе с проектами')
                                ->setDescription(_('Ссылка на проект некорректная или он был уже удален.'))
                                ->setClosable();
                            Toaster::show($tm);
                        }
                    });
                });
            });
        }
    }
}