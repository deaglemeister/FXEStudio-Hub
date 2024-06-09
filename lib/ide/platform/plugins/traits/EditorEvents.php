<?php
namespace fxe\platform\plugins\traits;

use ide\editors\AbstractEditor;

trait EditorEvents
{
    public function handleRequestFocus(AbstractEditor $editor) {}
}