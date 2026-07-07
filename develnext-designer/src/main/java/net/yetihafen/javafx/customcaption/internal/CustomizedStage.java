package net.yetihafen.javafx.customcaption.internal;

import com.sun.jna.Pointer;
import com.sun.jna.platform.win32.User32;
import com.sun.jna.platform.win32.BaseTSD;
import com.sun.jna.platform.win32.WinDef;
import com.sun.jna.platform.win32.WinUser;
import javafx.application.Platform;
import javafx.beans.Observable;
import javafx.beans.value.ObservableValue;
import javafx.collections.ListChangeListener;
import javafx.fxml.FXMLLoader;
import javafx.geometry.Bounds;
import javafx.geometry.BoundingBox;
import javafx.geometry.Point2D;
import javafx.geometry.Pos;
import javafx.scene.Node;
import javafx.scene.Parent;
import javafx.scene.Scene;
import javafx.scene.control.MenuBar;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Pane;
import javafx.scene.layout.Region;
import javafx.scene.layout.StackPane;
import javafx.stage.Stage;
import net.yetihafen.javafx.customcaption.CaptionConfiguration;
import net.yetihafen.javafx.customcaption.DragRegion;

import java.util.ArrayList;
import java.util.List;
import net.yetihafen.javafx.customcaption.internal.libraries.User32Ex;
import net.yetihafen.javafx.customcaption.internal.structs.NCCALCSIZE_PARAMS;
import net.yetihafen.javafx.customcaption.internal.structs.TRACKMOUSEEVENT;

import java.io.IOException;

import static com.sun.jna.platform.win32.WinUser.*;

public class CustomizedStage implements ShowInitializable {

    private static final int CAPTION_BTN_WIDTH = 46;
    private static final int CAPTION_BTNS_WIDTH = 138;

    private final Stage stage;
    private CaptionConfiguration config;
    private WinDef.HWND hWnd;
    private Pointer defWndProc;
    private WndProc wndProc;

    private HBox captionControls;
    private StackPane newRoot;
    private ControlsController controller;
    private boolean isRootReplaced;
    private boolean isInjected;

    private Node closeButton;
    private Node restoreButton;
    private Node minimizeButton;

    // Кэш в scene-координатах (совпадают с client-областью окна) — не устаревает при перемещении окна
    private volatile Bounds cachedTitleBarBounds;
    private volatile Bounds[] cachedInteractiveBounds;

    public CustomizedStage(Stage stage, CaptionConfiguration config) {
        this.stage = stage;
        this.config = config;
        stage.showingProperty().addListener(this::onShowUpdate);

        if (stage.isShowing()) {
            showInit();
        }
    }

    public Stage getStage() {
        return stage;
    }

    public CaptionConfiguration getConfig() {
        return config;
    }

    @Override
    public void showInit() {
        inject();
        config.showInit();
        updateHitTestCache();
    }

    private void onShowUpdate(Observable observable, boolean oldVal, boolean newVal) {
        if (newVal && !this.isInjected) {
            inject();
            config.showInit();
            updateHitTestCache();
        }

        WinDef.HWND updatedHwnd = NativeUtilities.getHwnd(stage);

        if (updatedHwnd != null) {
            this.hWnd = updatedHwnd;
        }

        if (newVal) {
            Win32WindowHelper.setWindowProcedure(hWnd, wndProc);
            refreshStageBounds();
        }
    }

    public void inject() {
        this.isInjected = true;

        this.hWnd = NativeUtilities.getHwnd(stage);
        this.wndProc = new WndProc();
        this.defWndProc = Win32WindowHelper.setWindowProcedure(hWnd, wndProc);

        refreshStageBounds();

        stage.getScene().rootProperty().addListener(this::onParentChange);
        stage.sceneProperty().addListener(this::onSceneChange);
        if (config.isUseControls()) {
            addControlsToParent(stage.getScene().getRoot());
        }

        updateHitTestCache();
    }

    private void updateHitTestCache() {
        Runnable task = new Runnable() {
            @Override
            public void run() {
                if (stage.getScene() == null) {
                    return;
                }

                cachedTitleBarBounds = null;
                cachedInteractiveBounds = null;
                DragRegion region = config.getCaptionDragRegion();
                if (region != null) {
                    Node base = region.getBase();
                    attachHitTestListeners(base);
                    cachedTitleBarBounds = boundsOf(base);
                    cachedInteractiveBounds = collectInteractiveBounds(base);
                }
            }
        };

        if (Platform.isFxApplicationThread()) {
            task.run();
        } else {
            Platform.runLater(task);
        }
    }

