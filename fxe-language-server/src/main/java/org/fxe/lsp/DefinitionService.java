package org.fxe.lsp;

import java.util.regex.Matcher;
import java.util.regex.Pattern;

public class DefinitionService {
    private final ProjectIndexer projectIndexer;

    public DefinitionService(ProjectIndexer projectIndexer) {
        this.projectIndexer = projectIndexer;
    }

    public String findDefinition(String content, String file, int line, int column) {
        String word = wordAt(content, line, column);
        if (word == null || word.isEmpty()) {
            return "";
        }

        SymbolIndex.ClassSymbol symbol = projectIndexer.getIndex().getClass(word);
        if (symbol != null) {
            return symbol.file + ":1:0";
        }

        for (SymbolIndex.ClassSymbol cls : projectIndexer.getIndex().getAllClasses()) {
            if (cls.methods.contains(word) || cls.properties.contains(word)) {
                if (file != null && cls.file.equalsIgnoreCase(file.replace('/', '\\'))) {
                    return findMemberLine(cls, word, content);
                }
            }
        }

        for (SymbolIndex.ClassSymbol cls : projectIndexer.getIndex().getAllClasses()) {
            if (cls.methods.contains(word)) {
                return cls.file + ":1:0";
            }
        }

        return "";
    }

    private static String findMemberLine(SymbolIndex.ClassSymbol cls, String word, String content) {
        Pattern p = Pattern.compile("(?m)^\\s*(?:public|protected|private|function)\\s+.*\\b" + Pattern.quote(word) + "\\b");
        Matcher m = p.matcher(content);
        if (m.find()) {
            int ln = lineOf(content, m.start());
            return cls.file + ":" + ln + ":0";
        }
        return cls.file + ":1:0";
    }

    private static String wordAt(String content, int line, int column) {
        String[] lines = content.split("\n", -1);
        if (line < 1 || line > lines.length) {
            return "";
        }
        String current = lines[line - 1];
        if (column > current.length()) {
            column = current.length();
        }
        int start = column;
        while (start > 0 && isWordChar(current.charAt(start - 1))) {
            start--;
        }
        int end = column;
        while (end < current.length() && isWordChar(current.charAt(end))) {
            end++;
        }
        return current.substring(start, end);
    }

    private static boolean isWordChar(char c) {
        return Character.isLetterOrDigit(c) || c == '_';
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
}
