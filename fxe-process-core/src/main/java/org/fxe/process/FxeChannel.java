package org.fxe.process;

/**
 * Канал структурированного лога FXE Studio.
 */
public enum FxeChannel {
    IDE("IDE"),
    INDEXER("INDEXER"),
    RUNNER("RUNNER"),
    BUILDER("BUILDER"),
    ANALYZER("ANALYZER"),
    LSP("LSP"),
    APP("APP");

    private final String tag;

    FxeChannel(String tag) {
        this.tag = tag;
    }

    public String getTag() {
        return tag;
    }

    public String format(String level, String message) {
        return "[FXE][" + tag + "][" + level + "] " + message;
    }
}
