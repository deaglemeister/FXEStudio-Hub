package org.fxe.console.jphp;

import org.fxe.console.FxeLogger;
import php.runtime.annotation.Reflection;
import php.runtime.env.Environment;
import php.runtime.invoke.Invoker;
import php.runtime.lang.BaseObject;
import php.runtime.reflection.ClassEntity;

@Reflection.Name(FxeConsoleExtension.NS + "UXFxeSmartConsole")
public class UXFxeSmartConsole extends BaseObject {
    private final FxeLogger logger = new FxeLogger();

    @Reflection.Property
    public Invoker onEvent;

    public UXFxeSmartConsole(Environment env, ClassEntity clazz) {
        super(env, clazz);

        logger.setListener(event -> {
            Invoker handler = onEvent;
            if (handler != null) {
                try {
                    handler.callAny(
                            event.formatLine() + "\n",
                            FxeLogger.colorFor(event.getLevel()),
                            event.getLevel().name()
                    );
                } catch (Throwable ignored) {
                }
            }
        });
    }

    @Reflection.Signature
    public void __construct() {
    }

    @Reflection.Signature
    public void info(String message) { logger.info(message); }

    @Reflection.Signature
    public void ok(String message) { logger.ok(message); }

    @Reflection.Signature
    public void success(String message) { logger.success(message); }

    @Reflection.Signature
    public void warn(String message) { logger.warn(message); }

    @Reflection.Signature
    public void error(String message) { logger.error(message); }

    @Reflection.Signature
    public void debug(String message) { logger.debug(message); }

    @Reflection.Signature
    public void javaLog(String message) { logger.javaLog(message); }

    @Reflection.Signature
    public void jphp(String message) { logger.jphp(message); }

    @Reflection.Signature
    public void bundle(String message) { logger.bundle(message); }

    @Reflection.Signature
    public void run(String message) { logger.run(message); }

    @Reflection.Signature
    public void build(String message) { logger.build(message); }

    @Reflection.Signature
    public void fix(String message) { logger.fix(message); }

    @Reflection.Signature
    public void processBuildLine(String line) { logger.processBuildLine(line); }

    @Reflection.Signature
    public void processAppLine(String line, boolean stderr) { logger.processAppLine(line, stderr); }

    @Reflection.Signature
    public void markStart() { logger.markStart(); }

    @Reflection.Signature
    public void finish(int exitCode, boolean hasError) { logger.finish(exitCode, hasError); }

    @Reflection.Signature
    public void clear() { logger.clear(); }

    @Reflection.Signature
    public String exportText() { return logger.exportText(); }

    @Reflection.Signature
    public void setShowDebug(boolean value) { logger.setShowDebug(value); }

    @Reflection.Signature
    public boolean isShowDebug() { return logger.isShowDebug(); }

    @Reflection.Signature
    public void setFilter(String mode) {
        if (mode == null) {
            logger.setFilterMode(FxeLogger.FilterMode.ALL);
            return;
        }

        switch (mode.toUpperCase()) {
            case "FXE":
                logger.setFilterMode(FxeLogger.FilterMode.FXE);
                break;
            case "APP":
                logger.setFilterMode(FxeLogger.FilterMode.APP);
                break;
            case "ERRORS":
                logger.setFilterMode(FxeLogger.FilterMode.ERRORS);
                break;
            case "WARNINGS":
                logger.setFilterMode(FxeLogger.FilterMode.WARNINGS);
                break;
            case "DEBUG":
                logger.setFilterMode(FxeLogger.FilterMode.DEBUG);
                logger.setShowDebug(true);
                break;
            default:
                logger.setFilterMode(FxeLogger.FilterMode.ALL);
                break;
        }
    }
}
