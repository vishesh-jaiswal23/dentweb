<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bootstrap.php';

require_admin();
$user = current_user();
$db = get_db();

$flashData = consume_flash();
$flashMessage = '';
$flashTone = 'info';
$flashIcons = [
    'success' => 'fa-circle-check',
    'warning' => 'fa-triangle-exclamation',
    'error' => 'fa-circle-exclamation',
    'info' => 'fa-circle-info',
];
$flashIcon = $flashIcons[$flashTone];
if (is_array($flashData)) {
    if (isset($flashData['message']) && is_string($flashData['message'])) {
        $flashMessage = trim($flashData['message']);
    }
    if (isset($flashData['type']) && is_string($flashData['type'])) {
        $candidateTone = strtolower($flashData['type']);
        if (isset($flashIcons[$candidateTone])) {
            $flashTone = $candidateTone;
            $flashIcon = $flashIcons[$candidateTone];
        }
    }
}

$rolesStmt = $db->query('SELECT id, name FROM roles ORDER BY name');
$availableRoles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC);

$employeeCount = (int) $db->query("SELECT COUNT(*) FROM users INNER JOIN roles ON users.role_id = roles.id WHERE roles.name = 'employee'")->fetchColumn();
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
$complaints = [];

$taskTeam = portal_list_team($db);
$taskItems = portal_list_tasks($db);
$complaints = portal_all_complaints($db);
$blogPosts = blog_admin_list($db);

$crmData = [
    'leads' => [],
    'customers' => [],
];

$installations = [
    'jobs' => [],
    'warranties' => [],
    'amc' => [],
];

$referrers = [];

$subsidyApplications = [];

