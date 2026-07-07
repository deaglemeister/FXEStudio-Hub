package org.fxe.console;

import java.io.BufferedReader;
import java.io.IOException;
import java.io.InputStream;
import java.io.InputStreamReader;
import java.nio.charset.Charset;

public class ProcessLogReader {
    public interface LineConsumer {
        void onLine(String line);
    }

    public static void readStreams(Process process, LineConsumer stdout, LineConsumer stderr, Runnable onComplete) {
        Thread outThread = new Thread(new StreamReader(process.getInputStream(), stdout), "fxe-console-stdout");
        Thread errThread = new Thread(new StreamReader(process.getErrorStream(), stderr), "fxe-console-stderr");
        Thread waitThread = new Thread(() -> {
            try {
                int code = process.waitFor();
                if (outThread.isAlive()) {
                    outThread.join(1500);
                }
                if (errThread.isAlive()) {
                    errThread.join(1500);
                }
                if (onComplete != null) {
                    onComplete.run();
                }
            } catch (InterruptedException e) {
                Thread.currentThread().interrupt();
            }
        }, "fxe-console-wait");

        outThread.setDaemon(true);
        errThread.setDaemon(true);
        waitThread.setDaemon(true);

        outThread.start();
        errThread.start();
        waitThread.start();
    }

    private static class StreamReader implements Runnable {
        private final InputStream stream;
        private final LineConsumer consumer;

        private StreamReader(InputStream stream, LineConsumer consumer) {
            this.stream = stream;
            this.consumer = consumer;
        }

        @Override
        public void run() {
            try (BufferedReader reader = new BufferedReader(new InputStreamReader(stream, Charset.forName("UTF-8")))) {
                String line;
                while ((line = reader.readLine()) != null) {
                    if (consumer != null) {
                        consumer.onLine(line);
                    }
                }
            } catch (IOException ignored) {
            }
        }
    }
}
