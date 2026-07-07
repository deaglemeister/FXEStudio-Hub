package org.fxe.console;

public class SmartErrorExplainer {
    private final FxeLogger logger;

    public SmartErrorExplainer(FxeLogger logger) {
        this.logger = logger;
    }

    public void explain(String line) {
        ErrorClassifier.Result result = ErrorClassifier.classify(line);

        if (result == null) {
            return;
        }

        if (result.cause != null && !result.cause.isEmpty()) {
            logger.cause(result.cause);
        }

        if (result.fix != null && !result.fix.isEmpty()) {
            logger.fix(result.fix);
        }

        if (result.action != null && !result.action.isEmpty()) {
            logger.action(result.action);
        }
    }
}
