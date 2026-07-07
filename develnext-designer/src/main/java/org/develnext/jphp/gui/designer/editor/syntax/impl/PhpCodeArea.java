package org.develnext.jphp.gui.designer.editor.syntax.impl;

import javafx.application.Platform;
import org.develnext.jphp.core.tokenizer.TokenMeta;
import org.develnext.jphp.core.tokenizer.Tokenizer;
import org.develnext.jphp.core.tokenizer.token.*;
import org.develnext.jphp.core.tokenizer.token.Token;
import org.develnext.jphp.core.tokenizer.token.expr.BraceExprToken;
import org.develnext.jphp.core.tokenizer.token.expr.CommaToken;
import org.develnext.jphp.core.tokenizer.token.expr.OperatorExprToken;
import org.develnext.jphp.core.tokenizer.token.expr.operator.*;
import org.develnext.jphp.core.tokenizer.token.expr.value.*;
import org.develnext.jphp.core.tokenizer.token.stmt.ImplementsStmtToken;
import org.develnext.jphp.core.tokenizer.token.stmt.LambdaStmtToken;
import org.develnext.jphp.core.tokenizer.token.stmt.StmtToken;
import org.develnext.jphp.gui.designer.editor.syntax.AbstractCodeArea;
import org.develnext.jphp.gui.designer.editor.syntax.CodeAreaGutterNote;
import org.develnext.jphp.gui.designer.editor.syntax.CodeAreaInlineColorOverlay;
import org.develnext.jphp.gui.designer.editor.syntax.FxeEditorSpanBuilder;
import org.develnext.jphp.gui.designer.editor.syntax.hotkey.*;
import org.fxmisc.richtext.model.StyleSpansBuilder;
import org.fxe.analyzer.FxePhpSyntaxAnalyzer;
import php.runtime.env.Context;

import javafx.event.ActionEvent;

import java.io.IOException;
import java.util.*;
import java.util.concurrent.Executors;
import java.util.concurrent.ScheduledExecutorService;
import java.util.concurrent.ScheduledFuture;
import java.util.concurrent.TimeUnit;
import java.util.regex.Matcher;
import java.util.regex.Pattern;


public class PhpCodeArea extends AbstractCodeArea {
    public static final List<String> SEMICOLON = Collections.singletonList("semicolon");
    public static final List<String> CONTROL = Collections.singletonList("control");
    public static final List<String> COMMENT = Collections.singletonList("comment");
    public static final List<String> PHPDOC = Collections.singletonList("phpdoc");
    public static final List<String> STRING = Collections.singletonList("string");
    public static final List<String> NUMBER = Collections.singletonList("number");
    public static final List<String> VARIABLE = Collections.singletonList("variable");
    public static final List<String> KEYWORD = Collections.singletonList("keyword");
    public static final List<String> TYPE = Collections.singletonList("type");
    public static final List<String> OPERATOR = Collections.singletonList("operator");

    public static final Map<Class<? extends Token>, List<String>> tokenStyles = new HashMap<Class<? extends Token>, List<String>>(){{
        put(SemicolonToken.class, SEMICOLON);

        put(ColonToken.class, CONTROL);
        put(CommaToken.class, CONTROL);
        put(BraceExprToken.class, CONTROL);

        put(CommentToken.class, COMMENT);

        put(StringExprToken.class, STRING);

        put(IntegerExprToken.class, NUMBER);
        put(DoubleExprToken.class, NUMBER);

        put(VariableExprToken.class, VARIABLE);

        put(StmtToken.class, KEYWORD);
        put(BooleanExprToken.class, KEYWORD);
        put(NullExprToken.class, KEYWORD);
        put(NewExprToken.class, KEYWORD);
        put(SelfExprToken.class, KEYWORD);
        put(StaticExprToken.class, KEYWORD);
        put(ParentExprToken.class, KEYWORD);
        put(EmptyExprToken.class, KEYWORD);
        put(IssetExprToken.class, KEYWORD);
        put(DieExprToken.class, KEYWORD);
        put(UnsetExprToken.class, KEYWORD);
        put(InstanceofExprToken.class, KEYWORD);
        put(CloneExprToken.class, KEYWORD);
        put(BooleanAnd2ExprToken.class, KEYWORD);
        put(BooleanOr2ExprToken.class, KEYWORD);
        put(BooleanXorExprToken.class, KEYWORD);
        put(OpenTagToken.class, KEYWORD);
        put(ImplementsStmtToken.class, KEYWORD);
        put(LambdaStmtToken.class, KEYWORD);
        put(IncludeExprToken.class, KEYWORD);
        put(IncludeOnceExprToken.class, KEYWORD);
        put(RequireExprToken.class, KEYWORD);
        put(RequireOnceExprToken.class, KEYWORD);

        put(OperatorExprToken.class, OPERATOR);
    }};

