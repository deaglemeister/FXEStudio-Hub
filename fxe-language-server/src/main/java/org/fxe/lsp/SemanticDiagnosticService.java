package org.fxe.lsp;

import org.fxe.analyzer.FxePhpSyntaxAnalyzer;

import java.util.ArrayList;
import java.util.List;
import java.util.Locale;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

public class SemanticDiagnosticService {
    private static final Pattern THIS_PROP = Pattern.compile("\\$this->([A-Za-z_][A-Za-z0-9_]*)");
    private static final Pattern METHOD_CALL = Pattern.compile("->([A-Za-z_][A-Za-z0-9_]*)\\s*\\(");

    private final ProjectIndexer projectIndexer;

    public SemanticDiagnosticService(ProjectIndexer projectIndexer) {
        this.projectIndexer = projectIndexer;
    }

    public List<FxePhpSyntaxAnalyzer.Diagnostic> analyze(String content, String file) {
        List<FxePhpSyntaxAnalyzer.Diagnostic> result = new ArrayList<>();
        if (content == null || content.isEmpty()) {
            return result;
        }

        SymbolIndex.ClassSymbol current = projectIndexer.getIndex().getClassByFile(file);
        String className = current != null ? current.name : guessClassName(content);

        checkThisProperties(content, className, result);
        checkMethodCalls(content, current, result);

        return result;
    }

    private void checkThisProperties(String content, String className, List<FxePhpSyntaxAnalyzer.Diagnostic> result) {
        if (className == null) {
            return;
        }

        java.util.Set<String> components = projectIndexer.getIndex().getFormComponents(className);
        if (components.isEmpty() && projectIndexer.getIndex().getClass(className) != null) {
            components = projectIndexer.getIndex().getClass(className).properties;
        }

        if (components.isEmpty()) {
            return;
        }

        Matcher matcher = THIS_PROP.matcher(content);
        while (matcher.find()) {
            String prop = matcher.group(1);
            if (!components.contains(prop) && !isMagicProperty(prop)) {
                int line = lineOf(content, matcher.start());
                int col = columnOf(content, matcher.start());
                result.add(new FxePhpSyntaxAnalyzer.Diagnostic(
                        line,
                        col,
                        prop.length(),
                        "Свойство $this->" + prop + " не найдено в форме " + className,
                        "warning"
                ));
            }
        }
    }

    private void checkMethodCalls(String content, SymbolIndex.ClassSymbol current, List<FxePhpSyntaxAnalyzer.Diagnostic> result) {
        if (current == null) {
            return;
        }

        Matcher matcher = METHOD_CALL.matcher(content);
        while (matcher.find()) {
            String method = matcher.group(1);
            if (method.equals("__construct") || method.equals("get") || method.equals("set")) {
                continue;
            }

            if (!current.methods.contains(method) && !isObjectMethod(method)) {
                SymbolIndex.ClassSymbol parent = current.parent != null
                        ? projectIndexer.getIndex().getClass(current.parent) : null;
                if (parent == null || !parent.methods.contains(method)) {
                    int line = lineOf(content, matcher.start() + 2);
                    int col = columnOf(content, matcher.start() + 2);
                    result.add(new FxePhpSyntaxAnalyzer.Diagnostic(
                            line,
                            col,
                            method.length(),
                            "Метод " + method + "() не найден в " + current.name,
                            "warning"
                    ));
                }
            }
        }
    }

    private static String guessClassName(String content) {
        Matcher m = Pattern.compile("(?m)^\\s*class\\s+([A-Za-z_][A-Za-z0-9_]*)").matcher(content);
        return m.find() ? m.group(1) : null;
    }

    private static boolean isMagicProperty(String name) {
        return name.startsWith("__");
    }

    private static boolean isObjectMethod(String name) {
        String n = name.toLowerCase(Locale.ROOT);
        return n.equals("tostring") || n.equals("call") || n.equals("getclass");
    }

    private static int lineOf(String text, int offset) {
        int line = 1;
        for (int i = 0; i < offset && i < text.length(); i++) {
            if (text.charAt(i) == '\n') {
                line++;
            }
        }
        return line;
    }

    private static int columnOf(String text, int offset) {
        int col = 0;
        for (int i = offset - 1; i >= 0; i--) {
            if (text.charAt(i) == '\n') {
                break;
            }
            col++;
        }
        return col;
    }
}
