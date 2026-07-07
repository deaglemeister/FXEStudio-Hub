package org.develnext.jphp.gui.designer.editor.tree;

import javafx.beans.value.ChangeListener;
import javafx.beans.value.ObservableValue;
import javafx.event.EventHandler;
import javafx.geometry.Insets;
import javafx.scene.Node;
import javafx.scene.SnapshotParameters;
import javafx.scene.control.TextField;
import javafx.scene.control.Tooltip;
import javafx.scene.control.TreeItem;
import javafx.scene.control.cell.TextFieldTreeCell;
import javafx.scene.input.*;
import javafx.scene.layout.Region;
import javafx.scene.paint.Color;
import javafx.scene.text.Text;
import javafx.scene.text.TextFlow;
import javafx.util.StringConverter;

import java.io.File;
import java.util.ArrayList;
import java.util.List;

public class DirectoryTreeCell extends TextFieldTreeCell<DirectoryTreeValue> {
    private static final class EditContext {
        DirectoryTreeCell cell;
        DirectoryTreeValue editingItem;
    }

    private final EditContext editContext = new EditContext();
    private ChangeListener<Boolean> focusCommitListener;

    /** Вертикальные направляющие по уровням вложенности (как в IntelliJ). */
    private final List<Region> indentGuides = new ArrayList<>();
    private static final double INDENT_WIDTH = 18;
    private static final double GUIDE_OFFSET = 16;

    private static StringConverter<DirectoryTreeValue> createConverter(final EditContext[] holder) {
        return new StringConverter<DirectoryTreeValue>() {
            @Override
            public String toString(DirectoryTreeValue object) {
                if (object != null && holder[0] != null) {
                    holder[0].editingItem = object;
                }

                return object == null ? "" : object.getText();
            }

            @Override
            public DirectoryTreeValue fromString(String string) {
                if (holder[0] == null) {
                    return null;
                }

                DirectoryTreeValue current = holder[0].editingItem;

                if (current == null && holder[0].cell != null) {
                    current = holder[0].cell.getItem();
                }

                if (current == null) {
                    return null;
                }

                return current.withText(string);
            }
        };
    }

    public DirectoryTreeCell() {
        super();

        final EditContext[] holder = new EditContext[1];
        holder[0] = editContext;
        editContext.cell = this;
        setConverter(createConverter(holder));

        setOnDragDetected(new EventHandler<MouseEvent>() {
            @Override
            public void handle(MouseEvent event) {
                DirectoryTreeView treeView = (DirectoryTreeView) getTreeView();

                if (getTreeItem() == null || getTreeItem().getValue() == null) {
                    return;
                }

                Object dragContent = treeView.getTreeSource().getDragContent(getTreeItem().getValue().getPath());

                if (dragContent != null) {
                    Node source = (Node) event.getSource();

                    if (dragContent instanceof List) {
                        Dragboard dragboard = source.startDragAndDrop(TransferMode.MOVE);
                        SnapshotParameters parameters = new SnapshotParameters();
                        parameters.setFill(Color.TRANSPARENT);

                        Text text = new Text(getText());
                        text.setFont(getFont());

                        TextFlow textFlow = new TextFlow(text);
                        textFlow.setPadding(new Insets(3));
                        dragboard.setDragView(textFlow.snapshot(parameters, null));
                        dragboard.setDragViewOffsetY(-15);
                        dragboard.setDragViewOffsetX(-10);

                        ClipboardContent content = new ClipboardContent();
                        content.putFiles((List<File>) dragContent);

                        dragboard.setContent(content);
                    }
                }
            }
        });

        setOnDragEntered(new EventHandler<DragEvent>() {
            @Override
            public void handle(DragEvent event) {
                getTreeView().requestFocus();
                getTreeView().getFocusModel().focus(getTreeView().getRow(getTreeItem()));

                if (!getTreeItem().isExpanded()) {
                    getTreeItem().setExpanded(true);
                }
            }
        });
    }

    @Override
    public void startEdit() {
        editContext.editingItem = getItem();
        super.startEdit();

        if (!(getGraphic() instanceof TextField)) {
            return;
        }

        final TextField textField = (TextField) getGraphic();

        if (focusCommitListener != null) {
            textField.focusedProperty().removeListener(focusCommitListener);
        }

        focusCommitListener = new ChangeListener<Boolean>() {
            @Override
            public void changed(ObservableValue<? extends Boolean> observable, Boolean oldValue, Boolean newValue) {
                if (Boolean.TRUE.equals(oldValue) && Boolean.FALSE.equals(newValue) && isEditing()) {
                    DirectoryTreeValue item = getItem();

                    if (item != null) {
                        commitEdit(item.withText(textField.getText()));
                    }
                }
            }
        };

        textField.focusedProperty().addListener(focusCommitListener);
    }

    @Override
    public void cancelEdit() {
        if (getGraphic() instanceof TextField && focusCommitListener != null) {
            ((TextField) getGraphic()).focusedProperty().removeListener(focusCommitListener);
            focusCommitListener = null;
        }

        editContext.editingItem = null;
        super.cancelEdit();
    }

    @Override
    public void commitEdit(DirectoryTreeValue newValue) {
        if (newValue == null) {
            cancelEdit();
            return;
        }

        if (getGraphic() instanceof TextField && focusCommitListener != null) {
            ((TextField) getGraphic()).focusedProperty().removeListener(focusCommitListener);
            focusCommitListener = null;
        }

        editContext.editingItem = null;
        super.commitEdit(newValue);
    }

    @Override
    public void updateItem(DirectoryTreeValue item, boolean empty) {
        super.updateItem(item, empty);

        if (empty || item == null) {
            setText(null);
            setGraphic(null);
            setTooltip(null);
            return;
        }

        setText(item.getText());
        setTooltip(new Tooltip(item.getText()));

        Node icon = item.getIcon();

        TreeItem<DirectoryTreeValue> treeItem = getTreeItem();

        if (item.getExpandIcon() != null && treeItem != null && treeItem.isExpanded()) {
            icon = item.getExpandIcon();
        }

        if (icon == null) {
            icon = DirectoryTreeUtils.getIconByName("anyType");
        }

        setGraphic(icon);
    }

    @Override
    protected void layoutChildren() {
        super.layoutChildren();

        int level = 0;
        TreeItem<DirectoryTreeValue> treeItem = getTreeItem();

        if (treeItem != null && getTreeView() != null && !isEmpty()) {
            level = getTreeView().getTreeItemLevel(treeItem);
        }

        int guideCount = Math.max(0, level - 1);

        while (indentGuides.size() > guideCount) {
            Region guide = indentGuides.remove(indentGuides.size() - 1);
            getChildren().remove(guide);
        }

        while (indentGuides.size() < guideCount) {
            Region guide = new Region();
            guide.setManaged(false);
            guide.setMouseTransparent(true);
            guide.getStyleClass().add("fxe-tree-indent-guide");
            indentGuides.add(guide);
            getChildren().add(0, guide);
        }

        double height = getHeight();

        for (int i = 0; i < guideCount; i++) {
            double x = snappedLeftInset() + GUIDE_OFFSET + INDENT_WIDTH * i;
            indentGuides.get(i).resizeRelocate(x, 0, 1, height);
        }
    }
}
