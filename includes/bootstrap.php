<?php
declare(strict_types=1);

require_once __DIR__ . '/blog.php';

function get_db(): PDO
{
    static $db = null;
    if ($db instanceof PDO) {
        return $db;
    }

    if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        throw new RuntimeException('The SQLite PDO driver is not installed.');
    }

    $storageDir = __DIR__ . '/../storage';
    if (!is_dir($storageDir)) {
        mkdir($storageDir, 0775, true);
    }

    $dbPath = $storageDir . '/app.sqlite';

    try {
        $db = new PDO('sqlite:' . $dbPath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $exception) {
        throw new RuntimeException('Failed to initialise the application database.', 0, $exception);
    }
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
CREATE TABLE IF NOT EXISTS portal_tasks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    description TEXT,
    status TEXT NOT NULL DEFAULT 'todo' CHECK(status IN ('todo','in_progress','done')),
    priority TEXT NOT NULL DEFAULT 'medium' CHECK(priority IN ('low','medium','high')),
    due_date TEXT,
    linked_reference TEXT,
    notes TEXT,
    assignee_id INTEGER,
    created_by INTEGER,
    completed_at TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now', '+330 minutes')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now', '+330 minutes')),
    FOREIGN KEY(assignee_id) REFERENCES users(id),
    FOREIGN KEY(created_by) REFERENCES users(id)
)
SQL
    );

    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS portal_documents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    linked_to TEXT,
    reference TEXT,
    tags TEXT,
    url TEXT,
    version INTEGER NOT NULL DEFAULT 1,
    visibility TEXT NOT NULL DEFAULT 'employee' CHECK(visibility IN ('employee','admin','both')),
    uploaded_by INTEGER,
    created_at TEXT NOT NULL DEFAULT (datetime('now', '+330 minutes')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now', '+330 minutes')),
    FOREIGN KEY(uploaded_by) REFERENCES users(id)
)
SQL
    );

    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS portal_notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    audience TEXT NOT NULL DEFAULT 'employee' CHECK(audience IN ('employee','admin','all')),
    tone TEXT NOT NULL DEFAULT 'info' CHECK(tone IN ('info','success','warning','danger')),
    icon TEXT,
    title TEXT NOT NULL,
    message TEXT NOT NULL,
    link TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now', '+330 minutes')),
    expires_at TEXT
)
SQL
    );

    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS portal_notification_status (
    notification_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    status TEXT NOT NULL DEFAULT 'unread' CHECK(status IN ('unread','read','dismissed')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now', '+330 minutes')),
    PRIMARY KEY(notification_id, user_id),
    FOREIGN KEY(notification_id) REFERENCES portal_notifications(id) ON DELETE CASCADE,
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
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

    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS blog_posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    slug TEXT NOT NULL UNIQUE,
    excerpt TEXT,
    body_html TEXT NOT NULL,
    body_text TEXT NOT NULL,
    cover_image TEXT,
    cover_image_alt TEXT,
    author_name TEXT,
    status TEXT NOT NULL DEFAULT 'draft' CHECK(status IN ('draft','pending','published','archived')),
    published_at TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
)
SQL
    );

    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS blog_tags (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    slug TEXT NOT NULL UNIQUE,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
)
SQL
    );

    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS blog_post_tags (
    post_id INTEGER NOT NULL,
    tag_id INTEGER NOT NULL,
    PRIMARY KEY (post_id, tag_id),
    FOREIGN KEY(post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
    FOREIGN KEY(tag_id) REFERENCES blog_tags(id) ON DELETE CASCADE
)
SQL
    );

    $db->exec('CREATE INDEX IF NOT EXISTS idx_blog_posts_status_published_at ON blog_posts(status, published_at DESC)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_blog_post_tags_tag ON blog_post_tags(tag_id)');

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

    ensure_default_user($db, [
        'role' => 'admin',
        'full_name' => 'Primary Administrator',
        'email' => 'admin@dakshayani.in',
        'username' => 'admin',
        'password' => 'Dent@2025',
        'permissions_note' => 'Full access',
        'legacy_emails' => ['d.entranchi@gmail.com'],
        'legacy_usernames' => ['d.entranchi@gmail.com', 'sysadmin'],
    ]);

    ensure_default_user($db, [
        'role' => 'employee',
        'full_name' => 'Operations Coordinator',
        'email' => 'employee@dakshayani.in',
        'username' => 'employee',
        'password' => 'Employee@2025',
        'permissions_note' => 'Employee workspace access',
        'legacy_usernames' => ['employee'],
    ]);

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
        'gemini_api_key' => 'SET_IN_ADMIN_PORTAL',
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
    $db->exec("INSERT OR IGNORE INTO login_policies(id, retry_limit, lockout_minutes, twofactor_mode, session_timeout) VALUES (1, 5, 30, 'admin', 45)");

    blog_seed_default($db);
    blog_backfill_cover_images($db);

    seed_portal_defaults($db);
}

