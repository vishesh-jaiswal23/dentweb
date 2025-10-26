<?php
declare(strict_types=1);

function get_db(): PDO
{
    static $db = null;
    if ($db instanceof PDO) {
        return $db;
    }

    $storageDir = __DIR__ . '/../storage';
    if (!is_dir($storageDir)) {
        mkdir($storageDir, 0775, true);
    }

    $dbPath = $storageDir . '/app.sqlite';
    $needSeed = !file_exists($dbPath);

    $db = new PDO('sqlite:' . $dbPath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $db->exec('PRAGMA foreign_keys = ON');

    initialize_schema($db);

    if ($needSeed) {
        seed_defaults($db);
    }

    return $db;
}

function initialize_schema(PDO $db): void
{
    $db->exec(
        'CREATE TABLE IF NOT EXISTS roles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            description TEXT
        )'
    );

    $db->exec(
        'CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            full_name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            role_id INTEGER NOT NULL,
            status TEXT NOT NULL DEFAULT "active" CHECK(status IN ("active","inactive","pending")),
            permissions_note TEXT,
            last_login_at TEXT,
            password_last_set_at TEXT,
            created_at TEXT NOT NULL DEFAULT (datetime("now")),
            updated_at TEXT NOT NULL DEFAULT (datetime("now")),
            FOREIGN KEY(role_id) REFERENCES roles(id)
        )'
    );

    $db->exec(
        'CREATE TABLE IF NOT EXISTS invitations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            inviter_id INTEGER NOT NULL,
            invitee_name TEXT NOT NULL,
            invitee_email TEXT NOT NULL,
            role_id INTEGER NOT NULL,
            status TEXT NOT NULL DEFAULT "pending" CHECK(status IN ("pending","approved","rejected")),
            token TEXT NOT NULL UNIQUE,
            message TEXT,
            created_at TEXT NOT NULL DEFAULT (datetime("now")),
            approved_at TEXT,
            FOREIGN KEY(inviter_id) REFERENCES users(id),
            FOREIGN KEY(role_id) REFERENCES roles(id)
        )'
    );

    $db->exec(
        'CREATE TABLE IF NOT EXISTS complaints (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            reference TEXT NOT NULL UNIQUE,
            title TEXT NOT NULL,
            description TEXT,
            priority TEXT NOT NULL DEFAULT "medium" CHECK(priority IN ("low","medium","high","urgent")),
            status TEXT NOT NULL DEFAULT "intake" CHECK(status IN ("intake","triage","work","resolution","closed")),
            assigned_to INTEGER,
            sla_due_at TEXT,
            created_at TEXT NOT NULL DEFAULT (datetime("now")),
            updated_at TEXT NOT NULL DEFAULT (datetime("now")),
            FOREIGN KEY(assigned_to) REFERENCES users(id)
        )'
    );

    $db->exec(
        'CREATE TABLE IF NOT EXISTS audit_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            actor_id INTEGER,
            action TEXT NOT NULL,
            entity_type TEXT,
            entity_id INTEGER,
            description TEXT,
            created_at TEXT NOT NULL DEFAULT (datetime("now")),
            FOREIGN KEY(actor_id) REFERENCES users(id)
        )'
    );

    $db->exec(
        'CREATE TABLE IF NOT EXISTS system_metrics (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            value TEXT NOT NULL,
            updated_at TEXT NOT NULL DEFAULT (datetime("now"))
        )'
    );

    $db->exec(
        'CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL,
            updated_at TEXT NOT NULL DEFAULT (datetime("now"))
        )'
    );

    $db->exec(
        'CREATE TABLE IF NOT EXISTS login_policies (
            id INTEGER PRIMARY KEY CHECK (id = 1),
            retry_limit INTEGER NOT NULL DEFAULT 5,
            lockout_minutes INTEGER NOT NULL DEFAULT 30,
            twofactor_mode TEXT NOT NULL DEFAULT "admin",
            session_timeout INTEGER NOT NULL DEFAULT 45,
            updated_at TEXT NOT NULL DEFAULT (datetime("now"))
        )'
    );
    apply_schema_patches($db);
}

