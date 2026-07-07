<?php
namespace ide\systems;

use ide\Logger;
use ide\utils\Json;
use php\lang\Process;
use php\lang\Thread;
use php\util\Scanner;

/**
 * JSON-lines IPC с fxe-language-server (stdin/stdout).
 */
class FxeLanguageServerIpc
{
    /** @var int */
    protected static $seq = 0;

    /** @var array */
    protected static $callbacks = [];

    /** @var bool */
    protected static $readerStarted = false;

    public static function resetReader()
    {
        static::$readerStarted = false;
        static::$callbacks = [];
    }

    public static function ensureReader()
    {
        if (static::$readerStarted) {
            return;
        }

        $process = FxeProcessSystem::getProcess('lsp');
        if (!$process) {
            return;
        }

        static::$readerStarted = true;

        $thread = new Thread(function () use ($process) {
            try {
                $scanner = new Scanner($process->getInput());

                while ($scanner->hasNextLine()) {
                    $line = trim($scanner->nextLine());

                    if ($line === '' || $line[0] !== '{') {
                        if (strpos($line, '[FXE]') === 0) {
                            Logger::debug($line);
                        }
                        continue;
                    }

                    try {
                        $data = Json::decode($line);
                    } catch (\Exception $e) {
                        continue;
                    }

                    if (!is_array($data)) {
                        continue;
                    }

                    if (($data['type'] ?? '') === 'response') {
                        $id = (string)($data['id'] ?? '');
                        if ($id !== '' && isset(static::$callbacks[$id])) {
                            $callback = static::$callbacks[$id];
                            unset(static::$callbacks[$id]);
                            $ok = (bool)($data['ok'] ?? false);
                            $message = (string)($data['message'] ?? '');
                            uiLater(function () use ($callback, $ok, $message) {
                                $callback($ok, $message);
                            });
                        }
                    }

                    if (($data['type'] ?? '') === 'progress') {
                        $stage = (string)($data['stage'] ?? '');
                        $percent = (int)($data['percent'] ?? 0);

                        if ($stage === 'index') {
                            uiLater(function () use ($percent) {
                                FxeIndexingStatusSystem::onIndexProgress($percent, 'Indexing project...');
                            });
                        }
                    }
                }
            } catch (\Exception $e) {
                Logger::warn('[FXE][LSP] reader stopped: ' . $e->getMessage());
                static::$readerStarted = false;

                uiLater(function () use ($e) {
                    FxeIndexingStatusSystem::onLspDisconnected('LSP disconnected');
                });
            }
        });
        $thread->setName('fxe-lsp-ipc-reader');
        $thread->start();
    }

    /**
     * @param string $cmd
     * @param array $fields
     * @param callable|null $callback function(bool $ok, string $message)
     */
    public static function request($cmd, array $fields = [], callable $callback = null)
    {
        static::ensureReader();

        $process = FxeProcessSystem::getProcess('lsp');
        if (!$process) {
            if ($callback) {
                $callback(false, '');
            }
            return;
        }

        $id = (string)(++static::$seq);
        $payload = array_merge([
            'type' => 'command',
            'id' => $id,
            'cmd' => $cmd,
        ], $fields);

        if ($callback) {
            static::$callbacks[$id] = $callback;
        }

        try {
            $json = Json::encode($payload);
            $output = $process->getOutput();
            $output->write($json . "\n");

            if (method_exists($output, 'flush')) {
                $output->flush();
            }
        } catch (\Exception $e) {
            Logger::warn('[FXE][LSP] send failed: ' . $e->getMessage());
            unset(static::$callbacks[$id]);
            if ($callback) {
                $callback(false, '');
            }
        }
    }
}
