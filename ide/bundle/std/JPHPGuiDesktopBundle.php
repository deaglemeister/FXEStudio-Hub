<?php
namespace ide\bundle\std;

use action\Animation;
use action\Collision;
use action\Element;
use action\Geometry;
use action\Media;
use action\Score;
use develnext\bundle\game2d\Game2DBundle;
use game\Jumping;
use ide\bundle\AbstractBundle;
use ide\bundle\AbstractJarBundle;
use ide\project\behaviours\GradleProjectBehaviour;
use php\framework\Logger;
use php\game\event\UXCollisionEvent;
use php\gui\animation\UXAnimationTimer;
use php\gui\animation\UXKeyFrame;
use php\gui\event\UXContextMenuEvent;
use php\gui\event\UXDragEvent;
use php\gui\event\UXEvent;
use php\gui\event\UXKeyEvent;
use php\gui\event\UXMouseEvent;
use php\gui\event\UXWebEvent;
use php\gui\event\UXWindowEvent;
use php\gui\framework\AbstractForm;
use php\gui\framework\AbstractModule;
use php\gui\layout\UXAnchorPane;
use php\gui\layout\UXFlowPane;
use php\gui\layout\UXHBox;
use php\gui\layout\UXPane;
use php\gui\layout\UXPanel;
use php\gui\layout\UXScrollPane;
use php\gui\layout\UXStackPane;
use php\gui\layout\UXVBox;
use php\gui\paint\UXColor;
use php\gui\text\UXFont;
use php\gui\UXApplication;
use php\gui\UXButton;
use php\gui\UXCanvas;
use php\gui\UXCell;
use php\gui\UXChoiceBox;
use php\gui\UXClipboard;
use php\gui\UXColorPicker;
use php\gui\UXComboBox;
use php\gui\UXContextMenu;
use php\gui\UXControl;
use php\gui\UXDialog;
use php\gui\UXDirectoryChooser;
use php\gui\UXFileChooser;
use php\gui\UXFlatButton;
use php\gui\UXForm;
use php\gui\UXGeometry;
use php\gui\UXHyperlink;
use php\gui\UXImage;
use php\gui\UXImageArea;
use php\gui\UXImageView;
use php\gui\UXLabel;
use php\gui\UXLabeled;
use php\gui\UXLabelEx;
use php\gui\UXList;
use php\gui\UXListCell;
use php\gui\UXListView;
use php\gui\UXMedia;
use php\gui\UXMediaPlayer;
use php\gui\UXMenu;
use php\gui\UXMenuItem;
use php\gui\UXNode;
use php\gui\UXParent;
use php\gui\UXPasswordField;
use php\gui\UXPopupWindow;
use php\gui\UXProgressBar;
use php\gui\UXProgressIndicator;
use php\gui\UXSlider;
use php\gui\UXSpinner;
use php\gui\UXTab;
use php\gui\UXTabPane;
use php\gui\UXTitledPane;
use php\gui\UXToggleButton;
use php\gui\UXToggleGroup;
use php\gui\UXTooltip;
use php\gui\UXTrayNotification;
use php\gui\UXTreeView;
use php\gui\UXWebEngine;
use php\gui\UXWebView;
use php\gui\UXWindow;

/**
 * Class JPHPGuiDesktopBundle
 * @deprecated
 * @package ide\bundle\std
 */
class JPHPGuiDesktopBundle extends AbstractJarBundle
{
    function getName()
    {
        return "JPHP UI Desktop";
    }

    public function useNewBundles()
    {
        return [
            UIDesktopBundle::class,
            Game2DBundle::class
        ];
    }
}