function ensure_default_user(PDO $db, array $account): void
{
    $roleStmt = $db->prepare('SELECT id FROM roles WHERE name = :name LIMIT 1');
    $roleStmt->execute([':name' => $account['role']]);
    $roleId = $roleStmt->fetchColumn();
    if ($roleId === false) {
        return;
    }
    $roleId = (int) $roleId;

    $emails = array_map('strtolower', array_filter([
        $account['email'] ?? null,
        ...($account['legacy_emails'] ?? []),
    ], 'is_string'));
    $usernames = array_map('strtolower', array_filter([
        $account['username'] ?? null,
        ...($account['legacy_usernames'] ?? []),
    ], 'is_string'));

    $conditions = [];
    $params = [];

    if ($emails) {
        $emails = array_values(array_unique($emails));
        $placeholders = [];
        foreach ($emails as $index => $email) {
            $placeholder = ':email_lookup_' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $email;
        }
        $conditions[] = 'LOWER(email) IN (' . implode(', ', $placeholders) . ')';
    }

    if ($usernames) {
        $usernames = array_values(array_unique($usernames));
        $placeholders = [];
        foreach ($usernames as $index => $username) {
            $placeholder = ':username_lookup_' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $username;
        }
        $conditions[] = 'LOWER(username) IN (' . implode(', ', $placeholders) . ')';
    }

    $existing = null;
    if ($conditions) {
        $sql = 'SELECT id, email, username, password_hash, status, role_id, permissions_note, full_name FROM users WHERE ' . implode(' OR ', $conditions) . ' LIMIT 1';
        $lookup = $db->prepare($sql);
        $lookup->execute($params);
        $existing = $lookup->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    $defaultPassword = (string) ($account['password'] ?? '');
    $nowPasswordHash = null;

    if ($existing === null) {
        $insert = $db->prepare("INSERT INTO users(full_name, email, username, password_hash, role_id, status, permissions_note, password_last_set_at, created_at, updated_at) VALUES(:full_name, :email, :username, :password_hash, :role_id, 'active', :permissions_note, datetime('now'), datetime('now'), datetime('now'))");
        if ($defaultPassword !== '') {
            $nowPasswordHash = password_hash($defaultPassword, PASSWORD_DEFAULT);
        }

        $insert->execute([
            ':full_name' => $account['full_name'],
            ':email' => $account['email'],
            ':username' => $account['username'],
            ':password_hash' => $nowPasswordHash,
            ':role_id' => $roleId,
            ':permissions_note' => $account['permissions_note'] ?? '',
        ]);
        return;
    }

    $updates = [];
    $updateParams = [':id' => (int) $existing['id']];

    if ((int) $existing['role_id'] !== $roleId) {
        $updates[] = 'role_id = :role_id';
        $updateParams[':role_id'] = $roleId;
    }

    $legacyEmails = array_map('strtolower', $account['legacy_emails'] ?? []);
    $existingEmail = strtolower((string) ($existing['email'] ?? ''));
    $targetEmail = strtolower((string) ($account['email'] ?? ''));
    if ($targetEmail !== '' && $existingEmail !== $targetEmail) {
        if (!$legacyEmails || in_array($existingEmail, $legacyEmails, true)) {
            $updates[] = 'email = :email';
            $updateParams[':email'] = $account['email'];
        }
    }

    $legacyUsernames = array_map('strtolower', $account['legacy_usernames'] ?? []);
    $existingUsername = strtolower((string) ($existing['username'] ?? ''));
    $targetUsername = strtolower((string) ($account['username'] ?? ''));
    if ($targetUsername !== '' && $existingUsername !== $targetUsername) {
        if (!$legacyUsernames || in_array($existingUsername, $legacyUsernames, true)) {
            $updates[] = 'username = :username';
            $updateParams[':username'] = $account['username'];
        }
    }

    $existingName = trim((string) ($existing['full_name'] ?? ''));
    $targetName = (string) ($account['full_name'] ?? '');
    if ($targetName !== '' && ($existingName === '' || $existingName === $targetName)) {
        if ($existingName !== $targetName) {
            $updates[] = 'full_name = :full_name';
            $updateParams[':full_name'] = $targetName;
        }
    }

    $existingNote = trim((string) ($existing['permissions_note'] ?? ''));
    $targetNote = (string) ($account['permissions_note'] ?? '');
    if ($targetNote !== '' && ($existingNote === '' || $existingNote === $targetNote)) {
        if ($existingNote !== $targetNote) {
            $updates[] = 'permissions_note = :permissions_note';
            $updateParams[':permissions_note'] = $targetNote;
        }
    }

    if (($existing['status'] ?? '') !== 'active') {
        $updates[] = "status = 'active'";
    }

    $existingHash = (string) ($existing['password_hash'] ?? '');
    if ($defaultPassword !== '' && ($existingHash === '' || !password_verify($defaultPassword, $existingHash))) {
        if ($nowPasswordHash === null) {
            $nowPasswordHash = password_hash($defaultPassword, PASSWORD_DEFAULT);
        }
        $updates[] = 'password_hash = :password_hash';
        $updateParams[':password_hash'] = $nowPasswordHash;
        $updates[] = "password_last_set_at = datetime('now')";
    }

    if ($updates) {
        $updates[] = "updated_at = datetime('now')";
        $sql = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = :id';
        $stmt = $db->prepare($sql);
        $stmt->execute($updateParams);
    }
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
    upgrade_blog_posts_table($db);
    ensure_login_policy_row($db);
    ensure_portal_tables($db);
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

    ensure_blog_indexes($db);
}

function upgrade_blog_posts_table(PDO $db): void
{
    $schema = (string) $db->query("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'blog_posts'")->fetchColumn();
    if ($schema === '') {
        return;
    }

    if (str_contains($schema, "'pending'")) {
        ensure_blog_indexes($db);
        return;
    }

    $db->beginTransaction();
    try {
        $db->exec('ALTER TABLE blog_posts RENAME TO blog_posts_backup');
        $db->exec(<<<'SQL'
CREATE TABLE blog_posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    slug TEXT NOT NULL UNIQUE,
    excerpt TEXT,
    body_html TEXT NOT NULL,
    body_text TEXT NOT NULL,
    cover_image TEXT,
    cover_image_alt TEXT,
    author_name TEXT,
    status TEXT NOT NULL DEFAULT 'draft' CHECK(status IN ('draft','pending','published','archived')),
    published_at TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
)
SQL
        );
        $db->exec(<<<'SQL'
INSERT INTO blog_posts (id, title, slug, excerpt, body_html, body_text, cover_image, cover_image_alt, author_name, status, published_at, created_at, updated_at)
SELECT id, title, slug, excerpt, body_html, body_text, cover_image, cover_image_alt, author_name, status, published_at, created_at, updated_at
FROM blog_posts_backup
SQL
        );
        $db->exec('DROP TABLE blog_posts_backup');
        ensure_blog_indexes($db);
        $db->commit();
    } catch (Throwable $exception) {
        $db->rollBack();
        throw $exception;
    }
}

