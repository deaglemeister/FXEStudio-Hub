package org.fxe.process;

import java.io.BufferedReader;
import java.io.BufferedWriter;
import java.io.IOException;
import java.io.InputStreamReader;
import java.io.OutputStreamWriter;
import java.io.PrintWriter;
import java.nio.charset.StandardCharsets;
import java.util.LinkedHashMap;
import java.util.Map;

/**
 * JSON-lines протокол между IDE и helper-процессами.
 */
public class FxeProcessIo {
    private final BufferedReader reader;
    private final PrintWriter writer;
    private final FxeChannel channel;

    public FxeProcessIo(FxeChannel channel) {
        this(channel, System.in, System.out);
    }

    public FxeProcessIo(FxeChannel channel, java.io.InputStream in, java.io.OutputStream out) {
        this.channel = channel;
        this.reader = new BufferedReader(new InputStreamReader(in, StandardCharsets.UTF_8));
        this.writer = new PrintWriter(new BufferedWriter(new OutputStreamWriter(out, StandardCharsets.UTF_8)), true);
    }

    public FxeChannel getChannel() {
        return channel;
    }

    public void log(String level, String message) {
        writer.println(FxeJsonLine.encode(map(
                "type", "log",
                "channel", channel.getTag(),
                "level", level,
                "message", message
        )));
        writer.println(channel.format(level, message));
    }

    public void info(String message) { log("INFO", message); }
    public void warn(String message) { log("WARN", message); }
    public void error(String message) { log("ERROR", message); }
    public void debug(String message) { log("DEBUG", message); }

    public void progress(String stage, int current, int total) {
        int percent = total > 0 ? (int) ((current * 100L) / total) : 0;
        writer.println(FxeJsonLine.encode(map(
                "type", "progress",
                "stage", stage == null ? "" : stage,
                "current", current,
                "total", total,
                "percent", percent
        )));
    }

    public void respond(String id, boolean ok, String message) {
        Map<String, Object> fields = new LinkedHashMap<>();
        fields.put("type", "response");
        fields.put("id", id == null ? "" : id);
        fields.put("ok", ok);
        fields.put("message", message == null ? "" : message);
        writer.println(FxeJsonLine.encode(fields));
    }

    public Map<String, String> readCommand() throws IOException {
        String line;
        while ((line = reader.readLine()) != null) {
            line = line.trim();
            if (line.isEmpty()) {
                continue;
            }
            return FxeJsonLine.decode(line);
        }
        return null;
    }

    public static Map<String, Object> map(Object... kv) {
        Map<String, Object> map = new LinkedHashMap<>();
        for (int i = 0; i + 1 < kv.length; i += 2) {
            map.put(String.valueOf(kv[i]), kv[i + 1]);
        }
        return map;
    }
}
