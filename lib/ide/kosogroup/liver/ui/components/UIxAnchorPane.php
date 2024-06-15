<?php

namespace kosogroup\liver\ui\components;

use kosogroup\liver\ui\components\traits\Cascadable;
use php\gui\layout\UXAnchorPane;

/**
* @method UIxAnchorPane setChildren($mixed)
* @method UIxAnchorPane _setMinHeight(mixed $arg0)
* @method UIxAnchorPane _setMaxWidth(mixed $arg0)
* @method UIxAnchorPane _setMinWidth(mixed $arg0)
* @method UIxAnchorPane _setMaxHeight(mixed $arg0)
* @method UIxAnchorPane _setPadding(mixed $arg0)
* @method UIxAnchorPane _setMinSize(array $arg0)
* @method UIxAnchorPane _setMaxSize(array $arg0)
* @method UIxAnchorPane _setBackgroundColor(mixed $arg0)
* @method UIxAnchorPane _setPaddingTop(mixed $arg0)
* @method UIxAnchorPane _setPaddingRight(mixed $arg0)
* @method UIxAnchorPane _setPaddingBottom(mixed $arg0)
* @method UIxAnchorPane _setPaddingLeft(mixed $arg0)
* @method UIxAnchorPane setPrefSize($mixed)
* @method UIxAnchorPane setStylesheets($mixed)
* @method UIxAnchorPane setChildrenUnmodifiable($mixed)
* @method UIxAnchorPane _setCache(mixed $arg0)
* @method UIxAnchorPane _setRotate(mixed $arg0)
* @method UIxAnchorPane _setId(mixed $arg0)
* @method UIxAnchorPane _setScaleX(mixed $arg0)
* @method UIxAnchorPane _setScaleY(mixed $arg0)
* @method UIxAnchorPane _setTranslateX(mixed $arg0)
* @method UIxAnchorPane _setTranslateY(mixed $arg0)
* @method UIxAnchorPane _setOpacity(mixed $arg0)
* @method UIxAnchorPane _setManaged(mixed $arg0)
* @method UIxAnchorPane _setStyle(mixed $arg0)
* @method UIxAnchorPane _setVisible(mixed $arg0)
* @method UIxAnchorPane setDisabled($mixed)
* @method UIxAnchorPane setFocused($mixed)
* @method UIxAnchorPane setPressed($mixed)
* @method UIxAnchorPane setHover($mixed)
* @method UIxAnchorPane _setBlendMode( $arg0)
* @method UIxAnchorPane _setPickOnBounds(mixed $arg0)
* @method UIxAnchorPane setClasses($mixed)
* @method UIxAnchorPane _setFocusTraversable(mixed $arg0)
* @method UIxAnchorPane setResizable($mixed)
* @method UIxAnchorPane _setEffect(php\gui\effect\UXEffect $arg0)
* @method UIxAnchorPane setBaselineOffset($mixed)
* @method UIxAnchorPane _setClip(php\gui\UXNode $arg0)
* @method UIxAnchorPane _setDepthTest( $arg0)
* @method UIxAnchorPane _setScaleZ(mixed $arg0)
* @method UIxAnchorPane _setTranslateZ(mixed $arg0)
* @method UIxAnchorPane _setMouseTransparent(mixed $arg0)
* @method UIxAnchorPane _setCacheHint( $arg0)
* @method UIxAnchorPane setContentBias($mixed)
* @method UIxAnchorPane setParent($mixed)
* @method UIxAnchorPane _setSize(array $arg0)
* @method UIxAnchorPane setLayoutBounds($mixed)
* @method UIxAnchorPane _setWidth(mixed $arg0)
* @method UIxAnchorPane setScene($mixed)
* @method UIxAnchorPane _setX(mixed $arg0)
* @method UIxAnchorPane _setY(mixed $arg0)
* @method UIxAnchorPane _setCursor(mixed $arg0)
* @method UIxAnchorPane _setUserData(mixed $arg0)
* @method UIxAnchorPane setBoundsInParent($mixed)
* @method UIxAnchorPane _setPosition(array $arg0)
* @method UIxAnchorPane _setScale(mixed $arg0)
* @method UIxAnchorPane _setHeight(mixed $arg0)
* @method UIxAnchorPane setWindow($mixed)
* @method UIxAnchorPane _setEnabled(mixed $arg0)
* @method UIxAnchorPane setForm($mixed)
* @method UIxAnchorPane _setScreenPosition(array $arg0)
* @method UIxAnchorPane _setScreenY(mixed $arg0)
* @method UIxAnchorPane _setScreenX(mixed $arg0)
* @method UIxAnchorPane _setLeftAnchor(php\gui\UXNode $arg0, mixed $arg1)
* @method UIxAnchorPane _setBottomAnchor(php\gui\UXNode $arg0, mixed $arg1)
* @method UIxAnchorPane _setClassesString(mixed $arg0)
* @method UIxAnchorPane _setAnchorFlags(array $arg0)
* @method UIxAnchorPane _setAnchors(array $arg0)
* @method UIxAnchorPane _setTopAnchor(php\gui\UXNode $arg0, mixed $arg1)
* @method UIxAnchorPane _setRightAnchor(php\gui\UXNode $arg0, mixed $arg1)
* @method UIxAnchorPane setEffects($mixed) */

class UIxAnchorPane extends UXAnchorPane {
    use Cascadable;
}
