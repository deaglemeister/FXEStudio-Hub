package org.develnext.jphp.gui.designer.classes;

import javafx.application.Platform;
import javafx.beans.value.ChangeListener;
import javafx.beans.value.ObservableValue;
import javafx.collections.ListChangeListener;
import javafx.collections.ObservableList;
import javafx.event.EventHandler;
import javafx.geometry.Bounds;
import javafx.geometry.Point2D;
import javafx.scene.Cursor;
import javafx.scene.Node;
import javafx.scene.Scene;
import javafx.scene.input.MouseButton;
import javafx.scene.input.MouseEvent;
import javafx.scene.input.ScrollEvent;
import javafx.scene.control.ScrollPane;
import javafx.scene.layout.AnchorPane;
import javafx.scene.layout.Background;
import javafx.scene.layout.Pane;
import javafx.scene.layout.Region;
import javafx.scene.transform.Scale;
import org.develnext.jphp.ext.javafx.classes.layout.UXAnchorPane;
import org.develnext.jphp.gui.designer.GuiDesignerExtension;
import php.runtime.annotation.Reflection;
import php.runtime.annotation.Reflection.*;
import php.runtime.env.Environment;
import php.runtime.invoke.Invoker;
import php.runtime.reflection.ClassEntity;

@NotWrapper
@Namespace(GuiDesignerExtension.NS)
public class UXDesignPane extends UXAnchorPane {
    protected boolean resizing = false;

    protected int borderWidth = 4;
    protected int snapSize = 8;
    protected String borderColor = "#393B40";

    protected double startWidth;
    protected double startHeight;
    protected Point2D startDragPoint = null;

    protected Invoker onResize = null;

    protected AnchorPane topBorders = new AnchorPane();
    private static final double ZOOM_MIN = 0.1;
    private static final double ZOOM_MAX = 5.0;

    private double zoom = 1.0;
    private Scale scale;

    private ScrollPane wheelZoomScrollPane;
    private double baseContentWidth = -1;
    private double baseContentHeight = -1;
    private Invoker onZoomChanged = null;
    private boolean wheelZoomActive = false;

    private ScrollPane panScrollPane;
    private Point2D panStartPoint = null;
    private double panStartH = 0;
    private double panStartV = 0;
    private EventHandler<MouseEvent> panPressFilter;
    private EventHandler<MouseEvent> panDragFilter;
    private EventHandler<MouseEvent> panReleaseFilter;

    public UXDesignPane(Environment env, AnchorPane wrappedObject) {
        super(env, wrappedObject);
    }

    public UXDesignPane(Environment env, ClassEntity clazz) {
        super(env, clazz);
    }

