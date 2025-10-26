<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bootstrap.php';

require_role('employee');
$user = current_user();

$employeeName = $user['full_name'] ?? 'Rohan Iyer';
$employeeRole = $user['role_name'] === 'employee' ? ($user['job_title'] ?? 'Service Engineer') : ($user['role_name'] ?? 'Employee');
$employeeAccess = 'Tickets, Tasks, Leads, Customers';
$firstName = trim((string) $employeeName);
if ($firstName === '') {
    $firstName = 'Employee';
} else {
    $parts = preg_split('/\s+/', $firstName) ?: [];
    $firstName = $parts[0] ?? $firstName;
}

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

$performanceMetrics = [
    ['value' => '94%', 'label' => 'SLA compliance'],
    ['value' => '1.2 hrs', 'label' => 'avg. response'],
    ['value' => '5★', 'label' => 'customer rating (latest ticket)'],
];

$tickets = [
    [
        'id' => 'T-205',
        'title' => 'Ticket T-205 · Inverter fault',
        'customer' => 'Meera Housing Society · Service',
        'status' => 'in_progress',
        'statusLabel' => 'In Progress',
        'statusTone' => 'progress',
        'assignedBy' => 'Admin (Service Desk)',
        'sla' => 'Due in 8 hrs',
        'contact' => 'Anita Sharma · +91 98765 44321',
        'attachments' => ['inverter-panel.jpg', 'load-test-report.pdf'],
        'timeline' => [
            ['time' => '2024-05-21T09:24', 'label' => '09:24', 'message' => 'Ticket assigned to you by Admin.'],
            ['time' => '2024-05-21T10:05', 'label' => '10:05', 'message' => 'Status updated to <strong>In Progress</strong>. Field visit scheduled for 3 PM.'],
        ],
        'noteLabel' => 'Add note',
        'noteIcon' => 'fa-solid fa-note-sticky',
    ],
    [
        'id' => 'T-238',
        'title' => 'Ticket T-238 · Net metering query',
        'customer' => 'Green Valley Apartments · Service',
        'status' => 'awaiting_response',
        'statusLabel' => 'Awaiting Response',
        'statusTone' => 'waiting',
        'assignedBy' => 'Admin (Customer Success)',
        'sla' => 'Follow-up by 4 hrs',
        'contact' => 'Facilities Office · +91 91234 55678',
        'attachments' => ['discom-email.eml'],
        'timeline' => [
            ['time' => '2024-05-20T16:40', 'label' => '16:40', 'message' => 'Awaiting customer response on meter replacement quote.'],
            ['time' => '2024-05-21T09:10', 'label' => '09:10', 'message' => 'Reminder sent to customer via email.'],
        ],
        'noteLabel' => 'Log follow-up',
        'noteIcon' => 'fa-solid fa-phone',
    ],
    [
        'id' => 'T-189',
        'title' => 'Ticket T-189 · AMC inspection',
        'customer' => 'Skyline Residency · Service',
        'status' => 'resolved',
        'statusLabel' => 'Resolved',
        'statusTone' => 'resolved',
        'assignedBy' => 'Admin (AMC)',
        'sla' => 'Closed on time',
        'contact' => 'Maintenance Desk · +91 99887 22110',
        'attachments' => ['amc-inspection-report.pdf'],
        'timeline' => [
            ['time' => '2024-05-20T08:55', 'label' => '08:55', 'message' => 'Inspection completed. Uploading documentation.'],
            ['time' => '2024-05-20T12:42', 'label' => '12:42', 'message' => 'Marked as resolved with checklist photos attached.'],
        ],
        'noteLabel' => 'Add final photos',
        'noteIcon' => 'fa-solid fa-image',
    ],
];

$statusOptions = [
    'in_progress' => 'In Progress',
    'awaiting_response' => 'Awaiting Response',
    'resolved' => 'Resolved',
    'escalated' => 'Escalated to Admin',
];

