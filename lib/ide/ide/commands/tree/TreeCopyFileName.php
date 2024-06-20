<?
namespace ide\commands\tree;

use ide\editors\AbstractEditor;
use ide\editors\menu\AbstractMenuCommand;
use ide\forms\MessageBoxForm;
use ide\Ide;
use ide\project\ProjectTree;
use php\gui\UXClipboard;
use php\lib\fs;

class TreeCopyFileName extends AbstractMenuCommand
{
    protected $tree;

    public function __construct(ProjectTree $tree)
    {
        $this->tree = $tree;
    }

    public function getIcon()
    {
        return '';
    }

    public function getName()
    {
        return _('Cкопировать имя');
    }

    public function onExecute($e = null, AbstractEditor $editor = null)
    {
        $file = $this->tree->getSelectedFullPath();

        if ($file) {
            $fileName = fs::name($file); 
            UXClipboard::setText($fileName); 
            Ide::toast("Имя файла '$fileName' скопировано в буфер обмена."); 
        }
    }

    public function onBeforeShow($item, AbstractEditor $editor = null)
    {
        parent::onBeforeShow($item, $editor);

        $file = $this->tree->getSelectedFullPath();
        $item->disable = !$file; 
    }
}
