<?php
declare(strict_types=1);

require_once __DIR__ . '/blog.php';

function safe_get_constant(string $name, $default = null)
{
    if (!defined($name)) {
        return $default;
    }

    try {
        return constant($name);
    } catch (Throwable $exception) {
        error_log(sprintf('Failed to read constant %s: %s', $name, $exception->getMessage()));
        return $default;
    }
}

function resolve_admin_email(): string
{
    static $resolvedEmail = null;
    if ($resolvedEmail !== null) {
        return $resolvedEmail;
    }

    $candidates = [
        safe_get_constant('ADMIN_EMAIL'),
        $_ENV['ADMIN_EMAIL'] ?? null,
        $_SERVER['ADMIN_EMAIL'] ?? null,
        getenv('ADMIN_EMAIL') ?: null,
        $_ENV['FALLBACK_ADMIN_EMAIL'] ?? null,
        $_SERVER['FALLBACK_ADMIN_EMAIL'] ?? null,
        getenv('FALLBACK_ADMIN_EMAIL') ?: null,
    ];

    foreach ($candidates as $candidate) {
        if (!is_string($candidate)) {
            continue;
        }

        $candidate = trim($candidate);
        if ($candidate === '') {
            continue;
        }

        if (!filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
            continue;
        }

        $resolvedEmail = $candidate;
        break;
    }

    if ($resolvedEmail === null) {
        $resolvedEmail = 'support@dakshayani.in';
    }

    if (!defined('ADMIN_EMAIL')) {
        define('ADMIN_EMAIL', $resolvedEmail);
    }

    return $resolvedEmail;
}

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
    notes TEXT DEFAULT '[]',
    attachments TEXT DEFAULT '[]',
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY(assigned_to) REFERENCES users(id)
)
SQL
    );

    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS crm_leads (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    phone TEXT,
    email TEXT,
    source TEXT,
    status TEXT NOT NULL DEFAULT 'new' CHECK(status IN ('new','visited','quotation','converted','lost')),
    assigned_to INTEGER,
    created_by INTEGER,
    site_location TEXT,
    site_details TEXT,
    notes TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now', '+330 minutes')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now', '+330 minutes')),
    FOREIGN KEY(assigned_to) REFERENCES users(id),
    FOREIGN KEY(created_by) REFERENCES users(id)
)
SQL
    );

    $db->exec('CREATE INDEX IF NOT EXISTS idx_crm_leads_status ON crm_leads(status)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_crm_leads_updated_at ON crm_leads(updated_at DESC)');

    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS lead_visits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    lead_id INTEGER NOT NULL,
    employee_id INTEGER NOT NULL,
    note TEXT NOT NULL,
    photo_name TEXT,
    photo_mime TEXT,
    photo_data TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now', '+330 minutes')),
    FOREIGN KEY(lead_id) REFERENCES crm_leads(id) ON DELETE CASCADE,
    FOREIGN KEY(employee_id) REFERENCES users(id)
)
SQL
    );

    $db->exec('CREATE INDEX IF NOT EXISTS idx_lead_visits_lead ON lead_visits(lead_id)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_lead_visits_created_at ON lead_visits(created_at DESC)');

    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS lead_stage_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    lead_id INTEGER NOT NULL,
    actor_id INTEGER,
    from_status TEXT,
    to_status TEXT NOT NULL,
    note TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now', '+330 minutes')),
    FOREIGN KEY(lead_id) REFERENCES crm_leads(id) ON DELETE CASCADE,
    FOREIGN KEY(actor_id) REFERENCES users(id)
)
SQL
    );

    $db->exec('CREATE INDEX IF NOT EXISTS idx_lead_stage_logs_lead ON lead_stage_logs(lead_id)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_lead_stage_logs_created_at ON lead_stage_logs(created_at DESC)');

    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS lead_proposals (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    lead_id INTEGER NOT NULL,
    employee_id INTEGER NOT NULL,
    summary TEXT NOT NULL,
    estimate_amount REAL,
    document_name TEXT,
    document_mime TEXT,
    document_data TEXT,
    status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','approved','rejected')),
    review_note TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now', '+330 minutes')),
    approved_at TEXT,
    approved_by INTEGER,
    FOREIGN KEY(lead_id) REFERENCES crm_leads(id) ON DELETE CASCADE,
    FOREIGN KEY(employee_id) REFERENCES users(id),
    FOREIGN KEY(approved_by) REFERENCES users(id)
)
SQL
    );

    $db->exec('CREATE INDEX IF NOT EXISTS idx_lead_proposals_lead ON lead_proposals(lead_id)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_lead_proposals_status ON lead_proposals(status)');

    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS subsidy_applications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_name TEXT NOT NULL,
    application_number TEXT,
    status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','submitted','approved','rejected','disbursed')),
    amount INTEGER,
    submitted_on TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now', '+330 minutes')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now', '+330 minutes'))
)
SQL
    );

    $db->exec('CREATE INDEX IF NOT EXISTS idx_subsidy_applications_status ON subsidy_applications(status)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_subsidy_applications_updated_at ON subsidy_applications(updated_at DESC)');

    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS reminders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    module TEXT NOT NULL,
    due_on TEXT,
    status TEXT NOT NULL DEFAULT 'open' CHECK(status IN ('open','done','dismissed')),
    notes TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now', '+330 minutes')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now', '+330 minutes'))
)
SQL
    );

    $db->exec('CREATE INDEX IF NOT EXISTS idx_reminders_status ON reminders(status)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_reminders_module ON reminders(module)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_reminders_updated_at ON reminders(updated_at DESC)');

    $complaintColumns = $db->query("PRAGMA table_info('complaints')")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('notes', $complaintColumns, true)) {
        $db->exec("ALTER TABLE complaints ADD COLUMN notes TEXT DEFAULT '[]'");
    }
    if (!in_array('attachments', $complaintColumns, true)) {
        $db->exec("ALTER TABLE complaints ADD COLUMN attachments TEXT DEFAULT '[]'");
    }

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
    scope_user_id INTEGER,
    created_at TEXT NOT NULL DEFAULT (datetime('now', '+330 minutes')),
    expires_at TEXT,
    FOREIGN KEY(scope_user_id) REFERENCES users(id)
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
CREATE TABLE IF NOT EXISTS complaint_updates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    complaint_id INTEGER NOT NULL,
    actor_id INTEGER,
    entry_type TEXT NOT NULL CHECK(entry_type IN ('status','note','document','assignment')),
    summary TEXT NOT NULL,
    details TEXT,
    document_id INTEGER,
    status_to TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now', '+330 minutes')),
    FOREIGN KEY(complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
    FOREIGN KEY(actor_id) REFERENCES users(id),
    FOREIGN KEY(document_id) REFERENCES portal_documents(id)
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
CREATE TABLE IF NOT EXISTS login_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    identifier_hash TEXT NOT NULL,
    ip_hash TEXT NOT NULL,
    attempts INTEGER NOT NULL DEFAULT 0,
    locked_until TEXT,
    last_attempt_at TEXT NOT NULL DEFAULT (datetime('now')),
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE(identifier_hash, ip_hash)
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
        'installer' => 'Field installers responsible for on-site execution.',
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
        'email' => 'd.entranchi@gmail.com',
        'username' => 'admin',
        'password' => 'Dent@2025',
        'permissions_note' => 'Full access',
        'legacy_emails' => ['admin@dakshayani.in', 'd.entranchi@gmail.com'],
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

    ensure_default_user($db, [
        'role' => 'installer',
        'full_name' => 'Lead Installer',
        'email' => 'installer@dakshayani.in',
        'username' => 'installer',
        'password' => 'Installer@2025',
        'permissions_note' => 'Installer workspace access',
        'legacy_usernames' => ['installer'],
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
    merge_employee_roles($db);
}

function record_system_audit(PDO $db, string $action, string $entityType, int $entityId, string $description): void
{
    $stmt = $db->prepare('INSERT INTO audit_logs(actor_id, action, entity_type, entity_id, description) VALUES(NULL, :action, :entity_type, :entity_id, :description)');
    $stmt->execute([
        ':action' => $action,
        ':entity_type' => $entityType,
        ':entity_id' => $entityId,
        ':description' => $description,
    ]);
}

function unify_employee_roles(PDO $db): void
{
    $employeeRoleId = (int) $db->query("SELECT id FROM roles WHERE name = 'employee'")->fetchColumn();
    if ($employeeRoleId <= 0) {
        return;
    }

    $legacyStmt = $db->query("SELECT id, name FROM roles WHERE name NOT IN ('admin','employee')");
    $legacyRoles = $legacyStmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$legacyRoles) {
        return;
    }

    foreach ($legacyRoles as $legacyRole) {
        $roleId = (int) $legacyRole['id'];
        $roleName = (string) $legacyRole['name'];

        $usersStmt = $db->prepare('SELECT id, email, permissions_note FROM users WHERE role_id = :role_id');
        $usersStmt->execute([':role_id' => $roleId]);
        foreach ($usersStmt->fetchAll(PDO::FETCH_ASSOC) as $userRow) {
            $noteParts = [];
            $existingNote = trim((string) ($userRow['permissions_note'] ?? ''));
            if ($existingNote !== '') {
                $noteParts[] = $existingNote;
            }
            $noteParts[] = sprintf('Role auto-converted from %s on %s', ucfirst($roleName), now_ist());
            $note = implode("\n", $noteParts);

            $updateUser = $db->prepare('UPDATE users SET role_id = :employee_role, permissions_note = :note, updated_at = datetime(\'now\') WHERE id = :id');
            $updateUser->execute([
                ':employee_role' => $employeeRoleId,
                ':note' => $note,
                ':id' => (int) $userRow['id'],
            ]);

            record_system_audit($db, 'role_unified', 'user', (int) $userRow['id'], sprintf('User %s merged into Employee role (previously %s)', $userRow['email'], $roleName));
        }

        $inviteStmt = $db->prepare('SELECT id, invitee_email FROM invitations WHERE role_id = :role_id');
        $inviteStmt->execute([':role_id' => $roleId]);
        foreach ($inviteStmt->fetchAll(PDO::FETCH_ASSOC) as $inviteRow) {
            $db->prepare('UPDATE invitations SET role_id = :employee_role WHERE id = :id')->execute([
                ':employee_role' => $employeeRoleId,
                ':id' => (int) $inviteRow['id'],
            ]);

            record_system_audit($db, 'role_unified', 'invitation', (int) $inviteRow['id'], sprintf('Invitation for %s reassigned to Employee role (previously %s)', $inviteRow['invitee_email'], $roleName));
        }

        $db->prepare('DELETE FROM roles WHERE id = :role_id')->execute([':role_id' => $roleId]);
        record_system_audit($db, 'role_removed', 'role', $roleId, sprintf('Legacy role %s removed during Employee role unification', $roleName));
    }
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
    upgrade_installations_table($db);
    ensure_login_policy_row($db);
    ensure_portal_tables($db);
    ensure_lead_tables($db);
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

function upgrade_installations_table(PDO $db): void
{
    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS installations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_name TEXT NOT NULL,
    project_reference TEXT,
    capacity_kw REAL,
    status TEXT NOT NULL DEFAULT 'planning' CHECK(status IN ('planning','in_progress','completed','on_hold','cancelled')),
    stage TEXT NOT NULL DEFAULT 'structure' CHECK(stage IN ('structure','wiring','testing','meter','commissioned')),
    stage_entries TEXT NOT NULL DEFAULT '[]',
    requested_stage TEXT,
    requested_by INTEGER,
    requested_at TEXT,
    amc_committed INTEGER NOT NULL DEFAULT 0,
    scheduled_date TEXT,
    handover_date TEXT,
    assigned_to INTEGER,
    installer_id INTEGER,
    created_at TEXT NOT NULL DEFAULT (datetime('now', '+330 minutes')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now', '+330 minutes')),
    FOREIGN KEY(assigned_to) REFERENCES users(id),
    FOREIGN KEY(requested_by) REFERENCES users(id),
    FOREIGN KEY(installer_id) REFERENCES users(id)
)
SQL
    );

    $columns = $db->query("PRAGMA table_info('installations')")->fetchAll(PDO::FETCH_COLUMN, 1);

    if (!in_array('stage', $columns, true)) {
        $db->exec("ALTER TABLE installations ADD COLUMN stage TEXT NOT NULL DEFAULT 'structure'");
    }

    if (!in_array('stage_entries', $columns, true)) {
        $db->exec("ALTER TABLE installations ADD COLUMN stage_entries TEXT NOT NULL DEFAULT '[]'");
    }

    if (!in_array('requested_stage', $columns, true)) {
        $db->exec('ALTER TABLE installations ADD COLUMN requested_stage TEXT');
    }

    if (!in_array('requested_by', $columns, true)) {
        $db->exec('ALTER TABLE installations ADD COLUMN requested_by INTEGER');
    }

    if (!in_array('requested_at', $columns, true)) {
        $db->exec('ALTER TABLE installations ADD COLUMN requested_at TEXT');
    }

    if (!in_array('amc_committed', $columns, true)) {
        $db->exec("ALTER TABLE installations ADD COLUMN amc_committed INTEGER NOT NULL DEFAULT 0");
    }

    if (!in_array('installer_id', $columns, true)) {
        $db->exec('ALTER TABLE installations ADD COLUMN installer_id INTEGER');
    }

    // Normalise default values after adding new columns.
    $db->exec("UPDATE installations SET stage = CASE WHEN stage IS NULL OR stage = '' THEN 'structure' ELSE stage END");
    $db->exec("UPDATE installations SET stage_entries = CASE WHEN stage_entries IS NULL OR stage_entries = '' THEN '[]' ELSE stage_entries END");
    $db->exec("UPDATE installations SET amc_committed = CASE WHEN amc_committed IS NULL THEN 0 ELSE amc_committed END");

    // Align legacy status values with the new stage flow.
    $db->exec("UPDATE installations SET stage = 'commissioned' WHERE status = 'completed' AND (stage IS NULL OR stage NOT IN ('structure','wiring','testing','meter','commissioned'))");
    $db->exec("UPDATE installations SET status = 'in_progress' WHERE status NOT IN ('on_hold','cancelled','completed') AND stage != 'commissioned'");
    $db->exec("UPDATE installations SET status = 'completed' WHERE stage = 'commissioned'");

    $db->exec('CREATE INDEX IF NOT EXISTS idx_installations_status ON installations(status)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_installations_stage ON installations(stage)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_installations_requested_stage ON installations(requested_stage)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_installations_updated_at ON installations(updated_at DESC)');
}

function ensure_login_policy_row(PDO $db): void
{
    $count = (int) $db->query('SELECT COUNT(*) FROM login_policies')->fetchColumn();
    if ($count === 0) {
        $db->exec("INSERT INTO login_policies(id, retry_limit, lockout_minutes, twofactor_mode, session_timeout) VALUES (1, 5, 30, 'admin', 45)");
    }
}

function get_session_timeout_minutes(PDO $db): int
{
    ensure_login_policy_row($db);
    $timeout = (int) $db->query('SELECT session_timeout FROM login_policies WHERE id = 1')->fetchColumn();
    if ($timeout < 15) {
        return 15;
    }
    if ($timeout > 720) {
        return 720;
    }

    return $timeout;
}

function get_login_policy(PDO $db): array
{
    ensure_login_policy_row($db);
    $row = $db->query('SELECT retry_limit, lockout_minutes, session_timeout FROM login_policies WHERE id = 1')->fetch(PDO::FETCH_ASSOC) ?: [];

    $retryLimit = (int) ($row['retry_limit'] ?? 5);
    if ($retryLimit < 3) {
        $retryLimit = 3;
    }

    $lockoutMinutes = (int) ($row['lockout_minutes'] ?? 30);
    if ($lockoutMinutes < 1) {
        $lockoutMinutes = 1;
    }
    if ($lockoutMinutes > 720) {
        $lockoutMinutes = 720;
    }

    $sessionTimeout = get_session_timeout_minutes($db);

    return [
        'retry_limit' => $retryLimit,
        'lockout_minutes' => $lockoutMinutes,
        'session_timeout' => $sessionTimeout,
    ];
}

function normalize_login_identifier(string $identifier): string
{
    $normalized = strtolower(trim($identifier));
    return hash('sha256', $normalized);
}

function normalize_login_ip(string $ip): string
{
    $candidate = trim($ip);
    if ($candidate === '') {
        $candidate = '0.0.0.0';
    }

    return hash('sha256', $candidate);
}

function purge_login_attempts(PDO $db): void
{
    $db->exec("DELETE FROM login_attempts WHERE last_attempt_at < datetime('now', '-2 days')");
}

function login_attempt_snapshot(PDO $db, string $identifierHash, string $ipHash): ?array
{
    $stmt = $db->prepare('SELECT attempts, locked_until, last_attempt_at FROM login_attempts WHERE identifier_hash = :identifier_hash AND ip_hash = :ip_hash LIMIT 1');
    $stmt->execute([
        ':identifier_hash' => $identifierHash,
        ':ip_hash' => $ipHash,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function store_login_attempt(PDO $db, string $identifierHash, string $ipHash, int $attempts, ?string $lockedUntil): void
{
    $stmt = $db->prepare("INSERT INTO login_attempts(identifier_hash, ip_hash, attempts, locked_until, last_attempt_at) VALUES(:identifier_hash, :ip_hash, :attempts, :locked_until, datetime('now'))\n        ON CONFLICT(identifier_hash, ip_hash) DO UPDATE SET attempts = excluded.attempts, locked_until = excluded.locked_until, last_attempt_at = excluded.last_attempt_at");
    $stmt->execute([
        ':identifier_hash' => $identifierHash,
        ':ip_hash' => $ipHash,
        ':attempts' => $attempts,
        ':locked_until' => $lockedUntil,
    ]);
}

function login_rate_limit_status(PDO $db, string $identifier, string $ipAddress, array $policy): array
{
    purge_login_attempts($db);

    $identifierHash = normalize_login_identifier($identifier);
    $ipHash = normalize_login_ip($ipAddress);
    $row = login_attempt_snapshot($db, $identifierHash, $ipHash);

    $attempts = (int) ($row['attempts'] ?? 0);
    $lockedUntilRaw = $row['locked_until'] ?? null;
    $lockedSeconds = 0;

    if (is_string($lockedUntilRaw) && $lockedUntilRaw !== '') {
        try {
            $lockedUntil = new DateTime($lockedUntilRaw, new DateTimeZone('UTC'));
            $lockedSeconds = max(0, $lockedUntil->getTimestamp() - time());
            if ($lockedSeconds <= 0) {
                store_login_attempt($db, $identifierHash, $ipHash, 0, null);
                $attempts = 0;
                $lockedSeconds = 0;
            }
        } catch (Throwable $exception) {
            error_log('Failed to parse locked_until: ' . $exception->getMessage());
            store_login_attempt($db, $identifierHash, $ipHash, 0, null);
            $attempts = 0;
            $lockedSeconds = 0;
        }
    }

    $retryLimit = (int) ($policy['retry_limit'] ?? 5);
    if ($retryLimit < 1) {
        $retryLimit = 5;
    }

    $remainingAttempts = $lockedSeconds > 0 ? 0 : max(0, $retryLimit - $attempts);

    return [
        'attempts' => $attempts,
        'locked' => $lockedSeconds > 0,
        'seconds_until_unlock' => $lockedSeconds,
        'remaining_attempts' => $remainingAttempts,
    ];
}

function login_rate_limit_register_failure(PDO $db, string $identifier, string $ipAddress, array $policy): array
{
    $identifierHash = normalize_login_identifier($identifier);
    $ipHash = normalize_login_ip($ipAddress);
    $row = login_attempt_snapshot($db, $identifierHash, $ipHash) ?: ['attempts' => 0, 'locked_until' => null];

    $attempts = (int) ($row['attempts'] ?? 0);
    $attempts++;

    $retryLimit = (int) ($policy['retry_limit'] ?? 5);
    if ($retryLimit < 1) {
        $retryLimit = 5;
    }

    $lockoutMinutes = (int) ($policy['lockout_minutes'] ?? 30);
    if ($lockoutMinutes < 1) {
        $lockoutMinutes = 1;
    }
    if ($lockoutMinutes > 720) {
        $lockoutMinutes = 720;
    }

    $lockedUntil = null;
    $secondsUntilUnlock = 0;

    if ($attempts >= $retryLimit) {
        $attempts = 0;
        $unlockTime = new DateTime('now', new DateTimeZone('UTC'));
        $unlockTime->modify('+' . $lockoutMinutes . ' minutes');
        $lockedUntil = $unlockTime->format('Y-m-d H:i:s');
        $secondsUntilUnlock = max(60, $lockoutMinutes * 60);
    }

    store_login_attempt($db, $identifierHash, $ipHash, $attempts, $lockedUntil);

    if ($lockedUntil !== null) {
        record_system_audit(
            $db,
            'login_rate_limit',
            'security',
            0,
            sprintf(
                'Login identifier %s locked after repeated failures from %s',
                mask_email_for_log($identifier),
                mask_ip_for_log($ipAddress)
            )
        );
    }

    return [
        'locked' => $lockedUntil !== null,
        'seconds_until_unlock' => $secondsUntilUnlock,
        'remaining_attempts' => $lockedUntil === null ? max(0, $retryLimit - $attempts) : 0,
    ];
}

function login_rate_limit_register_success(PDO $db, string $identifier, string $ipAddress): void
{
    $stmt = $db->prepare('DELETE FROM login_attempts WHERE identifier_hash = :identifier_hash AND ip_hash = :ip_hash');
    $stmt->execute([
        ':identifier_hash' => normalize_login_identifier($identifier),
        ':ip_hash' => normalize_login_ip($ipAddress),
    ]);
}

function mask_email_for_log(string $email): string
{
    $email = strtolower(trim($email));
    if ($email === '' || !str_contains($email, '@')) {
        return 'unknown-email';
    }

    [$local, $domain] = explode('@', $email, 2);
    $localLength = strlen($local);
    if ($localLength <= 2) {
        $localMasked = substr($local, 0, 1) . '*';
    } else {
        $localMasked = substr($local, 0, 1) . str_repeat('*', $localLength - 2) . substr($local, -1);
    }

    return $localMasked . '@' . $domain;
}

function mask_ip_for_log(string $ip): string
{
    $ip = trim($ip);
    if ($ip === '') {
        return '0.0.0.*';
    }

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $segments = explode(':', $ip);
        $segments = array_pad($segments, 8, '0');
        return sprintf('%s:%s:%s:%s::*', $segments[0], $segments[1], $segments[2], $segments[3]);
    }

    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return '0.0.0.*';
    }

    $parts = explode('.', $ip);
    $parts[3] = '*';
    return implode('.', $parts);
}

function is_password_hash_valid(?string $hash): bool
{
    if (!is_string($hash) || $hash === '') {
        return false;
    }

    $info = password_get_info($hash);
    return is_array($info) && ($info['algo'] ?? 0) !== 0;
}

function has_active_admin(PDO $db): bool
{
    $stmt = $db->prepare("SELECT COUNT(*) FROM users INNER JOIN roles ON users.role_id = roles.id WHERE roles.name = 'admin' AND users.status = 'active'");
    $stmt->execute();
    return (int) $stmt->fetchColumn() > 0;
}

function count_admin_accounts_with_invalid_hash(PDO $db): int
{
    $stmt = $db->prepare("SELECT users.password_hash FROM users INNER JOIN roles ON users.role_id = roles.id WHERE roles.name = 'admin' AND users.status = 'active'");
    $stmt->execute();
    $invalid = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $hash) {
        if (!is_password_hash_valid((string) $hash)) {
            $invalid++;
        }
    }

    return $invalid;
}

function admin_recovery_secret(): string
{
    $candidates = [
        $_ENV['ADMIN_RECOVERY_TOKEN'] ?? null,
        $_SERVER['ADMIN_RECOVERY_TOKEN'] ?? null,
        getenv('ADMIN_RECOVERY_TOKEN') ?: null,
    ];

    foreach ($candidates as $candidate) {
        if (!is_string($candidate)) {
            continue;
        }

        $candidate = trim($candidate);
        if ($candidate !== '') {
            return $candidate;
        }
    }

    return '';
}

function admin_recovery_force_enabled(): bool
{
    $candidates = [
        $_ENV['ADMIN_RECOVERY_FORCE'] ?? null,
        $_SERVER['ADMIN_RECOVERY_FORCE'] ?? null,
        getenv('ADMIN_RECOVERY_FORCE') ?: null,
    ];

    foreach ($candidates as $candidate) {
        if (!is_string($candidate)) {
            continue;
        }

        $normalized = strtolower(trim($candidate));
        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }
    }

    return false;
}

