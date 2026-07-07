package org.develnext.jphp.gui.designer.window;

import javafx.application.Platform;
import javafx.geometry.Pos;
import javafx.geometry.Rectangle2D;
import javafx.scene.Node;
import javafx.scene.control.Button;
import javafx.scene.control.Label;
import javafx.scene.input.MouseButton;
import javafx.scene.layout.BorderPane;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Priority;
import javafx.scene.layout.Region;
import javafx.scene.paint.Color;
import javafx.scene.text.Font;
import javafx.stage.Screen;
import javafx.stage.Stage;
import javafx.stage.StageStyle;
import net.yetihafen.javafx.customcaption.CaptionConfiguration;
import net.yetihafen.javafx.customcaption.CustomCaption;

import java.util.concurrent.CountDownLatch;

/**
 * MainForm: UNDECORATED + MenuBar в titlebar + кнопки Segoe Fluent Icons.
 */
public final class FxeWindowChrome {
    private static final String STYLE_SHEET = FxeWindowChrome.class.getResource("FxeWindowChrome.css").toExternalForm();
    private static final boolean NATIVE_CAPTION = isWindows();

    private static final Color CAPTION_BG = Color.web("#2B2D30");
    private static final Color CAPTION_ICON = Color.web("#abb8c8");
    private static final Color CAPTION_HOVER = Color.web("#373b40");
    private static final Color CAPTION_CLOSE_HOVER = Color.web("#e81123");
    private static final Color CAPTION_BORDER = Color.web("#373b40");

    private final Stage stage;

    private HBox menuHost;
    private Label titleLabel;
    private Button maximizeButton;

    private Node pendingMenu;
    private Node pendingHeader;
    private boolean applied;

    private double dragOffsetX;
    private double dragOffsetY;
    private boolean maximized;
    private double restoreX;
    private double restoreY;
    private double restoreW;
    private double restoreH;

    private FxeWindowChrome(Stage stage) {
        this.stage = stage;
    }

    public static FxeWindowChrome install(Stage stage) {
        if (stage == null || stage.getScene() == null) {
            throw new IllegalArgumentException("Stage and scene required");
        }

        Node content = stage.getScene().getRoot();
        if (content instanceof BorderPane && content.getStyleClass().contains("fxe-window-root")) {
            return null;
        }

        return new FxeWindowChrome(stage);
    }

    public boolean isApplied() {
        return applied;
    }

    public boolean usesCustomTitleBar() {
        return pendingMenu != null || pendingHeader != null || !NATIVE_CAPTION;
    }

    private boolean useNativeCaption() {
        return NATIVE_CAPTION && pendingMenu == null && pendingHeader == null;
    }

    public void setTitleBarContent(Node content) {
        if (content == null) {
            return;
        }

        pendingHeader = content;
        pendingMenu = null;

        if (!stage.isShowing()) {
            stage.initStyle(StageStyle.UNDECORATED);
        }

        forceApply();
    }

    public void setMenuNode(Node menu) {
        if (menu == null) {
            return;
        }

        pendingMenu = menu;

        if (!stage.isShowing()) {
            stage.initStyle(StageStyle.UNDECORATED);
        }

        forceApply();
    }

    /** Окна без MenuBar (на Windows — customcaption). */
    public void apply() {
        forceApply();
    }

    private void forceApply() {
        if (Platform.isFxApplicationThread()) {
            applyIfNeeded();
            return;
        }

        CountDownLatch latch = new CountDownLatch(1);
        Platform.runLater(new Runnable() {
            @Override
            public void run() {
                try {
                    applyIfNeeded();
                } finally {
                    latch.countDown();
                }
            }
        });

        try {
            latch.await();
        } catch (InterruptedException e) {
            Thread.currentThread().interrupt();
        }
    }

    private void applyIfNeeded() {
        if (applied) {
            return;
        }

        if (!stage.isShowing()) {
            stage.initStyle(useNativeCaption() ? StageStyle.DECORATED : StageStyle.UNDECORATED);
        }

        applied = true;

        if (useNativeCaption()) {
            applyNativeCaption();
        } else {
            applyCustomChrome();
        }
    }

    private void applyNativeCaption() {
        CaptionConfiguration config = new CaptionConfiguration(32, CAPTION_ICON, CAPTION_BG)
                .setButtonHoverColor(CAPTION_HOVER)
                .setCloseButtonHoverColor(CAPTION_CLOSE_HOVER)
                .setIconHoverColor(Color.WHITE);

        CustomCaption.useForStage(stage, config);
        CustomCaption.setImmersiveDarkMode(stage, true);
        CustomCaption.setBorderColor(stage, CAPTION_BORDER);
    }

