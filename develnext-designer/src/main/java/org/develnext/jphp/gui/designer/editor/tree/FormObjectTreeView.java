package org.develnext.jphp.gui.designer.editor.tree;

import javafx.application.Platform;
import javafx.scene.control.TreeItem;
import javafx.scene.control.TreeView;
import javafx.scene.input.KeyCode;
import javafx.scene.input.KeyEvent;
import php.runtime.invoke.Invoker;

public class FormObjectTreeView extends TreeView<FormObjectTreeValue> {
    private Invoker onRename;
    private Invoker onSelectionChanged;
    private boolean renameAllowed = false;

    public FormObjectTreeView() {
        setCellFactory(param -> new FormObjectTreeCell());
        setEditable(true);
        setShowRoot(true);

        getSelectionModel().selectedItemProperty().addListener((observable, oldValue, newValue) -> {
            if (onSelectionChanged == null) {
                return;
            }

            String id = "";

            if (newValue != null && newValue.getValue() != null) {
                id = newValue.getValue().getNodeId();
            }

            onSelectionChanged.callAny(id);
        });

        setOnEditCommit(event -> {
            renameAllowed = false;

            FormObjectTreeValue oldValue = event.getOldValue();
            FormObjectTreeValue newValue = event.getNewValue();

            if (oldValue == null || newValue == null || onRename == null) {
                return;
            }

            String oldId = oldValue.getNodeId();
            String newId = newValue.getId();

            if (oldId.equals(newId)) {
                return;
            }

            Object result = onRename.callAny(oldId, newId);

            if (result == null || Boolean.FALSE.equals(result) || "false".equals(String.valueOf(result))) {
                TreeItem<FormObjectTreeValue> item = event.getTreeItem();

                if (item != null) {
                    item.setValue(oldValue);
                }
            }
        });

        setOnEditCancel(event -> {
            renameAllowed = false;
        });

        addEventFilter(KeyEvent.KEY_PRESSED, event -> {
            if (event.getCode() != KeyCode.F2) {
                return;
            }

            TreeItem<FormObjectTreeValue> item = getSelectionModel().getSelectedItem();
            startRename(item);
            event.consume();
        });
    }

    public void startRename(TreeItem<FormObjectTreeValue> item) {
        if (item == null || item.getValue() == null || !item.getValue().isRenameable()) {
            return;
        }

        Platform.runLater(() -> {
            renameAllowed = true;
            super.edit(item);
        });
    }

    @Override
    public void edit(TreeItem<FormObjectTreeValue> item) {
        if (!renameAllowed) {
            return;
        }

        if (item != null && item.getValue() != null && !item.getValue().isRenameable()) {
            return;
        }

        super.edit(item);
    }

    public void setOnRename(Invoker onRename) {
        this.onRename = onRename;
    }

    public void setOnSelectionChanged(Invoker onSelectionChanged) {
        this.onSelectionChanged = onSelectionChanged;
    }
}
