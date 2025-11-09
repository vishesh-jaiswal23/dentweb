<?php
declare(strict_types=1);

function ai_storage_dir(): string
{
    $base = __DIR__ . '/../storage/ai';
    if (!is_dir($base)) {
        mkdir($base, 0775, true);
    }

    return $base;
}

function ai_settings_file(): string
{
    return ai_storage_dir() . '/settings.json';
}

function ai_settings_lock_file(): string
{
    return ai_storage_dir() . '/settings.lock';
}

function ai_settings_defaults(): array
{
    return [
        'enabled' => false,
        'api_key' => '',
        'models' => [
            'text' => 'gemini-2.5-flash',
            'image' => 'gemini-2.5-flash-image',
            'tts' => 'gemini-2.5-flash-preview-tts',
        ],
        'temperature' => 0.9,
        'max_tokens' => 1024,
        'updated_at' => null,
    ];
}

function ai_blog_draft_dir(): string
{
    $path = ai_storage_dir() . '/blog_drafts';
    if (!is_dir($path)) {
        mkdir($path, 0775, true);
    }

    return $path;
}

function ai_blog_draft_path(int $adminId): string
{
    $file = $adminId > 0 ? 'draft_' . $adminId . '.json' : 'draft_default.json';
    return ai_blog_draft_dir() . '/' . $file;
}

function ai_blog_draft_load(int $adminId): array
{
    $path = ai_blog_draft_path($adminId);
    if (!is_file($path)) {
        return [];
    }

    $contents = file_get_contents($path);
    if ($contents === false || trim($contents) === '') {
        return [];
    }

    try {
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $exception) {
        error_log('ai_blog_draft_load: failed to decode draft: ' . $exception->getMessage());
        return [];
    }

    return is_array($decoded) ? $decoded : [];
}

function ai_blog_draft_save(int $adminId, array $draft): void
{
    $payload = json_encode($draft, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        throw new RuntimeException('Unable to encode blog draft payload.');
    }

    if (file_put_contents(ai_blog_draft_path($adminId), $payload, LOCK_EX) === false) {
        throw new RuntimeException('Unable to persist blog draft.');
    }
}

function ai_blog_draft_clear(int $adminId): void
{
    $path = ai_blog_draft_path($adminId);
    if (is_file($path)) {
        unlink($path);
    }
}

function ai_settings_masked_key(string $value): string
{
    if ($value === '') {
        return '';
    }

    $length = strlen($value);
    if ($length <= 4) {
        return str_repeat('•', $length);
    }

    return str_repeat('•', max(0, $length - 4)) . substr($value, -4);
}

function ai_settings_load(): array
{
    $defaults = ai_settings_defaults();
    $path = ai_settings_file();

    if (!is_file($path)) {
        return $defaults;
    }

    $contents = file_get_contents($path);
    if ($contents === false || trim($contents) === '') {
        return $defaults;
    }

    try {
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $exception) {
        error_log('ai_settings_load: failed to decode settings: ' . $exception->getMessage());
        return $defaults;
    }

    if (!is_array($decoded)) {
        return $defaults;
    }

    $settings = array_replace_recursive($defaults, $decoded);

    $settings['enabled'] = (bool) ($settings['enabled'] ?? false);
    $settings['api_key'] = is_string($settings['api_key'] ?? null) ? trim((string) $settings['api_key']) : '';

    $models = is_array($settings['models'] ?? null) ? $settings['models'] : [];
    $settings['models'] = [
        'text' => ai_normalize_model_code($models['text'] ?? '', $defaults['models']['text']),
        'image' => ai_normalize_model_code($models['image'] ?? '', $defaults['models']['image']),
        'tts' => ai_normalize_model_code($models['tts'] ?? '', $defaults['models']['tts']),
    ];

    $settings['temperature'] = ai_normalize_temperature($settings['temperature'] ?? $defaults['temperature']);
    $settings['max_tokens'] = ai_normalize_max_tokens($settings['max_tokens'] ?? $defaults['max_tokens']);
    $settings['updated_at'] = is_string($settings['updated_at'] ?? null) ? $settings['updated_at'] : null;

    return $settings;
}

