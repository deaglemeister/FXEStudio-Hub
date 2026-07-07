package org.fxestudio.jphp.ext.gui.support;

import javafx.beans.value.ChangeListener;
import javafx.geometry.Insets;
import javafx.scene.Node;
import javafx.scene.control.Button;
import javafx.scene.control.ContentDisplay;
import javafx.scene.layout.Background;
import javafx.scene.layout.BackgroundFill;
import javafx.scene.layout.CornerRadii;

public class IconButton extends Button {
    private static final String STYLE_CLASS = "x-icon-button";

    private double borderRadius = 6;
    private final ChangeListener<Number> sizeListener = (obs, oldVal, newVal) -> applyBorderRadius();

    public IconButton() {
        this(null, null);
    }

    public IconButton(Node graphic) {
        this(null, graphic);
    }

    public IconButton(String text, Node graphic) {
        super(text != null ? text : "", graphic);

        getStyleClass().add(STYLE_CLASS);
        setMnemonicParsing(false);
        setContentDisplay(ContentDisplay.LEFT);
        setPadding(new Insets(6, 12, 6, 12));
        setWrapText(false);

        widthProperty().addListener(sizeListener);
        heightProperty().addListener(sizeListener);
        applyBorderRadius();
    }

    public double getBorderRadius() {
        return borderRadius;
    }

    public void setBorderRadius(double borderRadius) {
        this.borderRadius = Math.max(0, borderRadius);
        applyBorderRadius();
    }

    private void applyBorderRadius() {
        double radius = borderRadius;
        CornerRadii radii = new CornerRadii(radius);

        Background background = getBackground();
        if (background != null && !background.getFills().isEmpty()) {
            BackgroundFill fill = background.getFills().get(0);
            setBackground(new Background(new BackgroundFill(fill.getFill(), radii, fill.getInsets())));
        }

        setStyle(String.format(
                "-fx-background-radius: %f; -fx-border-radius: %f;",
                radius, radius
        ));
    }

    @Override
    public void resize(double width, double height) {
        super.resize(width, height);
        applyBorderRadius();
    }
}
