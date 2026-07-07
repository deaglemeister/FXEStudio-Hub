package org.fxe.console;

import javafx.geometry.Insets;
import javafx.scene.control.Button;
import javafx.scene.control.ComboBox;
import javafx.scene.control.TextArea;
import javafx.scene.control.Tooltip;
import javafx.scene.layout.Priority;
import javafx.scene.layout.VBox;
import javafx.scene.text.Text;
import javafx.scene.text.TextFlow;

public class FxeConsoleView extends VBox {
    private final TextArea textArea;
    private final FxeLogger logger;
    private final ComboBox<String> filterBox;

    public FxeConsoleView() {
        this.logger = new FxeLogger();
        this.textArea = new TextArea();
        this.filterBox = new ComboBox<>();

        getStyleClass().add("fxe-console-view");
        setSpacing(6);
        setPadding(new Insets(4));

        textArea.setEditable(false);
        textArea.setWrapText(true);
        textArea.getStyleClass().add("fxe-console-text");
        VBox.setVgrow(textArea, Priority.ALWAYS);

        filterBox.getItems().addAll("Все", "FXE", "APP", "Ошибки", "Предупреждения", "Debug");
        filterBox.setValue("Все");
        filterBox.setOnAction(e -> applyFilter(filterBox.getValue()));

        Button clearBtn = new Button("Очистить");
        clearBtn.setOnAction(e -> clear());

        getChildren().addAll(filterBox, textArea, clearBtn);

        logger.setListener(event -> appendEvent(event));
    }

    public FxeLogger getLogger() {
        return logger;
    }

    public void appendEvent(FxeLogEvent event) {
        if (event == null) {
            return;
        }

        String line = event.formatLine() + System.lineSeparator();
        textArea.appendText(line);
    }

    public void clear() {
        textArea.clear();
        logger.clear();
    }

    public String exportText() {
        return logger.exportText();
    }

    private void applyFilter(String value) {
        if (value == null) {
            logger.setFilterMode(FxeLogger.FilterMode.ALL);
            return;
        }

        switch (value) {
            case "FXE":
                logger.setFilterMode(FxeLogger.FilterMode.FXE);
                break;
            case "APP":
                logger.setFilterMode(FxeLogger.FilterMode.APP);
                break;
            case "Ошибки":
                logger.setFilterMode(FxeLogger.FilterMode.ERRORS);
                break;
            case "Предупреждения":
                logger.setFilterMode(FxeLogger.FilterMode.WARNINGS);
                break;
            case "Debug":
                logger.setFilterMode(FxeLogger.FilterMode.DEBUG);
                logger.setShowDebug(true);
                break;
            default:
                logger.setFilterMode(FxeLogger.FilterMode.ALL);
                break;
        }
    }
}
