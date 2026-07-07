package org.fxe.console.jphp;

import org.fxe.console.ErrorClassifier;
import org.fxe.console.FxeConsoleView;
import org.fxe.console.FxeLogger;
import php.runtime.env.CompileScope;
import php.runtime.ext.support.Extension;

public class FxeConsoleExtension extends Extension {
    public static final String NS = "php\\console\\";

    @Override
    public Status getStatus() {
        return Status.STABLE;
    }

    @Override
    public void onRegister(CompileScope scope) {
        registerClass(scope, UXFxeSmartConsole.class);
    }
}
