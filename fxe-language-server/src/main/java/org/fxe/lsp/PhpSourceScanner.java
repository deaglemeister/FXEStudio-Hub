package org.fxe.lsp;

import java.io.BufferedReader;
import java.io.File;
import java.io.FileInputStream;
import java.io.InputStreamReader;
import java.nio.charset.StandardCharsets;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

public final class PhpSourceScanner {
    private static final Pattern CLASS = Pattern.compile("(?m)^\\s*(?:abstract\\s+|final\\s+)?class\\s+([A-Za-z_][A-Za-z0-9_]*)");
    private static final Pattern EXTENDS = Pattern.compile("\\bextends\\s+([A-Za-z_][A-Za-z0-9_]*)");
    private static final Pattern METHOD = Pattern.compile("(?m)^\\s*(?:public|protected|private|static)\\s+function\\s+([A-Za-z_][A-Za-z0-9_]*)");
    private static final Pattern PROPERTY = Pattern.compile("(?m)^\\s*(?:public|protected|private)\\s+\\$([A-Za-z_][A-Za-z0-9_]*)");
    private static final Pattern USE_CLASS = Pattern.compile("(?m)^\\s*use\\s+([A-Za-z_\\\\][A-Za-z0-9_\\\\]*)");

    private PhpSourceScanner() {
    }

    public static SymbolIndex.ClassSymbol scanFile(File file) {
        if (file == null || !file.isFile()) {
            return null;
        }

        String content = read(file);
        if (content.isEmpty()) {
            return null;
        }

        Matcher classMatcher = CLASS.matcher(content);
        if (!classMatcher.find()) {
            return null;
        }

        String className = classMatcher.group(1);
        SymbolIndex.ClassSymbol symbol = new SymbolIndex.ClassSymbol(className, file.getAbsolutePath());

        Matcher extendsMatcher = EXTENDS.matcher(content.substring(classMatcher.start(), Math.min(content.length(), classMatcher.start() + 400)));
        if (extendsMatcher.find()) {
            symbol.parent = extendsMatcher.group(1);
        }

        Matcher methodMatcher = METHOD.matcher(content);
        while (methodMatcher.find()) {
            symbol.methods.add(methodMatcher.group(1));
        }

        Matcher propMatcher = PROPERTY.matcher(content);
        while (propMatcher.find()) {
            symbol.properties.add(propMatcher.group(1));
        }

        return symbol;
    }

    public static String read(File file) {
        StringBuilder sb = new StringBuilder();
        try (BufferedReader reader = new BufferedReader(new InputStreamReader(new FileInputStream(file), StandardCharsets.UTF_8))) {
            String line;
            while ((line = reader.readLine()) != null) {
                sb.append(line).append('\n');
            }
        } catch (Exception e) {
            return "";
        }
        return sb.toString();
    }
}
