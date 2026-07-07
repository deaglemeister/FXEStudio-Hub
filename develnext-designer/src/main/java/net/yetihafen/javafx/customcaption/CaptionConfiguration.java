package net.yetihafen.javafx.customcaption;

import javafx.scene.Node;
import javafx.scene.control.MenuBar;
import javafx.scene.paint.Color;
import javafx.stage.Stage;
import net.yetihafen.javafx.customcaption.internal.MenuBarDragRegion;
import net.yetihafen.javafx.customcaption.internal.ShowInitializable;

public class CaptionConfiguration implements ShowInitializable {

    public static final CaptionConfiguration DEFAULT_CONFIG = new CaptionConfiguration();

    private int captionHeight;
    private Color iconColor;
    private Color controlBackgroundColor;
    private Color buttonHoverColor = Color.web("#808080");
    private Color closeButtonHoverColor = Color.RED;
    private Color iconHoverColor = Color.WHITE;
    private boolean useControls = true;
    private DragRegion captionDragRegion;

    public CaptionConfiguration() {
        this(31);
    }

    public CaptionConfiguration(int captionHeight) {
        this(captionHeight, Color.web("#A9A9A9"));
    }

    public CaptionConfiguration(int captionHeight, Color iconColor) {
        this(captionHeight, iconColor, Color.TRANSPARENT);
    }

    public CaptionConfiguration(int captionHeight, Color iconColor, Color controlBackgroundColor) {
        this.captionHeight = captionHeight;
        this.iconColor = iconColor;
        this.controlBackgroundColor = controlBackgroundColor;
    }

    public CaptionConfiguration setIconHoverColor(Color iconHoverColor) {
        this.iconHoverColor = iconHoverColor;
        return this;
    }

    public CaptionConfiguration setCloseButtonHoverColor(Color closeButtonHoverColor) {
        this.closeButtonHoverColor = closeButtonHoverColor;
        return this;
    }

    public CaptionConfiguration setButtonHoverColor(Color buttonHoverColor) {
        this.buttonHoverColor = buttonHoverColor;
        return this;
    }

    public CaptionConfiguration setIconColor(Color iconColor) {
        this.iconColor = iconColor;
        return this;
    }

    public CaptionConfiguration setCaptionHeight(int captionHeight) {
        this.captionHeight = captionHeight;
        return this;
    }

    public CaptionConfiguration setControlBackgroundColor(Color controlBackgroundColor) {
        this.controlBackgroundColor = controlBackgroundColor;
        return this;
    }

    public CaptionConfiguration useControls(boolean useControls) {
        this.useControls = useControls;
        return this;
    }

    public CaptionConfiguration setCaptionDragRegion(Node captionDragRegion) {
        this.captionDragRegion = new DragRegion(captionDragRegion);
        return this;
    }

    public CaptionConfiguration setCaptionDragRegion(DragRegion captionDragRegion) {
        this.captionDragRegion = captionDragRegion;
        return this;
    }

    public CaptionConfiguration setCaptionDragRegion(MenuBar menuBar) {
        this.captionDragRegion = new MenuBarDragRegion(menuBar);
        return this;
    }

    @Override
    public void showInit() {
        if (captionDragRegion != null) {
            captionDragRegion.showInit();
        }
    }

    public int getCaptionHeight() {
        return captionHeight;
    }

    public Color getIconColor() {
        return iconColor;
    }

    public Color getControlBackgroundColor() {
        return controlBackgroundColor;
    }

    public Color getButtonHoverColor() {
        return buttonHoverColor;
    }

    public Color getCloseButtonHoverColor() {
        return closeButtonHoverColor;
    }

    public Color getIconHoverColor() {
        return iconHoverColor;
    }

    public boolean isUseControls() {
        return useControls;
    }

    public DragRegion getCaptionDragRegion() {
        return captionDragRegion;
    }
}
