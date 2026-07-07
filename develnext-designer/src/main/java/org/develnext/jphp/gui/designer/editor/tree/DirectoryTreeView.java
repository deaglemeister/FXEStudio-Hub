package org.develnext.jphp.gui.designer.editor.tree;

import javafx.application.Platform;
import javafx.beans.property.SimpleObjectProperty;
import javafx.beans.value.ChangeListener;
import javafx.scene.control.TreeItem;
import javafx.scene.control.TreeView;
import javafx.util.Callback;

import java.util.*;

public class DirectoryTreeView extends TreeView<DirectoryTreeValue> {
    public static final AbstractDirectoryTreeSource EMPTY_TREE_SOURCE = new AbstractDirectoryTreeSource() {
        @Override
        public boolean isEmpty(String path) {
            return true;
        }

        @Override
        DirectoryTreeValue createValue(String path) {
            return new DirectoryTreeValue(path, "", "", null, null, false);
        }

        @Override
        List<DirectoryTreeValue> list(String path) {
            return Collections.emptyList();
        }

        @Override
        public DirectoryTreeListener listener(String path) {
            return null;
        }

        @Override
        public void shutdown() {

        }

        @Override
        public String rename(String path, String newName) {
            return null;
        }
    };
    private SimpleObjectProperty<AbstractDirectoryTreeSource> treeSource = new SimpleObjectProperty<>();
    private Set<String> selectedPathPool = new HashSet<>();
    private boolean renameAllowed = false;

    protected boolean isRenameablePath(String path) {
        if (path == null || path.isEmpty()) {
            return false;
        }

        if (path.startsWith("$v$")) {
            return false;
        }

        String name = new java.io.File(path).getName();

        return !name.endsWith(".dnproject");
    }

    public void startRename(TreeItem<DirectoryTreeValue> item) {
        if (item == null || item.getValue() == null || !isRenameablePath(item.getValue().getPath())) {
            return;
        }

        Platform.runLater(() -> {
            renameAllowed = true;
            super.edit(item);
        });
    }

    @Override
    public void edit(TreeItem<DirectoryTreeValue> item) {
        if (!renameAllowed) {
            return;
        }

        if (item == null || item.getValue() == null || !isRenameablePath(item.getValue().getPath())) {
            return;
        }

        super.edit(item);
    }

    public DirectoryTreeView() {
        setCellFactory(param -> new DirectoryTreeCell());

        treeSourceProperty().addListener((observable, oldValue, newValue) -> {
            TreeItem<DirectoryTreeValue> value = new TreeItem<>(getTreeSource().createValue(""));
            setRoot(value);

            refreshItem(value, "");
        });

        setEditable(true);

        setOnEditCommit(event -> {
            renameAllowed = false;

            DirectoryTreeValue oldValue = event.getOldValue();
            DirectoryTreeValue newValue = event.getNewValue();

            if (oldValue == null || newValue == null || !isRenameablePath(oldValue.getPath())) {
                return;
            }

            String newName = newValue.getText();

            if (newName == null || newName.trim().isEmpty() || newName.equals(oldValue.getText())) {
                return;
            }

            String rename = getTreeSource().rename(oldValue.getPath(), newName.trim());

            if (rename != null) {
                selectedPathPool.add(rename);
            }
        });

        setOnEditCancel(event -> {
            renameAllowed = false;
            refresh("");
        });

        addEventFilter(javafx.scene.input.KeyEvent.KEY_PRESSED, event -> {
            if (event.getCode() != javafx.scene.input.KeyCode.F2) {
                return;
            }

            TreeItem<DirectoryTreeValue> item = getSelectionModel().getSelectedItem();

            if (item != null) {
                startRename(item);
                event.consume();
            }
        });
    }

    public DirectoryTreeView(AbstractDirectoryTreeSource treeSource) {
        this();
        setTreeSource(treeSource);
    }

    public AbstractDirectoryTreeSource getTreeSource() {
        return treeSource.get();
    }

