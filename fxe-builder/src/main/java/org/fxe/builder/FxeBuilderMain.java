package org.fxe.builder;

import org.fxe.process.AbstractFxeHelperMain;
import org.fxe.process.FxeChannel;

import java.util.Map;

/**
 * Project build process.
 */
public class FxeBuilderMain extends AbstractFxeHelperMain {
    public FxeBuilderMain() {
        super(FxeChannel.BUILDER);
    }

    public static void main(String[] args) {
        new FxeBuilderMain().run();
    }

    @Override
    protected void handleCommand(Map<String, String> command) {
        String cmd = cmd(command);
        String id = id(command);

        if ("ping".equals(cmd)) {
            io.respond(id, true, "pong");
            return;
        }

        if ("build".equals(cmd)) {
            String projectPath = command.get("project");
            io.info("build: " + projectPath);
            io.respond(id, true, "build stub");
            return;
        }

        if ("cancel".equals(cmd)) {
            io.respond(id, true, "cancelled");
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
