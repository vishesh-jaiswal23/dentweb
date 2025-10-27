<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bootstrap.php';

require_role('employee');
$user = current_user();
$db = get_db();

$employeeRecord = null;
if (!empty($user['id'])) {
    $employeeRecord = portal_find_user($db, (int) $user['id']);
}
$employeeId = (int) ($employeeRecord['id'] ?? ($user['id'] ?? 0));
$tasks = portal_list_tasks($db, $employeeId);
$complaints = portal_employee_complaints($db, $employeeId);
$documentsRaw = portal_list_documents($db, 'employee', $employeeId);
$notificationsRaw = portal_list_notifications($db, $employeeId, 'employee');

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

$viewDefinitions = [
    'overview' => [
        'label' => 'Overview',
        'icon' => 'fa-solid fa-gauge-high',
    ],
    'complaints' => [
        'label' => 'Complaints',
        'icon' => 'fa-solid fa-ticket',
    ],
    'tasks' => [
        'label' => 'Tasks',
        'icon' => 'fa-solid fa-list-check',
    ],
    'leads' => [
        'label' => 'Leads',
        'icon' => 'fa-solid fa-user-plus',
    ],
    'field-work' => [
        'label' => 'Field work',
        'icon' => 'fa-solid fa-route',
    ],
    'documents' => [
        'label' => 'Documents',
        'icon' => 'fa-solid fa-folder-open',
    ],
    'subsidy' => [
        'label' => 'Subsidy',
        'icon' => 'fa-solid fa-sack-dollar',
    ],
    'warranty' => [
        'label' => 'Warranty',
        'icon' => 'fa-solid fa-shield-halved',
    ],
    'communication' => [
        'label' => 'Communication',
        'icon' => 'fa-solid fa-comments',
    ],
    'ai-assist' => [
        'label' => 'AI assist',
        'icon' => 'fa-solid fa-robot',
    ],
];

$requestedView = strtolower(trim((string) ($_GET['view'] ?? '')));
if ($requestedView === '' || !array_key_exists($requestedView, $viewDefinitions)) {
    $requestedView = 'overview';
}
$currentView = $requestedView;

$viewUrlFor = static function (string $view) use ($pathFor, $viewDefinitions): string {
    if (!array_key_exists($view, $viewDefinitions)) {
        $view = 'overview';
    }

    $base = $pathFor('employee-dashboard.php');

    return $base . '?view=' . rawurlencode($view);
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
$monthKeys = [];
$monthCursor = (clone $currentMonthStart)->modify('-3 months');
for ($i = 0; $i < 4; $i++) {
    $monthKeys[] = $monthCursor->format('Y-m');
    $monthCursor->modify('+1 month');
}
$monthKeyCurrent = $monthKeys[count($monthKeys) - 1] ?? $currentMonthStart->format('Y-m');
$monthKeyPrevious = $monthKeys[count($monthKeys) - 2] ?? $monthKeyCurrent;

$parseDateTime = static function (?string $value) {
    if ($value === null || $value === '') {
        return null;
    }

    try {
        return new DateTime($value, new DateTimeZone('Asia/Kolkata'));
    } catch (Throwable $exception) {
        return null;
    }
};

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
        'items' => [],
    ],
    'in_progress' => [
        'label' => 'In Progress',
        'meta' => 'Work currently underway.',
        'items' => [],
    ],
    'done' => [
        'label' => 'Done',
        'meta' => 'Completed work synced back to Admin.',
        'items' => [],
    ],
];

$taskStatusCounts = ['todo' => 0, 'in_progress' => 0, 'done' => 0];
$taskCompletionBuckets = [];
foreach ($monthKeys as $monthKey) {
    $taskCompletionBuckets[$monthKey] = ['completed' => 0, 'onTime' => 0];
}
$doneTasksTotal = 0;
$onTimeTasksTotal = 0;
foreach ($tasks as $taskRow) {
    $statusKey = $taskRow['status'];
    if (!isset($taskColumns[$statusKey])) {
        continue;
    }
    $taskStatusCounts[$statusKey]++;
    $priority = $taskRow['priority'] ?? 'medium';
    $priorityLabel = match ($priority) {
        'high' => 'High priority',
        'low' => 'Low priority',
        default => 'Medium priority',
    };
    $deadline = $taskRow['dueDate'] !== '' ? 'Due ' . date('d M Y', strtotime($taskRow['dueDate'])) : 'No due date set';
    $linkText = $taskRow['linkedTo'] !== '' ? 'Linked to ' . $taskRow['linkedTo'] : 'No linked record';
    $taskColumns[$statusKey]['items'][] = [
        'id' => $taskRow['id'],
        'title' => $taskRow['title'],
        'priority' => $priority,
        'priorityLabel' => $priorityLabel,
        'deadline' => $deadline,
        'link' => htmlspecialchars($linkText, ENT_QUOTES),
        'action' => $statusKey === 'done'
            ? ['label' => 'Reopen task', 'attr' => 'data-task-undo']
            : ['label' => 'Mark complete', 'attr' => 'data-task-complete'],
    ];

    if ($taskRow['status'] === 'done') {
        $doneTasksTotal++;
        $completedAtRaw = $taskRow['completedAt'] ?: $taskRow['updatedAt'] ?: $taskRow['createdAt'] ?? '';
        $completedAt = $parseDateTime($completedAtRaw);
        $onTime = true;
        if ($taskRow['dueDate'] !== '') {
            $dueDate = DateTime::createFromFormat('Y-m-d', $taskRow['dueDate'], new DateTimeZone('Asia/Kolkata'));
            if ($dueDate instanceof DateTime) {
                $dueDate->setTime(23, 59, 59);
                $onTime = $completedAt instanceof DateTime ? $completedAt <= $dueDate : false;
            }
        }
        if ($onTime) {
            $onTimeTasksTotal++;
        }
        if ($completedAt instanceof DateTime) {
            $monthKey = $completedAt->format('Y-m');
            if (isset($taskCompletionBuckets[$monthKey])) {
                $taskCompletionBuckets[$monthKey]['completed']++;
                if ($onTime) {
                    $taskCompletionBuckets[$monthKey]['onTime']++;
                }
            }
        }
    }
}

