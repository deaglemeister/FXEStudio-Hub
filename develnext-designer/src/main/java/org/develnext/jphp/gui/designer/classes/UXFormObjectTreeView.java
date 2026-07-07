package org.develnext.jphp.gui.designer.classes;

import javafx.scene.Node;
import javafx.scene.control.TreeItem;
import javafx.scene.control.TreeView;
import org.develnext.jphp.ext.javafx.classes.UXTreeItem;
import org.develnext.jphp.ext.javafx.classes.UXTreeView;
import org.develnext.jphp.gui.designer.GuiDesignerExtension;
import org.develnext.jphp.gui.designer.editor.tree.FormObjectTreeValue;
import org.develnext.jphp.gui.designer.editor.tree.FormObjectTreeView;
import php.runtime.annotation.Reflection;
import php.runtime.annotation.Reflection.*;
import php.runtime.env.Environment;
import php.runtime.invoke.Invoker;
import php.runtime.reflection.ClassEntity;

@Reflection.Namespace(GuiDesignerExtension.NS)
public class UXFormObjectTreeView extends UXTreeView {
    public UXFormObjectTreeView(Environment env, TreeView wrappedObject) {
        super(env, wrappedObject);
    }

    public UXFormObjectTreeView(Environment env, ClassEntity clazz) {
        super(env, clazz);
    }

    @Signature
    public void __construct() {
        __wrappedObject = new FormObjectTreeView();
    }

    private FormObjectTreeView tree() {
        return (FormObjectTreeView) getWrappedObject();
    }

    @Signature
    public UXTreeItem createRootItem(Environment env, String id, String typeName, @Nullable Node icon) {
        return createItem(env, id, typeName, icon, false);
    }

    @Signature
    public UXTreeItem createNodeItem(Environment env, String id, String typeName, @Nullable Node icon) {
        return createItem(env, id, typeName, icon, true);
    }

    @Signature
    public UXTreeItem createItem(Environment env, String id, String typeName, @Nullable Node icon, boolean renameable) {
        FormObjectTreeValue value = new FormObjectTreeValue(id, typeName, icon, renameable);
        TreeItem<FormObjectTreeValue> item = new TreeItem<>(value);

        return new UXTreeItem(env, item);
    }

    @Signature
    public void editFocused() {
        FormObjectTreeView tree = tree();
        TreeItem item = tree.getSelectionModel().getSelectedItem();

        if (item != null) {
            tree().startRename(item);
        }
    }

    @Signature
    public void onRename(@Nullable Invoker invoker) {
        tree().setOnRename(invoker);
    }

    @Signature
    public void onSelectionChanged(@Nullable Invoker invoker) {
        tree().setOnSelectionChanged(invoker);
    }
}
