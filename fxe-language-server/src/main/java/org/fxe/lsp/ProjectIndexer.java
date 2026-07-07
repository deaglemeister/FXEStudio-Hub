package org.fxe.lsp;

import org.fxe.process.FxeProcessIo;

import java.io.File;
import java.util.ArrayList;
import java.util.List;

public class ProjectIndexer {
    private final FxeProcessIo io;
    private final SymbolIndex index = new SymbolIndex();
    private String projectPath = "";

    public ProjectIndexer(FxeProcessIo io) {
        this.io = io;
    }

    public SymbolIndex getIndex() {
        return index;
    }

    public String getProjectPath() {
        return projectPath;
    }

    public int scan(String projectPath) {
        index.clear();
        this.projectPath = projectPath == null ? "" : projectPath;

        if (this.projectPath.isEmpty()) {
            io.warn("index: empty project path");
            return 0;
        }

        File root = new File(this.projectPath);
        if (!root.isDirectory()) {
            io.warn("index: not a directory: " + this.projectPath);
            return 0;
        }

        List<File> phpFiles = new ArrayList<>();
        collectPhpFiles(root, phpFiles, 0);

        int total = phpFiles.size();
        io.progress("index", 0, Math.max(total, 1));

        int current = 0;
        for (File file : phpFiles) {
            indexFile(file);
            current++;
            io.progress("index", current, Math.max(total, 1));
        }

        io.info("index done, classes: " + index.classCount());
        return index.classCount();
    }

    private void collectPhpFiles(File dir, List<File> out, int depth) {
        if (depth > 14) {
            return;
        }

        File[] files = dir.listFiles();
        if (files == null) {
            return;
        }

        for (File file : files) {
            if (file.isDirectory()) {
                String name = file.getName();
                if ("vendor".equals(name) || ".git".equals(name) || "build".equals(name) || ".dn".equals(name)) {
                    continue;
                }
                collectPhpFiles(file, out, depth + 1);
            } else if (file.getName().endsWith(".php")) {
                out.add(file);
            }
        }
    }

    private void indexFile(File file) {
        SymbolIndex.ClassSymbol symbol = PhpSourceScanner.scanFile(file);
        if (symbol == null) {
            return;
        }

        index.addClass(symbol);

        if (symbol.name.endsWith("Form") || symbol.name.contains("Form")) {
            for (String prop : symbol.properties) {
                index.addFormComponent(symbol.name, prop);
            }
        }
    }
}
