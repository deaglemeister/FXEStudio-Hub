package net.yetihafen.javafx.customcaption.internal;

import javafx.stage.Stage;
import net.yetihafen.javafx.customcaption.CaptionConfiguration;

import java.util.HashMap;

public class StageManager {

    private final HashMap<Stage, CustomizedStage> customizedStages = new HashMap<Stage, CustomizedStage>();

    public void registerStage(Stage stage, CaptionConfiguration config) {
        if (customizedStages.containsKey(stage)) {
            throw new IllegalArgumentException("stage was already registered");
        }

        CustomizedStage customStage = new CustomizedStage(stage, config);
        customizedStages.put(stage, customStage);
    }

    public void releaseStage(Stage stage) {
        CustomizedStage customizedStage = customizedStages.get(stage);
        if (customizedStage == null) {
            throw new IllegalArgumentException("cannot remove customization if stage was not customized");
        }
        customizedStage.release();
        customizedStages.remove(stage);
    }

    public void refreshHitTest(Stage stage) {
        CustomizedStage customizedStage = customizedStages.get(stage);
        if (customizedStage != null) {
            customizedStage.refreshHitTestCache();
        }
    }
}
