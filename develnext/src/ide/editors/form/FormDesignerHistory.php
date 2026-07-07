<?php
namespace ide\editors\form;

use php\lib\fs;

/**
 * История изменений дизайнера формы (undo / redo).
 */
class FormDesignerHistory
{
    protected $undo = [];

    protected $redo = [];

    protected $maxSize = 40;

    /** @var array|null */
    protected $lastSnapshot;

    protected $initialized = false;

    /**
     * @param \ide\editors\FormEditor $editor
     */
    public function reset($editor)
    {
        $this->undo = [];
        $this->redo = [];
        $this->lastSnapshot = $this->capture($editor);
        $this->initialized = true;
    }

    public function isInitialized()
    {
        return $this->initialized;
    }

    public function canUndo()
    {
        return \sizeof($this->undo) > 0;
    }

    public function canRedo()
    {
        return \sizeof($this->redo) > 0;
    }

    /**
     * @param \ide\editors\FormEditor $editor
     */
    public function onBeforeChange($editor)
    {
        if (!$this->initialized) {
            $this->reset($editor);
            return;
        }

        $snapshot = $this->lastSnapshot;

        if ($snapshot) {
            $snapshot['sel'] = $editor->getDesignerSelectionIds();
        }

        $this->undo[] = $snapshot;

        if (\sizeof($this->undo) > $this->maxSize) {
            \array_shift($this->undo);
        }

        $this->redo = [];
    }

    /**
     * @param \ide\editors\FormEditor $editor
     */
    public function onAfterChange($editor)
    {
        $this->lastSnapshot = $this->capture($editor);
        $this->initialized = true;
    }

    /**
     * @param \ide\editors\FormEditor $editor
     * @return bool
     */
    public function undo($editor)
    {
        if (!$this->canUndo()) {
            return false;
        }

        $this->redo[] = $this->capture($editor);
        $snapshot = \array_pop($this->undo);
        $keepSelection = $editor->getDesignerSelectionIds();

        $editor->restoreDesignerSnapshot($snapshot, $keepSelection);
        $this->lastSnapshot = $snapshot;

        return true;
    }

    /**
     * @param \ide\editors\FormEditor $editor
     * @return bool
     */
    public function redo($editor)
    {
        if (!$this->canRedo()) {
            return false;
        }

        $this->undo[] = $this->capture($editor);
        $snapshot = \array_pop($this->redo);
        $keepSelection = $editor->getDesignerSelectionIds();

        $editor->restoreDesignerSnapshot($snapshot, $keepSelection);
        $this->lastSnapshot = $snapshot;

        return true;
    }

    /**
     * @param \ide\editors\FormEditor $editor
     * @return array
     */
    public function capture($editor)
    {
        $base = fs::pathNoExt($editor->getCodeFile());
        $snapshot = [
            'fxml' => fs::isFile($editor->getFxmlFile()) ? fs::get($editor->getFxmlFile()) : '',
        ];

        $axml = $base . '.axml';

        if (fs::isFile($axml)) {
            $snapshot['axml'] = fs::get($axml);
        }

        if (fs::isFile($editor->getCodeFile())) {
            $snapshot['code'] = fs::get($editor->getCodeFile());
        }

        $behaviour = $base . '.behaviour';

        if (fs::isFile($behaviour)) {
            $snapshot['behaviour'] = fs::get($behaviour);
        }

        $ideConfig = $editor->getIdeConfigForHistory();

        if ($ideConfig && fs::isFile($ideConfig)) {
            $snapshot['ideConf'] = fs::get($ideConfig);
        }

        $snapshot['sel'] = $editor->getDesignerSelectionIds();

        return $snapshot;
    }
}