    public SimpleObjectProperty<AbstractDirectoryTreeSource> treeSourceProperty() {
        return treeSource;
    }

    public void setTreeSource(AbstractDirectoryTreeSource treeSource) {
        if (treeSource == null) {
            this.treeSource.set(EMPTY_TREE_SOURCE);
        } else {
            this.treeSource.set(treeSource);
        }
    }

    public void refresh(String path) {
        if (path.isEmpty() || path.equals("/")) {
            refreshItem(getRoot(), "");
        } else {
            String[] strings = path.split("//");
        }
    }

    private Set<String> selectedPaths = new HashSet<>();
    private Set<String> expandedPaths = new HashSet<>();

    private void eachChild(TreeItem<DirectoryTreeValue> owner, Callback<TreeItem<DirectoryTreeValue>, Void> callback) {
        for (TreeItem<DirectoryTreeValue> item : owner.getChildren()) {
            callback.call(item);
            eachChild(item, callback);
        }
    }

    protected void refreshItem(TreeItem<DirectoryTreeValue> item, String path) {
        refreshItem(item, path, true);
    }

    protected void refreshItem(TreeItem<DirectoryTreeValue> item, String path, boolean saveState) {
        boolean empty = getTreeSource().isEmpty(path);

        if (saveState) {
            expandedPaths.clear();
            selectedPaths.clear();

            eachChild(item, treeItem -> {
                if (treeItem.isExpanded()) {
                    expandedPaths.add(treeItem.getValue().getPath());
                }

                return null;
            });

            selectedPaths.addAll(selectedPathPool);
            selectedPathPool.clear();

            for (TreeItem<DirectoryTreeValue> treeItem : getSelectionModel().getSelectedItems()) {
                if (treeItem != null && treeItem.getValue() != null && treeItem.getValue().getPath() != null) {
                    String p = treeItem.getValue().getPath();
                    selectedPaths.add(p);
                }
            }
        }

        item.getChildren().clear();
        TreeItem<DirectoryTreeValue> sub = new TreeItem<>(new DirectoryTreeValue("", ".", ".", null, null, false));

        if (!item.getValue().isAlreadyLoaded()) {
            item.getValue().setAlreadyLoaded(true);
            ChangeListener<Boolean> expandListener = (observable, oldValue, newValue) -> {
                if (newValue) {
                    refreshItem(item, path, false);
                } else {
                    eachChild(item, param -> {
                        if (param.getValue().isFolder()) {
                            DirectoryTreeListener listener = getTreeSource().listener(param.getValue().getPath());

                            if (listener != null) {
                                listener.shutdown();
                            }
                        }
                        return null;
                    });

                    item.getChildren().clear();
                    item.getChildren().add(sub);
                }
            };
            item.expandedProperty().addListener(expandListener);
        }

        DirectoryTreeListener listener = getTreeSource().listener(path);

        if (listener != null) {
            listener.bind(() -> {
                Platform.runLater(() -> refreshItem(item, path));
            });
        }

        if (!empty && item.isExpanded()) {
            List<DirectoryTreeValue> list = getTreeSource().list(path);

            List<TreeItem<DirectoryTreeValue>> selectedItems = new ArrayList<>();

            for (DirectoryTreeValue value : list) {
                TreeItem<DirectoryTreeValue> treeItem = new TreeItem<>(value);
                item.getChildren().add(treeItem);

                if (value.isFolder()) {
                    refreshItem(treeItem, value.getPath(), false);

                    if (expandedPaths.contains(value.getPath())) {
                        treeItem.setExpanded(true);
                    }
                }

                if (selectedPaths.contains(value.getPath())) {
                    selectedItems.add(treeItem);
                }
            }

            getSelectionModel().clearSelection(0);
            for (TreeItem<DirectoryTreeValue> selectedItem : selectedItems) {
                getSelectionModel().select(selectedItem);
            }


            if (saveState) {
                selectedPaths.clear();
                expandedPaths.clear();
            }
        } else {
            if (!empty) {
                item.getChildren().add(sub);
            }
        }
    }
}
