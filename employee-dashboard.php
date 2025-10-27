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

$taskReminders = [
    ['icon' => 'fa-solid fa-bell', 'message' => 'Call DISCOM for Ticket T-238 by 14:00.'],
    ['icon' => 'fa-solid fa-flag', 'message' => 'Verify AMC stock checklist before 24 May visit.'],
    ['icon' => 'fa-solid fa-user-check', 'message' => 'Share Doranda site entry pass with installer team.'],
];

$siteVisits = [
    [
        'id' => 'VIS-301',
        'title' => '5 kW rooftop installation',
        'customer' => 'Green Valley Apartments',
        'address' => 'Tower 2, Ranchi · Pin 834001',
        'scheduled' => '22 May 2024 · 09:30 AM',
        'checklist' => [
            'Verify structure alignment and torque settings',
            'Mount 10 × 500 W panels and connect string DC wiring',
            'Capture inverter serial number and meter reading',
        ],
        'requiredPhotos' => ['Array layout', 'Inverter close-up', 'Net meter panel'],
        'status' => 'scheduled',
        'statusLabel' => 'Scheduled',
        'statusTone' => 'progress',
        'notes' => 'Commissioning with DISCOM inspector at 13:00 hrs.',
    ],
    [
        'id' => 'VIS-302',
        'title' => 'Preventive maintenance check',
        'customer' => 'Skyline Residency',
        'address' => 'Block B Utility Room, Ranchi',
        'scheduled' => '23 May 2024 · 11:15 AM',
        'checklist' => [
            'Clean modules and check string voltages',
            'Record inverter voltage and current',
            'Upload geo-tagged verification photo',
        ],
        'requiredPhotos' => ['String voltage screen', 'Array condition'],
        'status' => 'scheduled',
        'statusLabel' => 'Scheduled',
        'statusTone' => 'progress',
        'notes' => 'Customer contact: Maintenance Desk · +91 99887 22110',
    ],
    [
        'id' => 'VIS-298',
        'title' => 'On-site troubleshooting',
        'customer' => 'Meera Housing Society',
        'address' => 'Block C Terrace, Ranchi',
        'scheduled' => '21 May 2024 · 03:00 PM',
        'checklist' => [
            'Inspect inverter alarms',
            'Capture thermal image of combiner box',
            'Update job checklist on completion',
        ],
        'requiredPhotos' => ['Combiner box interior'],
        'status' => 'awaiting_photos',
        'statusLabel' => 'Awaiting photos',
        'statusTone' => 'warning',
        'notes' => 'Awaiting panel photos to close ticket T-205.',
    ],
];

$visitActivity = [
    ['time' => '2024-05-21T12:45', 'label' => '21 May · 12:45', 'message' => 'VIS-298: Uploaded inverter alarm screenshot for Admin review.'],
    ['time' => '2024-05-20T18:20', 'label' => '20 May · 18:20', 'message' => 'VIS-301 scheduled · Checklist shared with installer team.'],
];

$documentVault = [
    [
        'id' => 'DOC-109',
        'customer' => 'Green Valley Apartments',
        'type' => 'Service Photo Set',
        'filename' => 'green-valley-array-may21.zip',
        'uploadedBy' => $employeeName,
        'uploadedAt' => '2024-05-21T13:20',
        'status' => 'pending',
        'statusLabel' => 'Pending Admin review',
        'tone' => 'warning',
    ],
    [
        'id' => 'DOC-105',
        'customer' => 'Skyline Residency',
        'type' => 'Maintenance Invoice',
        'filename' => 'skyline-amc-invoice.pdf',
        'uploadedBy' => 'Admin (Finance)',
        'uploadedAt' => '2024-05-18T16:05',
        'status' => 'approved',
        'statusLabel' => 'Approved by Admin',
        'tone' => 'positive',
    ],
];

$documentUploadCustomers = [
    'Green Valley Apartments',
    'Skyline Residency',
    'Meera Housing Society',
];

