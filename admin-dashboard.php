<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bootstrap.php';

require_role('admin');
$user = current_user();
$db = get_db();

$rolesStmt = $db->query('SELECT id, name FROM roles ORDER BY name');
$availableRoles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC);

$customerCount = (int) $db->query("SELECT COUNT(*) FROM users INNER JOIN roles ON users.role_id = roles.id WHERE roles.name = 'customer'")->fetchColumn();
$pendingInvites = (int) $db->query("SELECT COUNT(*) FROM invitations WHERE status = 'pending'")->fetchColumn();
$openComplaints = (int) $db->query("SELECT COUNT(*) FROM complaints WHERE status IN ('intake','triage','work')")->fetchColumn();
$systemMetrics = $db->query('SELECT name, value FROM system_metrics')->fetchAll(PDO::FETCH_KEY_PAIR);
$subsidyPipeline = $systemMetrics['subsidy_pipeline'] ?? '0';
$lastBackup = $systemMetrics['last_backup'] ?? 'Not recorded';
$errorCount = $systemMetrics['errors_24h'] ?? '0';
$diskUsage = $systemMetrics['disk_usage'] ?? 'Normal';
$uptime = $systemMetrics['uptime'] ?? 'Unknown';
$retentionSettings = [
    'archiveDays' => (int) ($systemMetrics['retention_archive_days'] ?? 90),
    'purgeDays' => (int) ($systemMetrics['retention_purge_days'] ?? 180),
    'includeAudit' => ($systemMetrics['retention_include_audit'] ?? '1') !== '0',
];
$geminiSettings = [
    'apiKey' => get_setting('gemini_api_key') ?? '',
    'textModel' => get_setting('gemini_text_model') ?? 'gemini-2.5-flash',
    'imageModel' => get_setting('gemini_image_model') ?? 'gemini-2.5-flash-image',
    'ttsModel' => get_setting('gemini_tts_model') ?? 'gemini-2.5-flash-preview-tts',
];

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
if ($scriptDir === '/' || $scriptDir === '.') {
    $scriptDir = '';
}
$basePath = rtrim($scriptDir, '/');
$prefix = $basePath === '' ? '' : $basePath;
$pathFor = static function (string $path) use ($prefix): string {
    $clean = ltrim($path, '/');
    return ($prefix === '' ? '' : $prefix) . '/' . $clean;
};

$logoutUrl = $pathFor('logout.php');
$apiBase = $pathFor('api/admin.php');

$taskTeam = [];
$taskItems = [];
$documents = [];

$blogPosts = blog_admin_list($db);

$dataQuality = [
    'validations' => [],
    'duplicates' => [],
    'approvals' => [],
];

$crmData = [
    'leads' => [],
    'customers' => [],
];

$installations = [
    'jobs' => [],
    'warranties' => [],
    'amc' => [],
];

$analytics = [
    'kpis' => [],
    'installerProductivity' => [],
    'funnel' => [],
];

$governance = [
    'roleMatrix' => [],
    'pendingReviews' => [],
    'activityLogs' => [],
];

$referrers = [];

