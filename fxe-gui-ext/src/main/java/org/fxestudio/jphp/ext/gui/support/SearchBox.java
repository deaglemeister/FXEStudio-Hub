package org.fxestudio.jphp.ext.gui.support;

import javafx.beans.property.StringProperty;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.control.Button;
import javafx.scene.control.TextField;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Priority;
import javafx.scene.layout.StackPane;

public class SearchBox extends StackPane {
    private static final String STYLE_CLASS = "x-search-box";

    private final TextField field;
    private final Button clearButton;
    private final HBox row;

    private double borderRadius = 6;

    public SearchBox() {
        getStyleClass().add(STYLE_CLASS);

        field = new TextField();
        field.getStyleClass().add("x-search-box-field");
        field.setStyle("-fx-background-color: transparent; -fx-border-width: 0; -fx-padding: 4 8;");

        clearButton = new Button("\u00d7");
        clearButton.getStyleClass().add("x-search-box-clear");
        clearButton.setFocusTraversable(false);
        clearButton.setMinSize(24, 24);
        clearButton.setMaxSize(24, 24);
        clearButton.setStyle("-fx-background-color: transparent; -fx-text-fill: #94a3b8; -fx-padding: 0; -fx-font-size: 16;");
        clearButton.setVisible(false);
        clearButton.setManaged(false);
        clearButton.setOnAction(e -> {
            field.clear();
            field.requestFocus();
        });

        field.textProperty().addListener((obs, oldValue, newValue) -> updateClearButton(newValue));

        row = new HBox(2);
        row.setAlignment(Pos.CENTER_LEFT);
        row.setPadding(new Insets(2, 6, 2, 8));
        HBox.setHgrow(field, Priority.ALWAYS);
        row.getChildren().addAll(field, clearButton);

        getChildren().add(row);
        setMinHeight(32);

        setOnMouseClicked(e -> field.requestFocus());

        updateBorderStyle();
    }

    public String getText() {
        return field.getText();
    }

    public void setText(String text) {
        field.setText(text != null ? text : "");
    }

    public StringProperty textProperty() {
        return field.textProperty();
    }

    public String getPromptText() {
        return field.getPromptText();
    }

    public void setPromptText(String text) {
        field.setPromptText(text);
    }

    public double getBorderRadius() {
        return borderRadius;
    }

    public void setBorderRadius(double borderRadius) {
        this.borderRadius = Math.max(0, borderRadius);
        updateBorderStyle();
    }

    public TextField getField() {
        return field;
    }

    private void updateClearButton(String text) {
        boolean hasText = text != null && !text.isEmpty();
        clearButton.setVisible(hasText);
        clearButton.setManaged(hasText);
    }

    private void updateBorderStyle() {
        setStyle(String.format(
                "-fx-border-color: #cbd5e1; -fx-border-radius: %f; -fx-background-radius: %f; -fx-background-color: white;",
                borderRadius, borderRadius
        ));
    }
}