$taskColumns = [
    'todo' => [
        'label' => 'To Do',
        'meta' => 'Upcoming work queued by Admin.',
        'items' => [
            [
                'id' => 'task-101',
                'title' => 'Site visit – Doranda',
                'priority' => 'high',
                'priorityLabel' => 'High',
                'deadline' => 'Due 22 May · 10:00 AM',
                'link' => 'Linked to Ticket <strong>T-205</strong>',
                'action' => ['attr' => 'data-task-complete', 'label' => 'Mark done'],
            ],
            [
                'id' => 'task-102',
                'title' => 'Call back – Skyline AMC',
                'priority' => 'medium',
                'priorityLabel' => 'Medium',
                'deadline' => 'Due 21 May · 04:30 PM',
                'link' => 'Linked to Ticket <strong>T-189</strong>',
                'action' => ['attr' => 'data-task-complete', 'label' => 'Mark done'],
            ],
        ],
    ],
    'in_progress' => [
        'label' => 'In Progress',
        'meta' => 'Work currently underway.',
        'items' => [
            [
                'id' => 'task-201',
                'title' => 'Update ticket attachments',
                'priority' => 'medium',
                'priorityLabel' => 'Medium',
                'deadline' => 'Due Today · 05:30 PM',
                'link' => 'Upload field photos for ticket <strong>T-205</strong>',
                'action' => ['attr' => 'data-task-complete', 'label' => 'Mark done'],
            ],
            [
                'id' => 'task-202',
                'title' => 'Prep AMC visit brief',
                'priority' => 'low',
                'priorityLabel' => 'Low',
                'deadline' => 'Due 24 May · 12:30 PM',
                'link' => 'Linked to Ticket <strong>T-189</strong>',
                'action' => ['attr' => 'data-task-complete', 'label' => 'Mark done'],
            ],
        ],
    ],
    'done' => [
        'label' => 'Done',
        'meta' => 'Completed work synced back to Admin.',
        'items' => [
            [
                'id' => 'task-301',
                'title' => 'Submit AMC checklist',
                'priority' => 'completed',
                'priorityLabel' => 'Completed',
                'deadline' => 'Completed 20 May · 05:10 PM',
                'link' => 'Checklist shared with Admin records.',
                'action' => ['attr' => 'data-task-undo', 'label' => 'Move back'],
            ],
        ],
    ],
];

$activeComplaintsCount = count(array_filter($tickets, static function (array $ticket): bool {
    return ($ticket['status'] ?? '') !== 'resolved';
}));

$pendingTasksCount = 0;
foreach ($taskColumns as $columnKey => $column) {
    if ($columnKey === 'done') {
        continue;
    }
    $pendingTasksCount += count($column['items']);
}

$summaryMetrics = [
    'activeComplaints' => $activeComplaintsCount,
    'pendingTasks' => $pendingTasksCount,
];

$taskActivity = [
    ['time' => '2024-05-21T09:45', 'label' => '21 May · 09:45', 'message' => 'You moved <strong>Update ticket attachments</strong> to In Progress.'],
    ['time' => '2024-05-20T17:10', 'label' => '20 May · 17:10', 'message' => 'Marked <strong>Submit AMC checklist</strong> as completed and shared with Admin.'],
    ['time' => '2024-05-20T11:05', 'label' => '20 May · 11:05', 'message' => 'Added <strong>Call back – Skyline AMC</strong> with reminder set for 21 May.'],
];

$leadUpdates = [
    ['time' => '2024-05-21T08:30', 'label' => '21 May · 08:30', 'message' => 'Contacted <strong>Rakesh Kumar</strong> – scheduled roof assessment for 23 May.'],
    ['time' => '2024-05-20T18:15', 'label' => '20 May · 18:15', 'message' => 'Updated <strong>Shivani Constructions</strong> lead stage to Proposal Sent.'],
    ['time' => '2024-05-20T14:20', 'label' => '20 May · 14:20', 'message' => 'Added customer documents for <strong>Anil Kumar</strong> – pending Admin review.'],
];

$pendingLeads = [
    ['name' => 'Mahesh Colony Resident', 'contact' => '+91 90000 11223', 'source' => 'Field visit'],
    ['name' => 'Sunrise Apartments Committee', 'contact' => '+91 98111 33221', 'source' => 'Community outreach'],
];