    private static final Set<String> TYPE_KEYWORDS = new HashSet<>(Arrays.asList(
            "bool", "boolean", "int", "integer", "float", "double", "string", "array", "object",
            "callable", "iterable", "void", "mixed", "never", "null"
    ));

    private static final ScheduledExecutorService SYNTAX_EXECUTOR = Executors.newSingleThreadScheduledExecutor(r -> {
        Thread t = new Thread(r, "fxe-php-syntax");
        t.setDaemon(true);
        return t;
    });

    public static final List<String> FIND_MATCH = Collections.singletonList("find-match");
    public static final List<String> OCCURRENCE = Collections.singletonList("occurrence");
    public static final List<String> OCCURRENCE_CURRENT = Collections.singletonList("occurrence-current");
    public static final List<String> BRACKET_MATCH = Collections.singletonList("bracket-match");

    private ScheduledFuture<?> pendingSyntaxCheck;
    private volatile List<FxePhpSyntaxAnalyzer.Diagnostic> lastDiagnostics = Collections.emptyList();
    private volatile List<FxePhpSyntaxAnalyzer.Diagnostic> lastLspDiagnostics = Collections.emptyList();
    private volatile String lastCheckedText = "";
    private volatile List<int[]> findMatches = Collections.emptyList();
    private volatile int lastCaretPosition = 0;
    private volatile List<ColorMatch> lastColorMatches = Collections.emptyList();

    private static final Pattern HEX_COLOR_PATTERN = Pattern.compile("#([0-9A-Fa-f]{6}|[0-9A-Fa-f]{3})(?![0-9A-Fa-f])");

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

    public PhpCodeArea() {
        super();
        clearGutterOnHighlight = false;
        getStyleClass().add("fxe-monaco-editor");

        registerHotkey(new AddTabsHotkey());
        registerHotkey(new RemoveTabsHotkey());
        registerHotkey(new DuplicateSelectionHotkey());
        registerHotkey(new AutoSpaceEnterHotkey());
        registerHotkey(new AutoBracketsHotkey());
        registerHotkey(new BackspaceHotkey());

        setStylesheet(AbstractCodeArea.class.getResource("PhpCodeArea.css").toExternalForm());

        caretPositionProperty().addListener((obs, oldValue, newValue) -> {
            lastCaretPosition = newValue == null ? 0 : newValue;
        });

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

    private static Collection<String> getStyleOfToken(Token token, String text, int startIndex) {
        List<String> result;

        Class<?> cls = token.getClass();
        boolean first = true;

        do {
            result = tokenStyles.get(cls);

            cls = cls.getSuperclass();

            if (result != null && !first) {
                tokenStyles.put(token.getClass(), result);
            }

            if (!Token.class.isAssignableFrom(cls)) {
                break;
            }

            first = false;
        } while (result == null);

        if (result == null) {
            if (token instanceof NameToken) {
                String word = token.getWord().toLowerCase();
                if ("array".equals(word)) {
                    return KEYWORD;
                }
                if (TYPE_KEYWORDS.contains(word)) {
                    return TYPE;
                }
            }
        }

        if (token instanceof CommentToken && text != null) {
            int from = Math.max(0, startIndex);
            if (from + 3 <= text.length() && text.startsWith("/**", from)) {
                return PHPDOC;
            }
        }

        return result == null ? Collections.emptyList() : result;
    }

    @Override
    protected void computeHighlighting(StyleSpansBuilder<Collection<String>> spansBuilder, String text) {
        if (text == null || text.isEmpty()) {
            return;
        }

        FxeEditorSpanBuilder editorSpans = new FxeEditorSpanBuilder(text.length());

        Tokenizer tokenizer;
        try {
            tokenizer = new Tokenizer(new Context(text));
        } catch (IOException e) {
            return;
        }

        List<int[]> braceSpans = new ArrayList<>();
        List<ColorMatch> colorMatches = new ArrayList<>();

        Token token;
        while ((token = tokenizer.nextToken()) != null) {
            TokenMeta meta = token.getMeta();
            int startIndex = meta.getStartIndex();
            int endIndex = meta.getEndIndex();

            if (endIndex > startIndex) {
                editorSpans.addStyle(startIndex, endIndex, getStyleOfToken(token, text, startIndex));
            }

            if (token instanceof BraceExprToken) {
                BraceExprToken brace = (BraceExprToken) token;
                braceSpans.add(new int[]{startIndex, endIndex, brace.isOpened() ? 1 : 0});
            }

            if (token instanceof StringExprToken && endIndex > startIndex) {
                Matcher matcher = HEX_COLOR_PATTERN.matcher(text.substring(startIndex, endIndex));

                if (matcher.find()) {
                    int absStart = startIndex + matcher.start();
                    int absEnd = startIndex + matcher.end();
                    colorMatches.add(new ColorMatch(lineOf(text, absStart), absStart, absEnd, matcher.group()));
                }
            }
        }

        lastColorMatches = colorMatches;

        List<CodeAreaInlineColorOverlay.Match> inlineMatches = new ArrayList<>();
        for (ColorMatch colorMatch : colorMatches) {
            inlineMatches.add(new CodeAreaInlineColorOverlay.Match(
                    colorMatch.start, colorMatch.end, colorMatch.hex
            ));
        }
        updateInlineColorMatches(inlineMatches);

        for (FxePhpSyntaxAnalyzer.Diagnostic diagnostic : mergedDiagnostics()) {
            editorSpans.addDiagnostic(diagnostic, text);
        }

        for (int[] range : findMatches) {
            editorSpans.addStyle(range[0], range[1], FIND_MATCH);
        }

        // Подсветка слова под кареткой и скобок отключена — слишком тяжёлая и мешает чтению.
        editorSpans.write(spansBuilder);
        scheduleSyntaxCheck(text);
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
            List<FxePhpSyntaxAnalyzer.Diagnostic> diagnostics =
                    FxePhpSyntaxAnalyzer.analyze(text, "source.php");

            Platform.runLater(() -> {
                if (!text.equals(getText())) {
                    return;
                }

                lastCheckedText = text;
                lastDiagnostics = diagnostics;
                refreshAllGutterNotes(text);
                requestRehighlight();
            });
        }, 700, TimeUnit.MILLISECONDS);
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