function admin_recovery_consumed(PDO $db): bool
{
    $consumed = get_setting('admin_recovery_consumed_at', $db);
    if ($consumed === null) {
        return false;
    }

    return trim($consumed) !== '';
}

function is_admin_recovery_available(PDO $db): bool
{
    $secret = admin_recovery_secret();
    if ($secret === '') {
        return false;
    }

    if (admin_recovery_consumed($db)) {
        return false;
    }

    if (admin_recovery_force_enabled()) {
        return true;
    }

    if (!has_active_admin($db)) {
        return true;
    }

    return count_admin_accounts_with_invalid_hash($db) > 0;
}

function perform_admin_recovery(PDO $db, string $email, string $username, string $fullName, string $password, string $permissionsNote = 'Full access'): int
{
    $roleStmt = $db->prepare("SELECT id FROM roles WHERE name = 'admin' LIMIT 1");
    $roleStmt->execute();
    $roleId = $roleStmt->fetchColumn();
    if ($roleId === false) {
        throw new RuntimeException('Administrator role is missing.');
    }

    $roleId = (int) $roleId;
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $lookup = $db->prepare('SELECT id FROM users WHERE (LOWER(email) = LOWER(:email) OR LOWER(username) = LOWER(:username)) LIMIT 1');
    $lookup->execute([
        ':email' => $email,
        ':username' => $username,
    ]);
    $existing = $lookup->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($existing) {
        $userId = (int) $existing['id'];
        $stmt = $db->prepare("UPDATE users SET full_name = :full_name, email = :email, username = :username, password_hash = :password_hash, role_id = :role_id, status = 'active', permissions_note = :permissions_note, password_last_set_at = datetime('now'), updated_at = datetime('now') WHERE id = :id");
        $stmt->execute([
            ':full_name' => $fullName,
            ':email' => $email,
            ':username' => $username,
            ':password_hash' => $hash,
            ':role_id' => $roleId,
            ':permissions_note' => $permissionsNote,
            ':id' => $userId,
        ]);
    } else {
        $stmt = $db->prepare("INSERT INTO users(full_name, email, username, password_hash, role_id, status, permissions_note, password_last_set_at, created_at, updated_at) VALUES(:full_name, :email, :username, :password_hash, :role_id, 'active', :permissions_note, datetime('now'), datetime('now'), datetime('now'))");
        $stmt->execute([
            ':full_name' => $fullName,
            ':email' => $email,
            ':username' => $username,
            ':password_hash' => $hash,
            ':role_id' => $roleId,
            ':permissions_note' => $permissionsNote,
        ]);
        $userId = (int) $db->lastInsertId();
    }

    return $userId;
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
    scope_user_id INTEGER,
    created_at TEXT NOT NULL DEFAULT (datetime('now', '+330 minutes')),
    expires_at TEXT,
    FOREIGN KEY(scope_user_id) REFERENCES users(id)
)
SQL
        );
    } else {
        $columns = $db->query('PRAGMA table_info(portal_notifications)')->fetchAll(PDO::FETCH_ASSOC);
        $hasScope = false;
        foreach ($columns as $column) {
            if (($column['name'] ?? '') === 'scope_user_id') {
                $hasScope = true;
                break;
            }
        }
        if (!$hasScope) {
            $db->exec('ALTER TABLE portal_notifications ADD COLUMN scope_user_id INTEGER');
        }
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

    if (!in_array('complaint_updates', $tables, true)) {
        $db->exec(<<<'SQL'
CREATE TABLE complaint_updates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    complaint_id INTEGER NOT NULL,
    actor_id INTEGER,
    entry_type TEXT NOT NULL CHECK(entry_type IN ('status','note','document','assignment')),
    summary TEXT NOT NULL,
    details TEXT,
    document_id INTEGER,
    status_to TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now', '+330 minutes')),
    FOREIGN KEY(complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
    FOREIGN KEY(actor_id) REFERENCES users(id),
    FOREIGN KEY(document_id) REFERENCES portal_documents(id)
)
SQL
        );
    }
}

