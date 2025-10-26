<?php
require_once __DIR__ . '/../common/config.php';
require_once __DIR__ . '/../common/auth.php';
require_once __DIR__ . '/../common/security.php';
require_once __DIR__ . '/../common/settings.php';

function portal_admin_nav_items($user) {
    $items = [
        ['id' => 'dashboard', 'label' => 'Executive Overview', 'icon' => 'fa-gauge-high', 'href' => 'index.php', 'cap' => 'dashboard.view'],
        ['id' => 'activity', 'label' => 'Activity Log', 'icon' => 'fa-clock-rotate-left', 'href' => 'index.php#activity-log', 'cap' => 'activity.view'],
        ['id' => 'errors', 'label' => 'Error Monitor', 'icon' => 'fa-bug', 'href' => 'index.php#error-log', 'cap' => 'errors.view'],
        ['id' => 'health', 'label' => 'System Health', 'icon' => 'fa-heart-pulse', 'href' => 'index.php#system-health', 'cap' => 'dashboard.view'],
        ['id' => 'ai-settings', 'label' => 'AI Settings', 'icon' => 'fa-robot', 'href' => 'ai-settings.php', 'cap' => 'settings.view'],
    ];

    foreach (portal_protected_modules() as $module) {
        $items[] = [
            'id' => $module['id'],
            'label' => $module['label'],
            'icon' => $module['icon'],
            'href' => $module['href'],
            'cap' => $module['capability'],
        ];
    }

    return array_values(array_filter($items, function ($item) use ($user) {
        return portal_user_has_capability($item['cap'], $user);
    }));
}

function portal_admin_shell_open($title, $active, $user, $notifications = [], array $options = []) {
    global $portal_admin_shell_options;
    $navItems = portal_admin_nav_items($user);
    $maskedEmail = htmlspecialchars($user['email'] ?? '');
    $userName = htmlspecialchars($user['name'] ?? '');
    $active = $active ?: 'dashboard';
    $notifications = is_array($notifications) ? $notifications : [];
    $canSeeNotifications = portal_user_has_capability('notifications.view', $user);
    $defaults = [
        'styles' => [],
        'scripts' => [],
    ];
    $portal_admin_shell_options = array_merge($defaults, $options);
    $portal_admin_shell_options['styles'] = array_values(array_filter(array_map('portal_admin_resolve_asset', (array) $portal_admin_shell_options['styles'])));
    $portal_admin_shell_options['scripts'] = array_values(array_filter(array_map('portal_admin_resolve_asset', (array) $portal_admin_shell_options['scripts'])));
    $extraStyles = $portal_admin_shell_options['styles'];
    include __DIR__ . '/partials/shell-open.php';
}

function portal_admin_shell_close() {
    global $portal_admin_shell_options;
    $extraScripts = [];
    if (isset($portal_admin_shell_options['scripts']) && is_array($portal_admin_shell_options['scripts'])) {
        $extraScripts = $portal_admin_shell_options['scripts'];
    }
    include __DIR__ . '/partials/shell-close.php';
    $portal_admin_shell_options = null;
}

function portal_admin_render_notifications($notifications) {
    $notifications = array_slice(array_filter($notifications, function ($item) {
        return isset($item['message']);
    }), 0, 10);
    include __DIR__ . '/partials/notifications.php';
}

function portal_admin_resolve_asset($asset) {
    if (!is_string($asset) || $asset === '') {
        return null;
    }
    if (preg_match('#^(https?:)?//#', $asset)) {
        return $asset;
    }
    if ($asset[0] === '/') {
        return portal_url(ltrim($asset, '/'));
    }
    return portal_url($asset);
}

