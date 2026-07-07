package org.fxe.lsp;

import org.fxe.analyzer.FxePhpSyntaxAnalyzer;

import java.util.List;

public class JphpParserService {
    public List<FxePhpSyntaxAnalyzer.Diagnostic> parse(String content, String file) {
        return FxePhpSyntaxAnalyzer.analyze(content, file);
    }
}