function ai_settings_save(array $settings): void
{
    $settings['temperature'] = ai_normalize_temperature($settings['temperature'] ?? 0.9);
    $settings['max_tokens'] = ai_normalize_max_tokens($settings['max_tokens'] ?? 1024);
    $settings['models'] = [
        'text' => ai_normalize_model_code($settings['models']['text'] ?? '', 'gemini-2.5-flash'),
        'image' => ai_normalize_model_code($settings['models']['image'] ?? '', 'gemini-2.5-flash-image'),
        'tts' => ai_normalize_model_code($settings['models']['tts'] ?? '', 'gemini-2.5-flash-preview-tts'),
    ];
    $settings['enabled'] = (bool) ($settings['enabled'] ?? false);
    $settings['api_key'] = is_string($settings['api_key'] ?? null) ? trim((string) $settings['api_key']) : '';

    $settings['updated_at'] = (new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata')))->format(DateTimeInterface::ATOM);

    $payload = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        throw new RuntimeException('Unable to encode AI settings.');
    }

    $lockHandle = fopen(ai_settings_lock_file(), 'c+');
    if ($lockHandle === false) {
        throw new RuntimeException('Unable to open AI settings lock.');
    }

    try {
        if (!flock($lockHandle, LOCK_EX)) {
            throw new RuntimeException('Unable to acquire AI settings lock.');
        }

        if (file_put_contents(ai_settings_file(), $payload, LOCK_EX) === false) {
            throw new RuntimeException('Failed to persist AI settings.');
        }

        fflush($lockHandle);
        flock($lockHandle, LOCK_UN);
    } finally {
        fclose($lockHandle);
    }
}

function ai_normalize_model_code(?string $value, string $fallback): string
{
    $value = is_string($value) ? trim($value) : '';
    if ($value === '') {
        return $fallback;
    }

    $value = preg_replace('/[^A-Za-z0-9._\-]/', '', $value);
    if (!is_string($value) || $value === '') {
        return $fallback;
    }

    return $value;
}

function ai_normalize_temperature($value): float
{
    $number = is_numeric($value) ? (float) $value : 0.0;
    if (!is_finite($number)) {
        $number = 0.0;
    }
    $number = max(0.0, min(2.0, $number));
    return round($number, 2);
}

function ai_normalize_max_tokens($value): int
{
    $number = is_numeric($value) ? (int) $value : 0;
    if ($number <= 0) {
        $number = 1;
    }

    if ($number > 8192) {
        $number = 8192;
    }

    return $number;
}

function ai_collect_settings_from_request(array $current, array $input): array
{
    $updated = $current;

    $updated['enabled'] = isset($input['ai_enabled']) && (string) $input['ai_enabled'] === '1';

    $textModel = ai_normalize_model_code($input['gemini_text_model'] ?? '', $current['models']['text']);
    $imageModel = ai_normalize_model_code($input['gemini_image_model'] ?? '', $current['models']['image']);
    $ttsModel = ai_normalize_model_code($input['gemini_tts_model'] ?? '', $current['models']['tts']);

    $updated['models']['text'] = $textModel;
    $updated['models']['image'] = $imageModel;
    $updated['models']['tts'] = $ttsModel;

    if (array_key_exists('api_key', $input)) {
        $candidateKey = trim((string) $input['api_key']);
        if ($candidateKey !== '') {
            $updated['api_key'] = $candidateKey;
        }
    }

    $updated['temperature'] = ai_normalize_temperature($input['temperature'] ?? $current['temperature']);
    $updated['max_tokens'] = ai_normalize_max_tokens($input['max_tokens'] ?? $current['max_tokens']);

    return $updated;
}

