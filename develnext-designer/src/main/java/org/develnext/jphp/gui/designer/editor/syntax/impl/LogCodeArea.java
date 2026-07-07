package org.develnext.jphp.gui.designer.editor.syntax.impl;

import javafx.scene.input.MouseEvent;
import org.develnext.jphp.gui.designer.editor.syntax.AbstractCodeArea;
import org.fxmisc.richtext.model.StyleSpansBuilder;

import java.util.Collection;
import java.util.Collections;
import java.util.List;

public class LogCodeArea extends AbstractCodeArea {

    private static final List<String> DEFAULT = Collections.singletonList("log-c-default");
    private static final List<String> TRACE = Collections.singletonList("log-c-trace");
    private static final List<String> DEBUG = Collections.singletonList("log-c-debug");
    private static final List<String> INFO = Collections.singletonList("log-c-info");
    private static final List<String> WARN = Collections.singletonList("log-c-warn");
    private static final List<String> ERROR = Collections.singletonList("log-c-red");
    private static final List<String> GREEN = Collections.singletonList("log-c-green");

    public LogCodeArea() {
        super();
        clearGutterOnHighlight = false;
        setShowGutter(false);
        setEditable(false);
        setFocusTraversable(false);
        getStyleClass().add("fxe-monaco-editor");
        getStyleClass().add("fxe-log-console-area");

        setStylesheet(AbstractCodeArea.class.getResource("LogCodeArea.css").toExternalForm());

        addEventFilter(MouseEvent.MOUSE_PRESSED, e -> {
            if (getText().isEmpty()) {
                e.consume();
                return;
            }

            double lineHeight = (getFontSize() > 0 ? getFontSize() : 13) * 1.35;
            double textHeight = Math.max(lineHeight, getParagraphs().size() * lineHeight);

            if (e.getY() > textHeight + 2) {
                e.consume();
            }
        });
    }

    @Override
    protected boolean isAutoHighlightEnabled() {
        return false;
    }

    /**
     * Безопасное добавление в лог-консоль: вставка в конец без гонки с async-подсветкой.
     */
    public void appendLogText(String text, String styleClass) {
        if (text == null || text.isEmpty()) {
            return;
        }

        int start = getLength();
        replaceText(start, start, text);
        int end = getLength();

        if (end <= start) {
            return;
        }

        List<String> style = resolveStyle(styleClass, text);

        try {
            setStyle(start, end, style);
        } catch (IndexOutOfBoundsException | IllegalArgumentException ignored) {
            requestRehighlight();
        }
    }

    private static List<String> resolveStyle(String styleClass, String text) {
        if (styleClass != null && !styleClass.trim().isEmpty()) {
            return Collections.singletonList(styleClass.trim());
        }

        return styleForLine(text);
    }

    @Override
    protected void computeHighlighting(StyleSpansBuilder<Collection<String>> spansBuilder, String text) {
        if (text == null || text.isEmpty()) {
            return;
        }

        int lineStart = 0;

        for (int i = 0; i <= text.length(); i++) {
            if (i == text.length() || text.charAt(i) == '\n') {
                String line = text.substring(lineStart, i);
                int segmentLen = i - lineStart;

                if (i < text.length()) {
                    segmentLen++;
                }

                if (segmentLen > 0) {
                    spansBuilder.add(styleForLine(line), segmentLen);
                }

                lineStart = i + 1;
            }
        }
    }

    private static List<String> styleForLine(String line) {
        if (line == null || line.isEmpty()) {
            return DEFAULT;
        }

        String trimmed = line.trim();

        if (trimmed.contains("успешно") || trimmed.contains("завершена")) {
            return GREEN;
        }

        if (trimmed.startsWith("[ERROR]") || trimmed.startsWith("ERROR [") || trimmed.startsWith("Fatal error")) {
            return ERROR;
        }

        if (trimmed.startsWith("[WARN]") || trimmed.startsWith("[WARNING]") || trimmed.startsWith("WARN [")) {
            return WARN;
        }

        if (trimmed.startsWith("[INFO]") || trimmed.startsWith("INFO [") || trimmed.startsWith("[LIVE]")) {
            return INFO;
        }

        if (trimmed.startsWith("[DEBUG]") || trimmed.startsWith("DEBUG [")) {
            return DEBUG;
        }

        if (trimmed.startsWith("[TRACE]") || trimmed.startsWith("TRACE [")) {
            return TRACE;
        }

        if (trimmed.startsWith(":")) {
            return DEBUG;
        }

        return DEFAULT;
    }
}
