package org.develnext.jphp.gui.designer.classes;

import org.develnext.jphp.gui.designer.GuiDesignerExtension;
import org.develnext.jphp.gui.designer.editor.tree.AbstractDirectoryTreeSource;
import org.develnext.jphp.gui.designer.editor.tree.DirectoryTreeValue;
import org.develnext.jphp.gui.designer.editor.tree.FileDirectoryTreeSource;
import php.runtime.Memory;
import php.runtime.annotation.Reflection;
import php.runtime.annotation.Reflection.Signature;
import php.runtime.env.Environment;
import php.runtime.invoke.Invoker;
import php.runtime.memory.ArrayMemory;
import php.runtime.reflection.ClassEntity;

import java.io.File;
import java.io.FileFilter;

@Reflection.Namespace(GuiDesignerExtension.NS)
public class UXFileDirectoryTreeSource extends UXAbstractDirectoryTreeSource {
    interface WrappedInterface {
        @Reflection.Property boolean showHidden();
    }

    public UXFileDirectoryTreeSource(Environment env, FileDirectoryTreeSource wrappedObject) {
        super(env, wrappedObject);
    }

    public UXFileDirectoryTreeSource(Environment env, ClassEntity clazz) {
        super(env, clazz);
    }

    @Signature
    public void __construct(File directory) {
        __wrappedObject = new FileDirectoryTreeSource(directory);
    }

    @Override
    public FileDirectoryTreeSource getWrappedObject() {
        return (FileDirectoryTreeSource) super.getWrappedObject();
    }

    @Signature
    public File getDirectory() {
        return getWrappedObject().getDirectory();
    }

    @Signature
    public void addFileFilter(Invoker invoker) {
        getWrappedObject().addFileFilter(new FileFilter() {
            @Override
            public boolean accept(File pathname) {
                return invoker.callAny(pathname).toBoolean();
            }
        });
    }

    @Signature
    public void addValueCreator(Invoker invoker) {
        getWrappedObject().addValueCreator(new FileDirectoryTreeSource.ValueCreator() {
            @Override
            public DirectoryTreeValue create(String path, File file) {
                Memory value = invoker.callAny(path, file);

                if (value.isNull() || !value.isObject()) {
                    return null;
                }

                return value.instanceOf(UXDirectoryTreeValue.class)
                        ? value.toObject(UXDirectoryTreeValue.class).getWrappedObject()
                        : null;
            }
        });
    }

    @Signature
    public void setStructureProvider(Invoker listChildren, Invoker createValue, Invoker isVirtualPath) {
        getWrappedObject().setStructureProvider(new FileDirectoryTreeSource.StructureProvider() {
            @Override
            public java.util.List<String> listChildPaths(String path) {
                Memory result = listChildren.callAny(path);

                if (result.isNull()) {
                    return java.util.Collections.emptyList();
                }

                java.util.List<String> list = new java.util.ArrayList<>();

                if (result.isArray()) {
                    ArrayMemory arrayMemory = result.toValue(ArrayMemory.class);

                    for (Memory el : arrayMemory.values()) {
                        if (el != null && !el.isNull()) {
                            list.add(el.toString());
                        }
                    }
                }

                return list;
            }

            @Override
            public DirectoryTreeValue createVirtualValue(String path) {
                Memory value = createValue.callAny(path);

                if (value.isNull() || !value.isObject()) {
                    return null;
                }

                return value.instanceOf(UXDirectoryTreeValue.class)
                        ? value.toObject(UXDirectoryTreeValue.class).getWrappedObject()
                        : null;
            }

            @Override
            public boolean isVirtualPath(String path) {
                return isVirtualPath.callAny(path).toBoolean();
            }
        });
    }
}
