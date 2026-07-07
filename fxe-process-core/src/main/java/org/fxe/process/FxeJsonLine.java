package org.fxe.process;

import java.util.LinkedHashMap;
import java.util.Map;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

/**
 * Минимальный JSON-lines без внешних зависимостей (Java 8).
 */
public final class FxeJsonLine {
    private static final Pattern FIELD = Pattern.compile("\"([^\"]+)\"\\s*:\\s*(\"((?:\\\\.|[^\"\\\\])*)\"|(-?\\d+)|(true|false|null))");

    private FxeJsonLine() {
    }

    public static String encode(Map<String, Object> fields) {
        StringBuilder sb = new StringBuilder("{");
        boolean first = true;

        for (Map.Entry<String, Object> entry : fields.entrySet()) {
            if (!first) {
                sb.append(',');
            }
            first = false;
            sb.append('"').append(escape(entry.getKey())).append("\":");
            appendValue(sb, entry.getValue());
        }

        sb.append('}');
        return sb.toString();
    }

    public static Map<String, String> decode(String line) {
        Map<String, String> result = new LinkedHashMap<>();
        Matcher matcher = FIELD.matcher(line);

        while (matcher.find()) {
            String key = matcher.group(1);
            String quoted = matcher.group(3);
            String number = matcher.group(4);
            String boolOrNull = matcher.group(5);

            if (quoted != null) {
                result.put(key, unescape(quoted));
            } else if (number != null) {
                result.put(key, number);
            } else {
                result.put(key, boolOrNull);
            }
        }

        return result;
    }

    private static void appendValue(StringBuilder sb, Object value) {
        if (value == null) {
            sb.append("null");
        } else if (value instanceof Boolean) {
            sb.append(value);
        } else if (value instanceof Number) {
            sb.append(value);
        } else {
            sb.append('"').append(escape(String.valueOf(value))).append('"');
        }
    }

    private static String escape(String text) {
        if (text == null) {
            return "";
        }

        return text
                .replace("\\", "\\\\")
                .replace("\"", "\\\"")
                .replace("\n", "\\n")
                .replace("\r", "\\r")
                .replace("\t", "\\t");
    }

    private static String unescape(String text) {
        StringBuilder sb = new StringBuilder(text.length());

        for (int i = 0; i < text.length(); i++) {
            char c = text.charAt(i);
            if (c == '\\' && i + 1 < text.length()) {
                char next = text.charAt(++i);
                switch (next) {
                    case 'n': sb.append('\n'); break;
                    case 'r': sb.append('\r'); break;
                    case 't': sb.append('\t'); break;
                    case '"': sb.append('"'); break;
                    case '\\': sb.append('\\'); break;
                    default: sb.append(next); break;
                }
            } else {
                sb.append(c);
            }
        }

        return sb.toString();
    }
}