$taskActivity = [];
$sortedTasks = $tasks;
usort($sortedTasks, static function (array $a, array $b): int {
    return strcmp($b['updatedAt'] ?? '', $a['updatedAt'] ?? '');
});
foreach (array_slice($sortedTasks, 0, 5) as $recent) {
    $timestamp = $recent['updatedAt'] ?: $recent['createdAt'];
    if (!$timestamp) {
        continue;
    }
    $isoTime = (new DateTime($timestamp))->format(DATE_ATOM);
    $label = (new DateTime($timestamp))->format('d M H:i');
    $taskActivity[] = [
        'time' => $isoTime,
        'label' => $label,
        'message' => sprintf('%s moved to %s', $recent['title'], str_replace('_', ' ', ucfirst($recent['status']))),
    ];
}

$taskReminders = [];
$reminderClock = clone $nowIst;
foreach ($tasks as $taskRow) {
    if ($taskRow['dueDate'] === '') {
        continue;
    }
    $dueDate = DateTime::createFromFormat('Y-m-d', $taskRow['dueDate'], new DateTimeZone('Asia/Kolkata'));
    if (!$dueDate) {
        continue;
    }
    $intervalDays = (int) $reminderClock->diff($dueDate)->format('%r%a');
    if ($intervalDays >= 0 && $intervalDays <= 2) {
        $taskReminders[] = [
            'icon' => 'fa-solid fa-bell',
            'message' => sprintf('%s due on %s', $taskRow['title'], $dueDate->format('d M Y')),
        ];
    }
}

$tickets = [];
$complaintStatusMap = [
    'intake' => ['key' => 'in_progress', 'label' => 'Intake review', 'tone' => 'progress'],
    'triage' => ['key' => 'in_progress', 'label' => 'Admin triage', 'tone' => 'attention'],
    'work' => ['key' => 'in_progress', 'label' => 'In progress', 'tone' => 'progress'],
    'resolution' => ['key' => 'awaiting_response', 'label' => 'Awaiting response', 'tone' => 'attention'],
    'closed' => ['key' => 'resolved', 'label' => 'Resolved', 'tone' => 'resolved'],
];
$ticketClosureByMonth = [];
$resolutionDurationsByMonth = [];
$csatBucketsByMonth = [];
foreach ($monthKeys as $monthKey) {
    $ticketClosureByMonth[$monthKey] = 0;
    $resolutionDurationsByMonth[$monthKey] = [];
    $csatBucketsByMonth[$monthKey] = ['closed' => 0, 'escalated' => 0, 'awaiting' => 0, 'total' => 0];
}
$totalResolutionDurations = [];
$overallCsatBucket = ['closed' => 0, 'escalated' => 0, 'awaiting' => 0, 'total' => 0];
foreach ($complaints as $complaint) {
    $map = $complaintStatusMap[$complaint['status']] ?? $complaintStatusMap['intake'];
    $timeline = [];
    $timelineEntries = $complaint['timeline'] ?? [];
    foreach ($timelineEntries as $entry) {
        $label = match ($entry['type'] ?? 'note') {
            'status' => 'Status change',
            'document' => 'Document',
            'assignment' => 'Assignment',
            default => 'Note',
        };
        $messageParts = [htmlspecialchars($entry['summary'] ?? '', ENT_QUOTES)];
        if (!empty($entry['details'])) {
            $messageParts[] = htmlspecialchars($entry['details'], ENT_QUOTES);
        }
        if (!empty($entry['actor'])) {
            $messageParts[] = '— ' . htmlspecialchars($entry['actor'], ENT_QUOTES);
        }
        $message = implode(' ', array_filter($messageParts));
        $timeline[] = [
            'time' => !empty($entry['time']) ? (new DateTime($entry['time']))->format(DATE_ATOM) : '',
            'label' => $label,
            'message' => $message,
        ];
    }
    $slaStatus = $complaint['slaStatus'] ?? 'unset';
    $slaBadgeLabel = match ($slaStatus) {
        'overdue' => 'Overdue',
        'due_soon' => 'Due soon',
        'on_track' => 'On track',
        default => 'Not set',
    };
    $slaTone = match ($slaStatus) {
        'overdue' => 'attention',
        'due_soon' => 'waiting',
        'on_track' => 'progress',
        default => 'muted',
    };
    $ageLabel = $complaint['ageDays'] !== null ? sprintf('%d day%s open', (int) $complaint['ageDays'], ((int) $complaint['ageDays']) === 1 ? '' : 's') : '—';

    $tickets[] = [
        'id' => $complaint['reference'],
        'title' => $complaint['title'],
        'customer' => $complaint['reference'],
        'status' => $map['key'],
        'statusLabel' => $map['label'],
        'statusTone' => $map['tone'],
        'assignedBy' => 'Admin Control Center',
        'sla' => $complaint['slaDue'] !== '' ? date('d M Y', strtotime($complaint['slaDue'])) : 'Not set',
        'slaBadgeLabel' => $slaBadgeLabel,
        'slaBadgeTone' => $slaTone,
        'age' => $ageLabel,
        'contact' => 'Shared via Admin',
        'noteIcon' => 'fa-solid fa-pen-to-square',
        'noteLabel' => 'Add note',
        'attachments' => [],
        'timeline' => $timeline,
    ];

    $updatedAt = $parseDateTime($complaint['updatedAt'] ?? '') ?: $parseDateTime($complaint['createdAt'] ?? '');
    $monthKey = $updatedAt instanceof DateTime ? $updatedAt->format('Y-m') : null;

    $overallCsatBucket['total']++;
    if ($monthKey !== null && isset($csatBucketsByMonth[$monthKey])) {
        $csatBucketsByMonth[$monthKey]['total']++;
    }

    switch ($complaint['status']) {
        case 'closed':
            $overallCsatBucket['closed']++;
            if ($monthKey !== null && isset($csatBucketsByMonth[$monthKey])) {
                $csatBucketsByMonth[$monthKey]['closed']++;
                $ticketClosureByMonth[$monthKey]++;
            }
            if (!empty($complaint['createdAt']) && $updatedAt instanceof DateTime) {
                $createdAt = $parseDateTime($complaint['createdAt']);
                if ($createdAt instanceof DateTime) {
                    $durationDays = max(0, round(($updatedAt->getTimestamp() - $createdAt->getTimestamp()) / 86400, 1));
                    $totalResolutionDurations[] = $durationDays;
                    if ($monthKey !== null && isset($resolutionDurationsByMonth[$monthKey])) {
                        $resolutionDurationsByMonth[$monthKey][] = $durationDays;
                    }
                }
            }
            break;
        case 'triage':
            $overallCsatBucket['escalated']++;
            if ($monthKey !== null && isset($csatBucketsByMonth[$monthKey])) {
                $csatBucketsByMonth[$monthKey]['escalated']++;
            }
            break;
        case 'resolution':
            $overallCsatBucket['awaiting']++;
            if ($monthKey !== null && isset($csatBucketsByMonth[$monthKey])) {
                $csatBucketsByMonth[$monthKey]['awaiting']++;
            }
            break;
    }
}