function ensure_lead_tables(PDO $db): void
{
    $schema = (string) $db->query("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'crm_leads'")->fetchColumn();
    if ($schema === '') {
        return;
    }

    $requiresRebuild = str_contains($schema, "'contacted'") || str_contains($schema, "'qualified'") || !str_contains($schema, 'site_location') || !str_contains($schema, 'created_by');

    if ($requiresRebuild) {
        $db->beginTransaction();
        try {
            $db->exec('ALTER TABLE crm_leads RENAME TO crm_leads_backup');
            $db->exec(<<<'SQL'
CREATE TABLE crm_leads (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    phone TEXT,
    email TEXT,
    source TEXT,
    status TEXT NOT NULL DEFAULT 'new' CHECK(status IN ('new','visited','quotation','converted','lost')),
    assigned_to INTEGER,
    created_by INTEGER,
    site_location TEXT,
    site_details TEXT,
    notes TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now', '+330 minutes')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now', '+330 minutes')),
    FOREIGN KEY(assigned_to) REFERENCES users(id),
    FOREIGN KEY(created_by) REFERENCES users(id)
)
SQL
            );
            $db->exec(<<<'SQL'
INSERT INTO crm_leads (id, name, phone, email, source, status, assigned_to, created_by, site_location, site_details, notes, created_at, updated_at)
SELECT id, name, phone, email, source,
       CASE
           WHEN status IN ('new','visited','quotation','converted','lost') THEN status
           WHEN status = 'contacted' THEN 'visited'
           WHEN status = 'qualified' THEN 'quotation'
           ELSE 'new'
       END AS status,
       assigned_to,
       NULL AS created_by,
       NULL AS site_location,
       NULL AS site_details,
       notes,
       created_at,
       updated_at
FROM crm_leads_backup
SQL
            );
            $db->exec('DROP TABLE crm_leads_backup');
        } catch (Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }
        $db->commit();
    }

    $columns = $db->query('PRAGMA table_info(crm_leads)')->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('created_by', $columns, true)) {
        $db->exec('ALTER TABLE crm_leads ADD COLUMN created_by INTEGER');
    }
    if (!in_array('site_location', $columns, true)) {
        $db->exec('ALTER TABLE crm_leads ADD COLUMN site_location TEXT');
    }
    if (!in_array('site_details', $columns, true)) {
        $db->exec('ALTER TABLE crm_leads ADD COLUMN site_details TEXT');
    }
    $db->exec('CREATE INDEX IF NOT EXISTS idx_crm_leads_status ON crm_leads(status)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_crm_leads_updated_at ON crm_leads(updated_at DESC)');

    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS lead_visits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    lead_id INTEGER NOT NULL,
    employee_id INTEGER NOT NULL,
    note TEXT NOT NULL,
    photo_name TEXT,
    photo_mime TEXT,
    photo_data TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now', '+330 minutes')),
    FOREIGN KEY(lead_id) REFERENCES crm_leads(id) ON DELETE CASCADE,
    FOREIGN KEY(employee_id) REFERENCES users(id)
)
SQL
    );
    $db->exec('CREATE INDEX IF NOT EXISTS idx_lead_visits_lead ON lead_visits(lead_id)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_lead_visits_created_at ON lead_visits(created_at DESC)');

    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS lead_stage_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    lead_id INTEGER NOT NULL,
    actor_id INTEGER,
    from_status TEXT,
    to_status TEXT NOT NULL,
    note TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now', '+330 minutes')),
    FOREIGN KEY(lead_id) REFERENCES crm_leads(id) ON DELETE CASCADE,
    FOREIGN KEY(actor_id) REFERENCES users(id)
)
SQL
    );
    $db->exec('CREATE INDEX IF NOT EXISTS idx_lead_stage_logs_lead ON lead_stage_logs(lead_id)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_lead_stage_logs_created_at ON lead_stage_logs(created_at DESC)');

    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS lead_proposals (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    lead_id INTEGER NOT NULL,
    employee_id INTEGER NOT NULL,
    summary TEXT NOT NULL,
    estimate_amount REAL,
    document_name TEXT,
    document_mime TEXT,
    document_data TEXT,
    status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending','approved','rejected')),
    review_note TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now', '+330 minutes')),
    approved_at TEXT,
    approved_by INTEGER,
    FOREIGN KEY(lead_id) REFERENCES crm_leads(id) ON DELETE CASCADE,
    FOREIGN KEY(employee_id) REFERENCES users(id),
    FOREIGN KEY(approved_by) REFERENCES users(id)
)
SQL
    );
    $db->exec('CREATE INDEX IF NOT EXISTS idx_lead_proposals_lead ON lead_proposals(lead_id)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_lead_proposals_status ON lead_proposals(status)');
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
        $insert = $db->prepare("INSERT INTO portal_tasks(title, description, priority, status, due_date, linked_reference, created_at, updated_at) VALUES(:title, :description, :priority, :status, :due_date, :linked_reference, datetime('now', '+330 minutes'), datetime('now', '+330 minutes'))");
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
        $insertDoc = $db->prepare("INSERT INTO portal_documents(name, linked_to, reference, tags, url, version, visibility, uploaded_by, created_at, updated_at) VALUES(:name, :linked_to, :reference, :tags, :url, :version, :visibility, NULL, datetime('now', '+330 minutes'), datetime('now', '+330 minutes'))");
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
        $insertNotification = $db->prepare("INSERT INTO portal_notifications(audience, tone, icon, title, message, link, created_at) VALUES(:audience, :tone, :icon, :title, :message, :link, datetime('now', '+330 minutes'))");
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

    seed_installations($db);
}

