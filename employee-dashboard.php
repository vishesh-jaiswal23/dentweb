<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bootstrap.php';

require_role('employee');
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

$employeeRecord = null;
if (!empty($user['id'])) {
    $employeeRecord = portal_find_user($db, (int) $user['id']);
}
$employeeId = (int) ($employeeRecord['id'] ?? ($user['id'] ?? 0));
$reminderBannerAlerts = portal_consume_reminder_banners($db, $employeeId);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $token = $_POST['csrf_token'] ?? null;
    if (!verify_csrf_token(is_string($token) ? $token : null)) {
        set_flash('error', 'Your session expired. Please submit again.');
        header('Location: employee-dashboard.php?view=leads');
        exit;
    }

    $redirectViewInput = $_POST['redirect_view'] ?? null;
    $redirectViewProvided = is_string($redirectViewInput) && trim($redirectViewInput) !== '';
    $redirectView = strtolower(trim((string) ($redirectViewInput ?? 'leads')));

    try {
        switch ($action) {
            case 'add_visit':
                $payload = [
                    'lead_id' => (int) ($_POST['lead_id'] ?? 0),
                    'note' => (string) ($_POST['note'] ?? ''),
                    'photo' => $_FILES['photo'] ?? null,
                ];
                employee_add_lead_visit($db, $payload, $employeeId);
                set_flash('success', 'Visit logged successfully.');
                $redirectView = 'leads';
                break;
            case 'advance_stage':
                $leadId = (int) ($_POST['lead_id'] ?? 0);
                $targetStage = (string) ($_POST['target_stage'] ?? '');
                employee_progress_lead($db, $leadId, $targetStage, $employeeId);
                set_flash('success', 'Lead stage updated.');
                $redirectView = 'leads';
                break;
            case 'submit_proposal':
                $payload = [
                    'lead_id' => (int) ($_POST['lead_id'] ?? 0),
                    'summary' => (string) ($_POST['summary'] ?? ''),
                    'estimate' => (string) ($_POST['estimate'] ?? ''),
                    'document' => $_FILES['document'] ?? null,
                ];
                employee_submit_lead_proposal($db, $payload, $employeeId);
                set_flash('success', 'Proposal submitted for admin approval.');
                $redirectView = 'leads';
                break;
            case 'update_installation_stage':
                $installationId = (int) ($_POST['installation_id'] ?? 0);
                $targetStage = (string) ($_POST['target_stage'] ?? '');
                $remarks = trim((string) ($_POST['remarks'] ?? ''));
                $photo = trim((string) ($_POST['photo_label'] ?? ''));
                installation_update_stage($db, $installationId, $targetStage, $employeeId, 'employee', $remarks, $photo);
                $message = strtolower($targetStage) === 'commissioned'
                    ? 'Commissioning request sent to Admin.'
                    : 'Installation update saved.';
                set_flash('success', $message);
                $redirectView = 'installations';
                break;
            case 'toggle_installation_amc':
                $installationId = (int) ($_POST['installation_id'] ?? 0);
                $targetAmc = isset($_POST['target_amc']) && $_POST['target_amc'] === '1';
                installation_toggle_amc($db, $installationId, $targetAmc, $employeeId);
                set_flash('success', $targetAmc ? 'AMC commitment recorded.' : 'AMC commitment removed.');
                $redirectView = 'installations';
                break;
            case 'propose_reminder':
                $module = (string) ($_POST['module'] ?? '');
                $linkedId = (int) ($_POST['linked_id'] ?? 0);
                $moduleChoice = (string) ($_POST['module_choice'] ?? '');
                if (($module === '' || $linkedId <= 0) && $moduleChoice !== '') {
                    $parts = explode(':', $moduleChoice, 2);
                    if (!empty($parts[0])) {
                        $module = (string) $parts[0];
                    }
                    if (!empty($parts[1])) {
                        $linkedId = (int) $parts[1];
                    }
                }
                $payload = [
                    'module' => $module,
                    'linked_id' => $linkedId,
                    'title' => (string) ($_POST['title'] ?? ''),
                    'due_at' => (string) ($_POST['due_at'] ?? ''),
                    'notes' => (string) ($_POST['notes'] ?? ''),
                ];
                employee_propose_reminder($db, $payload, $employeeId);
                set_flash('success', 'Reminder proposed for admin approval.');
                if (!$redirectViewProvided) {
                    $redirectView = 'reminders';
                }
                break;
            case 'request_profile_update':
                $payload = [
                    'full_name' => (string) ($_POST['new_name'] ?? ''),
                    'email' => (string) ($_POST['new_email'] ?? ''),
                    'username' => (string) ($_POST['new_username'] ?? ''),
                    'notes' => (string) ($_POST['request_notes'] ?? ''),
                ];
                employee_submit_request($db, $employeeId, 'profile_edit', $payload);
                set_flash('success', 'Profile update request sent to Admin.');
                $redirectView = 'requests';
                break;
            case 'request_leave':
                $payload = [
                    'start_date' => (string) ($_POST['leave_start'] ?? ''),
                    'end_date' => (string) ($_POST['leave_end'] ?? ''),
                    'reason' => (string) ($_POST['leave_reason'] ?? ''),
                ];
                employee_submit_request($db, $employeeId, 'leave', $payload);
                set_flash('success', 'Leave request submitted for approval.');
                $redirectView = 'requests';
                break;
            case 'request_expense':
                $amountRaw = (string) ($_POST['expense_amount'] ?? '0');
                $payload = [
                    'amount' => (float) $amountRaw,
                    'category' => (string) ($_POST['expense_category'] ?? ''),
                    'description' => (string) ($_POST['expense_description'] ?? ''),
                ];
                employee_submit_request($db, $employeeId, 'expense', $payload);
                set_flash('success', 'Expense submitted for Admin review.');
                $redirectView = 'requests';
                break;
            case 'request_data_correction':
                $payload = [
                    'module' => (string) ($_POST['correction_module'] ?? ''),
                    'record_id' => (string) ($_POST['correction_record'] ?? '0'),
                    'field' => (string) ($_POST['correction_field'] ?? ''),
                    'value' => (string) ($_POST['correction_value'] ?? ''),
                    'details' => (string) ($_POST['correction_details'] ?? ''),
                ];
                employee_submit_request($db, $employeeId, 'data_correction', $payload);
                set_flash('success', 'Data correction routed to Admin.');
                $redirectView = 'requests';
                break;
            case 'withdraw_reminder':
                $reminderId = (int) ($_POST['reminder_id'] ?? 0);
                if ($reminderId <= 0) {
                    throw new RuntimeException('Reminder reference is required.');
                }
                employee_cancel_reminder($db, $reminderId, $employeeId);
                set_flash('success', 'Reminder proposal withdrawn.');
                $redirectView = 'reminders';
                break;
            case 'complete_reminder':
                $reminderId = (int) ($_POST['reminder_id'] ?? 0);
                if ($reminderId <= 0) {
                    throw new RuntimeException('Reminder reference is required.');
                }
                employee_complete_reminder($db, $reminderId, $employeeId);
                set_flash('success', 'Reminder marked as completed.');
                $redirectView = 'reminders';
                break;
            default:
                set_flash('error', 'Unsupported action.');
                break;
        }
    } catch (Throwable $exception) {
        set_flash('error', $exception->getMessage());
        if (!$redirectViewProvided) {
            $redirectView = 'installations';
        }
    }

    header('Location: employee-dashboard.php?view=' . urlencode($redirectView));
    exit;
}

