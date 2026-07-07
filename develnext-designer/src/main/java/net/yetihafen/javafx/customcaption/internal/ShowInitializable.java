package net.yetihafen.javafx.customcaption.internal;

import javafx.stage.Stage;

public interface ShowInitializable {
    /**
     * this function is designed to be called after
     * {@link Stage#show()} has been called to configure customizations
     * should only be called once per stage
     */
    void showInit();
}
