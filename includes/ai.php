<?php
declare(strict_types=1);

const AI_DAILY_NOTE_TYPES = ['work_done', 'next_plan'];

function ai_storage_base(): string
{
    return __DIR__ . '/../storage/ai';
}

function ai_temp_path(string ...$segments): string
{
    return ai_storage_path('tmp', ...$segments);
}

function ai_storage_path(string ...$segments): string
{
    $path = ai_storage_base();
    foreach ($segments as $segment) {
        $segment = str_replace(['\\', '..'], '/', $segment);
        $segment = trim($segment, '/');
        if ($segment === '') {
            continue;
        }
        $path .= DIRECTORY_SEPARATOR . $segment;
    }
    return $path;
}

function ai_ensure_directory(string $path): void
{
    if (is_dir($path)) {
        return;
    }
    if (!mkdir($path, 0775, true) && !is_dir($path)) {
        throw new RuntimeException(sprintf('Unable to create directory: %s', $path));
    }
}

function ai_bootstrap_storage(): void
{
    ai_ensure_directory(ai_storage_base());
    ai_ensure_directory(ai_storage_path('drafts'));
    ai_ensure_directory(ai_storage_path('images'));
    ai_ensure_directory(ai_storage_path('notes'));
    ai_ensure_directory(ai_storage_path('secrets'));
    ai_ensure_directory(ai_storage_path('logs'));
    ai_ensure_directory(ai_storage_path('tmp'));
}

function ai_api_key_path(): string
{
    return ai_storage_path('secrets', 'gemini.key');
}

function ai_store_api_key(string $apiKey): void
{
    $apiKey = trim($apiKey);
    if ($apiKey === '') {
        return;
    }

    ai_safe_write(ai_api_key_path(), $apiKey);
    @chmod(ai_api_key_path(), 0660);
    $GLOBALS['__ai_cached_api_key'] = $apiKey;
    $GLOBALS['__ai_cached_api_key_initialised'] = true;
}

function ai_read_stored_api_key(): ?string
{
    $path = ai_api_key_path();
    if (!is_file($path)) {
        return null;
    }

    $contents = @file_get_contents($path);
    if ($contents === false) {
        return null;
    }

    $contents = trim($contents);
    return $contents !== '' ? $contents : null;
}

function ai_resolve_api_key(bool $quiet = false): ?string
{
    $initialised = (bool) ($GLOBALS['__ai_cached_api_key_initialised'] ?? false);
    if ($initialised) {
        $cached = $GLOBALS['__ai_cached_api_key'] ?? null;
        if (!$quiet && ($cached === null || $cached === '')) {
            throw new RuntimeException('Configure the Gemini API key in AI Studio settings or environment variables.');
        }
        return $cached !== '' ? $cached : null;
    }

    $candidates = [];
    $stored = ai_read_stored_api_key();
    if (is_string($stored)) {
        $candidates[] = $stored;
    }

    $envKeys = ['AI_GEMINI_API_KEY', 'AI_API_KEY', 'GEMINI_API_KEY', 'GOOGLE_GEMINI_API_KEY'];
    foreach ($envKeys as $envKey) {
        $value = $_ENV[$envKey] ?? $_SERVER[$envKey] ?? getenv($envKey) ?: null;
        if (is_string($value)) {
            $value = trim($value);
            if ($value !== '') {
                $candidates[] = $value;
            }
        }
    }

    $constants = ['AI_API_KEY', 'GEMINI_API_KEY'];
    foreach ($constants as $constant) {
        if (defined($constant)) {
            $value = trim((string) constant($constant));
            if ($value !== '') {
                $candidates[] = $value;
            }
        }
    }

    $resolved = null;
    foreach ($candidates as $candidate) {
        if (is_string($candidate) && $candidate !== '') {
            $resolved = $candidate;
            break;
        }
    }

    $GLOBALS['__ai_cached_api_key'] = $resolved;
    $GLOBALS['__ai_cached_api_key_initialised'] = true;

    if (!$quiet && ($resolved === null || $resolved === '')) {
        throw new RuntimeException('Configure the Gemini API key in AI Studio settings or environment variables.');
    }

    return $resolved !== '' ? $resolved : null;
}

function ai_has_api_key(): bool
{
    $key = ai_resolve_api_key(true);
    return is_string($key) && $key !== '';
}

function ai_content_safety_check(string $text): void
{
    $disallowedKeywords = [
        // Hate speech
        'nazi', 'supremacist', 'incel', 'white power',
        // Violence
        'kill', 'murder', 'slaughter', 'massacre', 'terrorist', 'bombing',
        // Sexual content
        'porn', 'sex', 'naked', 'xxx',
    ];

    $pattern = '/\b(' . implode('|', $disallowedKeywords) . ')\b/i';
    if (preg_match($pattern, $text, $matches)) {
        throw new RuntimeException(sprintf('Content blocked by safety check due to keyword: %s', $matches[0]));
    }
}

function ai_http_post_json(string $url, array $payload, array $headers = []): array
{
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($body === false) {
        throw new RuntimeException('Failed to encode request payload for Gemini API.');
    }

    $httpHeaders = array_merge(['Content-Type: application/json', 'Accept: application/json'], array_values($headers));

    $responseBody = '';
    $statusCode = 0;

    if (function_exists('curl_init')) {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException('Unable to initialise HTTP client for Gemini request.');
        }
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_POST, true);
        curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        curl_setopt($handle, CURLOPT_HTTPHEADER, $httpHeaders);
        curl_setopt($handle, CURLOPT_TIMEOUT, 45);

        $responseBody = curl_exec($handle);
        if ($responseBody === false) {
            $error = curl_error($handle);
            curl_close($handle);
            throw new RuntimeException('Network timeout or connection error. Please retry.');
        }

        $statusCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $httpHeaders),
                'content' => $body,
                'timeout' => 45,
                'ignore_errors' => true,
            ],
        ]);
        $responseBody = @file_get_contents($url, false, $context);
        if ($responseBody === false) {
            throw new RuntimeException('Network timeout or connection error. Please retry.');
        }

        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $headerLine) {
                if (preg_match('/^HTTP\/\S+\s+(\d+)/', (string) $headerLine, $matches)) {
                    $statusCode = (int) $matches[1];
                    break;
                }
            }
        }
    }

    if ($statusCode !== 200) {
        switch ($statusCode) {
            case 400:
                throw new RuntimeException('Invalid request. Check the model name.');
            case 401:
            case 403:
                throw new RuntimeException('Permission denied. Check your API key.');
            case 429:
                throw new RuntimeException('Quota exceeded or rate limited. Please try again later.');
            case 500:
            case 503:
                throw new RuntimeException('The model provider is temporarily unavailable. Please try again later.');
            default:
                throw new RuntimeException("The model provider returned an unexpected error (HTTP {$statusCode}).");
        }
    }

    $decoded = json_decode((string) $responseBody, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('The model returned an invalid JSON response.');
    }

    if (isset($decoded['error'])) {
        $message = is_array($decoded['error']) ? ($decoded['error']['message'] ?? 'Unknown error') : (string) $decoded['error'];
        throw new RuntimeException('Gemini API error: ' . $message);
    }

    return $decoded;
}