$complaints = portal_employee_complaints($db, $employeeId);

$employeeName = trim((string) ($employeeRecord['full_name'] ?? $user['full_name'] ?? ''));
if ($employeeName === '') {
    $employeeName = 'Employee';
}

$employeeStatus = strtolower((string) ($employeeRecord['status'] ?? $user['status'] ?? 'active'));
if (!in_array($employeeStatus, ['active', 'inactive', 'pending'], true)) {
    $employeeStatus = 'active';
}
$employeeStatusLabel = match ($employeeStatus) {
    'inactive' => 'Inactive',
    'pending' => 'Pending approval',
    default => 'Active',
};

$employeeRole = 'Employee';
$employeeAccess = trim((string) ($employeeRecord['permissions_note'] ?? ''));
if ($employeeAccess === '') {
    $employeeAccess = 'Employee workspace access managed by Admin';
}

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

$bootstrapData = employee_bootstrap_payload($db, $employeeId);
$complaints = $bootstrapData['complaints'] ?? [];
$reminders = $bootstrapData['reminders'] ?? [];
$requestsRaw = $bootstrapData['requests'] ?? [];
$syncSnapshot = $bootstrapData['sync'] ?? null;
$employeeRequests = is_array($requestsRaw) ? $requestsRaw : [];
$supportEmail = resolve_admin_email();

$viewDefinitions = [
    'leads' => [
        'label' => 'My Leads / Visits',
        'icon' => 'fa-solid fa-user-plus',
    ],
    'installations' => [
        'label' => 'My Installations',
        'icon' => 'fa-solid fa-route',
    ],
    'complaints' => [
        'label' => 'My Complaints',
        'icon' => 'fa-solid fa-ticket',
    ],
    'reminders' => [
        'label' => 'My Reminders',
        'icon' => 'fa-solid fa-list-check',
    ],
    'profile' => [
        'label' => 'My Profile',
        'icon' => 'fa-solid fa-id-badge',
    ],
    'requests' => [
        'label' => 'Requests',
        'icon' => 'fa-solid fa-inbox',
    ],
];

$requestedView = strtolower(trim((string) ($_GET['view'] ?? '')));
if ($requestedView === '' || !array_key_exists($requestedView, $viewDefinitions)) {
    $requestedView = 'leads';
}
$currentView = $requestedView;

$viewUrlFor = static function (string $view) use ($pathFor, $viewDefinitions): string {
    if (!array_key_exists($view, $viewDefinitions)) {
        $view = 'leads';
    }

    $base = $pathFor('employee-dashboard.php');

    return $base . '?view=' . rawurlencode($view);
};

$reminderFilterUrlFor = static function (string $scope) use ($viewUrlFor): string {
    $base = $viewUrlFor('reminders');
    if ($scope === 'all') {
        return $base;
    }

    return $base . '&reminder_scope=' . rawurlencode($scope);
};

$dashboardViews = [];
foreach ($viewDefinitions as $viewKey => $viewConfig) {
    $dashboardViews[$viewKey] = $viewConfig + [
        'href' => $viewUrlFor($viewKey),
    ];
}

$currentViewLabel = $dashboardViews[$currentView]['label'] ?? 'Overview';
$pageTitle = sprintf('%s · Employee Workspace | Dakshayani Enterprises', $currentViewLabel);

$performanceMetrics = [];

$nowIst = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
$currentMonthStart = (clone $nowIst)->modify('first day of this month')->setTime(0, 0, 0);
$statusOptions = [
    'in_progress' => 'In Progress',
    'awaiting_response' => 'Resolved (Pending Admin)',
    'escalated' => 'Escalated to Admin',
];

$complaintStatusMap = [
    'intake' => ['key' => 'in_progress', 'label' => 'Intake review', 'tone' => 'progress'],
    'triage' => ['key' => 'escalated', 'label' => 'Admin triage', 'tone' => 'attention'],
    'work' => ['key' => 'in_progress', 'label' => 'In progress', 'tone' => 'progress'],
    'resolved' => ['key' => 'awaiting_response', 'label' => 'Pending admin review', 'tone' => 'attention'],
    'closed' => ['key' => 'resolved', 'label' => 'Closed', 'tone' => 'resolved'],
];

