<?php
namespace ide\formats\form\elements;

use ide\formats\form\AbstractFormElement;
use ide\Ide;
use ide\library\IdeLibraryScriptGeneratorResource;
use php\gui\designer\UXDesignProperties;
use php\gui\event\UXWebEvent;
use php\gui\UXNode;
use php\gui\UXTextField;
use php\gui\UXWebView;
use platform\facades\Toaster;
use platform\toaster\ToasterMessage;
use php\gui\UXImage;


class WebViewFormElement extends AbstractFormElement
{
    /**
     * @return string
     */
    public function getName()
    {
        return _('webbrowser');
    }

    public function getElementClass()
    {
        return null;
    }

    public function getGroup()
    {
        return 'Дополнительно';
    }

    public function getIcon()
    {
        return 'icons/webBrowser16.png';
    }

    public function getIdPattern()
    {
        return "browser%s";
    }

    /**
     * @return UXNode
     */
    public function createElement()
    {


        $tm = new ToasterMessage();
        $iconImage = new UXImage('res://resources/expui/icons/fileTypes/Error.png');
        $tm
            ->setIcon($iconImage)
            ->setTitle('Менеджер по работе с компонентом')
            ->setDescription(_('Браузер был перенсён в пакет расширений. Данный браузер использует движок: Chromium'))
            ->setClosable();
        Toaster::show($tm);

        #$element = new UXWebView();

        return null;
    }

    public function getDefaultSize()
    {
        return [300, 300];
    }

    public function isOrigin($any)
    {
        return null;
    }

    public function getScriptGenerators()
    {
        return [
            new IdeLibraryScriptGeneratorResource('res://.dn/bundle/uiDesktop/scriptgen/LoadHtmlWebViewScriptGen'),
            new IdeLibraryScriptGeneratorResource('res://.dn/bundle/uiDesktop/scriptgen/HistoryListWebViewScriptGen'),
        ];
    }


}