function seed_defaults(PDO $db): void
{
    $roles = [
        'admin' => 'System administrators with full permissions.',
        'employee' => 'Internal staff managing operations and service.',
        'installer' => 'Installation partners and crews.',
        'referrer' => 'Channel partners and referrers.',
        'customer' => 'Customers using subsidy and service portals.',
    ];

    $insertRole = $db->prepare('INSERT OR IGNORE INTO roles(name, description) VALUES(:name, :description)');
    foreach ($roles as $name => $description) {
        $insertRole->execute([
            ':name' => $name,
            ':description' => $description,
        ]);
    }

    // Seed default admin if none exists
    $adminRoleId = (int) $db->query('SELECT id FROM roles WHERE name = "admin"')->fetchColumn();
    $existingAdminCount = (int) $db->query('SELECT COUNT(*) FROM users WHERE role_id = ' . $adminRoleId)->fetchColumn();
    if ($existingAdminCount === 0) {
        $stmt = $db->prepare('INSERT INTO users(full_name, email, username, password_hash, role_id, status, permissions_note, password_last_set_at) VALUES(:full_name, :email, :username, :password_hash, :role_id, "active", :permissions_note, datetime("now"))');
        $stmt->execute([
            ':full_name' => 'Primary Administrator',
            ':email' => 'admin@dakshayani.in',
            ':username' => 'sysadmin',
            ':password_hash' => password_hash('ChangeMe@123', PASSWORD_DEFAULT),
            ':role_id' => $adminRoleId,
            ':permissions_note' => 'Full access',
        ]);
    }

    $defaultMetrics = [
        'last_backup' => 'Not recorded',
        'errors_24h' => '0',
        'disk_usage' => 'Normal',
        'uptime' => 'Unknown',
        'subsidy_pipeline' => '0',
    ];

    $insertMetric = $db->prepare('INSERT OR IGNORE INTO system_metrics(name, value) VALUES(:name, :value)');
    foreach ($defaultMetrics as $name => $value) {
        $insertMetric->execute([
            ':name' => $name,
            ':value' => $value,
        ]);
    }

    $defaultGeminiSettings = [
        'gemini_api_key' => 'AIzaSyAsCEn7cd9vZlb5M5z9kw3XwbGkOjg8md0',
        'gemini_text_model' => 'gemini-2.5-flash',
        'gemini_image_model' => 'gemini-2.5-flash-image',
        'gemini_tts_model' => 'gemini-2.5-flash-preview-tts',
    ];

    $insertSetting = $db->prepare('INSERT OR IGNORE INTO settings(key, value) VALUES(:key, :value)');
    foreach ($defaultGeminiSettings as $key => $value) {
        $insertSetting->execute([
            ':key' => $key,
            ':value' => $value,
        ]);
    }
    $db->exec('INSERT OR IGNORE INTO login_policies(id, retry_limit, lockout_minutes, twofactor_mode, session_timeout) VALUES (1, 5, 30, "admin", 45)');
}

function get_setting(string $key, ?PDO $db = null): ?string
{
    $db = $db ?? get_db();
    $stmt = $db->prepare('SELECT value FROM settings WHERE key = :key LIMIT 1');
    $stmt->execute([':key' => $key]);
    $value = $stmt->fetchColumn();

    return $value !== false ? (string) $value : null;
}

function set_setting(string $key, string $value, ?PDO $db = null): void
{
    $db = $db ?? get_db();
    $stmt = $db->prepare('INSERT INTO settings(key, value, updated_at) VALUES(:key, :value, datetime("now"))
        ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = excluded.updated_at');
    $stmt->execute([
        ':key' => $key,
        ':value' => $value,
    ]);
}

function apply_schema_patches(PDO $db): void
{
    upgrade_users_table($db);
    ensure_login_policy_row($db);
}

function upgrade_users_table(PDO $db): void
{
    $columns = $db->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'name');

    if (!in_array('username', $columnNames, true)) {
        $db->exec('ALTER TABLE users ADD COLUMN username TEXT');
        $db->exec("UPDATE users SET username = CASE WHEN instr(email, '@') > 0 THEN lower(substr(email, 1, instr(email, '@') - 1)) ELSE email END WHERE username IS NULL OR username = ''");
    }

    if (!in_array('permissions_note', $columnNames, true)) {
        $db->exec('ALTER TABLE users ADD COLUMN permissions_note TEXT');
    }

    if (!in_array('password_last_set_at', $columnNames, true)) {
        $db->exec('ALTER TABLE users ADD COLUMN password_last_set_at TEXT');
    }

    $db->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_users_username ON users(username)');
    $db->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_users_email ON users(email)');
}

function ensure_login_policy_row(PDO $db): void
{
    $count = (int) $db->query('SELECT COUNT(*) FROM login_policies')->fetchColumn();
    if ($count === 0) {
        $db->exec("INSERT INTO login_policies(id, retry_limit, lockout_minutes, twofactor_mode, session_timeout) VALUES (1, 5, 30, 'admin', 45)");
    }
}
