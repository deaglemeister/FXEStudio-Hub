package org.develnext.jphp.gui.designer.editor.syntax;

public class CodeAreaGutterNote {
    protected String hint;
    protected String styleClass;

    /** CSS-цвет hex-литерала (например "#ffcc00"), null для обычных заметок (ошибка/предупреждение). */
    protected String previewColor;
    protected int colorStart = -1;
    protected int colorEnd = -1;

    public CodeAreaGutterNote(String styleClass, String hint) {
        this.styleClass = styleClass;
        this.hint = hint;
    }

    public CodeAreaGutterNote(String styleClass, String hint, String previewColor, int colorStart, int colorEnd) {
        this.styleClass = styleClass;
        this.hint = hint;
        this.previewColor = previewColor;
        this.colorStart = colorStart;
        this.colorEnd = colorEnd;
    }

    public String getHint() {
        return hint;
    }

    public String getStyleClass() {
        return styleClass;
    }

    public String getPreviewColor() {
        return previewColor;
    }

    public int getColorStart() {
        return colorStart;
    }

    public int getColorEnd() {
        return colorEnd;
    }
}