    @Override
    public void __construct() {
        super.__construct();

        updateStyle();

        getWrappedObject().getChildren().add(topBorders);
        UXAnchorPane.setAnchor(topBorders, -borderWidth);

        //topBorders.setLayoutX(-borderWidth/2);
        //topBorders.setLayoutY(-borderWidth/2);

        scale = new Scale();
        getWrappedObject().getTransforms().add(scale);

        getChildren().addListener((ListChangeListener) c -> setZoom(zoom));

        getWrappedObject().getChildren().addListener((ListChangeListener<Node>) c -> {
            Platform.runLater(this::update);
        });

        getWrappedObject().setOnMouseExited(event -> {
            if (!resizing) {
                getWrappedObject().getScene().setCursor(Cursor.DEFAULT);
            }
        });

        getWrappedObject().setOnMouseMoved(event -> {
            double x = event.getX();
            double y = event.getY();

            Scene scene = getWrappedObject().getScene();

            boolean blockWidth = getWrappedObject().getMaxWidth() == getWrappedObject().getPrefWidth() &&
                    getWrappedObject().getMinWidth() == getWrappedObject().getPrefWidth();

            boolean blockHeight = getWrappedObject().getMaxHeight() == getWrappedObject().getPrefHeight() &&
                    getWrappedObject().getMinHeight() == getWrappedObject().getPrefHeight();

            if ((!blockWidth && isHResize(x, y)) && (!blockHeight && isVResize(x, y))) {
                scene.setCursor(Cursor.SE_RESIZE);
            } else if (!blockWidth && isHResize(x, y)) {
                scene.setCursor(Cursor.H_RESIZE);
            } else if (!blockHeight && isVResize(x, y)) {
                scene.setCursor(Cursor.V_RESIZE);
            } else {
                scene.setCursor(Cursor.DEFAULT);
            }
        });

        getWrappedObject().setOnMousePressed(event -> {
            double x = event.getX();
            double y = event.getY();

            if (isHResize(x, y) && isVResize(x, y)) {
                startDragPoint = new Point2D(event.getScreenX(), event.getScreenY());
            } else if (isHResize(x, y)) {
                startDragPoint = new Point2D(event.getScreenX(), 0.0);
            } else if (isVResize(x, y)) {
                startDragPoint = new Point2D(0.0, event.getScreenY());
            } else {
                startDragPoint = null;
            }

            Pane node = (Pane) getWrappedObject().getChildren().get(0);

            if (startDragPoint != null) {
                startWidth = node.getPrefWidth();
                startHeight = node.getPrefHeight();

                update();
                event.consume();
            }
        });

        getWrappedObject().setOnMouseDragged(event -> {
            if (startDragPoint != null) {
                double hOffset = (event.getScreenX() - startDragPoint.getX()) / zoom;
                double vOffset = (event.getScreenY() - startDragPoint.getY()) / zoom;

                Pane node = (Pane) getWrappedObject().getChildren().get(0);

                if (startDragPoint.getX() > 0) {
                    double value = startWidth + hOffset;

                    if (snapSize > 1) {
                        value = Math.round(Math.round(value / snapSize) * snapSize);
                    }

                    node.setPrefWidth(value);
                }

                if (startDragPoint.getY() > 0) {
                    double value = startHeight + vOffset;

                    if (snapSize > 1) {
                        value = Math.round(Math.round(value / snapSize) * snapSize);
                    }

                    node.setPrefHeight(value);
                }

                resizing = true;

                if (onResize != null) {
                    onResize.callAny();
                }

                update();
                event.consume();
            }
        });

        getWrappedObject().addEventFilter(MouseEvent.MOUSE_RELEASED, event -> {
            if (resizing) {
                resizing = false;
                update();
                event.consume();
            }
        });
    }

    protected boolean isHResize(double x, double y) {
        double width = getWidth();

        return x > width - borderWidth && x < width;
    }

    protected boolean isVResize(double x, double y) {
        double height = getHeight();

        return y > height - borderWidth && y < height;
    }

    @Setter
    public void setZoom(double zoom) {
        this.zoom = clampZoom(zoom);

        scale.setX(this.zoom);
        scale.setY(this.zoom);

        if (wheelZoomScrollPane == null) {
            return;
        }

        ensureBaseContentSize();
        getWrappedObject().setLayoutX(0);
        getWrappedObject().setLayoutY(0);

        if (this.zoom == 1.0 && !wheelZoomActive) {
            wheelZoomScrollPane.setFitToWidth(true);
            wheelZoomScrollPane.setFitToHeight(true);
            resetParentSizeForFit();
            wheelZoomScrollPane.setHvalue(0);
            wheelZoomScrollPane.setVvalue(0);
        } else {
            wheelZoomScrollPane.setFitToWidth(false);
            wheelZoomScrollPane.setFitToHeight(false);
            updateParentSizeForZoom(this.zoom);

            if (this.zoom != 1.0) {
                wheelZoomActive = true;
            }
        }
    }

    @Signature
    public void enableWheelZoom(@Reflection.Nullable ScrollPane scrollPane) {
        if (wheelZoomScrollPane != null) {
            wheelZoomScrollPane.removeEventFilter(ScrollEvent.SCROLL, this::handleWheelZoom);
        }

        wheelZoomScrollPane = scrollPane;

        if (wheelZoomScrollPane != null) {
            wheelZoomScrollPane.addEventFilter(ScrollEvent.SCROLL, this::handleWheelZoom);
        }
    }

