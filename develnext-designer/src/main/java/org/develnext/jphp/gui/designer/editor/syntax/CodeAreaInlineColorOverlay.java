package org.develnext.jphp.gui.designer.editor.syntax;

import javafx.application.Platform;
import javafx.event.ActionEvent;
import javafx.geometry.Bounds;
import javafx.geometry.Point2D;
import javafx.scene.layout.Pane;
import javafx.scene.layout.Region;

import java.util.ArrayList;
import java.util.Collections;
import java.util.List;
import java.util.Optional;

/**
 * Цветовые квадратики прямо в строке кода после #hex (как VS Code).
 */
public class CodeAreaInlineColorOverlay extends Pane {
    public static final class Match {
        public final int start;
        public final int end;
        public final String cssColor;

        public Match(int start, int end, String cssColor) {
            this.start = start;
            this.end = end;
            this.cssColor = cssColor;
        }
    }

    private final AbstractCodeArea area;
    private volatile List<Match> matches = Collections.emptyList();

    public CodeAreaInlineColorOverlay(AbstractCodeArea area) {
        this.area = area;
        setMouseTransparent(true);
        setPickOnBounds(false);

        area.textProperty().addListener((obs, o, n) -> redraw());
        area.caretPositionProperty().addListener((obs, o, n) -> redraw());
    }

    public void attachScrollPane(org.fxmisc.flowless.VirtualizedScrollPane<?> scrollPane) {
        scrollPane.estimatedScrollYProperty().addListener((obs, o, n) -> redraw());
        scrollPane.estimatedScrollXProperty().addListener((obs, o, n) -> redraw());
    }

    public void setMatches(List<Match> matches) {
        this.matches = matches == null ? Collections.emptyList() : new ArrayList<>(matches);
        Platform.runLater(this::redraw);
    }

    public void redraw() {
        getChildren().clear();

        for (Match match : matches) {
            Optional<Bounds> bounds;

            try {
                bounds = area.getCharacterBoundsOnScreen(match.start, match.end);
            } catch (IndexOutOfBoundsException | IllegalArgumentException e) {
                continue;
            }

            if (!bounds.isPresent()) {
                continue;
            }

            Bounds box = bounds.get();
            Point2D topLeft = screenToLocal(box.getMaxX(), box.getMinY());
            double swatchSize = Math.min(11, Math.max(8, box.getHeight() - 2));

            Region swatch = new Region();
            swatch.getStyleClass().add("inline-color-swatch");
            swatch.setPrefSize(swatchSize, swatchSize);
            swatch.setMinSize(swatchSize, swatchSize);
            swatch.setMaxSize(swatchSize, swatchSize);
            swatch.setStyle(
                    "-fx-background-color: " + match.cssColor + ";" +
                    "-fx-border-color: #8a8a8a;" +
                    "-fx-border-width: 1px;" +
                    "-fx-background-radius: 2px;" +
                    "-fx-border-radius: 2px;"
            );

            swatch.setLayoutX(topLeft.getX() + 4);
            swatch.setLayoutY(topLeft.getY() + Math.max(0, (box.getHeight() - swatchSize) / 2));
            swatch.setCursor(javafx.scene.Cursor.HAND);
            swatch.setOnMouseClicked(e -> {
                try {
                    area.selectRange(match.start, match.end);
                } catch (IndexOutOfBoundsException ignored) {
                }

                if (area.getOnColorPick() != null) {
                    area.getOnColorPick().handle(new ActionEvent(area, area));
                }

                e.consume();
            });

            getChildren().add(swatch);
        }
    }
}