function ai_gemini_generate_content(string $model, array $payload, string $apiKey): array
{
    $urlTemplate = 'https://generativelanguage.googleapis.com/v1beta/models/%s:%s?key=%s';
    $maxRetries = 2;
    $lastException = null;

    // First, try the streaming endpoint
    $streamingUrl = sprintf($urlTemplate, rawurlencode($model), 'streamGenerateContent', urlencode($apiKey));
    for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
        try {
            $response = ai_http_post_json($streamingUrl, $payload);
            if (!empty($response['candidates'])) {
                $text = ai_extract_text_from_gemini($response);
                if ($text !== '') {
                    return $response;
                }
            }
            $lastException = new RuntimeException('Gemini API returned a successful but empty streaming response.');
        } catch (Throwable $exception) {
            $lastException = $exception;
        }
        if ($attempt < $maxRetries) usleep(500000);
    }

    // Fallback to the non-streaming endpoint
    $nonStreamingUrl = sprintf($urlTemplate, rawurlencode($model), 'generateContent', urlencode($apiKey));
    try {
        $response = ai_http_post_json($nonStreamingUrl, $payload);
        if (!empty($response['candidates'])) {
            $text = ai_extract_text_from_gemini($response);
            if ($text !== '') {
                return $response;
            }
        }
         throw new RuntimeException('Gemini API returned a successful but empty non-streaming response.');
    } catch (Throwable $exception) {
        $lastException = $exception;
    }

    throw new RuntimeException(
        'The model did not respond after multiple attempts. Please retry or check API key.',
        0,
        $lastException
    );
}

function ai_extract_text_from_gemini(array $response): string
{
    $candidates = $response['candidates'] ?? [];
    foreach ($candidates as $candidate) {
        if (!empty($candidate['finishReason']) && $candidate['finishReason'] === 'SAFETY') {
            continue;
        }
        $parts = $candidate['content']['parts'] ?? [];
        foreach ($parts as $part) {
            if (isset($part['text']) && is_string($part['text'])) {
                $text = trim($part['text']);
                if (strlen($text) > 10) {
                    return $text;
                }
            }
        }
    }

    return '';
}

function ai_json_candidates_from_text(string $text): array
{
    $candidates = [];
    $trimmed = trim($text);
    if ($trimmed !== '') {
        $candidates[] = $trimmed;
    }

    if (preg_match_all('/```(?:json5?|JSON)?\s*(.*?)\s*```/is', $text, $matches)) {
        foreach ($matches[1] as $block) {
            $block = trim((string) $block);
            if ($block !== '') {
                $candidates[] = $block;
            }
        }
    }

    if (preg_match_all('/\{/', $text)) {
        $possible = ai_extract_balanced_json($text, '{', '}');
        foreach ($possible as $snippet) {
            $snippet = trim($snippet);
            if ($snippet !== '') {
                $candidates[] = $snippet;
            }
        }
    }

    if (preg_match_all('/\[/', $text)) {
        $possible = ai_extract_balanced_json($text, '[', ']');
        foreach ($possible as $snippet) {
            $snippet = trim($snippet);
            if ($snippet !== '') {
                $candidates[] = $snippet;
            }
        }
    }

    return array_values(array_unique($candidates));
}

function ai_extract_balanced_json(string $text, string $open, string $close): array
{
    $snippets = [];
    $length = strlen($text);
    for ($start = 0; $start < $length; $start++) {
        if ($text[$start] !== $open) {
            continue;
        }

        $depth = 0;
        $inString = false;
        $escape = false;

        for ($i = $start; $i < $length; $i++) {
            $char = $text[$i];

            if ($inString) {
                if ($escape) {
                    $escape = false;
                } elseif ($char === '\\') {
                    $escape = true;
                } elseif ($char === '"') {
                    $inString = false;
                }
                continue;
            }

            if ($char === '"') {
                $inString = true;
                continue;
            }

            if ($char === $open) {
                $depth++;
                continue;
            }

            if ($char === $close) {
                $depth--;
                if ($depth === 0) {
                    $snippet = substr($text, $start, $i - $start + 1);
                    if ($snippet !== false) {
                        $snippets[] = $snippet;
                    }
                    break;
                }
                if ($depth < 0) {
                    break;
                }
            }
        }
    }

    return $snippets;
}

function ai_decode_model_json(string $text): array
{
    foreach (ai_json_candidates_from_text($text) as $candidate) {
        $decoded = json_decode($candidate, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    throw new RuntimeException('Gemini returned an unexpected response while generating the blog draft.');
}

function ai_gemini_generate_image(string $model, string $prompt, string $apiKey): array
{
    $payload = [
        'prompt' => [
            'text' => $prompt,
        ],
        'aspectRatio' => '16:9',
    ];

    $url = sprintf(
        'https://generativelanguage.googleapis.com/v1beta/models/%s:generateImage?key=%s',
        rawurlencode($model),
        urlencode($apiKey)
    );

    return ai_http_post_json($url, $payload);
}

function ai_extract_image_payload(array $response): array
{
    $candidates = [];

    if (!empty($response['images']) && is_array($response['images'])) {
        foreach ($response['images'] as $image) {
            if (!is_array($image)) {
                continue;
            }
            $candidates[] = [
                'data' => $image['content'] ?? ($image['data'] ?? null),
                'mime' => $image['mimeType'] ?? '',
            ];
        }
    }

    if (!empty($response['result']['images']) && is_array($response['result']['images'])) {
        foreach ($response['result']['images'] as $image) {
            if (!is_array($image)) {
                continue;
            }
            $candidates[] = [
                'data' => $image['image']['bytesBase64Encoded'] ?? ($image['image']['data'] ?? null),
                'mime' => $image['mimeType'] ?? ($image['image']['mimeType'] ?? ''),
            ];
        }
    }

    if (!empty($response['predictions']) && is_array($response['predictions'])) {
        foreach ($response['predictions'] as $prediction) {
            if (!is_array($prediction)) {
                continue;
            }
            $candidates[] = [
                'data' => $prediction['bytesBase64Encoded'] ?? ($prediction['b64_json'] ?? null),
                'mime' => $prediction['mimeType'] ?? '',
            ];
        }
    }

    if (!empty($response['data']) && is_array($response['data'])) {
        foreach ($response['data'] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $candidates[] = [
                'data' => $item['b64_json'] ?? ($item['content'] ?? null),
                'mime' => $item['mime'] ?? '',
            ];
        }
    }

    foreach ($candidates as $candidate) {
        $data = $candidate['data'] ?? null;
        if (!is_string($data) || $data === '') {
            continue;
        }
        $mime = (string) ($candidate['mime'] ?? '');

        if (strpos($data, 'data:') === 0) {
            $commaPos = strpos($data, ',');
            if ($commaPos !== false) {
                $mimeHeader = substr($data, 5, $commaPos - 5);
                if ($mimeHeader !== '' && strpos($mimeHeader, ';') !== false) {
                    [$mimeCandidate] = explode(';', $mimeHeader, 2);
                    $mime = $mimeCandidate;
                } elseif ($mimeHeader !== '') {
                    $mime = $mimeHeader;
                }
                $data = substr($data, $commaPos + 1);
            }
        }

        $binary = base64_decode($data, true);
        if ($binary === false) {
            continue;
        }

        return [
            'binary' => $binary,
            'mime' => $mime !== '' ? $mime : 'image/png',
        ];
    }

    throw new RuntimeException('Gemini did not return image bytes.');
}

function ai_extension_from_mime(string $mime): string
{
    $mime = strtolower(trim($mime));
    $map = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/svg+xml' => 'svg',
    ];

    return $map[$mime] ?? 'png';
}

function ai_store_draft_image(string $draftId, string $binary, string $extension): string
{
    $extension = strtolower(preg_replace('/[^a-z0-9]+/', '', $extension));
    if ($extension === '') {
        $extension = 'png';
    }

    $relativePath = 'images/' . $draftId . '.' . $extension;
    $pattern = ai_storage_path('images', $draftId . '.*');
    foreach (glob($pattern) ?: [] as $existing) {
        if (is_file($existing) && basename($existing) !== $draftId . '.' . $extension) {
            @unlink($existing);
        }
    }
    ai_safe_write(ai_storage_path($relativePath), $binary);
    return $relativePath;
}

function ai_safe_write(string $path, string $contents): void
{
    ai_ensure_directory(dirname($path));
    $result = @file_put_contents($path, $contents, LOCK_EX);
    if ($result === false) {
        throw new RuntimeException(sprintf('Unable to write to %s', $path));
    }
}

function ai_read_json_file(string $path, array $default = []): array
{
    if (!is_file($path)) {
        return $default;
    }
    $contents = @file_get_contents($path);
    if ($contents === false || $contents === '') {
        return $default;
    }
    $decoded = json_decode($contents, true);
    return is_array($decoded) ? $decoded : $default;
}

function ai_write_json_file(string $path, array $data): void
{
    $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        throw new RuntimeException('Failed to encode AI data as JSON.');
    }
    ai_safe_write($path, $encoded);
}

