<?php
require_once __DIR__ . '/config.php';

/**
 * Centralized validation helpers
 */
function portal_validate_string($value, $label, $options = []) {
    $value = is_string($value) ? trim($value) : '';
    $min = $options['min'] ?? 0;
    $max = $options['max'] ?? 0;
    if ($min > 0 && mb_strlen($value) < $min) {
        throw new InvalidArgumentException($label . ' must be at least ' . $min . ' characters long.');
    }
    if ($max > 0 && mb_strlen($value) > $max) {
        throw new InvalidArgumentException($label . ' must be less than ' . $max . ' characters.');
    }
    return $value;
}

function portal_validate_enum($value, $label, array $allowed) {
    if (!in_array($value, $allowed, true)) {
        throw new InvalidArgumentException($label . ' is invalid.');
    }
    return $value;
}

function portal_mask_secret($value) {
    if ($value === '' || $value === null) {
        return '';
    }
    $len = mb_strlen($value);
    if ($len <= 4) {
        return str_repeat('•', $len);
    }
    return mb_substr($value, 0, 2) . str_repeat('•', $len - 4) . mb_substr($value, -2);
}

/**
 * Role based permissions. Deny by default, allow per capability.
 */
function portal_default_permissions() {
    return [
        'admin' => [
            'dashboard.view',
            'settings.view',
            'settings.update',
            'ai.test',
            'activity.view',
            'errors.view',
            'notifications.view',
        ],
        'employee' => [
            'dashboard.view',
            'notifications.view',
        ],
        'installer' => [
            'dashboard.view',
        ],
        'referrer' => [
            'dashboard.view',
        ],
        'customer' => [
            'dashboard.view',
        ],
    ];
}

function portal_permission_catalog() {
    static $catalog = null;
    if ($catalog === null) {
        $catalog = portal_default_permissions();
    }
    return $catalog;
}

function portal_user_has_capability($capability, $user = null) {
    $user = $user ?: portal_current_user();
    if (!$user) {
        return false;
    }
    $role = $user['role'] ?? '';
    $catalog = portal_permission_catalog();
    $caps = $catalog[$role] ?? [];
    return in_array($capability, $caps, true);
}

function portal_require_capability($capability, $user = null) {
    if (!portal_user_has_capability($capability, $user)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

function portal_safe_array(array $data, array $allowedKeys) {
    $clean = [];
    foreach ($allowedKeys as $key) {
        if (array_key_exists($key, $data)) {
            $clean[$key] = $data[$key];
        }
    }
    return $clean;
}

