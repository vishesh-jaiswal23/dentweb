<?php
declare(strict_types=1);

final class ChatHistoryStore
{
    private const MAX_HISTORY_LENGTH = 100;

    private string $storagePath;
    private string $lockPath;
    /** @var resource|null */
    private $lockHandle = null;

    public function __construct(?string $storagePath = null)
    {
        $this->storagePath = $storagePath ?? __DIR__ . '/../storage/ai_chat_history.json';
        $this->lockPath = $this->storagePath . '.lock';
        $this->ensureFileExists();
    }

    public function __destruct()
    {
        $this->releaseLock();
    }

    public function getHistory(): array
    {
        return $this->read();
    }

    public function addMessage(array $message): array
    {
        $this->acquireLock();
        try {
            $history = $this->read();
            $history[] = $message;

            // Trim history if it gets too long
            if (count($history) > self::MAX_HISTORY_LENGTH) {
                $history = array_slice($history, -self::MAX_HISTORY_LENGTH);
            }

            $this->write($history);
            return $history;
        } finally {
            $this->releaseLock();
        }
    }

    public function clearHistory(): void
    {
        $this->acquireLock();
        try {
            $this->write([]);
        } finally {
            $this->releaseLock();
        }
    }

    private function ensureFileExists(): void
    {
        if (!file_exists($this->storagePath)) {
            $this->write([]);
        }
    }

    private function read(): array
    {
        $content = file_get_contents($this->storagePath);
        if ($content === false || $content === '') {
            return [];
        }
        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    private function write(array $data): void
    {
        file_put_contents($this->storagePath, json_encode($data, JSON_PRETTY_PRINT));
    }

    private function acquireLock(): void
    {
        if ($this->lockHandle !== null) {
            return;
        }
        $handle = fopen($this->lockPath, 'c+');
        if ($handle === false) {
            throw new RuntimeException('Unable to open chat history lock.');
        }
        if (flock($handle, LOCK_EX) !== true) {
            fclose($handle);
            throw new RuntimeException('Unable to acquire chat history lock.');
        }
        $this->lockHandle = $handle;
    }

    private function releaseLock(): void
    {
        if ($this->lockHandle === null) {
            return;
        }
        flock($this->lockHandle, LOCK_UN);
        fclose($this->lockHandle);
        $this->lockHandle = null;
    }
}

function chat_history_store(?string $storagePath = null): ChatHistoryStore
{
    static $instance = null;
    if ($instance === null) {
        $instance = new ChatHistoryStore($storagePath);
    }
    return $instance;
}
