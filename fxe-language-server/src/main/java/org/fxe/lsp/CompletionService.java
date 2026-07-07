package org.fxe.lsp;

import java.util.ArrayList;
import java.util.Collections;
import java.util.List;
import java.util.Locale;
import java.util.Set;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

public class CompletionService {
    private static final Pattern THIS_PREFIX = Pattern.compile("\\$this->([A-Za-z0-9_]*)$");
    private static final Pattern METHOD_PREFIX = Pattern.compile("->([A-Za-z0-9_]*)$");
    private static final Pattern WORD_PREFIX = Pattern.compile("([A-Za-z_][A-Za-z0-9_]*)$");

    private final ProjectIndexer projectIndexer;

    public CompletionService(ProjectIndexer projectIndexer) {
        this.projectIndexer = projectIndexer;
    }

    public String complete(String content, String file, int line, int column) {
        if (content == null) {
            return "";
        }

        String before = extractBefore(content, line, column);
        List<CompletionItem> items = new ArrayList<>();

        Matcher thisMatcher = THIS_PREFIX.matcher(before);
        if (thisMatcher.find()) {
            String prefix = thisMatcher.group(1).toLowerCase(Locale.ROOT);
            SymbolIndex.ClassSymbol current = projectIndexer.getIndex().getClassByFile(file);
            if (current == null) {
                current = projectIndexer.getIndex().getClass(guessClassName(content));
            }
            if (current != null) {
                Set<String> components = projectIndexer.getIndex().getFormComponents(current.name);
                if (components.isEmpty()) {
                    components = current.properties;
                }
                for (String id : components) {
                    if (prefix.isEmpty() || id.toLowerCase(Locale.ROOT).startsWith(prefix)) {
                        items.add(new CompletionItem(id, "property", id, "Компонент формы"));
                    }
                }
                for (String method : current.methods) {
                    if (prefix.isEmpty() || method.toLowerCase(Locale.ROOT).startsWith(prefix)) {
                        items.add(new CompletionItem(method, "method", method + "()", "Метод " + current.name));
                    }
                }
            }
            return encode(items);
        }

        Matcher methodMatcher = METHOD_PREFIX.matcher(before);
        if (methodMatcher.find()) {
            String prefix = methodMatcher.group(1).toLowerCase(Locale.ROOT);
            for (String method : commonUxMethods()) {
                if (prefix.isEmpty() || method.toLowerCase(Locale.ROOT).startsWith(prefix)) {
                    items.add(new CompletionItem(method, "method", method + "()", "UX control method"));
                }
            }
            return encode(items);
        }

        Matcher wordMatcher = WORD_PREFIX.matcher(before);
        if (wordMatcher.find()) {
            String prefix = wordMatcher.group(1).toLowerCase(Locale.ROOT);
            for (SymbolIndex.ClassSymbol cls : projectIndexer.getIndex().getAllClasses()) {
                if (prefix.isEmpty() || cls.name.toLowerCase(Locale.ROOT).startsWith(prefix)) {
                    items.add(new CompletionItem(cls.name, "class", cls.name, cls.file));
                }
            }
        }

        return encode(items);
    }

    private static List<String> commonUxMethods() {
        return java.util.Arrays.asList(
                "setText", "getText", "show", "hide", "enable", "disable", "on", "off",
                "setEnabled", "setVisible", "requestFocus", "setStyle", "setWidth", "setHeight"
        );
    }

    private static String guessClassName(String content) {
        Matcher m = Pattern.compile("(?m)^\\s*class\\s+([A-Za-z_][A-Za-z0-9_]*)").matcher(content);
        return m.find() ? m.group(1) : null;
    }

    private static String extractBefore(String content, int line, int column) {
        String[] lines = content.split("\n", -1);
        if (line < 1 || line > lines.length) {
            return "";
        }
        String current = lines[line - 1];
        if (column < 0) {
            column = 0;
        }
        if (column > current.length()) {
            column = current.length();
        }
        return current.substring(0, column);
    }

    private static String encode(List<CompletionItem> items) {
        if (items.isEmpty()) {
            return "";
        }
        StringBuilder sb = new StringBuilder();
        for (int i = 0; i < items.size(); i++) {
            if (i > 0) {
                sb.append('|');
            }
            sb.append(items.get(i).encode());
        }
        return sb.toString();
    }

    static class CompletionItem {
        final String label;
        final String kind;
        final String detail;
        final String documentation;

        CompletionItem(String label, String kind, String detail, String documentation) {
            this.label = label;
            this.kind = kind;
            this.detail = detail;
            this.documentation = documentation;
        }

        String encode() {
            return escape(label) + ':' + escape(kind) + ':' + escape(detail) + ':' + escape(documentation);
        }
    }

    public static List<CompletionItem> decode(String payload) {
        if (payload == null || payload.isEmpty()) {
            return Collections.emptyList();
        }
        List<CompletionItem> result = new ArrayList<>();
        for (String part : payload.split("\\|")) {
            String[] fields = part.split(":", 4);
            if (fields.length < 4) {
                continue;
            }
            result.add(new CompletionItem(unescape(fields[0]), unescape(fields[1]), unescape(fields[2]), unescape(fields[3])));
        }
        return result;
    }

    private static String escape(String text) {
        if (text == null) {
            return "";
        }
        return text.replace("\\", "\\\\").replace("|", "\\|").replace(":", "\\:");
    }

    private static String unescape(String text) {
        StringBuilder sb = new StringBuilder();
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