function seed_installations(PDO $db): void
{
    $count = (int) $db->query('SELECT COUNT(*) FROM installations')->fetchColumn();
    if ($count > 0) {
        return;
    }

    $employeeId = (int) ($db->query("SELECT id FROM users WHERE LOWER(username) = 'employee' LIMIT 1")->fetchColumn() ?: 0);
    $installerId = (int) ($db->query("SELECT id FROM users WHERE LOWER(username) = 'installer' LIMIT 1")->fetchColumn() ?: 0);
    $employeeName = (string) ($db->query("SELECT full_name FROM users WHERE id = $employeeId")->fetchColumn() ?: 'Operations Coordinator');
    $installerName = (string) ($db->query("SELECT full_name FROM users WHERE id = $installerId")->fetchColumn() ?: 'Lead Installer');
    $adminId = (int) ($db->query("SELECT id FROM users WHERE LOWER(username) = 'admin' LIMIT 1")->fetchColumn() ?: 0);
    $adminName = (string) ($db->query("SELECT full_name FROM users WHERE id = $adminId")->fetchColumn() ?: 'Administrator');

    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata'));

    $entrySetOne = json_encode([
        [
            'id' => bin2hex(random_bytes(8)),
            'stage' => 'structure',
            'type' => 'stage',
            'remarks' => 'Mounting structure aligned and anchor bolts torqued.',
            'photo' => 'structure-east-wing.jpg',
            'actorId' => $employeeId ?: null,
            'actorName' => $employeeName,
            'actorRole' => 'Employee',
            'timestamp' => $now->modify('-4 days')->format('Y-m-d H:i:s'),
        ],
        [
            'id' => bin2hex(random_bytes(8)),
            'stage' => 'wiring',
            'type' => 'stage',
            'remarks' => 'DC string continuity verified on rooftop.',
            'photo' => 'string-test-report.pdf',
            'actorId' => $installerId ?: null,
            'actorName' => $installerName,
            'actorRole' => 'Installer',
            'timestamp' => $now->modify('-2 days')->format('Y-m-d H:i:s'),
        ],
    ], JSON_THROW_ON_ERROR);

    $entrySetTwo = json_encode([
        [
            'id' => bin2hex(random_bytes(8)),
            'stage' => 'structure',
            'type' => 'stage',
            'remarks' => 'Module mounting completed, fasteners inspected with torque wrench.',
            'photo' => 'mounting-bolts.jpg',
            'actorId' => $installerId ?: null,
            'actorName' => $installerName,
            'actorRole' => 'Installer',
            'timestamp' => $now->modify('-6 days')->format('Y-m-d H:i:s'),
        ],
        [
            'id' => bin2hex(random_bytes(8)),
            'stage' => 'testing',
            'type' => 'stage',
            'remarks' => 'Megger tests logged for DC and AC sides.',
            'photo' => 'megger-log.xlsx',
            'actorId' => $employeeId ?: null,
            'actorName' => $employeeName,
            'actorRole' => 'Employee',
            'timestamp' => $now->modify('-3 days')->format('Y-m-d H:i:s'),
        ],
        [
            'id' => bin2hex(random_bytes(8)),
            'stage' => 'meter',
            'type' => 'request',
            'remarks' => 'DISCOM meter installed, commissioning evidence shared.',
            'photo' => 'net-meter.jpg',
            'actorId' => $installerId ?: null,
            'actorName' => $installerName,
            'actorRole' => 'Installer',
            'timestamp' => $now->modify('-1 days')->format('Y-m-d H:i:s'),
        ],
    ], JSON_THROW_ON_ERROR);

    $entrySetThree = json_encode([
        [
            'id' => bin2hex(random_bytes(8)),
            'stage' => 'commissioned',
            'type' => 'stage',
            'remarks' => 'Handover and training completed with customer family.',
            'photo' => 'handover-checklist.pdf',
            'actorId' => $adminId ?: null,
            'actorName' => $adminName,
            'actorRole' => 'Admin',
            'timestamp' => $now->modify('-8 hours')->format('Y-m-d H:i:s'),
        ],
    ], JSON_THROW_ON_ERROR);

    $insert = $db->prepare("INSERT INTO installations(customer_name, project_reference, capacity_kw, status, stage, stage_entries, requested_stage, requested_by, requested_at, amc_committed, scheduled_date, handover_date, assigned_to, installer_id, created_at, updated_at)
        VALUES(:customer_name, :project_reference, :capacity_kw, :status, :stage, :stage_entries, :requested_stage, :requested_by, :requested_at, :amc_committed, :scheduled_date, :handover_date, :assigned_to, :installer_id, datetime('now', '+330 minutes'), datetime('now', '+330 minutes'))");

    $insert->execute([
        ':customer_name' => 'Asha Devi',
        ':project_reference' => 'INST-001',
        ':capacity_kw' => 6.5,
        ':status' => 'in_progress',
        ':stage' => 'testing',
        ':stage_entries' => $entrySetOne,
        ':requested_stage' => null,
        ':requested_by' => null,
        ':requested_at' => null,
        ':amc_committed' => 1,
        ':scheduled_date' => $now->modify('+2 days')->format('Y-m-d'),
        ':handover_date' => $now->modify('+7 days')->format('Y-m-d'),
        ':assigned_to' => $employeeId ?: null,
        ':installer_id' => $installerId ?: null,
    ]);

    $insert->execute([
        ':customer_name' => 'Rajesh Kumar',
        ':project_reference' => 'INST-004',
        ':capacity_kw' => 8.0,
        ':status' => 'in_progress',
        ':stage' => 'meter',
        ':stage_entries' => $entrySetTwo,
        ':requested_stage' => 'commissioned',
        ':requested_by' => $installerId ?: null,
        ':requested_at' => $now->modify('-1 hours')->format('Y-m-d H:i:s'),
        ':amc_committed' => 0,
        ':scheduled_date' => $now->modify('+1 days')->format('Y-m-d'),
        ':handover_date' => $now->modify('+3 days')->format('Y-m-d'),
        ':assigned_to' => $employeeId ?: null,
        ':installer_id' => $installerId ?: null,
    ]);

    $insert->execute([
        ':customer_name' => 'Sangeeta P.',
        ':project_reference' => 'INST-006',
        ':capacity_kw' => 5.2,
        ':status' => 'completed',
        ':stage' => 'commissioned',
        ':stage_entries' => $entrySetThree,
        ':requested_stage' => null,
        ':requested_by' => null,
        ':requested_at' => null,
        ':amc_committed' => 1,
        ':scheduled_date' => $now->modify('-15 days')->format('Y-m-d'),
        ':handover_date' => $now->modify('-8 days')->format('Y-m-d'),
        ':assigned_to' => $employeeId ?: null,
        ':installer_id' => $installerId ?: null,
    ]);
}

function merge_employee_roles(PDO $db): void
{
    $aliases = ['employee', 'installer', 'referrer', 'staff', 'team', 'field', 'agent', 'technician', 'support'];
    $roleStmt = $db->prepare('SELECT id FROM roles WHERE LOWER(name) = LOWER(:name) LIMIT 1');

    $roleStmt->execute([':name' => 'employee']);
    $employeeRoleId = $roleStmt->fetchColumn();
    if ($employeeRoleId === false) {
        $db->prepare('INSERT INTO roles(name, description) VALUES(:name, :description)')->execute([
            ':name' => 'employee',
            ':description' => 'Internal staff managing operations and service.',
        ]);
        $roleStmt->execute([':name' => 'employee']);
        $employeeRoleId = $roleStmt->fetchColumn();
    }

    if ($employeeRoleId === false) {
        return;
    }

    $employeeRoleId = (int) $employeeRoleId;
    foreach ($aliases as $alias) {
        if ($alias === 'employee') {
            continue;
        }
        $roleStmt->execute([':name' => $alias]);
        $legacyRoleId = $roleStmt->fetchColumn();
        if ($legacyRoleId === false) {
            continue;
        }
        $legacyRoleId = (int) $legacyRoleId;
        $db->prepare('UPDATE users SET role_id = :employee_role WHERE role_id = :legacy_role')->execute([
            ':employee_role' => $employeeRoleId,
            ':legacy_role' => $legacyRoleId,
        ]);
        $db->prepare('UPDATE invitations SET role_id = :employee_role WHERE role_id = :legacy_role')->execute([
            ':employee_role' => $employeeRoleId,
            ':legacy_role' => $legacyRoleId,
        ]);
        $db->prepare('DELETE FROM roles WHERE id = :id')->execute([':id' => $legacyRoleId]);
    }
}

function now_ist(): string
{
    $now = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
    return $now->format('Y-m-d H:i:s');
}

function canonical_role_name(string $roleName): string
{
    $normalized = strtolower(trim($roleName));
    $map = [
        'referrer' => 'employee',
        'staff' => 'employee',
        'team' => 'employee',
        'field' => 'employee',
        'agent' => 'employee',
        'technician' => 'employee',
        'support' => 'employee',
    ];

    if (isset($map[$normalized])) {
        return $map[$normalized];
    }

    return $normalized;
}

function portal_role_label(string $roleName): string
{
    $canonical = canonical_role_name($roleName);
    if ($canonical === 'employee') {
        return 'Employee';
    }

    return ucfirst($canonical);
}

function portal_find_user(PDO $db, int $id): ?array
{
    $stmt = $db->prepare('SELECT users.*, roles.name AS role_name FROM users INNER JOIN roles ON users.role_id = roles.id WHERE users.id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function portal_ensure_employee(PDO $db, int $id): array
{
    $user = portal_find_user($db, $id);
    if (!$user) {
        throw new RuntimeException('Employee not found.');
    }

    if (canonical_role_name($user['role_name'] ?? '') !== 'employee') {
        throw new RuntimeException('Assignee must be an employee.');
    }

    if (($user['status'] ?? 'inactive') !== 'active') {
        throw new RuntimeException('Employee must be active.');
    }

    return $user;
}

function portal_list_team(PDO $db): array
{
    $stmt = $db->query("SELECT users.id, users.full_name, users.email, users.permissions_note, roles.name AS role_name FROM users INNER JOIN roles ON users.role_id = roles.id WHERE roles.name IN ('employee','installer','admin') ORDER BY roles.name, users.full_name");
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

    $normalized = portal_normalize_task_row($row);
    portal_generate_task_notifications($db, $normalized);

    return $normalized;
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

    $normalized = portal_normalize_task_row($row);
    portal_generate_task_notifications($db, $normalized);

    return $normalized;
}

function portal_generate_task_notifications(PDO $db, array $task): void
{
    $assigneeId = isset($task['assigneeId']) && $task['assigneeId'] !== '' ? (int) $task['assigneeId'] : null;
    if ($assigneeId === null || $assigneeId <= 0) {
        return;
    }

    $dueDate = $task['dueDate'] ?? '';
    if ($dueDate === '') {
        return;
    }

    $due = DateTimeImmutable::createFromFormat('Y-m-d', $dueDate, new DateTimeZone('Asia/Kolkata'));
    if (!$due) {
        return;
    }

    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata'));
    $diffDays = (int) $now->diff($due)->format('%r%a');
    $link = '#task-' . $task['id'];

    $check = $db->prepare('SELECT id FROM portal_notifications WHERE link = :link AND scope_user_id = :scope_user_id LIMIT 1');
    $check->execute([
        ':link' => $link,
        ':scope_user_id' => $assigneeId,
    ]);
    $existing = $check->fetchColumn();

    if ($diffDays > 1) {
        if ($existing !== false) {
            portal_mark_notification($db, (int) $existing, $assigneeId, 'dismissed');
        }
        return;
    }

    $tone = $diffDays < 0 ? 'danger' : 'warning';
    $title = $diffDays < 0 ? 'Task overdue' : 'Task due soon';
    $message = $diffDays < 0
        ? sprintf('%s is overdue by %d day%s.', $task['title'], abs($diffDays), abs($diffDays) === 1 ? '' : 's')
        : sprintf('%s is due within %d day%s.', $task['title'], max(0, $diffDays), $diffDays === 1 ? '' : 's');

    if ($existing !== false) {
        $db->prepare('UPDATE portal_notifications SET tone = :tone, title = :title, message = :message, created_at = :created_at WHERE id = :id')->execute([
            ':tone' => $tone,
            ':title' => $title,
            ':message' => $message,
            ':created_at' => now_ist(),
            ':id' => (int) $existing,
        ]);
        $notificationId = (int) $existing;
    } else {
        $db->prepare('INSERT INTO portal_notifications(audience, tone, icon, title, message, link, scope_user_id, created_at) VALUES(\'employee\', :tone, :icon, :title, :message, :link, :scope_user_id, :created_at)')->execute([
            ':tone' => $tone,
            ':icon' => 'fa-solid fa-list-check',
            ':title' => $title,
            ':message' => $message,
            ':link' => $link,
            ':scope_user_id' => $assigneeId,
            ':created_at' => now_ist(),
        ]);
        $notificationId = (int) $db->lastInsertId();
    }

    portal_mark_notification($db, $notificationId, $assigneeId, 'unread');
}

function portal_list_documents(PDO $db, string $audience = 'admin', ?int $userId = null): array
{
    if (!in_array($audience, ['employee', 'admin'], true)) {
        $audience = 'admin';
    }

    if ($audience === 'admin') {
        $stmt = $db->query('SELECT portal_documents.*, users.full_name AS uploaded_by_name FROM portal_documents LEFT JOIN users ON portal_documents.uploaded_by = users.id ORDER BY portal_documents.updated_at DESC');
        return portal_normalize_documents($stmt->fetchAll());
    }

    $sql = 'SELECT portal_documents.*, users.full_name AS uploaded_by_name FROM portal_documents LEFT JOIN users ON portal_documents.uploaded_by = users.id WHERE portal_documents.visibility IN (\'employee\', \'both\')';
    $params = [];

    if ($userId !== null) {
        $scope = portal_employee_document_scope($db, $userId);
        $conditions = [];
        $params[':user_id'] = $userId;
        $conditions[] = 'portal_documents.uploaded_by = :user_id';

        $index = 0;
        foreach ($scope['tickets'] as $reference) {
            $key = ':ticket_ref_' . $index++;
            $conditions[] = "(portal_documents.linked_to = 'ticket' AND portal_documents.reference = $key)";
            $params[$key] = $reference;
        }
        foreach ($scope['customers'] as $customer) {
            $key = ':customer_ref_' . $index++;
            $conditions[] = "(portal_documents.linked_to IN ('customer','operations') AND portal_documents.reference = $key)";
            $params[$key] = $customer;
        }
        foreach ($scope['tasks'] as $taskReference) {
            $key = ':task_ref_' . $index++;
            $conditions[] = "(portal_documents.linked_to = 'task' AND portal_documents.reference = $key)";
            $params[$key] = $taskReference;
        }

        if (!empty($conditions)) {
            $sql .= ' AND (' . implode(' OR ', $conditions) . ')';
        }
    }

    $sql .= ' ORDER BY portal_documents.updated_at DESC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

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

function portal_employee_document_scope(PDO $db, int $userId): array
{
    $tickets = [];
    $customers = [];
    $tasks = [];

    $stmt = $db->prepare('SELECT reference FROM complaints WHERE assigned_to = :user_id');
    $stmt->execute([':user_id' => $userId]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $reference) {
        if ($reference === null) {
            continue;
        }
        $tickets[] = (string) $reference;
        $customers[] = (string) $reference;
    }

    $taskStmt = $db->prepare('SELECT DISTINCT linked_reference FROM portal_tasks WHERE assignee_id = :user_id AND linked_reference IS NOT NULL AND linked_reference != \'\'');
    $taskStmt->execute([':user_id' => $userId]);
    foreach ($taskStmt->fetchAll(PDO::FETCH_COLUMN) as $reference) {
        if ($reference === null) {
            continue;
        }
        $tasks[] = (string) $reference;
        $customers[] = (string) $reference;
    }

    return [
        'tickets' => array_values(array_unique($tickets)),
        'customers' => array_values(array_unique($customers)),
        'tasks' => array_values(array_unique($tasks)),
    ];
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

function portal_employee_submit_document(PDO $db, array $input, int $actorId): array
{
    $customer = trim((string) ($input['customer'] ?? ''));
    $type = trim((string) ($input['type'] ?? ''));
    $filename = trim((string) ($input['filename'] ?? ''));
    $note = trim((string) ($input['note'] ?? ''));
    $fileSizeRaw = trim((string) ($input['fileSize'] ?? ($input['file_size'] ?? '')));

    if ($customer === '' || $type === '' || $filename === '') {
        throw new RuntimeException('Customer, document type, and file name are required.');
    }

    $scope = portal_employee_document_scope($db, $actorId);
    $allowedReferences = array_merge($scope['tickets'], $scope['customers']);
    if (!in_array($customer, $allowedReferences, true)) {
        throw new RuntimeException('You can only upload documents for your assigned tickets or customers.');
    }

    $tags = [];
    if ($note !== '') {
        $tags[] = $note;
    }
    if ($fileSizeRaw !== '' && is_numeric($fileSizeRaw)) {
        $size = max(0, (float) $fileSizeRaw);
        $tags[] = sprintf('filesize:%0.1fMB', $size);
    }

    $document = portal_save_document($db, [
        'name' => $type,
        'linkedTo' => 'customer',
        'reference' => $customer,
        'tags' => $tags,
        'url' => $input['url'] ?? '',
        'visibility' => 'employee',
    ], $actorId);

    $complaintId = portal_find_complaint_id($db, $customer);
    if ($complaintId !== null) {
        $summary = sprintf('%s uploaded (%s)', $type, $filename);
        $details = $note !== '' ? $note : null;
        portal_record_complaint_event($db, $complaintId, $actorId, 'document', $summary, $details, $document['id']);
        $db->prepare('UPDATE complaints SET updated_at = :updated_at WHERE id = :id')->execute([
            ':updated_at' => now_ist(),
            ':id' => $complaintId,
        ]);
    }

    return $document;
}

function portal_list_notifications(PDO $db, int $userId, string $audience = 'employee'): array
{
    $stmt = $db->prepare('SELECT n.*, IFNULL(s.status, \'unread\') AS read_status FROM portal_notifications n LEFT JOIN portal_notification_status s ON n.id = s.notification_id AND s.user_id = :user_id WHERE n.audience IN (\'all\', :audience) AND IFNULL(s.status, \'unread\') != \'dismissed\' AND (n.scope_user_id IS NULL OR n.scope_user_id = :user_id) ORDER BY n.created_at DESC');
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

function portal_fetch_complaint_updates(PDO $db, array $complaintIds): array
{
    if (empty($complaintIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($complaintIds), '?'));
    $stmt = $db->prepare("SELECT cu.*, users.full_name AS actor_name, portal_documents.name AS document_name, portal_documents.reference AS document_reference FROM complaint_updates cu LEFT JOIN users ON cu.actor_id = users.id LEFT JOIN portal_documents ON cu.document_id = portal_documents.id WHERE cu.complaint_id IN ($placeholders) ORDER BY cu.created_at ASC");
    $stmt->execute(array_map('intval', $complaintIds));

    $grouped = [];
    foreach ($stmt->fetchAll() as $row) {
        $complaintId = (int) $row['complaint_id'];
        $entry = [
            'type' => $row['entry_type'] ?? 'note',
            'summary' => $row['summary'] ?? '',
            'details' => $row['details'] ?? '',
            'status' => $row['status_to'] ?? '',
            'actor' => $row['actor_name'] ?? 'System',
            'time' => $row['created_at'] ?? '',
        ];
        if (!empty($row['document_id'])) {
            $entry['document'] = [
                'id' => (int) $row['document_id'],
                'name' => $row['document_name'] ?? '',
                'reference' => $row['document_reference'] ?? '',
            ];
        }
        $grouped[$complaintId][] = $entry;
    }

    return $grouped;
}

function portal_normalize_complaint_rows(PDO $db, array $rows): array
{
    if (empty($rows)) {
        return [];
    }

    $complaintIds = [];
    foreach ($rows as $row) {
        if (isset($row['id'])) {
            $complaintIds[] = (int) $row['id'];
        }
    }

    $timelines = portal_fetch_complaint_updates($db, $complaintIds);
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata'));
    $results = [];

    foreach ($rows as $row) {
        $id = (int) ($row['id'] ?? 0);
        $slaDueRaw = $row['sla_due_at'] ?? '';
        $slaStatus = 'unset';
        $slaLabel = 'SLA not set';
        $slaDueFormatted = $slaDueRaw ?: '';
        if ($slaDueRaw) {
            try {
                $due = new DateTimeImmutable($slaDueRaw, new DateTimeZone('Asia/Kolkata'));
                $diffDays = (int) $now->diff($due)->format('%r%a');
                if ($diffDays < 0) {
                    $slaStatus = 'overdue';
                    $slaLabel = sprintf('Overdue by %d day%s', abs($diffDays), abs($diffDays) === 1 ? '' : 's');
                } elseif ($diffDays <= 1) {
                    $slaStatus = 'due_soon';
                    $slaLabel = sprintf('Due in %d day%s', $diffDays, $diffDays === 1 ? '' : 's');
                } else {
                    $slaStatus = 'on_track';
                    $slaLabel = sprintf('Due in %d days', $diffDays);
                }
                $slaDueFormatted = $due->format('Y-m-d');
            } catch (Throwable $exception) {
                unset($exception);
            }
        }

        $createdAtRaw = $row['created_at'] ?? '';
        $ageDays = null;
        if ($createdAtRaw) {
            try {
                $created = new DateTimeImmutable($createdAtRaw, new DateTimeZone('Asia/Kolkata'));
                $ageDays = (int) $created->diff($now)->format('%a');
            } catch (Throwable $exception) {
                unset($exception);
            }
        }

        $results[] = [
            'id' => $id,
            'reference' => (string) ($row['reference'] ?? ''),
            'title' => (string) ($row['title'] ?? ''),
            'description' => $row['description'] ?? '',
            'priority' => $row['priority'] ?? 'medium',
            'status' => $row['status'] ?? 'intake',
            'assignedTo' => $row['assigned_to'] !== null ? (int) $row['assigned_to'] : null,
            'assigneeName' => $row['assigned_to_name'] ?? '',
            'assigneeRole' => isset($row['assigned_role']) && $row['assigned_role'] !== null ? portal_role_label((string) $row['assigned_role']) : '',
            'slaDue' => $slaDueFormatted,
            'slaStatus' => $slaStatus,
            'slaLabel' => $slaLabel,
            'createdAt' => $row['created_at'] ?? '',
            'updatedAt' => $row['updated_at'] ?? '',
            'timeline' => $timelines[$id] ?? [],
            'ageDays' => $ageDays,
        ];
    }

    return $results;
}

function portal_employee_complaints(PDO $db, int $userId): array
{
    $stmt = $db->prepare('SELECT complaints.*, users.full_name AS assigned_to_name, roles.name AS assigned_role FROM complaints LEFT JOIN users ON complaints.assigned_to = users.id LEFT JOIN roles ON users.role_id = roles.id WHERE complaints.assigned_to = :user_id ORDER BY complaints.created_at DESC');
    $stmt->execute([':user_id' => $userId]);
    return portal_normalize_complaint_rows($db, $stmt->fetchAll());
}

function portal_all_complaints(PDO $db): array
{
    $stmt = $db->query('SELECT complaints.*, users.full_name AS assigned_to_name, roles.name AS assigned_role FROM complaints LEFT JOIN users ON complaints.assigned_to = users.id LEFT JOIN roles ON users.role_id = roles.id ORDER BY complaints.created_at DESC');
    return portal_normalize_complaint_rows($db, $stmt->fetchAll());
}

function admin_overview_counts(PDO $db): array
{
    $activeEmployees = (int) $db->query("SELECT COUNT(*) FROM users INNER JOIN roles ON users.role_id = roles.id WHERE roles.name = 'employee' AND users.status = 'active'")->fetchColumn();
    $newLeads = (int) $db->query("SELECT COUNT(*) FROM crm_leads WHERE status = 'new'")->fetchColumn();
    $activeInstallations = (int) $db->query("SELECT COUNT(*) FROM installations WHERE stage != 'commissioned' AND status NOT IN ('cancelled')")->fetchColumn();
    $openComplaints = (int) $db->query("SELECT COUNT(*) FROM complaints WHERE status IN ('intake','triage','work')")->fetchColumn();
    $pendingSubsidy = (int) $db->query("SELECT COUNT(*) FROM subsidy_applications WHERE status IN ('pending','submitted')")->fetchColumn();

    return [
        'employees' => $activeEmployees,
        'leads' => $newLeads,
        'installations' => $activeInstallations,
        'complaints' => $openComplaints,
        'subsidy' => $pendingSubsidy,
    ];
}

function admin_today_highlights(PDO $db, int $limit = 12): array
{
    $entries = [];

    $addEntry = static function (?string $timestamp, string $module, string $summary, array $context = []) use (&$entries): void {
        if ($timestamp === null || trim($timestamp) === '') {
            return;
        }

        $parsed = strtotime($timestamp);
        if ($parsed === false) {
            return;
        }

        $entries[] = [
            'module' => $module,
            'summary' => $summary,
            'timestamp' => $timestamp,
            'sort_key' => $parsed,
            'context' => $context,
        ];
    };

    $leadStmt = $db->query('SELECT id, name, status, updated_at, created_at FROM crm_leads ORDER BY COALESCE(updated_at, created_at) DESC LIMIT 25');
    foreach ($leadStmt->fetchAll() as $row) {
        $status = lead_status_label($row['status'] ?? '');
        $title = sprintf('Lead "%s" is %s', $row['name'], strtolower($status));
        $addEntry($row['updated_at'] ?: $row['created_at'], 'leads', $title, [
            'id' => (int) $row['id'],
            'status' => $status,
        ]);
    }

    $installationStmt = $db->query('SELECT id, customer_name, project_reference, stage, requested_stage, updated_at, created_at FROM installations ORDER BY COALESCE(updated_at, created_at) DESC LIMIT 25');
    foreach ($installationStmt->fetchAll() as $row) {
        $stage = strtolower(trim((string) ($row['stage'] ?? 'structure')));
        $stageLabel = installation_stage_label($stage);
        $name = $row['project_reference'] ?: $row['customer_name'];
        $requested = strtolower(trim((string) ($row['requested_stage'] ?? '')));
        if ($requested === 'commissioned') {
            $title = sprintf('Installation %s pending commissioning approval', $name);
        } else {
            $title = sprintf('Installation %s now %s', $name, strtolower($stageLabel));
        }
        $addEntry($row['updated_at'] ?: $row['created_at'], 'installations', $title, [
            'id' => (int) $row['id'],
            'status' => $requested === 'commissioned' ? 'Commissioning pending' : $stageLabel,
        ]);
    }

    $complaintStmt = $db->query('SELECT id, reference, status, updated_at, created_at FROM complaints ORDER BY COALESCE(updated_at, created_at) DESC LIMIT 25');
    foreach ($complaintStmt->fetchAll() as $row) {
        $status = complaint_status_label($row['status'] ?? '');
        $title = sprintf('Complaint %s moved to %s', $row['reference'], strtolower($status));
        $addEntry($row['updated_at'] ?: $row['created_at'], 'complaints', $title, [
            'id' => (int) $row['id'],
            'status' => $status,
        ]);
    }

    $subsidyStmt = $db->query('SELECT id, application_number, customer_name, status, updated_at, created_at FROM subsidy_applications ORDER BY COALESCE(updated_at, created_at) DESC LIMIT 25');
    foreach ($subsidyStmt->fetchAll() as $row) {
        $status = subsidy_status_label($row['status'] ?? '');
        $label = $row['application_number'] ?: $row['customer_name'];
        $title = sprintf('Subsidy %s marked %s', $label, strtolower($status));
        $addEntry($row['updated_at'] ?: $row['created_at'], 'subsidy', $title, [
            'id' => (int) $row['id'],
            'status' => $status,
        ]);
    }

    $reminderStmt = $db->query('SELECT id, title, status, module, due_on, updated_at, created_at FROM reminders ORDER BY COALESCE(updated_at, created_at) DESC LIMIT 25');
    foreach ($reminderStmt->fetchAll() as $row) {
        $status = reminder_status_label($row['status'] ?? '');
        $due = $row['due_on'] ? (' due ' . format_due_date($row['due_on'])) : '';
        $title = sprintf('Reminder "%s" is %s%s', $row['title'], strtolower($status), $due);
        $addEntry($row['updated_at'] ?: $row['created_at'], 'reminders', $title, [
            'id' => (int) $row['id'],
            'status' => $status,
            'module' => $row['module'] ?? '',
        ]);
    }

    if (count($entries) === 0) {
        return [];
    }

    usort($entries, static function (array $a, array $b): int {
        return $b['sort_key'] <=> $a['sort_key'];
    });

    $sliced = array_slice($entries, 0, $limit);

    return array_map(static function (array $item): array {
        $timestamp = new DateTimeImmutable($item['timestamp']);
        return [
            'module' => $item['module'],
            'summary' => $item['summary'],
            'timestamp' => $timestamp->format(DateTimeInterface::ATOM),
            'context' => $item['context'],
        ];
    }, $sliced);
}

function installation_stage_keys(): array
{
    return ['structure', 'wiring', 'testing', 'meter', 'commissioned'];
}

function installation_stage_label(string $stage): string
{
    $map = [
        'structure' => 'Structure',
        'wiring' => 'Wiring',
        'testing' => 'Testing',
        'meter' => 'Meter',
        'commissioned' => 'Commissioned',
    ];

    $normalized = strtolower(trim($stage));

    return $map[$normalized] ?? ucfirst($normalized ?: 'Structure');
}

function installation_stage_index(string $stage): int
{
    $stages = installation_stage_keys();
    $normalized = strtolower(trim($stage));
    $index = array_search($normalized, $stages, true);

    return $index === false ? 0 : (int) $index;
}

function installation_stage_progress(string $stage): array
{
    $currentIndex = installation_stage_index($stage);
    $steps = [];

    foreach (installation_stage_keys() as $index => $key) {
        $steps[] = [
            'key' => $key,
            'label' => installation_stage_label($key),
            'state' => $index < $currentIndex ? 'done' : ($index === $currentIndex ? 'current' : 'upcoming'),
        ];
    }

    return $steps;
}

function installation_stage_tone(string $stage): string
{
    return match (strtolower(trim($stage))) {
        'commissioned' => 'success',
        'meter' => 'positive',
        'testing' => 'warning',
        'wiring' => 'info',
        default => 'progress',
    };
}

function installation_decode_stage_entries(?string $json): array
{
    if (!is_string($json) || trim($json) === '') {
        return [];
    }

    try {
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $exception) {
        return [];
    }

    if (!is_array($decoded)) {
        return [];
    }

    $entries = [];
    foreach ($decoded as $item) {
        if (!is_array($item)) {
            continue;
        }

        $stage = strtolower(trim((string) ($item['stage'] ?? 'structure')));
        $entries[] = [
            'id' => (string) ($item['id'] ?? ''),
            'stage' => $stage,
            'stageLabel' => installation_stage_label($stage),
            'type' => (string) ($item['type'] ?? 'note'),
            'remarks' => (string) ($item['remarks'] ?? ''),
            'photo' => (string) ($item['photo'] ?? ''),
            'actorId' => isset($item['actorId']) ? (int) $item['actorId'] : null,
            'actorName' => (string) ($item['actorName'] ?? ''),
            'actorRole' => (string) ($item['actorRole'] ?? ''),
            'timestamp' => (string) ($item['timestamp'] ?? ''),
        ];
    }

    usort($entries, static fn (array $a, array $b): int => strcmp($a['timestamp'], $b['timestamp']));

    return $entries;
}

function installation_actor_details(PDO $db, int $actorId): array
{
    $user = portal_find_user($db, $actorId);
    if (!$user) {
        return [
            'id' => $actorId,
            'name' => 'User #' . $actorId,
            'role' => 'User',
        ];
    }

    $roleName = (string) ($user['role_name'] ?? '');

    return [
        'id' => (int) $user['id'],
        'name' => (string) ($user['full_name'] ?? ('User #' . $actorId)),
        'role' => portal_role_label($roleName),
    ];
}

function installation_append_stage_entry(PDO $db, array $row, array $entry): array
{
    $entries = installation_decode_stage_entries($row['stage_entries'] ?? '[]');
    $entries[] = $entry;

    $encoded = json_encode($entries, JSON_THROW_ON_ERROR);
    $timestamp = now_ist();

    $db->prepare('UPDATE installations SET stage_entries = :entries, updated_at = :updated_at WHERE id = :id')->execute([
        ':entries' => $encoded,
        ':updated_at' => $timestamp,
        ':id' => (int) $row['id'],
    ]);

    $row['stage_entries'] = $encoded;
    $row['updated_at'] = $timestamp;

    return $row;
}

function installation_stage_max_for_role(string $role): string
{
    $canonical = strtolower(trim($role));

    return match ($canonical) {
        'admin' => 'commissioned',
        default => 'meter',
    };
}

function installation_role_can_finalize(string $role): bool
{
    return strtolower(trim($role)) === 'admin';
}

function installation_fetch(PDO $db, int $id): array
{
    $stmt = $db->prepare('SELECT installations.*, emp.full_name AS employee_name, inst.full_name AS installer_name, req.full_name AS requested_by_name FROM installations
        LEFT JOIN users emp ON installations.assigned_to = emp.id
        LEFT JOIN users inst ON installations.installer_id = inst.id
        LEFT JOIN users req ON installations.requested_by = req.id
        WHERE installations.id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new RuntimeException('Installation not found.');
    }

    return $row;
}

function installation_stage_transition_allowed(string $role, string $currentStage, string $targetStage): bool
{
    $currentIndex = installation_stage_index($currentStage);
    $targetIndex = installation_stage_index($targetStage);
    $maxIndex = installation_stage_index(installation_stage_max_for_role($role));

    if ($targetIndex > $maxIndex) {
        return false;
    }

    if ($targetIndex < $currentIndex && strtolower(trim($role)) !== 'admin') {
        return false;
    }

    return true;
}

function installation_stage_requires_approval(string $role, string $targetStage): bool
{
    if (installation_role_can_finalize($role)) {
        return false;
    }

    return strtolower(trim($targetStage)) === 'commissioned';
}

function installation_update_stage(
    PDO $db,
    int $installationId,
    string $targetStage,
    int $actorId,
    string $actorRole,
    string $remarks = '',
    string $photoLabel = ''
): array {
    $row = installation_fetch($db, $installationId);
    $currentStage = strtolower(trim((string) ($row['stage'] ?? 'structure')));
    $targetStage = strtolower(trim($targetStage));

    if (!in_array($targetStage, installation_stage_keys(), true)) {
        throw new RuntimeException('Unsupported installation stage.');
    }

    $actor = installation_actor_details($db, $actorId);
    $now = now_ist();

    $logId = bin2hex(random_bytes(8));
    $entry = [
        'id' => $logId,
        'stage' => $targetStage,
        'type' => 'note',
        'remarks' => $remarks,
        'photo' => $photoLabel,
        'actorId' => $actor['id'],
        'actorName' => $actor['name'],
        'actorRole' => $actor['role'],
        'timestamp' => $now,
    ];

    $requiresApproval = installation_stage_requires_approval($actorRole, $targetStage);

    if ($requiresApproval) {
        $db->prepare('UPDATE installations SET requested_stage = :requested_stage, requested_by = :requested_by, requested_at = :requested_at, updated_at = :updated_at WHERE id = :id')->execute([
            ':requested_stage' => $targetStage,
            ':requested_by' => $actor['id'],
            ':requested_at' => $now,
            ':updated_at' => $now,
            ':id' => $installationId,
        ]);

        $entry['type'] = 'request';
        $row['requested_stage'] = $targetStage;
        $row['requested_by'] = $actor['id'];
        $row['requested_at'] = $now;
    } elseif ($targetStage !== $currentStage) {
        if (!installation_stage_transition_allowed($actorRole, $currentStage, $targetStage)) {
            throw new RuntimeException('Stage change is not permitted.');
        }

        $status = $targetStage === 'commissioned' ? 'completed' : 'in_progress';

        $db->prepare('UPDATE installations SET stage = :stage, status = :status, requested_stage = NULL, requested_by = NULL, requested_at = NULL, updated_at = :updated_at WHERE id = :id')->execute([
            ':stage' => $targetStage,
            ':status' => $status,
            ':updated_at' => $now,
            ':id' => $installationId,
        ]);

        $row['stage'] = $targetStage;
        $row['status'] = $status;
        $row['requested_stage'] = null;
        $row['requested_by'] = null;
        $row['requested_at'] = null;
        $entry['type'] = 'stage';
    }

    if ($remarks !== '' || $photoLabel !== '' || $entry['type'] !== 'note') {
        $row = installation_append_stage_entry($db, $row, $entry);
    }

    return $row;
}

function installation_approve_commissioning(PDO $db, int $installationId, int $actorId, string $remarks = ''): array
{
    $row = installation_fetch($db, $installationId);
    $requestedStage = strtolower(trim((string) ($row['requested_stage'] ?? '')));
    if ($requestedStage !== 'commissioned') {
        throw new RuntimeException('No commissioning request is pending.');
    }

    return installation_update_stage($db, $installationId, 'commissioned', $actorId, 'admin', $remarks);
}

function installation_toggle_amc(PDO $db, int $installationId, bool $committed, int $actorId): array
{
    $row = installation_fetch($db, $installationId);
    $current = (int) ($row['amc_committed'] ?? 0) === 1;
    if ($current === $committed) {
        return $row;
    }

    $db->prepare('UPDATE installations SET amc_committed = :amc, updated_at = :updated_at WHERE id = :id')->execute([
        ':amc' => $committed ? 1 : 0,
        ':updated_at' => now_ist(),
        ':id' => $installationId,
    ]);

    $row['amc_committed'] = $committed ? 1 : 0;

    $actor = installation_actor_details($db, $actorId);
    $entry = [
        'id' => bin2hex(random_bytes(8)),
        'stage' => strtolower(trim((string) ($row['stage'] ?? 'structure'))),
        'type' => 'amc',
        'remarks' => $committed ? 'AMC commitment confirmed.' : 'AMC commitment removed.',
        'photo' => '',
        'actorId' => $actor['id'],
        'actorName' => $actor['name'],
        'actorRole' => $actor['role'],
        'timestamp' => now_ist(),
    ];

    installation_append_stage_entry($db, $row, $entry);

    return $row;
}

function installation_normalize_row(PDO $db, array $row, string $role = 'admin'): array
{
    $stage = strtolower(trim((string) ($row['stage'] ?? 'structure')));
    $entries = installation_decode_stage_entries($row['stage_entries'] ?? '[]');
    $progress = installation_stage_progress($stage);
    $requestedStage = strtolower(trim((string) ($row['requested_stage'] ?? '')));

    $maxStage = installation_stage_max_for_role($role);
    $maxIndex = installation_stage_index($maxStage);
    $currentIndex = installation_stage_index($stage);

    $stageOptions = [];
    $hasCurrentStage = false;
    $hasCommissionedOption = false;
    $roleKey = strtolower(trim($role));
    $commissionIndex = installation_stage_index('commissioned');

    foreach (installation_stage_keys() as $key) {
        $index = installation_stage_index($key);
        if ($index < $currentIndex && $roleKey !== 'admin') {
            continue;
        }
        if ($index > $maxIndex && $key !== $stage) {
            continue;
        }

        $isCurrent = $key === $stage;
        $isLocked = $requestedStage !== '' && $requestedStage !== $stage && $roleKey !== 'admin';

        $stageOptions[] = [
            'value' => $key,
            'label' => installation_stage_label($key),
            'disabled' => $isLocked && !$isCurrent,
        ];

        if ($isCurrent) {
            $hasCurrentStage = true;
        }

        if ($key === 'commissioned') {
            $hasCommissionedOption = true;
        }
    }

    if (!$hasCurrentStage) {
        $stageOptions[] = [
            'value' => $stage,
            'label' => installation_stage_label($stage),
            'disabled' => false,
        ];
    }

    $canRequestCommissioning = $roleKey !== 'admin'
        && $currentIndex < $commissionIndex
        && ($requestedStage === '' || $requestedStage === $stage);

    if ($canRequestCommissioning && !$hasCommissionedOption) {
        $stageOptions[] = [
            'value' => 'commissioned',
            'label' => 'Request commissioning approval',
            'disabled' => false,
        ];
        $hasCommissionedOption = true;
    }

    return [
        'id' => (int) $row['id'],
        'customer' => (string) ($row['customer_name'] ?? ''),
        'project' => (string) ($row['project_reference'] ?? ''),
        'capacity' => isset($row['capacity_kw']) ? (float) $row['capacity_kw'] : null,
        'stage' => $stage,
        'stageLabel' => installation_stage_label($stage),
        'stageTone' => installation_stage_tone($stage),
        'progress' => $progress,
        'entries' => $entries,
        'amcCommitted' => (int) ($row['amc_committed'] ?? 0) === 1,
        'scheduled' => (string) ($row['scheduled_date'] ?? ''),
        'handover' => (string) ($row['handover_date'] ?? ''),
        'employeeName' => (string) ($row['employee_name'] ?? ''),
        'installerName' => (string) ($row['installer_name'] ?? ''),
        'requestedStage' => $requestedStage,
        'requestedByName' => (string) ($row['requested_by_name'] ?? ''),
        'stageOptions' => $stageOptions,
        'stageLocked' => $requestedStage !== '' && $requestedStage !== $stage,
        'updated' => (string) ($row['updated_at'] ?? ''),
        'created' => (string) ($row['created_at'] ?? ''),
    ];
}

function installation_list_for_role(PDO $db, string $role, ?int $userId = null): array
{
    $roleKey = strtolower(trim($role));
    $params = [];
    $conditions = [];

    if ($roleKey === 'employee' && $userId) {
        $conditions[] = 'installations.assigned_to = :user_id';
        $params[':user_id'] = $userId;
    } elseif ($roleKey === 'installer' && $userId) {
        $conditions[] = 'installations.installer_id = :user_id';
        $params[':user_id'] = $userId;
    }

    $sql = 'SELECT installations.*, emp.full_name AS employee_name, inst.full_name AS installer_name, req.full_name AS requested_by_name FROM installations
        LEFT JOIN users emp ON installations.assigned_to = emp.id
        LEFT JOIN users inst ON installations.installer_id = inst.id
        LEFT JOIN users req ON installations.requested_by = req.id';

    if ($conditions) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }

    $sql .= ' ORDER BY CASE WHEN installations.stage = "commissioned" THEN 1 ELSE 0 END, COALESCE(installations.updated_at, installations.created_at) DESC';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $record) {
        $rows[] = installation_normalize_row($db, $record, $roleKey);
    }

    return $rows;
}

function installation_admin_filter(PDO $db, string $filter): array
{
    $filter = strtolower(trim($filter));
    $params = [];
    $conditions = [];

    switch ($filter) {
        case 'structure':
        case 'wiring':
        case 'testing':
        case 'meter':
        case 'commissioned':
            $conditions[] = 'installations.stage = :stage';
            $params[':stage'] = $filter;
            break;
        case 'on_hold':
        case 'cancelled':
            $conditions[] = 'installations.status = :status';
            $params[':status'] = $filter;
            break;
        case 'pending_commissioned':
            $conditions[] = "installations.requested_stage = 'commissioned'";
            break;
        case 'ongoing':
            $conditions[] = "installations.stage != 'commissioned'";
            $conditions[] = "installations.status NOT IN ('cancelled')";
            break;
        case 'all':
        default:
            break;
    }

    $sql = 'SELECT installations.*, emp.full_name AS employee_name, inst.full_name AS installer_name, req.full_name AS requested_by_name FROM installations
        LEFT JOIN users emp ON installations.assigned_to = emp.id
        LEFT JOIN users inst ON installations.installer_id = inst.id
        LEFT JOIN users req ON installations.requested_by = req.id';

    if ($conditions) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }

    $sql .= ' ORDER BY CASE WHEN installations.stage = "commissioned" THEN 1 ELSE 0 END, COALESCE(installations.updated_at, installations.created_at) DESC';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $record) {
        $rows[] = installation_normalize_row($db, $record, 'admin');
    }

    return $rows;
}

function format_due_date(string $date): string
{
    try {
        $dt = new DateTimeImmutable($date, new DateTimeZone('UTC'));
    } catch (Throwable $exception) {
        return $date;
    }

    return $dt->format('j M');
}

function lead_status_label(string $status): string
{
    $map = [
        'new' => 'New',
        'visited' => 'Visited',
        'quotation' => 'Quotation',
        'converted' => 'Converted',
        'lost' => 'Lost',
    ];

    $normalized = strtolower(trim($status));

    return $map[$normalized] ?? ucfirst($normalized ?: 'New');
}


function lead_stage_order(): array
{
    return [
        'new' => 0,
        'visited' => 1,
        'quotation' => 2,
        'converted' => 3,
        'lost' => 4,
    ];
}

function lead_stage_index(string $status): int
{
    $order = lead_stage_order();
    $normalized = strtolower(trim($status));

    return $order[$normalized] ?? 99;
}

function lead_next_stage(string $status): ?string
{
    $sequence = ['new', 'visited', 'quotation', 'converted'];
    $normalized = strtolower(trim($status));
    foreach ($sequence as $index => $stage) {
        if ($stage === $normalized) {
            return $sequence[$index + 1] ?? null;
        }
    }

    return null;
}

function lead_fetch(PDO $db, int $leadId): array
{
    $stmt = $db->prepare('SELECT * FROM crm_leads WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $leadId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new RuntimeException('Lead not found.');
    }

    return $row;
}

function lead_record_stage_change(PDO $db, int $leadId, string $from, string $to, int $actorId, ?string $note = null): void
{
    $stmt = $db->prepare('INSERT INTO lead_stage_logs(lead_id, actor_id, from_status, to_status, note, created_at) VALUES(:lead_id, :actor_id, :from_status, :to_status, :note, :created_at)');
    $stmt->execute([
        ':lead_id' => $leadId,
        ':actor_id' => $actorId ?: null,
        ':from_status' => $from !== '' ? strtolower($from) : null,
        ':to_status' => strtolower($to),
        ':note' => $note !== null && $note !== '' ? $note : null,
        ':created_at' => now_ist(),
    ]);
}

function lead_has_pending_proposal(PDO $db, int $leadId): bool
{
    $stmt = $db->prepare("SELECT COUNT(*) FROM lead_proposals WHERE lead_id = :lead_id AND status = 'pending'");
    $stmt->execute([':lead_id' => $leadId]);

    return ((int) $stmt->fetchColumn()) > 0;
}

function lead_has_approved_proposal(PDO $db, int $leadId): bool
{
    $stmt = $db->prepare("SELECT COUNT(*) FROM lead_proposals WHERE lead_id = :lead_id AND status = 'approved'");
    $stmt->execute([':lead_id' => $leadId]);

    return ((int) $stmt->fetchColumn()) > 0;
}

function lead_change_stage(PDO $db, int $leadId, string $targetStage, int $actorId, string $actorRole, string $note = ''): array
{
    $allowedStages = array_keys(lead_stage_order());
    $target = strtolower(trim($targetStage));
    if (!in_array($target, $allowedStages, true)) {
        throw new RuntimeException('Unsupported lead stage.');
    }

    $lead = lead_fetch($db, $leadId);
    $current = strtolower((string) ($lead['status'] ?? 'new'));
    if ($current === $target) {
        return $lead;
    }

    if (in_array($current, ['converted', 'lost'], true)) {
        throw new RuntimeException('Finalized leads cannot change stages.');
    }

    $actorRole = strtolower(trim($actorRole));
    if ($actorRole === 'employee') {
        $next = lead_next_stage($current);
        if ($next === null || $next !== $target || $target === 'converted') {
            throw new RuntimeException('Employees can only advance leads to the next stage.');
        }
    } else {
        if ($target === 'converted' && !lead_has_approved_proposal($db, $leadId)) {
            throw new RuntimeException('Approve a proposal before marking the lead as converted.');
        }
        if (in_array($target, ['new', 'visited', 'quotation'], true) && lead_stage_index($target) < lead_stage_index($current)) {
            throw new RuntimeException('Leads cannot be moved backwards in this workflow.');
        }
    }

    $stmt = $db->prepare('UPDATE crm_leads SET status = :status, updated_at = :updated_at WHERE id = :id');
    $stmt->execute([
        ':status' => $target,
        ':updated_at' => now_ist(),
        ':id' => $leadId,
    ]);

    lead_record_stage_change($db, $leadId, $current, $target, $actorId, $note);

    return lead_fetch($db, $leadId);
}

function lead_extract_file_upload(?array $file, array $allowedMime, int $maxBytes): ?array
{
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $error = (int) ($file['error'] ?? UPLOAD_ERR_OK);
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Failed to upload the file.');
    }

    $size = (int) ($file['size'] ?? 0);
    if ($maxBytes > 0 && $size > $maxBytes) {
        throw new RuntimeException('The uploaded file is too large.');
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('Invalid file upload payload.');
    }

    $data = file_get_contents($tmp);
    if ($data === false) {
        throw new RuntimeException('Unable to read uploaded file.');
    }

    $mimeDetector = function_exists('finfo_open') ? new finfo(FILEINFO_MIME_TYPE) : null;
    $mime = $mimeDetector ? ($mimeDetector->file($tmp) ?: '') : '';
    if ($mime === '' && isset($file['type']) && is_string($file['type'])) {
        $mime = $file['type'];
    }
    if (!in_array($mime, $allowedMime, true)) {
        throw new RuntimeException('Unsupported file type.');
    }

    $name = trim((string) ($file['name'] ?? 'upload'));
    if ($name === '') {
        $name = 'upload';
    }

    return [
        'name' => $name,
        'mime' => $mime,
        'data' => base64_encode($data),
    ];
}

function lead_fetch_visits_grouped(PDO $db, array $leadIds): array
{
    if (empty($leadIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($leadIds), '?'));
    $stmt = $db->prepare(<<<'SQL'
SELECT v.*, u.full_name AS employee_name
FROM lead_visits v
LEFT JOIN users u ON v.employee_id = u.id
WHERE v.lead_id IN ($placeholders)
ORDER BY v.created_at DESC
SQL
    );
    $stmt->execute(array_map('intval', $leadIds));

    $grouped = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $leadId = (int) $row['lead_id'];
        $photoDataUrl = null;
        if (!empty($row['photo_data']) && !empty($row['photo_mime'])) {
            $photoDataUrl = 'data:' . $row['photo_mime'] . ';base64,' . $row['photo_data'];
        }
        $grouped[$leadId][] = [
            'id' => (int) $row['id'],
            'note' => (string) ($row['note'] ?? ''),
            'createdAt' => (string) ($row['created_at'] ?? ''),
            'employeeId' => $row['employee_id'] !== null ? (int) $row['employee_id'] : null,
            'employeeName' => (string) ($row['employee_name'] ?? ''),
            'photoName' => (string) ($row['photo_name'] ?? ''),
            'photoMime' => (string) ($row['photo_mime'] ?? ''),
            'photoDataUrl' => $photoDataUrl,
        ];
    }

    return $grouped;
}

