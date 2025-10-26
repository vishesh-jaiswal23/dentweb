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
$geminiSettings = [
    'apiKey' => get_setting('gemini_api_key') ?? '',
    'textModel' => get_setting('gemini_text_model') ?? 'gemini-2.5-flash',
    'imageModel' => get_setting('gemini_image_model') ?? 'gemini-2.5-flash-image',
    'ttsModel' => get_setting('gemini_tts_model') ?? 'gemini-2.5-flash-preview-tts',
];
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
          <a href="logout.php" class="btn btn-ghost">
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
              <button class="dashboard-nav-link" type="button" role="tab" aria-selected="false" data-tab-target="onboarding">
                <i class="fa-solid fa-user-plus"></i> Onboarding &amp; Approvals
              </button>
            </li>
            <li>
              <button class="dashboard-nav-link" type="button" role="tab" aria-selected="false" data-tab-target="access">
                <i class="fa-solid fa-fingerprint"></i> Access Control
              </button>
            </li>
            <li>
              <button class="dashboard-nav-link" type="button" role="tab" aria-selected="false" data-tab-target="health">
                <i class="fa-solid fa-heart-pulse"></i> System Health
              </button>
            </li>
            <li>
              <button class="dashboard-nav-link" type="button" role="tab" aria-selected="false" data-tab-target="ai">
                <i class="fa-solid fa-robot"></i> Gemini AI Provider
              </button>
            </li>
            <li>
              <button class="dashboard-nav-link" type="button" role="tab" aria-selected="false" data-tab-target="complaints">
                <i class="fa-solid fa-headset"></i> Complaints &amp; Service
              </button>
            </li>
            <li>
              <button class="dashboard-nav-link" type="button" role="tab" aria-selected="false" data-tab-target="audit">
                <i class="fa-solid fa-shield-halved"></i> Audit &amp; Data Logs
              </button>
            </li>
          </ul>
          <div class="dashboard-nav-footer">
            <a class="dashboard-nav-link dashboard-nav-link--logout" href="logout.php">
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
            </div>
          </section>

          <section class="dashboard-section" id="health" role="tabpanel" data-tab-panel hidden>
            <h2>System health</h2>
            <p class="dashboard-section-sub">Monitor uptime, SLA breaches, and exportable audit trails for every user action.</p>
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

          <section class="dashboard-section" id="ai" role="tabpanel" data-tab-panel hidden>
            <h2>Gemini AI provider settings</h2>
            <p class="dashboard-section-sub">
              Manage secure API keys and model codes for text, image, and TTS interactions used across internal copilots.
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
              <div>
                <button type="submit" class="btn btn-secondary" data-action="save-gemini">Save settings</button>
                <button type="button" class="btn btn-ghost" data-action="reset-gemini">Reset defaults</button>
                <button type="button" class="btn btn-tertiary" data-action="test-gemini">
                  <i class="fa-solid fa-plug-circle-check" aria-hidden="true"></i>
                  Test connection
                </button>
              </div>
            </form>
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
    ]) ?>
  });
</script>
<script src="admin-dashboard.js" defer></script>
<script src="script.js" defer></script>
</body>
</html>
