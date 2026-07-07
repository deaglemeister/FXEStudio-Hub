package org.develnext.jphp.gui.designer.editor.syntax;

import javafx.scene.control.Label;
import javafx.scene.input.MouseButton;

import java.util.*;

/**
 * Сворачивание блоков { } как в VS Code — скрывает строки через paragraph style, текст не меняется.
 */
public class CodeAreaFoldManager {
    public interface FoldChangeListener {
        void onFoldChanged();
    }

    private static final Collection<String> FOLDED_STYLE = Collections.singleton("folded");

    private final AbstractCodeArea area;
    private final Map<Integer, Integer> foldStarts = new LinkedHashMap<>();
    private final Set<Integer> collapsed = new HashSet<>();
    private FoldChangeListener listener;

    public CodeAreaFoldManager(AbstractCodeArea area) {
        this.area = area;
    }

    public void setListener(FoldChangeListener listener) {
        this.listener = listener;
    }

    public void refreshRegions() {
        foldStarts.clear();

        int paragraphs = area.getParagraphs().size();
        for (int i = 0; i < paragraphs; i++) {
            String line = paragraphText(i);
            if (!isFoldHeader(line)) {
                continue;
            }

            int end = findFoldEnd(i);
            if (end > i) {
                foldStarts.put(i, end);
            }
        }

        collapsed.retainAll(foldStarts.keySet());
        applyFoldStyles();
    }

    public boolean isFoldable(int paragraphIndex) {
        return foldStarts.containsKey(paragraphIndex);
    }

    public boolean isCollapsed(int paragraphIndex) {
        return collapsed.contains(paragraphIndex);
    }

    public void toggle(int paragraphIndex) {
        if (!foldStarts.containsKey(paragraphIndex)) {
            return;
        }

        if (collapsed.contains(paragraphIndex)) {
            collapsed.remove(paragraphIndex);
        } else {
            collapsed.add(paragraphIndex);
        }

        applyFoldStyles();
        notifyChanged();
    }

    public Label createFoldButton(int paragraphIndex) {
        Label button = new Label();
        button.getStyleClass().add("fold-toggle");
        button.setMinWidth(12);
        button.setPrefWidth(12);

        if (!isFoldable(paragraphIndex)) {
            button.setText("");
            button.getStyleClass().add("empty");
            return button;
        }

        button.setText(isCollapsed(paragraphIndex) ? "\u25B6" : "\u25BC");
        button.setOnMouseClicked(e -> {
            if (e.getButton() == MouseButton.PRIMARY) {
                toggle(paragraphIndex);
                e.consume();
            }
        });

        return button;
    }

    private void applyFoldStyles() {
        int paragraphs = area.getParagraphs().size();

        for (int p = 0; p < paragraphs; p++) {
            area.setParagraphStyle(p, Collections.emptyList());
        }

        for (Map.Entry<Integer, Integer> entry : foldStarts.entrySet()) {
            int start = entry.getKey();
            int end = entry.getValue();

            if (!collapsed.contains(start)) {
                continue;
            }

            for (int p = start + 1; p <= end; p++) {
                if (p < paragraphs) {
                    area.setParagraphStyle(p, FOLDED_STYLE);
                }
            }
        }
    }

    private void notifyChanged() {
        if (listener != null) {
            listener.onFoldChanged();
        }
    }

    private int findFoldEnd(int startParagraph) {
        int depth = 0;
        int paragraphs = area.getParagraphs().size();

        for (int i = startParagraph; i < paragraphs; i++) {
            String line = paragraphText(i);

            for (int c = 0; c < line.length(); c++) {
                char ch = line.charAt(c);

                if (ch == '{') {
                    depth++;
                } else if (ch == '}') {
                    depth--;
                    if (depth == 0) {
                        return i;
                    }
                }
            }
        }

        return startParagraph;
    }

    private static boolean isFoldHeader(String line) {
        if (line == null) {
            return false;
        }

        String trimmed = line.trim();
        return !trimmed.isEmpty() && trimmed.endsWith("{");
    }

    private String paragraphText(int index) {
        if (index < 0 || index >= area.getParagraphs().size()) {
            return "";
        }

        return area.getParagraph(index).toString();
    }
}