function ai_gemini_ping(array $settings, string $prompt = 'Ping from Dakshayani AI Studio'): array
{
    try {
        $response = ai_gemini_generate($settings, [[
            'role' => 'user',
            'parts' => [['text' => $prompt]],
        ]]);
        $text = ai_gemini_extract_text($response);
        if (trim($text) === '') {
            throw new RuntimeException('Empty response received from Gemini.');
        }

        return [
            'ok' => true,
            'response' => $text,
        ];
    } catch (Throwable $exception) {
        return [
            'ok' => false,
            'error' => $exception->getMessage(),
        ];
    }
}

function ai_gemini_generate(array $settings, array $contents): array
{
    $apiKey = trim((string) ($settings['api_key'] ?? ''));
    if ($apiKey === '') {
        throw new RuntimeException('Gemini API key is missing.');
    }

    $model = ai_normalize_model_code($settings['models']['text'] ?? '', 'gemini-2.5-flash');
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($apiKey);

    $payload = [
        'contents' => $contents,
        'generationConfig' => [
            'temperature' => ai_normalize_temperature($settings['temperature'] ?? 0.9),
            'maxOutputTokens' => ai_normalize_max_tokens($settings['max_tokens'] ?? 1024),
        ],
    ];

    $result = ai_http_json_post($url, $payload, [
        'Content-Type: application/json',
    ]);

    if ($result['http_code'] < 200 || $result['http_code'] >= 300) {
        $message = 'Gemini API error (' . $result['http_code'] . ')';
        if (is_array($result['body']) && isset($result['body']['error']['message'])) {
            $message .= ': ' . (string) $result['body']['error']['message'];
        }
        throw new RuntimeException($message);
    }

    if (!is_array($result['body'])) {
        throw new RuntimeException('Unexpected Gemini response.');
    }

    return $result['body'];
}

function ai_gemini_generate_text(array $settings, string $prompt): string
{
    $contents = [[
        'role' => 'user',
        'parts' => [['text' => $prompt]],
    ]];

    $response = ai_gemini_generate($settings, $contents);
    $text = ai_gemini_extract_text($response);
    if ($text === '') {
        throw new RuntimeException('Gemini returned an empty response.');
    }

    return $text;
}

function ai_gemini_generate_image(array $settings, string $prompt): array
{
    $apiKey = trim((string) ($settings['api_key'] ?? ''));
    if ($apiKey === '') {
        throw new RuntimeException('Gemini API key is missing.');
    }

    $model = ai_normalize_model_code($settings['models']['image'] ?? '', 'gemini-2.5-flash-image');
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($apiKey);

    $payload = [
        'contents' => [[
            'role' => 'user',
            'parts' => [[
                'text' => $prompt,
            ]],
        ]],
        'generationConfig' => [
            'temperature' => 0.8,
        ],
    ];

    $response = ai_http_json_post($url, $payload, ['Content-Type: application/json']);
    if ($response['http_code'] < 200 || $response['http_code'] >= 300) {
        $message = 'Gemini image generation failed (' . $response['http_code'] . ')';
        if (is_array($response['body']) && isset($response['body']['error']['message'])) {
            $message .= ': ' . (string) $response['body']['error']['message'];
        }
        throw new RuntimeException($message);
    }

    if (!is_array($response['body'])) {
        throw new RuntimeException('Unexpected Gemini image response.');
    }

    $media = ai_gemini_extract_inline_data($response['body'], 'image/');
    if ($media === null) {
        throw new RuntimeException('Gemini did not return an image.');
    }

    $path = ai_gemini_store_binary($media['data'], $media['mimeType'], 'generated_images');

    return [
        'path' => $path,
        'mimeType' => $media['mimeType'],
    ];
}