function ensure_login_policy_row(PDO $db): void
{
    $count = (int) $db->query('SELECT COUNT(*) FROM login_policies')->fetchColumn();
    if ($count === 0) {
        $db->exec("INSERT INTO login_policies(id, retry_limit, lockout_minutes, twofactor_mode, session_timeout) VALUES (1, 5, 30, 'admin', 45)");
    }
}

function ensure_blog_indexes(PDO $db): void
{
    $db->exec('CREATE INDEX IF NOT EXISTS idx_blog_posts_status_published_at ON blog_posts(status, published_at DESC)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_blog_post_tags_tag ON blog_post_tags(tag_id)');
}

function ensure_portal_tables(PDO $db): void
{
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('portal_tasks', $tables, true)) {
        $db->exec(<<<'SQL'
CREATE TABLE portal_tasks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    description TEXT,
    status TEXT NOT NULL DEFAULT 'todo' CHECK(status IN ('todo','in_progress','done')),
    priority TEXT NOT NULL DEFAULT 'medium' CHECK(priority IN ('low','medium','high')),
    due_date TEXT,
    linked_reference TEXT,
    notes TEXT,
    assignee_id INTEGER,
    created_by INTEGER,
    completed_at TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now', '+330 minutes')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now', '+330 minutes')),
    FOREIGN KEY(assignee_id) REFERENCES users(id),
    FOREIGN KEY(created_by) REFERENCES users(id)
)
SQL
        );
    }

    if (!in_array('portal_documents', $tables, true)) {
        $db->exec(<<<'SQL'
CREATE TABLE portal_documents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    linked_to TEXT,
    reference TEXT,
    tags TEXT,
    url TEXT,
    version INTEGER NOT NULL DEFAULT 1,
    visibility TEXT NOT NULL DEFAULT 'employee' CHECK(visibility IN ('employee','admin','both')),
    uploaded_by INTEGER,
    created_at TEXT NOT NULL DEFAULT (datetime('now', '+330 minutes')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now', '+330 minutes')),
    FOREIGN KEY(uploaded_by) REFERENCES users(id)
)
SQL
        );
    }

    if (!in_array('portal_notifications', $tables, true)) {
        $db->exec(<<<'SQL'
CREATE TABLE portal_notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    audience TEXT NOT NULL DEFAULT 'employee' CHECK(audience IN ('employee','admin','all')),
    tone TEXT NOT NULL DEFAULT 'info' CHECK(tone IN ('info','success','warning','danger')),
    icon TEXT,
    title TEXT NOT NULL,
    message TEXT NOT NULL,
    link TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now', '+330 minutes')),
    expires_at TEXT
)
SQL
        );
    }

    if (!in_array('portal_notification_status', $tables, true)) {
        $db->exec(<<<'SQL'
CREATE TABLE portal_notification_status (
    notification_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    status TEXT NOT NULL DEFAULT 'unread' CHECK(status IN ('unread','read','dismissed')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now', '+330 minutes')),
    PRIMARY KEY(notification_id, user_id),
    FOREIGN KEY(notification_id) REFERENCES portal_notifications(id) ON DELETE CASCADE,
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
)
SQL
        );
    }
}

