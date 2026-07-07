package org.fxe.analyzer;

import java.util.ArrayList;
import java.util.Arrays;
import java.util.Collections;
import java.util.HashSet;
import java.util.List;
import java.util.Locale;
import java.util.Set;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

/**
 * Локальный анализ JavaFX CSS: неизвестные -fx- свойства, неверные hex-цвета.
 */
public final class FxeFxCssAnalyzer {
    private static final Pattern PROPERTY = Pattern.compile("(-fx-[a-zA-Z0-9\\-]+)\\s*:");
    private static final Pattern HEX_COLOR = Pattern.compile("#([0-9A-Fa-f]+)");
    private static final Pattern BRACE = Pattern.compile("[{}]");

    private static final Set<String> FX_PROPERTIES = new HashSet<>(Arrays.asList(
            "-fx-background-color", "-fx-background-image", "-fx-background-insets",
            "-fx-background-position", "-fx-background-radius", "-fx-background-repeat",
            "-fx-background-size", "-fx-border-color", "-fx-border-insets", "-fx-border-radius",
            "-fx-border-style", "-fx-border-width", "-fx-effect", "-fx-font-family",
            "-fx-font-size", "-fx-font-style", "-fx-font-weight", "-fx-opacity",
            "-fx-padding", "-fx-text-fill", "-fx-text-alignment", "-fx-text-overrun",
            "-fx-wrap-text", "-fx-cursor", "-fx-fill", "-fx-stroke", "-fx-stroke-width",
            "-fx-stroke-type", "-fx-stroke-dash-array", "-fx-stroke-dash-offset",
            "-fx-stroke-line-cap", "-fx-stroke-line-join", "-fx-stroke-miter-limit",
            "-fx-blend-mode", "-fx-rotate", "-fx-scale-x", "-fx-scale-y", "-fx-scale-z",
            "-fx-translate-x", "-fx-translate-y", "-fx-translate-z", "-fx-min-width",
            "-fx-min-height", "-fx-max-width", "-fx-max-height", "-fx-pref-width",
            "-fx-pref-height", "-fx-alignment", "-fx-content-display", "-fx-graphic-text-gap",
            "-fx-indent", "-fx-label-padding", "-fx-line-spacing", "-fx-spacing",
            "-fx-tab-min-width", "-fx-tab-max-width", "-fx-tab-min-height", "-fx-tab-max-height",
            "-fx-focus-color", "-fx-faint-focus-color", "-fx-highlight-fill",
            "-fx-highlight-text-fill", "-fx-control-inner-background", "-fx-outer-border",
            "-fx-inner-border", "-fx-body-color", "-fx-text-box-border", "-fx-mark-color",
            "-fx-mark-highlight-color", "-fx-accent", "-fx-default-button", "-fx-cancel-button",
            "-fx-image", "-fx-shape", "-fx-position-shape", "-fx-snap-to-pixel",
            "-fx-underline", "-fx-display-caret", "-fx-prompt-text-fill", "-fx-cell-size",
            "-fx-vertical-scrollbar-policy", "-fx-horizontal-scrollbar-policy",
            "-fx-hbar-policy", "-fx-vbar-policy", "-fx-fit-to-width", "-fx-fit-to-height",
            "-fx-fixed-cell-size", "-fx-selection-bar", "-fx-selection-bar-non-focused",
            "-fx-open-tab-animation", "-fx-close-tab-animation", "-fx-tab-disabling-policy",
            "-fx-show-delay", "-fx-show-duration", "-fx-hide-delay", "-fx-hide-duration",
            "-fx-arrow-visible", "-fx-arrow-size", "-fx-arrow-direction", "-fx-tick-label-fill",
            "-fx-tick-length", "-fx-major-tick-unit", "-fx-minor-tick-count", "-fx-block-increment",
            "-fx-unit-increment", "-fx-show-tick-labels", "-fx-show-tick-marks", "-fx-snap-to-ticks",
            "-fx-orientation", "-fx-indeterminate", "-fx-progress-color", "-fx-bar-fill",
            "-fx-track-color", "-fx-box-border", "-fx-text-background-color"
    ));