$tickets = [];
foreach ($complaints as $complaint) {
    $map = $complaintStatusMap[$complaint['status']] ?? $complaintStatusMap['intake'];
    $timeline = [];
    if (!empty($complaint['createdAt'])) {
        $createdAt = new DateTime($complaint['createdAt']);
        $timeline[] = [
            'time' => $createdAt->format(DATE_ATOM),
            'label' => 'Created',
            'message' => 'Ticket opened from Admin portal.',
        ];
    }
    if (!empty($complaint['updatedAt']) && $complaint['updatedAt'] !== $complaint['createdAt']) {
        $updatedAt = new DateTime($complaint['updatedAt']);
        $timeline[] = [
            'time' => $updatedAt->format(DATE_ATOM),
            'label' => 'Last update',
            'message' => sprintf('Status updated to %s.', strtolower($map['label'])),
        ];
    }
    foreach ($complaint['notes'] ?? [] as $note) {
        if (!is_array($note)) {
            continue;
        }
        $noteTime = !empty($note['createdAt']) ? new DateTime($note['createdAt']) : null;
        if ($noteTime instanceof DateTime) {
            $timeline[] = [
                'time' => $noteTime->format(DATE_ATOM),
                'label' => 'Note added',
                'message' => sprintf(
                    '<strong>%s:</strong> %s',
                    htmlspecialchars($note['authorName'] ?? 'Team', ENT_QUOTES),
                    nl2br(htmlspecialchars($note['body'] ?? '', ENT_QUOTES))
                ),
            ];
        }
    }
    $attachments = [];
    foreach ($complaint['attachments'] ?? [] as $attachment) {
        if (!is_array($attachment)) {
            continue;
        }
        $visibility = $attachment['visibility'] ?? 'both';
        if ($visibility === 'admin') {
            continue;
        }
        $uploadedAt = !empty($attachment['uploadedAt']) ? new DateTime($attachment['uploadedAt']) : null;
        if ($uploadedAt instanceof DateTime) {
            $timeline[] = [
                'time' => $uploadedAt->format(DATE_ATOM),
                'label' => 'Attachment uploaded',
                'message' => sprintf(
                    '%s uploaded <strong>%s</strong>.',
                    htmlspecialchars($attachment['uploadedBy'] ?? 'Team', ENT_QUOTES),
                    htmlspecialchars($attachment['label'] ?? ($attachment['filename'] ?? 'Attachment'), ENT_QUOTES)
                ),
            ];
        }
        $token = $attachment['downloadToken'] ?? '';
        $downloadUrl = $token !== ''
            ? $pathFor('download.php') . '?complaint=' . rawurlencode($complaint['reference']) . '&token=' . rawurlencode($token)
            : '';
        $attachments[] = [
            'label' => $attachment['label'] ?? ($attachment['filename'] ?? 'Attachment'),
            'filename' => $attachment['filename'] ?? '',
            'url' => $downloadUrl,
        ];
    }
    usort($timeline, static function (array $a, array $b): int {
        return strcmp($b['time'], $a['time']);
    });

    $customerName = $complaint['customerName'] !== '' ? $complaint['customerName'] : $complaint['reference'];
    $contactDisplay = $complaint['customerContact'] !== '' ? $complaint['customerContact'] : 'Shared via Admin';

    $tickets[] = [
        'id' => $complaint['reference'],
        'complaintId' => (int) ($complaint['id'] ?? 0),
        'reference' => $complaint['reference'],
        'title' => $complaint['title'],
        'customer' => $customerName,
        'status' => $map['key'],
        'statusLabel' => $map['label'],
        'statusTone' => $map['tone'],
        'assignedBy' => 'Admin Control Center',
        'sla' => $complaint['slaDue'] !== '' ? date('d M Y', strtotime($complaint['slaDue'])) : 'Not set',
        'slaBadgeLabel' => $complaint['slaLabel'] ?? 'SLA not set',
        'slaBadgeTone' => $complaint['slaStatus'] ?? 'muted',
        'age' => $complaint['ageDays'] !== null ? sprintf('%d day%s old', $complaint['ageDays'], $complaint['ageDays'] === 1 ? '' : 's') : '—',
        'contact' => $contactDisplay,
        'noteIcon' => 'fa-solid fa-pen-to-square',
        'noteLabel' => 'Add note',
        'attachments' => $attachments,
        'timeline' => $timeline,
    ];
}


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
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES) ?></title>
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
<body data-dashboard-theme="light" data-current-view="<?= htmlspecialchars($currentView, ENT_QUOTES) ?>" data-user-status="<?= htmlspecialchars($employeeStatus, ENT_QUOTES) ?>">
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
          <i class="fa-solid fa-user-tie" aria-hidden="true"></i>
          <div>
            <small>Signed in as</small>
            <strong><?= htmlspecialchars($employeeName, ENT_QUOTES) ?> · <?= htmlspecialchars($employeeRole, ENT_QUOTES) ?></strong>
            <p class="text-xs text-muted mb-0">Access: <?= htmlspecialchars($employeeAccess, ENT_QUOTES) ?></p>
            <span class="badge badge-soft">Status: <?= htmlspecialchars($employeeStatusLabel, ENT_QUOTES) ?></span>
            <?php if ($employeeStatus !== 'active'): ?>
            <p class="text-xs text-warning mb-0">Workspace actions are read-only until Admin reactivates your account.</p>
            <?php endif; ?>
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
          <a class="employee-header-button" href="<?= htmlspecialchars($viewUrlFor('profile'), ENT_QUOTES) ?>">
            <i class="fa-solid fa-id-badge" aria-hidden="true"></i>
            <span>Profile</span>
          </a>
          <a class="employee-header-button" href="<?= htmlspecialchars($viewUrlFor('reminders'), ENT_QUOTES) ?>">
            <i class="fa-solid fa-list-check" aria-hidden="true"></i>
            <span>My Reminders</span>
          </a>
        </div>
        <nav class="dashboard-quick-nav" aria-label="Employee navigation">
          <?php foreach ($dashboardViews as $viewKey => $viewConfig): ?>
          <a
            href="<?= htmlspecialchars($viewConfig['href'], ENT_QUOTES) ?>"
            class="dashboard-quick-nav__link<?= $currentView === $viewKey ? ' is-active' : '' ?>"
            data-quick-link="<?= htmlspecialchars($viewKey, ENT_QUOTES) ?>"
          >
            <i class="<?= htmlspecialchars($viewConfig['icon'], ENT_QUOTES) ?>" aria-hidden="true"></i>
            <span><?= htmlspecialchars($viewConfig['label'], ENT_QUOTES) ?></span>
          </a>
          <?php endforeach; ?>
        </nav>
      </header>

      <div class="dashboard-body">
        <div class="dashboard-main">
          
