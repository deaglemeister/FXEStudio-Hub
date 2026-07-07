package org.develnext.jphp.gui.designer.classes;

import javafx.application.Platform;
import javafx.scene.Node;
import javafx.stage.Stage;
import javafx.stage.StageStyle;
import net.yetihafen.javafx.customcaption.CaptionConfiguration;
import net.yetihafen.javafx.customcaption.CustomCaption;
import net.yetihafen.javafx.customcaption.internal.NativeUtilities;
import net.yetihafen.javafx.customcaption.internal.TitleBarDragRegion;
import org.develnext.jphp.gui.designer.GuiDesignerExtension;
import org.develnext.jphp.ext.javafx.classes.UXForm;
import org.develnext.jphp.gui.designer.window.FxeWindowChrome;
import php.runtime.annotation.Reflection.Namespace;
import php.runtime.annotation.Reflection.Signature;
import php.runtime.env.Environment;
import php.runtime.lang.BaseObject;
import php.runtime.reflection.ClassEntity;

import javafx.scene.paint.Color;

@Namespace(GuiDesignerExtension.NS)
public class FxeMainWindowChrome extends BaseObject {

    private static final Color CAPTION_BG = Color.web("#2B2D30");
    private static final Color CAPTION_ICON = Color.web("#DFE1E5");
    private static final Color CAPTION_HOVER = Color.web("#393B40");
    private static final Color CAPTION_CLOSE_HOVER = Color.web("#E81123");
    private static final Color CAPTION_BORDER = Color.web("#393B40");

    private static final String APPLIED_KEY = "fxe-main-window-chrome";

    public FxeMainWindowChrome(Environment env, ClassEntity clazz) {
        super(env, clazz);
    }

    /**
     * MainForm: Win11 — CustomCaption (snap, контекстное меню, закругления).
     * Остальные ОС — кастомный UNDECORATED chrome.
     */
    @Signature
    public static void apply(UXForm form, Node titleBarRow) {
        if (form == null || titleBarRow == null) {
            return;
        }

        Stage stage = form.getWrappedObject();
        if (stage == null || stage.getScene() == null) {
            return;
        }

        if (isWindows()) {
            applyWindowsNative(stage, titleBarRow);
        } else {
            applyCustomChrome(stage, titleBarRow);
        }
    }

    @Signature
    public static void refreshHitTest(UXForm form) {
        if (form == null) {
            return;
        }
        Stage stage = form.getWrappedObject();
        if (stage != null) {
            CustomCaption.refreshHitTest(stage);
        }
    }

    /**
     * Тёмный нативный titlebar для диалогов/прочих окон IDE (только Windows).
     */
    @Signature
    public static void applyDialog(UXForm form) {
        if (form == null || !isWindows()) {
            return;
        }

        final Stage stage = form.getWrappedObject();
        if (stage == null) {
            return;
        }

        final Runnable task = new Runnable() {
            @Override
            public void run() {
                try {
                    NativeUtilities.setImmersiveDarkMode(stage, true);
                    NativeUtilities.customizeCation(stage, CAPTION_BG);
                    NativeUtilities.setBorderColor(stage, CAPTION_BORDER);
                    NativeUtilities.setWindowCornerPreference(stage, 2);
                } catch (Throwable ignore) {
                    // не критично для диалогов
                }
            }
        };

        if (stage.isShowing()) {
            Platform.runLater(task);
        } else {
            stage.showingProperty().addListener((obs, oldVal, newVal) -> {
                if (newVal) {
                    Platform.runLater(task);
                }
            });
        }
    }

    private static void applyWindowsNative(final Stage stage, final Node titleBarRow) {
        if (!stage.isShowing()) {
            stage.initStyle(StageStyle.DECORATED);
        }

        Runnable task = new Runnable() {
            @Override
            public void run() {
                if (Boolean.TRUE.equals(stage.getProperties().get(APPLIED_KEY))) {
                    return;
                }
                stage.getProperties().put(APPLIED_KEY, true);

                try {
                    CaptionConfiguration config = new CaptionConfiguration(34, CAPTION_ICON, CAPTION_BG)
                            .setButtonHoverColor(CAPTION_HOVER)
                            .setCloseButtonHoverColor(CAPTION_CLOSE_HOVER)
                            .setIconHoverColor(Color.WHITE);

                    config.setCaptionDragRegion(new TitleBarDragRegion(titleBarRow));

                    CustomCaption.useForStage(stage, config);
                    stage.setMinWidth(800);
                    stage.setMinHeight(500);
                    NativeUtilities.setImmersiveDarkMode(stage, true);
                    NativeUtilities.customizeCation(stage, CAPTION_BG);
                    NativeUtilities.setBorderColor(stage, CAPTION_BORDER);
                    NativeUtilities.setWindowCornerPreference(stage, 2);
                } catch (Throwable t) {
                    stage.getProperties().remove(APPLIED_KEY);
                    t.printStackTrace();
                    applyCustomChrome(stage, titleBarRow);
                }
            }
        };

        if (stage.isShowing()) {
            Platform.runLater(task);
        } else {
            stage.setOnShown(e -> Platform.runLater(task));
        }
    }

    private static void applyCustomChrome(Stage stage, Node titleBarRow) {
        if (!stage.isShowing()) {
            stage.initStyle(StageStyle.UNDECORATED);
        }

        FxeWindowChrome chrome = FxeWindowChrome.install(stage);
        if (chrome != null) {
            chrome.setTitleBarContent(titleBarRow);
        }
    }

    private static boolean isWindows() {
        String os = System.getProperty("os.name", "");
        return os.toLowerCase().contains("win");
    }
}
