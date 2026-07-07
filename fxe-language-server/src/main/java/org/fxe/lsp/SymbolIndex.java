package org.fxe.lsp;

import java.util.ArrayList;
import java.util.Collections;
import java.util.HashMap;
import java.util.HashSet;
import java.util.List;
import java.util.Map;
import java.util.Set;

public class SymbolIndex {
    public static class ClassSymbol {
        public final String name;
        public final String file;
        public final Set<String> methods = new HashSet<>();
        public final Set<String> properties = new HashSet<>();
        public String parent;

        public ClassSymbol(String name, String file) {
            this.name = name;
            this.file = file;
        }
    }

    private final Map<String, ClassSymbol> classesByName = new HashMap<>();
    private final Map<String, ClassSymbol> classesByFile = new HashMap<>();
    private final Map<String, Set<String>> formComponents = new HashMap<>();

    public void clear() {
        classesByName.clear();
        classesByFile.clear();
        formComponents.clear();
    }

    public void addClass(ClassSymbol symbol) {
        classesByName.put(symbol.name.toLowerCase(), symbol);
        classesByFile.put(normalize(symbol.file), symbol);
    }

    public ClassSymbol getClass(String name) {
        if (name == null) {
            return null;
        }
        return classesByName.get(name.toLowerCase());
    }

    public ClassSymbol getClassByFile(String file) {
        return classesByFile.get(normalize(file));
    }

    public List<ClassSymbol> getAllClasses() {
        return new ArrayList<>(classesByName.values());
    }

    public void addFormComponent(String className, String componentId) {
        if (className == null || componentId == null || componentId.isEmpty()) {
            return;
        }
        Set<String> set = formComponents.get(className.toLowerCase());
        if (set == null) {
            formComponents.put(className.toLowerCase(), set = new HashSet<>());
        }
        set.add(componentId);
    }

    public Set<String> getFormComponents(String className) {
        if (className == null) {
            return Collections.emptySet();
        }
        Set<String> set = formComponents.get(className.toLowerCase());
        return set == null ? Collections.<String>emptySet() : Collections.unmodifiableSet(set);
    }

    public int classCount() {
        return classesByName.size();
    }

    private static String normalize(String file) {
        return file == null ? "" : file.replace('\\', '/').toLowerCase();
    }
}
