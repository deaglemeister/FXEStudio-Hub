package org.fxe.lsp;

import org.fxe.analyzer.FxePhpSyntaxAnalyzer;

import java.util.ArrayList;
import java.util.List;

public class DiagnosticService {
    private final JphpParserService parserService;
    private final SemanticDiagnosticService semanticService;

    public DiagnosticService(JphpParserService parserService, SemanticDiagnosticService semanticService) {
        this.parserService = parserService;
        this.semanticService = semanticService;
    }

    public String analyze(String content, String file) {
        List<FxePhpSyntaxAnalyzer.Diagnostic> all = new ArrayList<>();
        all.addAll(parserService.parse(content, file));
        all.addAll(semanticService.analyze(content, file));
        return FxePhpSyntaxAnalyzer.formatForProtocol(all);
    }
}