$requests = [];

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
      <?php if ($flashMessage !== ''): ?>
      <div class="portal-flash portal-flash--<?= htmlspecialchars($flashTone, ENT_QUOTES) ?>" role="status" aria-live="polite">
        <i class="fa-solid <?= htmlspecialchars($flashIcon, ENT_QUOTES) ?>" aria-hidden="true"></i>
        <span><?= htmlspecialchars($flashMessage, ENT_QUOTES) ?></span>
      </div>
      <?php endif; ?>
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

      <div class="dashboard-body dashboard-body--nav-first">
        <nav class="dashboard-nav" aria-label="Admin navigation" role="tablist">
          <p class="dashboard-nav-title">Workspace</p>
          <ul>
            <li>
              <button class="dashboard-nav-link" type="button" role="tab" aria-selected="true" data-tab-target="overview">
                <i class="fa-solid fa-chart-line"></i> Overview
              </button>
            </li>
            <li>
              <button class="dashboard-nav-link" type="button" role="tab" aria-selected="false" data-tab-target="leads">
                <i class="fa-solid fa-people-arrows"></i> Leads &amp; Site Visits
              </button>
            </li>
            <li>
              <button class="dashboard-nav-link" type="button" role="tab" aria-selected="false" data-tab-target="installations">
                <i class="fa-solid fa-solar-panel"></i> Installations
              </button>
            </li>
            <li>
              <button class="dashboard-nav-link" type="button" role="tab" aria-selected="false" data-tab-target="complaints">
                <i class="fa-solid fa-headset"></i> Complaints &amp; Service
              </button>
            </li>
            <li>
              <button class="dashboard-nav-link" type="button" role="tab" aria-selected="false" data-tab-target="subsidy">
                <i class="fa-solid fa-indian-rupee-sign"></i> Subsidy Tracker
              </button>
            </li>
            <li>
              <button class="dashboard-nav-link" type="button" role="tab" aria-selected="false" data-tab-target="reminders">
                <i class="fa-solid fa-bell"></i> Reminders / Follow-Ups
              </button>
            </li>
            <li>
              <button class="dashboard-nav-link" type="button" role="tab" aria-selected="false" data-tab-target="referrers">
                <i class="fa-solid fa-handshake"></i> Referrers
              </button>
            </li>
            <li>
              <button class="dashboard-nav-link" type="button" role="tab" aria-selected="false" data-tab-target="blog">
                <i class="fa-solid fa-blog"></i> Blog Publishing
              </button>
            </li>
            <li>
              <button class="dashboard-nav-link" type="button" role="tab" aria-selected="false" data-tab-target="ai">
                <i class="fa-solid fa-robot"></i> AI Settings &amp; Studio
              </button>
            </li>
            <li>
              <button class="dashboard-nav-link" type="button" role="tab" aria-selected="false" data-tab-target="user-management">
                <i class="fa-solid fa-user-gear"></i> User Management
              </button>
            </li>
            <li>
              <button class="dashboard-nav-link" type="button" role="tab" aria-selected="false" data-tab-target="requests">
                <i class="fa-solid fa-inbox"></i> Requests Center
              </button>
            </li>
          </ul>
        </nav>

        <div class="dashboard-main">
          <section class="dashboard-section" id="overview" role="tabpanel" data-tab-panel>
            <h2>Operational overview</h2>
            <p class="dashboard-section-sub">Real-time KPIs from onboarding, employees, service tickets, AMC and subsidy queues.</p>
            <div class="dashboard-cards">
              <article class="dashboard-card dashboard-card--neutral">
                <div class="dashboard-card-icon" aria-hidden="true"><i class="fa-solid fa-solar-panel"></i></div>
                <div>
                  <p class="dashboard-card-title">Active employees</p>
                  <p class="dashboard-card-value" data-metric="employees"><?= htmlspecialchars((string) $employeeCount, ENT_QUOTES) ?></p>
                  <p class="dashboard-card-meta">Counts update automatically when Admin activates an employee account.</p>
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

          <section class="dashboard-section" id="leads" role="tabpanel" data-tab-panel hidden>
            <h2>Leads &amp; site visits</h2>
            <p class="dashboard-section-sub">
              Track the pipeline from new enquiries through confirmed site visits so field teams have a clear queue.
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
                <h3>Scheduled site visits</h3>
                <div class="dashboard-form-grid dashboard-form-grid--two">
                  <label>
                    Confirmed visits
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
                        <th scope="col">Visit date</th>
                        <th scope="col">Lead ref</th>
                        <th scope="col">Actions</th>
                      </tr>
                    </thead>
                    <tbody data-customers-table>
                      <tr class="dashboard-empty-row">
                        <td colspan="5">No site visits scheduled.</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <p class="dashboard-form-note">
                  <i class="fa-solid fa-route" aria-hidden="true"></i>
                  Sync visit plans with the employee portal to keep field teams aligned.
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
                <label class="dashboard-inline-option">
                  <input type="checkbox" name="amcActivate" value="1" checked />
                  Activate AMC plan immediately after this installation closes
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

              <article class="dashboard-form">
                <h3>Assignment &amp; collaboration</h3>
                <form class="dashboard-stacked-form" data-complaint-assign-form>
                  <label>
                    Ticket
                    <select name="reference" required data-complaint-select>
                      <option value="" selected disabled>Select ticket</option>
                    </select>
                  </label>
                  <label>
                    Assign to
                    <select name="assigneeId" data-complaint-assignee>
                      <option value="">Unassigned</option>
                    </select>
                  </label>
                  <label>
                    SLA due date
                    <input type="date" name="slaDue" data-complaint-sla />
                  </label>
                  <button type="submit" class="btn btn-secondary btn-sm">Update assignment</button>
                  <p class="text-xs text-muted mb-0">Assignments sync instantly with the employee dashboard.</p>
                </form>
                <form class="dashboard-stacked-form" data-complaint-note-form>
                  <label>
                    Add note
                    <textarea name="note" rows="3" placeholder="Share resolution guidance or escalation notes." required></textarea>
                  </label>
                  <button type="submit" class="btn btn-ghost btn-sm">Log note</button>
                </form>
                <div class="complaint-detail" data-complaint-detail>
                  <p class="primary" data-complaint-empty>Select a ticket to view notes, attachments, and SLA timers.</p>
                  <div class="complaint-detail__meta" data-complaint-meta hidden>
                    <p class="text-sm"><strong data-complaint-meta-reference></strong></p>
                    <p class="text-sm" data-complaint-meta-owner></p>
                    <p class="text-sm" data-complaint-meta-sla></p>
                  </div>
                  <div class="complaint-detail__notes">
                    <h4>Notes</h4>
                    <ul data-complaint-notes>
                      <li class="text-muted">No notes recorded yet.</li>
                    </ul>
                  </div>
                  <div class="complaint-detail__attachments">
                    <h4>Attachments</h4>
                    <ul data-complaint-attachments>
                      <li class="text-muted">No attachments synced.</li>
                    </ul>
                  </div>
                </div>
              </article>
            </div>
            <div class="dashboard-profile-grid">
              <form class="dashboard-form" data-complaint-form>
                <h3>Create or update ticket</h3>
                <div class="dashboard-form-grid dashboard-form-grid--two">
                  <label>
                    Ticket reference
                    <input type="text" name="reference" placeholder="Leave blank to auto-generate" />
                  </label>
                  <label>
                    Ticket title
                    <input type="text" name="title" placeholder="Service call from Ranchi" required />
                  </label>
                  <label>
                    Status
                    <select name="status">
                      <option value="intake">Intake</option>
                      <option value="triage">Triage</option>
                      <option value="work">Work</option>
                      <option value="resolution">Resolution</option>
                      <option value="closed">Closed</option>
                    </select>
                  </label>
                  <label>
                    Priority
                    <select name="priority">
                      <option value="medium" selected>Medium</option>
                      <option value="high">High</option>
                      <option value="low">Low</option>
                      <option value="urgent">Urgent</option>
                    </select>
                  </label>
                  <label>
                    Assign to employee
                    <select name="assignedTo" data-complaint-assignee>
                      <option value="">Unassigned</option>
                    </select>
                  </label>
                  <label>
                    SLA target (DD-MM-YYYY)
                    <input type="text" name="slaDue" placeholder="DD-MM-YYYY" />
                  </label>
                </div>
                <label>
                  Description
                  <textarea
                    name="description"
                    rows="3"
                    placeholder="Capture customer statement, affected equipment, and any troubleshooting performed."
                  ></textarea>
                </label>
                <p class="dashboard-form-note">
                  <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                  Leave the reference blank to generate a new SR code. Using an existing reference updates that ticket in place.
                </p>
                <div>
                  <button type="submit" class="btn btn-secondary">Save ticket</button>
                  <button type="reset" class="btn btn-ghost">Clear</button>
                </div>
                <div class="dashboard-inline-status" data-complaint-form-status hidden></div>
              </form>

              <article class="dashboard-form dashboard-form--table">
                <h3>Ticket register</h3>
                <div class="dashboard-table-wrapper" role="region" aria-live="polite">
                  <table class="dashboard-table">
                    <thead>
                      <tr>
                        <th scope="col">Reference</th>
                        <th scope="col">Summary</th>
                        <th scope="col">Status</th>
                        <th scope="col">SLA</th>
                        <th scope="col">Assignee</th>
                        <th scope="col">Actions</th>
                      </tr>
                    </thead>
                    <tbody data-complaint-table>
                      <tr class="dashboard-empty-row">
                        <td colspan="6">No complaints logged yet.</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </article>

              <article class="dashboard-form dashboard-form--list">
                <h3>Ticket timeline</h3>
                <p class="dashboard-form-note" data-complaint-timeline-note>
                  <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                  Select a ticket from the register to review its activity log and append notes.
                </p>
                <div class="ticket-timeline" data-complaint-timeline-wrapper hidden>
                  <ol data-complaint-timeline></ol>
                </div>
                <form class="dashboard-inline-form" data-complaint-note-form hidden>
                  <h4>Add internal note</h4>
                  <div class="dashboard-inline-fields">
                    <label>
                      Note for employees
                      <textarea
                        name="note"
                        rows="3"
                        placeholder="Share troubleshooting guidance or customer commitments."
                        required
                      ></textarea>
                    </label>
                  </div>
                  <div>
                    <button type="submit" class="btn btn-secondary btn-sm">Log note</button>
                  </div>
                  <div class="dashboard-inline-status" data-complaint-note-status hidden></div>
                </form>
              </article>
            </div>
          </section>

          <section class="dashboard-section" id="subsidy" role="tabpanel" data-tab-panel hidden>
            <h2>Subsidy tracker (PM Surya Ghar)</h2>
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

          <section class="dashboard-section" id="reminders" role="tabpanel" data-tab-panel hidden>
            <h2>Reminders &amp; follow-ups</h2>
            <p class="dashboard-section-sub">
              Keep an eye on follow-up actions, upcoming visits, and assignments that require nudges across teams.
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

          <section class="dashboard-section" id="ai" role="tabpanel" data-tab-panel hidden>
            <h2>AI Settings &amp; Studio (Gemini only)</h2>
            <p class="dashboard-section-sub">
              Manage Gemini credentials and generate blog drafts, cover art, and narrations without exposing any other AI providers.
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

            <div class="dashboard-profile-grid dashboard-ai-stack">
              <form class="dashboard-form dashboard-ai-suite" data-ai-blog-form>
                <div class="dashboard-ai-suite__header">
                  <h3>AI content studio</h3>
                  <p class="dashboard-muted">
                    Generate the draft, artwork, and narration without leaving this workflow.
                  </p>
                </div>
                <div class="dashboard-ai-suite__grid">
                  <section class="dashboard-ai-suite__panel dashboard-ai-suite__panel--primary">
                    <h4>Generate AI-assisted blog</h4>
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
                      <textarea
                        name="outline"
                        rows="3"
                        placeholder="Add sections or bullet points you want the draft to include."
                        data-ai-outline
                      ></textarea>
                    </label>
                    <div class="dashboard-ai-suite__actions">
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
                  </section>

          <section class="dashboard-section" id="user-management" role="tabpanel" data-tab-panel hidden>
            <h2>User management</h2>
            <p class="dashboard-section-sub">
              Admins can create and maintain access for the five supported roles: Admin, Employee, Installer, Referrer, and Customer.
              Invitations remain pending until you approve and assign permissions.
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


          <section class="dashboard-section" id="requests" role="tabpanel" data-tab-panel hidden>
            <h2>Requests Center</h2>
            <p class="dashboard-section-sub">
              Review and respond to employee-submitted access changes, hardware asks, and field support requests in one queue.
            </p>
            <div class="dashboard-profile-grid">
              <article class="dashboard-form">
                <h3>Employee requests</h3>
                <div class="dashboard-table-wrapper" role="region">
                  <table class="dashboard-table">
                    <thead>
                      <tr>
                        <th scope="col">Request</th>
                        <th scope="col">Type</th>
                        <th scope="col">Submitted</th>
                        <th scope="col">Assignee</th>
                        <th scope="col">Status</th>
                        <th scope="col">Actions</th>
                      </tr>
                    </thead>
                    <tbody data-requests-table>
                      <tr class="dashboard-empty-row">
                        <td colspan="6">No employee requests logged.</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <div class="dashboard-inline-status" data-requests-status hidden></div>
              </article>

              <article class="dashboard-form dashboard-form--list">
                <h3>Escalations &amp; follow-ups</h3>
                <ul class="dashboard-list" data-requests-summary>
                  <li class="dashboard-list-empty">
                    <p class="primary">No escalations pending.</p>
                    <p class="secondary">Employee requests marked as urgent will surface here for faster action.</p>
                  </li>
                </ul>
                <p class="dashboard-form-note">
                  <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                  Link requests to tasks or complaints to keep every follow-up traceable.
                </p>
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
        'employees' => $employeeCount,
        'customers' => count($crmData['customers'] ?? []),
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
    complaints: <?= json_encode($complaints) ?>,
    crm: <?= json_encode($crmData) ?>,
    referrers: <?= json_encode($referrers) ?>,
    subsidy: <?= json_encode(['applications' => $subsidyApplications]) ?>,
    installations: <?= json_encode($installations) ?>,
    blog: <?= json_encode(['posts' => $blogPosts]) ?>,
    requests: <?= json_encode($requests) ?>
  });
</script>
<script src="admin-dashboard.js" defer></script>
<script src="script.js" defer></script>
</body>
</html>