function lead_fetch_proposals_grouped(PDO $db, array $leadIds): array
{
    if (empty($leadIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($leadIds), '?'));
    $stmt = $db->prepare(<<<'SQL'
SELECT p.*, submitter.full_name AS employee_name, approver.full_name AS approved_name
FROM lead_proposals p
LEFT JOIN users submitter ON p.employee_id = submitter.id
LEFT JOIN users approver ON p.approved_by = approver.id
WHERE p.lead_id IN ($placeholders)
ORDER BY p.created_at DESC
SQL
    );
    $stmt->execute(array_map('intval', $leadIds));

    $grouped = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $leadId = (int) $row['lead_id'];
        $documentUrl = null;
        if (!empty($row['document_data']) && !empty($row['document_mime'])) {
            $documentUrl = 'data:' . $row['document_mime'] . ';base64,' . $row['document_data'];
        }
        $grouped[$leadId][] = [
            'id' => (int) $row['id'],
            'summary' => (string) ($row['summary'] ?? ''),
            'estimate' => isset($row['estimate_amount']) ? (float) $row['estimate_amount'] : null,
            'status' => strtolower((string) ($row['status'] ?? 'pending')),
            'createdAt' => (string) ($row['created_at'] ?? ''),
            'employeeId' => $row['employee_id'] !== null ? (int) $row['employee_id'] : null,
            'employeeName' => (string) ($row['employee_name'] ?? ''),
            'documentName' => (string) ($row['document_name'] ?? ''),
            'documentMime' => (string) ($row['document_mime'] ?? ''),
            'documentUrl' => $documentUrl,
            'reviewNote' => (string) ($row['review_note'] ?? ''),
            'approvedAt' => (string) ($row['approved_at'] ?? ''),
            'approvedById' => $row['approved_by'] !== null ? (int) $row['approved_by'] : null,
            'approvedByName' => (string) ($row['approved_name'] ?? ''),
        ];
    }

    return $grouped;
}