function ai_settings_path(): string
{
    return ai_storage_path('settings.json');
}

function ai_settings_defaults(): array
{
    return [
        'enabled' => false,
        'provider' => 'Gemini',
        'text_model' => '',
        'image_model' => '',
        'tts_model' => '',
        'temperature' => 0.7,
        'max_tokens' => 2048,
        'max_drafts_per_day' => 10,
        'max_tokens_per_day' => 50000,
        'api_key_hash' => null,
        'last_test_result' => null,
        'last_tested_at' => null,
        'updated_at' => null,
    ];
}

function ai_get_settings(): array
{
    ai_bootstrap_storage();
    $defaults = ai_settings_defaults();
    $settings = ai_read_json_file(ai_settings_path(), $defaults);
    $settings['enabled'] = (bool) ($settings['enabled'] ?? $defaults['enabled']);
    $settings['provider'] = (string) ($settings['provider'] ?? $defaults['provider']);
    $settings['text_model'] = (string) ($settings['text_model'] ?? $defaults['text_model']);
    $settings['image_model'] = (string) ($settings['image_model'] ?? $defaults['image_model']);
    $settings['tts_model'] = (string) ($settings['tts_model'] ?? $defaults['tts_model']);
    $settings['temperature'] = (float) ($settings['temperature'] ?? $defaults['temperature']);
    $settings['max_tokens'] = (int) ($settings['max_tokens'] ?? $defaults['max_tokens']);
    $settings['max_drafts_per_day'] = (int) ($settings['max_drafts_per_day'] ?? $defaults['max_drafts_per_day']);
    $settings['max_tokens_per_day'] = (int) ($settings['max_tokens_per_day'] ?? $defaults['max_tokens_per_day']);
    $settings['api_key_hash'] = $settings['api_key_hash'] ?? null;
    $settings['last_test_result'] = $settings['last_test_result'] ?? null;
    $settings['last_tested_at'] = $settings['last_tested_at'] ?? null;
    $settings['updated_at'] = $settings['updated_at'] ?? null;
    $settings['has_api_key'] = ai_has_api_key();
    return $settings;
}

function ai_save_settings(array $input, int $actorId): array
{
    unset($actorId);
    $settings = ai_get_settings();
    $enabled = !empty($input['enabled']);
    $provider = trim((string) ($input['provider'] ?? 'Gemini'));
    if ($provider === '') {
        $provider = 'Gemini';
    }
    $textModel = trim((string) ($input['text_model'] ?? ''));
    $imageModel = trim((string) ($input['image_model'] ?? ''));
    $ttsModel = trim((string) ($input['tts_model'] ?? ''));
    $temperature = (float) ($input['temperature'] ?? 0.7);
    $maxTokens = (int) ($input['max_tokens'] ?? 2048);
    $maxDraftsPerDay = (int) ($input['max_drafts_per_day'] ?? 10);
    $maxTokensPerDay = (int) ($input['max_tokens_per_day'] ?? 50000);
    $apiKey = trim((string) ($input['api_key'] ?? ''));

    if ($apiKey !== '') {
        $settings['api_key_hash'] = password_hash($apiKey, PASSWORD_DEFAULT);
        $settings['last_test_result'] = null;
        $settings['last_tested_at'] = null;
        ai_store_api_key($apiKey);
    }

    $settings['enabled'] = $enabled;
    $settings['provider'] = $provider;
    $settings['text_model'] = $textModel;
    $settings['image_model'] = $imageModel;
    $settings['tts_model'] = $ttsModel;
    $settings['temperature'] = max(0.0, min(1.0, $temperature));
    $settings['max_tokens'] = max(256, min(8192, $maxTokens));
    $settings['max_drafts_per_day'] = max(1, $maxDraftsPerDay);
    $settings['max_tokens_per_day'] = max(1000, $maxTokensPerDay);
    $settings['updated_at'] = gmdate(DateTimeInterface::ATOM);
    $settings['has_api_key'] = ai_has_api_key();

    ai_write_json_file(ai_settings_path(), $settings);
    return ai_get_settings();
}

function ai_get_activity_logs(int $limit = 25): array
{
    $logFile = ai_storage_path('logs', 'activity.log');
    if (!is_file($logFile)) {
        return [];
    }

    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return [];
    }

    $lines = array_slice(array_reverse($lines), 0, $limit);
    $logs = [];
    foreach ($lines as $line) {
        $data = json_decode($line, true);
        if (is_array($data)) {
            $logs[] = $data;
        }
    }

    return $logs;
}

function ai_log_activity(string $action, array $details): void
{
    $logFile = ai_storage_path('logs', 'activity.log');
    $timestamp = gmdate(DateTimeInterface::ATOM);
    $user = current_user();
    $userId = $user['id'] ?? 'system';

    $logEntry = [
        'timestamp' => $timestamp,
        'user_id' => $userId,
        'action' => $action,
        'details' => $details,
    ];

    $logLine = json_encode($logEntry) . PHP_EOL;
    file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
}

function ai_get_usage_data(): array
{
    $usagePath = ai_storage_path('usage.json');
    $defaults = [
        'daily_drafts' => 0,
        'daily_tokens' => 0,
        'last_request_date' => '',
    ];
    return ai_read_json_file($usagePath, $defaults);
}

function ai_update_usage_data(int $tokensUsed): void
{
    $usage = ai_get_usage_data();
    $today = gmdate('Y-m-d');

    if ($usage['last_request_date'] !== $today) {
        $usage['daily_drafts'] = 0;
        $usage['daily_tokens'] = 0;
        $usage['last_request_date'] = $today;
    }

    $usage['daily_drafts']++;
    $usage['daily_tokens'] += $tokensUsed;

    ai_write_json_file(ai_storage_path('usage.json'), $usage);
}

function ai_check_quotas(): void
{
    $settings = ai_get_settings();
    $usage = ai_get_usage_data();
    $today = gmdate('Y-m-d');

    if ($usage['last_request_date'] === $today) {
        if ($usage['daily_drafts'] >= $settings['max_drafts_per_day']) {
            throw new RuntimeException('Daily draft limit reached. Please try again tomorrow.');
        }
        if ($usage['daily_tokens'] >= $settings['max_tokens_per_day']) {
            throw new RuntimeException('Daily token limit reached. Please try again tomorrow.');
        }
    }
}

