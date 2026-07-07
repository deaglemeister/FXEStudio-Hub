package org.develnext.jphp.gui.designer.editor.tree;

import javafx.scene.Node;

import java.io.File;
import java.io.FileFilter;
import java.io.IOException;
import java.nio.file.*;
import java.util.*;

public class FileDirectoryTreeSource extends AbstractDirectoryTreeSource {
    public interface ValueCreator {
        DirectoryTreeValue create(String path, File file);
    }

    public interface StructureProvider {
        List<String> listChildPaths(String path);
        DirectoryTreeValue createVirtualValue(String path);
        boolean isVirtualPath(String path);
    }


    private final File directory;
    private final Map<String, DirectoryTreeListener> watchers = new HashMap<>();

    private boolean showHidden = false;
    private List<FileFilter> fileFilters = new ArrayList<>();
    private List<ValueCreator> valueCreators = new ArrayList<>();
    private StructureProvider structureProvider;

    public FileDirectoryTreeSource(File directory) {
        this.directory = directory;
    }

    public boolean isShowHidden() {
        return showHidden;
    }

    public void setShowHidden(boolean showHidden) {
        this.showHidden = showHidden;
    }

    @Override
    public void shutdown() {
        Collection<DirectoryTreeListener> values = new ArrayList<>(watchers.values());

        for (DirectoryTreeListener listener : values) {
            listener.shutdown();
        }
    }

    @Override
    public String rename(String path, String newName) {
        if (isVirtualPath(path)) {
            return null;
        }

        if (path == null || path.isEmpty()) {
            return null;
        }

        String name = new File(path).getName();

        if (name.endsWith(".dnproject")) {
            return null;
        }

        File file = new File(directory, "/" + path);

        if (file.exists()) {
            File newFile = new File(file.getParent(), "/" + newName);
            boolean success = file.renameTo(newFile);

            if (success) {
                return new File(new File(path).getParent(), newName).getPath().replace("\\", "/");
            }
        }

        return null;
    }

    public File getDirectory() {
        return directory;
    }

    public void addFileFilter(FileFilter filter) {
        fileFilters.add(filter);
    }

    public void addValueCreator(ValueCreator creator) {
        valueCreators.add(creator);
    }

    public void setStructureProvider(StructureProvider structureProvider) {
        this.structureProvider = structureProvider;
    }

    private boolean isVirtualPath(String path) {
        if (path == null) {
            return false;
        }

        if (structureProvider != null && structureProvider.isVirtualPath(path)) {
            return true;
        }

        return false;
    }

    private List<String> listVirtualChildPaths(String path) {
        if (structureProvider == null || path == null) {
            return Collections.emptyList();
        }

        List<String> childPaths = structureProvider.listChildPaths(path);

        return childPaths == null ? Collections.<String>emptyList() : childPaths;
    }

    private boolean hasVirtualChildren(String path) {
        return !listVirtualChildPaths(path).isEmpty();
    }

    private boolean isPhpFilePath(String path) {
        return path != null && path.toLowerCase(Locale.ROOT).endsWith(".php");
    }

    private boolean isShowingInTree(File file) {
        if (!showHidden && file.isHidden()) {
            return false;
        }

        for (FileFilter filter : fileFilters) {
            if (!filter.accept(file)) {
                return false;
            }
        }

        return true;
    }

    @Override
    public Object getDragContent(String path) {
        if (isVirtualPath(path)) {
            return null;
        }

        return Collections.singletonList(new File(directory, "/" + path));
    }

    public boolean isEmpty(String path) {
        if (isVirtualPath(path)) {
            return !hasVirtualChildren(path);
        }

        if (isPhpFilePath(path)) {
            return !hasVirtualChildren(path);
        }

        try (DirectoryStream<Path> dirStream = Files.newDirectoryStream(Paths.get(directory.getPath(), path))) {
            for (Path el : dirStream) {
                File file = el.toFile();

                if (isShowingInTree(file)) {
                    return false;
                }
            }

            return true;
        } catch (IOException e) {
            return true;
        }
    }

