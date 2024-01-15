<?php
namespace ide\project\behaviours;

use ide\account\ui\NeedAuthPane;
use ide\forms\area\ShareProjectArea;
use ide\forms\MessageBoxForm;
use ide\Ide;
use ide\project\AbstractProjectBehaviour;
use ide\project\control\CommonProjectControlPane;
use ide\ui\Notifications;
use ide\utils\TimeUtils;
use php\gui\layout\UXHBox;
use php\gui\layout\UXVBox;
use php\gui\UXHyperlink;
use php\gui\UXLabel;
use php\gui\UXNode;
use php\gui\UXParent;
use php\net\URL;
use php\time\Time;

class ShareProjectBehaviour extends AbstractProjectBehaviour
{
    /**
     * @var UXVBox
     */
    protected $uiSettings;

    /**
     * @var NeedAuthPane
     */
    protected $uiAuthPane;

    /**
     * @var ShareProjectArea
     */
    protected $uiSyncPane;

    /**
     * @var ProjectArchiveService
     */
    protected $projectService;

    /**
     * @var array
     */
    protected $data;

    /**
     * ...
     */
    public function inject()
    {
        $this->project->on('makeSettings', [$this, 'doMakeSettings']);
        $this->project->on('updateSettings', [$this, 'doUpdateSettings']);
        $this->project->on('close', [$this, 'doClose']);

        $this->projectService = Ide::service()->projectArchive();
    }

    /**
     * see PRIORITY_* constants
     * @return int
     */
    public function getPriority()
    {
        return self::PRIORITY_COMPONENT;
    }

    public function doClose()
    {
    }

    public function doUpdateSettings(CommonProjectControlPane $editor = null)
    {
        if ($this->uiSettings) {
            $this->uiAuthPane->free();
            $this->uiSyncPane->free();

            if ( Ide::accountManager()->isAuthorized() ) {
                $this->uiSettings->add($this->uiSyncPane);

                $uid = Ide::project()->getIdeServiceConfig()->get('projectArchive.uid');

                if ($uid) {
                    $this->projectService->getAsync($uid, function (ServiceResponse $response) {
                        if ($response->isSuccess()) {
                            $this->data = $response->result();
                            $this->uiSyncPane->setData($data = $response->result());
                        } else {
                            $this->uiSyncPane->setData(null);
                            $this->data = $response->result();
                        }
                    });
                } else {
                    $this->uiSyncPane->setData(null);
                }
            } else {
                $this->uiSettings->add($this->uiAuthPane);
            }
        }
    }

    public function doMakeSettings(CommonProjectControlPane $editor)
    {
		

	/* 	//dd
        $title = new UXLabel('Dev: Alexander Fitchers and Andrey Guschin');
        $title->font = $title->font->withBold();

        $colon = new UXLabel(': ');
        $colon->font = $colon->font->withBold();

       $url = new URL(Ide::service()->getEndpoint());

        $link = new UXHyperlink('FXEdition: 1.0 | NextApp');
        $link->on('action', function () use ($url) {
            browse('https://hub.develnext.org');
			
        }); 

        $titleFlow = new UXHBox([$title, $link, $colon]);

        $this->uiAuthPane = $authPane = new NeedAuthPane();
        $authPane->setTitle('DevelNext FXEdition 2024');
		


        $this->uiSyncPane = $syncPane = new ShareProjectArea([$this, 'doUpdateSettings']);

     // Ide::accountManager()->bind('login', [$this, 'doUpdateSettings']);
   //  Ide::accountManager()->bind('logout', [$this, 'doUpdateSettings']);

        $ui = new UXVBox([$titleFlow]);
        $ui->spacing = 5;

        $pane = $editor->addSettingsPane($ui);
        $pane->backgroundColor = 'white'; 

        $this->uiSettings = $ui;*/
    }
}