$subsidyCases = [
    [
        'id' => 'PM-78',
        'customer' => 'Meera Housing Society',
        'capacity' => '5 kW Rooftop',
        'stages' => [
            'applied' => ['label' => 'Applied', 'completed' => true, 'completedAt' => '2024-05-15'],
            'inspected' => ['label' => 'Inspected', 'completed' => false],
            'redeemed' => ['label' => 'Redeemed', 'completed' => false],
        ],
        'note' => 'Awaiting DISCOM inspection slot confirmation.',
    ],
    [
        'id' => 'PM-82',
        'customer' => 'Sunrise Apartments',
        'capacity' => '12 kW Rooftop',
        'stages' => [
            'applied' => ['label' => 'Applied', 'completed' => true, 'completedAt' => '2024-05-12'],
            'inspected' => ['label' => 'Inspected', 'completed' => true, 'completedAt' => '2024-05-19'],
            'redeemed' => ['label' => 'Redeemed', 'completed' => false],
        ],
        'note' => 'Docs ready for Admin final submission.',
    ],
];

$subsidyActivity = [
    ['time' => '2024-05-21T09:05', 'label' => '21 May · 09:05', 'message' => 'Collected inspection checklist for case PM-82 – awaiting Admin validation.'],
    ['time' => '2024-05-20T15:42', 'label' => '20 May · 15:42', 'message' => 'Submitted rooftop photos for case PM-78 (Applied stage).'],
];

$warrantyAssets = [
    [
        'id' => 'AMC-451',
        'customer' => 'Skyline Residency',
        'asset' => 'Sungrow Inverter SG5K-D',
        'warranty' => 'Warranty till Sep 2026',
        'nextVisit' => '24 May 2024',
        'status' => 'due',
        'statusLabel' => 'Due in 3 days',
        'tone' => 'waiting',
        'lastVisit' => '26 May 2023',
    ],
    [
        'id' => 'AMC-458',
        'customer' => 'Green Valley Apartments',
        'asset' => 'Adani Solar 5 kW array',
        'warranty' => 'Module warranty till Jan 2040',
        'nextVisit' => '21 Jun 2024',
        'status' => 'scheduled',
        'statusLabel' => 'Scheduled',
        'tone' => 'progress',
        'lastVisit' => '22 Dec 2023',
    ],
    [
        'id' => 'AMC-460',
        'customer' => 'Meera Housing Society',
        'asset' => 'Polycab Balance of System',
        'warranty' => 'AMC valid till Dec 2024',
        'nextVisit' => '15 May 2024',
        'status' => 'overdue',
        'statusLabel' => 'Overdue',
        'tone' => 'escalated',
        'lastVisit' => '14 May 2023',
    ],
];

$warrantyActivity = [
    ['time' => '2024-05-21T10:25', 'label' => '21 May · 10:25', 'message' => 'Logged inverter voltage check for Skyline Residency AMC.'],
    ['time' => '2024-05-20T12:10', 'label' => '20 May · 12:10', 'message' => 'Uploaded filter cleaning photos for Green Valley Apartments.'],
];

$communicationLogs = [
    ['time' => '2024-05-21T10:10', 'label' => '21 May · 10:10', 'channel' => 'Call', 'summary' => 'Confirmed AMC visit with Skyline Residency for 24 May.'],
    ['time' => '2024-05-20T17:55', 'label' => '20 May · 17:55', 'channel' => 'Email', 'summary' => 'Shared inverter troubleshooting steps with Meera Housing Society.'],
    ['time' => '2024-05-20T09:30', 'label' => '20 May · 09:30', 'channel' => 'Visit', 'summary' => 'Completed site walk-through at Green Valley Apartments.'],
];

