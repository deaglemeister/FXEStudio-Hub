package org.develnext.jphp.gui.designer.editor.tree;

import javafx.application.Platform;
import javafx.scene.SnapshotParameters;
import javafx.scene.canvas.Canvas;
import javafx.scene.canvas.GraphicsContext;
import javafx.scene.image.Image;
import javafx.scene.image.WritableImage;
import javafx.scene.paint.Color;
import javafx.scene.shape.FillRule;
import javafx.scene.shape.StrokeLineCap;

import java.io.ByteArrayOutputStream;
import java.io.InputStream;
import java.nio.charset.StandardCharsets;
import java.util.HashMap;
import java.util.Locale;
import java.util.Map;
import java.util.concurrent.CountDownLatch;
import java.util.concurrent.TimeUnit;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

/**
 * Растеризует IntelliJ-style SVG-иконки дерева проекта в {@link Image} (16x16).
 */
public final class TreeSvgIconLoader {
    private static final String RESOURCE_PREFIX = "/org/develnext/jphp/gui/designer/editor/tree/ui/tree/";
    private static final int ICON_SIZE = 16;

    private static final Pattern VIEW_BOX = Pattern.compile("viewBox\\s*=\\s*\"([^\"]+)\"", Pattern.CASE_INSENSITIVE);
    private static final Pattern WIDTH = Pattern.compile("width\\s*=\\s*\"([0-9.]+)", Pattern.CASE_INSENSITIVE);
    private static final Pattern HEIGHT = Pattern.compile("height\\s*=\\s*\"([0-9.]+)", Pattern.CASE_INSENSITIVE);
    private static final Pattern PATH_TAG = Pattern.compile("<path\\s+([^>]+?)/?>", Pattern.CASE_INSENSITIVE);
    private static final Pattern RECT_TAG = Pattern.compile("<rect\\s+([^>]+?)/?>", Pattern.CASE_INSENSITIVE);
    private static final Pattern ELLIPSE_TAG = Pattern.compile("<ellipse\\s+([^>]+?)/?>", Pattern.CASE_INSENSITIVE);
    private static final Pattern CIRCLE_TAG = Pattern.compile("<circle\\s+([^>]+?)/?>", Pattern.CASE_INSENSITIVE);
    private static final Pattern ATTR = Pattern.compile("([\\w:-]+)\\s*=\\s*\"([^\"]*)\"");

    private static final Map<String, Image> cache = new HashMap<>();

    /** FXE использует тёмную тему по умолчанию. */
    public static boolean useDarkTheme = true;

    private TreeSvgIconLoader() {
    }

    public static Image load(String baseName) {
        String cacheKey = baseName + (useDarkTheme ? "_dark" : "");
        Image cached = cache.get(cacheKey);
        if (cached != null) {
            return cached;
        }

        String svg = readSvg(baseName);
        if (svg == null) {
            return null;
        }

        Image image = renderSvg(svg);
        if (image != null) {
            cache.put(cacheKey, image);
        }
        return image;
    }

    private static String readSvg(String baseName) {
        if (useDarkTheme) {
            String darkPath = RESOURCE_PREFIX + baseName + "_dark.svg";
            String content = readStream(darkPath);
            if (content != null) {
                return content;
            }
        }

        return readStream(RESOURCE_PREFIX + baseName + ".svg");
    }

    private static String readStream(String path) {
        try (InputStream stream = TreeSvgIconLoader.class.getResourceAsStream(path)) {
            if (stream == null) {
                return null;
            }

            byte[] buffer = new byte[4096];
            ByteArrayOutputStream out = new ByteArrayOutputStream();
            int read;
            while ((read = stream.read(buffer)) != -1) {
                out.write(buffer, 0, read);
            }

            if (out.size() == 0) {
                return null;
            }

            return out.toString(StandardCharsets.UTF_8.name());
        } catch (Exception ignored) {
            return null;
        }
    }

    private static Image renderSvg(String svg) {
        if (Platform.isFxApplicationThread()) {
            return renderSvgImpl(svg);
        }

        final CountDownLatch latch = new CountDownLatch(1);
        final Image[] holder = new Image[1];

        Platform.runLater(() -> {
            try {
                holder[0] = renderSvgImpl(svg);
            } finally {
                latch.countDown();
            }
        });

        try {
            latch.await(800, TimeUnit.MILLISECONDS);
        } catch (InterruptedException e) {
            Thread.currentThread().interrupt();
        }

        return holder[0];
    }

