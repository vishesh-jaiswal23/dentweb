<?php
require_once __DIR__ . '/config.php';

function portal_log_write(string $filename, array $record): void {
    $directory = portal_storage_path('logs');
    if (!is_dir($directory)) {
        @mkdir($directory, 0755, true);
    }
    $record['time'] = $record['time'] ?? time();
    $payload = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($payload === false) {
        return;
    }
    $payload .= PHP_EOL;
    $path = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($filename, DIRECTORY_SEPARATOR);
    @file_put_contents($path, $payload, FILE_APPEND | LOCK_EX);
}

function portal_log_protected_page_access(string $pageId, bool $allowed, ?array $user = null): void {
    $user = is_array($user) ? $user : [];
    $record = [
        'time' => time(),
        'page' => $pageId,
        'allowed' => $allowed ? 1 : 0,
        'role' => $user['role'] ?? 'guest',
        'user' => $user['email'] ?? null,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
    ];
    portal_log_write('protected-access.log', $record);
}

function portal_recent_denied_access_summary(int $windowMinutes = 120): array {
    $windowMinutes = max(1, $windowMinutes);
    $path = portal_storage_path('logs/protected-access.log');
    if (!is_file($path)) {
        return [
            'total' => 0,
            'window' => $windowMinutes,
            'pages' => [],
            'latest' => null,
        ];
    }

    $threshold = time() - ($windowMinutes * 60);
    $total = 0;
    $latest = null;
    $pages = [];

    try {
        $file = new SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $lastLine = (int) $file->key();
        $start = max(0, $lastLine - 600);
        $file->seek($start);
        while (!$file->eof()) {
            $line = trim((string) $file->current());
            $file->next();
            if ($line === '') {
                continue;
            }
            $entry = json_decode($line, true);
            if (!is_array($entry)) {
                continue;
            }
            $time = (int) ($entry['time'] ?? 0);
            if ($time < $threshold) {
                continue;
            }
            if ((int) ($entry['allowed'] ?? 0) !== 0) {
                continue;
            }
            $page = (string) ($entry['page'] ?? '');
            $pages[$page] = ($pages[$page] ?? 0) + 1;
            $total++;
            if ($latest === null || $time > $latest) {
                $latest = $time;
            }
        }
    } catch (Throwable $exception) {
        return [
            'total' => 0,
            'window' => $windowMinutes,
            'pages' => [],
            'latest' => null,
        ];
    }

    return [
        'total' => $total,
        'window' => $windowMinutes,
        'pages' => $pages,
        'latest' => $latest,
    ];
}
