package org.fxe.analyzer;

import org.develnext.jphp.core.syntax.SyntaxAnalyzer;
import org.develnext.jphp.core.tokenizer.Tokenizer;
import php.runtime.env.Context;
import php.runtime.env.Environment;
import php.runtime.lang.exception.BaseError;

import java.io.File;
import java.io.IOException;
import java.util.ArrayList;
import java.util.Collections;
import java.util.List;

/**
 * Локальный синтаксический анализ JPHP/PHP для редактора и helper-процессов.
 */
public final class FxePhpSyntaxAnalyzer {
    public static final class Diagnostic {
        public final int line;
        public final int column;
        public final int length;
        public final String message;
        public final String severity;

        public Diagnostic(int line, int column, int length, String message, String severity) {
            this.line = line;
            this.column = column;
            this.length = length;
            this.message = message;
            this.severity = severity;
        }
    }

    private FxePhpSyntaxAnalyzer() {
    }

    public static List<Diagnostic> analyze(String text, String fileName) {
        if (text == null || text.isEmpty()) {
            return Collections.emptyList();
        }

        Environment env = new Environment();
        String name = fileName == null || fileName.isEmpty() ? "source.php" : fileName;

        try {
            SyntaxAnalyzer analyzer = new SyntaxAnalyzer(
                    env,
                    new Tokenizer(new Context(text, new File(name)))
            );
            analyzer.getTree();
            return Collections.emptyList();
        } catch (IOException e) {
            return singleton(1, 0, 1, "Ошибка чтения: " + e.getMessage(), "error");
        } catch (BaseError e) {
            int line = e.getLine(env).toInteger();
            int column = e.getPosition(env).toInteger();
            String msg = cleanMessage(e.getMessage(env).toString());

            if (line < 1) {
                line = 1;
            }
            if (column < 0) {
                column = 0;
            }

            int[] adjusted = adjustDiagnosticPosition(text, line, column, msg);
            line = adjusted[0];
            column = adjusted[1];

            int length = guessErrorLength(text, line, column, msg);
            return singleton(line, column, length, msg, "error");
        } catch (Throwable e) {
            String msg = e.getMessage();
            if (msg == null || msg.isEmpty()) {
                msg = e.getClass().getSimpleName();
            }
            return singleton(1, 0, 1, msg, "error");
        }
    }

    public static String formatForProtocol(List<Diagnostic> diagnostics) {
        if (diagnostics == null || diagnostics.isEmpty()) {
            return "";
        }

        StringBuilder sb = new StringBuilder();
        for (int i = 0; i < diagnostics.size(); i++) {
            if (i > 0) {
                sb.append('|');
            }
            Diagnostic d = diagnostics.get(i);
            sb.append(d.line).append(':').append(d.column).append(':').append(d.length)
                    .append(':').append(d.severity).append(':').append(escapeField(d.message));
        }
        return sb.toString();
    }

    public static List<Diagnostic> parseFromProtocol(String payload) {
        if (payload == null || payload.isEmpty()) {
            return Collections.emptyList();
        }

        List<Diagnostic> result = new ArrayList<>();
        for (String part : payload.split("\\|")) {
            if (part.isEmpty()) {
                continue;
            }

            String[] fields = part.split(":", 5);
            if (fields.length < 5) {
                continue;
            }

            result.add(new Diagnostic(
                    Integer.parseInt(fields[0]),
                    Integer.parseInt(fields[1]),
                    Integer.parseInt(fields[2]),
                    unescapeField(fields[4]),
                    fields[3]
            ));
        }
        return result;
    }

    public static int toAbsoluteOffset(String text, int line1Based, int column0) {
        if (text == null || text.isEmpty()) {
            return 0;
        }

        int line = Math.max(1, line1Based);
        int col = Math.max(0, column0);
        int currentLine = 1;
        int i = 0;
        int len = text.length();

        while (i < len && currentLine < line) {
            if (text.charAt(i) == '\n') {
                currentLine++;
            }
            i++;
        }

        int offset = i + col;
        return Math.min(offset, len);
    }

    private static List<Diagnostic> singleton(int line, int column, int length, String message, String severity) {
        List<Diagnostic> list = new ArrayList<>(1);
        list.add(new Diagnostic(line, column, length, message, severity));
        return list;
    }

    private static String cleanMessage(String msg) {
        if (msg == null || msg.isEmpty()) {
            return "Синтаксическая ошибка";
        }

        return msg.replaceAll("\\s+at pos \\d+$", "").trim();
    }

    private static int guessErrorLength(String text, int line1Based, int column0, String message) {
        int start = toAbsoluteOffset(text, line1Based, column0);
        if (start >= text.length()) {
            return 1;
        }

        if (message != null && message.contains("';'")) {
            return 1;
        }

        int end = start;
        while (end < text.length()) {
            char c = text.charAt(end);
            if (c == '\n' || c == '\r' || c == ' ' || c == '\t' || c == ';' || c == ')' || c == ']') {
                break;
            }
            end++;
        }

        int length = end - start;
        return length < 1 ? 1 : length;
    }

    /**
     * Сдвигает позицию ошибки на предыдущую строку, если пропущена точка с запятой
     * (типичный случай: ошибка на {@code }}, а {@code ;} отсутствует выше).
     */
    private static int[] adjustDiagnosticPosition(String text, int line1Based, int column0, String message) {
        if (text == null || message == null) {
            return new int[]{line1Based, column0};
        }

        String lower = message.toLowerCase();
        boolean unexpectedBrace = lower.contains("unexpected") && (lower.contains("'") && lower.contains("}"));
        boolean missingSemicolon = lower.contains("';'") || lower.contains("semicolon");

        if (!unexpectedBrace && !missingSemicolon) {
            return new int[]{line1Based, column0};
        }

        int prevLine = line1Based - 1;
        if (prevLine < 1) {
            return new int[]{line1Based, column0};
        }

        int lineStart = toAbsoluteOffset(text, prevLine, 0);
        int lineEnd = toAbsoluteOffset(text, prevLine + 1, 0);
        if (lineEnd > text.length()) {
            lineEnd = text.length();
        }

        String lineText = text.substring(lineStart, lineEnd).replace("\r", "").replace("\n", "");
        String trimmed = lineText.trim();

        if (trimmed.isEmpty() || trimmed.startsWith("//") || trimmed.startsWith("/*") || trimmed.startsWith("*")) {
            return new int[]{line1Based, column0};
        }

        if (trimmed.endsWith(";") || trimmed.endsWith("{") || trimmed.endsWith("}") || trimmed.endsWith(",")) {
            return new int[]{line1Based, column0};
        }

        int col = lineText.length();
        while (col > 0 && Character.isWhitespace(lineText.charAt(col - 1))) {
            col--;
        }

        return new int[]{prevLine, col};
    }

    private static String escapeField(String text) {
        if (text == null) {
            return "";
        }
        return text.replace("\\", "\\\\").replace("|", "\\|").replace(":", "\\:");
    }

    private static String unescapeField(String text) {
        StringBuilder sb = new StringBuilder(text.length());
        for (int i = 0; i < text.length(); i++) {
            char c = text.charAt(i);
            if (c == '\\' && i + 1 < text.length()) {
                sb.append(text.charAt(++i));
            } else {
                sb.append(c);
            }
        }
        return sb.toString();
    }
}
