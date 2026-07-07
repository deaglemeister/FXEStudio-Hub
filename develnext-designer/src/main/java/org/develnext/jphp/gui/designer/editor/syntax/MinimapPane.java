package org.develnext.jphp.gui.designer.editor.syntax;

import javafx.application.Platform;
import javafx.scene.canvas.Canvas;
import javafx.scene.canvas.GraphicsContext;
import javafx.scene.input.MouseButton;
import javafx.scene.paint.Color;
import javafx.scene.text.Font;
import org.develnext.jphp.gui.designer.editor.syntax.impl.CssCodeArea;
import org.develnext.jphp.gui.designer.editor.syntax.impl.PhpCodeArea;
import org.fxmisc.richtext.model.Paragraph;
import org.fxe.analyzer.FxePhpSyntaxAnalyzer;

import java.util.ArrayList;
import java.util.Collection;
import java.util.HashSet;
import java.util.List;
import java.util.Set;

/**
 * VS Code-style minimap: узкая полоса с миниатюрным текстом и маркерами ошибок.
 */
public class MinimapPane extends Canvas {
    private static final double WIDTH = 72;
    private static final Color BG = Color.web("#252729");
    private static final Color VIEWPORT = Color.web("rgba(67, 69, 74, 0.40)");
    private static final Color VIEWPORT_BORDER = Color.web("rgba(67, 69, 74, 0.80)");

    private final AbstractCodeArea area;
    private volatile List<Integer> errorLines = new ArrayList<>();
    private org.fxmisc.flowless.VirtualizedScrollPane<?> scrollPane;

    public MinimapPane(AbstractCodeArea area) {
        super(WIDTH, 200);
        this.area = area;
        setWidth(WIDTH);
        getStyleClass().add("fxe-minimap");

        setOnMousePressed(e -> {
            if (e.getButton() != MouseButton.PRIMARY) {
                return;
            }
            scrollToY(e.getY());
        });

        setOnMouseDragged(e -> {
            if (e.getButton() != MouseButton.PRIMARY) {
                return;
            }
            scrollToY(e.getY());
        });

        area.heightProperty().addListener((obs, o, n) -> scheduleRedraw());
    }

    public void attachScrollPane(org.fxmisc.flowless.VirtualizedScrollPane<?> scrollPane) {
        this.scrollPane = scrollPane;
        scrollPane.estimatedScrollYProperty().addListener((obs, o, n) -> scheduleRedraw());
        scrollPane.heightProperty().addListener((obs, o, n) -> scheduleRedraw());
    }

    private void scheduleRedraw() {
        if (area.getScene() == null) {
            return;
        }

        Platform.runLater(this::redraw);
    }

    private double contentHeight() {
        return Math.max(1, area.getParagraphs().size() * lineHeight());
    }

    public void rebuildFrom(AbstractCodeArea codeArea) {
        Set<Integer> errorLineSet = new HashSet<>();

        if (codeArea instanceof PhpCodeArea) {
            PhpCodeArea php = (PhpCodeArea) codeArea;

            for (FxePhpSyntaxAnalyzer.Diagnostic diagnostic : php.getLastDiagnostics()) {
                errorLineSet.add(diagnostic.line);
            }

            for (FxePhpSyntaxAnalyzer.Diagnostic diagnostic : php.getLastLspDiagnostics()) {
                errorLineSet.add(diagnostic.line);
            }
        } else if (codeArea instanceof CssCodeArea) {
            CssCodeArea css = (CssCodeArea) codeArea;

            for (FxePhpSyntaxAnalyzer.Diagnostic diagnostic : css.getLastDiagnostics()) {
                errorLineSet.add(diagnostic.line);
            }
        }

        List<Integer> errors = new ArrayList<>(errorLineSet);
        java.util.Collections.sort(errors);
        this.errorLines = errors;
        scheduleRedraw();
    }

    private double lineHeight() {
        double h = area.getLineHeight();
        return h > 0 ? h : 18;
    }

    private double viewportHeight() {
        if (scrollPane != null && scrollPane.getHeight() > 0) {
            return scrollPane.getHeight();
        }

        return 200;
    }

    private void scrollToY(double localY) {
        double h = viewportHeight();
        if (h <= 0) {
            return;
        }

        double total = contentHeight();
        double viewport = Math.max(lineHeight(), viewportHeight());
        double target = (localY / h) * total - viewport / 2;
        area.scrollYToPixel(Math.max(0, target));
    }

    public void redraw() {
        if (area.getScene() == null) {
            return;
        }

        double w = getWidth();
        double areaHeight = viewportHeight();
        if (areaHeight <= 0) {
            return;
        }

        double h = areaHeight;

        GraphicsContext gc = getGraphicsContext2D();
        gc.clearRect(0, 0, w, h);
        gc.setFill(BG);
        gc.fillRect(0, 0, w, h);

        int lineCount = area.getParagraphs().size();
        if (lineCount <= 0) {
            return;
        }

        double total = contentHeight();
        double scale = h / total;
        double lh = lineHeight();
        double lineStep = Math.max(1.0, lh * scale);
        double fontSize = Math.max(1.8, Math.min(4.5, lineStep * 0.65));

        gc.setFont(Font.font("Consolas", fontSize));

        int lineNum = 0;
        for (Paragraph<Collection<String>, String, Collection<String>> paragraph : area.getParagraphs()) {
            String text = paragraph.getText();
            double y = lineNum * lineStep;

            if (y <= h + lineStep) {
                gc.setFill(colorForLine(text));
                String preview = text.length() > 80 ? text.substring(0, 80) : text;
                if (!preview.isEmpty()) {
                    gc.fillText(preview.replace('\t', ' '), 1, y + fontSize);
                }
            }

            lineNum++;
        }

        for (int line : errorLines) {
            double y = (line - 1) * lineStep;
            gc.setFill(Color.web("#BC3F3C"));
            gc.fillRect(0, y, w, Math.max(1.0, lineStep * 0.85));
        }

        double scrollY = scrollPane != null ? scrollPane.estimatedScrollYProperty().getValue() : area.getEstimatedScrollY();
        double viewport = Math.max(lh, viewportHeight());
        double vy = scrollY * scale;
        double vh = viewport * scale;

        gc.setFill(VIEWPORT);
        gc.fillRect(0, vy, w, vh);
        gc.setStroke(VIEWPORT_BORDER);
        gc.strokeRect(0.5, vy + 0.5, w - 1, vh);
    }

    private static Color colorForLine(String text) {
        if (text == null || text.isEmpty()) {
            return Color.web("#606366");
        }

        String trimmed = text.trim();

        if (trimmed.startsWith("//") || trimmed.startsWith("/*") || trimmed.startsWith("*")) {
            return Color.web("#808080");
        }

        if (trimmed.contains("\"") || trimmed.contains("'")) {
            return Color.web("#6A8759");
        }

        if (trimmed.contains("function ") || trimmed.startsWith("class ")
                || trimmed.contains("-fx-") || trimmed.startsWith(".")) {
            return Color.web("#FFC66D");
        }

        if (trimmed.contains("$")) {
            return Color.web("#9876AA");
        }

        return Color.web("#A9B7C6");
    }
}
