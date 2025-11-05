<?php
declare(strict_types=1);

final class AiStudioSettings
{
    private const DEFAULTS = [
        'enabled' => false,
        'api_key' => '',
        'text_model' => 'gemini-2.5-flash',
        'image_model' => 'gemini-2.5-flash-image',
        'tts_model' => 'gemini-2.5-flash-preview-tts',
        'temperature' => 0.7,
        'max_tokens' => 1024,
    ];

    private string $storagePath;
    private string $lockPath;
    /** @var resource|null */
    private $lockHandle = null;

    public function __construct(?string $storagePath = null)
    {
        $this->storagePath = $storagePath ?? __DIR__ . '/../storage/ai_studio_settings.json';
        $this->lockPath = $this->storagePath . '.lock';
        $this->ensureFileExists();
    }

    public function __destruct()
    {
        $this->releaseLock();
    }

    public function getSettings(): array
    {
        return $this->read();
    }

    public function saveSettings(array $settings): array
    {
        $this->acquireLock();
        try {
            $current = $this->read();
            $updated = array_merge($current, $settings);
            $this->write($updated);
            return $updated;
        } finally {
            $this->releaseLock();
        }
    }

    private function ensureFileExists(): void
    {
        if (!file_exists($this->storagePath)) {
            $this->write(self::DEFAULTS);
        }
    }

    private function read(): array
    {
        $content = file_get_contents($this->storagePath);
        if ($content === false) {
            return self::DEFAULTS;
        }
        $data = json_decode($content, true);
        if (!is_array($data)) {
            return self::DEFAULTS;
        }
        return array_merge(self::DEFAULTS, $data);
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
            throw new RuntimeException('Unable to open AI Studio settings lock.');
        }
        if (flock($handle, LOCK_EX) !== true) {
            fclose($handle);
            throw new RuntimeException('Unable to acquire AI Studio settings lock.');
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

function ai_studio_settings(?string $storagePath = null): AiStudioSettings
{
    static $instance = null;
    if ($instance === null) {
        $instance = new AiStudioSettings($storagePath);
    }
    return $instance;
}
