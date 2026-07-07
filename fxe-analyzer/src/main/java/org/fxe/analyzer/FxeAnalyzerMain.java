package org.fxe.analyzer;

import org.fxe.process.AbstractFxeHelperMain;
import org.fxe.process.FxeChannel;

import java.util.List;
import java.util.Map;

/**
 * Локальный анализатор PHP/JPHP для IDE (LSP-подобный helper).
 */
public class FxeAnalyzerMain extends AbstractFxeHelperMain {
    public FxeAnalyzerMain() {
        super(FxeChannel.ANALYZER);
    }

    public static void main(String[] args) {
        new FxeAnalyzerMain().run();
    }

    @Override
    protected void handleCommand(Map<String, String> command) {
        String cmd = cmd(command);
        String id = id(command);

        if ("ping".equals(cmd)) {
            io.respond(id, true, "pong");
            return;
        }

        if ("analyze".equals(cmd)) {
            String content = command.get("content");
            String file = command.get("file");
            if (file == null || file.isEmpty()) {
                file = "source.php";
            }

            List<FxePhpSyntaxAnalyzer.Diagnostic> diagnostics =
                    FxePhpSyntaxAnalyzer.analyze(content == null ? "" : content, file);

            io.respond(id, true, FxePhpSyntaxAnalyzer.formatForProtocol(diagnostics));
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
