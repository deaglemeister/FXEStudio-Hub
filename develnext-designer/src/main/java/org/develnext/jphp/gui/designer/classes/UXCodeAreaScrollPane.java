package org.develnext.jphp.gui.designer.classes;

import javafx.scene.layout.HBox;
import javafx.scene.layout.Priority;
import javafx.scene.layout.Region;
import javafx.scene.layout.StackPane;
import org.develnext.jphp.ext.javafx.classes.layout.UXRegion;
import org.develnext.jphp.gui.designer.GuiDesignerExtension;
import org.develnext.jphp.gui.designer.editor.syntax.AbstractCodeArea;
import org.develnext.jphp.gui.designer.editor.syntax.CodeAreaInlineColorOverlay;
import org.develnext.jphp.gui.designer.editor.syntax.IndentGuideOverlay;
import org.develnext.jphp.gui.designer.editor.syntax.MinimapPane;
import org.fxmisc.flowless.VirtualizedScrollPane;
import org.fxmisc.richtext.StyledTextArea;
import php.runtime.annotation.Reflection.Getter;
import php.runtime.annotation.Reflection.Namespace;
import php.runtime.annotation.Reflection.NotWrapper;
import php.runtime.annotation.Reflection.Setter;
import php.runtime.annotation.Reflection.Signature;
import php.runtime.env.Environment;
import php.runtime.reflection.ClassEntity;

@NotWrapper
@Namespace(GuiDesignerExtension.NS)
public class UXCodeAreaScrollPane extends UXRegion<Region> {
    private VirtualizedScrollPane<? extends StyledTextArea> scrollPane;

    public UXCodeAreaScrollPane(Environment env, Region wrappedObject) {
        super(env, wrappedObject);
    }

    public UXCodeAreaScrollPane(Environment env, ClassEntity clazz) {
        super(env, clazz);
    }

    @Signature
    public void __construct(Object codeArea) {
        if (codeArea instanceof AbstractCodeArea) {
            AbstractCodeArea area = (AbstractCodeArea) codeArea;
            scrollPane = new VirtualizedScrollPane<>(area);
            boolean showMinimap = !(area instanceof org.develnext.jphp.gui.designer.editor.syntax.impl.LogCodeArea);

            IndentGuideOverlay guides = new IndentGuideOverlay(area);
            guides.widthProperty().bind(scrollPane.widthProperty());
            guides.heightProperty().bind(scrollPane.heightProperty());
            guides.attachScrollPane(scrollPane);
            area.setIndentGuides(guides);

            CodeAreaInlineColorOverlay inlineColors = new CodeAreaInlineColorOverlay(area);
            inlineColors.attachScrollPane(scrollPane);
            area.setInlineColorOverlay(inlineColors);

            StackPane editorHost = new StackPane(scrollPane, guides, inlineColors);

            if (showMinimap) {
                MinimapPane minimap = new MinimapPane(area);
                area.setMinimap(minimap);
                area.setScrollPane(scrollPane);
                minimap.attachScrollPane(scrollPane);

                StackPane minimapHost = new StackPane(minimap);
                minimapHost.setMinWidth(72);
                minimapHost.setPrefWidth(72);
                minimapHost.setMaxWidth(72);
                minimap.heightProperty().bind(minimapHost.heightProperty());
                minimapHost.prefHeightProperty().bind(editorHost.heightProperty());
                HBox.setHgrow(minimapHost, Priority.NEVER);

                HBox root = new HBox(editorHost, minimapHost);
                HBox.setHgrow(editorHost, Priority.ALWAYS);
                root.setFillHeight(true);
                __wrappedObject = root;
            } else {
                area.setScrollPane(scrollPane);
                __wrappedObject = editorHost;
            }
            return;
        }

        if (codeArea instanceof StyledTextArea) {
            scrollPane = new VirtualizedScrollPane<>((StyledTextArea) codeArea);
            __wrappedObject = scrollPane;
            return;
        }

        throw new IllegalArgumentException("Invalid text area");
    }

    @Getter
    public double getScrollX() {
        return scrollPane.estimatedScrollXProperty().getValue();
    }

    @Setter
    public void setScrollX(double value) {
        scrollPane.estimatedScrollXProperty().setValue(value);
    }

    @Getter
    public double getScrollY() {
        return scrollPane.estimatedScrollYProperty().getValue();
    }

    @Setter
    public void setScrollY(double value) {
        scrollPane.estimatedScrollYProperty().setValue(value);
    }
}
