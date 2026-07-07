package org.develnext.jphp.gui.designer.editor.syntax;

import org.fxmisc.richtext.model.StyleSpansBuilder;
import org.fxe.analyzer.FxePhpSyntaxAnalyzer;

import java.util.ArrayList;
import java.util.Collection;
import java.util.Collections;
import java.util.HashSet;
import java.util.List;
import java.util.Set;

/**
 * Character-level span builder for Monaco-style diagnostic underlines.
 */
public final class FxeEditorSpanBuilder {
  private final List<Set<String>> charStyles = new ArrayList<>();

  public FxeEditorSpanBuilder(int length) {
    for (int i = 0; i < length; i++) {
      charStyles.add(new HashSet<String>());
    }
  }

  public int length() {
    return charStyles.size();
  }

  public void addStyle(int from, int to, Collection<String> styles) {
    if (styles == null || styles.isEmpty()) {
      return;
    }

    int start = Math.max(0, from);
    int end = Math.min(charStyles.size(), to);

    for (int i = start; i < end; i++) {
      charStyles.get(i).addAll(styles);
    }
  }

  public void addDiagnostic(FxePhpSyntaxAnalyzer.Diagnostic diagnostic, String text) {
    if (diagnostic == null || text == null || text.isEmpty()) {
      return;
    }

    int start = FxePhpSyntaxAnalyzer.toAbsoluteOffset(text, diagnostic.line, diagnostic.column);
    int len = Math.max(1, diagnostic.length);

    if (start < 0 || start >= text.length()) {
      return;
    }

    if (start + len > text.length()) {
      len = text.length() - start;
    }

    String style = "warning".equals(diagnostic.severity) ? "syntax-warning" : "syntax-error";

    for (int i = start; i < start + len; i++) {
      charStyles.get(i).add(style);
    }
  }

  public void write(StyleSpansBuilder<Collection<String>> spansBuilder) {
    if (charStyles.isEmpty()) {
      return;
    }

    List<String> current = toList(charStyles.get(0));
    int runStart = 0;

    for (int i = 1; i <= charStyles.size(); i++) {
      List<String> next = i < charStyles.size() ? toList(charStyles.get(i)) : null;

      if (next == null || !current.equals(next)) {
        spansBuilder.add(current, i - runStart);

        if (next != null) {
          current = next;
          runStart = i;
        }
      }
    }
  }

  private static List<String> toList(Set<String> styles) {
    if (styles == null || styles.isEmpty()) {
      return Collections.emptyList();
    }

    List<String> list = new ArrayList<>(styles);
    Collections.sort(list);
    return Collections.unmodifiableList(list);
  }
}
