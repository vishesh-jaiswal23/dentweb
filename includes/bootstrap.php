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

    $db = new PDO('sqlite:' . $dbPath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $db->exec('PRAGMA foreign_keys = ON');

    initialize_schema($db);

    // Always ensure the foundational data exists. The helper is idempotent and
    // will only insert missing rows or create the default administrator when no
    // active admin accounts are present, which protects installations that have
    // already been customized while still repairing partially created databases.
    seed_defaults($db);

    return $db;
}

function initialize_schema(PDO $db): void
{
    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS roles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    description TEXT
)
SQL
    );

    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    full_name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    username TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    role_id INTEGER NOT NULL,
    status TEXT NOT NULL DEFAULT 'active' CHECK(status IN ('active','inactive','pending')),
    permissions_note TEXT,
    last_login_at TEXT,
    password_last_set_at TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY(role_id) REFERENCES roles(id)
)
SQL
    );

    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS invitations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    inviter_id INTEGER NOT NULL,
    invitee_name TEXT NOT NULL,
    invitee_email TEXT NOT NULL,
    role_id INTEGER NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','approved','rejected')),
    token TEXT NOT NULL UNIQUE,
    message TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    approved_at TEXT,
    FOREIGN KEY(inviter_id) REFERENCES users(id),
    FOREIGN KEY(role_id) REFERENCES roles(id)
)
SQL
    );

    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS complaints (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    reference TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL,
    description TEXT,
    priority TEXT NOT NULL DEFAULT 'medium' CHECK(priority IN ('low','medium','high','urgent')),
    status TEXT NOT NULL DEFAULT 'intake' CHECK(status IN ('intake','triage','work','resolution','closed')),
    assigned_to INTEGER,
    sla_due_at TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY(assigned_to) REFERENCES users(id)
)
SQL
    );

    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS audit_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    actor_id INTEGER,
    action TEXT NOT NULL,
    entity_type TEXT,
    entity_id INTEGER,
    description TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY(actor_id) REFERENCES users(id)
)
SQL
    );

    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS system_metrics (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    value TEXT NOT NULL,
    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
)
SQL
    );

    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS settings (
    key TEXT PRIMARY KEY,
    value TEXT NOT NULL,
    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
)
SQL
    );

    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS login_policies (
    id INTEGER PRIMARY KEY CHECK (id = 1),
    retry_limit INTEGER NOT NULL DEFAULT 5,
    lockout_minutes INTEGER NOT NULL DEFAULT 30,
    twofactor_mode TEXT NOT NULL DEFAULT 'admin',
    session_timeout INTEGER NOT NULL DEFAULT 45,
    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
)
SQL
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

    $configuredAdminEmail = getenv('DENTWEB_ADMIN_EMAIL');
    $adminEmail = is_string($configuredAdminEmail) && trim($configuredAdminEmail) !== ''
        ? trim($configuredAdminEmail)
        : 'admin@dakshayani.local';

    $configuredAdminPassword = getenv('DENTWEB_ADMIN_PASSWORD');
    $adminPlaintextPassword = is_string($configuredAdminPassword) ? trim($configuredAdminPassword) : '';
    $adminPasswordGenerated = false;
    if ($adminPlaintextPassword === '') {
        $adminPlaintextPassword = generate_secure_password();
        $adminPasswordGenerated = true;
    }
    $adminPasswordHash = password_hash($adminPlaintextPassword, PASSWORD_DEFAULT);

    $adminFullName = 'Primary Administrator';
    $adminPermissionsNote = 'Full access';

    $createdDefaultAdmin = false;
    $migratedLegacyAdmin = false;

    if ($existingAdminCount === 0) {
        $stmt = $db->prepare("INSERT INTO users(full_name, email, username, password_hash, role_id, status, permissions_note, password_last_set_at) VALUES(:full_name, :email, :username, :password_hash, :role_id, 'active', :permissions_note, datetime('now'))");
        $stmt->execute([
            ':full_name' => $adminFullName,
            ':email' => $adminEmail,
            ':username' => $adminEmail,
            ':password_hash' => $adminPasswordHash,
            ':role_id' => $adminRoleId,
            ':permissions_note' => $adminPermissionsNote,
        ]);
        $createdDefaultAdmin = true;
    } else {
        $legacyEmails = array_map('strtolower', [
            'admin@dakshayani.in',
            'd.entranchi@gmail.com',
        ]);
        $legacyUsernames = array_map('strtolower', [
            'sysadmin',
            'd.entranchi@gmail.com',
        ]);

        $legacyAdminStmt = $db->prepare('SELECT id, email, username FROM users WHERE role_id = :role_id');
        $legacyAdminStmt->execute([':role_id' => $adminRoleId]);
        while (($candidate = $legacyAdminStmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $email = strtolower((string) ($candidate['email'] ?? ''));
            $username = strtolower((string) ($candidate['username'] ?? ''));
            if (in_array($email, $legacyEmails, true) || in_array($username, $legacyUsernames, true)) {
                $updateStmt = $db->prepare("UPDATE users SET full_name = :full_name, email = :email, username = :username, password_hash = :password_hash, status = 'active', permissions_note = :permissions_note, password_last_set_at = datetime('now'), updated_at = datetime('now') WHERE id = :id");
                $updateStmt->execute([
                    ':full_name' => $adminFullName,
                    ':email' => $adminEmail,
                    ':username' => $adminEmail,
                    ':password_hash' => $adminPasswordHash,
                    ':permissions_note' => $adminPermissionsNote,
                    ':id' => (int) $candidate['id'],
                ]);
                $migratedLegacyAdmin = true;
                break;
            }
        }
    }

    if (($createdDefaultAdmin || $migratedLegacyAdmin) && $adminPasswordGenerated) {
        persist_generated_admin_credentials($adminEmail, $adminPlaintextPassword);
    } elseif ($createdDefaultAdmin || $migratedLegacyAdmin) {
        error_log('Dentweb default administrator credentials were provisioned using environment configuration.');
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

    $deleteInsecureGeminiKey = $db->prepare('DELETE FROM settings WHERE key = :key AND value = :value');
    $deleteInsecureGeminiKey->execute([
        ':key' => 'gemini_api_key',
        ':value' => 'AIzaSyAsCEn7cd9vZlb5M5z9kw3XwbGkOjg8md0',
    ]);

    $configuredGeminiKey = getenv('GEMINI_API_KEY');
    if (is_string($configuredGeminiKey)) {
        $configuredGeminiKey = trim($configuredGeminiKey);
        if ($configuredGeminiKey !== '') {
            set_setting('gemini_api_key', $configuredGeminiKey, $db);
        }
    }
    $db->exec("INSERT OR IGNORE INTO login_policies(id, retry_limit, lockout_minutes, twofactor_mode, session_timeout) VALUES (1, 5, 30, 'admin', 45)");
}

function generate_secure_password(int $length = 24): string
{
    $raw = base64_encode(random_bytes(max(16, $length)));
    $sanitized = rtrim(strtr($raw, '+/', '-_'), '=');

    if (strlen($sanitized) >= $length) {
        return substr($sanitized, 0, $length);
    }

    while (strlen($sanitized) < $length) {
        $additional = rtrim(strtr(base64_encode(random_bytes(8)), '+/', '-_'), '=');
        $sanitized .= substr($additional, 0, $length - strlen($sanitized));
    }

    return $sanitized;
}

function persist_generated_admin_credentials(string $email, string $password): void
{
    $storageDir = __DIR__ . '/../storage';
    if (!is_dir($storageDir)) {
        mkdir($storageDir, 0775, true);
    }

    $filePath = $storageDir . '/initial-admin-credentials.txt';
    $contents = "Generated Dentweb administrator credentials\n";
    $contents .= "Email: {$email}\n";
    $contents .= "Password: {$password}\n";
    $contents .= 'Generated at: ' . date('c') . "\n\n";
    $contents .= "This password was generated automatically because the DENTWEB_ADMIN_PASSWORD environment variable was not provided.\n";
    $contents .= "Log in with these credentials immediately, create a dedicated administrator account, and rotate or disable this default user.\n";

    file_put_contents($filePath, $contents, LOCK_EX);
    @chmod($filePath, 0600);
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
    $stmt = $db->prepare("INSERT INTO settings(key, value, updated_at) VALUES(:key, :value, datetime('now'))
        ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = excluded.updated_at");
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