    public void refreshHitTestCache() {
        updateHitTestCache();
    }

    private void attachHitTestListeners(final Node titleBar) {
        if (titleBar == null || titleBar.getProperties().get("fxeHitTestListeners") != null) {
            return;
        }
        titleBar.getProperties().put("fxeHitTestListeners", Boolean.TRUE);
        titleBar.layoutBoundsProperty().addListener(new javafx.beans.value.ChangeListener<Bounds>() {
            @Override
            public void changed(ObservableValue<? extends Bounds> observable, Bounds oldValue, Bounds newValue) {
                updateHitTestCache();
            }
        });

        Node tools = findChildById(titleBar, "titleBarTools");
        if (tools instanceof Pane) {
            ((Pane) tools).getChildren().addListener(new ListChangeListener<Node>() {
                @Override
                public void onChanged(Change<? extends Node> change) {
                    while (change.next()) {
                        if (change.wasAdded() || change.wasRemoved()) {
                            updateHitTestCache();
                            return;
                        }
                    }
                }
            });
        }
    }

    // Bounds в scene-координатах: scene совпадает с client-областью окна
    private static Bounds boundsOf(Node node) {
        if (node == null || node.getScene() == null) {
            return null;
        }

        Bounds local = node.getBoundsInLocal();
        if (local.getWidth() <= 0 || local.getHeight() <= 0) {
            return null;
        }

        return node.localToScene(local);
    }

    private Bounds[] collectInteractiveBounds(Node titleBar) {
        if (!(titleBar instanceof Pane)) {
            return null;
        }

        List<Bounds> items = new ArrayList<Bounds>();

        MenuBar menuBar = null;
        for (Node child : ((Pane) titleBar).getChildren()) {
            if (child instanceof MenuBar) {
                menuBar = (MenuBar) child;
            }
        }

        if (menuBar != null && !menuBar.getChildrenUnmodifiable().isEmpty()) {
            Node container = menuBar.getChildrenUnmodifiable().get(0);
            if (container instanceof HBox) {
                for (Node menuNode : ((HBox) container).getChildrenUnmodifiable()) {
                    addBounds(items, boundsOf(menuNode));
                }
            }
        }

        Node tools = findChildById(titleBar, "titleBarTools");
        if (tools instanceof Pane) {
            for (Node toolNode : ((Pane) tools).getChildren()) {
                addBounds(items, boundsOf(toolNode));
            }
        }

        if (items.isEmpty() && menuBar != null) {
            addBounds(items, boundsOf(menuBar));
        }

        return items.isEmpty() ? null : items.toArray(new Bounds[items.size()]);
    }

    private static void addBounds(List<Bounds> items, Bounds bounds) {
        if (bounds != null && bounds.getWidth() > 0 && bounds.getHeight() > 0) {
            items.add(bounds);
        }
    }

    private static Node findChildById(Node parent, String id) {
        if (id != null && id.equals(parent.getId())) {
            return parent;
        }

        if (parent instanceof Pane) {
            for (Node child : ((Pane) parent).getChildren()) {
                Node found = findChildById(child, id);
                if (found != null) {
                    return found;
                }
            }
        }

        return null;
    }

    public void release() {
        this.isInjected = false;
        stage.sceneProperty().removeListener(this::onSceneChange);
        stage.getScene().rootProperty().removeListener(this::onParentChange);

        if (this.isRootReplaced) {
            StackPane root = (StackPane) stage.getScene().getRoot();
            Parent newParent = (Parent) root.getChildren().get(0);
            root.getChildren().clear();
            stage.getScene().setRoot(newParent);
        }

        Win32WindowHelper.restoreWindowProcedure(hWnd, defWndProc);
        refreshStageBounds();
    }

    public void refreshStageBounds() {
        WinDef.RECT rect = new WinDef.RECT();
        User32Ex.INSTANCE.GetWindowRect(hWnd, rect);
        User32Ex.INSTANCE.SetWindowPos(hWnd, null, rect.left, rect.top, rect.right - rect.left, rect.bottom - rect.top, WinUser.SWP_FRAMECHANGED);
    }

