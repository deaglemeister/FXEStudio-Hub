package org.fxestudio.jphp.ext.gui;

import org.fxestudio.jphp.ext.gui.classes.UXBadge;
import org.fxestudio.jphp.ext.gui.classes.UXIconButton;
import org.fxestudio.jphp.ext.gui.classes.UXSearchBox;
import org.fxestudio.jphp.ext.gui.support.Badge;
import org.fxestudio.jphp.ext.gui.support.IconButton;
import org.fxestudio.jphp.ext.gui.support.SearchBox;
import php.runtime.env.CompileScope;
import php.runtime.ext.support.Extension;

public class FXEGuiExtension extends Extension {
    public static final String NS = "php\\gui\\";

    @Override
    public Status getStatus() {
        return Status.STABLE;
    }

    @Override
    public void onRegister(CompileScope scope) {
        registerWrapperClass(scope, IconButton.class, UXIconButton.class);
        registerWrapperClass(scope, SearchBox.class, UXSearchBox.class);
        registerWrapperClass(scope, Badge.class, UXBadge.class);
    }
}
