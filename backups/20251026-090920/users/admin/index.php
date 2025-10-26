<?php
require_once __DIR__ . '/../common/config.php';
require_once __DIR__ . '/../common/auth.php';
require_once __DIR__ . '/../common/security.php';
require_once __DIR__ . '/../common/settings.php';
require_once __DIR__ . '/layout.php';

portal_require_role(['admin']);
portal_require_session();

$user = portal_current_user();

$notifications = [
    ['icon' => 'fa-briefcase', 'message' => 'Finance posted updated subsidy numbers', 'time' => '8 minutes ago', 'tone' => 'info'],
    ['icon' => 'fa-circle-check', 'message' => 'Installer roster confirmed for Bokaro cluster', 'time' => '45 minutes ago', 'tone' => 'success'],
    ['icon' => 'fa-triangle-exclamation', 'message' => '3 inverter warranty tickets pending approval', 'time' => 'Today', 'tone' => 'warning'],
];

$activityLog = [
    [
        'actor' => 'Ravi Sharma',
        'role' => 'Employee',
        'time' => '5 minutes ago',
        'action' => 'Updated project milestone',
        'module' => 'Projects • Suryodaya Plant',
        'diff' => ['old' => 'Status: Pending DISCOM', 'new' => 'Status: Grid synchronised'],
    ],
    [
        'actor' => 'Anita Patel',
        'role' => 'Installer',
        'time' => '20 minutes ago',
        'action' => 'Uploaded commissioning photos',
        'module' => 'Quality',
        'diff' => ['old' => 'Photos: 2', 'new' => 'Photos: 8'],
    ],
    [
        'actor' => 'You',
        'role' => 'Admin',
        'time' => '1 hour ago',
        'action' => 'Approved subsidy release',
        'module' => 'Finance',
        'diff' => ['old' => 'Approval: Pending', 'new' => 'Approval: Released'],
    ],
];

$errorGroups = [
    [
        'code' => 'ETL-102',
        'description' => 'Lead import validation warning',
        'count' => 7,
        'last_seen' => '10 minutes ago',
        'severity' => 'warning',
    ],
    [
        'code' => 'AI-401',
        'description' => 'AI content generator timeout',
        'count' => 2,
        'last_seen' => '1 hour ago',
        'severity' => 'minor',
    ],
    [
        'code' => 'SYNC-509',
        'description' => 'DISCOM API unavailable',
        'count' => 1,
        'last_seen' => 'Yesterday',
        'severity' => 'critical',
    ],
];

$aiSettings = portal_ai_settings_get();

$diskFree = @disk_free_space(__DIR__ . '/../../');
$diskFreeGb = $diskFree ? round($diskFree / 1024 / 1024 / 1024, 1) : 'n/a';
$accessSummary = portal_recent_denied_access_summary(180);
$systemHealth = [
    'last_backup' => 'Today • 02:00 AM',
    'disk_free' => $diskFreeGb . ' GB free',
    'recent_errors' => array_sum(array_column($errorGroups, 'count')),
    'pending_alerts' => 4,
    'total_records' => '18,420',
];

if (!empty($accessSummary['total']) && $accessSummary['total'] >= 3) {
    $pageLabels = [
        'service-workflow' => 'Service workflow',
        'crm-portal' => 'CRM workspace',
    ];
    $summaries = [];
    foreach ($accessSummary['pages'] as $page => $count) {
        $label = $pageLabels[$page] ?? ($page !== '' ? $page : 'unknown module');
        $summaries[] = $count . '× ' . $label;
    }
    $summaryText = !empty($summaries) ? implode(', ', $summaries) : 'Multiple protected areas';
    $errorGroups[] = [
        'code' => 'ACCESS-403',
        'description' => 'Blocked portal attempts detected: ' . $summaryText,
        'count' => $accessSummary['total'],
        'last_seen' => 'Within last ' . (int) $accessSummary['window'] . ' min',
        'severity' => 'minor',
    ];
    $systemHealth['pending_alerts'] += 1;
    $systemHealth['recent_errors'] = array_sum(array_column($errorGroups, 'count'));
}

$quickActions = [
    [
        'id' => 'run-health-check',
        'label' => 'Run live system check',
        'icon' => 'fa-stethoscope',
        'description' => 'Verify services, queues, and integrations.',
        'confirm' => 'Run the live system health check now?',
        'success' => 'Health check queued. Results will post to notifications.',
    ],
    [
        'id' => 'trigger-backup',
        'label' => 'Trigger on-demand backup',
        'icon' => 'fa-database',
        'description' => 'Initiate encrypted snapshot of admin data.',
        'confirm' => 'Start the encrypted backup now? Operations may slow during the process.',
        'success' => 'Backup started. You will be notified when it completes.',
    ],
];

