package org.fxe.process;

import java.util.Map;

/**
 * Базовый цикл helper-процесса: читает JSON-lines команды из stdin.
 */
public abstract class AbstractFxeHelperMain {
    protected final FxeProcessIo io;

    protected AbstractFxeHelperMain(FxeChannel channel) {
        this.io = new FxeProcessIo(channel);
    }

    public void run() {
        io.info("ready");

        try {
            Map<String, String> command;
            while ((command = io.readCommand()) != null) {
                handleCommand(command);
            }
        } catch (Exception e) {
            io.error(e.getClass().getSimpleName() + ": " + e.getMessage());
        }

        io.info("stopped");
    }

    protected abstract void handleCommand(Map<String, String> command);

    protected String cmd(Map<String, String> command) {
        return command.get("cmd");
    }

    protected String id(Map<String, String> command) {
        String id = command.get("id");
        return id == null ? "" : id;
    }
}