function ai_test_connection(): array
{
    $settings = ai_get_settings();
    $result = 'fail';
    $message = 'Test failed. Check settings and API key.';
    $startTime = microtime(true);

    try {
        if (!$settings['enabled']) {
            throw new RuntimeException('AI is disabled in settings.');
        }

        $apiKey = ai_resolve_api_key();
        if ($apiKey === null || $apiKey === '') {
            throw new RuntimeException('API key is not configured.');
        }

        $model = $settings['text_model'];
        if ($model === '') {
            throw new RuntimeException('Text model is not configured.');
        }

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [['text' => 'Hello']],
                ],
            ],
        ];

        ai_gemini_generate_content($model, $payload, $apiKey);

        $result = 'pass';
        $message = 'OK — Connection to provider is healthy.';
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
        if (str_contains($error, '400') || str_contains($error, 'API key not valid')) {
            $message = 'Invalid API key. Update settings.';
        } elseif (str_contains($error, '429') || str_contains($error, 'rate limit')) {
            $message = 'Rate limited. Try again shortly.';
        } elseif (str_contains($error, '404') || str_contains($error, 'not found')) {
            $message = 'Model not found. Check model code.';
        } elseif (str_contains($error, 'network') || str_contains($error, 'timed out')) {
            $message = 'Network error. Check connection and retry.';
        } else {
            $message = 'FAIL — ' . $error;
        }
    }

    $latency = microtime(true) - $startTime;
    ai_log_activity('test_connection', [
        'status' => $result,
        'message' => $message,
        'latency' => $latency,
    ]);

    $settings['last_test_result'] = $result;
    $settings['last_tested_at'] = gmdate(DateTimeInterface::ATOM);
    ai_write_json_file(ai_settings_path(), $settings);

    return ['status' => $result, 'message' => $message];
}

function ai_require_enabled(): void
{
    $settings = ai_get_settings();
    if (!$settings['enabled']) {
        throw new RuntimeException('Enable AI tools in settings to use this feature.');
    }
}

function ai_normalize_keywords($input): array
{
    if (is_array($input)) {
        $items = $input;
    } else {
        $items = preg_split('/[,\n]+/', (string) $input) ?: [];
    }
    $keywords = [];
    foreach ($items as $item) {
        $keyword = trim((string) $item);
        if ($keyword !== '') {
            $keywords[] = $keyword;
        }
    }
    return array_values(array_unique($keywords));
}

function ai_generate_blog_draft_content(string $prompt): array
{
    $prompt = trim($prompt);
    if ($prompt === '') {
        throw new RuntimeException('Enter a prompt for the blog draft.');
    }

    ai_require_enabled();
    $settings = ai_get_settings();
    $model = $settings['text_model'] ?? '';
    if ($model === '') {
        throw new RuntimeException('Set a Gemini text model in AI settings before generating drafts.');
    }

    $apiKey = ai_resolve_api_key();
    if ($apiKey === null || $apiKey === '') {
        throw new RuntimeException('Configure the Gemini API key to enable researched blog drafts.');
    }

    $normalizedPrompt = preg_replace('/\s+/', ' ', $prompt) ?: $prompt;
    $systemInstruction = <<<TEXT
You are Dakshayani Clean Energy's research blogger. Use the Google Search tool to gather recent statistics, regulations, and market updates about India's clean-energy sector. Prioritise information published within the last 18 months and never fabricate sources.
Return a researched blog as JSON with the following keys:
- title (string)
- topic (string)
- excerpt (string)
- body_html (string with valid HTML such as <p>, <h2>, <ul>)
- keywords (array of 4-8 SEO keywords in lowercase)
- image_prompt (string describing a text-free 16:9 feature image)
TEXT;

    $userPrompt = <<<TEXT
Research brief: {$normalizedPrompt}

Audience: business and policy decision-makers in India evaluating solar and clean-energy investments.
Deliver at least 650 words with cited figures and next-step recommendations. Integrate the researched references naturally within the narrative.
TEXT;

    $payload = [
        'systemInstruction' => [
            'parts' => [
                ['text' => $systemInstruction],
            ],
        ],
        'contents' => [
            [
                'role' => 'user',
                'parts' => [
                    ['text' => $userPrompt],
                ],
            ],
        ],
        'tools' => [
            ['googleSearch' => (object) []],
        ],
        'generationConfig' => [
            'temperature' => 0.65,
            'topP' => 0.9,
            'topK' => 64,
            'maxOutputTokens' => 2048,
        ],
    ];

    $startTime = microtime(true);
    $status = 'failure';
    $response = [];
    $error = null;
    $usageMetadata = [];

    try {
        $response = ai_gemini_generate_content($model, $payload, $apiKey);
        $status = 'success';
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
        ai_log_activity('generate_draft_failed', [
            'model' => $model,
            'error' => $error,
            'prompt_length' => strlen($normalizedPrompt),
        ]);
        throw new RuntimeException(
            'The model did not respond as expected. Please try again or check your API key.',
            0,
            $exception
        );
    } finally {
        $latency = microtime(true) - $startTime;
        if (isset($response['usageMetadata'])) {
            $usageMetadata = $response['usageMetadata'];
        }
        ai_log_activity('generate_draft_content', [
            'model' => $model,
            'status' => $status,
            'latency' => $latency,
            'usage' => $usageMetadata,
            'error' => $error,
            'prompt_length' => strlen($normalizedPrompt),
        ]);
    }

    $text = ai_extract_text_from_gemini($response);
    if ($text === '') {
        throw new RuntimeException('Gemini returned an empty response while generating the blog draft.');
    }

    $data = ai_decode_model_json($text);

    $title = trim((string) ($data['title'] ?? ''));
    if ($title === '') {
        $title = ucwords(mb_substr($normalizedPrompt, 0, 120));
    }
    if ($title === '') {
        $title = 'AI Studio Insight';
    }
    ai_content_safety_check($title);

    $topic = trim((string) ($data['topic'] ?? ''));
    if ($topic === '') {
        $topic = $title;
    }

    $bodyHtml = (string) ($data['body_html'] ?? ($data['bodyHtml'] ?? ''));
    ai_content_safety_check($bodyHtml);
    $bodyHtml = blog_sanitize_html($bodyHtml);
    if ($bodyHtml === '') {
        throw new RuntimeException('Gemini did not return any body content for the blog draft.');
    }

    $excerpt = trim((string) ($data['excerpt'] ?? ''));
    if ($excerpt === '') {
        $excerpt = blog_extract_plain_text($bodyHtml);
        if (mb_strlen($excerpt) > 240) {
            $excerpt = trim(mb_substr($excerpt, 0, 240)) . '…';
        }
    }

    $keywords = ai_normalize_keywords($data['keywords'] ?? []);
    if (!$keywords) {
        $keywords = array_values(array_filter(array_unique(array_map(
            static function ($word) {
                $word = trim(mb_strtolower((string) $word));
                return $word !== '' && mb_strlen($word) > 3 ? $word : null;
            },
            preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($normalizedPrompt)) ?: []
        ))));
        if (count($keywords) > 8) {
            $keywords = array_slice($keywords, 0, 8);
        }
    }

    $imagePrompt = trim((string) ($data['image_prompt'] ?? ($data['imagePrompt'] ?? '')));
    if ($imagePrompt === '') {
        $imagePrompt = sprintf('Feature image showing %s in the context of clean energy, 16:9, no text.', mb_strtolower($normalizedPrompt));
    }

    ai_content_safety_check($excerpt);
    ai_content_safety_check($imagePrompt);

    return [
        'prompt' => $normalizedPrompt,
        'topic' => $topic,
        'title' => $title,
        'body_html' => $bodyHtml,
        'excerpt' => $excerpt,
        'keywords' => $keywords,
        'image_prompt' => $imagePrompt,
        'usage' => $usageMetadata,
    ];
}