$documentVault = [];
foreach ($documentsRaw as $doc) {
    $documentVault[] = [
        'type' => $doc['name'],
        'filename' => $doc['reference'] !== '' ? $doc['reference'] : strtoupper((string) $doc['linkedTo']),
        'customer' => ucfirst((string) ($doc['linkedTo'] ?? 'Shared')),
        'statusLabel' => $doc['visibility'] === 'both' ? 'Shared with Admin' : 'Employee only',
        'tone' => $doc['visibility'] === 'both' ? 'resolved' : 'progress',
        'uploadedAt' => $doc['updatedAt'] ?? '',
        'uploadedBy' => $doc['uploadedBy'] ?? 'Admin',
    ];
}

$documentUploadCustomers = array_values(array_unique(array_map(static function (array $ticket): string {
    return (string) $ticket['customer'];
}, $tickets)));

$notifications = array_map(static function (array $notice): array {
    $category = stripos($notice['title'], 'ticket') !== false ? 'ticket' : 'general';
    return [
        'id' => 'N-' . $notice['id'],
        'tone' => $notice['tone'] ?? 'info',
        'icon' => $notice['icon'] ?? 'fa-solid fa-circle-info',
        'title' => $notice['title'],
        'message' => $notice['message'],
        'time' => $notice['time'] ?? '',
        'link' => $notice['link'] ?? '#',
        'isRead' => !empty($notice['isRead']),
        'category' => $category,
    ];
}, $notificationsRaw);

$unreadNotificationCount = count(array_filter($notifications, static fn (array $item): bool => empty($item['isRead'])));

$communicationLogs = [];
foreach (array_slice($complaints, 0, 5) as $complaint) {
    $time = $complaint['updatedAt'] ?: $complaint['createdAt'];
    if (!$time) {
        continue;
    }
    $communicationLogs[] = [
        'channel' => 'Call',
        'time' => (new DateTime($time))->format(DATE_ATOM),
        'label' => sprintf('Ticket %s', $complaint['reference']),
        'summary' => $complaint['title'],
    ];
}

$performanceMetrics = [];
$resolvedHistory = [];
foreach ($monthKeys as $monthKey) {
    $resolvedHistory[] = (int) ($ticketClosureByMonth[$monthKey] ?? 0);
}
$resolvedThisMonth = $ticketClosureByMonth[$monthKeyCurrent] ?? 0;
$previousResolved = $ticketClosureByMonth[$monthKeyPrevious] ?? 0;
$resolvedObservations = array_sum($resolvedHistory) > 0;
if (!$resolvedObservations) {
    $resolvedTrend = '—';
} else {
    $deltaResolved = $resolvedThisMonth - $previousResolved;
    if ($deltaResolved > 0) {
        $resolvedTrend = sprintf('+%d vs last month', $deltaResolved);
    } elseif ($deltaResolved < 0) {
        $resolvedTrend = sprintf('%d vs last month', $deltaResolved);
    } else {
        $resolvedTrend = 'No change vs last month';
    }
}
$ticketTargetBaseline = max($resolvedThisMonth, $previousResolved, 1);
$ticketTarget = max(5, (int) ceil($ticketTargetBaseline * 1.15));

$resolutionHistory = [];
foreach ($monthKeys as $monthKey) {
    $durations = $resolutionDurationsByMonth[$monthKey] ?? [];
    $resolutionHistory[] = !empty($durations)
        ? round(array_sum($durations) / count($durations), 1)
        : 0.0;
}
$avgResolution = !empty($totalResolutionDurations)
    ? round(array_sum($totalResolutionDurations) / count($totalResolutionDurations), 1)
    : 0.0;
$previousResolution = $resolutionHistory[count($resolutionHistory) - 2] ?? 0.0;
$resolutionObservations = !empty($totalResolutionDurations);
if (!$resolutionObservations) {
    $resolutionTrend = '—';
} else {
    $deltaResolution = round($avgResolution - $previousResolution, 1);
    if ($deltaResolution < 0) {
        $resolutionTrend = sprintf('%.1f days faster vs last month', abs($deltaResolution));
    } elseif ($deltaResolution > 0) {
        $resolutionTrend = sprintf('%.1f days slower vs last month', $deltaResolution);
    } else {
        $resolutionTrend = 'Steady vs last month';
    }
}

$amcCompliance = $doneTasksTotal > 0 ? (int) round(($onTimeTasksTotal / $doneTasksTotal) * 100) : 0;
$amcHistory = [];
foreach ($monthKeys as $monthKey) {
    $completed = $taskCompletionBuckets[$monthKey]['completed'] ?? 0;
    $onTime = $taskCompletionBuckets[$monthKey]['onTime'] ?? 0;
    $amcHistory[] = $completed > 0 ? (int) round(($onTime / $completed) * 100) : 0;
}
$previousAmc = $amcHistory[count($amcHistory) - 2] ?? 0;
$amcObservations = $doneTasksTotal > 0;
if (!$amcObservations) {
    $amcTrend = '—';
} else {
    $deltaAmc = $amcCompliance - $previousAmc;
    if ($deltaAmc > 0) {
        $amcTrend = sprintf('+%d pts vs last month', $deltaAmc);
    } elseif ($deltaAmc < 0) {
        $amcTrend = sprintf('%d pts vs last month', $deltaAmc);
    } else {
        $amcTrend = 'No change vs last month';
    }
}
$amcTarget = 95;