function ai_gemini_generate_tts(array $settings, string $text, string $format = 'mp3'): array
{
    $apiKey = trim((string) ($settings['api_key'] ?? ''));
    if ($apiKey === '') {
        throw new RuntimeException('Gemini API key is missing.');
    }

    $model = ai_normalize_model_code($settings['models']['tts'] ?? '', 'gemini-2.5-flash-preview-tts');
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($apiKey);

    $responseMime = strtolower($format) === 'wav' ? 'audio/wav' : 'audio/mpeg';

    $payload = [
        'contents' => [[
            'role' => 'user',
            'parts' => [[
                'text' => $text,
            ]],
        ]],
        'generationConfig' => [
            'responseMimeType' => $responseMime,
        ],
    ];

    $response = ai_http_json_post($url, $payload, ['Content-Type: application/json']);
    if ($response['http_code'] < 200 || $response['http_code'] >= 300) {
        $message = 'Gemini TTS failed (' . $response['http_code'] . ')';
        if (is_array($response['body']) && isset($response['body']['error']['message'])) {
            $message .= ': ' . (string) $response['body']['error']['message'];
        }
        throw new RuntimeException($message);
    }

    if (!is_array($response['body'])) {
        throw new RuntimeException('Unexpected Gemini TTS response.');
    }

    $media = ai_gemini_extract_inline_data($response['body'], 'audio/');
    if ($media === null) {
        throw new RuntimeException('Gemini did not return audio.');
    }

    $path = ai_gemini_store_binary($media['data'], $media['mimeType'], 'generated_audio');

    return [
        'path' => $path,
        'mimeType' => $media['mimeType'],
    ];
}

function ai_gemini_extract_inline_data(array $response, string $expectedPrefix): ?array
{
    if (isset($response['candidates']) && is_array($response['candidates'])) {
        foreach ($response['candidates'] as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }

            $content = $candidate['content'] ?? null;
            if (!is_array($content) || !isset($content['parts']) || !is_array($content['parts'])) {
                continue;
            }

            foreach ($content['parts'] as $part) {
                if (!is_array($part)) {
                    continue;
                }

                $inlineData = $part['inlineData'] ?? null;
                if (!is_array($inlineData)) {
                    continue;
                }

                $mimeType = (string) ($inlineData['mimeType'] ?? '');
                $data = (string) ($inlineData['data'] ?? '');

                if ($mimeType !== '' && $data !== '' && str_starts_with($mimeType, $expectedPrefix)) {
                    return [
                        'mimeType' => $mimeType,
                        'data' => $data,
                    ];
                }
            }
        }
    }

    return null;
}

function ai_gemini_store_binary(string $base64, string $mimeType, string $folder): string
{
    $binary = base64_decode($base64, true);
    if ($binary === false) {
        throw new RuntimeException('Failed to decode Gemini media payload.');
    }

    $extensions = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
        'audio/mpeg' => 'mp3',
        'audio/wav' => 'wav',
        'audio/mp3' => 'mp3',
    ];
    $extension = $extensions[strtolower($mimeType)] ?? 'bin';

    $dir = ai_storage_dir() . '/' . $folder;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $fileName = uniqid('gemini_', true) . '.' . $extension;
    $path = $dir . '/' . $fileName;

    if (file_put_contents($path, $binary) === false) {
        throw new RuntimeException('Unable to store Gemini media output.');
    }

    $relative = 'storage/ai/' . $folder . '/' . $fileName;
    return $relative;
}

function ai_http_json_post(string $url, array $payload, array $headers = []): array
{
    $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
    if ($body === false) {
        throw new RuntimeException('Failed to encode Gemini request payload.');
    }

    $responseBody = null;
    $httpCode = 0;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => 20,
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Gemini request failed: ' . $error);
        }

        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $responseBody = $response;
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", array_merge($headers, [
                    'Content-Length: ' . strlen($body),
                ])),
                'content' => $body,
                'timeout' => 20,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            $error = error_get_last();
            throw new RuntimeException('Gemini request failed: ' . ($error['message'] ?? 'connection error'));
        }

        $responseBody = $response;
        $httpCode = 200;
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $headerLine) {
                if (preg_match('/^HTTP\/(?:1\.1|2)\s+(\d{3})/', $headerLine, $matches)) {
                    $httpCode = (int) $matches[1];
                    break;
                }
            }
        }
    }

    $decoded = null;
    if (is_string($responseBody) && trim($responseBody) !== '') {
        try {
            $decoded = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            $decoded = null;
        }
    }

    return [
        'http_code' => $httpCode,
        'body' => $decoded,
        'raw' => $responseBody,
    ];
}

