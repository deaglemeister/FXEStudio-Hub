package org.fxestudio.jphp.ext.gui.classes;

import org.develnext.jphp.ext.javafx.classes.layout.UXStackPane;
import org.fxestudio.jphp.ext.gui.FXEGuiExtension;
import org.fxestudio.jphp.ext.gui.support.SearchBox;
import php.runtime.annotation.Reflection;
import php.runtime.annotation.Reflection.Property;
import php.runtime.env.Environment;
import php.runtime.reflection.ClassEntity;

@Reflection.Name(FXEGuiExtension.NS + "UXSearchBox")
public class UXSearchBox extends UXStackPane<SearchBox> {
    interface WrappedInterface {
        @Property String text();
        @Property String promptText();
        @Property double borderRadius();
    }

    public UXSearchBox(Environment env, SearchBox wrappedObject) {
        super(env, wrappedObject);
    }

    public UXSearchBox(Environment env, ClassEntity clazz) {
        super(env, clazz);
    }

    @Override
    public SearchBox getWrappedObject() {
        return (SearchBox) super.getWrappedObject();
    }

    @Reflection.Signature
    public void __construct() {
        __wrappedObject = new SearchBox();
    }
}
