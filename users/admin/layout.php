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

    return array_values(array_filter($items, function ($item) use ($user) {
        return portal_user_has_capability($item['cap'], $user);
    }));
}

function portal_admin_shell_open($title, $active, $user, $notifications = []) {
    $navItems = portal_admin_nav_items($user);
    $maskedEmail = htmlspecialchars($user['email'] ?? '');
    $userName = htmlspecialchars($user['name'] ?? '');
    $active = $active ?: 'dashboard';
    $notifications = is_array($notifications) ? $notifications : [];
    $canSeeNotifications = portal_user_has_capability('notifications.view', $user);
    include __DIR__ . '/partials/shell-open.php';
}

function portal_admin_shell_close() {
    include __DIR__ . '/partials/shell-close.php';
}

function portal_admin_render_notifications($notifications) {
    $notifications = array_slice(array_filter($notifications, function ($item) {
        return isset($item['message']);
    }), 0, 10);
    include __DIR__ . '/partials/notifications.php';
}