    private FxeFxCssAnalyzer() {
    }

    public static List<FxePhpSyntaxAnalyzer.Diagnostic> analyze(String text) {
        if (text == null || text.isEmpty()) {
            return Collections.emptyList();
        }

        List<FxePhpSyntaxAnalyzer.Diagnostic> result = new ArrayList<>();
        String[] lines = text.split("\n", -1);

        for (int i = 0; i < lines.length; i++) {
            String line = lines[i];
            int lineNo = i + 1;

            Matcher propMatcher = PROPERTY.matcher(line);
            while (propMatcher.find()) {
                String prop = propMatcher.group(1).toLowerCase(Locale.ROOT);

                if (!FX_PROPERTIES.contains(prop)) {
                    String suggestion = suggestProperty(prop);
                    String message = suggestion != null
                            ? "Неизвестное свойство, возможно: " + suggestion
                            : "Неизвестное JavaFX CSS свойство: " + prop;

                    result.add(new FxePhpSyntaxAnalyzer.Diagnostic(
                            lineNo,
                            propMatcher.start(1),
                            propMatcher.group(1).length(),
                            message,
                            "error"
                    ));
                }
            }

            Matcher hexMatcher = HEX_COLOR.matcher(line);
            while (hexMatcher.find()) {
                String hex = hexMatcher.group(1);
                int len = hex.length();

                if (len != 3 && len != 4 && len != 6 && len != 8) {
                    result.add(new FxePhpSyntaxAnalyzer.Diagnostic(
                            lineNo,
                            hexMatcher.start(),
                            hexMatcher.end() - hexMatcher.start(),
                            "Неверный hex-цвет: #" + hex + " (нужно 3, 4, 6 или 8 символов)",
                            "error"
                    ));
                }
            }
        }

        int depth = 0;
        for (int i = 0; i < lines.length; i++) {
            Matcher braceMatcher = BRACE.matcher(lines[i]);
            while (braceMatcher.find()) {
                if (braceMatcher.group().equals("{")) {
                    depth++;
                } else {
                    depth--;
                    if (depth < 0) {
                        result.add(new FxePhpSyntaxAnalyzer.Diagnostic(
                                i + 1,
                                braceMatcher.start(),
                                1,
                                "Лишняя закрывающая скобка '}'",
                                "error"
                        ));
                        depth = 0;
                    }
                }
            }
        }

        if (depth > 0) {
            result.add(new FxePhpSyntaxAnalyzer.Diagnostic(
                    lines.length,
                    0,
                    1,
                    "Не хватает закрывающей скобки '}'",
                    "error"
            ));
        }

        return result;
    }

    private static String suggestProperty(String prop) {
        if (prop.contains("backgound")) {
            return prop.replace("backgound", "background");
        }
        if (prop.contains("forground")) {
            return prop.replace("forground", "foreground");
        }

        String best = null;
        int bestDist = 4;

        for (String known : FX_PROPERTIES) {
            int dist = levenshtein(prop, known);
            if (dist < bestDist) {
                bestDist = dist;
                best = known;
            }
        }

        return best;
    }

    private static int levenshtein(String a, String b) {
        int[][] dp = new int[a.length() + 1][b.length() + 1];

        for (int i = 0; i <= a.length(); i++) {
            dp[i][0] = i;
        }
        for (int j = 0; j <= b.length(); j++) {
            dp[0][j] = j;
        }

        for (int i = 1; i <= a.length(); i++) {
            for (int j = 1; j <= b.length(); j++) {
                int cost = a.charAt(i - 1) == b.charAt(j - 1) ? 0 : 1;
                dp[i][j] = Math.min(
                        Math.min(dp[i - 1][j] + 1, dp[i][j - 1] + 1),
                        dp[i - 1][j - 1] + cost
                );
            }
        }

        return dp[a.length()][b.length()];
    }
}