function lead_hydrate_rows(PDO $db, array $rows): array
{
    if (empty($rows)) {
        return [];
    }

    $leadIds = array_map(static fn ($row) => (int) ($row['id'] ?? 0), $rows);
    $visits = lead_fetch_visits_grouped($db, $leadIds);
    $proposals = lead_fetch_proposals_grouped($db, $leadIds);
    $order = lead_stage_order();

    $hydrated = [];
    foreach ($rows as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $status = strtolower((string) ($row['status'] ?? 'new'));
        $leadProposals = $proposals[$id] ?? [];
        $pending = null;
        $hasPending = false;
        $hasApproved = false;
        foreach ($leadProposals as $proposal) {
            if ($proposal['status'] === 'pending' && $pending === null) {
                $pending = $proposal;
                $hasPending = true;
            }
            if ($proposal['status'] === 'approved') {
                $hasApproved = true;
            }
        }

        $hydrated[] = [
            'id' => $id,
            'name' => (string) ($row['name'] ?? ''),
            'phone' => trim((string) ($row['phone'] ?? '')),
            'email' => trim((string) ($row['email'] ?? '')),
            'source' => trim((string) ($row['source'] ?? '')),
            'status' => $status,
            'statusLabel' => lead_status_label($status),
            'stageIndex' => $order[$status] ?? 99,
            'assignedId' => isset($row['assigned_to']) && $row['assigned_to'] !== null ? (int) $row['assigned_to'] : null,
            'assignedName' => (string) ($row['assigned_name'] ?? ''),
            'createdById' => isset($row['created_by']) && $row['created_by'] !== null ? (int) $row['created_by'] : null,
            'createdName' => (string) ($row['created_name'] ?? ''),
            'siteLocation' => trim((string) ($row['site_location'] ?? '')),
            'siteDetails' => trim((string) ($row['site_details'] ?? '')),
            'notes' => (string) ($row['notes'] ?? ''),
            'createdAt' => (string) ($row['created_at'] ?? ''),
            'updatedAt' => (string) ($row['updated_at'] ?? ''),
            'visits' => $visits[$id] ?? [],
            'proposals' => $leadProposals,
            'hasPendingProposal' => $hasPending,
            'pendingProposal' => $pending,
            'hasApprovedProposal' => $hasApproved,
            'latestVisit' => ($visits[$id] ?? [])[0] ?? null,
        ];
    }

    return $hydrated;
}