$leadRecords = [
    [
        'id' => 'L-472',
        'type' => 'lead',
        'label' => 'Lead #L-472',
        'description' => 'Housing Colony walk-in',
        'contactName' => 'Mrs. Anita Sharma',
        'contactDetail' => '+91 98765 44321',
        'statusTone' => 'progress',
        'statusLabel' => 'Follow-up scheduled',
        'nextAction' => '22 May · Site proposal',
        'owner' => $employeeName,
    ],
    [
        'id' => 'L-458',
        'type' => 'lead',
        'label' => 'Lead #L-458',
        'description' => 'Referral · Doranda',
        'contactName' => 'Mr. Nikhil Rao',
        'contactDetail' => '+91 99887 22110',
        'statusTone' => 'progress',
        'statusLabel' => 'Demo booked',
        'nextAction' => '21 May · System sizing',
        'owner' => 'Service Desk',
    ],
    [
        'id' => 'L-430',
        'type' => 'lead',
        'label' => 'Lead #L-430',
        'description' => 'Commercial enquiry',
        'contactName' => 'Ms. Kavya Patel',
        'contactDetail' => '+91 91234 11223',
        'statusTone' => 'waiting',
        'statusLabel' => 'Awaiting documents',
        'nextAction' => '23 May · Send checklist',
        'owner' => 'Service Desk',
    ],
    [
        'id' => 'C-214',
        'type' => 'customer',
        'label' => 'Customer #C-214',
        'description' => 'Green Valley Apartments',
        'contactName' => 'Facilities Office',
        'contactDetail' => 'support@greenvalley.in',
        'statusTone' => 'progress',
        'statusLabel' => 'AMC due',
        'nextAction' => '24 May · AMC visit',
        'owner' => $employeeName,
    ],
    [
        'id' => 'C-198',
        'type' => 'customer',
        'label' => 'Customer #C-198',
        'description' => 'Skyline Residency',
        'contactName' => 'Maintenance Desk',
        'contactDetail' => 'maintenance@skyline.in',
        'statusTone' => 'resolved',
        'statusLabel' => 'Resolved',
        'nextAction' => 'Completed AMC review',
        'owner' => 'Service Desk',
    ],
];

$leadsHandledCount = count(array_filter($leadRecords, static function (array $record): bool {
    return ($record['type'] ?? '') === 'lead';
}));

$amcRecords = array_values(array_filter($leadRecords, static function (array $record): bool {
    return stripos($record['statusLabel'] ?? '', 'AMC') !== false;
}));
$amcDueSoonCount = count($amcRecords);
$amcMeta = $amcDueSoonCount > 0
    ? sprintf('Next visit: %s · %s.', $amcRecords[0]['nextAction'], $amcRecords[0]['description'])
    : 'No AMC visits scheduled.';

$overviewMetrics = [
    [
        'icon' => 'fa-solid fa-ticket',
        'tone' => 'neutral',
        'title' => 'Active complaints',
        'value' => $summaryMetrics['activeComplaints'],
        'meta' => 'Tickets currently assigned to you.',
        'dataTarget' => 'activeComplaints',
    ],
    [
        'icon' => 'fa-solid fa-list-check',
        'tone' => 'warning',
        'title' => 'Pending tasks',
        'value' => $summaryMetrics['pendingTasks'],
        'meta' => 'Includes to-do and in-progress activities.',
        'dataTarget' => 'pendingTasks',
    ],
    [
        'icon' => 'fa-solid fa-user-plus',
        'tone' => 'neutral',
        'title' => 'Leads handled',
        'value' => $leadsHandledCount,
        'meta' => 'Active prospects owned by the Service desk.',
    ],
    [
        'icon' => 'fa-solid fa-calendar-check',
        'tone' => 'positive',
        'title' => 'AMC visits due soon',
        'value' => $amcDueSoonCount,
        'meta' => $amcMeta,
    ],
];

