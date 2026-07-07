package org.fxe.console;

import java.util.regex.Matcher;
import java.util.regex.Pattern;

public class ErrorClassifier {
    public static class Result {
        public final String cause;
        public final String fix;
        public final String action;

        public Result(String cause, String fix, String action) {
            this.cause = cause;
            this.fix = fix;
            this.action = action;
        }
    }

    public static Result classify(String line) {
        if (line == null || line.trim().isEmpty()) {
            return null;
        }

        String text = line.trim();

        if (contains(text, "ClassNotFoundException") || matches(text, "Class '([^']+)' not found")) {
            String cls = extractClass(text);
            return new Result(
                    cls != null ? "Не найден класс: " + cls : "Не найден Java/JPHP класс",
                    "Проверьте подключённые bundles и зависимости проекта",
                    "Откройте: Проект → Проверить проект"
            );
        }

        if (contains(text, "NoClassDefFoundError")) {
            return new Result(
                    "Класс был найден при сборке, но отсутствует при запуске",
                    "Проверьте runtime, classpath и bundles",
                    "Пересоберите проект и проверьте зависимости"
            );
        }

        if (contains(text, "Could not determine java version")) {
            return new Result(
                    "Старый Gradle не поддерживает выбранную версию Java",
                    "Для legacy-проекта нужна Java 8",
                    "Установите JDK 8 и укажите JAVA_HOME"
            );
        }

        if (contains(text, "FileNotFoundException") || contains(text, "file not found")) {
            return new Result(
                    "Не найден файл",
                    "Проверьте путь к файлу и наличие ресурса в assets",
                    "Проверьте пути в коде и структуру проекта"
            );
        }

        if (contains(text, "Fatal error") || contains(text, "[ERROR]")) {
            return new Result(
                    "Критическая ошибка выполнения",
                    "Проверьте стек вызовов выше и исправьте указанный файл",
                    "Кликните по сообщению об ошибке для перехода к коду"
            );
        }

        if (contains(text, "FXEGuiExtension") || contains(text, "UXBadge") || contains(text, "UXIconButton") || contains(text, "UXSearchBox")) {
            return new Result(
                    "Не подключено расширение FXE GUI (fxe-gui-ext)",
                    "Закройте и снова откройте проект, чтобы подтянуть fxe-gui-ext.jar",
                    "Перезапустите IDE после sync-ide-install"
            );
        }

        return null;
    }

    private static boolean contains(String text, String needle) {
        return text.toLowerCase().contains(needle.toLowerCase());
    }

    private static boolean matches(String text, String regex) {
        return Pattern.compile(regex, Pattern.CASE_INSENSITIVE).matcher(text).find();
    }

    private static String extractClass(String text) {
        Matcher m = Pattern.compile("Class '([^']+)' not found", Pattern.CASE_INSENSITIVE).matcher(text);
        if (m.find()) {
            return m.group(1);
        }

        m = Pattern.compile("ClassNotFoundException:\\s*([^\\s]+)").matcher(text);
        if (m.find()) {
            return m.group(1);
        }

        return null;
    }
}
