package net.yetihafen.javafx.customcaption;


import javafx.geometry.BoundingBox;
import javafx.geometry.Bounds;
import javafx.geometry.Point2D;
import javafx.scene.Node;
import net.yetihafen.javafx.customcaption.internal.ShowInitializable;

import java.util.ArrayList;

public class DragRegion implements ShowInitializable {

    private final ArrayList<Node> excludedBounds = new ArrayList<Node>();
    private final Node base;
    private double screenOffsetX;
    private double screenOffsetY;

    public DragRegion(Node base) {
        this.base = base;
    }

    public void setScreenOffset(double offsetX, double offsetY) {
        this.screenOffsetX = offsetX;
        this.screenOffsetY = offsetY;
    }

    /**
     * check if any given point is in the specified area
     * (excluded areas are considered)
     * @param p the {@link Point2D} (screen coordinates)
     * @return true if the point is inside the specified area
     */
    public boolean contains(Point2D p) {
        return this.contains(p.getX(), p.getY());
    }

    /**
     * check if any given point is in the specified area
     * (excluded areas are considered)
     * @param x the x coordinate (in screen coordinates)
     * @param y the y coordinate (in screen coordinates)
     * @return true if the point is inside the specified area
     */
    public boolean contains(double x, double y) {
        if (!excludedBounds.isEmpty()) {
            for (Node node : excludedBounds) {
                Bounds excluded = nodeToScreenBounds(node);
                if (excluded != null && excluded.contains(x, y)) {
                    return false;
                }
            }
        }

        Bounds baseBounds = nodeToScreenBounds(base);
        if (baseBounds == null) {
            return false;
        }

        return baseBounds.getMaxX() > x && baseBounds.getMinX() < x
                && baseBounds.getMaxY() > y && baseBounds.getMinY() < y;
    }

    private Bounds nodeToScreenBounds(Node node) {
        if (node == null) {
            return null;
        }

        Bounds bounds = node.localToScreen(node.getBoundsInLocal());
        if (bounds == null) {
            return null;
        }

        if (screenOffsetX == 0 && screenOffsetY == 0) {
            return bounds;
        }

        return new BoundingBox(
                bounds.getMinX() + screenOffsetX,
                bounds.getMinY() + screenOffsetY,
                bounds.getWidth(),
                bounds.getHeight()
        );
    }


    /**
     * adds a node to exclude its area
     * @param node the node to exclude
     */
    public DragRegion addExcludeBounds(Node node) {
        excludedBounds.add(node);
        return this;
    }

    public Node getBase() {
        return base;
    }

    @Override
    public void showInit() {
    }
}
