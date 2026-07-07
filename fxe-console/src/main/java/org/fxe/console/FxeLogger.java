package org.fxe.console;

import java.util.ArrayList;
import java.util.List;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

public class FxeLogger {
    public interface Listener {
        void onLog(FxeLogEvent event);
    }

    public enum FilterMode {
        ALL,
        FXE,
        APP,
        ERRORS,
        WARNINGS,
        DEBUG
    }

    private final List<FxeLogEvent> history = new ArrayList<>();
    private final SmartErrorExplainer explainer;
    private Listener listener;
    private FilterMode filterMode = FilterMode.ALL;
    private boolean showDebug = false;
    private long startedAt;

    public FxeLogger() {
        this.explainer = new SmartErrorExplainer(this);
    }

    public void setListener(Listener listener) {
        this.listener = listener;
    }

    public void setFilterMode(FilterMode filterMode) {
        this.filterMode = filterMode == null ? FilterMode.ALL : filterMode;
    }

    public FilterMode getFilterMode() {
        return filterMode;
    }

    public void setShowDebug(boolean showDebug) {
        this.showDebug = showDebug;
    }

    public boolean isShowDebug() {
        return showDebug;
    }

    public void markStart() {
        startedAt = System.currentTimeMillis();
    }

    public void info(String message) { log(FxeLogLevel.INFO, message); }
    public void ok(String message) { log(FxeLogLevel.OK, message); }
    public void success(String message) { log(FxeLogLevel.SUCCESS, message); }
    public void warn(String message) { log(FxeLogLevel.WARN, message); }
    public void error(String message) { log(FxeLogLevel.ERROR, message); }
    public void debug(String message) { log(FxeLogLevel.DEBUG, message); }
    public void javaLog(String message) { log(FxeLogLevel.JAVA, message); }
    public void jphp(String message) { log(FxeLogLevel.JPHP, message); }
    public void bundle(String message) { log(FxeLogLevel.BUNDLE, message); }
    public void run(String message) { log(FxeLogLevel.RUN, message); }
    public void build(String message) { log(FxeLogLevel.BUILD, message); }
    public void appOut(String message) { log(FxeLogLevel.APP_OUT, message); }
    public void appErr(String message) { log(FxeLogLevel.APP_ERR, message); }
    public void fix(String message) { log(FxeLogLevel.FIX, message); }
    public void cause(String message) { log(FxeLogLevel.CAUSE, message); }
    public void action(String message) { log(FxeLogLevel.ACTION, message); }

    public void log(FxeLogLevel level, String message) {
        if (message == null) {
            return;
        }

        if (level == FxeLogLevel.DEBUG && !showDebug) {
            return;
        }

        FxeLogEvent event = new FxeLogEvent(level, message);
        history.add(event);

        if (!passesFilter(event)) {
            return;
        }

        if (listener != null) {
            listener.onLog(event);
        }
    }

    public void processBuildLine(String line) {
        if (line == null) {
            return;
        }

        String text = line.trim();
        if (text.isEmpty()) {
            return;
        }

        if (text.startsWith(":apply-bundle")) {
            String bundle = extractQuoted(text);
            bundle("Подключён bundle: " + shortenBundle(bundle));
            return;
        }

        if (text.startsWith(":apply actions")) {
            String action = extractQuoted(text);
            build("Применение: " + action);
            return;
        }

        if (text.startsWith(":warn")) {
            warn(text.substring(5).trim());
            return;
        }

        if (text.startsWith("[TRACE]")) {
            if (showDebug) {
                debug(text.substring(7).trim());
            }
            return;
        }

        if (text.startsWith("[INFO]")) {
            info(text.substring(6).trim());
            return;
        }

        if (text.startsWith("[DEBUG]")) {
            if (showDebug) {
                debug(text.substring(7).trim());
            }
            return;
        }

        if (text.startsWith("[WARN]") || text.startsWith("[WARNING]")) {
            warn(text.replaceFirst("^\\[(WARN|WARNING)\\]\\s*", ""));
            return;
        }

        if (text.startsWith("[ERROR]") || text.startsWith("Fatal error")) {
            error(text);
            explainer.explain(text);
            return;
        }

        if (text.startsWith("> Run project") || text.startsWith("> ")) {
            run(text.substring(2).trim());
            return;
        }

        if (text.startsWith("-->")) {
            if (showDebug) {
                debug(text.substring(3).trim());
            }
            return;
        }

        if (text.startsWith(":")) {
            if (showDebug) {
                debug(text);
            }
            return;
        }

        build(text);
    }

