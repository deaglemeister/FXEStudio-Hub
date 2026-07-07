package org.develnext.jphp.gui.designer.editor.tree;

import javafx.geometry.Pos;
import javafx.scene.control.Label;
import javafx.scene.control.Tooltip;
import javafx.scene.control.cell.TextFieldTreeCell;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Priority;
import javafx.util.StringConverter;

public class FormObjectTreeCell extends TextFieldTreeCell<FormObjectTreeValue> {
    private final HBox content = new HBox(6);
    private final Label idLabel = new Label();
    private final Label typeLabel = new Label();
    private FormObjectTreeValue editItem;

    public FormObjectTreeCell() {
        super(new StringConverter<FormObjectTreeValue>() {
            private FormObjectTreeValue item;

            @Override
            public String toString(FormObjectTreeValue object) {
                item = object;
                return object == null ? "" : object.getId();
            }

            @Override
            public FormObjectTreeValue fromString(String string) {
                if (item == null) {
                    return null;
                }

                return item.withId(string);
            }
        });

        content.setAlignment(Pos.CENTER_LEFT);
        idLabel.getStyleClass().add("tree-item-id");
        typeLabel.getStyleClass().add("tree-item-type");
        typeLabel.setStyle("-fx-text-fill: #888888;");

        HBox.setHgrow(idLabel, Priority.NEVER);
        HBox.setHgrow(typeLabel, Priority.ALWAYS);
        typeLabel.setMaxWidth(Double.MAX_VALUE);
    }

    @Override
    public void updateItem(FormObjectTreeValue item, boolean empty) {
        super.updateItem(item, empty);
        editItem = item;

        if (empty || item == null) {
            setText(null);
            setGraphic(null);
            setTooltip(null);
            return;
        }

        if (isEditing()) {
            setGraphic(null);
            return;
        }

        idLabel.setText(item.getId());
        typeLabel.setText(item.getTypeName().isEmpty() ? "" : item.getTypeName());

        content.getChildren().clear();

        if (item.getIcon() != null) {
            content.getChildren().add(item.getIcon());
        }

        content.getChildren().addAll(idLabel, typeLabel);

        setText(null);
        setGraphic(content);

        String tooltip = item.getTypeName().isEmpty()
                ? item.getId()
                : item.getId() + " : " + item.getTypeName();

        setTooltip(new Tooltip(tooltip));
    }
}