$computeCsatScore = static function (array $bucket): ?float {
    $total = $bucket['total'] ?? 0;
    if ($total <= 0) {
        return null;
    }
    $closed = $bucket['closed'] ?? 0;
    $escalated = $bucket['escalated'] ?? 0;
    $awaiting = $bucket['awaiting'] ?? 0;
    $positiveRatio = $total > 0 ? $closed / $total : 0.0;
    $penalty = (($escalated * 1.5) + ($awaiting * 0.75)) / $total;
    $score = 4.2 + ($positiveRatio * 0.8) - ($penalty * 1.8);
    $score = max(3.0, min(5.0, $score));
    return round($score, 1);
};
$fillHistory = static function (array $values, float $fallback): array {
    $filled = [];
    $carry = $fallback;
    foreach ($values as $value) {
        if ($value === null) {
            $filled[] = $carry;
        } else {
            $filled[] = $value;
            $carry = $value;
        }
    }
    return $filled;
};

$overallCsatValue = $computeCsatScore($overallCsatBucket);
$csatScore = $overallCsatValue ?? 4.5;
$csatHistoryRaw = [];
foreach ($monthKeys as $monthKey) {
    $csatHistoryRaw[] = $computeCsatScore($csatBucketsByMonth[$monthKey] ?? []);
}
$csatHistory = $fillHistory($csatHistoryRaw, $csatScore);
$csatObservations = count(array_filter($csatHistoryRaw, static fn ($value) => $value !== null)) > 0;
$currentCsat = $csatHistory[count($csatHistory) - 1] ?? $csatScore;
$previousCsat = $csatHistory[count($csatHistory) - 2] ?? $currentCsat;
if (!$csatObservations) {
    $csatTrend = '—';
} else {
    $deltaCsat = round($currentCsat - $previousCsat, 1);
    if ($deltaCsat > 0) {
        $csatTrend = sprintf('+%.1f vs last month', $deltaCsat);
    } elseif ($deltaCsat < 0) {
        $csatTrend = sprintf('%.1f vs last month', $deltaCsat);
    } else {
        $csatTrend = 'No change vs last month';
    }
}

$performanceMetrics = [
    [
        'id' => 'tickets-closed',
        'label' => 'Tickets resolved this month',
        'value' => $resolvedThisMonth,
        'precision' => 0,
        'unit' => 'tickets',
        'target' => $ticketTarget,
        'trend' => $resolvedTrend,
        'history' => $resolvedHistory,
        'description' => 'Closed tickets synced instantly to the Admin portal.',
    ],
    [
        'id' => 'resolution-time',
        'label' => 'Avg. resolution time',
        'value' => $avgResolution,
        'precision' => 1,
        'unit' => 'days',
        'target' => 2.0,
        'trend' => $resolutionTrend,
        'history' => $resolutionHistory,
        'description' => 'Customer complaints resolved within SLA timers.',
    ],
    [
        'id' => 'amc-compliance',
        'label' => 'AMC compliance rate',
        'value' => $amcCompliance,
        'precision' => 0,
        'unit' => '%',
        'target' => $amcTarget,
        'trend' => $amcTrend,
        'history' => $amcHistory,
        'description' => 'Preventive visits completed before expiry.',
    ],
    [
        'id' => 'csat-score',
        'label' => 'Customer satisfaction',
        'value' => $csatScore,
        'precision' => 1,
        'unit' => '★',
        'target' => 5,
        'trend' => $csatTrend,
        'history' => $csatHistory,
        'description' => 'Feedback collected from resolved service tickets.',
    ],
];

$leadUpdates = [];

$pendingLeads = [];

$leadRecords = array_map(static function (array $complaint) use ($employeeName): array {
    return [
        'id' => $complaint['reference'],
        'label' => $complaint['title'],
        'description' => sprintf('Priority %s', ucfirst($complaint['priority'] ?? 'medium')),
        'contact' => '—',
        'status' => str_replace('_', ' ', $complaint['status']),
        'nextAction' => 'Update ticket',
        'owner' => $employeeName,
        'type' => 'lead',
    ];
}, $complaints);

$siteVisits = [];

$visitActivity = [];

$subsidyCases = [];

$subsidyActivity = [];

$warrantyAssets = [];

$warrantyActivity = [];

$complianceFlags = [];

$auditTrail = [];
foreach (array_slice($taskActivity, 0, 3) as $activity) {
    $auditTrail[] = [
        'time' => $activity['time'],
        'label' => $activity['label'],
        'detail' => $activity['message'],
    ];
}
if (empty($auditTrail) && !empty($communicationLogs)) {
    foreach (array_slice($communicationLogs, 0, 2) as $log) {
        $auditTrail[] = [
            'time' => $log['time'],
            'label' => $log['label'],
            'detail' => $log['summary'],
        ];
    }
}

$employeeProfile = [
    'email' => trim((string) ($employeeRecord['email'] ?? $user['email'] ?? 'employee@dakshayani.example')),
    'phone' => 'Not provided',
    'photo' => '',
    'lastUpdated' => $nowIst->format(DATE_ATOM),
];

$lastSyncRaw = portal_latest_sync($db, $employeeId);
if ($lastSyncRaw) {
    $lastSyncTime = (new DateTime($lastSyncRaw, new DateTimeZone('Asia/Kolkata')))->format(DATE_ATOM);
} else {
    $lastSyncTime = $nowIst->format(DATE_ATOM);
}
$syncStatus = [
    'label' => 'Realtime sync with Admin portal',
    'lastSync' => $lastSyncTime,
    'latency' => '0.3s',
];