function admin_active_employees(PDO $db): array
{
    $stmt = $db->query("SELECT users.id, users.full_name FROM users INNER JOIN roles ON users.role_id = roles.id WHERE roles.name = 'employee' AND users.status = 'active' ORDER BY users.full_name");
    $result = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $result[] = [
            'id' => (int) $row['id'],
            'name' => (string) ($row['full_name'] ?? ''),
        ];
    }

    return $result;
}

function admin_create_lead(PDO $db, array $input, int $actorId): array
{
    $name = trim((string) ($input['name'] ?? ''));
    if ($name === '') {
        throw new RuntimeException('Lead name is required.');
    }

    $phone = trim((string) ($input['phone'] ?? ''));
    $email = trim((string) ($input['email'] ?? ''));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Enter a valid email address.');
    }

    $source = trim((string) ($input['source'] ?? ''));
    $siteLocation = trim((string) ($input['site_location'] ?? ''));
    $siteDetails = trim((string) ($input['site_details'] ?? ''));
    $notes = trim((string) ($input['notes'] ?? ''));
    $assignedTo = isset($input['assigned_to']) && $input['assigned_to'] !== '' ? (int) $input['assigned_to'] : null;
    if ($assignedTo !== null && $assignedTo > 0) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM users INNER JOIN roles ON users.role_id = roles.id WHERE users.id = :id AND roles.name = 'employee' AND users.status = 'active'");
        $stmt->execute([':id' => $assignedTo]);
        if ((int) $stmt->fetchColumn() === 0) {
            throw new RuntimeException('Selected employee is not available.');
        }
    } else {
        $assignedTo = null;
    }

    $stmt = $db->prepare('INSERT INTO crm_leads(name, phone, email, source, status, assigned_to, created_by, site_location, site_details, notes, created_at, updated_at) VALUES(:name, :phone, :email, :source, :status, :assigned_to, :created_by, :site_location, :site_details, :notes, :created_at, :updated_at)');
    $now = now_ist();
    $stmt->execute([
        ':name' => $name,
        ':phone' => $phone !== '' ? $phone : null,
        ':email' => $email !== '' ? $email : null,
        ':source' => $source !== '' ? $source : null,
        ':status' => 'new',
        ':assigned_to' => $assignedTo,
        ':created_by' => $actorId ?: null,
        ':site_location' => $siteLocation !== '' ? $siteLocation : null,
        ':site_details' => $siteDetails !== '' ? $siteDetails : null,
        ':notes' => $notes !== '' ? $notes : null,
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);
    $leadId = (int) $db->lastInsertId();

    return lead_fetch($db, $leadId);
}

function admin_assign_lead(PDO $db, int $leadId, ?int $employeeId, int $actorId): array
{
    if ($employeeId !== null && $employeeId > 0) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM users INNER JOIN roles ON users.role_id = roles.id WHERE users.id = :id AND roles.name = 'employee' AND users.status = 'active'");
        $stmt->execute([':id' => $employeeId]);
        if ((int) $stmt->fetchColumn() === 0) {
            throw new RuntimeException('Selected employee is not available.');
        }
    }

    $stmt = $db->prepare('UPDATE crm_leads SET assigned_to = :assigned_to, updated_at = :updated_at WHERE id = :id');
    $stmt->execute([
        ':assigned_to' => $employeeId ?: null,
        ':updated_at' => now_ist(),
        ':id' => $leadId,
    ]);

    return lead_fetch($db, $leadId);
}

function admin_update_lead_stage(PDO $db, int $leadId, string $stage, int $actorId, string $note = ''): array
{
    return lead_change_stage($db, $leadId, $stage, $actorId, 'admin', $note);
}

function admin_fetch_lead_overview(PDO $db): array
{
    $orderExpr = "CASE status WHEN 'new' THEN 0 WHEN 'visited' THEN 1 WHEN 'quotation' THEN 2 WHEN 'converted' THEN 3 WHEN 'lost' THEN 4 ELSE 5 END";
    $stmt = $db->query("SELECT l.*, assignee.full_name AS assigned_name, creator.full_name AS created_name FROM crm_leads l LEFT JOIN users assignee ON l.assigned_to = assignee.id LEFT JOIN users creator ON l.created_by = creator.id ORDER BY $orderExpr, COALESCE(l.updated_at, l.created_at) DESC");

    return lead_hydrate_rows($db, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function employee_list_leads(PDO $db, int $employeeId): array
{
    $orderExpr = "CASE status WHEN 'new' THEN 0 WHEN 'visited' THEN 1 WHEN 'quotation' THEN 2 WHEN 'converted' THEN 3 WHEN 'lost' THEN 4 ELSE 5 END";
    $stmt = $db->prepare("SELECT l.*, assignee.full_name AS assigned_name FROM crm_leads l LEFT JOIN users assignee ON l.assigned_to = assignee.id WHERE l.assigned_to = :employee_id ORDER BY $orderExpr, COALESCE(l.updated_at, l.created_at) DESC");
    $stmt->execute([':employee_id' => $employeeId]);

    $leads = lead_hydrate_rows($db, $stmt->fetchAll(PDO::FETCH_ASSOC));
    foreach ($leads as &$lead) {
        $lead['nextStage'] = lead_next_stage($lead['status']);
        $lead['canAdvance'] = in_array($lead['status'], ['new', 'visited'], true) && !in_array($lead['status'], ['converted', 'lost'], true);
        if ($lead['canAdvance']) {
            if ($lead['status'] === 'visited') {
                $lead['nextStage'] = 'quotation';
            } elseif ($lead['status'] === 'new') {
                $lead['nextStage'] = 'visited';
            }
        }
        $lead['canSubmitProposal'] = $lead['status'] === 'quotation' && !$lead['hasPendingProposal'];
    }
    unset($lead);

    return $leads;
}

function employee_add_lead_visit(PDO $db, array $input, int $employeeId): array
{
    $leadId = (int) ($input['lead_id'] ?? 0);
    if ($leadId <= 0) {
        throw new RuntimeException('Lead reference is required.');
    }

    $note = trim((string) ($input['note'] ?? ''));
    if ($note === '') {
        throw new RuntimeException('Visit note cannot be empty.');
    }

    $lead = lead_fetch($db, $leadId);
    if ((int) ($lead['assigned_to'] ?? 0) !== $employeeId) {
        throw new RuntimeException('You are not assigned to this lead.');
    }
    $status = strtolower((string) ($lead['status'] ?? 'new'));
    if (in_array($status, ['converted', 'lost'], true)) {
        throw new RuntimeException('Closed leads cannot accept new visits.');
    }

    $photo = lead_extract_file_upload($input['photo'] ?? null, ['image/jpeg', 'image/png', 'image/gif'], 5 * 1024 * 1024);
    $stmt = $db->prepare('INSERT INTO lead_visits(lead_id, employee_id, note, photo_name, photo_mime, photo_data, created_at) VALUES(:lead_id, :employee_id, :note, :photo_name, :photo_mime, :photo_data, :created_at)');
    $stmt->execute([
        ':lead_id' => $leadId,
        ':employee_id' => $employeeId,
        ':note' => $note,
        ':photo_name' => $photo['name'] ?? null,
        ':photo_mime' => $photo['mime'] ?? null,
        ':photo_data' => $photo['data'] ?? null,
        ':created_at' => now_ist(),
    ]);

    $db->prepare('UPDATE crm_leads SET updated_at = :updated_at WHERE id = :id')->execute([
        ':updated_at' => now_ist(),
        ':id' => $leadId,
    ]);

    return lead_fetch($db, $leadId);
}

function employee_progress_lead(PDO $db, int $leadId, string $stage, int $employeeId): array
{
    $lead = lead_fetch($db, $leadId);
    if ((int) ($lead['assigned_to'] ?? 0) !== $employeeId) {
        throw new RuntimeException('You are not assigned to this lead.');
    }

    return lead_change_stage($db, $leadId, $stage, $employeeId, 'employee');
}

function employee_submit_lead_proposal(PDO $db, array $input, int $employeeId): array
{
    $leadId = (int) ($input['lead_id'] ?? 0);
    if ($leadId <= 0) {
        throw new RuntimeException('Lead reference is required.');
    }

    $summary = trim((string) ($input['summary'] ?? ''));
    if ($summary === '') {
        throw new RuntimeException('Proposal summary is required.');
    }

    $lead = lead_fetch($db, $leadId);
    if ((int) ($lead['assigned_to'] ?? 0) !== $employeeId) {
        throw new RuntimeException('You are not assigned to this lead.');
    }

    $status = strtolower((string) ($lead['status'] ?? 'new'));
    if ($status !== 'quotation') {
        throw new RuntimeException('Proposals can only be submitted at the quotation stage.');
    }

    if (lead_has_pending_proposal($db, $leadId)) {
        throw new RuntimeException('A proposal is already pending review.');
    }

    $estimateRaw = trim((string) ($input['estimate'] ?? ''));
    $estimate = $estimateRaw !== '' ? (float) $estimateRaw : null;
    $document = lead_extract_file_upload($input['document'] ?? null, ['application/pdf', 'image/jpeg', 'image/png'], 8 * 1024 * 1024);

    $stmt = $db->prepare('INSERT INTO lead_proposals(lead_id, employee_id, summary, estimate_amount, document_name, document_mime, document_data, status, created_at) VALUES(:lead_id, :employee_id, :summary, :estimate_amount, :document_name, :document_mime, :document_data, :status, :created_at)');
    $stmt->execute([
        ':lead_id' => $leadId,
        ':employee_id' => $employeeId,
        ':summary' => $summary,
        ':estimate_amount' => $estimate,
        ':document_name' => $document['name'] ?? null,
        ':document_mime' => $document['mime'] ?? null,
        ':document_data' => $document['data'] ?? null,
        ':status' => 'pending',
        ':created_at' => now_ist(),
    ]);

    $db->prepare('UPDATE crm_leads SET updated_at = :updated_at WHERE id = :id')->execute([
        ':updated_at' => now_ist(),
        ':id' => $leadId,
    ]);

    return lead_fetch($db, $leadId);
}

function admin_approve_lead_proposal(PDO $db, int $proposalId, int $actorId): array
{
    $stmt = $db->prepare('SELECT * FROM lead_proposals WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $proposalId]);
    $proposal = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$proposal) {
        throw new RuntimeException('Proposal not found.');
    }
    if (($proposal['status'] ?? 'pending') !== 'pending') {
        throw new RuntimeException('Only pending proposals can be approved.');
    }

    $update = $db->prepare("UPDATE lead_proposals SET status = 'approved', approved_at = :approved_at, approved_by = :approved_by, review_note = :review_note WHERE id = :id");
    $update->execute([
        ':approved_at' => now_ist(),
        ':approved_by' => $actorId ?: null,
        ':review_note' => null,
        ':id' => $proposalId,
    ]);

    $leadId = (int) $proposal['lead_id'];
    lead_change_stage($db, $leadId, 'converted', $actorId, 'admin', 'Proposal #' . $proposalId . ' approved');

    return lead_fetch($db, $leadId);
}

function admin_reject_lead_proposal(PDO $db, int $proposalId, int $actorId, string $note = ''): array
{
    $stmt = $db->prepare('SELECT * FROM lead_proposals WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $proposalId]);
    $proposal = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$proposal) {
        throw new RuntimeException('Proposal not found.');
    }
    if (($proposal['status'] ?? 'pending') !== 'pending') {
        throw new RuntimeException('Only pending proposals can be reviewed.');
    }

    $db->prepare("UPDATE lead_proposals SET status = 'rejected', approved_at = :approved_at, approved_by = :approved_by, review_note = :review_note WHERE id = :id")->execute([
        ':approved_at' => now_ist(),
        ':approved_by' => $actorId ?: null,
        ':review_note' => $note !== '' ? $note : null,
        ':id' => $proposalId,
    ]);

    $leadId = (int) $proposal['lead_id'];
    $db->prepare('UPDATE crm_leads SET updated_at = :updated_at WHERE id = :id')->execute([
        ':updated_at' => now_ist(),
        ':id' => $leadId,
    ]);

    return lead_fetch($db, $leadId);
}

function admin_mark_lead_lost(PDO $db, int $leadId, int $actorId, string $note = ''): array
{
    return lead_change_stage($db, $leadId, 'lost', $actorId, 'admin', $note);
}


function installation_status_label(string $status): string
{
    $map = [
        'planning' => 'Planning',
        'in_progress' => 'In Progress',
        'completed' => 'Completed',
        'on_hold' => 'On Hold',
        'cancelled' => 'Cancelled',
    ];

    $normalized = strtolower(trim($status));

    return $map[$normalized] ?? ucfirst(str_replace('_', ' ', $normalized ?: 'in progress'));
}

function complaint_status_label(string $status): string
{
    $map = [
        'intake' => 'Intake',
        'triage' => 'Triage',
        'work' => 'Work In Progress',
        'resolution' => 'Resolution',
        'closed' => 'Closed',
    ];

    $normalized = strtolower(trim($status));

    return $map[$normalized] ?? ucfirst($normalized ?: 'open');
}

function subsidy_status_label(string $status): string
{
    $map = [
        'pending' => 'Pending',
        'submitted' => 'Submitted',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'disbursed' => 'Disbursed',
    ];

    $normalized = strtolower(trim($status));

    return $map[$normalized] ?? ucfirst($normalized ?: 'pending');
}

function reminder_status_label(string $status): string
{
    $map = [
        'open' => 'Open',
        'done' => 'Done',
        'dismissed' => 'Dismissed',
    ];

    $normalized = strtolower(trim($status));

    return $map[$normalized] ?? ucfirst($normalized ?: 'open');
}

function admin_list_employees(PDO $db, string $status = 'active'): array
{
    $status = strtolower(trim($status));
    $stmt = $db->prepare('SELECT users.full_name, users.email, users.status, users.created_at, users.last_login_at FROM users INNER JOIN roles ON users.role_id = roles.id WHERE roles.name = \"employee\" AND (:status = \"all\" OR users.status = :status) ORDER BY users.full_name COLLATE NOCASE');
    $stmt->execute([
        ':status' => $status === 'all' ? 'all' : $status,
    ]);

    return $stmt->fetchAll();
}

function admin_list_leads(PDO $db, string $status = 'new'): array
{
    $status = strtolower(trim($status));
    $orderExpr = "CASE status WHEN 'new' THEN 0 WHEN 'visited' THEN 1 WHEN 'quotation' THEN 2 WHEN 'converted' THEN 3 WHEN 'lost' THEN 4 ELSE 5 END";
    if ($status === 'all') {
        $stmt = $db->query("SELECT l.name, l.phone, l.email, l.status, l.source, l.assigned_to, assignee.full_name AS assigned_name, l.created_at, l.updated_at FROM crm_leads l LEFT JOIN users assignee ON l.assigned_to = assignee.id ORDER BY $orderExpr, COALESCE(l.updated_at, l.created_at) DESC");
        return $stmt->fetchAll();
    }

    $stmt = $db->prepare("SELECT l.name, l.phone, l.email, l.status, l.source, l.assigned_to, assignee.full_name AS assigned_name, l.created_at, l.updated_at FROM crm_leads l LEFT JOIN users assignee ON l.assigned_to = assignee.id WHERE l.status = :status ORDER BY $orderExpr, COALESCE(l.updated_at, l.created_at) DESC");
    $stmt->execute([':status' => $status]);

    return $stmt->fetchAll();
}

function admin_list_installations(PDO $db, string $filter = 'ongoing'): array
{
    return installation_admin_filter($db, $filter);
}

function admin_list_complaints(PDO $db, string $status = 'open'): array
{
    if ($status === 'all') {
        $stmt = $db->query('SELECT reference, title, status, priority, created_at, updated_at FROM complaints ORDER BY COALESCE(updated_at, created_at) DESC');
        return $stmt->fetchAll();
    }

    $stmt = $db->prepare("SELECT reference, title, status, priority, created_at, updated_at FROM complaints WHERE status IN ('intake','triage','work') ORDER BY COALESCE(updated_at, created_at) DESC");
    $stmt->execute();

    return $stmt->fetchAll();
}

function admin_list_subsidy(PDO $db, string $status = 'pending'): array
{
    $status = strtolower(trim($status));
    if ($status === 'all') {
        $stmt = $db->query('SELECT customer_name, application_number, status, amount, submitted_on, created_at, updated_at FROM subsidy_applications ORDER BY COALESCE(updated_at, created_at) DESC');
        return $stmt->fetchAll();
    }

    $valid = [
        'pending' => ['pending', 'submitted'],
        'approved' => ['approved'],
        'rejected' => ['rejected'],
        'disbursed' => ['disbursed'],
    ];

    $statuses = $valid[$status] ?? [$status];
    $placeholders = implode(',', array_fill(0, count($statuses), '?'));
    $stmt = $db->prepare("SELECT customer_name, application_number, status, amount, submitted_on, created_at, updated_at FROM subsidy_applications WHERE status IN ($placeholders) ORDER BY COALESCE(updated_at, created_at) DESC");
    $stmt->execute($statuses);

    return $stmt->fetchAll();
}

function portal_find_complaint_id(PDO $db, string $reference): ?int
{
    $notes = [];
    if (!empty($row['notes'])) {
        $decoded = json_decode((string) $row['notes'], true);
        if (is_array($decoded)) {
            foreach ($decoded as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $notes[] = [
                    'id' => (string) ($item['id'] ?? ''),
                    'body' => (string) ($item['body'] ?? ''),
                    'visibility' => (string) ($item['visibility'] ?? 'internal'),
                    'authorId' => $item['authorId'] ?? null,
                    'authorName' => (string) ($item['authorName'] ?? ''),
                    'createdAt' => (string) ($item['createdAt'] ?? ''),
                ];
            }
        }
    }

    $attachments = [];
    if (!empty($row['attachments'])) {
        $decoded = json_decode((string) $row['attachments'], true);
        if (is_array($decoded)) {
            foreach ($decoded as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $attachments[] = [
                    'id' => (string) ($item['id'] ?? ''),
                    'filename' => (string) ($item['filename'] ?? ''),
                    'label' => (string) ($item['label'] ?? ''),
                    'note' => (string) ($item['note'] ?? ''),
                    'sizeMb' => isset($item['sizeMb']) ? (float) $item['sizeMb'] : null,
                    'visibility' => (string) ($item['visibility'] ?? 'both'),
                    'uploadedBy' => (string) ($item['uploadedBy'] ?? ''),
                    'uploadedById' => $item['uploadedById'] ?? null,
                    'uploadedAt' => (string) ($item['uploadedAt'] ?? ''),
                    'downloadToken' => (string) ($item['downloadToken'] ?? ''),
                ];
            }
        }
    }

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
        'notes' => $notes,
        'attachments' => $attachments,
    ];
}

function portal_update_complaint_status(PDO $db, string $reference, string $statusKey, int $actorId): array
{
    $reference = trim($reference);
    if ($reference === '') {
        throw new RuntimeException('Complaint reference is required.');
    }

    $row = portal_fetch_complaint_row($db, $reference);

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
    $stmtUpdate = $db->prepare('UPDATE complaints SET status = :status, updated_at = :updated_at, assigned_to = CASE WHEN :status = \'triage\' THEN NULL ELSE assigned_to END WHERE reference = :reference');
    $stmtUpdate->execute([
        ':status' => $newStatus,
        ':updated_at' => now_ist(),
        ':reference' => $reference,
    ]);

    portal_log_action($db, $actorId, 'status_change', 'complaint', (int) $row['id'], 'Complaint updated to ' . $newStatus);
    portal_record_complaint_event($db, (int) $row['id'], $actorId, 'status', 'Status updated to ' . strtoupper($newStatus), null, null, $newStatus);

    return portal_normalize_complaint_row(portal_fetch_complaint_row($db, $reference));
}

function portal_fetch_complaint_row(PDO $db, string $reference): array
{
    $stmt = $db->prepare('SELECT complaints.*, users.full_name AS assigned_to_name, roles.name AS assigned_role FROM complaints LEFT JOIN users ON complaints.assigned_to = users.id LEFT JOIN roles ON users.role_id = roles.id WHERE complaints.reference = :reference LIMIT 1');
    $stmt->execute([':reference' => $reference]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('Complaint not found.');
    }

    return $row;
}

function portal_assign_complaint(PDO $db, string $reference, ?int $assigneeId, ?string $slaDue, int $actorId): array
{
    $reference = trim($reference);
    if ($reference === '') {
        throw new RuntimeException('Complaint reference is required.');
    }

    $row = portal_fetch_complaint_row($db, $reference);

    if ($assigneeId !== null) {
        $assignee = portal_find_user($db, $assigneeId);
        if (!$assignee || ($assignee['role_name'] ?? '') !== 'employee') {
            throw new RuntimeException('Select a valid employee assignee.');
        }
    }

    $dueDate = null;
    if ($slaDue !== null && $slaDue !== '') {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $slaDue)) {
            throw new RuntimeException('SLA due date must be in YYYY-MM-DD format.');
        }
        $dueDate = $slaDue;
    }

    $stmt = $db->prepare('UPDATE complaints SET assigned_to = :assignee_id, sla_due_at = :sla_due_at, updated_at = :updated_at WHERE reference = :reference');
    $stmt->execute([
        ':assignee_id' => $assigneeId,
        ':sla_due_at' => $dueDate,
        ':updated_at' => now_ist(),
        ':reference' => $reference,
    ]);

    portal_log_action($db, $actorId, 'assign', 'complaint', (int) $row['id'], sprintf('Complaint assigned to %s', $assigneeId ? ('user #' . $assigneeId) : 'unassigned'));

    return portal_normalize_complaint_row(portal_fetch_complaint_row($db, $reference));
}

