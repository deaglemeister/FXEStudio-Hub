package org.develnext.jphp.gui.designer.classes;

import org.develnext.jphp.gui.designer.GuiDesignerExtension;
import org.develnext.jphp.gui.designer.editor.syntax.impl.LogCodeArea;
import php.runtime.annotation.Reflection;
import php.runtime.annotation.Reflection.Signature;
import php.runtime.env.Environment;
import php.runtime.reflection.ClassEntity;

@Reflection.Namespace(GuiDesignerExtension.NS)
public class UXLogCodeArea<T extends LogCodeArea> extends UXAbstractCodeArea<LogCodeArea> {
    interface WrappedInterface {
    }

    public UXLogCodeArea(Environment env, T wrappedObject) {
        super(env, wrappedObject);
    }

    public UXLogCodeArea(Environment env, ClassEntity clazz) {
        super(env, clazz);
    }

    @Signature
    public void __construct() {
        __wrappedObject = new LogCodeArea();
    }
}
