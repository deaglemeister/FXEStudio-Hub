<?php

namespace kosogroup\liver\ui\components;

use kosogroup\liver\ui\components\traits\Cascadable;
use php\gui\UXTableView;

/**
* @method UIxTableView _setItems(php\gui\UXList $arg0)
* @method UIxTableView setColumns($mixed)
* @method UIxTableView _setEditable(mixed $arg0)
* @method UIxTableView _setPlaceholder(php\gui\UXNode $arg0)
* @method UIxTableView _setTableMenuButtonVisible(mixed $arg0)
* @method UIxTableView _setFixedCellSize(mixed $arg0)
* @method UIxTableView _setSelectedIndexes(array $arg0)
* @method UIxTableView _setMultipleSelection(mixed $arg0)
* @method UIxTableView _setConstrainedResizePolicy(mixed $arg0)
* @method UIxTableView setSelectedItem($mixed)
* @method UIxTableView _setSelectedIndex(mixed $arg0)
* @method UIxTableView setFocusedItem($mixed)
* @method UIxTableView setSelectedItems($mixed)
* @method UIxTableView _setFocusedIndex(mixed $arg0)
* @method UIxTableView setResizable($mixed)
* @method UIxTableView _setTooltip(php\gui\UXTooltip $arg0)
* @method UIxTableView _setContextMenu(php\gui\UXContextMenu $arg0)
* @method UIxTableView _setTooltipText(mixed $arg0)
* @method UIxTableView _setMinHeight(mixed $arg0)
* @method UIxTableView _setMinWidth(mixed $arg0)
* @method UIxTableView _setMaxWidth(mixed $arg0)
* @method UIxTableView _setMaxHeight(mixed $arg0)
* @method UIxTableView _setPadding(mixed $arg0)
* @method UIxTableView _setMinSize(array $arg0)
* @method UIxTableView _setMaxSize(array $arg0)
* @method UIxTableView _setBackgroundColor(mixed $arg0)
* @method UIxTableView _setPaddingLeft(mixed $arg0)
* @method UIxTableView _setPaddingRight(mixed $arg0)
* @method UIxTableView setPrefSize($mixed)
* @method UIxTableView _setPaddingBottom(mixed $arg0)
* @method UIxTableView _setPaddingTop(mixed $arg0)
* @method UIxTableView setStylesheets($mixed)
* @method UIxTableView setChildrenUnmodifiable($mixed)
* @method UIxTableView _setCache(mixed $arg0)
* @method UIxTableView _setRotate(mixed $arg0)
* @method UIxTableView _setId(mixed $arg0)
* @method UIxTableView _setOpacity(mixed $arg0)
* @method UIxTableView _setStyle(mixed $arg0)
* @method UIxTableView _setVisible(mixed $arg0)
* @method UIxTableView _setManaged(mixed $arg0)
* @method UIxTableView setDisabled($mixed)
* @method UIxTableView setFocused($mixed)
* @method UIxTableView setPressed($mixed)
* @method UIxTableView setHover($mixed)
* @method UIxTableView _setFocusTraversable(mixed $arg0)
* @method UIxTableView _setBlendMode( $arg0)
* @method UIxTableView setClasses($mixed)
* @method UIxTableView _setPickOnBounds(mixed $arg0)
* @method UIxTableView _setEffect(php\gui\effect\UXEffect $arg0)
* @method UIxTableView setBaselineOffset($mixed)
* @method UIxTableView _setClip(php\gui\UXNode $arg0)
* @method UIxTableView _setScaleX(mixed $arg0)
* @method UIxTableView _setScaleY(mixed $arg0)
* @method UIxTableView _setTranslateX(mixed $arg0)
* @method UIxTableView _setTranslateY(mixed $arg0)
* @method UIxTableView _setDepthTest( $arg0)
* @method UIxTableView _setScaleZ(mixed $arg0)
* @method UIxTableView _setTranslateZ(mixed $arg0)
* @method UIxTableView _setCacheHint( $arg0)
* @method UIxTableView _setMouseTransparent(mixed $arg0)
* @method UIxTableView setContentBias($mixed)
* @method UIxTableView setParent($mixed)
* @method UIxTableView _setSize(array $arg0)
* @method UIxTableView setLayoutBounds($mixed)
* @method UIxTableView _setWidth(mixed $arg0)
* @method UIxTableView setScene($mixed)
* @method UIxTableView setBoundsInParent($mixed)
* @method UIxTableView _setY(mixed $arg0)
* @method UIxTableView _setX(mixed $arg0)
* @method UIxTableView _setUserData(mixed $arg0)
* @method UIxTableView _setCursor(mixed $arg0)
* @method UIxTableView _setScale(mixed $arg0)
* @method UIxTableView _setHeight(mixed $arg0)
* @method UIxTableView setWindow($mixed)
* @method UIxTableView _setPosition(array $arg0)
* @method UIxTableView setForm($mixed)
* @method UIxTableView _setEnabled(mixed $arg0)
* @method UIxTableView _setScreenPosition(array $arg0)
* @method UIxTableView _setScreenY(mixed $arg0)
* @method UIxTableView _setScreenX(mixed $arg0)
* @method UIxTableView setEffects($mixed)
* @method UIxTableView _setAnchors(array $arg0)
* @method UIxTableView _setTopAnchor(mixed $arg0)
* @method UIxTableView _setRightAnchor(mixed $arg0)
* @method UIxTableView _setClassesString(mixed $arg0)
* @method UIxTableView _setAnchorFlags(array $arg0)
* @method UIxTableView _setBottomAnchor(mixed $arg0)
* @method UIxTableView _setLeftAnchor(mixed $arg0) */

class UIxTableView extends UXTableView {
    use Cascadable;
}