    public void processAppLine(String line, boolean stderr) {
        if (line == null || line.trim().isEmpty()) {
            return;
        }

        String text = line.trim();

        if (stderr) {
            appErr(text);
        } else {
            if (text.startsWith("[INFO]")) {
                info(text.substring(6).trim());
                return;
            }
            if (text.startsWith("[DEBUG]")) {
                if (showDebug) {
                    debug(text.substring(7).trim());
                }
                return;
            }
            if (text.startsWith("[TRACE]")) {
                if (showDebug) {
                    debug(text.substring(7).trim());
                }
                return;
            }
            appOut(text);
        }

        explainer.explain(text);
    }

    public void finish(int exitCode, boolean hasError) {
        double seconds = startedAt > 0 ? (System.currentTimeMillis() - startedAt) / 1000.0 : 0;

        if (exitCode == 0 && !hasError) {
            if (seconds > 0) {
                success(String.format("Проект успешно запущен за %.1f сек", seconds));
            } else {
                success("Проект успешно запущен");
            }
        } else {
            error("Проект не запущен (код выхода: " + exitCode + ")");
            fix("Проверьте сообщения [CAUSE] и [FIX] выше");
            action("Откройте: Проект → Проверить проект");
        }
    }

    public List<FxeLogEvent> getHistory() {
        return new ArrayList<>(history);
    }

    public String exportText() {
        StringBuilder sb = new StringBuilder();
        for (FxeLogEvent event : history) {
            sb.append(event.formatLine()).append('\n');
        }
        return sb.toString();
    }

    public void clear() {
        history.clear();
    }

    public static String colorFor(FxeLogLevel level) {
        if (level == null) {
            return "#d1d5db";
        }

        switch (level) {
            case OK:
            case SUCCESS:
                return "#22c55e";
            case WARN:
                return "#f59e0b";
            case ERROR:
            case APP_ERR:
            case CAUSE:
                return "#ef4444";
            case FIX:
                return "#38bdf8";
            case ACTION:
                return "#60a5fa";
            case DEBUG:
                return "#6b7280";
            case INFO:
                return "#e5e7eb";
            case JAVA:
            case JPHP:
            case BUNDLE:
            case RUN:
            case BUILD:
                return "#c4b5fd";
            case APP_OUT:
            default:
                return "#d1d5db";
        }
    }

    private boolean passesFilter(FxeLogEvent event) {
        switch (filterMode) {
            case FXE:
                return event.isFxe();
            case APP:
                return event.isApp();
            case ERRORS:
                return event.isErrorLike();
            case WARNINGS:
                return event.isWarningLike();
            case DEBUG:
                return event.getLevel() == FxeLogLevel.DEBUG;
            case ALL:
            default:
                return true;
        }
    }

    private static String extractQuoted(String text) {
        Matcher m = Pattern.compile("\"([^\"]+)\"").matcher(text);
        return m.find() ? m.group(1) : text;
    }

    private static String shortenBundle(String bundle) {
        if (bundle == null) {
            return "";
        }

        int slash = Math.max(bundle.lastIndexOf('\\'), bundle.lastIndexOf('/'));
        if (slash >= 0 && slash < bundle.length() - 1) {
            return bundle.substring(slash + 1);
        }

        return bundle;
    }
}