function ai_generate_draft_id(): string
{
    return 'draft-' . bin2hex(random_bytes(8));
}

function ai_draft_path(string $id): string
{
    return ai_storage_path('drafts', $id . '.json');
}

function ai_load_draft(string $id): array
{
    $draft = ai_read_json_file(ai_draft_path($id));
    if (!$draft) {
        throw new RuntimeException('Draft not found.');
    }
    return $draft;
}

function ai_store_draft(array $draft): void
{
    if (empty($draft['id'])) {
        throw new RuntimeException('Invalid draft payload.');
    }
    ai_write_json_file(ai_draft_path($draft['id']), $draft);
}

function ai_delete_blog_draft(string $draftId, int $actorId): void
{
    unset($actorId);
    ai_bootstrap_storage();

    $draft = ai_load_draft($draftId);
    if (($draft['status'] ?? 'draft') === 'published') {
        throw new RuntimeException('Published drafts cannot be deleted.');
    }

    $draftPath = ai_draft_path($draftId);
    if (is_file($draftPath) && !@unlink($draftPath)) {
        throw new RuntimeException('Failed to delete draft file.');
    }

    $pattern = ai_storage_path('images', $draftId . '.*');
    foreach (glob($pattern) ?: [] as $imagePath) {
        if (is_file($imagePath)) {
            @unlink($imagePath);
        }
    }
}

function ai_all_drafts(): array
{
    ai_bootstrap_storage();
    $files = glob(ai_storage_path('drafts', '*.json')) ?: [];
    $drafts = [];
    foreach ($files as $file) {
        $data = ai_read_json_file($file);
        if (!is_array($data) || empty($data['id'])) {
            continue;
        }
        $drafts[$data['id']] = $data;
    }
    return $drafts;
}

function ai_unique_draft_slug(string $slug, ?string $currentId = null): string
{
    $slug = blog_slugify($slug);
    if ($slug === '') {
        $slug = 'draft';
    }
    $drafts = ai_all_drafts();
    $existing = array_map(static fn ($draft) => $draft['slug'] ?? '', $drafts);
    $existing = array_filter($existing);

    if ($currentId !== null && isset($drafts[$currentId])) {
        $currentSlug = $drafts[$currentId]['slug'] ?? '';
        $existing = array_diff($existing, [$currentSlug]);
    }

    $candidate = $slug;
    $index = 2;
    while (in_array($candidate, $existing, true)) {
        $candidate = $slug . '-' . $index;
        $index++;
    }

    return $candidate;
}

function ai_save_blog_draft(array $input, int $actorId): array
{
    unset($actorId);
    ai_bootstrap_storage();
    ai_require_enabled();

    $draftId = isset($input['draft_id']) ? trim((string) $input['draft_id']) : '';
    $isNew = $draftId === '';

    if ($isNew) {
        $draftId = ai_generate_draft_id();
        $draft = [
            'id' => $draftId,
            'status' => 'draft',
            'created_at' => gmdate(DateTimeInterface::ATOM),
            'published_post_id' => null,
            'published_slug' => null,
            'published_at' => null,
        ];
    } else {
        $draft = ai_load_draft($draftId);
        if ($draft['status'] === 'published') {
            throw new RuntimeException('Published drafts cannot be edited.');
        }
    }

    $title = trim((string) ($input['title'] ?? ($draft['title'] ?? '')));
    $body = (string) ($input['body'] ?? ($draft['body'] ?? ''));
    $excerpt = trim((string) ($input['excerpt'] ?? ($draft['excerpt'] ?? '')));
    $topic = trim((string) ($input['topic'] ?? ($draft['topic'] ?? $title)));
    $tone = trim((string) ($input['tone'] ?? ($draft['tone'] ?? '')));

    ai_content_safety_check($title);
    ai_content_safety_check($body);
    ai_content_safety_check($excerpt);
    ai_content_safety_check($topic);
    $audience = trim((string) ($input['audience'] ?? ($draft['audience'] ?? '')));
    $purpose = trim((string) ($input['purpose'] ?? ($draft['purpose'] ?? '')));
    $generatedTitle = trim((string) ($input['generated_title'] ?? ($draft['generated_title'] ?? '')));
    $generatedBody = (string) ($input['generated_body'] ?? ($draft['generated_body'] ?? ''));
    $keywords = ai_normalize_keywords($input['keywords'] ?? ($draft['keywords'] ?? []));
    $imagePrompt = trim((string) ($input['image_prompt'] ?? ($draft['image_prompt'] ?? '')));
    $authorName = trim((string) ($input['author_name'] ?? ($draft['author_name'] ?? '')));
    $slugInput = trim((string) ($input['slug'] ?? ($draft['slug'] ?? $title)));

    if ($title === '' || trim(strip_tags($body)) === '') {
        throw new RuntimeException('Provide a title and body before saving the draft.');
    }
    if ($excerpt === '') {
        $excerpt = blog_extract_plain_text($body);
        if (mb_strlen($excerpt) > 240) {
            $excerpt = trim(mb_substr($excerpt, 0, 240)) . '…';
        }
    }
    if ($topic === '') {
        $topic = $title;
    }

    $slug = ai_unique_draft_slug($slugInput !== '' ? $slugInput : $title, $isNew ? null : $draftId);

    $draft['title'] = $title;
    $draft['topic'] = $topic;
    $draft['tone'] = $tone;
    $draft['audience'] = $audience;
    $draft['purpose'] = $purpose;
    $draft['generated_title'] = $generatedTitle !== '' ? $generatedTitle : $title;
    $draft['generated_body'] = $generatedBody !== '' ? $generatedBody : $body;
    $draft['body'] = $body;
    $draft['excerpt'] = $excerpt;
    $draft['keywords'] = $keywords;
    $draft['image_prompt'] = $imagePrompt;
    $draft['author_name'] = $authorName;
    $draft['slug'] = $slug;
    $draft['updated_at'] = gmdate(DateTimeInterface::ATOM);

    ai_store_draft($draft);

    return [
        'id' => $draftId,
        'title' => $title,
        'slug' => $slug,
        'status' => $draft['status'],
    ];
}

