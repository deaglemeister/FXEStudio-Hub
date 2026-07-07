package org.fxe.runner;

import org.fxe.process.AbstractFxeHelperMain;
import org.fxe.process.FxeChannel;

import java.util.Map;

/**
 * User JPHP app runner process.
 */
public class FxeRunnerMain extends AbstractFxeHelperMain {
    public FxeRunnerMain() {
        super(FxeChannel.RUNNER);
    }

    public static void main(String[] args) {
        new FxeRunnerMain().run();
    }

    @Override
    protected void handleCommand(Map<String, String> command) {
        String cmd = cmd(command);
        String id = id(command);

        if ("ping".equals(cmd)) {
            io.respond(id, true, "pong");
            return;
        }

        if ("run".equals(cmd)) {
            String projectPath = command.get("project");
            io.info("run requested: " + projectPath);
            io.respond(id, true, "run stub");
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
