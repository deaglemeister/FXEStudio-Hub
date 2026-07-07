<?php
namespace ide\ui;

/**
 * Handle для toast — совместимость с UXTrayNotification (on click / hide).
 */
class FxeToastHandle
{
    /** @var callable[] */
    protected $clickHandlers = [];

    /** @var callable[] */
    protected $hideHandlers = [];

    /** @var bool */
    protected $hidden = false;

    /**
     * @param string $event
     * @param callable $handler
     * @param string|null $group
     */
    public function on($event, callable $handler, $group = null)
    {
        if ($event === 'click') {
            $this->clickHandlers[] = $handler;
        } elseif ($event === 'hide') {
            $this->hideHandlers[] = $handler;
        }
    }

    public function triggerClick()
    {
        foreach ($this->clickHandlers as $handler) {
            $handler();
        }
    }

    public function triggerHide()
    {
        if ($this->hidden) {
            return;
        }

        $this->hidden = true;

        foreach ($this->hideHandlers as $handler) {
            $handler();
        }
    }

    public function hide()
    {
        FxeToast::dismissHandle($this);
    }
}
