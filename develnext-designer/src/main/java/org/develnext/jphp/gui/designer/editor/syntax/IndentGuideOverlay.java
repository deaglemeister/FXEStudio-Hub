package org.develnext.jphp.gui.designer.editor.syntax;

import javafx.scene.canvas.Canvas;
import javafx.scene.canvas.GraphicsContext;
import javafx.scene.paint.Color;
import javafx.scene.shape.Rectangle;
import org.fxmisc.flowless.VirtualizedScrollPane;

/**
 * Вертикальные «палки» отступов в левом поле редактора (как VS Code).
 */
public class IndentGuideOverlay extends Canvas {
    private static final Color GUIDE_COLOR; // fxe-border-subtle
    private static final Color ACTIVE_GUIDE_COLOR; // fxe-border
    private static final double LEFT_MARGIN = 120.0;

    static {
        GUIDE_COLOR = Color.web("#43454A");
        ACTIVE_GUIDE_COLOR = Color.web("#6F737A");
    }

    private final AbstractCodeArea area;
    private VirtualizedScrollPane<?> scrollPane;

    public IndentGuideOverlay(AbstractCodeArea area) {
        this.area = area;
        setMouseTransparent(true);

        area.textProperty().addListener((obs, o, n) -> redraw());
        widthProperty().addListener((obs, o, n) -> redraw());
        heightProperty().addListener((obs, o, n) -> redraw());
    }

    public void attachScrollPane(VirtualizedScrollPane<?> scrollPane) {
        this.scrollPane = scrollPane;
        scrollPane.estimatedScrollYProperty().addListener((obs, o, n) -> redraw());
        scrollPane.estimatedScrollXProperty().addListener((obs, o, n) -> redraw());
    }

    public void redraw() {
        double w = getWidth();
        double h = getHeight();

        if (w <= 0 || h <= 0) {
            return;
        }

        setClip(new Rectangle(0, 0, LEFT_MARGIN, h));

        GraphicsContext gc = getGraphicsContext2D();
        gc.clearRect(0, 0, w, h);

        int tabSize = Math.max(1, area.getTabSize());
        double fontSize = area.getFontSize() > 0 ? area.getFontSize() : 13.0;
        double charWidth = Math.max(7.0, fontSize * 0.55);
        double lineHeight = area.getLineHeight();
        double scrollY = scrollPane != null ? scrollPane.estimatedScrollYProperty().getValue() : 0;
        double scrollX = scrollPane != null ? scrollPane.estimatedScrollXProperty().getValue() : 0;
        double baseX = 8 - scrollX;

        int paragraphs = area.getParagraphs().size();
        if (paragraphs <= 0 || lineHeight <= 0) {
            return;
        }

        int firstLine = Math.max(0, (int) Math.floor(scrollY / lineHeight));
        int visibleLines = Math.max(1, (int) Math.ceil(h / lineHeight) + 2);
        int lastLine = Math.min(paragraphs - 1, firstLine + visibleLines);

        if (firstLine > lastLine) {
            return;
        }

        int maxIndent = 0;
        int[] indents = new int[lastLine - firstLine + 1];

        for (int line = firstLine; line <= lastLine; line++) {
            int indent = leadingIndentLevel(area.getParagraph(line).toString(), tabSize);
            indents[line - firstLine] = indent;
            maxIndent = Math.max(maxIndent, indent);
        }

        if (maxIndent <= 1) {
            return;
        }

        for (int level = 1; level < maxIndent; level++) {
            for (int line = firstLine; line <= lastLine; line++) {
                int indent = indents[line - firstLine];

                if (indent <= level) {
                    continue;
                }

                double x = baseX + level * tabSize * charWidth;
                double y1 = line * lineHeight - scrollY;
                double y2 = y1 + lineHeight;

                if (y2 < 0 || y1 > h) {
                    continue;
                }

                boolean active = indent == level + 1;
                gc.setStroke(active ? ACTIVE_GUIDE_COLOR : GUIDE_COLOR);
                gc.setLineWidth(active ? 1.2 : 1.0);
                gc.strokeLine(x, y1, x, y2);
            }
        }
    }

    private static int leadingIndentLevel(String line, int tabSize) {
        if (line == null || line.isEmpty()) {
            return 0;
        }

        int spaces = 0;

        for (int i = 0; i < line.length(); i++) {
            char ch = line.charAt(i);

            if (ch == ' ') {
                spaces++;
            } else if (ch == '\t') {
                spaces += tabSize;
            } else {
                break;
            }
        }

        return spaces / tabSize;
    }
}
