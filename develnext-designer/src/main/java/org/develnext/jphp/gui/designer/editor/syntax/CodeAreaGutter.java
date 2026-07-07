package org.develnext.jphp.gui.designer.editor.syntax;

import java.util.*;
import java.util.function.IntFunction;

import javafx.geometry.Insets;
import javafx.scene.Node;
import javafx.scene.control.Label;
import javafx.scene.control.Tooltip;
import javafx.scene.layout.HBox;

import org.fxmisc.richtext.StyledTextArea;
import org.reactfx.collection.LiveList;
import org.reactfx.value.Val;

/**
 * Graphic factory that produces labels containing line numbers.
 * To customize appearance, use {@code .lineno} style class in CSS stylesheets.
 */
public class CodeAreaGutter implements IntFunction<Node> {
    private static final Insets DEFAULT_INSETS = new Insets(0.0, 5.0, 0.0, 5.0);
    private final StyledTextArea<?, ?> area;

    public static CodeAreaGutter get(StyledTextArea<?, ?> area) {
        return get(area, digits -> "%0" + digits + "d");
    }

    public static CodeAreaGutter get(StyledTextArea<?, ?> area, IntFunction<String> format) {
        return new CodeAreaGutter(area, format);
    }

    /**
     * Обработчик клика по цветовому свотчу в gutter.
     */
    public interface ColorClickHandler {
        void onColorClick(int line, CodeAreaGutterNote note);
    }

    private final Val<Integer> nParagraphs;
    private final IntFunction<String> format;
    private final Map<Integer, List<CodeAreaGutterNote>> notes = new HashMap<>();
    private int gutterNoteMax = 1;
    private ColorClickHandler onColorClick;
    private CodeAreaFoldManager foldManager;

    private CodeAreaGutter(StyledTextArea<?, ?> area, IntFunction<String> format) {
        this.area = area;
        nParagraphs = LiveList.sizeOf(area.getParagraphs());
        this.format = format;
    }

    public CodeAreaGutter duplicate() {
        CodeAreaGutter factory = new CodeAreaGutter(area, format);
        factory.notes.putAll(notes);
        factory.gutterNoteMax = gutterNoteMax;
        factory.onColorClick = onColorClick;
        factory.foldManager = foldManager;

        return factory;
    }

    public void setFoldManager(CodeAreaFoldManager foldManager) {
        this.foldManager = foldManager;
    }

    public void setOnColorClick(ColorClickHandler handler) {
        this.onColorClick = handler;
    }

    public void clearNotes() {
        notes.clear();
        gutterNoteMax = 1;
    }

    public void addNote(int line, CodeAreaGutterNote note) {
        List<CodeAreaGutterNote> noteList = notes.get(line);

        if (noteList == null) {
            notes.put(line, noteList = new ArrayList<>());
        }

        for (CodeAreaGutterNote gutterNote : noteList) {
            if (gutterNote.getStyleClass().equals(note.getStyleClass())) {
                gutterNote.hint += "\n" + note.hint;
                return;
            }
        }

        noteList.add(note);

        if (noteList.size() > gutterNoteMax) {
            gutterNoteMax = noteList.size();
        }
    }

    public List<CodeAreaGutterNote> getNotes(int line) {
        List<CodeAreaGutterNote> noteList = notes.get(line);

        if (noteList == null) {
            return Collections.emptyList();
        }

        return Collections.unmodifiableList(noteList);
    }

    @Override
    public Node apply(int idx) {
        int lineNumber = idx + 1;

        List<CodeAreaGutterNote> notes = getNotes(lineNumber);

        Label lineNo = new Label();
        lineNo.setPadding(DEFAULT_INSETS);
        lineNo.getStyleClass().add("lineno");
        lineNo.setText(formatLineNumber(lineNumber));

        HBox box = new HBox();
        box.getStyleClass().add("gutter");
        box.setSpacing(2);

        if (foldManager != null) {
            box.getChildren().add(foldManager.createFoldButton(idx));
        }

        box.getChildren().add(lineNo);

        for (int i = 0; i < gutterNoteMax; i++) {
            CodeAreaGutterNote note = i <= notes.size() - 1 ? notes.get(i) : null;

            Label label = new Label();
            label.getStyleClass().add("note");

            if (note != null) {
                Tooltip tooltip = new Tooltip(note.getHint());
                tooltip.getStyleClass().add("fxe-diagnostic-tooltip");
                label.setTooltip(tooltip);
                label.getStyleClass().addAll(note.getStyleClass());

                if (note.getPreviewColor() != null) {
                    int line = idx + 1;
                    label.getStyleClass().add("color-swatch");
                    label.setStyle("-fx-background-color: " + note.getPreviewColor() + ";");
                    label.setCursor(javafx.scene.Cursor.HAND);
                    label.setOnMouseClicked(e -> {
                        if (onColorClick != null) {
                            onColorClick.onColorClick(line, note);
                        }
                        e.consume();
                    });
                }
            } else {
                label.getStyleClass().add("empty");
            }

            box.getChildren().add(label);
        }

        return box;
    }

    private String formatLineNumber(int x) {
        return String.valueOf(x);
    }
}
