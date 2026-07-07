package org.fxestudio.jphp.ext.gui.classes;

import javafx.scene.paint.Color;
import org.develnext.jphp.ext.javafx.classes.UXLabel;
import org.fxestudio.jphp.ext.gui.FXEGuiExtension;
import org.fxestudio.jphp.ext.gui.support.Badge;
import php.runtime.annotation.Reflection;
import php.runtime.annotation.Reflection.Nullable;
import php.runtime.annotation.Reflection.Property;
import php.runtime.env.Environment;
import php.runtime.reflection.ClassEntity;

@Reflection.Name(FXEGuiExtension.NS + "UXBadge")
public class UXBadge extends UXLabel<Badge> {
    interface WrappedInterface {
        @Property String badgeType();
        @Property double borderRadius();
        @Property @Nullable Color backgroundColor();
    }

    public UXBadge(Environment env, Badge wrappedObject) {
        super(env, wrappedObject);
    }

    public UXBadge(Environment env, ClassEntity clazz) {
        super(env, clazz);
    }

    @Override
    public Badge getWrappedObject() {
        return (Badge) super.getWrappedObject();
    }

    @Reflection.Signature
    public void __construct() {
        __wrappedObject = new Badge();
    }

    @Reflection.Signature
    public void __construct(String text) {
        __wrappedObject = new Badge(text);
    }
}
