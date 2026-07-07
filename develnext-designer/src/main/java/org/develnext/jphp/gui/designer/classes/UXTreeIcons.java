package org.develnext.jphp.gui.designer.classes;

import javafx.application.Platform;
import javafx.scene.Node;
import javafx.scene.image.Image;
import javafx.scene.image.ImageView;
import org.develnext.jphp.gui.designer.GuiDesignerExtension;
import org.develnext.jphp.gui.designer.editor.tree.DirectoryTreeUtils;
import org.develnext.jphp.gui.designer.editor.tree.TreeSvgIconLoader;
import php.runtime.annotation.Reflection.Namespace;
import php.runtime.annotation.Reflection.Signature;
import php.runtime.env.Environment;
import php.runtime.lang.BaseObject;
import php.runtime.reflection.ClassEntity;

@Namespace(GuiDesignerExtension.NS)
public class UXTreeIcons extends BaseObject {
    public UXTreeIcons(Environment env, ClassEntity clazz) {
        super(env, clazz);
    }

    @Signature
    public static Node load(String name) {
        Image image = TreeSvgIconLoader.load(name);
        if (image == null) {
            image = DirectoryTreeUtils.loadIconImage(name);
        }
        if (image == null) {
            image = DirectoryTreeUtils.loadIconImage("anyType");
        }

        return toView(image);
    }

    @Signature
    public static Node folder() {
        return DirectoryTreeUtils.getFolderIcon(false);
    }

    @Signature
    public static Node folderOpen() {
        return DirectoryTreeUtils.getFolderIcon(true);
    }

    @Signature
    public static void warmUp() {
        String[] icons = {
                "folder", "open", "anyType", "php", "class", "abstractClass", "interface", "trait",
                "module", "uiForm", "application", "ideaProject", "config",
                "projection", "json", "html", "javaScript", "yaml", "css", "xml",
                "text", "properties", "markdown", "sql", "shell", "addFile", "newFolder",
                "docker", "gradle", "groovy", "cpp", "Csharp", "vue", "jupyter", "binaryData",
                "editorConfig", "gitignore", "toml", "csv", "patch", "graphql", "terraform",
                "threadRunning", "stop", "build", "openNewTab"
        };

        Runnable task = () -> {
            for (String name : icons) {
                TreeSvgIconLoader.load(name);
            }
        };

        if (Platform.isFxApplicationThread()) {
            task.run();
        } else {
            Platform.runLater(task);
        }
    }

    @Signature
    public static void setDarkTheme(boolean dark) {
        TreeSvgIconLoader.useDarkTheme = dark;
        DirectoryTreeUtils.clearCache();
    }

    private static ImageView toView(Image image) {
        if (image == null) {
            ImageView empty = new ImageView();
            empty.setFitWidth(16);
            empty.setFitHeight(16);
            return empty;
        }

        ImageView view = new ImageView(image);
        view.setFitWidth(16);
        view.setFitHeight(16);
        view.setPreserveRatio(true);
        return view;
    }
}