    private void onParentChange(ObservableValue<? extends Parent> observable, Parent oldVal, Parent newVal) {
        if (!isInjected) {
            return;
        }
        if (newRoot == newVal) {
            return;
        }
        addControlsToParent(newVal);
    }

    private void onSceneChange(ObservableValue<? extends Scene> observable, Scene oldVal, Scene newVal) {
        if (!isInjected) {
            return;
        }
        oldVal.rootProperty().removeListener(this::onParentChange);
        newVal.rootProperty().addListener(this::onParentChange);
        addControlsToParent(newVal.getRoot());
    }

    private void addControlsToParent(Parent parent) {
        this.isRootReplaced = true;
        initControls();

        newRoot = new StackPane();

        if (parent instanceof Region) {
            Region content = (Region) parent;
            content.setMaxSize(Double.MAX_VALUE, Double.MAX_VALUE);
            content.prefWidthProperty().bind(newRoot.widthProperty());
            content.prefHeightProperty().bind(newRoot.heightProperty());
            content.minWidthProperty().bind(newRoot.widthProperty());
            content.minHeightProperty().bind(newRoot.heightProperty());
        }

        StackPane.setAlignment(parent, Pos.TOP_LEFT);
        StackPane.setAlignment(captionControls, Pos.TOP_RIGHT);
        captionControls.setMouseTransparent(true);
        captionControls.setPickOnBounds(false);
        newRoot.getChildren().addAll(parent, captionControls);

        stage.getScene().setRoot(newRoot);
        updateHitTestCache();
    }

    private void initControls() {
        FXMLLoader loader = new FXMLLoader(getClass().getResource("/net/yetihafen/javafx/customcaption/caption-controls.fxml"));
        try {
            captionControls = loader.load();
            controller = loader.getController();
            captionControls.getStylesheets().add(getClass().getResource("/net/yetihafen/javafx/customcaption/caption-controls.css").toExternalForm());
            controller.applyConfig(config);
            minimizeButton = controller.getMinimizeButton();
            restoreButton = controller.getMaximizeRestoreButton();
            closeButton = controller.getCloseButton();
        } catch (IOException e) {
            e.printStackTrace();
        }
    }

    class WndProc implements WinUser.WindowProc {

        private static final int WM_NCCALCSIZE = 0x0083;
        private static final int WM_NCHITTEST = 0x0084;
        private static final int WM_NCMOUSEMOVE = 0x00A0;
        private static final int WM_NCLBUTTONDOWN = 0x00A1;
        private static final int WM_NCLBUTTONUP = 0x00A2;
        private static final int WM_MOUSELEAVE = 0x02A3;
        private static final int WM_NCMOUSELEAVE = 0x02A2;
        private static final int HTCLIENT = 1;
        private static final int HTCAPTION = 2;
        private static final int HTMAXBUTTON = 9;
        private static final int HTCLOSE = 20;
        private static final int HTMINBUTTON = 8;
        private static final int HTTOP = 12;
        private static final int SC_MINIMIZE = 0xF020;
        private static final int SC_MAXIMIZE = 0xF030;
        private static final int SC_RESTORE = 0xF120;
        private static final int SC_CLOSE = 0xF060;
        private static final int TME_LEAVE = 0x00000002;
        private static final int TME_NONCLIENT = 0x00000010;
        private static final int HOVER_DEFAULT = 0xFFFFFFFF;

        private CaptionButton acitveButton;

        @Override
        public WinDef.LRESULT callback(WinDef.HWND hWnd, int msg, WinDef.WPARAM wParam, WinDef.LPARAM lParam) {
            switch (msg) {
                case WM_NCCALCSIZE:
                    return onWmNcCalcSize(hWnd, msg, wParam, lParam);
                case WM_NCHITTEST:
                    return onWmNcHitTest(hWnd, msg, wParam, lParam);
                case WM_NCLBUTTONDOWN:
                    return onWmNcLButtonDown(hWnd, msg, wParam, lParam);
                case WM_NCLBUTTONUP:
                    return onWmNcLButtonUp(hWnd, msg, wParam, lParam);
                case WM_NCMOUSEMOVE:
                    return onWmNcMouseMove(hWnd, msg, wParam, lParam);
                case WM_NCMOUSELEAVE:
                case WM_MOUSELEAVE:
                    if (isRootReplaced) {
                        scheduleHover(null);
                        acitveButton = null;
                    }
                    return DefWndProc(hWnd, msg, wParam, lParam);
                case WM_SIZE:
                    if (controller != null) {
                        controller.onResize(wParam);
                    }
                    updateHitTestCache();
                    return DefWndProc(hWnd, msg, wParam, lParam);
                default:
                    return DefWndProc(hWnd, msg, wParam, lParam);
            }
        }

