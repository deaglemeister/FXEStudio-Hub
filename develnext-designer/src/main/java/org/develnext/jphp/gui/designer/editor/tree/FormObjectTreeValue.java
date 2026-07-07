package org.develnext.jphp.gui.designer.editor.tree;

import javafx.scene.Node;

public class FormObjectTreeValue {
    private String id;
    private final String typeName;
    private final Node icon;
    private final boolean renameable;

    public FormObjectTreeValue(String id, String typeName, Node icon, boolean renameable) {
        this.id = id == null ? "" : id;
        this.typeName = typeName == null ? "" : typeName;
        this.icon = icon;
        this.renameable = renameable;
    }

    public String getId() {
        return id;
    }

    public void setId(String id) {
        this.id = id == null ? "" : id;
    }

    public String getNodeId() {
        return id;
    }

    public String getTypeName() {
        return typeName;
    }

    public Node getIcon() {
        return icon;
    }

    public boolean isRenameable() {
        return renameable;
    }

    public FormObjectTreeValue withId(String newId) {
        return new FormObjectTreeValue(newId, typeName, icon, renameable);
    }

    @Override
    public String toString() {
        return id;
    }
}