function ai_generate_blog_draft(array $options, int $actorId): array
{
    unset($actorId);
    ai_require_enabled();
    if (!ai_has_api_key()) {
        throw new RuntimeException('Missing API key. Configure it in AI Studio settings.');
    }
    ai_check_quotas();

    $topic = trim((string) ($options['topic'] ?? ''));
    if ($topic === '') {
        throw new RuntimeException('Topic/Keywords are required to generate a draft.');
    }

    $title = trim((string) ($options['title'] ?? ''));
    $tone = trim((string) ($options['tone'] ?? 'informative'));
    $length = trim((string) ($options['target_length'] ?? 'medium'));
    $audience = trim((string) ($options['audience'] ?? ''));

    $prompt = "Blog topic: {$topic}.";
    if ($title !== '') {
        $prompt .= " Desired title: \"{$title}\".";
    }
    if ($tone !== '') {
        $prompt .= " Tone should be {$tone}.";
    }
    if ($length !== '') {
        $wordCount = ['short' => 300, 'medium' => 600, 'long' => 1000];
        $prompt .= " Target length is around {$wordCount[$length]} words.";
    }
    if ($audience !== '') {
        $prompt .= " The target audience is {$audience}.";
    }

    $content = ai_generate_blog_draft_content($prompt);

    $tokens = (int) ($content['usage']['totalTokens'] ?? 0);
    if ($tokens === 0) {
        $tokens = (int) (strlen($content['body_html']) / 4);
    }
    ai_update_usage_data($tokens);

    ai_log_activity('generate_draft_api', [
        'topic' => $topic,
        'title' => $title,
        'tone' => $tone,
        'length' => $length,
        'audience' => $audience,
        'tokens' => $tokens,
    ]);

    $payload = [
        'title' => $title !== '' ? $title : $content['title'],
        'body' => $content['body_html'],
        'excerpt' => $content['excerpt'],
        'topic' => $content['topic'],
        'tone' => $tone,
        'audience' => $audience,
        'purpose' => 'Prompt: ' . mb_substr($content['prompt'], 0, 240),
        'generated_title' => $content['title'],
        'generated_body' => $content['body_html'],
        'keywords' => $content['keywords'],
        'image_prompt' => $content['image_prompt'],
        'author_name' => '',
    ];

    $saved = ai_save_blog_draft($payload, 0);

    $artworkGenerated = true;
    try {
        ai_generate_image_for_draft($saved['id'], 0);
    } catch (Throwable $exception) {
        $artworkGenerated = false;
        error_log('AI Studio draft image skipped: ' . $exception->getMessage());
    }

    return [
        'draft_id' => $saved['id'],
        'title' => $saved['title'],
        'artwork_generated' => $artworkGenerated,
    ];
}

function ai_generate_blog_draft_from_prompt(string $prompt, int $actorId): array
{
    $options = [
        'topic' => $prompt,
    ];
    return ai_generate_blog_draft($options, $actorId);
}

function ai_list_temp_snapshots(): array
{
    ai_bootstrap_storage();
    $files = glob(ai_temp_path('*.json')) ?: [];
    $snapshots = [];
    foreach ($files as $file) {
        $data = ai_read_json_file($file);
        if (is_array($data) && !empty($data['content'])) {
            $snapshots[basename($file, '.json')] = $data;
        }
    }
    return $snapshots;
}

function ai_restore_draft_from_temp(array $input, int $actorId): array
{
    $draftId = trim((string) ($input['draftId'] ?? ''));
    if ($draftId === '') {
        throw new RuntimeException('Draft ID is required.');
    }

    $tempPath = ai_temp_path($draftId . '.json');
    if (!is_file($tempPath)) {
        throw new RuntimeException('Snapshot not found.');
    }

    $data = ai_read_json_file($tempPath);
    $content = (string) ($data['content'] ?? '');
    if ($content === '') {
        throw new RuntimeException('Snapshot is empty.');
    }

    // Clean up the temp file after restoring
    @unlink($tempPath);

    return ['content' => $content];
}

function ai_stream_generate_blog_draft(array $options, int $actorId): void
{
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');

    ob_end_flush();

    $sendEvent = static function (string $name, array $data): void {
        echo "event: ${name}\n";
        echo 'data: ' . json_encode($data) . "\n\n";
        flush();
    };

    try {
        ai_require_enabled();
        if (!ai_has_api_key()) {
            throw new RuntimeException('Missing API key. Configure it in AI Studio settings.');
        }
        ai_check_quotas();

        $topic = trim((string) ($options['topic'] ?? ''));
        if ($topic === '') {
            throw new RuntimeException('Topic/Keywords are required to generate a draft.');
        }

        $topic = trim((string) ($options['topic'] ?? ''));
        if ($topic === '') {
            throw new RuntimeException('Topic/Keywords are required to generate a draft.');
        }

        $title = trim((string) ($options['title'] ?? ''));
        $tone = trim((string) ($options['tone'] ?? 'informative'));
        $length = trim((string) ($options['target_length'] ?? 'medium'));
        $audience = trim((string) ($options['audience'] ?? ''));

        $prompt = "Blog topic: {$topic}.";
        if ($title !== '') {
            $prompt .= " Desired title: \"{$title}\".";
        }
        if ($tone !== '') {
            $prompt .= " Tone should be {$tone}.";
        }
        if ($length !== '') {
            $wordCount = ['short' => 300, 'medium' => 600, 'long' => 1000];
            $prompt .= " Target length is around {$wordCount[$length]} words.";
        }
        if ($audience !== '') {
            $prompt .= " The target audience is {$audience}.";
        }

        $content = ai_generate_blog_draft_content($prompt);
        $fullBody = $content['body_html'];
        $words = preg_split('/\s+/', $fullBody) ?: [];
        $draftId = ai_generate_draft_id();

        $sendEvent('start', [
            'draftId' => $draftId,
            'metadata' => [
                'title' => $content['title'],
                'topic' => $content['topic'],
                'excerpt' => $content['excerpt'],
                'keywords' => $content['keywords'],
                'image_prompt' => $content['image_prompt'],
            ]
        ]);

        $streamAborted = false;
        $streamedContent = '';
        $lastSaveTime = microtime(true);
        $tempPath = ai_temp_path($draftId . '.json');

        register_shutdown_function(function() use ($tempPath) {
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }
        });

        foreach ($words as $word) {
            if (connection_aborted()) {
                $streamAborted = true;
                // Save one last time before aborting
                ai_write_json_file($tempPath, ['content' => $streamedContent]);
                break;
            }
            $streamedContent .= $word . ' ';
            $sendEvent('chunk', ['text' => $word . ' ']);

            if (microtime(true) - $lastSaveTime > 15) {
                ai_write_json_file($tempPath, ['content' => $streamedContent]);
                $lastSaveTime = microtime(true);
            }

            usleep(50000); // 50ms delay
        }

        if (file_exists($tempPath)) {
            @unlink($tempPath);
        }

        ai_content_safety_check($streamedContent);

        $payload = [
            'draft_id' => $draftId,
            'title' => $title !== '' ? $title : $content['title'],
            'body' => $streamedContent,
            'excerpt' => $content['excerpt'],
            'topic' => $content['topic'],
            'tone' => $tone,
            'audience' => $audience,
            'keywords' => $content['keywords'],
            'image_prompt' => $content['image_prompt'],
        ];

        $saved = ai_save_blog_draft($payload, $actorId);
        $sendEvent('saved', ['draft' => $saved]);

        $tokens = (int) ($content['usage']['totalTokens'] ?? 0);
        ai_update_usage_data($tokens > 0 ? $tokens : (int)(strlen($fullBody) / 4));

        ai_log_activity('stream_generate_draft', [
            'topic' => $topic,
            'status' => 'success',
            'aborted' => $streamAborted,
            'tokens' => $tokens,
        ]);

        $sendEvent('complete', ['message' => 'Draft saved successfully.']);

    } catch (Throwable $exception) {
        $sendEvent('error', ['message' => $exception->getMessage()]);
    }
}

function ai_image_file_path(string $draftId): string
{
    return ai_storage_path('images', $draftId . '.svg');
}

function ai_draft_image_data_uri(array $draft): string
{
    $path = $draft['image_file'] ?? '';
    if ($path === '' || !is_file(ai_storage_path($path))) {
        return '';
    }
    $fullPath = ai_storage_path($path);
    $contents = @file_get_contents($fullPath);
    if ($contents === false) {
        return '';
    }
    $mime = (string) ($draft['image_mime'] ?? 'image/png');
    if (stripos($mime, 'image/') !== 0) {
        $mime = 'image/png';
    }
    return 'data:' . $mime . ';base64,' . base64_encode($contents);
}

