<?php

namespace kosogroup\liver\ui\components;

use kosogroup\liver\ui\components\traits\Cascadable;
use php\gui\UXPagination;
 
/**
* @method UIxPagination _setPageSize(mixed $arg0)
* @method UIxPagination setPageCount($mixed)
* @method UIxPagination _setTotal(mixed $arg0)
* @method UIxPagination _setHintText(mixed $arg0)
* @method UIxPagination setPreviousButton($mixed)
* @method UIxPagination setNextButton($mixed)
* @method UIxPagination _setTextColor(mixed $arg0)
* @method UIxPagination _setSelectedPage(mixed $arg0)
* @method UIxPagination _setMaxPageCount(mixed $arg0)
* @method UIxPagination _setShowPrevNext(mixed $arg0)
* @method UIxPagination _setShowTotal(mixed $arg0)
* @method UIxPagination _setFont(php\gui\text\UXFont $arg0)
* @method UIxPagination _setAlignment( $arg0)
* @method UIxPagination _setHgap(mixed $arg0)
* @method UIxPagination _setVgap(mixed $arg0)
* @method UIxPagination _setRowValignment( $arg0)
* @method UIxPagination _setOrientation( $arg0)
* @method UIxPagination _setColumnHalignment( $arg0)
* @method UIxPagination _setPrefWrapLength(mixed $arg0)
* @method UIxPagination setChildren($mixed)
* @method UIxPagination _setMinHeight(mixed $arg0)
* @method UIxPagination _setMaxWidth(mixed $arg0)
* @method UIxPagination _setMinWidth(mixed $arg0)
* @method UIxPagination _setPadding(mixed $arg0)
* @method UIxPagination _setMaxHeight(mixed $arg0)
* @method UIxPagination _setMaxSize(array $arg0)
* @method UIxPagination _setMinSize(array $arg0)
* @method UIxPagination _setBackgroundColor(mixed $arg0)
* @method UIxPagination _setPaddingLeft(mixed $arg0)
* @method UIxPagination _setPaddingRight(mixed $arg0)
* @method UIxPagination _setPaddingTop(mixed $arg0)
* @method UIxPagination _setPaddingBottom(mixed $arg0)
* @method UIxPagination setPrefSize($mixed)
* @method UIxPagination setStylesheets($mixed)
* @method UIxPagination setChildrenUnmodifiable($mixed)
* @method UIxPagination _setCache(mixed $arg0)
* @method UIxPagination _setRotate(mixed $arg0)
* @method UIxPagination _setId(mixed $arg0)
* @method UIxPagination _setScaleX(mixed $arg0)
* @method UIxPagination _setScaleY(mixed $arg0)
* @method UIxPagination _setTranslateX(mixed $arg0)
* @method UIxPagination _setTranslateY(mixed $arg0)
* @method UIxPagination _setOpacity(mixed $arg0)
* @method UIxPagination setDisabled($mixed)
* @method UIxPagination _setStyle(mixed $arg0)
* @method UIxPagination _setVisible(mixed $arg0)
* @method UIxPagination _setManaged(mixed $arg0)
* @method UIxPagination setFocused($mixed)
* @method UIxPagination setPressed($mixed)
* @method UIxPagination setHover($mixed)
* @method UIxPagination _setBlendMode( $arg0)
* @method UIxPagination setClasses($mixed)
* @method UIxPagination _setPickOnBounds(mixed $arg0)
* @method UIxPagination _setFocusTraversable(mixed $arg0)
* @method UIxPagination setResizable($mixed)
* @method UIxPagination _setEffect(php\gui\effect\UXEffect $arg0)
* @method UIxPagination setBaselineOffset($mixed)
* @method UIxPagination _setClip(php\gui\UXNode $arg0)
* @method UIxPagination _setDepthTest( $arg0)
* @method UIxPagination _setScaleZ(mixed $arg0)
* @method UIxPagination _setCacheHint( $arg0)
* @method UIxPagination _setTranslateZ(mixed $arg0)
* @method UIxPagination _setMouseTransparent(mixed $arg0)
* @method UIxPagination setContentBias($mixed)
* @method UIxPagination setParent($mixed)
* @method UIxPagination _setSize(array $arg0)
* @method UIxPagination setLayoutBounds($mixed)
* @method UIxPagination _setWidth(mixed $arg0)
* @method UIxPagination setScene($mixed)
* @method UIxPagination _setX(mixed $arg0)
* @method UIxPagination _setY(mixed $arg0)
* @method UIxPagination setBoundsInParent($mixed)
* @method UIxPagination _setCursor(mixed $arg0)
* @method UIxPagination _setUserData(mixed $arg0)
* @method UIxPagination _setPosition(array $arg0)
* @method UIxPagination _setScale(mixed $arg0)
* @method UIxPagination _setHeight(mixed $arg0)
* @method UIxPagination setWindow($mixed)
* @method UIxPagination setForm($mixed)
* @method UIxPagination _setEnabled(mixed $arg0)
* @method UIxPagination _setScreenPosition(array $arg0)
* @method UIxPagination _setScreenX(mixed $arg0)
* @method UIxPagination _setScreenY(mixed $arg0)
* @method UIxPagination _setClassesString(mixed $arg0)
* @method UIxPagination _setAnchorFlags(array $arg0)
* @method UIxPagination _setLeftAnchor(mixed $arg0)
* @method UIxPagination setEffects($mixed)
* @method UIxPagination _setRightAnchor(mixed $arg0)
* @method UIxPagination _setTopAnchor(mixed $arg0)
* @method UIxPagination _setBottomAnchor(mixed $arg0)
* @method UIxPagination _setAnchors(array $arg0) */

class UIxPagination extends UXPagination {
    use Cascadable;
}