function seed_portal_defaults(PDO $db): void
{
    $taskCount = (int) $db->query('SELECT COUNT(*) FROM portal_tasks')->fetchColumn();
    if ($taskCount === 0) {
        $now = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
        $tasks = [
            [
                'title' => 'Verify subsidy documents',
                'description' => 'Cross-check uploaded paperwork before subsidy submission.',
                'priority' => 'high',
                'status' => 'todo',
                'due_date' => $now->modify('+2 days')->format('Y-m-d'),
                'linked_reference' => 'SR-101',
            ],
            [
                'title' => 'Schedule AMC visit for Asha Devi',
                'description' => 'Confirm availability and share visit checklist.',
                'priority' => 'medium',
                'status' => 'in_progress',
                'due_date' => $now->modify('+3 days')->format('Y-m-d'),
                'linked_reference' => 'AMC-204',
            ],
        ];
        $insert = $db->prepare('INSERT INTO portal_tasks(title, description, priority, status, due_date, linked_reference, created_at, updated_at) VALUES(:title, :description, :priority, :status, :due_date, :linked_reference, datetime(\'now\', \' +330 minutes\'), datetime(\'now\', \' +330 minutes\'))');
        foreach ($tasks as $task) {
            $insert->execute([
                ':title' => $task['title'],
                ':description' => $task['description'],
                ':priority' => $task['priority'],
                ':status' => $task['status'],
                ':due_date' => $task['due_date'],
                ':linked_reference' => $task['linked_reference'],
            ]);
        }
    }

    $documentCount = (int) $db->query('SELECT COUNT(*) FROM portal_documents')->fetchColumn();
    if ($documentCount === 0) {
        $insertDoc = $db->prepare('INSERT INTO portal_documents(name, linked_to, reference, tags, url, version, visibility, uploaded_by, created_at, updated_at) VALUES(:name, :linked_to, :reference, :tags, :url, :version, :visibility, NULL, datetime(\'now\', \' +330 minutes\'), datetime(\'now\', \' +330 minutes\'))');
        $insertDoc->execute([
            ':name' => 'Service Playbook',
            ':linked_to' => 'operations',
            ':reference' => 'DOC-OPS-01',
            ':tags' => json_encode(['SOP', 'Service']),
            ':url' => '#',
            ':version' => 1,
            ':visibility' => 'both',
        ]);
        $insertDoc->execute([
            ':name' => 'Subsidy Checklist',
            ':linked_to' => 'subsidy',
            ':reference' => 'DOC-SUB-11',
            ':tags' => json_encode(['Checklist']),
            ':url' => '#',
            ':version' => 1,
            ':visibility' => 'employee',
        ]);
    }

    $notificationCount = (int) $db->query('SELECT COUNT(*) FROM portal_notifications')->fetchColumn();
    if ($notificationCount === 0) {
        $insertNotification = $db->prepare('INSERT INTO portal_notifications(audience, tone, icon, title, message, link, created_at) VALUES(:audience, :tone, :icon, :title, :message, :link, datetime(\'now\', \' +330 minutes\'))');
        $insertNotification->execute([
            ':audience' => 'employee',
            ':tone' => 'info',
            ':icon' => 'fa-solid fa-ticket',
            ':title' => 'Ticket SR-219 assigned',
            ':message' => 'Admin added SR-219 to your queue with a 24-hour SLA.',
            ':link' => '#complaints',
        ]);
        $insertNotification->execute([
            ':audience' => 'employee',
            ':tone' => 'warning',
            ':icon' => 'fa-solid fa-clock',
            ':title' => 'SLA due soon',
            ':message' => 'SR-205 needs an update before 18:00 IST today.',
            ':link' => '#complaints',
        ]);
    }
}

function now_ist(): string
{
    $now = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
    return $now->format('Y-m-d H:i:s');
}

function portal_role_label(string $roleName): string
{
    return strtolower($roleName) === 'employee' ? 'Employee' : ucfirst($roleName);
}