    @Signature
    public void enableMiddleMousePan(@Reflection.Nullable ScrollPane scrollPane) {
        if (panScrollPane != null) {
            if (panPressFilter != null) {
                panScrollPane.removeEventFilter(MouseEvent.MOUSE_PRESSED, panPressFilter);
            }
            if (panDragFilter != null) {
                panScrollPane.removeEventFilter(MouseEvent.MOUSE_DRAGGED, panDragFilter);
            }
            if (panReleaseFilter != null) {
                panScrollPane.removeEventFilter(MouseEvent.MOUSE_RELEASED, panReleaseFilter);
            }
        }

        panScrollPane = scrollPane;

        if (panScrollPane == null) {
            return;
        }

        panPressFilter = event -> {
            if (event.getButton() == MouseButton.MIDDLE) {
                panStartPoint = new Point2D(event.getX(), event.getY());
                panStartH = panScrollPane.getHvalue();
                panStartV = panScrollPane.getVvalue();
                panScrollPane.setCursor(Cursor.MOVE);
                event.consume();
            }
        };

        panDragFilter = event -> {
            if (panStartPoint == null || !event.isMiddleButtonDown()) {
                return;
            }

            Node content = panScrollPane.getContent();
            double contentW = content != null ? content.getBoundsInLocal().getWidth() : 0;
            double contentH = content != null ? content.getBoundsInLocal().getHeight() : 0;
            double viewW = panScrollPane.getViewportBounds().getWidth();
            double viewH = panScrollPane.getViewportBounds().getHeight();
            double rangeX = Math.max(0, contentW - viewW);
            double rangeY = Math.max(0, contentH - viewH);
            double dx = event.getX() - panStartPoint.getX();
            double dy = event.getY() - panStartPoint.getY();

            if (rangeX > 0) {
                panScrollPane.setHvalue(clamp(panStartH - dx / rangeX, 0, 1));
            }

            if (rangeY > 0) {
                panScrollPane.setVvalue(clamp(panStartV - dy / rangeY, 0, 1));
            }

            event.consume();
        };

        panReleaseFilter = event -> {
            if (panStartPoint != null) {
                panStartPoint = null;
                panScrollPane.setCursor(Cursor.DEFAULT);
            }
        };

        panScrollPane.addEventFilter(MouseEvent.MOUSE_PRESSED, panPressFilter);
        panScrollPane.addEventFilter(MouseEvent.MOUSE_DRAGGED, panDragFilter);
        panScrollPane.addEventFilter(MouseEvent.MOUSE_RELEASED, panReleaseFilter);
    }

    @Signature
    public void onZoomChanged(@Reflection.Nullable Invoker onZoomChanged) {
        this.onZoomChanged = onZoomChanged;
    }

    protected void handleWheelZoom(ScrollEvent event) {
        if (wheelZoomScrollPane == null || !event.isControlDown()) {
            return;
        }

        event.consume();

        ensureBaseContentSize();

        double factor = event.getDeltaY() > 0 ? 1.08 : 1 / 1.08;
        double newZoom = clampZoom(zoom * factor);

        if (Math.abs(newZoom - zoom) < 0.0001) {
            return;
        }

        zoomAt(newZoom, event.getX(), event.getY());
    }

    protected void ensureBaseContentSize() {
        if (baseContentWidth >= 0) {
            return;
        }

        baseContentWidth = getWrappedObject().getPrefWidth();
        baseContentHeight = getWrappedObject().getPrefHeight();

        if (baseContentWidth <= 0) {
            baseContentWidth = getWrappedObject().getWidth();
        }

        if (baseContentHeight <= 0) {
            baseContentHeight = getWrappedObject().getHeight();
        }
    }

