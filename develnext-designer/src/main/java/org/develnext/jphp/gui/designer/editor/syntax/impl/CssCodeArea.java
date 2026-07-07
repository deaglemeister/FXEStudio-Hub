package org.develnext.jphp.gui.designer.editor.syntax.impl;

import javafx.application.Platform;
import javafx.event.ActionEvent;
import org.develnext.jphp.gui.designer.editor.syntax.AbstractCodeArea;
import org.develnext.jphp.gui.designer.editor.syntax.CodeAreaGutterNote;
import org.develnext.jphp.gui.designer.editor.syntax.CodeAreaInlineColorOverlay;
import org.develnext.jphp.gui.designer.editor.syntax.FxeEditorSpanBuilder;
import org.develnext.jphp.gui.designer.editor.syntax.hotkey.*;
import org.develnext.lexer.regex.RegexToken;
import org.develnext.lexer.regex.css.FxCssRegexLexer;
import org.fxe.analyzer.FxeFxCssAnalyzer;
import org.fxe.analyzer.FxePhpSyntaxAnalyzer;
import org.fxmisc.richtext.model.StyleSpansBuilder;

import java.util.*;
import java.util.concurrent.Executors;
import java.util.concurrent.ScheduledExecutorService;
import java.util.concurrent.ScheduledFuture;
import java.util.concurrent.TimeUnit;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

public class CssCodeArea extends AbstractCodeArea {
    private static final ScheduledExecutorService SYNTAX_EXECUTOR = Executors.newSingleThreadScheduledExecutor(r -> {
        Thread t = new Thread(r, "fxe-css-syntax");
        t.setDaemon(true);
        return t;
    });

    private static final Pattern HEX_COLOR_PATTERN = Pattern.compile(
            "#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{4}|[0-9A-Fa-f]{6}|[0-9A-Fa-f]{8})(?![0-9A-Fa-f])"
    );

    protected final FxCssRegexLexer cssRegexLexer = new FxCssRegexLexer();

    private ScheduledFuture<?> pendingSyntaxCheck;
    private volatile List<FxePhpSyntaxAnalyzer.Diagnostic> lastDiagnostics = Collections.emptyList();
    private volatile String lastCheckedText = "";
    private volatile List<ColorMatch> lastColorMatches = Collections.emptyList();

    private static final class ColorMatch {
        final int line;
        final int start;
        final int end;
        final String hex;

        ColorMatch(int line, int start, int end, String hex) {
            this.line = line;
            this.start = start;
            this.end = end;
            this.hex = hex;
        }
    }

    public CssCodeArea() {
        super();
        clearGutterOnHighlight = false;
        getStyleClass().add("fxe-monaco-editor");

        registerHotkey(new AddTabsHotkey());
        registerHotkey(new RemoveTabsHotkey());
        registerHotkey(new DuplicateSelectionHotkey());
        registerHotkey(new AutoSpaceEnterHotkey());
        registerHotkey(new AutoBracketsHotkey());
        registerHotkey(new BackspaceHotkey());

        setStylesheet(AbstractCodeArea.class.getResource("CssCodeArea.css").toExternalForm());

        getGutter().setOnColorClick((line, note) -> {
            try {
                selectRange(note.getColorStart(), note.getColorEnd());
            } catch (IndexOutOfBoundsException ignored) {
            }

            if (getOnColorPick() != null) {
                getOnColorPick().handle(new ActionEvent(this, this));
            }
        });
    }

    @Override
    protected void computeHighlighting(StyleSpansBuilder<Collection<String>> spansBuilder, String text) {
        if (text == null || text.isEmpty()) {
            updateInlineColorMatches(Collections.emptyList());
            return;
        }

        FxeEditorSpanBuilder editorSpans = new FxeEditorSpanBuilder(text.length());
        List<ColorMatch> colorMatches = new ArrayList<>();

        cssRegexLexer.setContent(text);

        RegexToken token;
        while ((token = cssRegexLexer.nextToken()) != null) {
            int start = token.getStart();
            int end = token.getEnd();

            if (end > start) {
                editorSpans.addStyle(start, end, styleForToken(token.getCode()));
            }
        }

        Matcher colorMatcher = HEX_COLOR_PATTERN.matcher(text);
        while (colorMatcher.find()) {
            int absStart = colorMatcher.start();
            int absEnd = colorMatcher.end();
            colorMatches.add(new ColorMatch(lineOf(text, absStart), absStart, absEnd, colorMatcher.group()));
        }

        addPartialHexAtCaret(text, colorMatches);

        lastColorMatches = colorMatches;

        List<CodeAreaInlineColorOverlay.Match> inlineMatches = new ArrayList<>();
        for (ColorMatch colorMatch : colorMatches) {
            inlineMatches.add(new CodeAreaInlineColorOverlay.Match(
                    colorMatch.start, colorMatch.end, colorMatch.hex
            ));
        }
        updateInlineColorMatches(inlineMatches);

        for (FxePhpSyntaxAnalyzer.Diagnostic diagnostic : lastDiagnostics) {
            editorSpans.addDiagnostic(diagnostic, text);
        }

        editorSpans.write(spansBuilder);
        scheduleSyntaxCheck(text);
    }