    public void applyLspDiagnostics(String payload) {
        List<FxePhpSyntaxAnalyzer.Diagnostic> parsed =
                FxePhpSyntaxAnalyzer.parseFromProtocol(payload == null ? "" : payload);

        Platform.runLater(() -> {
            lastLspDiagnostics = parsed;
            refreshAllGutterNotes(getText());
            requestRehighlight();
        });
    }

    public void applyFindMatches(String payload) {
        List<int[]> parsed = new ArrayList<>();

        if (payload != null && !payload.isEmpty()) {
            for (String part : payload.split("\\|")) {
                if (part.isEmpty()) {
                    continue;
                }

                String[] fields = part.split(":", 2);
                if (fields.length < 2) {
                    continue;
                }

                try {
                    parsed.add(new int[]{Integer.parseInt(fields[0]), Integer.parseInt(fields[1])});
                } catch (NumberFormatException ignored) {
                }
            }
        }

        Platform.runLater(() -> {
            findMatches = parsed;
            requestRehighlight();
        });
    }

    private List<FxePhpSyntaxAnalyzer.Diagnostic> mergedDiagnostics() {
        if (lastLspDiagnostics.isEmpty()) {
            return lastDiagnostics;
        }
        if (lastDiagnostics.isEmpty()) {
            return lastLspDiagnostics;
        }

        List<FxePhpSyntaxAnalyzer.Diagnostic> merged = new ArrayList<>(
                lastDiagnostics.size() + lastLspDiagnostics.size()
        );
        merged.addAll(lastDiagnostics);
        merged.addAll(lastLspDiagnostics);
        return merged;
    }

    private void refreshAllGutterNotes(String text) {
        getGutter().clearNotes();
        refreshGutter();
    }

    public List<FxePhpSyntaxAnalyzer.Diagnostic> getLastDiagnostics() {
        return lastDiagnostics;
    }

    public List<FxePhpSyntaxAnalyzer.Diagnostic> getLastLspDiagnostics() {
        return lastLspDiagnostics;
    }

    public String getLastCheckedText() {
        return lastCheckedText;
    }
}