    protected void zoomAt(double newZoom, double viewportMouseX, double viewportMouseY) {
        ScrollPane scrollPane = wheelZoomScrollPane;
        if (scrollPane == null) {
            return;
        }

        ensureBaseContentSize();

        double oldZoom = zoom;
        newZoom = clampZoom(newZoom);

        if (Math.abs(newZoom - oldZoom) < 0.0001) {
            return;
        }

        if (oldZoom == 1.0 && !wheelZoomActive) {
            scrollPane.setFitToWidth(false);
            scrollPane.setFitToHeight(false);
            wheelZoomActive = true;
            baseContentWidth = -1;
            ensureBaseContentSize();
            updateParentSizeForZoom(1.0);
        }

        Node content = scrollPane.getContent();
        double contentW = content != null ? content.getBoundsInLocal().getWidth() : 0;
        double contentH = content != null ? content.getBoundsInLocal().getHeight() : 0;
        double viewW = scrollPane.getViewportBounds().getWidth();
        double viewH = scrollPane.getViewportBounds().getHeight();

        double rangeX = Math.max(0, contentW - viewW);
        double rangeY = Math.max(0, contentH - viewH);
        double scrollX = rangeX > 0 ? scrollPane.getHvalue() * rangeX : 0;
        double scrollY = rangeY > 0 ? scrollPane.getVvalue() * rangeY : 0;

        double designX = (scrollX + viewportMouseX) / oldZoom;
        double designY = (scrollY + viewportMouseY) / oldZoom;

        this.zoom = newZoom;
        scale.setX(newZoom);
        scale.setY(newZoom);
        getWrappedObject().setLayoutX(0);
        getWrappedObject().setLayoutY(0);
        updateParentSizeForZoom(newZoom);

        if (newZoom == 1.0) {
            wheelZoomActive = false;
            scrollPane.setFitToWidth(true);
            scrollPane.setFitToHeight(true);
            resetParentSizeForFit();
            scrollPane.setHvalue(0);
            scrollPane.setVvalue(0);
        } else {
            double newContentW = baseContentWidth * newZoom;
            double newContentH = baseContentHeight * newZoom;
            double newRangeX = Math.max(0, newContentW - viewW);
            double newRangeY = Math.max(0, newContentH - viewH);
            double newScrollX = designX * newZoom - viewportMouseX;
            double newScrollY = designY * newZoom - viewportMouseY;

            if (newRangeX > 0) {
                scrollPane.setHvalue(clamp(newScrollX / newRangeX, 0, 1));
            } else {
                scrollPane.setHvalue(0);
            }

            if (newRangeY > 0) {
                scrollPane.setVvalue(clamp(newScrollY / newRangeY, 0, 1));
            } else {
                scrollPane.setVvalue(0);
            }
        }

        if (onZoomChanged != null) {
            onZoomChanged.callAny(Math.round(newZoom * 100));
        }
    }

    protected void resetParentSizeForFit() {
        Node parent = getWrappedObject().getParent();

        if (!(parent instanceof Region)) {
            return;
        }

        Region region = (Region) parent;
        region.setMinWidth(Region.USE_COMPUTED_SIZE);
        region.setMinHeight(Region.USE_COMPUTED_SIZE);
        region.setPrefWidth(Region.USE_COMPUTED_SIZE);
        region.setPrefHeight(Region.USE_COMPUTED_SIZE);
    }

    protected void updateParentSizeForZoom(double newZoom) {
        Node parent = getWrappedObject().getParent();

        if (!(parent instanceof Region)) {
            return;
        }

        Region region = (Region) parent;
        double width = baseContentWidth * newZoom;
        double height = baseContentHeight * newZoom;

        region.setMinWidth(width);
        region.setMinHeight(height);
        region.setPrefWidth(width);
        region.setPrefHeight(height);
    }

    protected static double clampZoom(double value) {
        return clamp(value, ZOOM_MIN, ZOOM_MAX);
    }

    protected static double clamp(double value, double min, double max) {
        return Math.max(min, Math.min(max, value));
    }

    @Getter
    public double getZoom() {
        return this.zoom;
    }

    @Getter
    public int getSnapSize() {
        return snapSize;
    }

    @Setter
    public void setSnapSize(int snapSize) {
        this.snapSize = snapSize;
    }

    @Getter
    public boolean getEditing() {
        return resizing;
    }

    @Getter
    public int getBorderWidth() {
        return borderWidth;
    }

    @Setter
    public void setBorderWidth(int borderWidth) {
        this.borderWidth = borderWidth;
        updateStyle();
    }

    @Getter
    public String getBorderColor() {
        return borderColor;
    }

    @Setter
    public void setBorderColor(String borderColor) {
        this.borderColor = borderColor;
        updateStyle();
    }

    @Signature
    public void onResize(@Reflection.Nullable Invoker onResize) {
        this.onResize = onResize;
    }

    @Signature
    public void update() {
        topBorders.toFront();
    }

    protected void updateStyle() {
        getWrappedObject().setStyle("-fx-border-color: " + borderColor + "; -fx-border-width: " + borderWidth + "px; -fx-border-radius: 0px;");
        topBorders.setStyle("-fx-border-color: " + borderColor + "; -fx-border-width: " + borderWidth + "px; -fx-border-radius: 0px;");
        topBorders.setMouseTransparent(true);
        topBorders.setBackground(Background.EMPTY);
        topBorders.setOpacity(0.5);
    }
}