$guardedModules = [
    ['name' => 'Projects', 'capabilities' => ['view', 'edit', 'approve', 'export']],
    ['name' => 'Leads', 'capabilities' => ['view', 'create', 'edit', 'delete']],
    ['name' => 'Finance', 'capabilities' => ['view', 'approve', 'export']],
];

portal_admin_shell_open('Admin Dashboard | Dakshayani Enterprises', 'dashboard', $user, $notifications);
?>
          <section class="admin-section">
            <header class="admin-section__header">
              <div>
                <h1>Executive overview</h1>
                <p class="text-muted">Monitor health, approvals, and alerts across the organisation.</p>
              </div>
              <div class="admin-section__actions">
                <button class="btn btn-primary" data-action="open-search"><i class="fa-solid fa-magnifying-glass"></i> Global search</button>
              </div>
            </header>
            <div class="admin-grid admin-grid--stats">
              <article class="admin-card">
                <header>
                  <span class="admin-card__icon"><i class="fa-solid fa-solar-panel"></i></span>
                  <span class="admin-card__label">Active projects</span>
                </header>
                <strong class="admin-card__value">12</strong>
                <p class="admin-card__meta text-positive">+3 vs last week</p>
              </article>
              <article class="admin-card">
                <header>
                  <span class="admin-card__icon"><i class="fa-solid fa-user-check"></i></span>
                  <span class="admin-card__label">Approvals pending</span>
                </header>
                <strong class="admin-card__value">4</strong>
                <p class="admin-card__meta text-warning">Finance, Compliance, Operations</p>
              </article>
              <article class="admin-card">
                <header>
                  <span class="admin-card__icon"><i class="fa-solid fa-indian-rupee-sign"></i></span>
                  <span class="admin-card__label">Collections rate</span>
                </header>
                <strong class="admin-card__value">92%</strong>
                <p class="admin-card__meta">₹42L collected in April</p>
              </article>
              <article class="admin-card">
                <header>
                  <span class="admin-card__icon"><i class="fa-solid fa-robot"></i></span>
                  <span class="admin-card__label">AI provider status</span>
                </header>
                <strong class="admin-card__value">Ready</strong>
                <p class="admin-card__meta">Text: <?php echo htmlspecialchars($aiSettings['models']['text'] ?? 'n/a'); ?></p>
              </article>
            </div>
          </section>

          <section class="admin-section" id="activity-log" data-capability="activity.view">
            <header class="admin-section__header">
              <div>
                <h2>Activity log</h2>
                <p class="text-muted">Every sensitive change is tracked with before/after details.</p>
              </div>
              <button class="btn btn-secondary" data-action="export-activity"><i class="fa-solid fa-arrow-up-right-from-square"></i> Export</button>
            </header>
            <div class="admin-table-wrapper">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th scope="col">Who</th>
                    <th scope="col">Action</th>
                    <th scope="col">Module</th>
                    <th scope="col">Diff</th>
                    <th scope="col">When</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($activityLog as $entry): ?>
                    <tr>
                      <td>
                        <strong><?php echo htmlspecialchars($entry['actor']); ?></strong>
                        <span class="text-muted"><?php echo htmlspecialchars($entry['role']); ?></span>
                      </td>
                      <td><?php echo htmlspecialchars($entry['action']); ?></td>
                      <td><?php echo htmlspecialchars($entry['module']); ?></td>
                      <td>
                        <div class="diff">
                          <span class="diff__old"><?php echo htmlspecialchars($entry['diff']['old']); ?></span>
                          <i class="fa-solid fa-arrow-right"></i>
                          <span class="diff__new"><?php echo htmlspecialchars($entry['diff']['new']); ?></span>
                        </div>
                      </td>
                      <td><?php echo htmlspecialchars($entry['time']); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </section>

          <section class="admin-section" id="error-log" data-capability="errors.view">
            <header class="admin-section__header">
              <div>
                <h2>Error monitor</h2>
                <p class="text-muted">Grouped by error signature for rapid triage.</p>
              </div>
              <button class="btn btn-secondary" data-action="acknowledge-errors"><i class="fa-solid fa-check-double"></i> Acknowledge all</button>
            </header>
            <div class="admin-table-wrapper">
              <table class="admin-table admin-table--compact">
                <thead>
                  <tr>
                    <th scope="col">Code</th>
                    <th scope="col">Description</th>
                    <th scope="col">Occurrences</th>
                    <th scope="col">Last seen</th>
                    <th scope="col">Severity</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($errorGroups as $group): ?>
                    <tr>
                      <td><code><?php echo htmlspecialchars($group['code']); ?></code></td>
                      <td><?php echo htmlspecialchars($group['description']); ?></td>
                      <td><?php echo (int) $group['count']; ?></td>
                      <td><?php echo htmlspecialchars($group['last_seen']); ?></td>
                      <td><span class="badge badge-<?php echo htmlspecialchars($group['severity']); ?>"><?php echo htmlspecialchars(ucfirst($group['severity'])); ?></span></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </section>

          <section class="admin-section" id="system-health">
            <header class="admin-section__header">
              <div>
                <h2>System health</h2>
                <p class="text-muted">Operational summary covering backups, storage, and alerts.</p>
              </div>
            </header>
            <div class="admin-grid admin-grid--health">
              <article class="admin-health-card">
                <h3>Last backup</h3>
                <p class="admin-health-card__value"><?php echo htmlspecialchars($systemHealth['last_backup']); ?></p>
                <p class="admin-health-card__meta">Automated nightly backups stored in cold storage.</p>
              </article>
              <article class="admin-health-card">
                <h3>Available disk</h3>
                <p class="admin-health-card__value"><?php echo htmlspecialchars($systemHealth['disk_free']); ?></p>
                <p class="admin-health-card__meta">Includes cached assets and queued exports.</p>
              </article>
              <article class="admin-health-card">
                <h3>Recent errors</h3>
                <p class="admin-health-card__value"><?php echo (int) $systemHealth['recent_errors']; ?></p>
                <p class="admin-health-card__meta">Resolved issues are auto-archived after validation.</p>
              </article>
              <article class="admin-health-card">
                <h3>Pending alerts</h3>
                <p class="admin-health-card__value"><?php echo (int) $systemHealth['pending_alerts']; ?></p>
                <p class="admin-health-card__meta">Escalations pending response across teams.</p>
              </article>
              <article class="admin-health-card">
                <h3>Total records</h3>
                <p class="admin-health-card__value"><?php echo htmlspecialchars($systemHealth['total_records']); ?></p>
                <p class="admin-health-card__meta">Customers, leads, and compliance documents.</p>
              </article>
            </div>
          </section>

          <section class="admin-section" data-capability="dashboard.view">
            <header class="admin-section__header">
              <div>
                <h2>Quick actions</h2>
                <p class="text-muted">Frequently used actions follow the confirmation-first rule.</p>
              </div>
            </header>
            <div class="admin-grid admin-grid--actions">
              <?php foreach ($quickActions as $action): ?>
                <article class="admin-action-card" data-action-card="<?php echo htmlspecialchars($action['id']); ?>">
                  <header>
                    <span class="admin-action-card__icon"><i class="fa-solid <?php echo htmlspecialchars($action['icon']); ?>"></i></span>
                    <h3><?php echo htmlspecialchars($action['label']); ?></h3>
                  </header>
                  <p><?php echo htmlspecialchars($action['description']); ?></p>
                  <button class="btn btn-primary" data-action="confirm" data-confirm-message="<?php echo htmlspecialchars($action['confirm']); ?>" data-success-message="<?php echo htmlspecialchars($action['success']); ?>">Execute</button>
                </article>
              <?php endforeach; ?>
            </div>
          </section>

          <section class="admin-section" data-capability="dashboard.view">
            <header class="admin-section__header">
              <div>
                <h2>Module guardrails</h2>
                <p class="text-muted">Capabilities default to deny until explicitly granted.</p>
              </div>
            </header>
            <div class="admin-grid admin-grid--guardrails">
              <?php foreach ($guardedModules as $module): ?>
                <article class="admin-guard-card">
                  <header>
                    <h3><?php echo htmlspecialchars($module['name']); ?></h3>
                  </header>
                  <ul>
                    <?php foreach ($module['capabilities'] as $cap): ?>
                      <li><i class="fa-solid fa-shield-halved"></i> <?php echo htmlspecialchars(ucfirst($cap)); ?></li>
                    <?php endforeach; ?>
                  </ul>
                </article>
              <?php endforeach; ?>
            </div>
          </section>

          <script>
            window.__ADMIN_TOASTS = (window.__ADMIN_TOASTS || []);
            window.__ADMIN_TOASTS.push({ type: 'info', message: 'Session secured with device binding.' });
          </script>

<?php portal_admin_shell_close();