$subsidyApplications = [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin Control Center | Dakshayani Enterprises</title>
  <meta
    name="description"
    content="Tabbed administrator workspace for onboarding approvals, access control, AI settings, complaints, and operational monitoring."
  />
  <link rel="icon" href="images/favicon.ico" />
  <link rel="stylesheet" href="style.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800;900&display=swap"
    rel="stylesheet"
  />
  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    crossorigin="anonymous"
    referrerpolicy="no-referrer"
  />
</head>
<body data-dashboard-theme="light">
  <main class="dashboard">
    <div class="container dashboard-shell">
      <div class="dashboard-auth-bar" role="banner">
        <div class="dashboard-auth-user">
          <i class="fa-solid fa-user-shield" aria-hidden="true"></i>
          <div>
            <small>Signed in as</small>
            <strong><?= htmlspecialchars($user['full_name'] ?? 'Administrator', ENT_QUOTES) ?></strong>
          </div>
        </div>
        <div class="dashboard-auth-actions">
          <button type="button" class="btn btn-ghost" data-open-quick-search>
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            Quick search
          </button>
          <div class="dashboard-theme-toggle" role="group" aria-label="Theme selection">
            <label>
              <input type="radio" name="theme" value="light" data-theme-option checked />
              <span>Light</span>
            </label>
            <label>
              <input type="radio" name="theme" value="dark" data-theme-option />
              <span>Dark</span>
            </label>
            <label>
              <input type="radio" name="theme" value="auto" data-theme-option />
              <span>Auto</span>
            </label>
          </div>
          <a href="<?= htmlspecialchars($logoutUrl, ENT_QUOTES) ?>" class="btn btn-ghost">
            <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
            Log out
          </a>
        </div>
      </div>

      <header class="dashboard-header">
        <div class="dashboard-heading">
          <span class="badge badge-soft"><i class="fa-solid fa-lock"></i> Secure oversight</span>
          <h1>Admin Control Center</h1>
          <p class="dashboard-subheading">
            Approve new users, orchestrate services, and monitor subsidy, AMC, and complaints without leaving this workspace.
          </p>
          <p class="dashboard-meta">
            <i class="fa-regular fa-circle-check" aria-hidden="true"></i>
            Backup verified <strong><?= htmlspecialchars($lastBackup, ENT_QUOTES) ?></strong> · <span>Lockout enforced after 5 failed login attempts</span>
          </p>
        </div>
        <div class="dashboard-search" role="search">
          <form class="dashboard-search-field" data-dashboard-search>
            <label for="dashboard-search-input" class="sr-only">Search admin records</label>
            <input
              id="dashboard-search-input"
              type="search"
              placeholder="Search users, tickets, installers…"
              autocomplete="off"
              aria-describedby="dashboard-search-help"
            />
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
          </form>
          <p id="dashboard-search-help" class="dashboard-subheading">
            Instant lookup across onboarding queues, customers, AMC renewals, and subsidy pipeline.
          </p>
          <div class="dashboard-search-results" data-dashboard-search-results hidden>
            <p class="dashboard-search-empty">Start typing to surface admin modules, records, and recent activity.</p>
          </div>
        </div>
      </header>

      <div class="dashboard-body">
        <nav class="dashboard-nav" aria-label="Admin navigation" role="tablist">
          <p class="dashboard-nav-title">Workspace</p>
          <ul>
            <li>
              <button class="dashboard-nav-link" type="button" role="tab" aria-selected="true" data-tab-target="overview">
                <i class="fa-solid fa-chart-line"></i> Overview
              </button>
            </li>
            <li>
              <button class="dashboard-nav-link" type="button" role="tab" aria-selected="false" data-tab-target="analytics">
                <i class="fa-solid fa-chart-pie"></i> Analytics &amp; KPIs
              </button>
            </li>
            <li>
              <button class="dashboard-nav-link" type="button" role="tab" aria-selected="false" data-tab-target="onboarding">
                <i class="fa-solid fa-user-plus"></i> Onboarding &amp; Approvals
              </button>
            </li>
            <li>
              <button class="dashboard-nav-link" type="button" role="tab" aria-selected="false" data-tab-target="tasks">
                <i class="fa-solid fa-list-check"></i> Tasks &amp; My Work
              </button>
            </li>
            <li>
              <button class="dashboard-nav-link" type="button" role="tab" aria-selected="false" data-tab-target="installations">
                <i class="fa-solid fa-solar-panel"></i> Installations &amp; AMC
              </button>
            </li>
            <li>
              <button class="dashboard-nav-link" type="button" role="tab" aria-selected="false" data-tab-target="documents">
                <i class="fa-solid fa-folder-tree"></i> Document Vault
              </button>
            </li>
            <li>
              <button class="dashboard-nav-link" type="button" role="tab" aria-selected="false" data-tab-target="data-quality">
                <i class="fa-solid fa-spell-check"></i> Data Quality &amp; Approvals
              </button>
            </li>
            <li>
              <button class="dashboard-nav-link" type="button" role="tab" aria-selected="false" data-tab-target="crm">
                <i class="fa-solid fa-people-arrows"></i> Leads &amp; CRM
              </button>
            </li>
            <li>
              <button class="dashboard-nav-link" type="button" role="tab" aria-selected="false" data-tab-target="referrers">
                <i class="fa-solid fa-handshake"></i> Referrers &amp; Partners
              </button>
            </li>
            <li>
              <button class="dashboard-nav-link" type="button" role="tab" aria-selected="false" data-tab-target="subsidy">
                <i class="fa-solid fa-indian-rupee-sign"></i> Subsidy Pipeline
              </button>
            </li>
            <li>
              <button class="dashboard-nav-link" type="button" role="tab" aria-selected="false" data-tab-target="access">
                <i class="fa-solid fa-fingerprint"></i> Access Control
              </button>
            </li>
            <li>
              <button class="dashboard-nav-link" type="button" role="tab" aria-selected="false" data-tab-target="health">
                <i class="fa-solid fa-heart-pulse"></i> Backups &amp; Health
              </button>
            </li>
            <li>
              <button class="dashboard-nav-link" type="button" role="tab" aria-selected="false" data-tab-target="blog">
                <i class="fa-solid fa-blog"></i> Blog Publishing
              </button>
            </li>
            <li>
              <button class="dashboard-nav-link" type="button" role="tab" aria-selected="false" data-tab-target="ai">
                <i class="fa-solid fa-robot"></i> AI Content Studio
              </button>
            </li>
            <li>
              <button class="dashboard-nav-link" type="button" role="tab" aria-selected="false" data-tab-target="complaints">
                <i class="fa-solid fa-headset"></i> Complaints &amp; Service
              </button>
            </li>
            <li>
              <button class="dashboard-nav-link" type="button" role="tab" aria-selected="false" data-tab-target="governance">
                <i class="fa-solid fa-scale-balanced"></i> Governance &amp; Compliance
              </button>
            </li>
            <li>
              <button class="dashboard-nav-link" type="button" role="tab" aria-selected="false" data-tab-target="audit">
                <i class="fa-solid fa-shield-halved"></i> Audit &amp; Data Logs
              </button>
            </li>
          </ul>
          <div class="dashboard-nav-footer">
            <a class="dashboard-nav-link dashboard-nav-link--logout" href="<?= htmlspecialchars($logoutUrl, ENT_QUOTES) ?>">
              <i class="fa-solid fa-door-open"></i> Sign out
            </a>
          </div>
        </nav>

        <div class="dashboard-main">
          <section class="dashboard-section" id="overview" role="tabpanel" data-tab-panel>
            <h2>Operational overview</h2>
            <p class="dashboard-section-sub">Real-time KPIs from leads, customers, service tickets, AMC and subsidy queues.</p>
            <div class="dashboard-cards">
              <article class="dashboard-card dashboard-card--neutral">
                <div class="dashboard-card-icon" aria-hidden="true"><i class="fa-solid fa-solar-panel"></i></div>
                <div>
                  <p class="dashboard-card-title">Active customers</p>
                  <p class="dashboard-card-value" data-metric="customers"><?= htmlspecialchars((string) $customerCount, ENT_QUOTES) ?></p>
                  <p class="dashboard-card-meta">Counts update automatically when new customer logins are activated.</p>
                </div>
              </article>
              <article class="dashboard-card dashboard-card--positive">
                <div class="dashboard-card-icon" aria-hidden="true"><i class="fa-solid fa-user-gear"></i></div>
                <div>
                  <p class="dashboard-card-title">New user requests</p>
                  <p class="dashboard-card-value" data-metric="approvals"><?= htmlspecialchars((string) $pendingInvites, ENT_QUOTES) ?></p>
                  <p class="dashboard-card-meta">Pending invitations awaiting admin approval.</p>
                </div>
              </article>
              <article class="dashboard-card dashboard-card--warning">
                <div class="dashboard-card-icon" aria-hidden="true"><i class="fa-solid fa-ticket"></i></div>
                <div>
                  <p class="dashboard-card-title">Open service tickets</p>
                  <p class="dashboard-card-value" data-metric="tickets"><?= htmlspecialchars((string) $openComplaints, ENT_QUOTES) ?></p>
                  <p class="dashboard-card-meta">Ticket volume updates after service data refresh.</p>
                </div>
              </article>
              <article class="dashboard-card">
                <div class="dashboard-card-icon" aria-hidden="true"><i class="fa-solid fa-indian-rupee-sign"></i></div>
                <div>
                  <p class="dashboard-card-title">Subsidy pipeline</p>
                  <p class="dashboard-card-value" data-metric="subsidy"><?= htmlspecialchars($subsidyPipeline, ENT_QUOTES) ?></p>
                  <p class="dashboard-card-meta">Financial progress appears when subsidy data loads.</p>
                </div>
              </article>
            </div>
          </section>

          <section class="dashboard-section" id="analytics" role="tabpanel" data-tab-panel hidden>
            <h2>Analytics &amp; KPIs</h2>
            <p class="dashboard-section-sub">
              Visualise operational throughput, installer productivity, subsidy funnel performance, and complaint resolution speed
              with export-ready summaries.
            </p>
            <div class="dashboard-profile-grid">
              <article class="dashboard-form dashboard-form--list">
                <h3>Executive KPI highlights</h3>
                <ul class="dashboard-list" data-analytics-kpis>
                  <li class="dashboard-list-empty">
                    <p class="primary">No KPI metrics loaded.</p>
                    <p class="secondary">Live analytics service will hydrate this section.</p>
                  </li>
                </ul>
                <p class="dashboard-form-note">
                  <i class="fa-solid fa-chart-simple" aria-hidden="true"></i>
                  Example: Average complaint resolution time currently <strong>1.8 days</strong> and trending downward month on month.
                </p>
              </article>

              <article class="dashboard-form">
                <h3>Installer productivity</h3>
                <div class="dashboard-table-wrapper" role="region">
                  <table class="dashboard-table">
                    <thead>
                      <tr>
                        <th scope="col">Installer</th>
                        <th scope="col">Installs (30d)</th>
                        <th scope="col">AMC visits</th>
                      </tr>
                    </thead>
                    <tbody data-analytics-installer>
                      <tr class="dashboard-empty-row">
                        <td colspan="3">No installer metrics recorded.</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <p class="dashboard-form-note">
                  <i class="fa-solid fa-user-helmet-safety" aria-hidden="true"></i>
                  Track utilisation to balance installation versus AMC commitments per crew.
                </p>
              </article>

              <article class="dashboard-form">
                <h3>Lead conversion funnel</h3>
                <div class="dashboard-table-wrapper" role="region">
                  <table class="dashboard-table">
                    <thead>
                      <tr>
                        <th scope="col">Stage</th>
                        <th scope="col">Volume</th>
                        <th scope="col">Conversion</th>
                      </tr>
                    </thead>
                    <tbody data-analytics-funnel>
                      <tr class="dashboard-empty-row">
                        <td colspan="3">Waiting on CRM sync.</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <p class="dashboard-form-note">
                  <i class="fa-solid fa-timeline" aria-hidden="true"></i>
                  Follow subsidy funnel drop-off to focus on stalled proposals before deadlines.
                </p>
              </article>

              <article class="dashboard-form">
                <h3>Reporting</h3>
                <div class="dashboard-form-grid dashboard-form-grid--two">
                  <label>
                    Export format
                    <select name="kpiExport">
                      <option value="xlsx">Excel workbook</option>
                      <option value="csv">CSV</option>
                      <option value="pdf">PDF dashboard</option>
                    </select>
                  </label>
                  <label>
                    Period
                    <select name="kpiPeriod">
                      <option value="monthly" selected>Last month</option>
                      <option value="quarterly">Quarter to date</option>
                      <option value="yearly">Financial year</option>
                    </select>
                  </label>
                </div>
                <div>
                  <button type="button" class="btn btn-secondary" data-action="export-kpi">
                    <i class="fa-solid fa-file-export" aria-hidden="true"></i>
                    Export KPI report
                  </button>
                  <button type="button" class="btn btn-ghost" data-action="refresh-kpi">Refresh data</button>
                </div>
                <div class="dashboard-inline-status" data-analytics-status hidden></div>
              </article>
            </div>
          </section>

          <section class="dashboard-section" id="onboarding" role="tabpanel" data-tab-panel hidden>
            <h2>Onboarding &amp; approvals</h2>
            <p class="dashboard-section-sub">
              Admins can directly create login credentials for any role. Employee-invited accounts remain pending until you approve
              and assign permissions.
            </p>
            <div class="dashboard-profile-grid">
              <form class="dashboard-form" data-admin-user-form>
                <h3>Create user account</h3>
                <div class="dashboard-form-grid dashboard-form-grid--two">
                  <label>
                    Full name
                    <input type="text" name="fullName" placeholder="Rohan Sharma" required />
                  </label>
                  <label>
                    Email address
                    <input type="email" name="email" placeholder="name@example.com" required />
                  </label>
                  <label>
                    Username
                    <input type="text" name="username" placeholder="rohan.sharma" minlength="3" required />
                  </label>
                  <label>
                    Temporary password
                    <input type="password" name="password" placeholder="Generate secure password" minlength="6" required />
                  </label>
                  <label>
                    Role
                    <select name="role" required>
                      <?php foreach ($availableRoles as $role): ?>
                      <option value="<?= htmlspecialchars($role['name'], ENT_QUOTES) ?>"><?= htmlspecialchars(ucfirst($role['name']), ENT_QUOTES) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </label>
                  <label>
                    Permissions note
                    <input type="text" name="permissions" placeholder="e.g. View solar proposals" />
                  </label>
                </div>
                <p class="dashboard-form-note">
                  <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                  Credentials are stored securely and can be reset anytime from the user roster.
                </p>
                <div>
                  <button type="submit" class="btn btn-secondary">Create user</button>
                  <button type="reset" class="btn btn-ghost">Clear</button>
                </div>
              </form>

              <section class="dashboard-form dashboard-form--list">
                <h3>Pending employee invitations</h3>
                <ul class="dashboard-list" data-pending-list>
                  <li class="dashboard-list-empty">
                    <p class="primary">No pending invitations logged.</p>
                    <p class="secondary">Employee-submitted profiles will appear here for your approval.</p>
                  </li>
                </ul>
                <form class="dashboard-inline-form" data-invite-form>
                  <h4>Record new invitation</h4>
                  <div class="dashboard-inline-fields">
                    <label>
                      Name
                      <input type="text" name="name" placeholder="Amit Verma" required />
                    </label>
                    <label>
                      Email
                      <input type="email" name="email" placeholder="amit@example.com" required />
                    </label>
                    <label>
                      Role requested
                      <select name="role" required>
                        <?php foreach ($availableRoles as $role): ?>
                        <option value="<?= htmlspecialchars($role['name'], ENT_QUOTES) ?>"><?= htmlspecialchars(ucfirst($role['name']), ENT_QUOTES) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </label>
                    <label>
                      Submitted by employee
                      <input type="text" name="submittedBy" placeholder="Priya" required />
                    </label>
                  </div>
                  <button type="submit" class="btn btn-secondary btn-sm">Log invitation</button>
                </form>
              </section>
            </div>

            <section class="dashboard-form dashboard-form--table">
              <h3>All platform users</h3>
              <div class="dashboard-table-toolbar">
                <label class="dashboard-filter">
                  Filter by role
                  <select data-user-filter>
                    <option value="all">All roles</option>
                    <?php foreach ($availableRoles as $role): ?>
                    <option value="<?= htmlspecialchars($role['name'], ENT_QUOTES) ?>"><?= htmlspecialchars(ucfirst($role['name']), ENT_QUOTES) ?></option>
                    <?php endforeach; ?>
                  </select>
                </label>
                <span class="dashboard-table-meta" data-user-count>0 users</span>
              </div>
              <div class="dashboard-table-wrapper" role="region" aria-live="polite">
                <table class="dashboard-table">
                  <thead>
                    <tr>
                      <th scope="col">Name</th>
                      <th scope="col">Role</th>
                      <th scope="col">Username</th>
                      <th scope="col">Status</th>
                      <th scope="col">Credentials</th>
                      <th scope="col">Actions</th>
                    </tr>
                  </thead>
                  <tbody data-user-table-body>
                    <tr class="dashboard-empty-row">
                      <td colspan="6">No users created yet.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </section>
          </section>

          <section class="dashboard-section" id="tasks" role="tabpanel" data-tab-panel hidden>
            <h2>Tasks &amp; workload coordination</h2>
            <p class="dashboard-section-sub">
              Create tasks for employees and installers, track execution through Kanban stages, and monitor workload balance in
              one place.
            </p>
            <div class="dashboard-profile-grid">
              <form class="dashboard-form" data-task-form>
                <h3>Create &amp; assign task</h3>
                <div class="dashboard-form-grid dashboard-form-grid--two">
                  <label>
                    Task title
                    <input type="text" name="title" placeholder="Site inspection at Hatia" required />
                  </label>
                  <label>
                    Assign to
                    <select name="assignee" required data-task-assignee>
                      <option value="">Select team member…</option>
                    </select>
                  </label>
                  <label>
                    Status
                    <select name="status" required>
                      <option value="todo">To Do</option>
                      <option value="in_progress">In Progress</option>
                      <option value="done">Done</option>
                    </select>
                  </label>
                  <label>
                    Due date
                    <input type="date" name="dueDate" />
                  </label>
                  <label>
                    Priority
                    <select name="priority">
                      <option value="medium" selected>Medium</option>
                      <option value="high">High</option>
                      <option value="low">Low</option>
                    </select>
                  </label>
                  <label>
                    Linked record
                    <input type="text" name="linkedTo" placeholder="Customer #C-220" />
                  </label>
                </div>
                <label>
                  Notes
                  <textarea name="notes" rows="3" placeholder="Add site access instructions or material checklist"></textarea>
                </label>
                <p class="dashboard-form-note">
                  <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                  Tasks instantly appear on the Kanban board and workload summary once saved.
                </p>
                <div>
                  <button type="submit" class="btn btn-secondary">Add task</button>
                  <button type="reset" class="btn btn-ghost">Clear</button>
                </div>
              </form>

              <section class="dashboard-form dashboard-form--list">
                <h3>Task board</h3>
                <div class="dashboard-lists dashboard-lists--columns" data-task-board>
                  <div class="dashboard-list">
                    <header>
                      <i class="fa-solid fa-clipboard-list" aria-hidden="true"></i>
                      <h3>To Do</h3>
                    </header>
                    <ul data-task-column="todo">
                      <li class="dashboard-list-empty" data-task-empty="todo">
                        <p class="primary">No tasks queued.</p>
                        <p class="secondary">Create assignments to populate this stage.</p>
                      </li>
                    </ul>
                  </div>
                  <div class="dashboard-list">
                    <header>
                      <i class="fa-solid fa-person-digging" aria-hidden="true"></i>
                      <h3>In Progress</h3>
                    </header>
                    <ul data-task-column="in_progress">
                      <li class="dashboard-list-empty" data-task-empty="in_progress">
                        <p class="primary">No active work.</p>
                        <p class="secondary">Move tasks here as field teams begin execution.</p>
                      </li>
                    </ul>
                  </div>
                  <div class="dashboard-list">
                    <header>
                      <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                      <h3>Done</h3>
                    </header>
                    <ul data-task-column="done">
                      <li class="dashboard-list-empty" data-task-empty="done">
                        <p class="primary">Nothing closed yet.</p>
                        <p class="secondary">Completed work will roll into this column.</p>
                      </li>
                    </ul>
                  </div>
                </div>
              </section>

              <article class="dashboard-form">
                <h3>Workload distribution</h3>
                <div class="dashboard-form-grid dashboard-form-grid--two">
                  <label>
                    Overall tasks
                    <input type="text" data-workload-summary value="0 open" readonly />
                  </label>
                  <label>
                    Installers vs. employees
                    <input type="text" data-workload-split value="0 / 0" readonly />
                  </label>
                </div>
                <div class="dashboard-table-wrapper" role="region">
                  <table class="dashboard-table">
                    <thead>
                      <tr>
                        <th scope="col">Team member</th>
                        <th scope="col">Role</th>
                        <th scope="col">To Do</th>
                        <th scope="col">In Progress</th>
                        <th scope="col">Done (7d)</th>
                      </tr>
                    </thead>
                    <tbody data-workload-table>
                      <tr class="dashboard-empty-row">
                        <td colspan="5">No workload recorded yet.</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <p class="dashboard-form-note">
                  <i class="fa-solid fa-user-group" aria-hidden="true"></i>
                  Quickly assess utilisation before assigning the next ticket or inspection.
                </p>
              </article>
            </div>
          </section>

          <section class="dashboard-section" id="installations" role="tabpanel" data-tab-panel hidden>
            <h2>Installation, warranty &amp; AMC orchestration</h2>
            <p class="dashboard-section-sub">
              Assign field crews, capture photo checklists, activate warranty cards, and keep AMC visits on schedule from one place.
            </p>
            <div class="dashboard-profile-grid">
              <form class="dashboard-form" data-installation-form>
                <h3>Assign installation visit</h3>
                <div class="dashboard-form-grid dashboard-form-grid--two">
                  <label>
                    Customer name
                    <input type="text" name="customer" placeholder="Meena Gupta" required />
                  </label>
                  <label>
                    System size
                    <input type="text" name="systemSize" placeholder="5 kW rooftop" required />
                  </label>
                  <label>
                    Site location
                    <input type="text" name="location" placeholder="Ranchi, Jharkhand" required />
                  </label>
                  <label>
                    Installer
                    <select name="installer" required data-installation-assignee>
                      <option value="">Select installer…</option>
                    </select>
                  </label>
                  <label>
                    Visit date
                    <input type="date" name="visitDate" required />
                  </label>
                  <label>
                    Visit time
                    <input type="time" name="visitTime" value="10:00" required />
                  </label>
                </div>
                <label>
                  Notes for crew
                  <textarea name="notes" rows="3" placeholder="Upload inverter photos, checklist confirmations, and customer signature."></textarea>
                </label>
                <div class="dashboard-form-note">
                  <i class="fa-solid fa-calendar-check" aria-hidden="true"></i>
                  Example: Assign installation visit for <strong>Customer Meena Gupta</strong> on <strong>05-Nov</strong> with instant AMC activation after completion.
                </div>
                <div>
                  <button type="submit" class="btn btn-secondary">Schedule visit</button>
                  <button type="reset" class="btn btn-ghost">Reset</button>
                </div>
              </form>

              <article class="dashboard-form">
                <h3>Installation queue</h3>
                <div class="dashboard-form-grid dashboard-form-grid--two">
                  <label>
                    Progress
                    <input type="text" data-installation-summary value="0/0 completed" readonly />
                  </label>
                  <label>
                    Photo checklist status
                    <input type="text" data-installation-checklist value="0 pending" readonly />
                  </label>
                </div>
                <div class="dashboard-table-wrapper" role="region">
                  <table class="dashboard-table">
                    <thead>
                      <tr>
                        <th scope="col">Visit</th>
                        <th scope="col">Installer</th>
                        <th scope="col">Schedule</th>
                        <th scope="col">Checklist</th>
                        <th scope="col">Status</th>
                        <th scope="col">Actions</th>
                      </tr>
                    </thead>
                    <tbody data-installation-table>
                      <tr class="dashboard-empty-row">
                        <td colspan="6">No installation visits planned.</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </article>

              <article class="dashboard-form">
                <h3>Warranty register</h3>
                <div class="dashboard-table-wrapper" role="region">
                  <table class="dashboard-table">
                    <thead>
                      <tr>
                        <th scope="col">Warranty ID</th>
                        <th scope="col">Customer</th>
                        <th scope="col">Product</th>
                        <th scope="col">Registered</th>
                        <th scope="col">Expires</th>
                        <th scope="col">Status</th>
                      </tr>
                    </thead>
                    <tbody data-warranty-table>
                      <tr class="dashboard-empty-row">
                        <td colspan="6">No warranties activated yet.</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </article>

              <article class="dashboard-form">
                <h3>AMC schedule &amp; reminders</h3>
                <ul class="dashboard-reminders" data-amc-list>
                  <li>
                    <p>No AMC visits scheduled.</p>
                    <span>Complete an installation to auto-create the AMC plan.</span>
                  </li>
                </ul>
              </article>
            </div>
          </section>

          <section class="dashboard-section" id="documents" role="tabpanel" data-tab-panel hidden>
            <h2>Document vault &amp; version control</h2>
            <p class="dashboard-section-sub">
              Keep subsidy artefacts, customer paperwork, and ticket evidence organised with automatic version history.
            </p>
            <div class="dashboard-profile-grid">
              <form class="dashboard-form" data-document-form>
                <h3>Upload or replace file</h3>
                <div class="dashboard-form-grid dashboard-form-grid--two">
                  <label>
                    Document name
                    <input type="text" name="name" placeholder="Subsidy inspection report" required />
                  </label>
                  <label>
                    Linked to
                    <select name="linkedTo" required>
                      <option value="">Choose record…</option>
                      <option value="customer">Customer</option>
                      <option value="ticket">Ticket</option>
                      <option value="lead">Lead</option>
                    </select>
                  </label>
                  <label>
                    Reference ID
                    <input type="text" name="reference" placeholder="Customer #C-220" />
                  </label>
                  <label>
                    Tags
                    <input type="text" name="tags" placeholder="inspection, subsidy" />
                  </label>
                </div>
                <label>
                  Secure file URL
                  <input type="url" name="url" placeholder="https://vault.example.com/report.pdf" />
                </label>
                <p class="dashboard-form-note">
                  <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                  Files stay encrypted at rest. Re-uploads automatically increment the version counter.
                </p>
                <div>
                  <button type="submit" class="btn btn-secondary">Save to vault</button>
                  <button type="reset" class="btn btn-ghost">Reset</button>
                </div>
              </form>

              <article class="dashboard-form">
                <h3>Vault catalogue</h3>
                <div class="dashboard-form-grid dashboard-form-grid--two">
                  <label>
                    Filter by visibility
                    <select name="visibility" data-document-filter>
                      <option value="all">All records</option>
                      <option value="customer">Customers</option>
                      <option value="ticket">Tickets</option>
                      <option value="lead">Leads</option>
                    </select>
                  </label>
                  <label>
                    Download control
                    <input type="text" value="Admin gated" readonly />
                  </label>
                </div>
                <div class="dashboard-table-wrapper" role="region">
                  <table class="dashboard-table">
                    <thead>
                      <tr>
                        <th scope="col">Document</th>
                        <th scope="col">Linked record</th>
                        <th scope="col">Version</th>
                        <th scope="col">Tags</th>
                        <th scope="col">Updated</th>
                      </tr>
                    </thead>
                    <tbody data-document-table>
                      <tr class="dashboard-empty-row">
                        <td colspan="5">No documents stored yet.</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <p class="dashboard-form-note">
                  <i class="fa-solid fa-tag" aria-hidden="true"></i>
                  Tag documents for faster retrieval during audits or subsidy reviews.
                </p>
              </article>
            </div>
          </section>

          <section class="dashboard-section" id="data-quality" role="tabpanel" data-tab-panel hidden>
            <h2>Data quality &amp; pending approvals</h2>
            <p class="dashboard-section-sub">
              Enforce validation rules, merge duplicates, and close out employee-submitted change requests.
            </p>
            <div class="dashboard-profile-grid">
              <article class="dashboard-form dashboard-form--list">
                <h3>Validation coverage</h3>
                <ul class="dashboard-list" data-validation-list>
                  <li class="dashboard-list-empty">
                    <p class="primary">No validation rules loaded.</p>
                    <p class="secondary">Bootstrap will hydrate the matrix of enforced fields.</p>
                  </li>
                </ul>
                <p class="dashboard-form-note">
                  <i class="fa-solid fa-square-check" aria-hidden="true"></i>
                  Phone, pincode, date, yes/no, and system-size fields are monitored for accuracy.
                </p>
              </article>

              <article class="dashboard-form dashboard-form--list">
                <h3>Duplicate review queue</h3>
                <ul class="dashboard-list" data-duplicate-list>
                  <li class="dashboard-list-empty">
                    <p class="primary">No duplicates flagged.</p>
                    <p class="secondary">CRM syncing will surface potential merge candidates here.</p>
                  </li>
                </ul>
              </article>

              <article class="dashboard-form dashboard-form--list">
                <h3>Approvals awaiting action</h3>
                <ul class="dashboard-list" data-approval-list>
                  <li class="dashboard-list-empty">
                    <p class="primary">No pending approvals.</p>
                    <p class="secondary">Employee-initiated updates will require sign-off before publishing.</p>
                  </li>
                </ul>
              </article>
            </div>
          </section>

          <section class="dashboard-section" id="crm" role="tabpanel" data-tab-panel hidden>
            <h2>Leads, customers &amp; CRM</h2>
            <p class="dashboard-section-sub">
              Track the full lifecycle from lead capture through PM Surya Ghar conversion and installation scheduling.
            </p>
            <div class="dashboard-profile-grid">
              <article class="dashboard-form">
                <h3>Lead pipeline</h3>
                <div class="dashboard-form-grid dashboard-form-grid--two">
                  <label>
                    Active leads
                    <input type="text" data-lead-count value="0" readonly />
                  </label>
                  <label>
                    Conversion rate
                    <input type="text" data-lead-conversion value="0%" readonly />
                  </label>
                </div>
                <div class="dashboard-table-wrapper" role="region">
                  <table class="dashboard-table">
                    <thead>
                      <tr>
                        <th scope="col">Lead</th>
                        <th scope="col">Source</th>
                        <th scope="col">Interest</th>
                        <th scope="col">Status</th>
                        <th scope="col">Actions</th>
                      </tr>
                    </thead>
                    <tbody data-leads-table>
                      <tr class="dashboard-empty-row">
                        <td colspan="5">No leads recorded.</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </article>

              <article class="dashboard-form">
                <h3>Customers under PM Surya Ghar</h3>
                <div class="dashboard-form-grid dashboard-form-grid--two">
                  <label>
                    Active customers
                    <input type="text" data-customer-count value="0" readonly />
                  </label>
                  <label>
                    Upcoming installations
                    <input type="text" data-installation-count value="0" readonly />
                  </label>
                </div>
                <div class="dashboard-table-wrapper" role="region">
                  <table class="dashboard-table">
                    <thead>
                      <tr>
                        <th scope="col">Customer</th>
                        <th scope="col">System size</th>
                        <th scope="col">Schedule</th>
                        <th scope="col">Lead ref</th>
                        <th scope="col">Actions</th>
                      </tr>
                    </thead>
                    <tbody data-customers-table>
                      <tr class="dashboard-empty-row">
                        <td colspan="5">No PM Surya Ghar customers.</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <p class="dashboard-form-note">
                  <i class="fa-solid fa-file-import" aria-hidden="true"></i>
                  Import or export CSV records with validation using the quick actions below.
                </p>
                <div class="dashboard-inline-actions">
                  <button type="button" class="btn btn-ghost" data-action="import-crm">Import CSV</button>
                  <button type="button" class="btn btn-tertiary" data-action="export-crm">Export CSV</button>
                </div>
              </article>
            </div>
          </section>

          <section class="dashboard-section" id="referrers" role="tabpanel" data-tab-panel hidden>
            <h2>Referrers &amp; partners</h2>
            <p class="dashboard-section-sub">
              Verify KYC and bank details, then track conversion success for each referral partner.
            </p>
            <div class="dashboard-profile-grid">
              <article class="dashboard-form">
                <h3>Partner roster</h3>
                <div class="dashboard-form-grid dashboard-form-grid--two">
                  <label>
                    Verified partners
                    <input type="text" data-referrer-verified value="0" readonly />
                  </label>
                  <label>
                    Leads this month
                    <input type="text" data-referrer-leads value="0" readonly />
                  </label>
                </div>
                <div class="dashboard-table-wrapper" role="region">
                  <table class="dashboard-table">
                    <thead>
                      <tr>
                        <th scope="col">Partner</th>
                        <th scope="col">KYC</th>
                        <th scope="col">Leads</th>
                        <th scope="col">Conversions</th>
                        <th scope="col">Payout</th>
                        <th scope="col">Actions</th>
                      </tr>
                    </thead>
                    <tbody data-referrer-table>
                      <tr class="dashboard-empty-row">
                        <td colspan="6">No partners registered.</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </article>

              <article class="dashboard-form dashboard-form--list">
                <h3>Highlights</h3>
                <ul class="dashboard-list" data-referrer-summary>
                  <li class="dashboard-list-empty">
                    <p class="primary">No partner activity logged.</p>
                    <p class="secondary">Approved referrers and installers will surface key stats here.</p>
                  </li>
                </ul>
              </article>
            </div>
          </section>

          <section class="dashboard-section" id="subsidy" role="tabpanel" data-tab-panel hidden>
            <h2>Subsidy (PM Surya Ghar) pipeline</h2>
            <p class="dashboard-section-sub">
              Move applications through Applied → Sanctioned → Inspected → Redeemed → Closed while tracking SLA progress.
            </p>
            <div class="dashboard-cards dashboard-cards--stretch">
              <article class="dashboard-card">
                <div class="dashboard-card-icon" aria-hidden="true"><i class="fa-solid fa-file-pen"></i></div>
                <div>
                  <p class="dashboard-card-title">Applied</p>
                  <p class="dashboard-card-value" data-subsidy-stage="applied">0</p>
                  <p class="dashboard-card-meta">Applications submitted and awaiting sanction.</p>
                </div>
              </article>
              <article class="dashboard-card">
                <div class="dashboard-card-icon" aria-hidden="true"><i class="fa-solid fa-file-signature"></i></div>
                <div>
                  <p class="dashboard-card-title">Sanctioned</p>
                  <p class="dashboard-card-value" data-subsidy-stage="sanctioned">0</p>
                  <p class="dashboard-card-meta">Approved by DISCOM and ready for inspection scheduling.</p>
                </div>
              </article>
              <article class="dashboard-card">
                <div class="dashboard-card-icon" aria-hidden="true"><i class="fa-solid fa-binoculars"></i></div>
                <div>
                  <p class="dashboard-card-title">Inspected</p>
                  <p class="dashboard-card-value" data-subsidy-stage="inspected">0</p>
                  <p class="dashboard-card-meta">Field verification completed, awaiting redemption.</p>
                </div>
              </article>
              <article class="dashboard-card">
                <div class="dashboard-card-icon" aria-hidden="true"><i class="fa-solid fa-sack-dollar"></i></div>
                <div>
                  <p class="dashboard-card-title">Redeemed</p>
                  <p class="dashboard-card-value" data-subsidy-stage="redeemed">0</p>
                  <p class="dashboard-card-meta">Funds disbursed to beneficiaries.</p>
                </div>
              </article>
              <article class="dashboard-card">
                <div class="dashboard-card-icon" aria-hidden="true"><i class="fa-solid fa-circle-check"></i></div>
                <div>
                  <p class="dashboard-card-title">Closed</p>
                  <p class="dashboard-card-value" data-subsidy-stage="closed">0</p>
                  <p class="dashboard-card-meta">All documentation archived and subsidy closed.</p>
                </div>
              </article>
            </div>
            <div class="dashboard-profile-grid">
              <article class="dashboard-form">
                <h3>Pipeline tracker</h3>
                <label>
                  Average processing time (days)
                  <input type="text" data-subsidy-average value="0" readonly />
                </label>
                <div class="dashboard-table-wrapper" role="region">
                  <table class="dashboard-table">
                    <thead>
                      <tr>
                        <th scope="col">Application</th>
                        <th scope="col">Customer</th>
                        <th scope="col">Capacity</th>
                        <th scope="col">Stage</th>
                        <th scope="col">Last update</th>
                        <th scope="col">Actions</th>
                      </tr>
                    </thead>
                    <tbody data-subsidy-table>
                      <tr class="dashboard-empty-row">
                        <td colspan="6">No subsidy applications tracked yet.</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </article>

              <article class="dashboard-form dashboard-form--list">
                <h3>Stage checklist</h3>
                <ul class="dashboard-list">
                  <li>
                    <p class="primary">Applied → Sanctioned</p>
                    <p class="secondary">Ensure application form, Aadhaar, and load sanction letter uploaded.</p>
                  </li>
                  <li>
                    <p class="primary">Sanctioned → Inspected</p>
                    <p class="secondary">Schedule inspection after payment verification and installation photos.</p>
                  </li>
                  <li>
                    <p class="primary">Inspected → Redeemed</p>
                    <p class="secondary">Upload inspection report and DISCOM approval letter.</p>
                  </li>
                  <li>
                    <p class="primary">Redeemed → Closed</p>
                    <p class="secondary">Confirm subsidy receipt and archive completion certificate.</p>
                  </li>
                </ul>
              </article>
            </div>
          </section>

          <section class="dashboard-section" id="access" role="tabpanel" data-tab-panel hidden>
            <h2>Login &amp; access control</h2>
            <p class="dashboard-section-sub">
              Configure retry limits, lockout policies, and monitor access assignments across every portal role.
            </p>
            <div class="dashboard-profile-grid">
              <form class="dashboard-form" data-login-policy>
                <h3>Authentication controls</h3>
                <div class="dashboard-form-grid dashboard-form-grid--two">
                  <label>
                    Maximum retry attempts
                    <input type="number" name="retry" value="5" min="3" max="10" />
                  </label>
                  <label>
                    Lockout duration (minutes)
                    <input type="number" name="lockout" value="30" min="5" max="120" />
                  </label>
                  <label>
                    2FA enforcement
                    <select name="twofactor">
                      <option value="all" selected>All roles (recommended)</option>
                      <option value="admin">Admin only</option>
                      <option value="none">Disabled</option>
                    </select>
                  </label>
                  <label>
                    Default session timeout (minutes)
                    <input type="number" name="session" value="45" min="15" max="240" />
                  </label>
                </div>
                <p class="dashboard-form-note">
                  <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                  CSRF tokens rotate every login · Email OTP fallback auto-enforced for administrators.
                </p>
                <div>
                  <button type="submit" class="btn btn-secondary">Save policies</button>
                  <button type="reset" class="btn btn-ghost">Reset</button>
                </div>
              </form>

              <section class="dashboard-form dashboard-form--list">
                <h3>Role assignments snapshot</h3>
                <ul class="dashboard-list" data-role-assignment-list>
                  <li class="dashboard-list-empty">
                    <p class="primary">No active users recorded.</p>
                    <p class="secondary">Create users or approve invitations to populate this summary.</p>
                  </li>
                </ul>
                <p class="dashboard-form-note">
                  <i class="fa-solid fa-shield-check" aria-hidden="true"></i>
                  Review the user roster to deactivate dormant accounts or adjust permissions.
                </p>
              </section>

              <form class="dashboard-form" data-password-form>
                <h3>Change administrator password</h3>
                <div class="dashboard-form-grid dashboard-form-grid--two">
                  <label>
                    Current password
                    <input type="password" name="currentPassword" autocomplete="current-password" required />
                  </label>
                  <label>
                    New password
                    <input type="password" name="newPassword" autocomplete="new-password" minlength="8" required />
                  </label>
                  <label>
                    Confirm new password
                    <input type="password" name="confirmPassword" autocomplete="new-password" minlength="8" required />
                  </label>
                </div>
                <p class="dashboard-form-note">
                  <i class="fa-solid fa-key" aria-hidden="true"></i>
                  Use at least 8 characters, mixing uppercase, lowercase, numbers, and symbols for stronger security.
                </p>
                <div>
                  <button type="submit" class="btn btn-secondary" data-action="change-password">Update password</button>
                  <button type="reset" class="btn btn-ghost">Clear</button>
                </div>
                <div class="dashboard-inline-status" data-password-status hidden>
                  <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                  <div>
                    <strong>Password status</strong>
                    <p>Submit the form to update your credentials.</p>
                  </div>
                </div>
              </form>
            </div>
          </section>

          <section class="dashboard-section" id="health" role="tabpanel" data-tab-panel hidden>
            <h2>Backups, retention &amp; system health</h2>
            <p class="dashboard-section-sub">Monitor uptime, backups, retention windows, and exportable audit trails for every user action.</p>
            <div class="dashboard-profile-grid">
              <article class="dashboard-form">
                <h3>Health summary</h3>
                <div class="dashboard-form-grid dashboard-form-grid--two">
                  <label>
                    Uptime (7 days)
                    <input type="text" value="<?= htmlspecialchars($uptime, ENT_QUOTES) ?>" readonly aria-label="Service uptime across the last seven days" data-system-metric="uptime" />
                  </label>
                  <label>
                    Error logs (24h)
                    <input type="text" value="<?= htmlspecialchars($errorCount, ENT_QUOTES) ?>" readonly aria-label="Errors recorded in the last 24 hours" data-system-metric="errors_24h" />
                  </label>
                  <label>
                    Last backup
                    <input type="text" value="<?= htmlspecialchars($lastBackup, ENT_QUOTES) ?>" readonly aria-label="Last successful backup timestamp" data-system-metric="last_backup" />
                  </label>
                  <label>
                    Storage utilisation
                    <input type="text" value="<?= htmlspecialchars($diskUsage, ENT_QUOTES) ?>" readonly aria-label="Current storage utilisation" data-system-metric="disk_usage" />
                  </label>
                </div>
                <p class="dashboard-form-note">
                  <i class="fa-solid fa-database" aria-hidden="true"></i>
                  Schedule exports for detailed activity, error, and notification logs.
                </p>
                <div>
                  <button type="button" class="btn btn-secondary" data-action="export-logs">
                    <i class="fa-solid fa-file-export"></i>
                    Export logs
                  </button>
                  <button type="button" class="btn btn-ghost" data-action="view-monitoring">View monitoring</button>
                </div>
              </article>

              <form class="dashboard-form" data-backup-form>
                <h3>Backup controls</h3>
                <p>Trigger an on-demand backup or confirm when the last snapshot was captured.</p>
                <div class="dashboard-form-grid dashboard-form-grid--two">
                  <label>
                    Last backup timestamp
                    <input type="text" value="<?= htmlspecialchars($lastBackup, ENT_QUOTES) ?>" readonly data-backup-last />
                  </label>
                  <label>
                    Storage remaining
                    <input type="text" value="<?= htmlspecialchars($diskUsage, ENT_QUOTES) ?>" readonly data-backup-storage />
                  </label>
                </div>
                <div>
                  <button type="button" class="btn btn-secondary" data-action="run-backup">
                    <i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i>
                    Run backup now
                  </button>
                  <button type="button" class="btn btn-ghost" data-action="verify-backup">Verify latest backup</button>
                </div>
                <div class="dashboard-inline-status" data-backup-status hidden></div>
              </form>

              <form class="dashboard-form" data-backup-schedule-form>
                <h3>Auto-backup schedule</h3>
                <div class="dashboard-form-grid dashboard-form-grid--two">
                  <label>
                    Frequency
                    <select name="backupFrequency" required>
                      <option value="nightly" selected>Nightly</option>
                      <option value="weekly">Weekly</option>
                      <option value="monthly">Monthly</option>
                    </select>
                  </label>
                  <label>
                    Backup time
                    <input type="time" name="backupTime" value="02:00" required />
                  </label>
                </div>
                <label>
                  Notification email
                  <input type="email" name="backupEmail" placeholder="ops@dentweb.in" value="ops@dentweb.in" required />
                </label>
                <div>
                  <button type="submit" class="btn btn-secondary">Save schedule</button>
                  <button type="reset" class="btn btn-ghost">Reset</button>
                </div>
                <div class="dashboard-inline-status" data-backup-schedule-status hidden></div>
              </form>

              <form class="dashboard-form" data-retention-form>
                <h3>Retention policy</h3>
                <p>Archive operational logs after 90 days and automatically purge older data for compliance.</p>
                <div class="dashboard-form-grid dashboard-form-grid--two">
                  <label>
                    Archive after (days)
                    <input
                      type="number"
                      name="archiveDays"
                      min="30"
                      max="365"
                      value="<?= htmlspecialchars((string) $retentionSettings['archiveDays'], ENT_QUOTES) ?>"
                      required
                      data-retention-archive
                    />
                  </label>
                  <label>
                    Purge after (days)
                    <input
                      type="number"
                      name="purgeDays"
                      min="60"
                      max="730"
                      value="<?= htmlspecialchars((string) $retentionSettings['purgeDays'], ENT_QUOTES) ?>"
                      required
                      data-retention-purge
                    />
                  </label>
                </div>
                <label class="dashboard-toggle">
                  <input type="checkbox" name="includeAudit" value="1" <?= $retentionSettings['includeAudit'] ? 'checked' : '' ?> data-retention-audit />
                  <span>Include audit log archives</span>
                </label>
                <p class="dashboard-form-note">
                  <i class="fa-solid fa-box-archive" aria-hidden="true"></i>
                  Logs archive after <?= htmlspecialchars((string) $retentionSettings['archiveDays'], ENT_QUOTES) ?> days and purge after <?= htmlspecialchars((string) $retentionSettings['purgeDays'], ENT_QUOTES) ?> days by default.
                </p>
                <div>
                  <button type="submit" class="btn btn-secondary">Save retention rule</button>
                  <button type="reset" class="btn btn-ghost">Reset</button>
                </div>
                <div class="dashboard-inline-status" data-retention-status hidden></div>
              </form>

              <article class="dashboard-form">
                <h3>Recent activity feed</h3>
                <ul class="dashboard-notifications" data-placeholder="activity-feed">
                  <li class="dashboard-notification dashboard-notification--info">
                    <i class="fa-solid fa-bolt" aria-hidden="true"></i>
                    <div>
                      <p>No recent activity to display.</p>
                      <span>Live audit events will appear after integration.</span>
                    </div>
                  </li>
                </ul>
              </article>
            </div>
          </section>

          <section class="dashboard-section" id="blog" role="tabpanel" data-tab-panel hidden>
            <h2>Blog publishing</h2>
            <p class="dashboard-section-sub">
              Draft, review, and publish posts for the public blog. Only published entries appear on the website; drafts and archived posts stay internal.
            </p>
            <div class="dashboard-profile-grid">
              <section class="dashboard-form dashboard-form--table" aria-labelledby="blog-posts-heading">
                <div class="dashboard-table-toolbar">
                  <div>
                    <h3 id="blog-posts-heading">Posts overview</h3>
                    <p class="dashboard-form-note">Statuses update instantly after saving or publishing.</p>
                  </div>
                  <button type="button" class="btn btn-secondary btn-sm" data-blog-new>
                    <i class="fa-solid fa-plus"></i> New post
                  </button>
                </div>
                <div class="dashboard-table-wrapper" role="region" aria-live="polite">
                  <table class="dashboard-table">
                    <thead>
                      <tr>
                        <th scope="col">Title</th>
                        <th scope="col">Status</th>
                        <th scope="col">Updated</th>
                        <th scope="col">Published</th>
                        <th scope="col" class="text-right">Actions</th>
                      </tr>
                    </thead>
                    <tbody data-blog-post-table>
                      <tr class="dashboard-empty-row">
                        <td colspan="5">No blog posts yet. Create one to get started.</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </section>

              <form class="dashboard-form" data-blog-form novalidate>
                <h3>Post editor</h3>
                <input type="hidden" name="id" value="" />
                <div class="dashboard-form-grid dashboard-form-grid--two">
                  <label>
                    Title
                    <input type="text" name="title" required maxlength="160" />
                  </label>
                  <label>
                    Slug
                    <input type="text" name="slug" placeholder="auto-generated if empty" maxlength="160" />
                  </label>
                  <label>
                    Author name (optional)
                    <input type="text" name="author" maxlength="120" />
                  </label>
                  <label>
                    Tags (comma separated)
                    <input type="text" name="tags" placeholder="e.g. PM Surya Ghar, Residential" />
                  </label>
                  <label>
                    Cover image URL (optional)
                    <input type="url" name="cover" placeholder="https://… or /images/…" />
                  </label>
                  <label>
                    Cover image alt text
                    <input type="text" name="coverAlt" maxlength="180" />
                  </label>
                </div>
                <label>
                  Excerpt (shown on listing)
                  <textarea name="excerpt" rows="3" maxlength="280"></textarea>
                </label>
                <label>
                  Body content (HTML supported, sanitised on save)
                  <textarea name="body" rows="12" required></textarea>
                </label>
                <div class="dashboard-form-actions">
                  <button type="submit" class="btn btn-secondary">Save draft</button>
                  <button type="button" class="btn btn-primary" data-blog-publish>Publish</button>
                  <button type="button" class="btn btn-outline" data-blog-archive>Archive</button>
                  <button type="reset" class="btn btn-ghost" data-blog-reset>Clear</button>
                </div>
                <div class="dashboard-inline-status" data-blog-status hidden>
                  <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                  <div>
                    <strong data-blog-status-title>Blog status</strong>
                    <p data-blog-status-message>Saved changes will appear here.</p>
                  </div>
                </div>
              </form>
            </div>
          </section>

          <section class="dashboard-section" id="ai" role="tabpanel" data-tab-panel hidden>
            <h2>AI-generated content (blogs, images, audio)</h2>
            <p class="dashboard-section-sub">
              Draft posts, generate cover art, produce narrations, and manage Gemini API credentials from one workspace.
            </p>
            <form class="dashboard-form" data-gemini-form>
              <h3>API credentials</h3>
              <div class="dashboard-form-grid dashboard-form-grid--two">
                <label>
                  Gemini API key
                  <input
                    type="password"
                    name="apiKey"
                    value="<?= htmlspecialchars($geminiSettings['apiKey'] ?? '', ENT_QUOTES) ?>"
                    placeholder="Enter secure API key"
                    autocomplete="off"
                    required
                  />
                </label>
                <label>
                  Text model code
                  <input type="text" name="textModel" value="<?= htmlspecialchars($geminiSettings['textModel'] ?? 'gemini-2.5-flash', ENT_QUOTES) ?>" required />
                </label>
                <label>
                  Image model code
                  <input type="text" name="imageModel" value="<?= htmlspecialchars($geminiSettings['imageModel'] ?? 'gemini-2.5-flash-image', ENT_QUOTES) ?>" required />
                </label>
                <label>
                  TTS model code
                  <input type="text" name="ttsModel" value="<?= htmlspecialchars($geminiSettings['ttsModel'] ?? 'gemini-2.5-flash-preview-tts', ENT_QUOTES) ?>" required />
                </label>
              </div>
              <p class="dashboard-form-note">
                <i class="fa-solid fa-shield" aria-hidden="true"></i>
                API keys stay masked · Rotate quarterly or immediately after any suspected compromise.
              </p>
              <div class="dashboard-inline-status" data-gemini-status hidden>
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                <div>
                  <strong>Status</strong>
                  <p>Run a test to verify Gemini connectivity.</p>
                </div>
              </div>
              <div>
                <button type="submit" class="btn btn-secondary" data-action="save-gemini">Save settings</button>
                <button type="button" class="btn btn-ghost" data-action="reset-gemini">Reset defaults</button>
                <button type="button" class="btn btn-tertiary" data-action="test-gemini">
                  <i class="fa-solid fa-plug-circle-check" aria-hidden="true"></i>
                  Test connection
                </button>
              </div>
            </form>

            <div class="dashboard-profile-grid">
              <form class="dashboard-form" data-ai-blog-form>
                <h3>Generate AI-assisted blog</h3>
                <div class="dashboard-form-grid dashboard-form-grid--two">
                  <label>
                    Describe what you want to post about
                    <div class="dashboard-input-with-action">
                      <input
                        type="text"
                        name="topic"
                        value="Solar Benefits in Jharkhand"
                        placeholder="Share the idea, audience, or outcome you want Gemini to cover"
                        required
                        data-ai-topic
                      />
                      <button type="button" class="btn btn-outline" data-action="research-topic">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        Research
                      </button>
                    </div>
                  </label>
                  <label>
                    Tone
                    <select name="tone" data-ai-tone>
                      <option value="informative" selected>Informative</option>
                      <option value="conversational">Conversational</option>
                      <option value="technical">Technical</option>
                      <option value="promotional">Promotional</option>
                    </select>
                  </label>
                  <label>
                    Target length (words)
                    <input type="number" name="length" min="200" max="1500" value="650" data-ai-length />
                  </label>
                  <label>
                    Focus keywords
                    <input type="text" name="keywords" placeholder="PM Surya Ghar, net metering" data-ai-keywords />
                  </label>
                </div>
                <label>
                  Outline hints
                  <textarea name="outline" rows="3" placeholder="Add sections or bullet points you want the draft to include." data-ai-outline></textarea>
                </label>
                <div>
                  <button type="submit" class="btn btn-secondary" data-action="generate-blog">Generate draft</button>
                  <button type="button" class="btn btn-ghost" data-action="clear-blog">Clear</button>
                  <button type="button" class="btn btn-tertiary" data-action="publish-blog">
                    <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                    Push to Blog Publishing
                  </button>
                </div>
                <div class="dashboard-ai-preview" data-ai-blog-preview>
                  <p class="dashboard-muted">Draft output will appear here for preview and editing.</p>
                </div>
                <div class="dashboard-inline-status" data-ai-status hidden></div>
              </form>

              <article class="dashboard-form">
                <h3>Cover image generation</h3>
                <p>Gemini now pairs a cover with every draft automatically. Regenerate it here if you want a fresh visual.</p>
                <div class="dashboard-form-grid dashboard-form-grid--two">
                  <label>
                    Prompt
                    <input type="text" value="Sunlit rooftop solar panels in Ranchi" data-ai-image-prompt />
                  </label>
                  <label>
                    Aspect ratio
                    <select data-ai-image-aspect>
                      <option value="16:9" selected>16:9</option>
                      <option value="1:1">1:1</option>
                      <option value="4:5">4:5</option>
                    </select>
                  </label>
                </div>
                <div class="dashboard-ai-figure">
                  <img
                    data-ai-image
                    src="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 120 80'><rect fill='%23eef2ff' width='120' height='80'/><text x='60' y='44' font-size='10' text-anchor='middle' fill='%234256b6'>AI Cover Preview</text></svg>"
                    alt="AI generated cover preview"
                  />
                </div>
                <div>
                  <button type="button" class="btn btn-secondary" data-action="generate-image">Regenerate cover image</button>
                  <button type="button" class="btn btn-ghost" data-action="download-image">Download</button>
                </div>
                <div class="dashboard-inline-status" data-ai-image-status hidden></div>
              </article>

              <article class="dashboard-form">
                <h3>Audio narration (TTS)</h3>
                <p>Request a narration track that pairs with the generated blog.</p>
                <label>
                  Voice style
                  <select data-ai-voice>
                    <option value="neutral" selected>Neutral professional</option>
                    <option value="friendly">Friendly Hindi-English mix</option>
                    <option value="youthful">Youthful energy</option>
                  </select>
                </label>
                <div>
                  <button type="button" class="btn btn-secondary" data-action="generate-audio">
                    <i class="fa-solid fa-wave-square" aria-hidden="true"></i>
                    Generate narration
                  </button>
                  <button type="button" class="btn btn-ghost" data-action="download-audio">Download</button>
                </div>
                <audio data-ai-audio controls hidden></audio>
                <div class="dashboard-inline-status" data-ai-audio-status hidden></div>
              </article>

              <form class="dashboard-form" data-ai-schedule-form>
                <h3>Auto-blog scheduling</h3>
                <div class="dashboard-form-grid dashboard-form-grid--two">
                  <label class="dashboard-toggle">
                    <input type="checkbox" name="autoblog" value="1" data-ai-autoblog-toggle />
                    <span>Enable daily auto-generation</span>
                  </label>
                  <label>
                    Publish time
                    <input type="time" name="autoblogTime" value="09:00" data-ai-autoblog-time disabled />
                  </label>
                </div>
                <label>
                  Content theme rotation
                  <select name="autoblogTheme" data-ai-autoblog-theme disabled>
                    <option value="regional" selected>Regional success stories</option>
                    <option value="technical">Technical explainers</option>
                    <option value="policy">Policy &amp; subsidy updates</option>
                    <option value="random">Random rotation</option>
                  </select>
                </label>
                <div>
                  <button type="submit" class="btn btn-secondary">Save schedule</button>
                  <button type="reset" class="btn btn-ghost">Reset</button>
                </div>
                <div class="dashboard-inline-status" data-ai-schedule-status hidden></div>
              </form>
            </div>
          </section>

          <section class="dashboard-section" id="complaints" role="tabpanel" data-tab-panel hidden>
            <h2>Complaints &amp; service management</h2>
            <p class="dashboard-section-sub">
              Track the complaint lifecycle from intake through resolution, assign owners, and monitor SLA deadlines.
            </p>
            <div class="dashboard-profile-grid">
              <article class="dashboard-form">
                <h3>Complaint pipeline</h3>
                <div class="dashboard-lists">
                  <div class="dashboard-list">
                    <header>
                      <i class="fa-solid fa-inbox" aria-hidden="true"></i>
                      <h3>Intake</h3>
                    </header>
                    <ul>
                      <li>
                        <p class="primary" data-placeholder="complaint-intake">No complaints loaded.</p>
                        <p class="secondary">Website and internal submissions appear here for triage.</p>
                      </li>
                    </ul>
                  </div>
                  <div class="dashboard-list">
                    <header>
                      <i class="fa-solid fa-stethoscope" aria-hidden="true"></i>
                      <h3>Triage</h3>
                    </header>
                    <ul>
                      <li>
                        <p class="primary" data-placeholder="complaint-triage">No triage items available.</p>
                        <p class="secondary">Prioritized complaints appear after admin review.</p>
                      </li>
                    </ul>
                  </div>
                  <div class="dashboard-list">
                    <header>
                      <i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i>
                      <h3>Work</h3>
                    </header>
                    <ul>
                      <li>
                        <p class="primary" data-placeholder="complaint-work">Work queue empty.</p>
                        <p class="secondary">Assigned employees will track SLA progress from this view.</p>
                      </li>
                    </ul>
                  </div>
                  <div class="dashboard-list">
                    <header>
                      <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                      <h3>Resolution</h3>
                    </header>
                    <ul>
                      <li>
                        <p class="primary" data-placeholder="complaint-resolution">No recent resolutions available.</p>
                        <p class="secondary">Closed complaints show here after customer confirmation.</p>
                      </li>
                    </ul>
                  </div>
                </div>
              </article>

              <article class="dashboard-form">
                <h3>SLA reminders</h3>
                <ul class="dashboard-reminders" data-placeholder="sla-reminders">
                  <li>
                    <p>No reminders configured.</p>
                    <span>Schedule AMC or maintenance follow-ups from the service module.</span>
                  </li>
                </ul>
              </article>
            </div>
          </section>

          <section class="dashboard-section" id="governance" role="tabpanel" data-tab-panel hidden>
            <h2>Governance &amp; compliance oversight</h2>
            <p class="dashboard-section-sub">
              Review user-role coverage, outstanding approvals, and log exports to keep the organisation audit ready.
            </p>
            <div class="dashboard-profile-grid">
              <article class="dashboard-form">
                <h3>Role coverage matrix</h3>
                <div class="dashboard-table-wrapper" role="region">
                  <table class="dashboard-table">
                    <thead>
                      <tr>
                        <th scope="col">Role</th>
                        <th scope="col">Users</th>
                        <th scope="col">Last review</th>
                        <th scope="col">Owner</th>
                      </tr>
                    </thead>
                    <tbody data-governance-role-table>
                      <tr class="dashboard-empty-row">
                        <td colspan="4">No governance data available.</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </article>

              <article class="dashboard-form dashboard-form--list">
                <h3>Pending governance reviews</h3>
                <ul class="dashboard-list" data-governance-review-list>
                  <li class="dashboard-list-empty">
                    <p class="primary">No pending reviews.</p>
                    <p class="secondary">Change requests will surface here for approval.</p>
                  </li>
                </ul>
                <p class="dashboard-form-note">
                  <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                  Examples include access attestations, referrer payout audits, and compliance acknowledgements.
                </p>
              </article>

              <article class="dashboard-form dashboard-form--list">
                <h3>Activity &amp; export trail</h3>
                <ul class="dashboard-list" data-governance-activity-list>
                  <li class="dashboard-list-empty">
                    <p class="primary">No governance activity logged.</p>
                    <p class="secondary">Monthly exports, approvals, and escalations will appear here.</p>
                  </li>
                </ul>
              </article>

              <article class="dashboard-form">
                <h3>Management reporting</h3>
                <p>Compile the monthly activity pack with access logs, backup confirmations, and subsidy approvals.</p>
                <div>
                  <button type="button" class="btn btn-secondary" data-action="governance-export">
                    <i class="fa-solid fa-file-shield" aria-hidden="true"></i>
                    Download governance bundle
                  </button>
                  <button type="button" class="btn btn-ghost" data-action="governance-refresh">Refresh insights</button>
                </div>
                <div class="dashboard-inline-status" data-governance-status hidden></div>
                <p class="dashboard-form-note">
                  <i class="fa-solid fa-file-arrow-down" aria-hidden="true"></i>
                  Example: Export monthly activity and error logs for the management board submission.
                </p>
              </article>
            </div>
          </section>

          <section class="dashboard-section" id="audit" role="tabpanel" data-tab-panel hidden>
            <h2>Audit &amp; data logs</h2>
            <p class="dashboard-section-sub">
              Review exported reports, data deletion requests, and regulator-ready audit evidence.
            </p>
            <div class="dashboard-profile-grid">
              <article class="dashboard-form dashboard-form--list">
                <h3>Data governance queue</h3>
                <ul class="dashboard-list">
                  <li>
                    <p class="primary">No pending deletion or export requests.</p>
                    <p class="secondary">Approved records will surface here once logged by support teams.</p>
                  </li>
                </ul>
              </article>
              <article class="dashboard-form">
                <h3>Reference resources</h3>
                <div class="dashboard-reference">
                  <a href="deletion-report.md" class="dashboard-reference-link">
                    <i class="fa-solid fa-file-lines" aria-hidden="true"></i>
                    <span>
                      <strong>Deletion register</strong>
                      <small>Recent data erasure activity &amp; compliance notes</small>
                    </span>
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                  </a>
                  <a href="policies.html" class="dashboard-reference-link">
                    <i class="fa-solid fa-scale-balanced" aria-hidden="true"></i>
                    <span>
                      <strong>Security policies</strong>
                      <small>Review retention, access, and audit standards</small>
                    </span>
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                  </a>
                </div>
              </article>
            </div>
          </section>
        </div>
      </div>
    </div>
  </main>

  <div class="dashboard-toast-container" aria-live="polite" aria-atomic="false" data-toast-container></div>

  <template id="dashboard-search-result-template">
    <div class="dashboard-search-group">
      <h3></h3>
      <ul></ul>
    </div>
  </template>

  <template id="dashboard-search-item-template">
    <li>
      <p class="title"></p>
      <p class="desc"></p>
    </li>
  </template>

  <template id="dashboard-toast-template">
    <div class="dashboard-toast" role="status" data-toast>
      <strong class="dashboard-toast-title"></strong>
      <span class="dashboard-toast-message"></span>
    </div>
  </template>

  <script>
  window.DakshayaniAdmin = Object.freeze({
    csrfToken: <?= json_encode($_SESSION["csrf_token"] ?? '') ?>,
    apiBase: <?= json_encode($apiBase) ?>,
    currentUser: <?= json_encode([
      'id' => $user['id'] ?? null,
      'name' => $user['full_name'] ?? 'Administrator',
      'email' => $user['email'] ?? '',
      'role' => $user['role_name'] ?? 'admin',
    ]) ?>,
    roles: <?= json_encode(array_map(fn($role) => [
      'id' => (int) $role['id'],
      'name' => $role['name'],
    ], $availableRoles)) ?>,
    gemini: <?= json_encode($geminiSettings) ?>,
    metrics: <?= json_encode([
      'counts' => [
        'customers' => $customerCount,
        'pendingInvitations' => $pendingInvites,
        'openComplaints' => $openComplaints,
        'subsidyPipeline' => $subsidyPipeline,
      ],
      'system' => [
        'last_backup' => $lastBackup,
        'errors_24h' => $errorCount,
        'disk_usage' => $diskUsage,
        'uptime' => $uptime,
      ],
    ]) ?>,
    tasks: <?= json_encode(['items' => $taskItems, 'team' => $taskTeam]) ?>,
    documents: <?= json_encode($documents) ?>,
    dataQuality: <?= json_encode($dataQuality) ?>,
    crm: <?= json_encode($crmData) ?>,
    referrers: <?= json_encode($referrers) ?>,
    subsidy: <?= json_encode(['applications' => $subsidyApplications]) ?>,
    installations: <?= json_encode($installations) ?>,
    analytics: <?= json_encode($analytics) ?>,
    governance: <?= json_encode($governance) ?>,
    retention: <?= json_encode($retentionSettings) ?>,
    blog: <?= json_encode(['posts' => $blogPosts]) ?>
  });
</script>
<script src="admin-dashboard.js" defer></script>
<script src="script.js" defer></script>
</body>
</html>