$unreadNotificationCount = count(array_filter($notifications, static fn (array $notice): bool => empty($notice['isRead'])));

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
          <button type="button" class="employee-header-button" data-open-profile>
            <i class="fa-solid fa-id-badge" aria-hidden="true"></i>
            <span>Profile</span>
          </button>
          <button type="button" class="employee-header-button" data-open-notifications>
            <i class="fa-solid fa-bell" aria-hidden="true"></i>
            <span>Notifications</span>
            <span class="employee-header-count" data-notification-count><?= (int) $unreadNotificationCount ?></span>
          </button>
          <a class="employee-header-button" href="<?= htmlspecialchars($viewUrlFor('tasks'), ENT_QUOTES) ?>">
            <i class="fa-solid fa-list-check" aria-hidden="true"></i>
            <span>My Work</span>
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

      <div class="dashboard-body dashboard-body--with-aside">
        <div class="dashboard-main">
          <?php if ($currentView === 'overview'): ?>
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
              <article class="dashboard-card dashboard-card--neutral dashboard-card--analytics">
                <div class="dashboard-card-icon"><i class="fa-solid fa-bolt" aria-hidden="true"></i></div>
                <div class="dashboard-card-body">
                  <div class="dashboard-card-heading">
                    <p class="dashboard-card-title">Performance analytics</p>
                    <p class="dashboard-card-meta">Realtime metrics pulled from the Admin data lake for your scope.</p>
                  </div>
                  <div class="analytics-metric-grid">
                    <?php if (empty($performanceMetrics)): ?>
                    <p class="text-muted mb-0">Performance metrics will appear after your activity is tracked.</p>
                    <?php else: ?>
                    <?php foreach ($performanceMetrics as $metric): ?>
                    <?php
                    $valueNumber = is_numeric($metric['value']) ? (float) $metric['value'] : null;
                    $precision = (int) ($metric['precision'] ?? 0);
                    if ($valueNumber !== null) {
                        $formattedValue = number_format($valueNumber, $precision, '.', '');
                        if ($precision > 0) {
                            $formattedValue = rtrim(rtrim($formattedValue, '0'), '.');
                        }
                    } else {
                        $formattedValue = (string) $metric['value'];
                    }
                    $unit = (string) ($metric['unit'] ?? '');
                    $targetNumber = is_numeric($metric['target'] ?? null) ? (float) $metric['target'] : null;
                    $historyPoints = array_map(
                        static fn ($point) => is_numeric($point) ? (string) $point : null,
                        $metric['history'] ?? []
                    );
                    $historyPoints = array_values(array_filter($historyPoints, static fn ($point) => $point !== null));
                    $historyAttribute = htmlspecialchars(implode(',', $historyPoints), ENT_QUOTES);
                    ?>
                    <div
                      class="analytics-metric"
                      data-analytics-card
                      data-metric-id="<?= htmlspecialchars($metric['id'], ENT_QUOTES) ?>"
                      data-metric-value="<?= htmlspecialchars((string) ($valueNumber ?? $metric['value']), ENT_QUOTES) ?>"
                      data-metric-target="<?= htmlspecialchars((string) ($targetNumber ?? ''), ENT_QUOTES) ?>"
                      data-metric-unit="<?= htmlspecialchars($unit, ENT_QUOTES) ?>"
                      data-metric-trend="<?= htmlspecialchars($metric['trend'], ENT_QUOTES) ?>"
                      data-metric-history="<?= $historyAttribute ?>"
                    >
                      <div class="analytics-metric__header">
                        <p class="analytics-metric__label"><?= htmlspecialchars($metric['label'], ENT_QUOTES) ?></p>
                        <span class="analytics-metric__value">
                          <?= htmlspecialchars($formattedValue, ENT_QUOTES) ?>
                          <?php if ($unit !== ''): ?><span class="analytics-metric__unit"><?= htmlspecialchars($unit, ENT_QUOTES) ?></span><?php endif; ?>
                        </span>
                      </div>
                      <p class="analytics-metric__trend"><?= htmlspecialchars($metric['trend'], ENT_QUOTES) ?></p>
                      <div
                        class="analytics-metric__progress"
                        data-metric-progress
                        role="progressbar"
                        aria-label="<?= htmlspecialchars($metric['label'] . ' progress', ENT_QUOTES) ?>"
                      ></div>
                      <div class="analytics-metric__sparkline" data-metric-sparkline aria-hidden="true"></div>
                      <p class="analytics-metric__description"><?= htmlspecialchars($metric['description'], ENT_QUOTES) ?></p>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                  </div>
                  <footer class="analytics-actions">
                      <button type="button" class="btn btn-secondary btn-sm" data-download-report data-report-period="">
                        <i class="fa-solid fa-file-arrow-down" aria-hidden="true"></i>
                        Download monthly report
                      </button>
                      <p class="text-xs text-muted mb-0">
                        Admin console aggregates every employee’s analytics for consolidated leadership dashboards.
                      </p>
                    </footer>
                  </div>
                </article>
              </div>
              <div class="dashboard-panel dashboard-panel--muted dashboard-panel--analytics-note">
                <p class="mb-0">
                  Analytics will display once Admin shares performance data for your queue. Check back after your first
                  assignments are completed.
                </p>
              </div>
          </section>

          <?php endif; ?>

          <?php if ($currentView === 'complaints'): ?>
          <section id="complaints" class="dashboard-section" data-section>
            <h2>Complaints &amp; service workflow</h2>
            <p class="dashboard-section-sub">
              Tickets are provisioned by Admin with customer details, SLA timers, and attachments. Update status, add field notes,
              or escalate back to Admin for complex issues—every action updates the timeline automatically.
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
                    <dd><?= htmlspecialchars($ticket['assignedBy'], ENT_QUOTES) ?></dd>
                  </div>
                  <div>
                    <dt>SLA target</dt>
                    <dd><?= htmlspecialchars($ticket['sla'], ENT_QUOTES) ?></dd>
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
                    <dd><?= htmlspecialchars($ticket['contact'], ENT_QUOTES) ?></dd>
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
              <?php endif; ?>
            </div>
          </section>
          <?php endif; ?>

          <?php if ($currentView === 'tasks'): ?>
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
                  <?php if (empty($column['items'])): ?>
                  <p class="empty-state">No tasks in this stage yet.</p>
                  <?php else: ?>
                  <?php foreach ($column['items'] as $item): ?>
                  <article class="task-card" data-task-id="<?= htmlspecialchars($item['id'], ENT_QUOTES) ?>" draggable="true">
                    <div class="task-card-head">
                      <p class="task-card-title"><?= htmlspecialchars($item['title'], ENT_QUOTES) ?></p>
                      <span class="task-label task-label--<?= htmlspecialchars($item['priority'], ENT_QUOTES) ?>"><?= htmlspecialchars($item['priorityLabel'], ENT_QUOTES) ?></span>
                    </div>
                    <p class="task-card-meta"><i class="fa-solid fa-clock" aria-hidden="true"></i> <?= htmlspecialchars($item['deadline'], ENT_QUOTES) ?></p>
                    <p class="task-card-link"><?= $item['link'] ?></p>
                    <?php if (!empty($item['action'])): ?>
                    <button type="button" class="task-card-action" <?= $item['action']['attr'] ?> <?= $employeeStatus !== 'active' ? 'disabled' : '' ?>><?= htmlspecialchars($item['action']['label'], ENT_QUOTES) ?></button>
                    <?php endif; ?>
                  </article>
                  <?php endforeach; ?>
                  <?php endif; ?>
                </div>
              </section>
              <?php endforeach; ?>
            </div>
            <div class="task-footer">
              <div class="task-activity-block">
                <h3>Task activity</h3>
                <ol class="task-activity" data-task-activity>
                  <?php if (empty($taskActivity)): ?>
                  <li class="text-muted">Task updates will appear here after you start logging work.</li>
                  <?php else: ?>
                  <?php foreach ($taskActivity as $entry): ?>
                  <li>
                    <time datetime="<?= htmlspecialchars($entry['time'], ENT_QUOTES) ?>"><?= htmlspecialchars($entry['label'], ENT_QUOTES) ?></time>
                    <p><?= $entry['message'] ?></p>
                  </li>
                  <?php endforeach; ?>
                  <?php endif; ?>
                </ol>
              </div>
              <div class="task-reminder-block">
                <h3>Upcoming reminders</h3>
                <ul class="task-reminders">
                  <?php if (empty($taskReminders)): ?>
                  <li class="text-muted">No reminders scheduled. Admin alerts will appear here when configured.</li>
                  <?php else: ?>
                  <?php foreach ($taskReminders as $reminder): ?>
                  <li><i class="<?= htmlspecialchars($reminder['icon'], ENT_QUOTES) ?>" aria-hidden="true"></i> <?= htmlspecialchars($reminder['message'], ENT_QUOTES) ?></li>
                  <?php endforeach; ?>
                  <?php endif; ?>
                </ul>
              </div>
            </div>
          </section>
          <?php endif; ?>

          <?php if ($currentView === 'leads'): ?>
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
                    <?php if (empty($leadRecords)): ?>
                    <tr>
                      <td colspan="5" class="text-center text-muted">No leads or customers assigned yet. Admin-approved records will populate automatically.</td>
                    </tr>
                    <?php else: ?>
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
                    <?php endif; ?>
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
                        <?php if (empty($leadRecords)): ?>
                        <option value="" disabled>No records available</option>
                        <?php else: ?>
                        <?php foreach ($leadRecords as $record): ?>
                        <option value="<?= htmlspecialchars($record['id'], ENT_QUOTES) ?>"><?= htmlspecialchars($record['label'] . ' · ' . $record['description'], ENT_QUOTES) ?></option>
                        <?php endforeach; ?>
                        <?php endif; ?>
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
                      <?php if (empty($leadUpdates)): ?>
                      <li class="text-muted">Log a follow-up to start building the timeline.</li>
                      <?php else: ?>
                      <?php foreach ($leadUpdates as $update): ?>
                      <li>
                        <time datetime="<?= htmlspecialchars($update['time'], ENT_QUOTES) ?>"><?= htmlspecialchars($update['label'], ENT_QUOTES) ?></time>
                        <p><?= $update['message'] ?></p>
                      </li>
                      <?php endforeach; ?>
                      <?php endif; ?>
                    </ul>
                  </div>
                </article>

                <article class="dashboard-panel dashboard-panel--muted">
                  <h2>Submit new prospect</h2>
                  <form class="lead-intake-form" data-lead-intake data-validate-form data-compliance-source="Lead intake">
                    <label>
                      Prospect name
                      <input type="text" name="prospect" placeholder="e.g., Sunrise Enclave" required />
                    </label>
                    <label>
                      Location
                      <input type="text" name="location" placeholder="City / landmark" required />
                    </label>
                    <label>
                      Pincode
                      <input type="text" name="pincode" placeholder="6-digit service area" required data-validate="pincode" maxlength="6" />
                      <small class="form-field-error" data-validation-message></small>
                    </label>
                    <label>
                      Contact number
                      <input type="tel" name="contact" placeholder="10-digit mobile" required pattern="[0-9]{10}" data-validate="phone" />
                      <small class="form-field-error" data-validation-message></small>
                    </label>
                    <label>
                      Preferred visit date
                      <input type="date" name="visit_date" data-validate="date" />
                      <small class="form-field-error" data-validation-message></small>
                    </label>
                    <button type="submit" class="btn btn-secondary btn-sm">Send for approval</button>
                  </form>
                  <p class="text-xs text-muted mb-0">New entries remain pending until Admin verifies the details.</p>
                  <ul class="pending-leads" data-pending-leads>
                    <?php if (empty($pendingLeads)): ?>
                    <li class="text-muted">Leads submitted for Admin approval will appear here once queued.</li>
                    <?php else: ?>
                    <?php foreach ($pendingLeads as $prospect): ?>
                    <li>
                      <strong><?= htmlspecialchars($prospect['name'], ENT_QUOTES) ?></strong>
                      <span>Pending Admin approval · <?= htmlspecialchars($prospect['source'], ENT_QUOTES) ?></span>
                    </li>
                    <?php endforeach; ?>
                    <?php endif; ?>
                  </ul>
                </article>
              </aside>
            </div>
          </section>
          <?php endif; ?>

          <?php if ($currentView === 'field-work'): ?>
          <section id="field-work" class="dashboard-section" data-section>
            <h2>Installation &amp; field work</h2>
            <p class="dashboard-section-sub">
              Review every scheduled installation or maintenance visit, capture geo-tags when available, and close assignments
              so Admin can review commissioning evidence before locking tickets.
            </p>
            <div class="visit-layout">
              <div class="visit-grid">
                <?php if (empty($siteVisits)): ?>
                <p class="empty-state">No field visits scheduled. Admin-assigned visits will display here immediately.</p>
                <?php else: ?>
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
                <?php endif; ?>
              </div>
              <aside class="visit-activity-panel dashboard-panel">
                <h3>Visit updates</h3>
                <ol class="visit-activity" data-visit-activity>
                  <?php if (empty($visitActivity)): ?>
                  <li class="text-muted">Updates from field visits will appear here once logged.</li>
                  <?php else: ?>
                  <?php foreach ($visitActivity as $entry): ?>
                  <li>
                    <time datetime="<?= htmlspecialchars($entry['time'], ENT_QUOTES) ?>"><?= htmlspecialchars($entry['label'], ENT_QUOTES) ?></time>
                    <p><?= htmlspecialchars($entry['message'], ENT_QUOTES) ?></p>
                  </li>
                  <?php endforeach; ?>
                  <?php endif; ?>
                </ol>
              </aside>
            </div>
          </section>
          <?php endif; ?>

          <?php if ($currentView === 'documents'): ?>
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
                    <?php if (empty($documentVault)): ?>
                    <tr>
                      <td colspan="4" class="text-center text-muted">No documents uploaded yet. Use the form to submit your first file.</td>
                    </tr>
                    <?php else: ?>
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
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
              <aside class="document-panel dashboard-panel">
                <h3>Upload to shared vault</h3>
                <form class="document-form" data-document-form data-validate-form data-compliance-source="Document upload">
                  <label>
                    Customer
                    <select name="customer" required>
                      <option value="" disabled selected>Select customer</option>
                      <?php if (empty($documentUploadCustomers)): ?>
                      <option value="" disabled>No customers available</option>
                      <?php else: ?>
                      <?php foreach ($documentUploadCustomers as $customer): ?>
                      <option value="<?= htmlspecialchars($customer, ENT_QUOTES) ?>"><?= htmlspecialchars($customer, ENT_QUOTES) ?></option>
                      <?php endforeach; ?>
                      <?php endif; ?>
                    </select>
                  </label>
                  <label>
                    Document type
                    <input type="text" name="type" placeholder="e.g., Service photos" required />
                  </label>
                  <label>
                    File name
                    <input type="text" name="filename" placeholder="example-file.jpg" required data-validate="filename" data-allowed-ext="pdf,jpg,jpeg,png,doc,docx" />
                    <small class="form-field-error" data-validation-message></small>
                  </label>
                  <label>
                    File size (MB)
                    <input type="number" name="file_size" step="0.1" min="0" max="50" placeholder="e.g., 3.5" data-validate="filesize" data-max-size="25" />
                    <small class="form-field-error" data-validation-message></small>
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
          <?php endif; ?>

          <?php if ($currentView === 'subsidy'): ?>
          <section id="subsidy" class="dashboard-section" data-section>
            <h2>PM Surya Ghar subsidy workflow</h2>
            <p class="dashboard-section-sub">
              Help customers progress through the subsidy stages. Mark Applied or Inspected once documents are complete; Admin
              advances cases to Redeemed after validating every upload.
            </p>
            <div class="subsidy-layout" data-subsidy-board>
              <?php if (empty($subsidyCases)): ?>
              <p class="empty-state">No subsidy cases are in progress. Submit a case to begin tracking the workflow.</p>
              <?php else: ?>
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
              <?php endif; ?>
            </div>
            <aside class="dashboard-panel subsidy-activity">
              <h3>Workflow updates</h3>
              <ol data-subsidy-activity>
                <?php if (empty($subsidyActivity)): ?>
                <li class="text-muted">Workflow history will build here once you log subsidy updates.</li>
                <?php else: ?>
                <?php foreach ($subsidyActivity as $entry): ?>
                <li>
                  <time datetime="<?= htmlspecialchars($entry['time'], ENT_QUOTES) ?>"><?= htmlspecialchars($entry['label'], ENT_QUOTES) ?></time>
                  <p><?= htmlspecialchars($entry['message'], ENT_QUOTES) ?></p>
                </li>
                <?php endforeach; ?>
                <?php endif; ?>
              </ol>
            </aside>
          </section>
          <?php endif; ?>

          <?php if ($currentView === 'warranty'): ?>
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
                    <?php if (empty($warrantyAssets)): ?>
                    <tr>
                      <td colspan="5" class="text-center text-muted">No warranty or AMC assets assigned yet. Admin-approved assets will appear here.</td>
                    </tr>
                    <?php else: ?>
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
                        <span class="text-xs text-muted d-block">
                          <?php if (!empty($asset['lastVisit'])): ?>
                          Last visit <?= htmlspecialchars(date('d M Y', strtotime($asset['lastVisit'])), ENT_QUOTES) ?>
                          <?php else: ?>
                          Last visit not recorded
                          <?php endif; ?>
                        </span>
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
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
              <aside class="dashboard-panel warranty-activity">
                <h3>Service visit history</h3>
                <ol data-warranty-activity>
                  <?php if (empty($warrantyActivity)): ?>
                  <li class="text-muted">Service history will log here when you add updates.</li>
                  <?php else: ?>
                  <?php foreach ($warrantyActivity as $entry): ?>
                  <li>
                    <time datetime="<?= htmlspecialchars($entry['time'], ENT_QUOTES) ?>"><?= htmlspecialchars($entry['label'], ENT_QUOTES) ?></time>
                    <p><?= htmlspecialchars($entry['message'], ENT_QUOTES) ?></p>
                  </li>
                  <?php endforeach; ?>
                  <?php endif; ?>
                </ol>
              </aside>
            </div>
          </section>
          <?php endif; ?>

          <?php if ($currentView === 'communication'): ?>
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
                      <?php if (empty($documentUploadCustomers)): ?>
                      <option value="" disabled>No customers available</option>
                      <?php else: ?>
                      <?php foreach ($documentUploadCustomers as $customer): ?>
                      <option value="<?= htmlspecialchars($customer, ENT_QUOTES) ?>"><?= htmlspecialchars($customer, ENT_QUOTES) ?></option>
                      <?php endforeach; ?>
                      <?php endif; ?>
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
                  <?php if (empty($communicationLogs)): ?>
                  <li class="text-muted">Add a call, email, or visit log to see it listed here.</li>
                  <?php else: ?>
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
                  <?php endif; ?>
                </ul>
              </article>
            </div>
          </section>
          <?php endif; ?>

          <?php if ($currentView === 'ai-assist'): ?>
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
          <?php endif; ?>
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
            <div class="dashboard-panel-header">
              <h2>Notifications</h2>
              <span class="badge badge-count" data-notification-count-secondary><?= (int) $unreadNotificationCount ?></span>
            </div>
            <ul class="dashboard-notifications" data-notification-summary>
              <?php if (empty($notifications)): ?>
              <li class="text-muted">Notifications from Admin will appear here.</li>
              <?php else: ?>
              <?php foreach ($notifications as $notice): ?>
              <?php
              $noticeTime = strtotime($notice['time'] ?? '') ?: null;
              $isRead = !empty($notice['isRead']);
              ?>
              <li
                class="dashboard-notification dashboard-notification--<?= htmlspecialchars($notice['tone'], ENT_QUOTES) ?><?= $isRead ? ' is-read' : ' is-unread' ?>"
                data-notification-summary-item
                data-notification-id="<?= htmlspecialchars($notice['id'], ENT_QUOTES) ?>"
                data-notification-read="<?= $isRead ? 'true' : 'false' ?>"
              >
                <i class="<?= htmlspecialchars($notice['icon'], ENT_QUOTES) ?>" aria-hidden="true"></i>
                <div>
                  <p><?= htmlspecialchars($notice['title'], ENT_QUOTES) ?></p>
                  <span><?= htmlspecialchars($notice['message'], ENT_QUOTES) ?></span>
                  <?php if ($noticeTime !== null): ?>
                  <time datetime="<?= htmlspecialchars(date('c', $noticeTime), ENT_QUOTES) ?>" class="text-xs text-muted d-block">
                    <?= htmlspecialchars(date('d M · H:i', $noticeTime), ENT_QUOTES) ?>
                  </time>
                  <?php endif; ?>
                </div>
              </li>
              <?php endforeach; ?>
              <?php endif; ?>
            </ul>
          </article>
          <article class="dashboard-panel dashboard-panel--muted dashboard-panel--compliance">
            <div class="dashboard-panel-header">
              <h2>Data integrity &amp; compliance</h2>
              <span class="badge badge-count" data-compliance-count><?= count($complianceFlags) ?></span>
            </div>
            <p class="text-xs text-muted">
              Validation enforced: phone format, date limits, Yes/No selections, pincode accuracy, and file size caps. Violations
              are blocked and flagged for Admin review.
            </p>
            <ul class="compliance-flags" data-compliance-flags>
              <?php if (empty($complianceFlags)): ?>
              <li class="text-muted">No issues flagged. Continue submitting complete, validated records.</li>
              <?php else: ?>
              <?php foreach ($complianceFlags as $flag): ?>
              <li>
                <strong><?= htmlspecialchars($flag['source'], ENT_QUOTES) ?></strong>
                <span><?= htmlspecialchars($flag['message'], ENT_QUOTES) ?></span>
                <?php if (!empty($flag['time'])): ?>
                <time datetime="<?= htmlspecialchars($flag['time'], ENT_QUOTES) ?>">
                  <?= htmlspecialchars(date('d M · H:i', strtotime($flag['time'])), ENT_QUOTES) ?>
                </time>
                <?php endif; ?>
              </li>
              <?php endforeach; ?>
              <?php endif; ?>
            </ul>
          </article>
          <article class="dashboard-panel dashboard-panel--muted dashboard-panel--audit">
            <h2>Audit trail &amp; accountability</h2>
            <p class="text-xs text-muted">
              Every ticket update, upload, and note is timestamped. Admin reviews your complete history on demand.
            </p>
            <ol class="audit-log" data-audit-log>
              <?php if (empty($auditTrail)): ?>
              <li class="text-muted">Your activity log will populate as you update records.</li>
              <?php else: ?>
              <?php foreach ($auditTrail as $entry): ?>
              <?php $auditTime = strtotime($entry['time'] ?? '') ?: null; ?>
              <li>
                <?php if ($auditTime !== null): ?>
                <time datetime="<?= htmlspecialchars(date('c', $auditTime), ENT_QUOTES) ?>">
                  <?= htmlspecialchars(date('d M · H:i', $auditTime), ENT_QUOTES) ?>
                </time>
                <?php endif; ?>
                <p>
                  <strong><?= htmlspecialchars($entry['label'], ENT_QUOTES) ?></strong>
                  <?php if (!empty($entry['detail'])): ?>
                  <span class="text-xs text-muted d-block"><?= htmlspecialchars($entry['detail'], ENT_QUOTES) ?></span>
                  <?php endif; ?>
                </p>
              </li>
              <?php endforeach; ?>
              <?php endif; ?>
            </ol>
            <?php
            $syncLastRaw = trim((string) ($syncStatus['lastSync'] ?? ''));
            $syncTime = $syncLastRaw !== '' ? strtotime($syncLastRaw) : null;
            ?>
            <p
              class="sync-indicator"
              data-sync-indicator
              data-sync-label="<?= htmlspecialchars($syncStatus['label'], ENT_QUOTES) ?>"
              data-last-sync="<?= htmlspecialchars($syncStatus['lastSync'], ENT_QUOTES) ?>"
            >
              <?= htmlspecialchars($syncStatus['label'], ENT_QUOTES) ?> · Last update
              <?= $syncTime ? htmlspecialchars(date('d M · H:i', $syncTime), ENT_QUOTES) : '—' ?> (Latency <?= htmlspecialchars($syncStatus['latency'], ENT_QUOTES) ?>)
            </p>
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

  <script>
    window.DakshayaniEmployee = Object.freeze({
      csrfToken: <?= json_encode($_SESSION['csrf_token'] ?? '') ?>,
      apiBase: <?= json_encode($pathFor('api/employee.php')) ?>,
      currentUser: <?= json_encode(['id' => $employeeId, 'name' => $employeeName, 'status' => $employeeStatus]) ?>,
      sync: <?= json_encode(['lastSync' => $syncStatus['lastSync'] ?? null]) ?>,
    });
  </script>
  <script src="employee-dashboard.js" defer></script>
  <script src="script.js" defer></script>
</body>
</html>
