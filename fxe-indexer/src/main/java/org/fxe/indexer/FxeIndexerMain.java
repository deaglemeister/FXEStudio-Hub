package org.fxe.indexer;

import org.fxe.process.AbstractFxeHelperMain;
import org.fxe.process.FxeChannel;

import java.util.Map;

/**
 * Project indexer: forms, events, PHP, Event Map.
 */
public class FxeIndexerMain extends AbstractFxeHelperMain {
    public FxeIndexerMain() {
        super(FxeChannel.INDEXER);
    }

    public static void main(String[] args) {
        new FxeIndexerMain().run();
    }

    @Override
    protected void handleCommand(Map<String, String> command) {
        String cmd = cmd(command);
        String id = id(command);

        if ("ping".equals(cmd)) {
            io.respond(id, true, "pong");
            return;
        }

        if ("scan".equals(cmd)) {
            String projectPath = command.get("project");
            io.info("scan: " + projectPath);
            io.respond(id, true, "scan stub");
            return;
        }

        if ("stop".equals(cmd)) {
            io.respond(id, true, "stopped");
            System.exit(0);
            return;
        }

        io.respond(id, false, "unknown cmd: " + cmd);
    }
}
