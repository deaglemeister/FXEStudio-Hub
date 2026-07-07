package org.fxe.console;

import java.text.SimpleDateFormat;
import java.util.Date;

public class FxeLogEvent {
    private static final SimpleDateFormat TIME = new SimpleDateFormat("HH:mm:ss");

    private final FxeLogLevel level;
    private final String message;
    private final long timestamp;

    public FxeLogEvent(FxeLogLevel level, String message) {
        this.level = level;
        this.message = message == null ? "" : message;
        this.timestamp = System.currentTimeMillis();
    }

    public FxeLogLevel getLevel() {
        return level;
    }

    public String getMessage() {
        return message;
    }

    public long getTimestamp() {
        return timestamp;
    }

    public String formatLine() {
        String prefix = level.name();

        if (level == FxeLogLevel.APP_OUT || level == FxeLogLevel.APP_ERR) {
            return String.format("[APP][%s][%s] %s", appTag(), TIME.format(new Date(timestamp)), message);
        }

        if (level == FxeLogLevel.CAUSE || level == FxeLogLevel.FIX || level == FxeLogLevel.ACTION) {
            return String.format("[FXE][%s][%s] %s", prefix, TIME.format(new Date(timestamp)), message);
        }

        return String.format("[FXE][%s][%s] %s", prefix, TIME.format(new Date(timestamp)), message);
    }

    private String appTag() {
        return level == FxeLogLevel.APP_ERR ? "ERR" : "OUT";
    }

    public boolean isFxe() {
        return level != FxeLogLevel.APP_OUT && level != FxeLogLevel.APP_ERR;
    }

    public boolean isApp() {
        return level == FxeLogLevel.APP_OUT || level == FxeLogLevel.APP_ERR;
    }

    public boolean isErrorLike() {
        return level == FxeLogLevel.ERROR || level == FxeLogLevel.APP_ERR || level == FxeLogLevel.CAUSE;
    }

    public boolean isWarningLike() {
        return level == FxeLogLevel.WARN;
    }
}