$taskReminders = [
    ['icon' => 'fa-solid fa-bell', 'message' => 'Call DISCOM for Ticket T-238 by 14:00.'],
    ['icon' => 'fa-solid fa-flag', 'message' => 'Verify AMC stock checklist before 24 May visit.'],
    ['icon' => 'fa-solid fa-user-check', 'message' => 'Share Doranda site entry pass with installer team.'],
];

$notifications = [
    ['tone' => 'info', 'icon' => 'fa-solid fa-circle-info', 'title' => 'New SOP uploaded', 'message' => "Admin shared the latest inverter safety SOP. Review before tomorrow's visit."],
    ['tone' => 'warning', 'icon' => 'fa-solid fa-triangle-exclamation', 'title' => 'SLA approaching', 'message' => 'Ticket T-238 follow-up is due in 4 hours.'],
];

$policyItems = [
    ['icon' => 'fa-solid fa-shield', 'text' => 'Employees join only after Admin approval and role assignment.'],
    ['icon' => 'fa-solid fa-eye-slash', 'text' => 'Access scope limits visibility to Service tickets, tasks, leads, and shared customers.'],
    ['icon' => 'fa-solid fa-lock', 'text' => 'Password hashing, session locks, and login throttling mirror Admin controls.'],
    ['icon' => 'fa-solid fa-ban', 'text' => "Admin settings and other users' data remain hidden."],
];