function portal_add_complaint_note(PDO $db, string $reference, string $noteBody, int $actorId, string $visibility = 'internal'): array
{
    $reference = trim($reference);
    if ($reference === '') {
        throw new RuntimeException('Complaint reference is required.');
    }

    $noteBody = trim($noteBody);
    if ($noteBody === '') {
        throw new RuntimeException('Note cannot be empty.');
    }

    $row = portal_fetch_complaint_row($db, $reference);

    $notes = [];
    if (!empty($row['notes'])) {
        $decoded = json_decode((string) $row['notes'], true);
        if (is_array($decoded)) {
            $notes = $decoded;
        }
    }

    $author = $actorId > 0 ? portal_find_user($db, $actorId) : null;
    $record = [
        'id' => bin2hex(random_bytes(6)),
        'body' => $noteBody,
        'visibility' => $visibility,
        'authorId' => $actorId ?: null,
        'authorName' => $author['full_name'] ?? 'System',
        'createdAt' => now_ist(),
    ];
    $notes[] = $record;

    $stmt = $db->prepare('UPDATE complaints SET notes = :notes, updated_at = :updated_at WHERE reference = :reference');
    $stmt->execute([
        ':notes' => json_encode($notes, JSON_THROW_ON_ERROR),
        ':updated_at' => $record['createdAt'],
        ':reference' => $reference,
    ]);

    portal_log_action($db, $actorId, 'note_added', 'complaint', (int) $row['id'], 'Complaint note recorded');

    return portal_normalize_complaint_row(portal_fetch_complaint_row($db, $reference));
}

function portal_add_complaint_attachment(PDO $db, string $reference, array $attachment, int $actorId): array
{
    $reference = trim($reference);
    if ($reference === '') {
        throw new RuntimeException('Complaint reference is required.');
    }

    $row = portal_fetch_complaint_row($db, $reference);

    $filename = trim((string) ($attachment['filename'] ?? ''));
    $label = trim((string) ($attachment['label'] ?? 'Attachment'));
    if ($filename === '') {
        throw new RuntimeException('Attachment filename is required.');
    }

    $sizeMb = isset($attachment['sizeMb']) ? (float) $attachment['sizeMb'] : null;
    $note = trim((string) ($attachment['note'] ?? ''));
    $visibility = (string) ($attachment['visibility'] ?? 'both');
    if (!in_array($visibility, ['employee', 'admin', 'both'], true)) {
        $visibility = 'both';
    }

    $attachments = [];
    if (!empty($row['attachments'])) {
        $decoded = json_decode((string) $row['attachments'], true);
        if (is_array($decoded)) {
            $attachments = $decoded;
        }
    }

    $author = $actorId > 0 ? portal_find_user($db, $actorId) : null;
    $record = [
        'id' => bin2hex(random_bytes(6)),
        'filename' => $filename,
        'label' => $label,
        'note' => $note,
        'sizeMb' => $sizeMb,
        'visibility' => $visibility,
        'uploadedBy' => $author['full_name'] ?? 'System',
        'uploadedById' => $actorId ?: null,
        'uploadedAt' => now_ist(),
        'downloadToken' => bin2hex(random_bytes(16)),
    ];

    if (isset($attachment['documentId'])) {
        $record['documentId'] = (int) $attachment['documentId'];
    }

    $attachments[] = $record;

    $stmt = $db->prepare('UPDATE complaints SET attachments = :attachments, updated_at = :updated_at WHERE reference = :reference');
    $stmt->execute([
        ':attachments' => json_encode($attachments, JSON_THROW_ON_ERROR),
        ':updated_at' => $record['uploadedAt'],
        ':reference' => $reference,
    ]);

    portal_log_action($db, $actorId, 'attachment_added', 'complaint', (int) $row['id'], 'Complaint attachment logged');

    return portal_normalize_complaint_row(portal_fetch_complaint_row($db, $reference));
}

function portal_recent_audit_logs(PDO $db, int $limit = 25): array
{
    $stmt = $db->prepare('SELECT audit_logs.id, audit_logs.action, audit_logs.entity_type, audit_logs.entity_id, audit_logs.description, audit_logs.created_at, users.full_name AS actor_name FROM audit_logs LEFT JOIN users ON audit_logs.actor_id = users.id ORDER BY audit_logs.created_at DESC LIMIT :limit');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function portal_get_complaint(PDO $db, string $reference): array
{
    return portal_normalize_complaint_row(portal_fetch_complaint_row($db, $reference));
}

function portal_employee_submit_complaint_document(PDO $db, int $userId, string $reference, array $payload): array
{
    enforce_complaint_access($db, $reference, $userId);

    $type = trim((string) ($payload['type'] ?? ''));
    $filename = trim((string) ($payload['filename'] ?? ''));
    if ($type === '' || $filename === '') {
        throw new RuntimeException('Document type and file name are required.');
    }

    $note = trim((string) ($payload['note'] ?? ''));
    $sizeValue = trim((string) ($payload['file_size'] ?? ''));
    $sizeMb = $sizeValue !== '' ? (float) $sizeValue : null;

    $documentData = [
        'name' => $type,
        'linkedTo' => 'complaint:' . $reference,
        'reference' => $filename,
        'tags' => [$reference],
        'url' => '#',
        'visibility' => 'both',
        'notes' => $note,
    ];

    $document = portal_save_document($db, $documentData, $userId);

    $complaint = portal_add_complaint_attachment($db, $reference, [
        'filename' => $filename,
        'label' => $type,
        'note' => $note,
        'sizeMb' => $sizeMb,
        'visibility' => 'both',
        'documentId' => $document['id'] ?? null,
    ], $userId);

    return [
        'document' => $document,
        'complaint' => $complaint,
    ];
}
