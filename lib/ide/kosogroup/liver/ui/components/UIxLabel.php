<?php 

namespace kosogroup\liver\ui\components;

use kosogroup\liver\ui\components\traits\Cascadable;
use php\gui\UXLabel;

/**
* @method UIxLabel _setLabelFor(php\gui\UXNode $arg0)
* @method UIxLabel _setAlignment( $arg0)
* @method UIxLabel _setText(mixed $arg0)
* @method UIxLabel _setContentDisplay( $arg0)
* @method UIxLabel _setGraphicTextGap(mixed $arg0)
* @method UIxLabel _setTextAlignment( $arg0)
* @method UIxLabel _setMnemonicParsing(mixed $arg0)
* @method UIxLabel _setWrapText(mixed $arg0)
* @method UIxLabel _setUnderline(mixed $arg0)
* @method UIxLabel _setEllipsisString(mixed $arg0)
* @method UIxLabel _setFont(php\gui\text\UXFont $arg0)
* @method UIxLabel _setGraphic(php\gui\UXNode $arg0)
* @method UIxLabel _setTextColor(mixed $arg0)
* @method UIxLabel setResizable($mixed)
* @method UIxLabel _setTooltip(php\gui\UXTooltip $arg0)
* @method UIxLabel _setContextMenu(php\gui\UXContextMenu $arg0)
* @method UIxLabel _setTooltipText(mixed $arg0)
* @method UIxLabel _setMinHeight(mixed $arg0)
* @method UIxLabel _setMinWidth(mixed $arg0)
* @method UIxLabel _setMaxWidth(mixed $arg0)
* @method UIxLabel _setMaxHeight(mixed $arg0)
* @method UIxLabel _setPadding(mixed $arg0)
* @method UIxLabel _setMinSize(array $arg0)
* @method UIxLabel _setMaxSize(array $arg0)
* @method UIxLabel _setBackgroundColor(mixed $arg0)
* @method UIxLabel _setPaddingRight(mixed $arg0)
* @method UIxLabel setPrefSize($mixed)
* @method UIxLabel _setPaddingTop(mixed $arg0)
* @method UIxLabel _setPaddingLeft(mixed $arg0)
* @method UIxLabel _setPaddingBottom(mixed $arg0)
* @method UIxLabel setStylesheets($mixed)
* @method UIxLabel setChildrenUnmodifiable($mixed)
* @method UIxLabel _setCache(mixed $arg0)
* @method UIxLabel _setRotate(mixed $arg0)
* @method UIxLabel _setId(mixed $arg0)
* @method UIxLabel _setOpacity(mixed $arg0)
* @method UIxLabel _setStyle(mixed $arg0)
* @method UIxLabel _setManaged(mixed $arg0)
* @method UIxLabel setFocused($mixed)
* @method UIxLabel _setVisible(mixed $arg0)
* @method UIxLabel setDisabled($mixed)
* @method UIxLabel setHover($mixed)
* @method UIxLabel setPressed($mixed)
* @method UIxLabel setClasses($mixed)
* @method UIxLabel _setPickOnBounds(mixed $arg0)
* @method UIxLabel _setBlendMode( $arg0)
* @method UIxLabel _setFocusTraversable(mixed $arg0)
* @method UIxLabel _setScaleX(mixed $arg0)
* @method UIxLabel _setScaleY(mixed $arg0)
* @method UIxLabel _setTranslateX(mixed $arg0)
* @method UIxLabel _setTranslateY(mixed $arg0)
* @method UIxLabel _setEffect(php\gui\effect\UXEffect $arg0)
* @method UIxLabel setBaselineOffset($mixed)
* @method UIxLabel _setClip(php\gui\UXNode $arg0)
* @method UIxLabel _setDepthTest( $arg0)
* @method UIxLabel _setScaleZ(mixed $arg0)
* @method UIxLabel _setTranslateZ(mixed $arg0)
* @method UIxLabel _setCacheHint( $arg0)
* @method UIxLabel _setMouseTransparent(mixed $arg0)
* @method UIxLabel setContentBias($mixed)
* @method UIxLabel setParent($mixed)
* @method UIxLabel _setSize(array $arg0)
* @method UIxLabel setLayoutBounds($mixed)
* @method UIxLabel setScene($mixed)
* @method UIxLabel _setWidth(mixed $arg0)
* @method UIxLabel _setX(mixed $arg0)
* @method UIxLabel _setY(mixed $arg0)
* @method UIxLabel setBoundsInParent($mixed)
* @method UIxLabel _setCursor(mixed $arg0)
* @method UIxLabel _setUserData(mixed $arg0)
* @method UIxLabel _setScale(mixed $arg0)
* @method UIxLabel _setPosition(array $arg0)
* @method UIxLabel _setHeight(mixed $arg0)
* @method UIxLabel setWindow($mixed)
* @method UIxLabel _setEnabled(mixed $arg0)
* @method UIxLabel setForm($mixed)
* @method UIxLabel _setScreenPosition(array $arg0)
* @method UIxLabel _setScreenX(mixed $arg0)
* @method UIxLabel _setScreenY(mixed $arg0)
* @method UIxLabel _setLeftAnchor(mixed $arg0)
* @method UIxLabel _setRightAnchor(mixed $arg0)
* @method UIxLabel _setAnchorFlags(array $arg0)
* @method UIxLabel _setTopAnchor(mixed $arg0)
* @method UIxLabel _setClassesString(mixed $arg0)
* @method UIxLabel setEffects($mixed)
* @method UIxLabel _setAnchors(array $arg0)
* @method UIxLabel _setBottomAnchor(mixed $arg0)
 */
class UIxLabel extends UXLabel
{
    use Cascadable;
        
    
    public function setText($text) : UIxLabel
    {
        $this->text = $text;
        return $this;
    }

    public function setFontSize(int $fontSize) : UIxLabel
    {   
        $this->font->size = $fontSize;
        return $this;
    }
    
    
}