    private void addPartialHexAtCaret(String text, List<ColorMatch> colorMatches) {
        int caret = getCaretPosition();

        if (caret <= 1 || caret > text.length()) {
            return;
        }

        int end = caret;
        int start = end;

        while (start > 0) {
            char ch = text.charAt(start - 1);

            if ((ch >= '0' && ch <= '9') || (ch >= 'a' && ch <= 'f') || (ch >= 'A' && ch <= 'F')) {
                start--;
            } else if (ch == '#' && start > 0) {
                start--;
                break;
            } else {
                break;
            }
        }

        if (start >= end || start < 0 || text.charAt(start) != '#') {
            return;
        }

        String candidate = text.substring(start, end);

        if (!candidate.matches("#[0-9A-Fa-f]{3,8}")) {
            return;
        }

        for (ColorMatch existing : colorMatches) {
            if (existing.start == start && existing.end == end) {
                return;
            }
        }

        colorMatches.add(new ColorMatch(lineOf(text, start), start, end, candidate));
    }

    private static Collection<String> styleForToken(String code) {
        if (code == null) {
            return Collections.emptyList();
        }

        switch (code.toUpperCase(Locale.ROOT)) {
            case "SELECTOR":
                return Collections.singletonList("selector");
            case "ATTRIBUTE":
                return Collections.singletonList("attribute");
            case "COLOR":
                return Collections.singletonList("color");
            case "STRING":
                return Collections.singletonList("string");
            case "NUMBER":
                return Collections.singletonList("number");
            case "COMMENT":
                return Collections.singletonList("comment");
            case "KEYWORD":
                return Collections.singletonList("keyword");
            case "PSEUDO-CLASS":
                return Collections.singletonList("pseudo-class");
            case "BRACE":
            case "BRACKET":
            case "PAREN":
            case "CONTROL":
                return Collections.singletonList("control");
            default:
                return Collections.emptyList();
        }
    }

    private void scheduleSyntaxCheck(String text) {
        if (text.isEmpty()) {
            lastDiagnostics = Collections.emptyList();
            lastCheckedText = "";
            Platform.runLater(() -> {
                getGutter().clearNotes();
                refreshGutter();
            });
            return;
        }

        if (text.equals(lastCheckedText)) {
            return;
        }

        ScheduledFuture<?> pending = pendingSyntaxCheck;
        if (pending != null) {
            pending.cancel(false);
        }

        pendingSyntaxCheck = SYNTAX_EXECUTOR.schedule(() -> {
            List<FxePhpSyntaxAnalyzer.Diagnostic> diagnostics = FxeFxCssAnalyzer.analyze(text);

            Platform.runLater(() -> {
                if (!text.equals(getText())) {
                    return;
                }

                lastCheckedText = text;
                lastDiagnostics = diagnostics;
                refreshAllGutterNotes(text);
                requestRehighlight();
            });
        }, 250, TimeUnit.MILLISECONDS);
    }

    private void refreshAllGutterNotes(String text) {
        getGutter().clearNotes();
        refreshGutter();
    }

    private static int lineOf(String text, int offset) {
        int line = 1;
        int limit = Math.min(offset, text.length());

        for (int i = 0; i < limit; i++) {
            if (text.charAt(i) == '\n') {
                line++;
            }
        }

        return line;
    }

    private static int clampLine(int line, String text) {
        int lines = 1;
        for (int i = 0; i < text.length(); i++) {
            if (text.charAt(i) == '\n') {
                lines++;
            }
        }

        if (line < 1) {
            return 1;
        }
        if (line > lines) {
            return lines;
        }
        return line;
    }

    public List<FxePhpSyntaxAnalyzer.Diagnostic> getLastDiagnostics() {
        return lastDiagnostics;
    }
}
