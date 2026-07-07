package org.develnext.jphp.gui.designer.classes;

import javafx.scene.Node;
import javafx.stage.Stage;
import org.develnext.jphp.gui.designer.GuiDesignerExtension;
import org.develnext.jphp.ext.javafx.classes.UXForm;
import php.runtime.annotation.Reflection.Namespace;
import php.runtime.annotation.Reflection.Signature;
import php.runtime.env.Environment;
import php.runtime.lang.BaseObject;
import php.runtime.reflection.ClassEntity;

@Namespace(GuiDesignerExtension.NS)
public class FxeWindowChrome extends BaseObject {
    private org.develnext.jphp.gui.designer.window.FxeWindowChrome chrome;

    public FxeWindowChrome(Environment env, ClassEntity clazz) {
        super(env, clazz);
    }

    @Signature
    public void __construct(UXForm form) {
        Stage stage = form.getWrappedObject();
        chrome = org.develnext.jphp.gui.designer.window.FxeWindowChrome.install(stage);
    }

    @Signature
    public boolean isInstalled() {
        return chrome != null;
    }

    @Signature
    public boolean isApplied() {
        return chrome != null && chrome.isApplied();
    }

    @Signature
    public void apply() {
        if (chrome != null) {
            chrome.apply();
        }
    }

    @Signature
    public void setMenuNode(Node node) {
        if (chrome != null && node != null) {
            chrome.setMenuNode(node);
        }
    }

    @Signature
    public boolean usesCustomTitleBar() {
        return chrome != null && chrome.usesCustomTitleBar();
    }

    @Signature
    public void toggleMaximize() {
        if (chrome != null) {
            chrome.toggleMaximize();
        }
    }
}