<?php if ($currentView === 'leads'): ?>
          <section id="leads" class="dashboard-section" data-section>
            <h2>Leads &amp; site visits</h2>
            <p class="dashboard-section-sub">
              Progress assigned leads from first meeting through quotation. Capture visit evidence and submit proposals for admin approval.
            </p>
            <?php if ($employeeLeadsCount > 0): ?>
            <p class="lead-count">Handling <?= $employeeLeadsCount ?> active lead<?= $employeeLeadsCount === 1 ? '' : 's' ?>.</p>
            <?php endif; ?>
            <?php if (empty($employeeLeads)): ?>
            <p class="empty-state">No leads assigned yet. Admin will share opportunities as soon as they are verified.</p>
            <?php else: ?>
            <div class="lead-board">
              <?php foreach ($employeeLeads as $lead): ?>
              <article class="lead-card" data-lead-id="<?= (int) $lead['id'] ?>">
                <header class="lead-card__header">
                  <div>
                    <h3><?= htmlspecialchars($lead['name'], ENT_QUOTES) ?></h3>
                    <p class="lead-card__stage">
                      <span class="lead-stage lead-stage--<?= htmlspecialchars($lead['status'], ENT_QUOTES) ?>"><?= htmlspecialchars($lead['statusLabel'], ENT_QUOTES) ?></span>
                      <?php if ($lead['siteLocation']): ?>
                      · <?= htmlspecialchars($lead['siteLocation'], ENT_QUOTES) ?>
                      <?php endif; ?>
                    </p>
                  </div>
                  <ul class="lead-card__contact">
                    <?php if ($lead['phone']): ?>
                    <li><i class="fa-solid fa-phone" aria-hidden="true"></i> <?= htmlspecialchars($lead['phone'], ENT_QUOTES) ?></li>
                    <?php endif; ?>
                    <?php if ($lead['email']): ?>
                    <li><i class="fa-solid fa-envelope" aria-hidden="true"></i> <?= htmlspecialchars($lead['email'], ENT_QUOTES) ?></li>
                    <?php endif; ?>
                  </ul>
                </header>

                <?php if ($lead['siteDetails']): ?>
                <p class="lead-card__notes"><?= nl2br(htmlspecialchars($lead['siteDetails'], ENT_QUOTES)) ?></p>
                <?php endif; ?>

                <div class="lead-card__timeline">
                  <h4>Visit history</h4>
                  <?php if (empty($lead['visits'])): ?>
                  <p class="lead-card__empty">No visits logged yet. Add your first visit below.</p>
                  <?php else: ?>
                  <ul>
                    <?php foreach (array_slice($lead['visits'], 0, 4) as $visit): ?>
                    <li>
                      <time datetime="<?= htmlspecialchars($visit['createdAt'], ENT_QUOTES) ?>"><?= employee_format_datetime($visit['createdAt']) ?></time>
                      <p><?= nl2br(htmlspecialchars($visit['note'], ENT_QUOTES)) ?></p>
                      <?php if (!empty($visit['photoDataUrl'])): ?>
                      <a class="lead-card__link" href="<?= htmlspecialchars($visit['photoDataUrl'], ENT_QUOTES) ?>" target="_blank" rel="noopener">View photo</a>
                      <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                  </ul>
                  <?php endif; ?>
                </div>

                <form method="post" class="lead-card__form" enctype="multipart/form-data">
                  <input type="hidden" name="action" value="add_visit" />
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($portalCsrfToken, ENT_QUOTES) ?>" />
                  <input type="hidden" name="lead_id" value="<?= (int) $lead['id'] ?>" />
                  <label>
                    Visit notes
                    <textarea name="note" rows="3" required placeholder="Summarise the discussion, questions, or next steps."></textarea>
                  </label>
                  <label>
                    Photo evidence (optional)
                    <input type="file" name="photo" accept=".jpg,.jpeg,.png,.gif" />
                  </label>
                  <button type="submit" class="btn btn-secondary btn-sm">Log visit</button>
                </form>

                <div class="lead-card__actions">
                  <?php if ($lead['canAdvance'] && $lead['nextStage']): ?>
                  <form method="post" class="lead-card__advance">
                    <input type="hidden" name="action" value="advance_stage" />
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($portalCsrfToken, ENT_QUOTES) ?>" />
                    <input type="hidden" name="lead_id" value="<?= (int) $lead['id'] ?>" />
                    <input type="hidden" name="target_stage" value="<?= htmlspecialchars($lead['nextStage'], ENT_QUOTES) ?>" />
                    <button type="submit" class="btn btn-primary btn-xs">Mark as <?= htmlspecialchars(lead_status_label($lead['nextStage']), ENT_QUOTES) ?></button>
                  </form>
                  <?php endif; ?>

                  <?php if ($lead['status'] === 'quotation'): ?>
                    <?php if (!empty($lead['pendingProposal'])): ?>
                    <p class="lead-card__status lead-card__status--pending">Proposal submitted · awaiting admin approval.</p>
                    <?php elseif ($lead['canSubmitProposal']): ?>
                    <form method="post" class="lead-card__form" enctype="multipart/form-data">
                      <input type="hidden" name="action" value="submit_proposal" />
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($portalCsrfToken, ENT_QUOTES) ?>" />
                      <input type="hidden" name="lead_id" value="<?= (int) $lead['id'] ?>" />
                      <label>
                        Proposal summary
                        <textarea name="summary" rows="3" required placeholder="Key deliverables, pricing, or timeline shared with the customer."></textarea>
                      </label>
                      <label>
                        Estimate value (₹)
                        <input type="number" name="estimate" min="0" step="0.01" placeholder="Optional" />
                      </label>
                      <label>
                        Attach proposal (optional)
                        <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" />
                      </label>
                      <button type="submit" class="btn btn-success btn-sm">Submit proposal</button>
                    </form>
                    <?php else: ?>
                    <p class="lead-card__status">Latest proposal reviewed by Admin.</p>
                    <?php endif; ?>
                  <?php elseif ($lead['status'] === 'converted'): ?>
                  <p class="lead-card__status lead-card__status--success">Converted · handover in progress.</p>
                  <?php elseif ($lead['status'] === 'lost'): ?>
                  <p class="lead-card__status lead-card__status--muted">Marked lost by Admin.</p>
                  <?php endif; ?>
                </div>

                <details class="reminder-proposal">
                  <summary><i class="fa-solid fa-bell" aria-hidden="true"></i> Propose reminder</summary>
                  <form method="post" class="reminder-proposal__form">
                    <input type="hidden" name="action" value="propose_reminder" />
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($portalCsrfToken, ENT_QUOTES) ?>" />
                    <input type="hidden" name="module" value="lead" />
                    <input type="hidden" name="linked_id" value="<?= (int) $lead['id'] ?>" />
                    <input type="hidden" name="redirect_view" value="leads" />
                    <p class="reminder-proposal__meta">Linked to Lead #<?= (int) $lead['id'] ?></p>
                    <label>
                      Title
                      <input type="text" name="title" maxlength="150" required placeholder="Follow up call" />
                    </label>
                    <label>
                      Due date &amp; time
                      <input type="datetime-local" name="due_at" required />
                    </label>
                    <label>
                      Notes (optional)
                      <textarea name="notes" rows="2" placeholder="Context for the admin reviewer."></textarea>
                    </label>
                    <button type="submit" class="btn btn-ghost btn-xs" <?= $employeeStatus !== 'active' ? 'disabled' : '' ?>>Send proposal</button>
                  </form>
                </details>

                <footer class="lead-card__footer">
                  <span>Last updated <?= employee_format_datetime($lead['updatedAt']) ?></span>
                </footer>
              </article>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </section>
          <?php endif; ?>

          <?php if ($currentView === 'installations'): ?>
          <section id="installations" class="dashboard-section" data-section>
            <h2>My installations</h2>
            <p class="dashboard-section-sub">
              Review every scheduled installation or maintenance visit, capture geo-tags when available, and close assignments so Admin can review commissioning evidence before locking tickets.
            </p>
            <div class="installation-layout">
              <?php if (empty($installations)): ?>
              <p class="empty-state">No installations assigned yet. Admin will share projects as soon as they are scheduled.</p>
              <?php else: ?>
              <div class="installation-grid">
                <?php foreach ($installations as $installation): ?>
                <?php $stageLabel = $installation['requestedStage'] === 'commissioned' ? 'Commissioning Pending' : $installation['stageLabel']; ?>
                <article class="installation-card dashboard-panel">
                  <header class="installation-card__header">
                    <div>
                      <h3><?= htmlspecialchars($installation['project'] ?: $installation['customer'], ENT_QUOTES) ?></h3>
                      <p class="installation-card__customer">Customer: <?= htmlspecialchars($installation['customer'], ENT_QUOTES) ?></p>
                    </div>
                    <span class="installation-card__stage installation-card__stage--<?= htmlspecialchars($installation['stageTone'], ENT_QUOTES) ?>">
                      <?= htmlspecialchars($stageLabel, ENT_QUOTES) ?>
                    </span>
                  </header>
                  <ul class="installation-card__meta">
                    <li><i class="fa-solid fa-calendar-days" aria-hidden="true"></i> Scheduled: <?= htmlspecialchars(employee_format_date($installation['scheduled'] ?? ''), ENT_QUOTES) ?></li>
                    <li><i class="fa-solid fa-flag-checkered" aria-hidden="true"></i> Target handover: <?= htmlspecialchars(employee_format_date($installation['handover'] ?? ''), ENT_QUOTES) ?></li>
                    <li><i class="fa-solid fa-bolt" aria-hidden="true"></i> Capacity: <?= htmlspecialchars($installation['capacity'] ? number_format($installation['capacity'], 1) . ' kW' : '—', ENT_QUOTES) ?></li>
                  </ul>
                  <ol class="installation-card__progress">
                    <?php foreach ($installation['progress'] as $step): ?>
                    <li class="installation-card__progress-item installation-card__progress-item--<?= htmlspecialchars($step['state'], ENT_QUOTES) ?>">
                      <?= htmlspecialchars($step['label'], ENT_QUOTES) ?>
                    </li>
                    <?php endforeach; ?>
                  </ol>
                  <form class="installation-card__amc" method="post">
                    <input type="hidden" name="action" value="toggle_installation_amc" />
                    <input type="hidden" name="installation_id" value="<?= (int) $installation['id'] ?>" />
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($portalCsrfToken, ENT_QUOTES) ?>" />
                    <input type="hidden" name="redirect_view" value="installations" />
                    <input type="hidden" name="target_amc" value="<?= $installation['amcCommitted'] ? '0' : '1' ?>" />
                    <label>
                      <input type="checkbox" <?= $installation['amcCommitted'] ? 'checked' : '' ?> onchange="this.form.submit()" />
                      AMC committed
                    </label>
                  </form>
                  <form class="installation-card__form" method="post">
                    <input type="hidden" name="action" value="update_installation_stage" />
                    <input type="hidden" name="installation_id" value="<?= (int) $installation['id'] ?>" />
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($portalCsrfToken, ENT_QUOTES) ?>" />
                    <input type="hidden" name="redirect_view" value="installations" />
                    <div class="installation-card__form-row">
                      <label>
                        Stage
                        <select name="target_stage">
                          <?php foreach ($installation['stageOptions'] as $option): ?>
                          <option value="<?= htmlspecialchars($option['value'], ENT_QUOTES) ?>" <?= $option['value'] === $installation['stage'] ? 'selected' : '' ?><?= !empty($option['disabled']) ? ' disabled' : '' ?>><?= htmlspecialchars($option['label'], ENT_QUOTES) ?></option>
                          <?php endforeach; ?>
                        </select>
                      </label>
                      <label>
                        Remarks
                        <input type="text" name="remarks" placeholder="Site note" />
                      </label>
                      <label>
                        Photo reference
                        <input type="text" name="photo_label" placeholder="Filename or link" />
                      </label>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Save update</button>
                  </form>
                  <details class="reminder-proposal reminder-proposal--inline">
                    <summary><i class="fa-solid fa-bell" aria-hidden="true"></i> Propose reminder</summary>
                    <form method="post" class="reminder-proposal__form">
                      <input type="hidden" name="action" value="propose_reminder" />
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($portalCsrfToken, ENT_QUOTES) ?>" />
                      <input type="hidden" name="module" value="installation" />
                      <input type="hidden" name="linked_id" value="<?= (int) $installation['id'] ?>" />
                      <input type="hidden" name="redirect_view" value="installations" />
                      <p class="reminder-proposal__meta">
                        Linked to Installation #<?= (int) $installation['id'] ?> <?= ($installation['project'] ?: $installation['customer']) !== '' ? '· ' . htmlspecialchars($installation['project'] ?: $installation['customer'], ENT_QUOTES) : '' ?>
                      </p>
                      <label>
                        Title
                        <input type="text" name="title" maxlength="150" required placeholder="Commissioning follow-up" />
                      </label>
                      <label>
                        Due date &amp; time
                        <input type="datetime-local" name="due_at" required />
                      </label>
                      <label>
                        Notes (optional)
                        <textarea name="notes" rows="2" placeholder="Add site context for admin review."></textarea>
                      </label>
                      <button type="submit" class="btn btn-ghost btn-xs" <?= $employeeStatus !== 'active' ? 'disabled' : '' ?>>Send proposal</button>
                    </form>
                  </details>
                  <?php if (!empty($installation['entries'])): ?>
                  <div class="installation-card__timeline">
                    <h4>Recent notes</h4>
                    <ol>
                      <?php foreach (array_slice(array_reverse($installation['entries']), 0, 4) as $entry): ?>
                      <li>
                        <div>
                          <strong><?= htmlspecialchars($entry['stageLabel'], ENT_QUOTES) ?></strong>
                          <span><?= htmlspecialchars($entry['actorName'], ENT_QUOTES) ?> · <?= htmlspecialchars(employee_format_datetime($entry['timestamp']), ENT_QUOTES) ?></span>
                        </div>
                        <?php if ($entry['remarks'] !== ''): ?>
                        <p><?= htmlspecialchars($entry['remarks'], ENT_QUOTES) ?></p>
                        <?php endif; ?>
                        <?php if ($entry['photo'] !== ''): ?>
                        <p class="installation-card__photo">Photo: <?= htmlspecialchars($entry['photo'], ENT_QUOTES) ?></p>
                        <?php endif; ?>
                        <?php if ($entry['type'] === 'request'): ?>
                        <span class="installation-card__badge">Awaiting admin approval</span>
                        <?php elseif ($entry['type'] === 'amc'): ?>
                        <span class="installation-card__badge">AMC updated</span>
                        <?php endif; ?>
                      </li>
                      <?php endforeach; ?>
                    </ol>
                  </div>
                  <?php endif; ?>
                </article>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
            </div>
          </section>
          <?php endif; ?>

          <?php if ($currentView === 'complaints'): ?>
          <section id="complaints" class="dashboard-section" data-section>
            <h2>My complaints</h2>
            <p class="dashboard-section-sub">
              Tickets are provisioned by Admin with customer details, SLA timers, and attachments. Update status, add field notes, or escalate back to Admin for complex issues—every action updates the timeline automatically.
            </p>
            <div class="ticket-board">
              <?php if (empty($tickets)): ?>
              <p class="empty-state">No service tickets assigned yet. New tickets from Admin will appear here instantly.</p>
              <?php else: ?>
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
                    <dd data-ticket-assigned-by><?= htmlspecialchars($ticket['assignedBy'], ENT_QUOTES) ?></dd>
                  </div>
                  <div>
                    <dt>SLA target</dt>
                    <dd data-ticket-sla><?= htmlspecialchars($ticket['sla'], ENT_QUOTES) ?></dd>
                  </div>
                  <div>
                    <dt>SLA health</dt>
                    <dd>
                      <?php if ($ticket['slaBadgeTone'] === 'muted'): ?>
                      <span><?= htmlspecialchars($ticket['slaBadgeLabel'], ENT_QUOTES) ?></span>
                      <?php else: ?>
                      <span class="dashboard-status dashboard-status--<?= htmlspecialchars($ticket['slaBadgeTone'], ENT_QUOTES) ?>"><?= htmlspecialchars($ticket['slaBadgeLabel'], ENT_QUOTES) ?></span>
                      <?php endif; ?>
                    </dd>
                  </div>
                  <div>
                    <dt>Contact</dt>
                    <dd data-ticket-contact><?= htmlspecialchars($ticket['contact'], ENT_QUOTES) ?></dd>
                  </div>
                  <div>
                    <dt>Age</dt>
                    <dd><?= htmlspecialchars($ticket['age'], ENT_QUOTES) ?></dd>
                  </div>
                </dl>
                <div class="ticket-actions">
                  <label class="ticket-actions__field">
                    <span>Status</span>
                    <select data-ticket-status <?= $employeeStatus !== 'active' ? 'disabled' : '' ?>>
                      <?php foreach ($statusOptions as $value => $label): ?>
                      <option value="<?= htmlspecialchars($value, ENT_QUOTES) ?>"<?= $ticket['status'] === $value ? ' selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </label>
                  <button type="button" class="btn btn-ghost btn-sm" data-ticket-note <?= $employeeStatus !== 'active' ? 'disabled' : '' ?>>
                    <i class="<?= htmlspecialchars($ticket['noteIcon'], ENT_QUOTES) ?>" aria-hidden="true"></i>
                    <?= htmlspecialchars($ticket['noteLabel'], ENT_QUOTES) ?>
                  </button>
                  <button type="button" class="btn btn-secondary btn-sm" data-ticket-escalate <?= $employeeStatus !== 'active' ? 'disabled' : '' ?>>
                    <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                    Return to Admin
                  </button>
                </div>
                <details class="reminder-proposal reminder-proposal--inline">
                  <summary><i class="fa-solid fa-bell" aria-hidden="true"></i> Propose reminder</summary>
                  <form method="post" class="reminder-proposal__form">
                    <input type="hidden" name="action" value="propose_reminder" />
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($portalCsrfToken, ENT_QUOTES) ?>" />
                    <input type="hidden" name="module" value="complaint" />
                    <input type="hidden" name="linked_id" value="<?= (int) ($ticket['complaintId'] ?? 0) ?>" />
                    <input type="hidden" name="redirect_view" value="complaints" />
                    <p class="reminder-proposal__meta">
                      Linked to Complaint <?= htmlspecialchars($ticket['reference'], ENT_QUOTES) ?>
                    </p>
                    <label>
                      Title
                      <input type="text" name="title" maxlength="150" required placeholder="Customer call back" />
                    </label>
                    <label>
                      Due date &amp; time
                      <input type="datetime-local" name="due_at" required />
                    </label>
                    <label>
                      Notes (optional)
                      <textarea name="notes" rows="2" placeholder="Add context for admin approval."></textarea>
                    </label>
                    <button type="submit" class="btn btn-ghost btn-xs" <?= $employeeStatus !== 'active' ? 'disabled' : '' ?>>Send proposal</button>
                  </form>
                </details>
                <?php if (!empty($ticket['attachments'])): ?>
                <div class="ticket-attachments">
                  <h4>Attachments</h4>
                  <ul>
                    <?php foreach ($ticket['attachments'] as $attachment): ?>
                    <?php $iconClass = $attachmentIcon($attachment['filename'] ?? ''); ?>
                    <li>
                      <i class="<?= htmlspecialchars($iconClass, ENT_QUOTES) ?>" aria-hidden="true"></i>
                      <?php if (!empty($attachment['url'])): ?>
                      <a href="<?= htmlspecialchars($attachment['url'], ENT_QUOTES) ?>" class="ticket-attachment-link" target="_blank" rel="noopener">
                        <?= htmlspecialchars($attachment['label'], ENT_QUOTES) ?>
                      </a>
                      <?php else: ?>
                      <span><?= htmlspecialchars($attachment['label'], ENT_QUOTES) ?></span>
                      <?php endif; ?>
                      <?php if (!empty($attachment['filename']) && $attachment['filename'] !== $attachment['label']): ?>
                      <span class="text-xs text-muted d-block"><?= htmlspecialchars($attachment['filename'], ENT_QUOTES) ?></span>
                      <?php endif; ?>
                    </li>
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
              <?php endif; ?>
            </div>
          </section>
          <?php endif; ?>

          <?php if ($currentView === 'reminders'): ?>
          <section id="reminders" class="dashboard-section" data-section>
            <h2>My reminders</h2>
            <p class="dashboard-section-sub">
              Propose follow-up reminders for Admin approval and keep your task board aligned with the latest priorities.
            </p>
            <div class="reminder-action-bar">
              <details class="reminder-proposal reminder-proposal--wide">
                <summary><i class="fa-solid fa-bell" aria-hidden="true"></i> Propose reminder</summary>
                <?php if (empty($reminderLinkOptions)): ?>
                <p class="text-muted">Link a lead, installation, or complaint to unlock reminder proposals.</p>
                <?php else: ?>
                <form method="post" class="reminder-proposal__form">
                  <input type="hidden" name="action" value="propose_reminder" />
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($portalCsrfToken, ENT_QUOTES) ?>" />
                  <input type="hidden" name="redirect_view" value="reminders" />
                  <label>
                    Linked item
                    <select name="module_choice" required>
                      <option value="">Select a record…</option>
                      <?php foreach ($reminderLinkOptions as $option): ?>
                      <option value="<?= htmlspecialchars($option['value'], ENT_QUOTES) ?>"><?= htmlspecialchars($option['label'], ENT_QUOTES) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </label>
                  <label>
                    Title
                    <input type="text" name="title" maxlength="150" required placeholder="Schedule follow-up" />
                  </label>
                  <label>
                    Due date &amp; time
                    <input type="datetime-local" name="due_at" required />
                  </label>
                  <label>
                    Notes (optional)
                    <textarea name="notes" rows="2" placeholder="Share context for the Admin reviewer."></textarea>
                  </label>
                  <button type="submit" class="btn btn-secondary btn-sm" <?= $employeeStatus !== 'active' ? 'disabled' : '' ?>>Send proposal</button>
                </form>
                <?php endif; ?>
              </details>
            </div>
            <div class="reminder-list">
              <h3>Reminder proposals</h3>
              <?php if (empty($reminders)): ?>
              <p class="empty-state">No reminder proposals yet. Submit one from a lead, installation, or complaint to get started.</p>
              <?php else: ?>
              <div class="reminder-table-wrapper">
                <table class="reminder-table">
                  <thead>
                    <tr>
                      <th scope="col">Title</th>
                      <th scope="col">Linked to</th>
                      <th scope="col">Due</th>
                      <th scope="col">Status</th>
                      <th scope="col">Notes</th>
                      <th scope="col" class="text-right">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($reminders as $reminder): ?>
                    <?php
                    $statusTone = match ($reminder['status']) {
                        'proposed' => 'progress',
                        'active' => 'attention',
                        'completed' => 'success',
                        'cancelled' => 'muted',
                        default => 'info',
                    };
                    ?>
                    <tr>
                      <td><?= htmlspecialchars($reminder['title'], ENT_QUOTES) ?></td>
                      <td><?= htmlspecialchars($reminder['linkedLabel'], ENT_QUOTES) ?></td>
                      <td>
                        <?php if ($reminder['dueIso'] !== ''): ?>
                        <time datetime="<?= htmlspecialchars($reminder['dueIso'], ENT_QUOTES) ?>"><?= htmlspecialchars($reminder['dueDisplay'], ENT_QUOTES) ?></time>
                        <?php else: ?>
                        <span>—</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <span class="dashboard-status dashboard-status--<?= htmlspecialchars($statusTone, ENT_QUOTES) ?>">
                          <?= htmlspecialchars($reminder['statusLabel'], ENT_QUOTES) ?>
                        </span>
                      </td>
                      <td>
                        <?php if ($reminder['notes'] !== ''): ?>
                        <p><?= nl2br(htmlspecialchars($reminder['notes'], ENT_QUOTES)) ?></p>
                        <?php endif; ?>
                        <?php if ($reminder['decisionNote'] !== ''): ?>
                        <p class="text-xs text-muted"><?= htmlspecialchars($reminder['decisionNote'], ENT_QUOTES) ?></p>
                        <?php endif; ?>
                      </td>
                      <td class="text-right">
                        <?php if ($reminder['canWithdraw']): ?>
                        <form method="post" class="reminder-withdraw">
                          <input type="hidden" name="action" value="withdraw_reminder" />
                          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($portalCsrfToken, ENT_QUOTES) ?>" />
                          <input type="hidden" name="reminder_id" value="<?= (int) $reminder['id'] ?>" />
                          <input type="hidden" name="redirect_view" value="reminders" />
                          <button type="submit" class="btn btn-ghost btn-xs" <?= $employeeStatus !== 'active' ? 'disabled' : '' ?>>Withdraw</button>
                        </form>
                        <?php else: ?>
                        <span class="text-xs text-muted">—</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              <?php endif; ?>
            </div>
            
          </section>
          <?php endif; ?>

          <?php if ($currentView === 'profile'): ?>
          <section id="profile" class="dashboard-section" data-section>
            <h2>My profile</h2>
            <p class="dashboard-section-sub">
              Your portal access inherits Admin security controls. Review your account details and confirm everything looks accurate.
            </p>
            <div class="dashboard-profile-grid">
              <article class="dashboard-panel">
                <h3>Account details</h3>
                <dl class="profile-summary">
                  <div>
                    <dt>Name</dt>
                    <dd><?= htmlspecialchars($employeeName, ENT_QUOTES) ?></dd>
                  </div>
                  <div>
                    <dt>Email</dt>
                    <dd><?= htmlspecialchars($user['email'] ?? 'Not set', ENT_QUOTES) ?></dd>
                  </div>
                  <div>
                    <dt>Role</dt>
                    <dd><?= htmlspecialchars($employeeRole, ENT_QUOTES) ?></dd>
                  </div>
                  <div>
                    <dt>Status</dt>
                    <dd><?= htmlspecialchars($employeeStatusLabel, ENT_QUOTES) ?></dd>
                  </div>
                  <div>
                    <dt>Access notes</dt>
                    <dd><?= htmlspecialchars($employeeAccess, ENT_QUOTES) ?></dd>
                  </div>
                </dl>
              </article>

              <article class="dashboard-panel dashboard-panel--muted">
                <h3>Security snapshot</h3>
                <ul class="profile-security">
                  <li><i class="fa-solid fa-key" aria-hidden="true"></i> Passwords are hashed and never stored in plain text.</li>
                  <li><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Sessions regenerate on login and expire after inactivity.</li>
                  <li><i class="fa-solid fa-hand" aria-hidden="true"></i> CSRF protection covers every data-changing action.</li>
                  <?php if ($syncSnapshot !== null): ?>
                  <li><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> Last data sync: <?= htmlspecialchars($syncSnapshot, ENT_QUOTES) ?></li>
                  <?php endif; ?>
                </ul>
              </article>

              <article class="dashboard-panel">
                <h3>Need an update?</h3>
                <p class="text-sm">
                  Contact Admin if your contact information changes or you require additional module access. Every change request is audited.
                </p>
                <a class="btn btn-secondary btn-sm" href="mailto:<?= htmlspecialchars($supportEmail, ENT_QUOTES) ?>">Email support</a>
              </article>
            </div>
          </section>
          <?php endif; ?>

          <?php if ($currentView === 'requests'): ?>
          <section id="requests" class="dashboard-section" data-section>
            <h2>Requests</h2>
            <p class="dashboard-section-sub">
              Track the status of any hardware, access, or policy requests you have raised with Admin.
            </p>
            <div class="dashboard-profile-grid">
              <article class="dashboard-panel">
                <h3>Submitted requests</h3>
                <div class="dashboard-table-wrapper">
                  <table class="dashboard-table">
                    <thead>
                      <tr>
                        <th scope="col">Subject</th>
                        <th scope="col">Type</th>
                        <th scope="col">Status</th>
                        <th scope="col">Updated</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (empty($employeeRequests)): ?>
                      <tr>
                        <td colspan="4" class="text-muted">You have not logged any requests yet.</td>
                      </tr>
                      <?php else: ?>
                      <?php foreach ($employeeRequests as $request): ?>
                      <?php
                      $statusRaw = (string) ($request['status'] ?? 'pending');
                      $statusLabel = ucwords(str_replace('_', ' ', $statusRaw));
                      $typeLabel = ucwords(str_replace('_', ' ', (string) ($request['type'] ?? 'general')));
                      $updatedRaw = (string) ($request['updatedAt'] ?? '');
                      $updatedDisplay = '—';
                      if ($updatedRaw !== '') {
                          $updatedTime = strtotime($updatedRaw);
                          if ($updatedTime !== false) {
                              $updatedDisplay = date('d M · H:i', $updatedTime);
                          }
                      }
                      $notes = trim((string) ($request['notes'] ?? ''));
                      ?>
                      <tr>
                        <td<?= $notes !== '' ? ' title="' . htmlspecialchars($notes, ENT_QUOTES) . '"' : '' ?>><?= htmlspecialchars($request['subject'] ?? 'Request', ENT_QUOTES) ?></td>
                        <td><?= htmlspecialchars($typeLabel, ENT_QUOTES) ?></td>
                        <td><span class="dashboard-status dashboard-status--<?= htmlspecialchars(strtolower($statusRaw), ENT_QUOTES) ?>"><?= htmlspecialchars($statusLabel, ENT_QUOTES) ?></span></td>
                        <td><?= htmlspecialchars($updatedDisplay, ENT_QUOTES) ?></td>
                      </tr>
                      <?php endforeach; ?>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </article>

              <article class="dashboard-panel dashboard-panel--muted">
                <h3>Raise a new request</h3>
                <p class="text-sm">
                  Use these forms to route common approvals directly to Admin. Detailed notes help speed up the decision.
                </p>

                <section class="dashboard-request" aria-label="Profile update request">
                  <h4>Profile update</h4>
                  <form method="post" class="dashboard-request__form">
                    <input type="hidden" name="action" value="request_profile_update" />
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>" />
                    <input type="hidden" name="redirect_view" value="requests" />
                    <label>
                      <span class="text-xs text-muted">New name</span>
                      <input type="text" name="new_name" placeholder="Updated full name" />
                    </label>
                    <label>
                      <span class="text-xs text-muted">New email</span>
                      <input type="email" name="new_email" placeholder="name@example.com" />
                    </label>
                    <label>
                      <span class="text-xs text-muted">New username</span>
                      <input type="text" name="new_username" placeholder="portal username" />
                    </label>
                    <label>
                      <span class="text-xs text-muted">Notes to Admin</span>
                      <textarea name="request_notes" rows="2" placeholder="Access or contact updates"></textarea>
                    </label>
                    <button type="submit" class="btn btn-secondary btn-xs" <?= $employeeStatus !== 'active' ? 'disabled' : '' ?>>Submit profile update</button>
                  </form>
                </section>

                <section class="dashboard-request" aria-label="Leave request">
                  <h4>Leave request</h4>
                  <form method="post" class="dashboard-request__form">
                    <input type="hidden" name="action" value="request_leave" />
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>" />
                    <input type="hidden" name="redirect_view" value="requests" />
                    <div class="dashboard-request__grid">
                      <label>
                        <span class="text-xs text-muted">Start date</span>
                        <input type="date" name="leave_start" required />
                      </label>
                      <label>
                        <span class="text-xs text-muted">End date</span>
                        <input type="date" name="leave_end" required />
                      </label>
                    </div>
                    <label>
                      <span class="text-xs text-muted">Reason</span>
                      <textarea name="leave_reason" rows="2" placeholder="Purpose and coverage plan" required></textarea>
                    </label>
                    <button type="submit" class="btn btn-secondary btn-xs" <?= $employeeStatus !== 'active' ? 'disabled' : '' ?>>Submit leave request</button>
                  </form>
                </section>

                <section class="dashboard-request" aria-label="Expense claim">
                  <h4>Expense reimbursement</h4>
                  <form method="post" class="dashboard-request__form">
                    <input type="hidden" name="action" value="request_expense" />
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>" />
                    <input type="hidden" name="redirect_view" value="requests" />
                    <div class="dashboard-request__grid">
                      <label>
                        <span class="text-xs text-muted">Amount (₹)</span>
                        <input type="number" name="expense_amount" step="0.01" min="0" placeholder="0.00" required />
                      </label>
                      <label>
                        <span class="text-xs text-muted">Category</span>
                        <input type="text" name="expense_category" placeholder="Travel, tools, etc." />
                      </label>
                    </div>
                    <label>
                      <span class="text-xs text-muted">Description</span>
                      <textarea name="expense_description" rows="2" placeholder="Include bill numbers or context" required></textarea>
                    </label>
                    <button type="submit" class="btn btn-secondary btn-xs" <?= $employeeStatus !== 'active' ? 'disabled' : '' ?>>Submit expense</button>
                  </form>
                </section>

                <section class="dashboard-request" aria-label="Data correction">
                  <h4>Data correction</h4>
                  <form method="post" class="dashboard-request__form">
                    <input type="hidden" name="action" value="request_data_correction" />
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>" />
                    <input type="hidden" name="redirect_view" value="requests" />
                    <label>
                      <span class="text-xs text-muted">Module</span>
                      <select name="correction_module" required>
                        <option value="">Select module</option>
                        <option value="lead">Lead</option>
                        <option value="installation">Installation</option>
                        <option value="complaint">Complaint</option>
                        <option value="other">Other</option>
                      </select>
                    </label>
                    <label>
                      <span class="text-xs text-muted">Record reference</span>
                      <input type="number" name="correction_record" min="0" placeholder="Numeric identifier" />
                    </label>
                    <label>
                      <span class="text-xs text-muted">Field to update</span>
                      <input type="text" name="correction_field" placeholder="e.g. phone" />
                    </label>
                    <label>
                      <span class="text-xs text-muted">New value</span>
                      <input type="text" name="correction_value" placeholder="Correct value" />
                    </label>
                    <label>
                      <span class="text-xs text-muted">Details</span>
                      <textarea name="correction_details" rows="2" placeholder="Reason and supporting info" required></textarea>
                    </label>
                    <button type="submit" class="btn btn-secondary btn-xs" <?= $employeeStatus !== 'active' ? 'disabled' : '' ?>>Submit correction</button>
                  </form>
                </section>
              </article>
            </div>
          </section>
          <?php endif; ?>
        </div>

        
      </div>
    </div>
  </main>

  <script>
    window.DakshayaniEmployee = Object.freeze({
      csrfToken: <?= json_encode($_SESSION['csrf_token'] ?? '') ?>,
      apiBase: <?= json_encode($pathFor('api/employee.php')) ?>
    });
  </script>
  <script src="employee-dashboard.js" defer></script>
</body>
</html>
