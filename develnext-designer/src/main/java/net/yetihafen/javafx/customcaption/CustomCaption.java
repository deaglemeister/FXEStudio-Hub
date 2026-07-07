package net.yetihafen.javafx.customcaption;

import javafx.scene.paint.Color;
import javafx.stage.Stage;
import net.yetihafen.javafx.customcaption.internal.NativeUtilities;
import net.yetihafen.javafx.customcaption.internal.StageManager;

/**
 * Vendored from javafx-customcaption (Apache-2.0), adapted for Java 8.
 * @see <a href="https://github.com/YetiHafen/javafx-customcaption">javafx-customcaption</a>
 */
public class CustomCaption {

    private static final StageManager stageManager = new StageManager();

    public static void useForStage(Stage stage, CaptionConfiguration config) {
        stageManager.registerStage(stage, config);
    }

    public static void useForStage(Stage stage) {
        useForStage(stage, CaptionConfiguration.DEFAULT_CONFIG);
    }

    public static void removeCustomization(Stage stage) {
        stageManager.releaseStage(stage);
    }

    public static boolean setCaptionColor(Stage stage, Color color) {
        return NativeUtilities.setCaptionColor(stage, color);
    }

    public static boolean setImmersiveDarkMode(Stage stage, boolean enabled) {
        return NativeUtilities.setImmersiveDarkMode(stage, enabled);
    }

    public static boolean setBorderColor(Stage stage, Color color) {
        return NativeUtilities.setBorderColor(stage, color);
    }

    public static void refreshHitTest(Stage stage) {
        stageManager.refreshHitTest(stage);
    }
}