        private void scheduleHover(final CaptionButton button) {
            if (!isRootReplaced || controller == null) {
                return;
            }

            Platform.runLater(new Runnable() {
                @Override
                public void run() {
                    controller.hoverButton(button);
                }
            });
        }

        private WinDef.LRESULT onWmNcMouseMove(WinDef.HWND hWnd, int msg, WinDef.WPARAM wParam, WinDef.LPARAM lParam) {
            if (!isRootReplaced) {
                return DefWndProc(hWnd, msg, wParam, lParam);
            }

            int position = wParam.intValue();

            if (position == HTMAXBUTTON) {
                if (acitveButton != CaptionButton.MAXIMIZE_RESTORE) {
                    acitveButton = CaptionButton.MAXIMIZE_RESTORE;
                    scheduleHover(acitveButton);
                    TRACKMOUSEEVENT ev = new TRACKMOUSEEVENT();
                    ev.cbSize = new WinDef.DWORD(ev.size());
                    ev.dwFlags = new WinDef.DWORD(TME_LEAVE | TME_NONCLIENT);
                    ev.hwndTrack = hWnd;
                    ev.dwHoverTime = new WinDef.DWORD(HOVER_DEFAULT);
                    User32Ex.INSTANCE.TrackMouseEvent(ev);
                }
                return DefWndProc(hWnd, msg, wParam, lParam);
            }

            CaptionButton newButton = null;
            switch (position) {
                case HTCLOSE:
                    newButton = CaptionButton.CLOSE;
                    break;
                case HTMINBUTTON:
                    newButton = CaptionButton.MINIMIZE;
                    break;
            }

            if (newButton == acitveButton) {
                return new LRESULT(0);
            }
            acitveButton = newButton;

            scheduleHover(acitveButton);

            if (acitveButton != null) {
                TRACKMOUSEEVENT ev = new TRACKMOUSEEVENT();
                ev.cbSize = new WinDef.DWORD(ev.size());
                ev.dwFlags = new WinDef.DWORD(TME_LEAVE | TME_NONCLIENT);
                ev.hwndTrack = hWnd;
                ev.dwHoverTime = new WinDef.DWORD(HOVER_DEFAULT);
                User32Ex.INSTANCE.TrackMouseEvent(ev);
                return new LRESULT(0);
            }
            return DefWndProc(hWnd, msg, wParam, lParam);
        }

        private WinDef.LRESULT onWmNcLButtonDown(WinDef.HWND hWnd, int msg, WinDef.WPARAM wParam, WinDef.LPARAM lParam) {
            int position = wParam.intValue();

            switch (position) {
                case HTMINBUTTON:
                    User32Ex.INSTANCE.SendMessage(hWnd, WinUser.WM_SYSCOMMAND, new WinDef.WPARAM(SC_MINIMIZE), new WinDef.LPARAM(0));
                    return new WinDef.LRESULT(0);
                case HTMAXBUTTON:
                    boolean maximized = NativeUtilities.isMaximized(hWnd);
                    User32Ex.INSTANCE.SendMessage(hWnd, WinUser.WM_SYSCOMMAND,
                            new WinDef.WPARAM(maximized ? SC_RESTORE : SC_MAXIMIZE), new WinDef.LPARAM(0));
                    return new WinDef.LRESULT(0);
                case HTCLOSE:
                    User32Ex.INSTANCE.SendMessage(hWnd, WinUser.WM_SYSCOMMAND, new WinDef.WPARAM(SC_CLOSE), new WinDef.LPARAM(0));
                    return new WinDef.LRESULT(0);
                default:
                    return DefWndProc(hWnd, msg, wParam, lParam);
            }
        }

        private WinDef.LRESULT onWmNcLButtonUp(WinDef.HWND hWnd, int msg, WinDef.WPARAM wParam, WinDef.LPARAM lParam) {
            int position = wParam.intValue();

            if (position == HTMINBUTTON || position == HTMAXBUTTON || position == HTCLOSE) {
                return new WinDef.LRESULT(0);
            }

            return DefWndProc(hWnd, msg, wParam, lParam);
        }