$notifications = [
    ['tone' => 'info', 'icon' => 'fa-solid fa-circle-info', 'title' => 'New SOP uploaded', 'message' => "Admin shared the latest inverter safety SOP. Review before tomorrow's visit."],
    ['tone' => 'warning', 'icon' => 'fa-solid fa-triangle-exclamation', 'title' => 'SLA approaching', 'message' => 'Ticket T-238 follow-up is due in 4 hours.'],
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

$scheduledVisitCount = count(array_filter($siteVisits, static function (array $visit): bool {
    return ($visit['status'] ?? '') !== 'completed';
}));

$leadsHandledCount = count(array_filter($leadRecords, static function (array $record): bool {
    return ($record['type'] ?? '') === 'lead';
}));

$amcDueSoonCount = count(array_filter($warrantyAssets, static function (array $asset): bool {
    return in_array($asset['status'] ?? '', ['scheduled', 'due', 'overdue'], true);
}));

$nextAmc = null;
foreach ($warrantyAssets as $asset) {
    if (in_array($asset['status'] ?? '', ['overdue', 'due', 'scheduled'], true)) {
        $nextAmc = $asset;
        break;
    }
}

$amcMeta = $nextAmc
    ? sprintf('Next visit: %s · %s.', $nextAmc['nextVisit'], $nextAmc['customer'])
    : 'No AMC visits scheduled.';

$summaryMetrics = [
    'activeComplaints' => $activeComplaintsCount,
    'pendingTasks' => $pendingTasksCount,
    'scheduledVisits' => $scheduledVisitCount,
];

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
        'icon' => 'fa-solid fa-map-location-dot',
        'tone' => 'neutral',
        'title' => 'Scheduled field visits',
        'value' => $summaryMetrics['scheduledVisits'],
        'meta' => 'Installations and maintenance visits awaiting completion.',
        'dataTarget' => 'scheduledVisits',
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
          <a href="#field-work" class="dashboard-quick-nav__link" data-quick-link>
            <i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i>
            <span>Field Work</span>
          </a>
          <a href="#documents" class="dashboard-quick-nav__link" data-quick-link>
            <i class="fa-solid fa-folder-open" aria-hidden="true"></i>
            <span>Documents</span>
          </a>
          <a href="#subsidy" class="dashboard-quick-nav__link" data-quick-link>
            <i class="fa-solid fa-indian-rupee-sign" aria-hidden="true"></i>
            <span>Subsidy</span>
          </a>
          <a href="#warranty" class="dashboard-quick-nav__link" data-quick-link>
            <i class="fa-solid fa-shield-heart" aria-hidden="true"></i>
            <span>Warranty &amp; AMC</span>
          </a>
          <a href="#communication" class="dashboard-quick-nav__link" data-quick-link>
            <i class="fa-solid fa-phone-volume" aria-hidden="true"></i>
            <span>Communication</span>
          </a>
          <a href="#ai-assist" class="dashboard-quick-nav__link" data-quick-link>
            <i class="fa-solid fa-robot" aria-hidden="true"></i>
            <span>AI Assistance</span>
          </a>
        </nav>
      </header>

      <div class="dashboard-body dashboard-body--employee">
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

          <section id="field-work" class="dashboard-section" data-section>
            <h2>Installation &amp; field work</h2>
            <p class="dashboard-section-sub">
              Review every scheduled installation or maintenance visit, capture geo-tags when available, and close assignments
              so Admin can review commissioning evidence before locking tickets.
            </p>
            <div class="visit-layout">
              <div class="visit-grid">
                <?php foreach ($siteVisits as $visit): ?>
                <article
                  class="visit-card dashboard-panel"
                  data-visit-card
                  data-visit-id="<?= htmlspecialchars($visit['id'], ENT_QUOTES) ?>"
                  data-visit-status="<?= htmlspecialchars($visit['status'], ENT_QUOTES) ?>"
                  data-visit-customer="<?= htmlspecialchars($visit['customer'], ENT_QUOTES) ?>"
                >
                  <header class="visit-card-header">
                    <div>
                      <small class="text-xs text-muted"><?= htmlspecialchars($visit['id'], ENT_QUOTES) ?></small>
                      <h3><?= htmlspecialchars($visit['title'], ENT_QUOTES) ?></h3>
                      <p class="visit-card-customer"><?= htmlspecialchars($visit['customer'], ENT_QUOTES) ?></p>
                    </div>
                    <span
                      class="dashboard-status dashboard-status--<?= htmlspecialchars($visit['statusTone'] ?? 'progress', ENT_QUOTES) ?>"
                      data-visit-status
                    ><?= htmlspecialchars($visit['statusLabel'] ?? 'Scheduled', ENT_QUOTES) ?></span>
                  </header>
                  <ul class="visit-meta">
                    <li><i class="fa-solid fa-calendar-days" aria-hidden="true"></i> <?= htmlspecialchars($visit['scheduled'], ENT_QUOTES) ?></li>
                    <li><i class="fa-solid fa-location-dot" aria-hidden="true"></i> <?= htmlspecialchars($visit['address'], ENT_QUOTES) ?></li>
                  </ul>
                  <div class="visit-block">
                    <h4>Job checklist</h4>
                    <ul>
                      <?php foreach ($visit['checklist'] as $item): ?>
                      <li><?= htmlspecialchars($item, ENT_QUOTES) ?></li>
                      <?php endforeach; ?>
                    </ul>
                  </div>
                  <div class="visit-block visit-block--photos">
                    <h4>Required photos</h4>
                    <ul>
                      <?php foreach ($visit['requiredPhotos'] as $photo): ?>
                      <li><i class="fa-solid fa-camera" aria-hidden="true"></i> <?= htmlspecialchars($photo, ENT_QUOTES) ?></li>
                      <?php endforeach; ?>
                    </ul>
                  </div>
                  <?php if (!empty($visit['notes'])): ?>
                  <p class="visit-notes"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> <?= htmlspecialchars($visit['notes'], ENT_QUOTES) ?></p>
                  <?php endif; ?>
                  <p class="visit-geotag" data-visit-geotag-wrapper hidden>
                    <i class="fa-solid fa-location-crosshairs" aria-hidden="true"></i>
                    <span>Geo-tag: <strong data-visit-geotag-label></strong></span>
                  </p>
                  <footer class="visit-card-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-visit-geotag>
                      <i class="fa-solid fa-location-arrow" aria-hidden="true"></i>
                      Log geo-tag
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" data-visit-complete>
                      <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                      Mark completed
                    </button>
                  </footer>
                </article>
                <?php endforeach; ?>
              </div>
              <aside class="visit-activity-panel dashboard-panel">
                <h3>Visit updates</h3>
                <ol class="visit-activity" data-visit-activity>
                  <?php foreach ($visitActivity as $entry): ?>
                  <li>
                    <time datetime="<?= htmlspecialchars($entry['time'], ENT_QUOTES) ?>"><?= htmlspecialchars($entry['label'], ENT_QUOTES) ?></time>
                    <p><?= htmlspecialchars($entry['message'], ENT_QUOTES) ?></p>
                  </li>
                  <?php endforeach; ?>
                </ol>
              </aside>
            </div>
          </section>

          <section id="documents" class="dashboard-section" data-section>
            <h2>Document vault access</h2>
            <p class="dashboard-section-sub">
              Upload photos, invoices, or forms tied to your assignments. Visibility stays limited to your customers until
              Admin reviews, versions, and tags each record for the master vault.
            </p>
            <div class="document-layout">
              <div class="dashboard-table-wrapper document-table">
                <table class="dashboard-table">
                  <thead>
                    <tr>
                      <th scope="col">Document</th>
                      <th scope="col">Customer</th>
                      <th scope="col">Status</th>
                      <th scope="col">Uploaded</th>
                    </tr>
                  </thead>
                  <tbody data-document-list>
                    <?php foreach ($documentVault as $doc): ?>
                    <?php $docTime = strtotime($doc['uploadedAt'] ?? '') ?: null; ?>
                    <tr>
                      <td>
                        <strong><?= htmlspecialchars($doc['type'], ENT_QUOTES) ?></strong>
                        <span class="text-xs text-muted d-block"><?= htmlspecialchars($doc['filename'], ENT_QUOTES) ?></span>
                      </td>
                      <td><?= htmlspecialchars($doc['customer'], ENT_QUOTES) ?></td>
                      <td>
                        <span class="dashboard-status dashboard-status--<?= htmlspecialchars($doc['tone'] ?? 'progress', ENT_QUOTES) ?>">
                          <?= htmlspecialchars($doc['statusLabel'], ENT_QUOTES) ?>
                        </span>
                      </td>
                      <td>
                        <?php if ($docTime !== null): ?>
                        <time datetime="<?= htmlspecialchars($doc['uploadedAt'], ENT_QUOTES) ?>"><?= htmlspecialchars(date('d M · H:i', $docTime), ENT_QUOTES) ?></time>
                        <?php else: ?>
                        <span>—</span>
                        <?php endif; ?>
                        <span class="text-xs text-muted d-block">by <?= htmlspecialchars($doc['uploadedBy'], ENT_QUOTES) ?></span>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              <aside class="document-panel dashboard-panel">
                <h3>Upload to shared vault</h3>
                <form class="document-form" data-document-form>
                  <label>
                    Customer
                    <select name="customer" required>
                      <option value="" disabled selected>Select customer</option>
                      <?php foreach ($documentUploadCustomers as $customer): ?>
                      <option value="<?= htmlspecialchars($customer, ENT_QUOTES) ?>"><?= htmlspecialchars($customer, ENT_QUOTES) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </label>
                  <label>
                    Document type
                    <input type="text" name="type" placeholder="e.g., Service photos" required />
                  </label>
                  <label>
                    File name
                    <input type="text" name="filename" placeholder="example-file.jpg" required />
                  </label>
                  <label>
                    Notes for Admin
                    <textarea name="note" rows="2" placeholder="Explain what this upload covers."></textarea>
                  </label>
                  <button type="submit" class="btn btn-primary btn-sm">Submit for approval</button>
                  <p class="text-xs text-muted mb-0">Admin verifies, tags, and versions files before wider access.</p>
                </form>
              </aside>
            </div>
          </section>

          <section id="subsidy" class="dashboard-section" data-section>
            <h2>PM Surya Ghar subsidy workflow</h2>
            <p class="dashboard-section-sub">
              Help customers progress through the subsidy stages. Mark Applied or Inspected once documents are complete; Admin
              advances cases to Redeemed after validating every upload.
            </p>
            <div class="subsidy-layout" data-subsidy-board>
              <?php foreach ($subsidyCases as $case): ?>
              <article class="subsidy-card dashboard-panel" data-subsidy-case="<?= htmlspecialchars($case['id'], ENT_QUOTES) ?>">
                <header>
                  <h3><?= htmlspecialchars($case['customer'], ENT_QUOTES) ?></h3>
                  <p class="text-sm text-muted">Case <?= htmlspecialchars($case['id'], ENT_QUOTES) ?> · <?= htmlspecialchars($case['capacity'], ENT_QUOTES) ?></p>
                </header>
                <ul class="subsidy-stages">
                  <?php foreach ($case['stages'] as $stageKey => $stage): ?>
                  <?php $completed = !empty($stage['completed']); ?>
                  <li
                    class="subsidy-stage<?= $completed ? ' is-complete' : '' ?>"
                    data-subsidy-stage="<?= htmlspecialchars($stageKey, ENT_QUOTES) ?>"
                    data-stage-label="<?= htmlspecialchars($stage['label'], ENT_QUOTES) ?>"
                    <?= $completed ? ' data-subsidy-completed="true"' : '' ?>
                  >
                    <div>
                      <strong><?= htmlspecialchars($stage['label'], ENT_QUOTES) ?></strong>
                      <?php if (!empty($stage['completedAt'])): ?>
                      <span class="text-xs text-muted">Completed <?= htmlspecialchars(date('d M', strtotime($stage['completedAt'])), ENT_QUOTES) ?></span>
                      <?php endif; ?>
                    </div>
                    <?php if ($stageKey !== 'redeemed'): ?>
                    <button
                      type="button"
                      class="btn btn-secondary btn-sm"
                      data-subsidy-action
                      data-subsidy-stage="<?= htmlspecialchars($stageKey, ENT_QUOTES) ?>"
                      data-subsidy-case="<?= htmlspecialchars($case['id'], ENT_QUOTES) ?>"
                      data-stage-label="<?= htmlspecialchars($stage['label'], ENT_QUOTES) ?>"
                      <?= $completed ? 'disabled' : '' ?>
                    ><?= $completed ? 'Completed' : 'Mark complete' ?></button>
                    <?php else: ?>
                    <span class="text-xs text-muted">Admin approval required</span>
                    <?php endif; ?>
                  </li>
                  <?php endforeach; ?>
                </ul>
                <?php if (!empty($case['note'])): ?>
                <p class="subsidy-note"><i class="fa-solid fa-clipboard-check" aria-hidden="true"></i> <?= htmlspecialchars($case['note'], ENT_QUOTES) ?></p>
                <?php endif; ?>
              </article>
              <?php endforeach; ?>
            </div>
            <aside class="dashboard-panel subsidy-activity">
              <h3>Workflow updates</h3>
              <ol data-subsidy-activity>
                <?php foreach ($subsidyActivity as $entry): ?>
                <li>
                  <time datetime="<?= htmlspecialchars($entry['time'], ENT_QUOTES) ?>"><?= htmlspecialchars($entry['label'], ENT_QUOTES) ?></time>
                  <p><?= htmlspecialchars($entry['message'], ENT_QUOTES) ?></p>
                </li>
                <?php endforeach; ?>
              </ol>
            </aside>
          </section>

          <section id="warranty" class="dashboard-section" data-section>
            <h2>Warranty &amp; AMC tracker</h2>
            <p class="dashboard-section-sub">
              Monitor service schedules, upload geo-tagged evidence, and highlight issues before they become escalations. Overdue
              visits appear with alerts until Admin marks them resolved.
            </p>
            <div class="warranty-layout">
              <div class="dashboard-table-wrapper warranty-table">
                <table class="dashboard-table">
                  <thead>
                    <tr>
                      <th scope="col">Customer</th>
                      <th scope="col">Asset</th>
                      <th scope="col">Status</th>
                      <th scope="col">Next visit</th>
                      <th scope="col">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($warrantyAssets as $asset): ?>
                    <tr
                      data-warranty-row
                      data-warranty-id="<?= htmlspecialchars($asset['id'], ENT_QUOTES) ?>"
                      data-warranty-status="<?= htmlspecialchars($asset['status'], ENT_QUOTES) ?>"
                      data-warranty-customer="<?= htmlspecialchars($asset['customer'], ENT_QUOTES) ?>"
                      data-warranty-asset="<?= htmlspecialchars($asset['asset'], ENT_QUOTES) ?>"
                    >
                      <td>
                        <strong><?= htmlspecialchars($asset['customer'], ENT_QUOTES) ?></strong>
                        <span class="text-xs text-muted d-block">Last visit <?= htmlspecialchars(date('d M Y', strtotime($asset['lastVisit'])), ENT_QUOTES) ?></span>
                      </td>
                      <td>
                        <strong><?= htmlspecialchars($asset['asset'], ENT_QUOTES) ?></strong>
                        <span class="text-xs text-muted d-block"><?= htmlspecialchars($asset['warranty'], ENT_QUOTES) ?></span>
                      </td>
                      <td>
                        <span class="dashboard-status dashboard-status--<?= htmlspecialchars($asset['tone'] ?? 'progress', ENT_QUOTES) ?>" data-warranty-status-label>
                          <?= htmlspecialchars($asset['statusLabel'], ENT_QUOTES) ?>
                        </span>
                      </td>
                      <td><?= htmlspecialchars($asset['nextVisit'], ENT_QUOTES) ?></td>
                      <td>
                        <button type="button" class="btn btn-secondary btn-sm" data-warranty-log>
                          <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                          Log service update
                        </button>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              <aside class="dashboard-panel warranty-activity">
                <h3>Service visit history</h3>
                <ol data-warranty-activity>
                  <?php foreach ($warrantyActivity as $entry): ?>
                  <li>
                    <time datetime="<?= htmlspecialchars($entry['time'], ENT_QUOTES) ?>"><?= htmlspecialchars($entry['label'], ENT_QUOTES) ?></time>
                    <p><?= htmlspecialchars($entry['message'], ENT_QUOTES) ?></p>
                  </li>
                  <?php endforeach; ?>
                </ol>
              </aside>
            </div>
          </section>

          <section id="communication" class="dashboard-section" data-section>
            <h2>Communication log &amp; follow-ups</h2>
            <p class="dashboard-section-sub">
              Maintain an auditable log of calls, emails, and visits. Entries stay visible to Admin, and the system records key
              ticket or task notes automatically.
            </p>
            <div class="communication-layout">
              <article class="dashboard-panel">
                <h3>Add log entry</h3>
                <form class="communication-form" data-communication-form>
                  <label>
                    Customer / ticket
                    <select name="customer" required>
                      <option value="" disabled selected>Select customer</option>
                      <?php foreach ($documentUploadCustomers as $customer): ?>
                      <option value="<?= htmlspecialchars($customer, ENT_QUOTES) ?>"><?= htmlspecialchars($customer, ENT_QUOTES) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </label>
                  <label>
                    Channel
                    <select name="channel" required>
                      <option value="call">Call</option>
                      <option value="email">Email</option>
                      <option value="visit">Visit</option>
                    </select>
                  </label>
                  <label>
                    Summary
                    <textarea
                      name="summary"
                      rows="3"
                      placeholder="Document the conversation, commitments, or next steps."
                      required
                    ></textarea>
                  </label>
                  <button type="submit" class="btn btn-primary btn-sm">Save communication</button>
                  <p class="text-xs text-muted mb-0">Admins can review these logs anytime.</p>
                </form>
              </article>
              <article class="dashboard-panel communication-history">
                <h3>Recent communication</h3>
                <ul class="communication-log" data-communication-log>
                  <?php foreach ($communicationLogs as $log): ?>
                  <li>
                    <div class="communication-log-meta">
                      <span class="communication-channel communication-channel--<?= htmlspecialchars(strtolower($log['channel']), ENT_QUOTES) ?>">
                        <?= htmlspecialchars($log['channel'], ENT_QUOTES) ?>
                      </span>
                      <time datetime="<?= htmlspecialchars($log['time'], ENT_QUOTES) ?>"><?= htmlspecialchars($log['label'], ENT_QUOTES) ?></time>
                    </div>
                    <p><?= htmlspecialchars($log['summary'], ENT_QUOTES) ?></p>
                  </li>
                  <?php endforeach; ?>
                </ul>
              </article>
            </div>
          </section>

          <section id="ai-assist" class="dashboard-section" data-section>
            <h2>AI assistance (Gemini)</h2>
            <p class="dashboard-section-sub">
              Generate quick summaries, follow-up drafts, or image captions using Admin-approved Gemini models. Requests are
              logged for compliance before content is shared with customers.
            </p>
            <div class="ai-layout">
              <article class="dashboard-panel">
                <h3>Request suggestion</h3>
                <form class="ai-form" data-ai-form>
                  <label>
                    Choose tool
                    <select name="purpose" required>
                      <option value="summary">Service summary</option>
                      <option value="followup">Follow-up message</option>
                      <option value="caption">Image caption</option>
                    </select>
                  </label>
                  <label>
                    Context / highlights
                    <textarea name="context" rows="3" placeholder="Describe the service outcome or request from Admin."></textarea>
                  </label>
                  <button type="submit" class="btn btn-primary btn-sm">Generate with Gemini</button>
                  <p class="text-xs text-muted mb-0">All prompts route through Admin-configured Gemini models (Text · Image · TTS).</p>
                </form>
              </article>
              <article class="dashboard-panel ai-output-panel">
                <h3>AI output</h3>
                <div class="ai-output" data-ai-output aria-live="polite">Select a tool and provide optional context to begin.</div>
              </article>
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
