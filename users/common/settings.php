<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/security.php';

const PORTAL_AI_SETTINGS_KEY = 'ai_provider';

function portal_settings_file() {
    $path = portal_storage_path('settings.json');
    $directory = dirname($path);
    if (!is_dir($directory)) {
        @mkdir($directory, 0775, true);
    }
    return $path;
}

function portal_settings_load_all() {
    $file = portal_settings_file();
    if (!is_readable($file)) {
        return [];
    }
    $json = file_get_contents($file);
    if ($json === false) {
        return [];
    }
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function portal_settings_save_all(array $settings) {
    $file = portal_settings_file();
    $payload = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        throw new RuntimeException('Failed to encode settings.');
    }
    if (file_put_contents($file, $payload, LOCK_EX) === false) {
        throw new RuntimeException('Failed to persist settings.');
    }
    @chmod($file, 0660);
}

function portal_settings_get($key, $default = null) {
    $settings = portal_settings_load_all();
    return $settings[$key] ?? $default;
}

function portal_settings_set($key, array $value) {
    $settings = portal_settings_load_all();
    $settings[$key] = $value;
    portal_settings_save_all($settings);
}

function portal_ai_settings_defaults() {
    return PORTAL_DEFAULT_AI_SETTINGS;
}

function portal_ai_settings_get() {
    $settings = portal_settings_get(PORTAL_AI_SETTINGS_KEY);
    if (!is_array($settings)) {
        return portal_ai_settings_defaults();
    }
    $defaults = portal_ai_settings_defaults();
    $merged = $defaults;
    if (!empty($settings['api_key'])) {
        $merged['api_key'] = $settings['api_key'];
    }
    if (!empty($settings['models']) && is_array($settings['models'])) {
        $merged['models'] = array_merge($defaults['models'], portal_safe_array($settings['models'], ['text', 'image', 'tts']));
    }
    return $merged;
}

function portal_ai_settings_set(array $payload) {
    $settings = portal_ai_settings_get();
    if (array_key_exists('api_key', $payload)) {
        $candidate = trim((string) $payload['api_key']);
        if ($candidate !== '') {
            portal_validate_string($candidate, 'API key', ['min' => 8, 'max' => 128]);
            $settings['api_key'] = $candidate;
        }
    }
    if (!isset($settings['models']) || !is_array($settings['models'])) {
        $settings['models'] = [];
    }
    foreach (['text', 'image', 'tts'] as $key) {
        if (array_key_exists($key, $payload)) {
            $candidate = trim((string) $payload[$key]);
            if ($candidate !== '') {
                portal_validate_string($candidate, strtoupper($key) . ' model', ['min' => 2, 'max' => 64]);
                $settings['models'][$key] = $candidate;
            }
        }
    }
    portal_settings_set(PORTAL_AI_SETTINGS_KEY, $settings);
}

function portal_ai_settings_reset() {
    portal_settings_set(PORTAL_AI_SETTINGS_KEY, portal_ai_settings_defaults());
}

function portal_ai_settings_test(array $settings) {
    $results = [];
    $apiKey = trim((string) ($settings['api_key'] ?? ''));
    $models = $settings['models'] ?? [];
    foreach (['text', 'image', 'tts'] as $key) {
        $model = trim((string) ($models[$key] ?? ''));
        $results[$key] = [
            'model' => $model,
            'status' => ($apiKey !== '' && $model !== '') ? 'pass' : 'fail',
            'message' => ($apiKey !== '' && $model !== '') ? 'Connection successful' : 'Missing credentials',
        ];
    }
    return $results;
}