function ai_gemini_extract_text(array $response): string
{
    if (isset($response['candidates']) && is_array($response['candidates'])) {
        foreach ($response['candidates'] as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }

            $content = $candidate['content'] ?? null;
            if (!is_array($content) || !isset($content['parts']) || !is_array($content['parts'])) {
                continue;
            }

            foreach ($content['parts'] as $part) {
                if (is_array($part) && isset($part['text']) && is_string($part['text'])) {
                    $text = trim($part['text']);
                    if ($text !== '') {
                        return $text;
                    }
                }
            }
        }
    }

    if (isset($response['text']) && is_string($response['text'])) {
        return trim($response['text']);
    }

    return '';
}

function ai_chat_history_path(int $userId): string
{
    $fileName = $userId > 0 ? 'chat_' . $userId . '.json' : 'chat_default.json';
    return ai_storage_dir() . '/' . $fileName;
}

function ai_chat_history_load(int $userId): array
{
    $path = ai_chat_history_path($userId);
    if (!is_file($path)) {
        return [];
    }

    $contents = file_get_contents($path);
    if ($contents === false || trim($contents) === '') {
        return [];
    }

    try {
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $exception) {
        error_log('ai_chat_history_load: failed to decode history: ' . $exception->getMessage());
        return [];
    }

    if (!is_array($decoded)) {
        return [];
    }

    $result = [];
    foreach ($decoded as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $role = $entry['role'] ?? '';
        $text = is_string($entry['text'] ?? null) ? $entry['text'] : '';
        $timestamp = is_string($entry['timestamp'] ?? null) ? $entry['timestamp'] : null;

        if (!in_array($role, ['user', 'assistant'], true)) {
            continue;
        }

        $result[] = [
            'role' => $role,
            'text' => $text,
            'timestamp' => $timestamp,
        ];
    }

    return $result;
}

function ai_chat_history_save(int $userId, array $history): void
{
    $normalized = [];
    foreach ($history as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $role = $entry['role'] ?? '';
        if (!in_array($role, ['user', 'assistant'], true)) {
            continue;
        }

        $normalized[] = [
            'role' => $role,
            'text' => (string) ($entry['text'] ?? ''),
            'timestamp' => is_string($entry['timestamp'] ?? null) ? $entry['timestamp'] : null,
        ];
    }

    $payload = json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        throw new RuntimeException('Unable to encode chat history.');
    }

    if (file_put_contents(ai_chat_history_path($userId), $payload, LOCK_EX) === false) {
        throw new RuntimeException('Unable to write chat history.');
    }
}

function ai_chat_history_append(int $userId, array $entry): array
{
    $history = ai_chat_history_load($userId);
    $history[] = [
        'role' => in_array($entry['role'] ?? '', ['user', 'assistant'], true) ? $entry['role'] : 'user',
        'text' => (string) ($entry['text'] ?? ''),
        'timestamp' => ai_timestamp(),
    ];

    $history = ai_chat_history_trim($history, 40);
    ai_chat_history_save($userId, $history);

    return $history;
}

function ai_chat_history_replace(int $userId, array $history): array
{
    $history = ai_chat_history_trim($history, 40);
    ai_chat_history_save($userId, $history);
    return $history;
}

function ai_chat_history_clear(int $userId): void
{
    $path = ai_chat_history_path($userId);
    if (is_file($path)) {
        unlink($path);
    }
}

function ai_chat_history_trim(array $history, int $limit): array
{
    if ($limit <= 0) {
        return [];
    }

    if (count($history) <= $limit) {
        return $history;
    }

    return array_slice($history, -$limit);
}

function ai_timestamp(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata')))->format(DateTimeInterface::ATOM);
}

