<?php
namespace ide\formats;

use ide\autocomplete\ui\FxCssCompletePane;
use ide\editors\AbstractEditor;
use ide\editors\CodeEditor;
use ide\systems\FxeCssAnalysisSystem;
use ide\utils\FileUtils;
use php\lib\fs;
use php\lib\Str;

class FxCssCodeFormat extends AbstractFormat
{
    /**
     * @param $file
     *
     * @param array $options
     * @return AbstractEditor
     */
    public function createEditor($file, array $options = [])
    {
        $editor = new CodeEditor($file, 'fxcss');
        $editor->registerDefaultCommands();

        FxeCssAnalysisSystem::attachEditor($editor);
        new FxCssCompletePane($editor->getTextArea());

        return $editor;
    }


    /**
     * @param $file
     *
     * @return bool
     */
    public function isValid($file)
    {
        return str::endsWith($file, '.fx.css');
    }

    public function getIcon()
    {
        return 'icons/cssFile16.png';
    }

    /**
     * @param $any
     *
     * @return mixed
     */
    public function register($any)
    {

    }
}