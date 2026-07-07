package org.fxe.lsp;

import org.fxe.process.AbstractFxeHelperMain;
import org.fxe.process.FxeChannel;

/**
 * Entry point for local Language Server (DevelNext / JPHP).
 */
public class FxeLanguageServerMain extends AbstractFxeHelperMain {
    private final FxeLanguageServer server;

    public FxeLanguageServerMain() {
        super(FxeChannel.LSP);
        this.server = new FxeLanguageServer(io);
    }

    public static void main(String[] args) {
        new FxeLanguageServerMain().run();
    }

    @Override
    protected void handleCommand(java.util.Map<String, String> command) {
        server.handle(command);
    }
}