function ai_generate_image_for_draft(string $draftId, int $actorId): array
{
    unset($actorId);
    ai_require_enabled();
    $draft = ai_load_draft($draftId);
    if (($draft['status'] ?? 'draft') === 'published') {
        throw new RuntimeException('Published drafts already carry a final cover.');
    }

    $title = $draft['title'] ?? ($draft['topic'] ?? 'Blog draft');
    $prompt = $draft['image_prompt'] ?? ($draft['topic'] ?? 'Clean energy insight');
    $settings = ai_get_settings();
    $imageModel = $settings['image_model'] ?? '';
    if ($imageModel === '') {
        throw new RuntimeException('Set a Gemini image model in AI settings before generating artwork.');
    }

    $apiKey = ai_resolve_api_key();
    if ($apiKey === null || $apiKey === '') {
        throw new RuntimeException('Configure the Gemini API key to generate artwork.');
    }

    $composedPrompt = sprintf(
        '%s. Photorealistic 16:9 composition, cinematic lighting, advanced solar technology, vibrant India skyline, no text or watermarks.',
        trim((string) $prompt) !== '' ? trim((string) $prompt) : 'Clean energy insight'
    );

    $imageDataUri = '';
    $alt = sprintf('Feature image for %s', $title);
    $mime = 'image/png';
    $relativePath = '';

    try {
        $response = ai_gemini_generate_image($imageModel, $composedPrompt, $apiKey);
        $payload = ai_extract_image_payload($response);
        $binary = $payload['binary'];
        $mime = $payload['mime'];
        if (stripos($mime, 'image/') !== 0) {
            $mime = 'image/png';
        }
        $extension = ai_extension_from_mime($mime);
        $relativePath = ai_store_draft_image($draftId, $binary, $extension);
        $imageDataUri = 'data:' . $mime . ';base64,' . base64_encode($binary);
    } catch (Throwable $exception) {
        error_log('Gemini image generation failed: ' . $exception->getMessage());
        [$fallbackImage, $fallbackAlt] = blog_generate_placeholder_cover($title, (string) $prompt);
        $parts = explode(',', $fallbackImage, 2);
        $raw = $parts[1] ?? '';
        $binary = base64_decode($raw, true);
        if ($binary === false) {
            throw new RuntimeException('Failed to prepare draft image.');
        }
        $mime = 'image/svg+xml';
        $relativePath = ai_store_draft_image($draftId, $binary, 'svg');
        $alt = $fallbackAlt;
        $imageDataUri = $fallbackImage;
    }

    $draft['image_file'] = $relativePath;
    $draft['image_alt'] = $alt;
    $draft['image_mime'] = $mime;
    $draft['updated_at'] = gmdate(DateTimeInterface::ATOM);
    ai_store_draft($draft);

    return ['image' => $imageDataUri, 'alt' => $alt];
}

function ai_schedule_blog_draft(string $draftId, ?DateTimeImmutable $publishAtIst, int $actorId): void
{
    unset($actorId);
    ai_require_enabled();
    $draft = ai_load_draft($draftId);
    if ($draft['status'] === 'published') {
        throw new RuntimeException('Published drafts cannot be rescheduled.');
    }

    if ($publishAtIst instanceof DateTimeImmutable) {
        $utc = $publishAtIst->setTimezone(new DateTimeZone('UTC'))->format(DateTimeInterface::ATOM);
        $draft['schedule_at'] = $utc;
        $draft['status'] = 'scheduled';
    } else {
        $draft['schedule_at'] = null;
        $draft['status'] = 'draft';
    }
    $draft['updated_at'] = gmdate(DateTimeInterface::ATOM);
    ai_store_draft($draft);
}

function ai_list_blog_drafts(): array
{
    ai_bootstrap_storage();
    $drafts = ai_all_drafts();
    $timezone = new DateTimeZone('Asia/Kolkata');

    uasort($drafts, static function (array $a, array $b): int {
        $timeA = isset($a['updated_at']) ? strtotime((string) $a['updated_at']) : 0;
        $timeB = isset($b['updated_at']) ? strtotime((string) $b['updated_at']) : 0;
        return $timeB <=> $timeA;
    });

    return array_map(static function (array $draft) use ($timezone): array {
        $scheduledAt = null;
        if (!empty($draft['schedule_at'])) {
            try {
                $scheduledAt = new DateTimeImmutable($draft['schedule_at']);
                $scheduledAt = $scheduledAt->setTimezone($timezone);
            } catch (Throwable $exception) {
                $scheduledAt = null;
            }
        }

        $image = '';
        if (!empty($draft['image_file'])) {
            $image = ai_draft_image_data_uri($draft);
        }

        return [
            'id' => $draft['id'],
            'title' => $draft['title'] ?? '',
            'topic' => $draft['topic'] ?? '',
            'tone' => $draft['tone'] ?? '',
            'audience' => $draft['audience'] ?? '',
            'keywords' => $draft['keywords'] ?? [],
            'status' => $draft['status'] ?? 'draft',
            'post_status' => ($draft['status'] ?? 'draft') === 'published' ? 'published' : 'draft',
            'scheduled_at' => $scheduledAt,
            'cover_image' => $image,
            'cover_image_alt' => $draft['image_alt'] ?? '',
            'slug' => $draft['slug'] ?? '',
            'updated_at' => $draft['updated_at'] ?? '',
            'published_post_id' => $draft['published_post_id'] ?? null,
            'published_slug' => $draft['published_slug'] ?? null,
        ];
    }, array_values($drafts));
}

function ai_publish_due_posts(PDO $db, ?DateTimeImmutable $now = null): int
{
    ai_bootstrap_storage();
    $drafts = ai_all_drafts();
    if (!$drafts) {
        return 0;
    }

    $now = $now ?: new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $count = 0;
    foreach ($drafts as $draft) {
        if (($draft['status'] ?? 'draft') !== 'scheduled') {
            continue;
        }
        if (empty($draft['schedule_at'])) {
            continue;
        }
        try {
            $scheduledUtc = new DateTimeImmutable($draft['schedule_at']);
        } catch (Throwable $exception) {
            continue;
        }
        if ($scheduledUtc > $now) {
            continue;
        }

        if (ai_publish_single_draft($db, $draft, $now)) {
            $count++;
        }
    }

    return $count;
}

function ai_publish_blog_draft_now(PDO $db, string $draftId, int $actorId): array
{
    unset($actorId);
    ai_bootstrap_storage();

    $draft = ai_load_draft($draftId);
    if (($draft['status'] ?? 'draft') === 'published') {
        throw new RuntimeException('Draft already published.');
    }

    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    if (!ai_publish_single_draft($db, $draft, $now)) {
        throw new RuntimeException('Failed to publish draft.');
    }

    return ai_load_draft($draftId);
}

