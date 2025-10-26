<?php
// Minimal config for portal auth.
// Reads from environment when available, otherwise falls back to defaults.

// Default admin credentials (can be overridden by environment)
$DEFAULT_ADMIN_EMAIL = 'd.entranchi@gmail.com';
$DEFAULT_ADMIN_PASSWORD = 'Dent@2025';
$DEFAULT_ADMIN_PASSWORD_HASH = '$2y$12$jEHjkFEOfFtAdfwi8zlBLufnzvc1Rb4XKj.U3FOEKICrtkZo6ytTi';
$DEFAULT_ADMIN_NAME = 'Head Administrator';

function portal_env($key, $fallback = null) {
    $val = getenv($key);
    return ($val !== false && $val !== null && $val !== '') ? $val : $fallback;
}

function portal_env_flag($key, $default = false) {
    $value = portal_env($key, null);
    if ($value === null) {
        return (bool) $default;
    }
    $normalized = strtolower(trim((string) $value));
    if ($normalized === '' && $default !== null) {
        return (bool) $default;
    }
    return in_array($normalized, ['1', 'true', 'on', 'yes'], true);
}

define('PORTAL_ADMIN_EMAIL', portal_env('MAIN_ADMIN_EMAIL', $DEFAULT_ADMIN_EMAIL));
define('PORTAL_ADMIN_PASSWORD', portal_env('MAIN_ADMIN_PASSWORD', $DEFAULT_ADMIN_PASSWORD));
define('PORTAL_ADMIN_PASSWORD_HASH', portal_env('MAIN_ADMIN_PASSWORD_HASH', $DEFAULT_ADMIN_PASSWORD_HASH));
define('PORTAL_ADMIN_NAME', portal_env('MAIN_ADMIN_NAME', $DEFAULT_ADMIN_NAME));

// Shared storage path for admin modules (e.g., settings, logs)
$storageRoot = portal_env('PORTAL_STORAGE_PATH', realpath(__DIR__ . '/../../storage'));
if (!$storageRoot) {
    $storageRoot = __DIR__ . '/../../storage';
}
define('PORTAL_STORAGE_PATH', rtrim($storageRoot, DIRECTORY_SEPARATOR));

// Default AI provider configuration (Gemini)
define('PORTAL_DEFAULT_AI_SETTINGS', [
    'api_key' => 'REPLACE_WITH_API_KEY',
    'models' => [
        'text' => 'gemini-2.5-flash',
        'image' => 'gemini-2.5-flash-image',
        'tts' => 'gemini-2.5-flash-preview-tts',
    ],
]);

// Determine base path (subdirectory) automatically; allow override via env PORTAL_BASE_PATH
$autoBase = '';
if (!empty($_SERVER['DOCUMENT_ROOT'])) {
    $projectRoot = realpath(__DIR__ . '/../../');
    $docRoot = realpath($_SERVER['DOCUMENT_ROOT']);
    if ($projectRoot && $docRoot && str_starts_with(str_replace('\\','/',$projectRoot), str_replace('\\','/',$docRoot))) {
        $autoBase = str_replace(str_replace('\\','/',$docRoot), '', str_replace('\\','/',$projectRoot));
    }
}
$envBase = portal_env('PORTAL_BASE_PATH', $autoBase);
if (!is_string($envBase)) { $envBase = ''; }
$envBase = trim($envBase);
if ($envBase === '/') { $envBase = ''; }
if ($envBase !== '' && $envBase[0] !== '/') { $envBase = '/' . $envBase; }
define('PORTAL_BASE_PATH', rtrim($envBase, '/'));

function portal_url($path) {
    $p = is_string($path) ? $path : '';
    if ($p === '') { $p = '/'; }
    if ($p[0] !== '/') { $p = '/' . $p; }
    return (PORTAL_BASE_PATH !== '' ? PORTAL_BASE_PATH : '') . $p;
}

define('PORTAL_ALLOW_EMPLOYEE_SERVICE_AND_CRM', portal_env_flag('ALLOW_EMPLOYEE_SERVICE_AND_CRM', false));

function portal_storage_path($relative = '') {
    $base = PORTAL_STORAGE_PATH;
    if ($relative === '' || $relative === null) {
        return $base;
    }
    $relative = str_replace(['\\', '..'], ['/', ''], (string) $relative);
    if ($relative === '') {
        return $base;
    }
    return rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($relative, DIRECTORY_SEPARATOR);
}

function portal_redirect($path) {
    header('Location: ' . portal_url($path));
    exit;
}

// Utility: map roles to dashboard paths
function portal_dashboard_for_role($role) {
    switch ($role) {
        case 'admin': return portal_url('users/admin/index.php');
        case 'employee': return portal_url('users/employee/index.php');
        case 'installer': return portal_url('users/installer/index.php');
        case 'referrer': return portal_url('users/referrer/index.php');
        case 'customer': return portal_url('users/customer/index.php');
        default: return portal_url('login.php');
    }
}