    public List<DirectoryTreeValue> list(String path) {
        if (isVirtualPath(path)) {
            ArrayList<DirectoryTreeValue> virtualList = new ArrayList<>();

            for (String childPath : listVirtualChildPaths(path)) {
                virtualList.add(createValue(childPath));
            }

            return virtualList;
        }

        File[] files = new File(directory, path).listFiles(this::isShowingInTree);

        ArrayList<DirectoryTreeValue> list = new ArrayList<>();

        if (files != null) {
            for (File file : files) {
                if (file.isDirectory()) {
                    list.add(createValue(path + "/" + file.getName()));
                }
            }

            for (File file : files) {
                if (!file.isDirectory()) {
                    list.add(createValue(path + "/" + file.getName()));
                }
            }
        }

        for (String childPath : listVirtualChildPaths(path)) {
            list.add(createValue(childPath));
        }

        return list;
    }

    @Override
    public DirectoryTreeListener listener(String path) {
        if (isVirtualPath(path)) {
            return null;
        }

        if (isPhpFilePath(path)) {
            return null;
        }

        if (watchers.containsKey(path)) {
            return watchers.get(path);
        }

        try {
            File file = new File(directory, "/" + path);

            if (!file.exists()) {
                return null;
            }

            return new Listener(path);
        } catch (Exception e) {
            return null;
        }
    }

    public DirectoryTreeValue createValue(String path) {
        if (isVirtualPath(path)) {
            if (structureProvider != null) {
                DirectoryTreeValue virtualValue = structureProvider.createVirtualValue(path);

                if (virtualValue != null) {
                    return virtualValue;
                }
            }

            return new DirectoryTreeValue(path, path, path, null, null, false);
        }

        File file = new File(directory, "/" + path);

        if (file.getPath().endsWith(File.separator)) {
            file = new File(file.getPath().substring(0, file.getPath().length() - 1));
        }

        for (ValueCreator valueCreator : valueCreators) {
            DirectoryTreeValue value = valueCreator.create(path, file);

            if (value != null) {
                if (isPhpFilePath(path) && hasVirtualChildren(path)) {
                    value.setFolder(true);
                }

                return value;
            }
        }

        String text = file.getName();
        String code = file.getName();

        Node icon = DirectoryTreeUtils.getIconOfFile(file, false);
        Node expandIcon = DirectoryTreeUtils.getIconOfFile(file, true);

        return new DirectoryTreeValue(path, code, text, icon, expandIcon, file.isDirectory());
    }

    private class Listener extends DirectoryTreeListener {
        private final String path;
        private final Timer timer;

        private Set<String> lastFiles;

        private boolean shutdown;

        public Listener(String path) {
            super(path);
            this.path = path;

            this.timer = new Timer("Listener-" + path);
            this.timer.schedule(new TimerTask() {
                @Override
                public void run() {
                    check();
                }
            }, 0, 1000);

            synchronized (watchers) {
                watchers.put(path, this);
            }
        }

        private void check() {
            File file = new File(directory, "/" + path);

            if (!file.exists()) {
                shutdown();
                return;
            }

            String[] files = file.list();

            if (files == null) {
                shutdown();
                return;
            }

            if (lastFiles == null) {
                lastFiles = new HashSet<>();
                Collections.addAll(lastFiles, files);
            } else {
                if (lastFiles.size() != files.length) {
                    saveAndTrigger(files);
                } else {
                    // check new files
                    for (String f : files) {
                        if (!lastFiles.contains(f)) {
                            if (!shutdown) {
                                saveAndTrigger(files);
                            }
                            return;
                        }
                    }

                    Set<String> newLastFiles = new HashSet<>();
                    Collections.addAll(newLastFiles, files);

                    // check deleted files
                    for (String f : lastFiles) {
                        if (!newLastFiles.contains(f)) {
                            lastFiles = newLastFiles;

                            if (!shutdown) {
                                trigger();
                            }

                            return;
                        }
                    }
                }
            }
        }

        private void saveAndTrigger(String[] files) {
            Set<String> newLastFiles = new HashSet<>();
            Collections.addAll(newLastFiles, files);

            lastFiles = newLastFiles;
            trigger();
        }

        @Override
        public void shutdown() {
            super.shutdown();

            synchronized (watchers) {
                watchers.remove(path);
            }

            shutdown = true;
            try {
                timer.cancel();
            } catch (Exception e) {
                e.printStackTrace();
            }
        }
    }
}