    private static Image renderSvgImpl(String svg) {
        // defs/clipPath содержат служебные rect'ы (например белый rect маски) — их рисовать нельзя.
        svg = svg.replaceAll("(?is)<defs>.*?</defs>", "");
        svg = svg.replaceAll("(?is)<clipPath[^>]*>.*?</clipPath>", "");

        double[] size = parseSvgSize(svg);
        double sourceWidth = size[0];
        double sourceHeight = size[1];

        Canvas canvas = new Canvas(ICON_SIZE, ICON_SIZE);
        GraphicsContext gc = canvas.getGraphicsContext2D();
        gc.clearRect(0, 0, ICON_SIZE, ICON_SIZE);

        double scaleX = ICON_SIZE / sourceWidth;
        double scaleY = ICON_SIZE / sourceHeight;
        gc.save();
        gc.scale(scaleX, scaleY);

        drawPaths(gc, svg);
        drawRects(gc, svg);
        drawCircles(gc, svg);
        drawEllipses(gc, svg);

        gc.restore();

        SnapshotParameters params = new SnapshotParameters();
        params.setFill(Color.TRANSPARENT);
        WritableImage image = new WritableImage(ICON_SIZE, ICON_SIZE);
        canvas.snapshot(params, image);
        return image;
    }

    private static double[] parseSvgSize(String svg) {
        Matcher viewBox = VIEW_BOX.matcher(svg);
        if (viewBox.find()) {
            String[] parts = viewBox.group(1).trim().split("\\s+");
            if (parts.length == 4) {
                return new double[]{
                        Math.max(1.0, Double.parseDouble(parts[2])),
                        Math.max(1.0, Double.parseDouble(parts[3]))
                };
            }
        }

        double width = 16.0;
        double height = 16.0;

        Matcher widthMatcher = WIDTH.matcher(svg);
        if (widthMatcher.find()) {
            width = Math.max(1.0, Double.parseDouble(widthMatcher.group(1)));
        }

        Matcher heightMatcher = HEIGHT.matcher(svg);
        if (heightMatcher.find()) {
            height = Math.max(1.0, Double.parseDouble(heightMatcher.group(1)));
        }

        return new double[]{width, height};
    }

    private static void drawPaths(GraphicsContext gc, String svg) {
        Matcher matcher = PATH_TAG.matcher(svg);
        while (matcher.find()) {
            Map<String, String> attrs = parseAttributes(matcher.group(1));
            String d = attrs.get("d");
            if (d == null || d.isEmpty()) {
                continue;
            }

            gc.beginPath();
            gc.appendSVGPath(d);

            String fillRule = attrs.get("fill-rule");
            gc.setFillRule("evenodd".equalsIgnoreCase(fillRule) ? FillRule.EVEN_ODD : FillRule.NON_ZERO);

            Color fill = toColor(attrs.get("fill"));
            if (fill != null) {
                gc.setFill(fill);
                gc.fill();
            }

            Color stroke = toColor(attrs.get("stroke"));
            if (stroke != null) {
                gc.setStroke(stroke);
                if (attrs.containsKey("stroke-width")) {
                    gc.setLineWidth(parseDouble(attrs.get("stroke-width"), 1.0));
                }

                String lineCap = attrs.get("stroke-linecap");
                if (lineCap != null) {
                    switch (lineCap.toUpperCase(Locale.ROOT)) {
                        case "ROUND":
                            gc.setLineCap(StrokeLineCap.ROUND);
                            break;
                        case "SQUARE":
                            gc.setLineCap(StrokeLineCap.SQUARE);
                            break;
                        default:
                            gc.setLineCap(StrokeLineCap.BUTT);
                            break;
                    }
                }

                gc.stroke();
            }
        }
    }

