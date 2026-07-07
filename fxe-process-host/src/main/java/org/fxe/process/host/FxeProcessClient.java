package org.fxe.process.host;

import org.fxe.process.FxeChannel;
import org.fxe.process.FxeJsonLine;

import java.io.BufferedReader;
import java.io.BufferedWriter;
import java.io.File;
import java.io.IOException;
import java.io.InputStreamReader;
import java.io.OutputStreamWriter;
import java.nio.charset.StandardCharsets;
import java.util.LinkedHashMap;
import java.util.Map;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import java.util.concurrent.atomic.AtomicInteger;
import java.util.function.Consumer;

/**
 * Запуск helper-процессов из IDE (не в JavaFX UI thread).
 */
public class FxeProcessClient {
    public interface Listener {
        void onLine(String line);
        void onExit(int code);
    }

    private static final ExecutorService EXECUTOR = Executors.newCachedThreadPool(r -> {
        Thread t = new Thread(r, "fxe-process-client");
        t.setDaemon(true);
        return t;
    });

    private final FxeChannel channel;
    private final File jarFile;
    private Process process;
    private BufferedWriter writer;
    private final AtomicInteger seq = new AtomicInteger();

    public FxeProcessClient(FxeChannel channel, File jarFile) {
        this.channel = channel;
        this.jarFile = jarFile;
    }

    public FxeChannel getChannel() {
        return channel;
    }

    public boolean isRunning() {
        Process p = process;
        return p != null && p.isAlive();
    }

    public synchronized void start(Listener listener) throws IOException {
        if (isRunning()) {
            return;
        }

        ProcessBuilder pb = new ProcessBuilder("java", "-Dfile.encoding=UTF-8", "-jar", jarFile.getAbsolutePath());
        pb.redirectErrorStream(true);
        process = pb.start();

        writer = new BufferedWriter(new OutputStreamWriter(process.getOutputStream(), StandardCharsets.UTF_8));

        EXECUTOR.execute(() -> readLoop(process, listener));
    }

    public synchronized void send(String cmd, Map<String, String> fields) throws IOException {
        if (!isRunning() || writer == null) {
            throw new IOException("process not running");
        }

        Map<String, Object> payload = new LinkedHashMap<>();
        payload.put("type", "command");
        payload.put("id", String.valueOf(seq.incrementAndGet()));
        payload.put("cmd", cmd);

        if (fields != null) {
            payload.putAll(fields);
        }

        writer.write(FxeJsonLine.encode(payload));
        writer.write('\n');
        writer.flush();
    }

    public synchronized void stop() {
        try {
            if (isRunning()) {
                send("stop", null);
            }
        } catch (IOException ignored) {
        }

        Process p = process;
        if (p != null) {
            p.destroy();
        }

        process = null;
        writer = null;
    }

    private void readLoop(Process process, Listener listener) {
        try (BufferedReader reader = new BufferedReader(new InputStreamReader(process.getInputStream(), StandardCharsets.UTF_8))) {
            String line;
            while ((line = reader.readLine()) != null) {
                if (listener != null) {
                    listener.onLine(line);
                }
            }
        } catch (IOException ignored) {
        }

        try {
            int code = process.waitFor();
            if (listener != null) {
                listener.onExit(code);
            }
        } catch (InterruptedException ignored) {
            Thread.currentThread().interrupt();
        }
    }

    public static File resolveHelperJar(File installLibDir, String jarName) {
        File direct = new File(installLibDir, jarName);
        if (direct.isFile()) {
            return direct;
        }

        File helpers = new File(installLibDir, "helpers/" + jarName);
        return helpers;
    }
}
