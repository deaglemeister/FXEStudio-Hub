package org.fxestudio.jphp.ext.gui.classes;

import org.develnext.jphp.ext.javafx.classes.UXButtonBase;
import org.fxestudio.jphp.ext.gui.FXEGuiExtension;
import org.fxestudio.jphp.ext.gui.support.IconButton;
import php.runtime.annotation.Reflection;
import php.runtime.annotation.Reflection.Property;
import php.runtime.env.Environment;
import php.runtime.reflection.ClassEntity;

@Reflection.Name(FXEGuiExtension.NS + "UXIconButton")
public class UXIconButton extends UXButtonBase<IconButton> {
    interface WrappedInterface {
        @Property double borderRadius();
    }

    public UXIconButton(Environment env, IconButton wrappedObject) {
        super(env, wrappedObject);
    }

    public UXIconButton(Environment env, ClassEntity clazz) {
        super(env, clazz);
    }

    @Override
    public IconButton getWrappedObject() {
        return (IconButton) super.getWrappedObject();
    }

    @Reflection.Signature
    public void __construct() {
        __wrappedObject = new IconButton();
    }
}
