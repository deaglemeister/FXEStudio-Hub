package org.develnext.jphp.gui.designer.classes;

import javafx.scene.Node;
import org.develnext.jphp.gui.designer.GuiDesignerExtension;
import org.develnext.jphp.gui.designer.editor.tree.FormObjectTreeValue;
import php.runtime.annotation.Reflection;
import php.runtime.annotation.Reflection.Property;
import php.runtime.env.Environment;
import php.runtime.lang.BaseWrapper;
import php.runtime.reflection.ClassEntity;

@Reflection.Namespace(GuiDesignerExtension.NS)
public class UXFormObjectTreeValue extends BaseWrapper<FormObjectTreeValue> {
    interface WrappedInterface {
        @Property String id();
        @Property String typeName();
        @Property Node icon();
        @Property boolean renameable();
    }

    public UXFormObjectTreeValue(Environment env, FormObjectTreeValue wrappedObject) {
        super(env, wrappedObject);
    }

    public UXFormObjectTreeValue(Environment env, ClassEntity clazz) {
        super(env, clazz);
    }
}