function ai_publish_single_draft(PDO $db, array $draft, DateTimeImmutable $nowUtc): bool
{
    if (empty($draft['id'])) {
        return false;
    }

    try {
        $slug = ai_resolve_publish_slug($db, (string) ($draft['slug'] ?? ''));
        $coverImage = '';
        if (!empty($draft['image_file']) && is_file(ai_storage_path($draft['image_file']))) {
            $coverContents = @file_get_contents(ai_storage_path($draft['image_file']));
            if ($coverContents !== false) {
                $mime = (string) ($draft['image_mime'] ?? 'image/png');
                if (stripos($mime, 'image/') !== 0) {
                    $mime = 'image/png';
                }
                $coverImage = 'data:' . $mime . ';base64,' . base64_encode($coverContents);
            }
        }
        if ($coverImage === '') {
            [$fallbackImage, $fallbackAlt] = blog_generate_placeholder_cover($draft['title'] ?? 'AI Draft', $draft['topic'] ?? '');
            $coverImage = $fallbackImage;
            if (empty($draft['image_alt'])) {
                $draft['image_alt'] = $fallbackAlt;
            }
        }

        $payload = [
            'title' => $draft['title'] ?? 'AI Draft',
            'slug' => $slug,
            'excerpt' => $draft['excerpt'] ?? '',
            'body' => $draft['body'] ?? '',
            'authorName' => $draft['author_name'] ?? '',
            'status' => 'published',
            'tags' => $draft['keywords'] ?? [],
            'coverImage' => $coverImage,
            'coverImageAlt' => $draft['image_alt'] ?? '',
            'coverPrompt' => $draft['image_prompt'] ?? '',
        ];

        $saved = blog_save_post($db, $payload, 0);
    } catch (Throwable $exception) {
        return false;
    }

    $draft['status'] = 'published';
    $draft['published_post_id'] = (int) ($saved['id'] ?? 0);
    $draft['published_slug'] = $saved['slug'] ?? $slug;
    $draft['slug'] = $draft['published_slug'];
    $draft['published_at'] = $nowUtc->format(DateTimeInterface::ATOM);
    $draft['schedule_at'] = null;
    $draft['updated_at'] = $nowUtc->format(DateTimeInterface::ATOM);

    try {
        ai_store_draft($draft);
    } catch (Throwable $exception) {
        return false;
    }

    return true;
}

function ai_resolve_publish_slug(PDO $db, string $desired): string
{
    $slug = $desired !== '' ? blog_slugify($desired) : '';
    if ($slug === '') {
        $slug = 'ai-draft';
    }
    $candidate = $slug;
    $index = 2;
    while (ai_blog_slug_exists($db, $candidate)) {
        $candidate = $slug . '-' . $index;
        $index++;
    }
    return $candidate;
}

function ai_blog_slug_exists(PDO $db, string $slug): bool
{
    $stmt = $db->prepare('SELECT 1 FROM blog_posts WHERE slug = :slug LIMIT 1');
    $stmt->execute([':slug' => $slug]);
    return (bool) $stmt->fetchColumn();
}

function ai_daily_notes_generate_if_due(?DateTimeImmutable $now = null): void
{
    ai_bootstrap_storage();
    $nowIst = ($now ?: new DateTimeImmutable('now', new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Kolkata'));
    $dateKey = $nowIst->format('Y-m-d');

    foreach (AI_DAILY_NOTE_TYPES as $type) {
        $targetHour = $type === 'work_done' ? 20 : 21;
        $target = $nowIst->setTime($targetHour, 0);
        if ($nowIst < $target) {
            continue;
        }

        $path = ai_storage_path('notes', sprintf('%s-%s.json', $dateKey, $type));
        if (is_file($path)) {
            continue;
        }

        $content = $type === 'work_done'
            ? ai_daily_notes_build_work_summary($nowIst)
            : ai_daily_notes_build_plan_summary($nowIst);

        $payload = [
            'date' => $dateKey,
            'type' => $type,
            'content' => $content,
            'generated_at' => $nowIst->setTimezone(new DateTimeZone('UTC'))->format(DateTimeInterface::ATOM),
        ];
        ai_write_json_file($path, $payload);
    }
}

function ai_daily_notes_recent(int $limit = 6): array
{
    ai_bootstrap_storage();
    $files = glob(ai_storage_path('notes', '*.json')) ?: [];
    $notes = [];
    foreach ($files as $file) {
        $data = ai_read_json_file($file);
        if (!is_array($data) || empty($data['type']) || empty($data['content'])) {
            continue;
        }
        $notes[] = $data;
    }

    usort($notes, static function (array $a, array $b): int {
        $timeA = isset($a['generated_at']) ? strtotime((string) $a['generated_at']) : 0;
        $timeB = isset($b['generated_at']) ? strtotime((string) $b['generated_at']) : 0;
        return $timeB <=> $timeA;
    });

    $notes = array_slice($notes, 0, $limit);
    $istZone = new DateTimeZone('Asia/Kolkata');

    return array_map(static function (array $note) use ($istZone): array {
        $generatedAt = null;
        try {
            $generatedAt = new DateTimeImmutable($note['generated_at'] ?? 'now', new DateTimeZone('UTC'));
        } catch (Throwable $exception) {
            $generatedAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        }
        $displayTime = $generatedAt->setTimezone($istZone)->format('d M Y · h:i A');
        $label = $note['type'] === 'work_done' ? 'Work Done Today' : 'Next-Day Plan';

        return [
            'date' => $note['date'] ?? '',
            'type' => $note['type'],
            'label' => $label,
            'content' => $note['content'],
            'generated_at' => $note['generated_at'] ?? '',
            'display_time' => $displayTime,
            'display_label' => 'Generated ' . $displayTime,
        ];
    }, $notes);
}

function ai_daily_notes_build_work_summary(DateTimeImmutable $nowIst): string
{
    $dateLabel = $nowIst->format('d M Y');
    return sprintf('Work Done Today (%s): Capture the day\'s wins, log customer escalations, and share quick updates before sign-off.', $dateLabel);
}

function ai_daily_notes_build_plan_summary(DateTimeImmutable $nowIst): string
{
    $nextDay = $nowIst->modify('+1 day')->format('d M Y');
    return sprintf('Next-Day Plan (%s): Align morning priorities, lock installation checklists, and confirm subsidy follow-ups for the team.', $nextDay);
}

function ai_generate_sms_template(string $prompt): array
{
    ai_require_enabled();
    $settings = ai_get_settings();
    $model = $settings['text_model'] ?? '';
    if ($model === '') {
        throw new RuntimeException('Set a Gemini text model in AI settings before generating templates.');
    }

    $apiKey = ai_resolve_api_key();
    if ($apiKey === null || $apiKey === '') {
        throw new RuntimeException('Configure the Gemini API key to enable SMS/WhatsApp templates.');
    }

    $systemInstruction = "You are a helpful assistant for a solar energy company. Generate a concise and professional SMS/WhatsApp template based on the user's prompt. The template should be under 160 characters.";
    $payload = [
        'contents' => [
            [
                'role' => 'user',
                'parts' => [['text' => $prompt]],
            ],
            [
                'role' => 'model',
                'parts' => [['text' => $systemInstruction]],
            ],
        ],
    ];

    $response = ai_gemini_generate_content($model, $payload, $apiKey);
    $text = ai_extract_text_from_gemini($response);

    if ($text === '') {
        throw new RuntimeException('Gemini returned an empty response while generating the template.');
    }

    return ['template' => $text];
}

function ai_convert_installation_notes(string $notes): array
{
    ai_require_enabled();
    $settings = ai_get_settings();
    $model = $settings['text_model'] ?? '';
    if ($model === '') {
        throw new RuntimeException('Set a Gemini text model in AI settings before converting notes.');
    }

    $apiKey = ai_resolve_api_key();
    if ($apiKey === null || $apiKey === '') {
        throw new RuntimeException('Configure the Gemini API key to enable note conversion.');
    }

    $prompt = "Convert the following raw installation notes into a formatted, professional message suitable for sharing with a customer:\n\n" . $notes;
    $payload = [
        'contents' => [
            [
                'role' => 'user',
                'parts' => [['text' => $prompt]],
            ],
        ],
    ];

    $response = ai_gemini_generate_content($model, $payload, $apiKey);
    $text = ai_extract_text_from_gemini($response);

    if ($text === '') {
        throw new RuntimeException('Gemini returned an empty response while converting the notes.');
    }

    return ['message' => $text];
}
