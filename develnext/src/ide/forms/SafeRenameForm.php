<?php
namespace ide\forms;

use ide\forms\mixins\DialogFormMixin;
use ide\utils\UiUtils;
use php\gui\event\UXEvent;
use php\gui\UXButton;
use php\gui\UXLabel;
use php\gui\UXTextArea;
use php\lib\str;

/**
 * Диалог подтверждения Safe Rename с перечнем затрагиваемых мест.
 *
 * @property UXLabel $titleLabel
 * @property UXLabel $hintLabel
 * @property UXTextArea $previewArea
 * @property UXButton $applyButton
 * @property UXButton $cancelButton
 */
class SafeRenameForm extends AbstractIdeForm
{
    use DialogFormMixin;

    /** @var string */
    protected $oldId;

    /** @var string */
    protected $newId;

    /** @var array */
    protected $items;

    /** @var bool */
    protected $confirmed = false;

    /**
     * @param string $oldId
     * @param string $newId
     * @param array $items
     */
    public function __construct($oldId, $newId, array $items)
    {
        $this->oldId = $oldId;
        $this->newId = $newId;
        $this->items = $items;

        parent::__construct();
    }

    public function isConfirmed()
    {
        return $this->confirmed;
    }

    protected function init()
    {
        parent::init();

        $this->titleLabel->text = "Переименовать компонент «{$this->oldId}» → «{$this->newId}»?";
        $this->previewArea->text = $this->formatItems($this->items);
        $this->previewArea->style = '-fx-font-family: Consolas, monospace; -fx-font-size: 11px;';

        $this->applyButton->style = '-fx-font-weight: bold;' . UiUtils::fontSizeStyle();
        $this->cancelButton->style = UiUtils::fontSizeStyle();
    }

    /**
     * @event applyButton.action
     */
    public function actionApply(UXEvent $e = null)
    {
        $this->confirmed = true;
        $this->setResult(true);
        $this->hide();
    }

    /**
     * @event cancelButton.action
     */
    public function actionCancel(UXEvent $e = null)
    {
        $this->confirmed = false;
        $this->setResult(null);
        $this->hide();
    }

    /**
     * @param array $items
     * @return string
     */
    protected function formatItems(array $items)
    {
        if (!$items) {
            return "• Форма: id компонента будет изменён.";
        }

        $lines = [];
        $lastGroup = null;

        foreach ($items as $item) {
            $group = isset($item['group']) ? $item['group'] : 'Прочее';
            $file = isset($item['file']) ? $item['file'] : '';
            $detail = isset($item['detail']) ? $item['detail'] : '';

            if ($group != $lastGroup) {
                if ($lastGroup !== null) {
                    $lines[] = '';
                }

                $lines[] = "[$group]";
                $lastGroup = $group;
            }

            $lines[] = $file ? "  $file — $detail" : "  $detail";
        }

        return str::join($lines, "\n");
    }

    /**
     * @param string $oldId
     * @param string $newId
     * @param array $items
     * @return bool
     */
    public static function confirm($oldId, $newId, array $items)
    {
        $form = new static($oldId, $newId, $items);

        return $form->showDialog() && $form->isConfirmed();
    }
}
