<?php
declare(strict_types=1);

/**
 * Lightweight loader for admin dashboard metrics stored in a JSON file.
 *
 * This module keeps the admin overview operational even when the SQL
 * database is unavailable by allowing manual file-based values to feed the
 * dashboard counters, reminder badges, and highlight timeline.
 */

function dashboard_metrics_default_path(): string
{
    return __DIR__ . '/../storage/dashboard-metrics.json';
}

function dashboard_metrics_load(?string $path = null): array
{
    $path = $path ?? dashboard_metrics_default_path();

    $result = [
        'cards' => [],
        'reminders' => [],
        'highlights' => [],
        'sources' => [
            'cards' => 'default',
            'reminders' => 'default',
            'highlights' => 'default',
        ],
    ];

    if (!is_file($path)) {
        return $result;
    }

    $contents = @file_get_contents($path);
    if ($contents === false || trim($contents) === '') {
        return $result;
    }

    try {
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $exception) {
        error_log(sprintf('dashboard_metrics_load: invalid JSON (%s)', $exception->getMessage()));
        return $result;
    }

    if (!is_array($decoded)) {
        return $result;
    }

    if (isset($decoded['cards']) && is_array($decoded['cards'])) {
        $result['sources']['cards'] = 'file';
        $result['cards'] = dashboard_metrics_normalise_cards($decoded['cards']);
    }

    if (isset($decoded['reminders']) && is_array($decoded['reminders'])) {
        $result['sources']['reminders'] = 'file';
        $result['reminders'] = dashboard_metrics_normalise_reminders($decoded['reminders']);
    }

    if (isset($decoded['highlights']) && is_array($decoded['highlights'])) {
        $normalisedHighlights = dashboard_metrics_normalise_highlights($decoded['highlights']);
        if ($normalisedHighlights !== []) {
            $result['sources']['highlights'] = 'file';
            $result['highlights'] = $normalisedHighlights;
        }
    }

    return $result;
}

function dashboard_metrics_normalise_cards(array $cards): array
{
    $normalised = [];

    foreach ($cards as $key => $card) {
        if (!is_array($card)) {
            continue;
        }

        $cardKey = dashboard_metrics_extract_key($key, $card);
        if ($cardKey === '') {
            continue;
        }

        $normalised[$cardKey] = [
            'value' => isset($card['value']) ? (int) $card['value'] : null,
            'label' => dashboard_metrics_clean_string($card['label'] ?? null),
            'description' => dashboard_metrics_clean_string($card['description'] ?? null),
            'link' => dashboard_metrics_clean_string($card['link'] ?? null),
        ];
    }

    return $normalised;
}

function dashboard_metrics_normalise_reminders(array $reminders): array
{
    $defaults = [
        'due_today' => 0,
        'overdue' => 0,
        'upcoming' => 0,
    ];

    foreach ($defaults as $key => $fallback) {
        if (isset($reminders[$key])) {
            $defaults[$key] = (int) $reminders[$key];
        }
    }

    return $defaults;
}

function dashboard_metrics_normalise_highlights(array $highlights): array
{
    $prepared = [];

    foreach ($highlights as $highlight) {
        if (!is_array($highlight)) {
            continue;
        }

        $module = dashboard_metrics_clean_key($highlight['module'] ?? '');
        $summary = dashboard_metrics_clean_string($highlight['summary'] ?? null);
        $timestamp = dashboard_metrics_clean_string($highlight['timestamp'] ?? null);

        if ($module === '' || $summary === '' || $timestamp === '') {
            continue;
        }

        try {
            $date = new DateTimeImmutable($timestamp);
        } catch (Throwable $exception) {
            continue;
        }

        $prepared[] = [
            'module' => $module,
            'summary' => $summary,
            'timestamp' => $date->format(DateTimeInterface::ATOM),
        ];
    }

    usort($prepared, static function (array $left, array $right): int {
        return strcmp($right['timestamp'], $left['timestamp']);
    });

    return $prepared;
}

function dashboard_metrics_extract_key($key, array $card): string
{
    if (is_string($key) && $key !== '') {
        $candidate = dashboard_metrics_clean_key($key);
        if ($candidate !== '') {
            return $candidate;
        }
    }

    if (isset($card['key'])) {
        $candidate = dashboard_metrics_clean_key($card['key']);
        if ($candidate !== '') {
            return $candidate;
        }
    }

    return '';
}

function dashboard_metrics_clean_key($value): string
{
    if (!is_string($value)) {
        return '';
    }

    $clean = strtolower(trim($value));
    $clean = preg_replace('/[^a-z0-9_-]+/', '', $clean);
    if (!is_string($clean)) {
        return '';
    }

    return $clean;
}

function dashboard_metrics_clean_string($value): string
{
    if (!is_string($value)) {
        return '';
    }

    $trimmed = trim($value);
    return $trimmed;
}

function dashboard_metrics_resolve_link(string $link, callable $pathResolver): string
{
    $link = dashboard_metrics_clean_string($link);
    if ($link === '') {
        return '';
    }

    if (preg_match('/^(https?:\/\/|mailto:|tel:)/i', $link) === 1) {
        return $link;
    }

    if ($link[0] === '#') {
        return $link;
    }

    if ($link[0] === '/') {
        return $pathResolver(ltrim($link, '/'));
    }

    return $pathResolver($link);
}
