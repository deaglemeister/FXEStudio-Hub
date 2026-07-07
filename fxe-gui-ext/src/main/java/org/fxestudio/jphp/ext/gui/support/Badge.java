package org.fxestudio.jphp.ext.gui.support;

import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.control.ContentDisplay;
import javafx.scene.control.Label;
import javafx.scene.paint.Color;
import javafx.scene.text.Font;
import javafx.scene.text.FontWeight;

public class Badge extends Label {
    private static final String STYLE_CLASS = "x-badge";

    private String badgeType = "default";
    private double borderRadius = -1;
    private Color backgroundColor;

    public Badge() {
        this("NEW");
    }

    public Badge(String text) {
        super(text != null ? text : "");

        getStyleClass().add(STYLE_CLASS);
        setAlignment(Pos.CENTER);
        setContentDisplay(ContentDisplay.TEXT_ONLY);
        setPadding(new Insets(2, 8, 2, 8));
        setFont(Font.font(null, FontWeight.BOLD, 11));
        setTextFill(Color.WHITE);
        applyBadgeType();
    }

    public String getBadgeType() {
        return badgeType;
    }

    public void setBadgeType(String badgeType) {
        this.badgeType = badgeType != null ? badgeType.toLowerCase() : "default";
        applyBadgeType();
    }

    public double getBorderRadius() {
        return borderRadius;
    }

    public void setBorderRadius(double borderRadius) {
        this.borderRadius = borderRadius;
        applyBadgeType();
    }

    public Color getBackgroundColor() {
        return backgroundColor;
    }

    public void setBackgroundColor(Color backgroundColor) {
        this.backgroundColor = backgroundColor;
        applyBadgeType();
    }

    private void applyBadgeType() {
        double radius = borderRadius >= 0 ? borderRadius : defaultRadiusForType();
        Color bg = backgroundColor != null ? backgroundColor : defaultBackgroundForType();

        setStyle(String.format(
                "-fx-background-color: %s; -fx-background-radius: %f;",
                toWeb(bg), radius
        ));
    }

    private Color defaultBackgroundForType() {
        switch (badgeType) {
            case "new":
                return Color.web("#22c55e");
            case "beta":
                return Color.web("#f59e0b");
            case "count":
                return Color.web("#ef4444");
            default:
                return Color.web("#64748b");
        }
    }

    private double defaultRadiusForType() {
        return "count".equals(badgeType) ? 10 : 4;
    }

    private static String toWeb(Color color) {
        if (color == null) {
            return "#64748b";
        }
        return color.toString().replace("0x", "#");
    }
}