function ai_convert_history_to_contents(array $history): array
{
    $contents = [];
    foreach ($history as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $role = $entry['role'] ?? '';
        $text = (string) ($entry['text'] ?? '');
        if ($text === '') {
            continue;
        }

        $contents[] = [
            'role' => $role === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => $text]],
        ];
    }

    return $contents;
}

function ai_chat_history_export_pdf(int $userId, string $adminName = 'Administrator'): string
{
    $history = ai_chat_history_load($userId);
    $lines = [];
    $title = 'AI Chat Transcript';

    foreach ($history as $entry) {
        $role = $entry['role'] === 'assistant' ? 'Gemini' : $adminName;
        $timestamp = $entry['timestamp'] ?? '';
        $displayTime = '';
        if ($timestamp !== '') {
            try {
                $dt = new DateTimeImmutable($timestamp);
                $displayTime = $dt->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('d M Y · h:i A');
            } catch (Throwable $exception) {
                $displayTime = $timestamp;
            }
        }
        $prefix = $displayTime !== '' ? sprintf('%s (%s)', $role, $displayTime) : $role;
        $text = preg_replace('/\s+/u', ' ', (string) ($entry['text'] ?? ''));
        $lines[] = trim($prefix . ': ' . $text);
    }

    if (empty($lines)) {
        $lines[] = 'No chat messages recorded yet.';
    }

    return ai_render_simple_pdf($title, $lines);
}

function ai_render_simple_pdf(string $title, array $lines): string
{
    $contentLines = [];
    $contentLines[] = 'BT';
    $contentLines[] = '/F1 16 Tf';
    $contentLines[] = '48 760 Td';
    $contentLines[] = '(' . ai_pdf_escape($title) . ') Tj';
    $contentLines[] = '0 -28 Td';
    $contentLines[] = '/F1 11 Tf';

    foreach ($lines as $line) {
        $wrapped = ai_pdf_wrap_text($line, 90);
        foreach ($wrapped as $index => $segment) {
            if ($index > 0) {
                $contentLines[] = '0 -14 Td';
            }
            $contentLines[] = '(' . ai_pdf_escape($segment) . ') Tj';
        }
        $contentLines[] = '0 -18 Td';
    }

    $contentLines[] = 'ET';

    $stream = implode("\n", $contentLines);
    $length = strlen($stream);

    $objects = [];
    $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
    $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>';
    $objects[] = "<< /Length $length >>\nstream\n$stream\nendstream";
    $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

    $offsets = [];
    $buffer = "%PDF-1.4\n";
    foreach ($objects as $index => $object) {
        $offsets[$index + 1] = strlen($buffer);
        $buffer .= ($index + 1) . " 0 obj\n" . $object . "\nendobj\n";
    }

    $xrefPosition = strlen($buffer);
    $buffer .= "xref\n0 " . (count($objects) + 1) . "\n";
    $buffer .= "0000000000 65535 f \n";
    for ($i = 1; $i <= count($objects); $i++) {
        $buffer .= sprintf('%010d 00000 n %s', $offsets[$i], "\n");
    }

    $buffer .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefPosition . "\n%%EOF";

    return $buffer;
}

function ai_pdf_escape(string $value): string
{
    $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    return preg_replace('/[\r\n]+/', ' ', $escaped) ?? $escaped;
}

function ai_pdf_wrap_text(string $text, int $maxLength): array
{
    $text = trim($text);
    if ($text === '') {
        return [''];
    }

    $words = preg_split('/\s+/u', $text);
    if (!is_array($words)) {
        return [$text];
    }

    $lines = [];
    $current = '';
    foreach ($words as $word) {
        $candidate = $current === '' ? $word : $current . ' ' . $word;
        if (mb_strlen($candidate) > $maxLength) {
            if ($current !== '') {
                $lines[] = $current;
            }
            $current = $word;
        } else {
            $current = $candidate;
        }
    }

    if ($current !== '') {
        $lines[] = $current;
    }

    return $lines;
}

