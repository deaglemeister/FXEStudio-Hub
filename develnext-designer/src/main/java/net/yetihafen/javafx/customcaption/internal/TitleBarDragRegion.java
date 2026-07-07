package net.yetihafen.javafx.customcaption.internal;

import javafx.scene.Node;
import javafx.scene.control.MenuBar;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Pane;
import net.yetihafen.javafx.customcaption.DragRegion;

/**
 * Перетаскивание по titlebar: лого, заголовок и пустое место.
 * Пункты меню исключаются — клики открывают меню.
 */
public class TitleBarDragRegion extends DragRegion {

    public TitleBarDragRegion(Node base) {
        super(base);
    }

    @Override
    public void showInit() {
        if (!(getBase() instanceof Pane)) {
            return;
        }

        for (Node child : ((Pane) getBase()).getChildren()) {
            if (child instanceof MenuBar) {
                excludeMenuItems((MenuBar) child);
            }
        }
    }

    private void excludeMenuItems(MenuBar menuBar) {
        if (menuBar.getChildrenUnmodifiable().isEmpty()) {
            return;
        }

        Node container = menuBar.getChildrenUnmodifiable().get(0);
        if (container instanceof HBox) {
            for (Node menuNode : ((HBox) container).getChildrenUnmodifiable()) {
                addExcludeBounds(menuNode);
            }
        }
    }
}