function portal_find_user(PDO $db, int $id): ?array
{
    $stmt = $db->prepare('SELECT users.*, roles.name AS role_name FROM users INNER JOIN roles ON users.role_id = roles.id WHERE users.id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function portal_list_team(PDO $db): array
{
    $stmt = $db->query("SELECT users.id, users.full_name, users.email, users.permissions_note, roles.name AS role_name FROM users INNER JOIN roles ON users.role_id = roles.id WHERE roles.name IN ('employee','admin') ORDER BY roles.name, users.full_name");
    $team = [];
    foreach ($stmt->fetchAll() as $row) {
        $team[] = [
            'id' => (int) $row['id'],
            'name' => $row['full_name'],
            'email' => $row['email'],
            'role' => portal_role_label($row['role_name']),
            'note' => $row['permissions_note'] ?? '',
        ];
    }

    return $team;
}

function portal_list_tasks(PDO $db, ?int $assigneeId = null): array
{
    $sql = "SELECT portal_tasks.*, users.full_name AS assignee_name, users.email AS assignee_email, roles.name AS assignee_role FROM portal_tasks LEFT JOIN users ON portal_tasks.assignee_id = users.id LEFT JOIN roles ON users.role_id = roles.id";
    $params = [];
    if ($assigneeId !== null) {
        $sql .= ' WHERE portal_tasks.assignee_id = :assignee_id';
        $params[':assignee_id'] = $assigneeId;
    }
    $sql .= " ORDER BY CASE portal_tasks.status WHEN 'todo' THEN 0 WHEN 'in_progress' THEN 1 ELSE 2 END, COALESCE(portal_tasks.due_date, ''), portal_tasks.id DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    $tasks = [];
    foreach ($stmt->fetchAll() as $row) {
        $tasks[] = portal_normalize_task_row($row);
    }

    return $tasks;
}

function portal_normalize_task_row(array $row): array
{
    $assigneeRole = $row['assignee_role'] ?? '';
    return [
        'id' => (int) $row['id'],
        'title' => $row['title'],
        'description' => $row['description'] ?? '',
        'status' => $row['status'],
        'priority' => $row['priority'],
        'dueDate' => $row['due_date'] ?? '',
        'linkedTo' => $row['linked_reference'] ?? '',
        'notes' => $row['notes'] ?? '',
        'assigneeId' => $row['assignee_id'] !== null ? (string) $row['assignee_id'] : '',
        'assigneeName' => $row['assignee_name'] ?? '',
        'assigneeRole' => $assigneeRole ? portal_role_label($assigneeRole) : '',
        'createdAt' => $row['created_at'] ?? '',
        'updatedAt' => $row['updated_at'] ?? '',
        'completedAt' => $row['completed_at'] ?? '',
    ];
}

function portal_save_task(PDO $db, array $input, int $actorId): array
{
    $title = trim((string) ($input['title'] ?? ''));
    if ($title === '') {
        throw new RuntimeException('Task title is required.');
    }

    $status = $input['status'] ?? 'todo';
    if (!in_array($status, ['todo', 'in_progress', 'done'], true)) {
        $status = 'todo';
    }

    $priority = $input['priority'] ?? 'medium';
    if (!in_array($priority, ['low', 'medium', 'high'], true)) {
        $priority = 'medium';
    }

    $dueDate = trim((string) ($input['dueDate'] ?? ''));
    if ($dueDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
        throw new RuntimeException('Due date must be in YYYY-MM-DD format.');
    }

    $assigneeId = isset($input['assigneeId']) && $input['assigneeId'] !== '' ? (int) $input['assigneeId'] : null;
    if ($assigneeId !== null) {
        $assignee = portal_find_user($db, $assigneeId);
        if (!$assignee || $assignee['role_name'] !== 'employee') {
            throw new RuntimeException('Select a valid employee for assignment.');
        }
    }

    $now = now_ist();
    $linked = trim((string) ($input['linkedTo'] ?? ''));
    $notes = trim((string) ($input['notes'] ?? ''));
    $description = trim((string) ($input['description'] ?? ''));

    $taskId = isset($input['id']) ? (int) $input['id'] : 0;
    if ($taskId > 0) {
        $stmt = $db->prepare('UPDATE portal_tasks SET title = :title, description = :description, status = :status, priority = :priority, due_date = :due_date, linked_reference = :linked_reference, notes = :notes, assignee_id = :assignee_id, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            ':title' => $title,
            ':description' => $description,
            ':status' => $status,
            ':priority' => $priority,
            ':due_date' => $dueDate !== '' ? $dueDate : null,
            ':linked_reference' => $linked !== '' ? $linked : null,
            ':notes' => $notes !== '' ? $notes : null,
            ':assignee_id' => $assigneeId,
            ':updated_at' => $now,
            ':id' => $taskId,
        ]);
        portal_log_action($db, $actorId, 'update', 'task', $taskId, 'Task updated via admin portal');
    } else {
        $stmt = $db->prepare('INSERT INTO portal_tasks(title, description, status, priority, due_date, linked_reference, notes, assignee_id, created_by, created_at, updated_at) VALUES(:title, :description, :status, :priority, :due_date, :linked_reference, :notes, :assignee_id, :created_by, :created_at, :updated_at)');
        $stmt->execute([
            ':title' => $title,
            ':description' => $description,
            ':status' => $status,
            ':priority' => $priority,
            ':due_date' => $dueDate !== '' ? $dueDate : null,
            ':linked_reference' => $linked !== '' ? $linked : null,
            ':notes' => $notes !== '' ? $notes : null,
            ':assignee_id' => $assigneeId,
            ':created_by' => $actorId ?: null,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
        $taskId = (int) $db->lastInsertId();
        portal_log_action($db, $actorId, 'create', 'task', $taskId, 'Task created via admin portal');
    }

    if ($status === 'done') {
        $db->prepare('UPDATE portal_tasks SET completed_at = :completed_at WHERE id = :id')->execute([
            ':completed_at' => $now,
            ':id' => $taskId,
        ]);
    } else {
        $db->prepare('UPDATE portal_tasks SET completed_at = NULL WHERE id = :id')->execute([':id' => $taskId]);
    }

    $stmt = $db->prepare('SELECT portal_tasks.*, users.full_name AS assignee_name, users.email AS assignee_email, roles.name AS assignee_role FROM portal_tasks LEFT JOIN users ON portal_tasks.assignee_id = users.id LEFT JOIN roles ON users.role_id = roles.id WHERE portal_tasks.id = :id LIMIT 1');
    $stmt->execute([':id' => $taskId]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('Task not found after save.');
    }

    return portal_normalize_task_row($row);
}

function portal_update_task_status(PDO $db, int $taskId, string $status, int $actorId): array
{
    if (!in_array($status, ['todo', 'in_progress', 'done'], true)) {
        throw new RuntimeException('Invalid task status.');
    }

    $stmt = $db->prepare('SELECT * FROM portal_tasks WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $taskId]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('Task not found.');
    }

    $now = now_ist();
    $update = $db->prepare('UPDATE portal_tasks SET status = :status, updated_at = :updated_at, completed_at = :completed_at WHERE id = :id');
    $update->execute([
        ':status' => $status,
        ':updated_at' => $now,
        ':completed_at' => $status === 'done' ? $now : null,
        ':id' => $taskId,
    ]);

    portal_log_action($db, $actorId, 'status_change', 'task', $taskId, 'Task status updated to ' . $status);

    $stmt = $db->prepare('SELECT portal_tasks.*, users.full_name AS assignee_name, users.email AS assignee_email, roles.name AS assignee_role FROM portal_tasks LEFT JOIN users ON portal_tasks.assignee_id = users.id LEFT JOIN roles ON users.role_id = roles.id WHERE portal_tasks.id = :id LIMIT 1');
    $stmt->execute([':id' => $taskId]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('Unable to load task after update.');
    }

    return portal_normalize_task_row($row);
}

function portal_list_documents(PDO $db, string $audience = 'admin'): array
{
    $allowed = ['employee', 'admin', 'both'];
    if (!in_array($audience, ['employee', 'admin'], true)) {
        $audience = 'admin';
    }

    if ($audience === 'admin') {
        $stmt = $db->query('SELECT portal_documents.*, users.full_name AS uploaded_by_name FROM portal_documents LEFT JOIN users ON portal_documents.uploaded_by = users.id ORDER BY portal_documents.updated_at DESC');
    } else {
        $stmt = $db->prepare('SELECT portal_documents.*, users.full_name AS uploaded_by_name FROM portal_documents LEFT JOIN users ON portal_documents.uploaded_by = users.id WHERE portal_documents.visibility IN (\'employee\', \'both\') ORDER BY portal_documents.updated_at DESC');
        $stmt->execute();
        return portal_normalize_documents($stmt->fetchAll());
    }

    return portal_normalize_documents($stmt->fetchAll());
}

function portal_normalize_documents(array $rows): array
{
    $documents = [];
    foreach ($rows as $row) {
        $tags = [];
        if (!empty($row['tags'])) {
            $decoded = json_decode((string) $row['tags'], true);
            if (is_array($decoded)) {
                $tags = array_values(array_filter(array_map('strval', $decoded)));
            }
        }
        $documents[] = [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'linkedTo' => $row['linked_to'] ?? '',
            'reference' => $row['reference'] ?? '',
            'tags' => $tags,
            'url' => $row['url'] ?? '',
            'version' => (int) ($row['version'] ?? 1),
            'visibility' => $row['visibility'] ?? 'employee',
            'uploadedBy' => $row['uploaded_by_name'] ?? 'Admin',
            'updatedAt' => $row['updated_at'] ?? '',
        ];
    }

    return $documents;
}

function portal_save_document(PDO $db, array $input, int $actorId): array
{
    $name = trim((string) ($input['name'] ?? ''));
    $linkedTo = trim((string) ($input['linkedTo'] ?? ''));
    if ($name === '' || $linkedTo === '') {
        throw new RuntimeException('Document name and linked module are required.');
    }

    $reference = trim((string) ($input['reference'] ?? ''));
    $url = trim((string) ($input['url'] ?? ''));
    $tags = $input['tags'] ?? [];
    if (is_string($tags)) {
        $tags = array_filter(array_map('trim', explode(',', $tags)));
    }
    if (!is_array($tags)) {
        $tags = [];
    }
    $visibility = $input['visibility'] ?? 'employee';
    if (!in_array($visibility, ['employee', 'admin', 'both'], true)) {
        $visibility = 'employee';
    }

    $now = now_ist();
    $documentId = isset($input['id']) ? (int) $input['id'] : 0;
    if ($documentId > 0) {
        $stmt = $db->prepare('UPDATE portal_documents SET name = :name, linked_to = :linked_to, reference = :reference, tags = :tags, url = :url, version = version + 1, visibility = :visibility, uploaded_by = :uploaded_by, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            ':name' => $name,
            ':linked_to' => $linkedTo,
            ':reference' => $reference !== '' ? $reference : null,
            ':tags' => json_encode(array_values($tags)),
            ':url' => $url !== '' ? $url : null,
            ':visibility' => $visibility,
            ':uploaded_by' => $actorId ?: null,
            ':updated_at' => $now,
            ':id' => $documentId,
        ]);
        portal_log_action($db, $actorId, 'update', 'document', $documentId, 'Document metadata updated');
    } else {
        $stmt = $db->prepare('INSERT INTO portal_documents(name, linked_to, reference, tags, url, version, visibility, uploaded_by, created_at, updated_at) VALUES(:name, :linked_to, :reference, :tags, :url, 1, :visibility, :uploaded_by, :created_at, :updated_at)');
        $stmt->execute([
            ':name' => $name,
            ':linked_to' => $linkedTo,
            ':reference' => $reference !== '' ? $reference : null,
            ':tags' => json_encode(array_values($tags)),
            ':url' => $url !== '' ? $url : null,
            ':visibility' => $visibility,
            ':uploaded_by' => $actorId ?: null,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
        $documentId = (int) $db->lastInsertId();
        portal_log_action($db, $actorId, 'create', 'document', $documentId, 'Document added to shared vault');
    }

    $stmt = $db->prepare('SELECT portal_documents.*, users.full_name AS uploaded_by_name FROM portal_documents LEFT JOIN users ON portal_documents.uploaded_by = users.id WHERE portal_documents.id = :id');
    $stmt->execute([':id' => $documentId]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('Document not found after save.');
    }

    return portal_normalize_documents([$row])[0];
}

function portal_list_notifications(PDO $db, int $userId, string $audience = 'employee'): array
{
    $stmt = $db->prepare('SELECT n.*, IFNULL(s.status, \'unread\') AS read_status FROM portal_notifications n LEFT JOIN portal_notification_status s ON n.id = s.notification_id AND s.user_id = :user_id WHERE n.audience IN (\'all\', :audience) ORDER BY n.created_at DESC');
    $stmt->execute([
        ':user_id' => $userId,
        ':audience' => $audience,
    ]);

    $notifications = [];
    foreach ($stmt->fetchAll() as $row) {
        $notifications[] = [
            'id' => (int) $row['id'],
            'tone' => $row['tone'] ?? 'info',
            'icon' => $row['icon'] ?? 'fa-solid fa-circle-info',
            'title' => $row['title'],
            'message' => $row['message'],
            'link' => $row['link'] ?? '#',
            'time' => $row['created_at'] ?? '',
            'isRead' => ($row['read_status'] ?? 'unread') === 'read',
        ];
    }

    return $notifications;
}

function portal_mark_notification(PDO $db, int $notificationId, int $userId, string $status): void
{
    if (!in_array($status, ['read', 'unread', 'dismissed'], true)) {
        throw new RuntimeException('Invalid notification status.');
    }

    $stmt = $db->prepare('INSERT INTO portal_notification_status(notification_id, user_id, status, updated_at) VALUES(:notification_id, :user_id, :status, :updated_at)
        ON CONFLICT(notification_id, user_id) DO UPDATE SET status = excluded.status, updated_at = excluded.updated_at');
    $stmt->execute([
        ':notification_id' => $notificationId,
        ':user_id' => $userId,
        ':status' => $status,
        ':updated_at' => now_ist(),
    ]);
}

function portal_latest_sync(PDO $db, ?int $userId = null): ?string
{
    $parts = [];
    $tasksQuery = 'SELECT MAX(updated_at) FROM portal_tasks';
    $complaintsQuery = 'SELECT MAX(updated_at) FROM complaints';
    $documentsQuery = 'SELECT MAX(updated_at) FROM portal_documents';
    $notificationsQuery = 'SELECT MAX(created_at) FROM portal_notifications';

    $parts[] = $db->query($tasksQuery)->fetchColumn();
    $parts[] = $db->query($complaintsQuery)->fetchColumn();
    $parts[] = $db->query($documentsQuery)->fetchColumn();
    $parts[] = $db->query($notificationsQuery)->fetchColumn();

    if ($userId !== null) {
        $stmt = $db->prepare('SELECT MAX(updated_at) FROM portal_notification_status WHERE user_id = :user_id');
        $stmt->execute([':user_id' => $userId]);
        $parts[] = $stmt->fetchColumn();
    }

    $parts = array_filter(array_map(static fn ($value) => $value !== false ? $value : null, $parts));
    if (empty($parts)) {
        return null;
    }

    rsort($parts);
    return $parts[0] ?: null;
}

function portal_log_action(PDO $db, int $actorId, string $action, string $entityType, int $entityId, string $description): void
{
    $stmt = $db->prepare('INSERT INTO audit_logs(actor_id, action, entity_type, entity_id, description, created_at) VALUES(:actor_id, :action, :entity_type, :entity_id, :description, :created_at)');
    $stmt->execute([
        ':actor_id' => $actorId ?: null,
        ':action' => $action,
        ':entity_type' => $entityType,
        ':entity_id' => $entityId,
        ':description' => $description,
        ':created_at' => now_ist(),
    ]);
}

function portal_employee_complaints(PDO $db, int $userId): array
{
    $stmt = $db->prepare('SELECT complaints.*, users.full_name AS assigned_to_name, roles.name AS assigned_role FROM complaints LEFT JOIN users ON complaints.assigned_to = users.id LEFT JOIN roles ON users.role_id = roles.id WHERE complaints.assigned_to = :user_id ORDER BY complaints.created_at DESC');
    $stmt->execute([':user_id' => $userId]);
    return array_map('portal_normalize_complaint_row', $stmt->fetchAll());
}

function portal_all_complaints(PDO $db): array
{
    $stmt = $db->query('SELECT complaints.*, users.full_name AS assigned_to_name, roles.name AS assigned_role FROM complaints LEFT JOIN users ON complaints.assigned_to = users.id LEFT JOIN roles ON users.role_id = roles.id ORDER BY complaints.created_at DESC');
    return array_map('portal_normalize_complaint_row', $stmt->fetchAll());
}

function portal_normalize_complaint_row(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'reference' => $row['reference'],
        'title' => $row['title'],
        'description' => $row['description'] ?? '',
        'priority' => $row['priority'] ?? 'medium',
        'status' => $row['status'] ?? 'intake',
        'assignedTo' => $row['assigned_to'] !== null ? (int) $row['assigned_to'] : null,
        'assigneeName' => $row['assigned_to_name'] ?? '',
        'assigneeRole' => $row['assigned_role'] ?? '',
        'slaDue' => $row['sla_due_at'] ?? '',
        'createdAt' => $row['created_at'] ?? '',
        'updatedAt' => $row['updated_at'] ?? '',
    ];
}

function portal_update_complaint_status(PDO $db, string $reference, string $statusKey, int $actorId): array
{
    $reference = trim($reference);
    if ($reference === '') {
        throw new RuntimeException('Complaint reference is required.');
    }

    $stmt = $db->prepare('SELECT * FROM complaints WHERE reference = :reference LIMIT 1');
    $stmt->execute([':reference' => $reference]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('Complaint not found.');
    }

    $statusMap = [
        'in_progress' => 'work',
        'awaiting_response' => 'resolution',
        'resolved' => 'closed',
        'escalated' => 'triage',
    ];

    if (!isset($statusMap[$statusKey])) {
        throw new RuntimeException('Unsupported ticket status.');
    }

    $newStatus = $statusMap[$statusKey];
    $update = $db->prepare('UPDATE complaints SET status = :status, updated_at = :updated_at, assigned_to = CASE WHEN :status = \'triage\' THEN NULL ELSE assigned_to END WHERE reference = :reference');
    $update->execute([
        ':status' => $newStatus,
        ':updated_at' => now_ist(),
        ':reference' => $reference,
    ]);

    portal_log_action($db, $actorId, 'status_change', 'complaint', (int) $row['id'], 'Complaint updated to ' . $newStatus);

    $stmt = $db->prepare('SELECT complaints.*, users.full_name AS assigned_to_name, roles.name AS assigned_role FROM complaints LEFT JOIN users ON complaints.assigned_to = users.id LEFT JOIN roles ON users.role_id = roles.id WHERE complaints.reference = :reference LIMIT 1');
    $stmt->execute([':reference' => $reference]);
    $updated = $stmt->fetch();
    if (!$updated) {
        throw new RuntimeException('Unable to load complaint after update.');
    }

    return portal_normalize_complaint_row($updated);
}