    private void applyCustomChrome() {
        Node content = stage.getScene().getRoot();
        if (content instanceof BorderPane && content.getStyleClass().contains("fxe-window-root")) {
            return;
        }

        BorderPane root = new BorderPane();
        root.getStyleClass().addAll("fxe-window-root", "root");
        root.setStyle("-fx-background-color: #252729;");

        HBox titleBar = new HBox();
        titleBar.getStyleClass().add("fxe-titlebar");
        titleBar.setAlignment(Pos.CENTER_LEFT);
        titleBar.setSpacing(0);
        titleBar.setMinHeight(34);
        titleBar.setPrefHeight(34);
        titleBar.setMaxHeight(Region.USE_PREF_SIZE);
        titleBar.setStyle("-fx-background-color: #2B2D30;");

        menuHost = new HBox();
        menuHost.getStyleClass().add("fxe-titlebar-menu");
        menuHost.setAlignment(Pos.CENTER_LEFT);
        menuHost.setMinHeight(34);
        menuHost.setPrefHeight(34);
        HBox.setHgrow(menuHost, Priority.ALWAYS);

        titleLabel = new Label(stage.getTitle());
        titleLabel.getStyleClass().add("fxe-titlebar-title");
        titleLabel.setMaxWidth(Double.MAX_VALUE);
        titleLabel.setVisible(pendingMenu == null);
        titleLabel.setManaged(pendingMenu == null);

        Button minimizeButton = createChromeButton("fxe-chrome-min", "\uE921");
        maximizeButton = createChromeButton("fxe-chrome-max", "\uE922");
        Button closeButton = createChromeButton("fxe-chrome-close", "\uE8BB");

        HBox windowButtons = new HBox(minimizeButton, maximizeButton, closeButton);
        windowButtons.getStyleClass().add("fxe-titlebar-buttons");
        windowButtons.setAlignment(Pos.CENTER_RIGHT);
        windowButtons.setMinHeight(34);
        windowButtons.setPrefHeight(34);

        if (pendingHeader != null) {
            titleBar.getChildren().addAll(pendingHeader, windowButtons);
            HBox.setHgrow(pendingHeader, Priority.ALWAYS);
        } else if (pendingMenu != null) {
            titleBar.getChildren().addAll(menuHost, windowButtons);
        } else {
            titleBar.getChildren().addAll(titleLabel, menuHost, windowButtons);
        }

        root.setTop(titleBar);
        root.setCenter(content);

        if (!stage.getScene().getStylesheets().contains(STYLE_SHEET)) {
            stage.getScene().getStylesheets().add(0, STYLE_SHEET);
        }

        stage.getScene().setRoot(root);
        stage.setResizable(true);

        titleLabel.textProperty().bind(stage.titleProperty());

        if (pendingHeader != null) {
            // header already contains menu and title
        } else if (pendingMenu != null) {
            attachMenu(pendingMenu);
        }

        minimizeButton.setOnAction(e -> stage.setIconified(true));
        maximizeButton.setOnAction(e -> toggleMaximize());
        closeButton.setOnAction(e -> stage.fireEvent(new javafx.stage.WindowEvent(stage, javafx.stage.WindowEvent.WINDOW_CLOSE_REQUEST)));

        titleBar.setOnMousePressed(e -> {
            if (e.getButton() != MouseButton.PRIMARY || e.getTarget() instanceof Button) {
                return;
            }

            if (e.getClickCount() == 2) {
                toggleMaximize();
                return;
            }

            if (maximized) {
                restore();
            }

            dragOffsetX = e.getScreenX() - stage.getX();
            dragOffsetY = e.getScreenY() - stage.getY();
        });

        titleBar.setOnMouseDragged(e -> {
            if (e.getButton() != MouseButton.PRIMARY || maximized) {
                return;
            }

            stage.setX(e.getScreenX() - dragOffsetX);
            stage.setY(e.getScreenY() - dragOffsetY);
        });

        stage.maximizedProperty().addListener((obs, oldVal, newVal) -> {
            maximized = newVal;
            if (maximizeButton != null) {
                maximizeButton.setText(newVal ? "\uE923" : "\uE922");
            }
        });
    }

    private static Button createChromeButton(String styleClass, String text) {
        Button button = new Button(text);
        button.getStyleClass().addAll("fxe-chrome-button", styleClass);

        String family = "Segoe Fluent Icons";
        Font font = Font.font(family, 10);
        if (!font.getFamily().equals(family)) {
            font = Font.font("Segoe MDL2 Assets", 10);
        }
        button.setFont(font);

        button.setMinSize(46, 34);
        button.setPrefSize(46, 34);
        button.setMaxSize(46, 34);
        return button;
    }

    private void attachMenu(Node menu) {
        if (menuHost == null || menu == null) {
            return;
        }

        menuHost.getChildren().setAll(menu);

        if (titleLabel != null) {
            titleLabel.setVisible(false);
            titleLabel.setManaged(false);
        }
    }

    public void toggleMaximize() {
        if (maximized) {
            restore();
        } else {
            maximize();
        }
    }

    public void maximize() {
        restoreX = stage.getX();
        restoreY = stage.getY();
        restoreW = stage.getWidth();
        restoreH = stage.getHeight();
        maximized = true;
        stage.setMaximized(true);
        applyMaximizeBounds();
        if (maximizeButton != null) {
            maximizeButton.setText("\uE923");
        }
    }

    public void restore() {
        maximized = false;
        stage.setMaximized(false);
        stage.setX(restoreX);
        stage.setY(restoreY);
        stage.setWidth(restoreW);
        stage.setHeight(restoreH);
        if (maximizeButton != null) {
            maximizeButton.setText("\uE922");
        }
    }

    private void applyMaximizeBounds() {
        Screen screen = Screen.getScreensForRectangle(stage.getX(), stage.getY(), 1, 1).stream()
                .findFirst()
                .orElse(Screen.getPrimary());

        Rectangle2D bounds = screen.getVisualBounds();
        stage.setX(bounds.getMinX());
        stage.setY(bounds.getMinY());
        stage.setWidth(bounds.getWidth());
        stage.setHeight(bounds.getHeight());
    }

    private static boolean isWindows() {
        String os = System.getProperty("os.name", "");
        return os.toLowerCase().contains("win");
    }
}
