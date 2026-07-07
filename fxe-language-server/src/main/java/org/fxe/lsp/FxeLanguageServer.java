package org.fxe.lsp;

import org.fxe.process.FxeProcessIo;

import java.util.Map;

public class FxeLanguageServer {
    private final FxeProcessIo io;
    private final ProjectIndexer projectIndexer;
    private final JphpParserService parserService;
    private final SemanticDiagnosticService semanticService;
    private final DiagnosticService diagnosticService;
    private final CompletionService completionService;
    private final DefinitionService definitionService;

    public FxeLanguageServer(FxeProcessIo io) {
        this.io = io;
        this.projectIndexer = new ProjectIndexer(io);
        this.parserService = new JphpParserService();
        this.semanticService = new SemanticDiagnosticService(projectIndexer);
        this.diagnosticService = new DiagnosticService(parserService, semanticService);
        this.completionService = new CompletionService(projectIndexer);
        this.definitionService = new DefinitionService(projectIndexer);
    }

    public void handle(Map<String, String> command) {
        String cmd = command.get("cmd");
        String id = command.get("id");
        if (id == null) {
            id = "";
        }

        if ("ping".equals(cmd)) {
            io.respond(id, true, "pong");
            return;
        }

        if ("index".equals(cmd)) {
            String project = command.get("project");
            io.info("index: " + project);
            int classes = projectIndexer.scan(project == null ? "" : project);
            io.respond(id, true, "classes:" + classes);
            return;
        }

        if ("analyze".equals(cmd) || "diagnostics".equals(cmd)) {
            String content = command.get("content");
            String file = command.get("file");
            if (file == null || file.isEmpty()) {
                file = "source.php";
            }
            String payload = diagnosticService.analyze(content == null ? "" : content, file);
            io.respond(id, true, payload);
            return;
        }

        if ("complete".equals(cmd)) {
            String content = command.get("content");
            String file = command.get("file");
            String line = command.get("line");
            String column = command.get("column");
            int ln = line == null ? 1 : Integer.parseInt(line);
            int col = column == null ? 0 : Integer.parseInt(column);
            String payload = completionService.complete(
                    content == null ? "" : content,
                    file == null ? "source.php" : file,
                    ln,
                    col
            );
            io.respond(id, true, payload);
            return;
        }

        if ("definition".equals(cmd)) {
            String content = command.get("content");
            String file = command.get("file");
            String line = command.get("line");
            String column = command.get("column");
            int ln = line == null ? 1 : Integer.parseInt(line);
            int col = column == null ? 0 : Integer.parseInt(column);
            String payload = definitionService.findDefinition(
                    content == null ? "" : content,
                    file,
                    ln,
                    col
            );
            io.respond(id, true, payload);
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