        private WinDef.LRESULT onWmNcHitTest(WinDef.HWND hWnd, int msg, WinDef.WPARAM wParam, WinDef.LPARAM lParam) {
            int screenX = GET_X_LPARAM(lParam);
            int screenY = GET_Y_LPARAM(lParam);

            if (cachedTitleBarBounds == null) {
                updateHitTestCache();
            }

            // Всё сравниваем в client-координатах — не устаревает при перемещении окна
            WinDef.POINT clientPt = new WinDef.POINT(screenX, screenY);
            User32Ex.INSTANCE.ScreenToClient(hWnd, clientPt);
            Point2D mouse = new Point2D(clientPt.x, clientPt.y);

            WinDef.RECT clientRect = new WinDef.RECT();
            User32.INSTANCE.GetClientRect(hWnd, clientRect);
            int clientW = clientRect.right - clientRect.left;

            int captionH = config.getCaptionHeight();

            // 1. Кнопки окна: правый верхний угол, всегда приоритет
            if (clientPt.y >= 0 && clientPt.y < captionH && clientPt.x >= clientW - CAPTION_BTNS_WIDTH && clientPt.x < clientW) {
                int relX = clientPt.x - (clientW - CAPTION_BTNS_WIDTH);
                if (relX < CAPTION_BTN_WIDTH) {
                    return new WinDef.LRESULT(HTMINBUTTON);
                }
                if (relX < CAPTION_BTN_WIDTH * 2) {
                    if (stage.isResizable()) {
                        return new WinDef.LRESULT(HTMAXBUTTON);
                    }
                    return new WinDef.LRESULT(HTCAPTION);
                }
                return new WinDef.LRESULT(HTCLOSE);
            }

            // 2. Titlebar: меню/кнопки → клиент, пустое место → drag
            Bounds titleBar = cachedTitleBarBounds;
            if (titleBar != null && titleBar.contains(mouse)) {
                Bounds[] interactive = cachedInteractiveBounds;
                if (interactive != null) {
                    for (Bounds item : interactive) {
                        if (item != null && item.contains(mouse)) {
                            return new WinDef.LRESULT(HTCLIENT);
                        }
                    }
                }

                return new WinDef.LRESULT(HTCAPTION);
            }

            WinDef.LRESULT res = DefWndProc(hWnd, msg, wParam, lParam);
            long code = res.longValue();

            if (code == HTCLIENT && !NativeUtilities.isMaximized(hWnd)) {
                if (clientPt.y <= 3) {
                    return new WinDef.LRESULT(HTTOP);
                }
            }

            return res;
        }

        private WinDef.LRESULT onWmNcCalcSize(WinDef.HWND hWnd, int msg, WinDef.WPARAM wParam, WinDef.LPARAM lParam) {
            if (wParam.longValue() == 0) {
                return new WinDef.LRESULT(0);
            }

            NCCALCSIZE_PARAMS params = new NCCALCSIZE_PARAMS(new Pointer(lParam.longValue()));
            int oldTop = params.rgrc[0].top;

            WinDef.LRESULT res = DefWndProc(hWnd, msg, wParam, lParam);
            if (res.longValue() != 0) {
                return res;
            }

            params.read();

            WinDef.RECT newSize = params.rgrc[0];
            newSize.top = oldTop;

            boolean maximized = NativeUtilities.isMaximized(hWnd);

            if (maximized && !stage.isFullScreen()) {
                newSize.top += NativeUtilities.getResizeHandleHeight(hWnd);
            }

            params.write();
            return new WinDef.LRESULT(0);
        }

        private WinDef.LRESULT DefWndProc(WinDef.HWND hWnd, int msg, WinDef.WPARAM wParam, WinDef.LPARAM lParam) {
            return Win32WindowHelper.callWindowProcedure(defWndProc, hWnd, msg, wParam, lParam);
        }

        private int GET_X_LPARAM(BaseTSD.LONG_PTR lParam) {
            return (int) (lParam.longValue() & 0xffff);
        }

        private int GET_Y_LPARAM(BaseTSD.LONG_PTR lParam) {
            return (int) ((lParam.longValue() >> 16) & 0xffff);
        }
    }

    public enum CaptionButton {
        CLOSE, MINIMIZE, MAXIMIZE_RESTORE
    }
}
