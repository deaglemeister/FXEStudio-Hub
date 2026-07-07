package org.develnext.jphp.gui.designer.editor.tree;

import javafx.scene.Node;
import javafx.scene.image.Image;
import javafx.scene.image.ImageView;

import java.io.File;
import java.util.HashMap;
import java.util.Map;

public class DirectoryTreeUtils {
    private static final Map<String, String> extToSvg = new HashMap<>();
    private static final Map<String, Image> iconCache = new HashMap<>();

    private static Image folderIcon;
    private static Image folderOpenIcon;
    private static Image fileAnyIcon;

    static {
        putExt("image", "jpg", "jpeg", "png", "gif", "ico", "bmp", "tiff", "tif", "psd", "eps", "cdr", "webp", "svg");
        putExt("archive", "zip", "jar", "rar", "7z", "tar", "gz", "bz2", "war", "iso", "cab", "tgz", "xpi");
        putExt("json", "json");
        putExt("jsonSchema", "schema");
        putExt("yaml", "yaml", "yml");
        putExt("toml", "toml");
        putExt("javaScript", "js", "mjs", "cjs");
        putExt("html", "html", "htm");
        putExt("xhtml", "xhtml");
        putExt("java", "java", "class");
        putExt("php", "php", "phb");
        putExt("projection", "dnproject");
        putExt("debug", "debug");
        putExt("css", "css", "less", "scss", "sass");
        putExt("xml", "xml", "fxml", "axml");
        putExt("text", "txt", "log", "rst");
        putExt("markdown", "md", "markdown", "mdown");
        putExt("properties", "ini", "conf", "properties", "cfg");
        putExt("sql", "sql");
        putExt("shell", "sh", "bat", "cmd", "ps1");
        putExt("csv", "csv");
        putExt("docker", "dockerfile");
        putExt("gradle", "gradle", "kts");
        putExt("graphql", "graphql", "gql");
        putExt("patch", "patch", "diff");
        putExt("groovy", "groovy");
        putExt("cpp", "cpp", "cc", "cxx", "h", "hpp", "c");
        putExt("Csharp", "cs");
        putExt("swiftLang", "swift");
        putExt("terraform", "tf");
        putExt("vue", "vue");
        putExt("jupyter", "ipynb");
        putExt("binaryData", "bin", "dat", "dll", "so", "exe", "db", "sqlite");
        putExt("font", "ttf", "otf", "woff", "woff2");
        putExt("regexp", "regexp");
    }

    public static void clearCache() {
        iconCache.clear();
        folderIcon = null;
        folderOpenIcon = null;
        fileAnyIcon = null;
    }

    public static Node getFolderIcon(boolean expanded) {
        ensureBaseIcons();
        return new ImageView(expanded ? folderOpenIcon : folderIcon);
    }

    public static Node getIconOfFile(File file, boolean expanded) {
        if (file.isDirectory()) {
            return getFolderIcon(expanded);
        }

        if (expanded) {
            return null;
        }

        String name = file.getName();
        if (".gitignore".equalsIgnoreCase(name)) {
            return new ImageView(icon("gitignore", "file"));
        }

        if (".editorconfig".equalsIgnoreCase(name)) {
            return new ImageView(icon("editorConfig", "file"));
        }

        if ("dockerfile".equalsIgnoreCase(name)) {
            return new ImageView(icon("docker", "file"));
        }

        int index = name.lastIndexOf('.');
        if (index == -1) {
            ensureBaseIcons();
            return new ImageView(fileAnyIcon);
        }

        String ext = name.substring(index + 1).toLowerCase();
        String svgName = extToSvg.get(ext);
        if (svgName != null) {
            return new ImageView(icon(svgName, "file"));
        }

        ensureBaseIcons();
        return new ImageView(fileAnyIcon);
    }

    public static Node getIconByName(String svgName) {
        Image icon = icon(svgName, "file");
        return icon == null ? null : new ImageView(icon);
    }

    public static Image loadIconImage(String svgName) {
        return icon(svgName, "file");
    }

    private static void ensureBaseIcons() {
        if (folderIcon == null) {
            folderIcon = icon("folder", "folder");
            folderOpenIcon = icon("open", "folder-open");
            fileAnyIcon = icon("anyType", "file");
        }
    }

    private static void putExt(String svgName, String... extensions) {
        for (String ext : extensions) {
            extToSvg.put(ext, svgName);
        }
    }

    private static Image icon(String svgName, String pngFallback) {
        Image cached = iconCache.get(svgName);
        if (cached != null) {
            return cached;
        }

        Image loaded = loadIcon(svgName, pngFallback);
        if (loaded != null) {
            iconCache.put(svgName, loaded);
        }
        return loaded;
    }

    private static Image loadIcon(String svgName, String pngFallback) {
        Image svgIcon = TreeSvgIconLoader.load(svgName);
        if (svgIcon != null) {
            return svgIcon;
        }

        return new Image("/org/develnext/jphp/gui/designer/editor/tree/" + pngFallback + ".png");
    }
}
