<?php
namespace ide\forms;

use ide\Ide;
use ide\Logger;
use php\gui\framework\AbstractForm;
use php\gui\UXForm;


/**
 * Class AbstractIdeForm
 * @package ide\forms
 */
class AbstractIdeForm extends AbstractForm
{
    public function __construct(?UXForm $origin = null)
    {
        parent::__construct($origin);
    
        $ideInstance = Ide::get();


        if ($ideInstance->isCreated()) {
            $this->owner = $ideInstance->getMainForm();
        }
    
        $formName = get_called_class();
        Logger::info("Create form '{$formName}'");
    
        $showFormCallback = function () use ($formName, $ideInstance) {
            Logger::info("Show form '{$formName}' ..");
            $ideInstance->trigger('showForm', [$this]);
        };
    
        $hideFormCallback = function () use ($formName, $ideInstance) {
            Logger::info("Hide form '{$formName}' ..");
            $ideInstance->trigger('hideForm', [$this]);
        };
    
        $this->on('show', $showFormCallback, static::class);
        $this->on('hide', $hideFormCallback, static::class);
    }
    
    protected function init()
    {
        parent::init();
    
        $l10n = Ide::get()->getL10n();

        $this->title = $l10n->translate($this->title);
    
        $l10n->translateNode($this->layout);
    }
}