    private static void drawRects(GraphicsContext gc, String svg) {
        Matcher matcher = RECT_TAG.matcher(svg);
        while (matcher.find()) {
            Map<String, String> attrs = parseAttributes(matcher.group(1));

            double x = parseDouble(attrs.get("x"), 0.0);
            double y = parseDouble(attrs.get("y"), 0.0);
            double width = parseDouble(attrs.get("width"), 0.0);
            double height = parseDouble(attrs.get("height"), 0.0);
            if (width <= 0 || height <= 0) {
                continue;
            }

            double rx = parseDouble(attrs.get("rx"), 0.0);
            double ry = parseDouble(attrs.get("ry"), rx);

            Color fill = toColor(attrs.get("fill"));
            Color stroke = toColor(attrs.get("stroke"));

            if (rx > 0 || ry > 0) {
                if (fill != null) {
                    gc.setFill(fill);
                    gc.fillRoundRect(x, y, width, height, rx * 2, ry * 2);
                }
                if (stroke != null) {
                    gc.setStroke(stroke);
                    if (attrs.containsKey("stroke-width")) {
                        gc.setLineWidth(parseDouble(attrs.get("stroke-width"), 1.0));
                    }
                    gc.strokeRoundRect(x, y, width, height, rx * 2, ry * 2);
                }
            } else {
                if (fill != null) {
                    gc.setFill(fill);
                    gc.fillRect(x, y, width, height);
                }
                if (stroke != null) {
                    gc.setStroke(stroke);
                    if (attrs.containsKey("stroke-width")) {
                        gc.setLineWidth(parseDouble(attrs.get("stroke-width"), 1.0));
                    }
                    gc.strokeRect(x, y, width, height);
                }
            }
        }
    }

    private static void drawCircles(GraphicsContext gc, String svg) {
        Matcher matcher = CIRCLE_TAG.matcher(svg);
        while (matcher.find()) {
            Map<String, String> attrs = parseAttributes(matcher.group(1));
            double cx = parseDouble(attrs.get("cx"), 0.0);
            double cy = parseDouble(attrs.get("cy"), 0.0);
            double r = parseDouble(attrs.get("r"), 0.0);
            if (r <= 0) {
                continue;
            }

            Color fill = toColor(attrs.get("fill"));
            Color stroke = toColor(attrs.get("stroke"));

            if (fill != null) {
                gc.setFill(fill);
                gc.fillOval(cx - r, cy - r, r * 2, r * 2);
            }

            if (stroke != null) {
                gc.setStroke(stroke);
                if (attrs.containsKey("stroke-width")) {
                    gc.setLineWidth(parseDouble(attrs.get("stroke-width"), 1.0));
                }
                gc.strokeOval(cx - r, cy - r, r * 2, r * 2);
            }
        }
    }

    private static void drawEllipses(GraphicsContext gc, String svg) {
        Matcher matcher = ELLIPSE_TAG.matcher(svg);
        while (matcher.find()) {
            Map<String, String> attrs = parseAttributes(matcher.group(1));
            Color fill = toColor(attrs.get("fill"));
            if (fill == null) {
                continue;
            }

            double cx = parseDouble(attrs.get("cx"), 0.0);
            double cy = parseDouble(attrs.get("cy"), 0.0);
            double rx = parseDouble(attrs.get("rx"), 0.0);
            double ry = parseDouble(attrs.get("ry"), 0.0);
            if (rx <= 0 || ry <= 0) {
                continue;
            }

            String ellipsePath = String.format(
                    Locale.ROOT,
                    "M %f %f A %f %f 0 1 0 %f %f A %f %f 0 1 0 %f %f Z",
                    cx - rx, cy, rx, ry, cx + rx, cy, rx, ry, cx - rx, cy
            );

            gc.beginPath();
            gc.appendSVGPath(ellipsePath);
            gc.setFill(fill);
            gc.setFillRule(FillRule.NON_ZERO);
            gc.fill();
        }
    }

    private static Map<String, String> parseAttributes(String rawAttrs) {
        Map<String, String> result = new HashMap<>();
        Matcher matcher = ATTR.matcher(rawAttrs);
        while (matcher.find()) {
            result.put(matcher.group(1).toLowerCase(Locale.ROOT), matcher.group(2));
        }
        return result;
    }

    private static Color toColor(String value) {
        if (value == null) {
            return null;
        }

        value = value.trim();
        if (value.isEmpty() || "none".equalsIgnoreCase(value)) {
            return null;
        }

        if (value.startsWith("#")) {
            return Color.web(value);
        }

        return Color.web(value);
    }

    private static double parseDouble(String value, double fallback) {
        if (value == null || value.isEmpty()) {
            return fallback;
        }

        try {
            return Double.parseDouble(value);
        } catch (NumberFormatException e) {
            return fallback;
        }
    }
}