$attachmentIcon = static function (string $filename): string {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return match ($ext) {
        'pdf' => 'fa-solid fa-file-lines',
        'jpg', 'jpeg', 'png', 'gif', 'webp' => 'fa-solid fa-image',
        default => 'fa-solid fa-paperclip',
    };
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Employee Workspace | Dakshayani Enterprises</title>
  <meta
    name="description"
    content="Role-based employee workspace for Dakshayani Enterprises with ticket updates, task management, and customer follow-ups."
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
          <i class="fa-solid fa-user-tie" aria-hidden="true"></i>
          <div>
            <small>Signed in as</small>
            <strong><?= htmlspecialchars($employeeName, ENT_QUOTES) ?> · <?= htmlspecialchars($employeeRole, ENT_QUOTES) ?></strong>
            <p class="text-xs text-muted mb-0">Access: <?= htmlspecialchars($employeeAccess, ENT_QUOTES) ?></p>
          </div>
        </div>
        <div class="dashboard-auth-actions">
          <div class="dashboard-theme-toggle" role="group" aria-label="Theme preference">
            <label>
              <input type="radio" name="employee-theme" value="light" checked data-theme-option />
              <span><i class="fa-solid fa-sun" aria-hidden="true"></i> Light</span>
            </label>
            <label>
              <input type="radio" name="employee-theme" value="dark" data-theme-option />
              <span><i class="fa-solid fa-moon" aria-hidden="true"></i> Dark</span>
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
          <span class="badge"><i class="fa-solid fa-solar-panel" aria-hidden="true"></i> Service desk workspace</span>
          <h1>Welcome back, <?= htmlspecialchars($firstName, ENT_QUOTES) ?></h1>
        </div>
        <p class="dashboard-subheading">
          Your portal mirrors admin-grade authentication but only surfaces assignments cleared for the Service role.
          Track open complaints, priority tasks, and follow-ups without exposing admin settings or other users' data.
        </p>
        <div class="employee-header-actions" role="toolbar" aria-label="Employee quick actions">
          <button type="button" class="employee-header-button" data-open-profile>
            <i class="fa-solid fa-id-badge" aria-hidden="true"></i>
            <span>Profile</span>
          </button>
          <button type="button" class="employee-header-button" data-open-notifications>
            <i class="fa-solid fa-bell" aria-hidden="true"></i>
            <span>Notifications</span>
            <span class="employee-header-count" data-notification-count><?= count($notifications) ?></span>
          </button>
          <a class="employee-header-button" href="#tasks" data-scroll-my-work>
            <i class="fa-solid fa-list-check" aria-hidden="true"></i>
            <span>My Work</span>
          </a>
        </div>
        <nav class="dashboard-quick-nav" aria-label="Employee navigation">
          <a href="#overview" class="dashboard-quick-nav__link is-active" data-quick-link>
            <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
            <span>Overview</span>
          </a>
          <a href="#complaints" class="dashboard-quick-nav__link" data-quick-link>
            <i class="fa-solid fa-ticket" aria-hidden="true"></i>
            <span>Complaints</span>
          </a>
          <a href="#tasks" class="dashboard-quick-nav__link" data-quick-link>
            <i class="fa-solid fa-clipboard-list" aria-hidden="true"></i>
            <span>My Work</span>
          </a>
          <a href="#leads" class="dashboard-quick-nav__link" data-quick-link>
            <i class="fa-solid fa-users" aria-hidden="true"></i>
            <span>Leads &amp; Follow-ups</span>
          </a>
        </nav>
      </header>

      <div class="dashboard-body">
        <div class="dashboard-main">
          <section id="overview" class="dashboard-section" data-section>
            <h2>Employee overview</h2>
            <p class="dashboard-section-sub">
              Employees authenticate with hashed passwords, identical session hardening, and throttled logins. Access scope,
              module visibility, and data ownership are enforced from your admin-defined role.
            </p>
            <div class="dashboard-cards dashboard-cards--grid">
              <?php foreach ($overviewMetrics as $metric): ?>
              <article class="dashboard-card dashboard-card--<?= htmlspecialchars($metric['tone'], ENT_QUOTES) ?>">
                <div class="dashboard-card-icon"><i class="<?= htmlspecialchars($metric['icon'], ENT_QUOTES) ?>" aria-hidden="true"></i></div>
                <div>
                  <p class="dashboard-card-title"><?= htmlspecialchars($metric['title'], ENT_QUOTES) ?></p>
                  <p class="dashboard-card-value"<?= isset($metric['dataTarget']) ? ' data-summary-target="' . htmlspecialchars($metric['dataTarget'], ENT_QUOTES) . '"' : '' ?>><?= htmlspecialchars((string) $metric['value'], ENT_QUOTES) ?></p>
                  <p class="dashboard-card-meta"><?= htmlspecialchars($metric['meta'], ENT_QUOTES) ?></p>
                </div>
              </article>
              <?php endforeach; ?>
              <article class="dashboard-card dashboard-card--neutral dashboard-card--metrics">
                <div class="dashboard-card-icon"><i class="fa-solid fa-bolt" aria-hidden="true"></i></div>
                <div>
                  <p class="dashboard-card-title">Performance metrics</p>
                  <ul class="dashboard-card-metrics">
                    <?php foreach ($performanceMetrics as $metric): ?>
                    <li><strong><?= htmlspecialchars($metric['value'], ENT_QUOTES) ?></strong> <?= htmlspecialchars($metric['label'], ENT_QUOTES) ?></li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              </article>
            </div>
          </section>

          <section id="complaints" class="dashboard-section" data-section>
            <h2>Complaints &amp; service workflow</h2>
            <p class="dashboard-section-sub">
              Tickets are provisioned by Admin with customer details, SLA timers, and attachments. Update status, add field notes,
              or escalate back to Admin for complex issues—every action updates the timeline automatically.
            </p>
            <div class="ticket-board">
              <?php foreach ($tickets as $ticket): ?>
              <article class="ticket-card" data-ticket-id="<?= htmlspecialchars($ticket['id'], ENT_QUOTES) ?>" data-status="<?= htmlspecialchars($ticket['status'], ENT_QUOTES) ?>">
                <header class="ticket-card__header">
                  <div>
                    <h3><?= htmlspecialchars($ticket['title'], ENT_QUOTES) ?></h3>
                    <p class="ticket-card__meta">Customer: <?= htmlspecialchars($ticket['customer'], ENT_QUOTES) ?></p>
                  </div>
                  <span class="dashboard-status dashboard-status--<?= htmlspecialchars($ticket['statusTone'], ENT_QUOTES) ?>" data-ticket-status-label><?= htmlspecialchars($ticket['statusLabel'], ENT_QUOTES) ?></span>
                </header>
                <dl class="ticket-details">
                  <div>
                    <dt>Assigned by</dt>
                    <dd><?= htmlspecialchars($ticket['assignedBy'], ENT_QUOTES) ?></dd>
                  </div>
                  <div>
                    <dt>SLA target</dt>
                    <dd><?= htmlspecialchars($ticket['sla'], ENT_QUOTES) ?></dd>
                  </div>
                  <div>
                    <dt>Contact</dt>
                    <dd><?= htmlspecialchars($ticket['contact'], ENT_QUOTES) ?></dd>
                  </div>
                </dl>
                <div class="ticket-actions">
                  <label class="ticket-actions__field">
                    <span>Status</span>
                    <select data-ticket-status>
                      <?php foreach ($statusOptions as $value => $label): ?>
                      <option value="<?= htmlspecialchars($value, ENT_QUOTES) ?>"<?= $ticket['status'] === $value ? ' selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </label>
                  <button type="button" class="btn btn-ghost btn-sm" data-ticket-note>
                    <i class="<?= htmlspecialchars($ticket['noteIcon'], ENT_QUOTES) ?>" aria-hidden="true"></i>
                    <?= htmlspecialchars($ticket['noteLabel'], ENT_QUOTES) ?>
                  </button>
                  <button type="button" class="btn btn-secondary btn-sm" data-ticket-escalate>
                    <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                    Return to Admin
                  </button>
                </div>
                <?php if (!empty($ticket['attachments'])): ?>
                <div class="ticket-attachments">
                  <h4>Attachments</h4>
                  <ul>
                    <?php foreach ($ticket['attachments'] as $attachment): ?>
                    <li><i class="<?= htmlspecialchars($attachmentIcon($attachment), ENT_QUOTES) ?>" aria-hidden="true"></i> <?= htmlspecialchars($attachment, ENT_QUOTES) ?></li>
                    <?php endforeach; ?>
                  </ul>
                </div>
                <?php endif; ?>
                <?php if (!empty($ticket['timeline'])): ?>
                <div class="ticket-timeline">
                  <h4>Timeline</h4>
                  <ol data-ticket-timeline>
                    <?php foreach ($ticket['timeline'] as $entry): ?>
                    <li>
                      <time datetime="<?= htmlspecialchars($entry['time'], ENT_QUOTES) ?>"><?= htmlspecialchars($entry['label'], ENT_QUOTES) ?></time>
                      <p><?= $entry['message'] ?></p>
                    </li>
                    <?php endforeach; ?>
                  </ol>
                </div>
                <?php endif; ?>
              </article>
              <?php endforeach; ?>
            </div>
          </section>

          <section id="tasks" class="dashboard-section" data-section>
            <h2>Tasks &amp; My Work</h2>
            <p class="dashboard-section-sub">
              Update statuses inline or drag cards between columns. Priorities, due dates, and reminders keep your workload aligned
              with Admin expectations.
            </p>
            <div class="task-board" data-task-board>
              <?php foreach ($taskColumns as $columnKey => $column): ?>
              <section class="task-column" data-task-column="<?= htmlspecialchars($columnKey, ENT_QUOTES) ?>" data-status-label="<?= htmlspecialchars($column['label'], ENT_QUOTES) ?>">
                <header>
                  <h3><?= htmlspecialchars($column['label'], ENT_QUOTES) ?> <span class="task-count" data-task-count>(<?= count($column['items']) ?>)</span></h3>
                  <p class="task-column-meta"><?= htmlspecialchars($column['meta'] ?? '', ENT_QUOTES) ?></p>
                </header>
                <div class="task-column-body">
                  <?php foreach ($column['items'] as $item): ?>
                  <article class="task-card" data-task-id="<?= htmlspecialchars($item['id'], ENT_QUOTES) ?>" draggable="true">
                    <div class="task-card-head">
                      <p class="task-card-title"><?= htmlspecialchars($item['title'], ENT_QUOTES) ?></p>
                      <span class="task-label task-label--<?= htmlspecialchars($item['priority'], ENT_QUOTES) ?>"><?= htmlspecialchars($item['priorityLabel'], ENT_QUOTES) ?></span>
                    </div>
                    <p class="task-card-meta"><i class="fa-solid fa-clock" aria-hidden="true"></i> <?= htmlspecialchars($item['deadline'], ENT_QUOTES) ?></p>
                    <p class="task-card-link"><?= $item['link'] ?></p>
                    <?php if (!empty($item['action'])): ?>
                    <button type="button" class="task-card-action" <?= $item['action']['attr'] ?>><?= htmlspecialchars($item['action']['label'], ENT_QUOTES) ?></button>
                    <?php endif; ?>
                  </article>
                  <?php endforeach; ?>
                </div>
              </section>
              <?php endforeach; ?>
            </div>
            <div class="task-footer">
              <div class="task-activity-block">
                <h3>Task activity</h3>
                <ol class="task-activity" data-task-activity>
                  <?php foreach ($taskActivity as $entry): ?>
                  <li>
                    <time datetime="<?= htmlspecialchars($entry['time'], ENT_QUOTES) ?>"><?= htmlspecialchars($entry['label'], ENT_QUOTES) ?></time>
                    <p><?= $entry['message'] ?></p>
                  </li>
                  <?php endforeach; ?>
                </ol>
              </div>
              <div class="task-reminder-block">
                <h3>Upcoming reminders</h3>
                <ul class="task-reminders">
                  <?php foreach ($taskReminders as $reminder): ?>
                  <li><i class="<?= htmlspecialchars($reminder['icon'], ENT_QUOTES) ?>" aria-hidden="true"></i> <?= htmlspecialchars($reminder['message'], ENT_QUOTES) ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
            </div>
          </section>

          <section id="leads" class="dashboard-section" data-section>
            <h2>Leads &amp; customer follow-ups</h2>
            <p class="dashboard-section-sub">
              Admin shares customer records with your role. Add notes, update contact details, or create new prospects—fresh entries
              wait for Admin approval before they appear in the main CRM.
            </p>
            <div class="lead-layout">
              <div class="dashboard-table-wrapper lead-table">
                <table class="dashboard-table">
                  <thead>
                    <tr>
                      <th scope="col">Lead</th>
                      <th scope="col">Contact</th>
                      <th scope="col">Status</th>
                      <th scope="col">Next action</th>
                      <th scope="col">Owner</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($leadRecords as $record): ?>
                    <tr data-lead-row="<?= htmlspecialchars($record['id'], ENT_QUOTES) ?>">
                      <td>
                        <div class="dashboard-user">
                          <strong><?= htmlspecialchars($record['label'], ENT_QUOTES) ?></strong>
                          <span><?= htmlspecialchars($record['description'], ENT_QUOTES) ?></span>
                        </div>
                      </td>
                      <td>
                        <div class="dashboard-user">
                          <strong><?= htmlspecialchars($record['contactName'], ENT_QUOTES) ?></strong>
                          <span><?= htmlspecialchars($record['contactDetail'], ENT_QUOTES) ?></span>
                        </div>
                      </td>
                      <td><span class="dashboard-status dashboard-status--<?= htmlspecialchars($record['statusTone'], ENT_QUOTES) ?>"><?= htmlspecialchars($record['statusLabel'], ENT_QUOTES) ?></span></td>
                      <td><?= htmlspecialchars($record['nextAction'], ENT_QUOTES) ?></td>
                      <td><?= htmlspecialchars($record['owner'], ENT_QUOTES) ?></td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>

              <aside class="lead-sidebar">
                <article class="dashboard-panel">
                  <h2>Log follow-up</h2>
                  <form class="lead-note-form" data-lead-note-form>
                    <label>
                      Lead or customer
                      <select name="lead" required>
                        <option value="" selected disabled>Select record</option>
                        <?php foreach ($leadRecords as $record): ?>
                        <option value="<?= htmlspecialchars($record['id'], ENT_QUOTES) ?>"><?= htmlspecialchars($record['label'] . ' · ' . $record['description'], ENT_QUOTES) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </label>
                    <label>
                      Outcome / next step
                      <textarea name="note" rows="3" placeholder="Document your conversation and agreed follow-up." required></textarea>
                    </label>
                    <button type="submit" class="btn btn-primary btn-sm">Save update</button>
                  </form>
                  <div class="lead-activity">
                    <h3>Recent updates</h3>
                    <ul data-lead-activity>
                      <?php foreach ($leadUpdates as $update): ?>
                      <li>
                        <time datetime="<?= htmlspecialchars($update['time'], ENT_QUOTES) ?>"><?= htmlspecialchars($update['label'], ENT_QUOTES) ?></time>
                        <p><?= $update['message'] ?></p>
                      </li>
                      <?php endforeach; ?>
                    </ul>
                  </div>
                </article>

                <article class="dashboard-panel dashboard-panel--muted">
                  <h2>Submit new prospect</h2>
                  <form class="lead-intake-form" data-lead-intake>
                    <label>
                      Prospect name
                      <input type="text" name="prospect" placeholder="e.g., Sunrise Enclave" required />
                    </label>
                    <label>
                      Location
                      <input type="text" name="location" placeholder="City / landmark" required />
                    </label>
                    <label>
                      Contact number
                      <input type="tel" name="contact" placeholder="10-digit mobile" required pattern="[0-9]{10}" />
                    </label>
                    <button type="submit" class="btn btn-secondary btn-sm">Send for approval</button>
                  </form>
                  <p class="text-xs text-muted mb-0">New entries remain pending until Admin verifies the details.</p>
                  <ul class="pending-leads" data-pending-leads>
                    <?php foreach ($pendingLeads as $prospect): ?>
                    <li>
                      <strong><?= htmlspecialchars($prospect['name'], ENT_QUOTES) ?></strong>
                      <span>Pending Admin approval · <?= htmlspecialchars($prospect['source'], ENT_QUOTES) ?></span>
                    </li>
                    <?php endforeach; ?>
                  </ul>
                </article>
              </aside>
            </div>
          </section>
        </div>

        <aside class="dashboard-aside">
          <article class="dashboard-panel">
            <h2>Role, access &amp; onboarding</h2>
            <ul class="policy-list">
              <?php foreach ($policyItems as $policy): ?>
              <li><i class="<?= htmlspecialchars($policy['icon'], ENT_QUOTES) ?>" aria-hidden="true"></i> <?= htmlspecialchars($policy['text'], ENT_QUOTES) ?></li>
              <?php endforeach; ?>
            </ul>
          </article>
          <article class="dashboard-panel">
            <h2>Notifications</h2>
            <ul class="dashboard-notifications">
              <?php foreach ($notifications as $notice): ?>
              <li class="dashboard-notification dashboard-notification--<?= htmlspecialchars($notice['tone'], ENT_QUOTES) ?>">
                <i class="<?= htmlspecialchars($notice['icon'], ENT_QUOTES) ?>" aria-hidden="true"></i>
                <div>
                  <p><?= htmlspecialchars($notice['title'], ENT_QUOTES) ?></p>
                  <span><?= htmlspecialchars($notice['message'], ENT_QUOTES) ?></span>
                </div>
              </li>
              <?php endforeach; ?>
            </ul>
          </article>
          <article class="dashboard-panel dashboard-panel--muted">
            <h2>Quick references</h2>
            <div class="dashboard-actions">
              <button class="dashboard-action" type="button">
                <span class="dashboard-action-icon"><i class="fa-solid fa-file-lines" aria-hidden="true"></i></span>
                <span>
                  <span class="label">Service playbook</span>
                  <span class="description">Check escalation steps and documentation templates.</span>
                </span>
                <span class="dashboard-action-caret"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></span>
              </button>
              <button class="dashboard-action" type="button">
                <span class="dashboard-action-icon"><i class="fa-solid fa-people-arrows" aria-hidden="true"></i></span>
                <span>
                  <span class="label">Escalation matrix</span>
                  <span class="description">Contact Admin when customer issues need reinforcements.</span>
                </span>
                <span class="dashboard-action-caret"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></span>
              </button>
            </div>
          </article>
        </aside>
      </div>
    </div>
  </main>

  <script src="employee-dashboard.js" defer></script>
  <script src="script.js" defer></script>
</body>
</html>
