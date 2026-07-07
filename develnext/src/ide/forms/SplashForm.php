<?php
namespace ide\forms;

use ide\Ide;
use ide\Logger;
use php\gui\UXImageView;
use php\gui\UXLabel;
use php\gui\UXProgressIndicator;
use php\lib\str;
use timer\AccurateTimer;

/**
 * @property UXImageView $image
 * @property UXLabel $loadingText
 * @property UXLabel $loadingDetail
 * @property UXProgressIndicator $loadingIndicator
 */
class SplashForm extends AbstractIdeForm
{
    /** @var AccurateTimer */
    protected $loadRefreshTimer;

    /** @var string */
    protected $statusText = 'LOAD ...';

    /** @var string */
    protected $detailText = '';

    protected function init()
    {
        Logger::debug("Init splash form ...");

        $this->centerOnScreen();

        if ($this->loadingIndicator) {
            $this->loadingIndicator->progress = -1;
        }

        $this->refreshLoadingText();
    }

    public function startLoadRefresh()
    {
        $this->stopLoadRefresh();
        $this->statusText = 'LOAD ...';
        $this->detailText = '';

        $this->loadRefreshTimer = new AccurateTimer(50, function () {
            if (!Ide::isCreated()) {
                return;
            }

            $messages = Ide::get()->drainSplashLoadHistory();

            if (!$messages) {
                return;
            }

            foreach ($messages as $message) {
                $this->applyLoadingMessage($message);
            }
        });
        $this->loadRefreshTimer->start();
    }

    public function stopLoadRefresh()
    {
        if ($this->loadRefreshTimer) {
            $this->loadRefreshTimer->stop();
            $this->loadRefreshTimer = null;
        }
    }

    /**
     * @param string $text
     */
    protected function applyLoadingMessage($text)
    {
        if (!$text) {
            return;
        }

        if ($this->isDetailMessage($text)) {
            $this->detailText = $text;
        } else {
            $this->statusText = $text;
        }

        $this->refreshLoadingText();
    }

    /**
     * @param string $text
     * @return bool
     */
    protected function isDetailMessage($text)
    {
        return str::startsWith($text, 'LOAD ');
    }

    protected function refreshLoadingText()
    {
        if ($this->loadingText) {
            $this->loadingText->text = $this->statusText;
        }

        if ($this->loadingDetail) {
            $this->loadingDetail->text = $this->detailText;
            $this->loadingDetail->visible = (bool) $this->detailText;
            $this->loadingDetail->managed = (bool) $this->detailText;
        }
    }

    public function setLoadingText($text)
    {
        $this->applyLoadingMessage($text);
    }

    /**
     * @param string|null $text
     */
    public function showPreloader($text = null)
    {
        if ($text) {
            $this->setLoadingText($text);
        }
    }

    public function hidePreloader()
    {
    }

    /**
     * @event show
     */
    public function doShow()
    {
        $this->startLoadRefresh();

        if (Ide::isCreated()) {
            Ide::get()->flushSplashLoadBuffer();
        }

        uiLater(function () {
            $this->toFront();
        });
    }

    public function hide()
    {
        $this->stopLoadRefresh();
        parent::hide();
    }
}
