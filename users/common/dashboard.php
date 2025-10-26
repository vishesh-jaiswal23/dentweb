<?php
require_once __DIR__ . '/config.php';

function portal_relative_time($timestamp)
{
    if (!is_int($timestamp)) {
        return 'just now';
    }
    $diff = time() - $timestamp;
    if ($diff < 60) {
        return 'just now';
    }
    $mins = floor($diff / 60);
    if ($mins < 60) {
        return $mins . ' minute' . ($mins === 1 ? '' : 's') . ' ago';
    }
    $hours = floor($mins / 60);
    if ($hours < 24) {
        return $hours . ' hour' . ($hours === 1 ? '' : 's') . ' ago';
    }
    $days = floor($hours / 24);
    if ($days < 7) {
        return $days . ' day' . ($days === 1 ? '' : 's') . ' ago';
    }
    $weeks = floor($days / 7);
    if ($weeks < 4) {
        return $weeks . ' week' . ($weeks === 1 ? '' : 's') . ' ago';
    }
    $months = floor($days / 30);
    if ($months < 12) {
        return $months . ' month' . ($months === 1 ? '' : 's') . ' ago';
    }
    $years = floor($days / 365);
    return $years . ' year' . ($years === 1 ? '' : 's') . ' ago';
}

function portal_dashboard_search_catalog()
{
    return [
        'customers' => [
            [
                'title' => 'Sunrise Hospital',
                'description' => '45 kW rooftop array — Ranchi',
                'badge' => 'Key account',
            ],
            [
                'title' => 'Singh Residence',
                'description' => '8 kW net-metered system — Bokaro',
                'badge' => 'Customer',
            ],
            [
                'title' => 'Kedia Cold Storage',
                'description' => '60 kW ground-mount — Industrial',
                'badge' => 'Prospect',
            ],
        ],
        'tickets' => [
            [
                'title' => 'Inverter reboot needed',
                'description' => 'Installer follow-up • Ticket #4821',
                'badge' => 'Urgent',
            ],
            [
                'title' => 'Meter reading clarification',
                'description' => 'Customer support • Ticket #4790',
                'badge' => 'Open',
            ],
            [
                'title' => 'Warranty documentation upload',
                'description' => 'Employee success • Ticket #4755',
                'badge' => 'Awaiting info',
            ],
        ],
        'documents' => [
            [
                'title' => 'DISCOM approval - Ranchi Urban',
                'description' => 'Signed PDF • 22 Feb 2025',
                'badge' => 'Compliance',
            ],
            [
                'title' => 'Site survey kit checklist',
                'description' => 'Notion document • Updated this week',
                'badge' => 'Internal',
            ],
            [
                'title' => 'PM Surya Ghar subsidy tracker',
                'description' => 'Spreadsheet • Finance ops',
                'badge' => 'Shared',
            ],
        ],
        'leads' => [
            [
                'title' => 'Meera Developers township',
                'description' => '3 MW proposal • Next review tomorrow',
                'badge' => 'High value',
            ],
            [
                'title' => 'Tribal welfare hostels',
                'description' => 'CSR pilot • Proposal drafted',
                'badge' => 'Govt',
            ],
            [
                'title' => 'AgroFresh warehouse',
                'description' => '30 kW hybrid system • Lead score 78%',
                'badge' => 'Hot lead',
            ],
        ],
    ];
}

function portal_dashboard_definitions($name, $email)
{
    $search = portal_dashboard_search_catalog();

    $base = [
        'admin' => [
            'role' => 'admin',
            'role_label' => 'Administrator',
            'headline' => 'Lead the charge, ' . $name,
            'subheadline' => 'Monitor company-wide performance, approvals, and partner access in a single workspace.',
            'modules' => [
                ['id' => 'overview', 'label' => 'Executive overview', 'icon' => 'fa-chart-line', 'target' => 'overview'],
                ['id' => 'team', 'label' => 'Team & approvals', 'icon' => 'fa-users-gear', 'target' => 'team'],
                ['id' => 'operations', 'label' => 'Operations hub', 'icon' => 'fa-solar-panel', 'target' => 'quick-actions'],
                ['id' => 'profile', 'label' => 'Profile & security', 'icon' => 'fa-user-shield', 'target' => 'profile'],
            ],
            'cards' => [
                ['title' => 'Active projects', 'value' => '12', 'meta' => '+3 vs last week', 'icon' => 'fa-solar-panel', 'tone' => 'positive'],
                ['title' => 'Pending approvals', 'value' => '4', 'meta' => 'Needs review today', 'icon' => 'fa-clipboard-check', 'tone' => 'warning'],
                ['title' => 'Collection rate', 'value' => '92%', 'meta' => '₹42L collected in April', 'icon' => 'fa-indian-rupee-sign', 'tone' => 'neutral'],
            ],
            'lists' => [
                [
                    'title' => 'Approvals queue',
                    'icon' => 'fa-inbox',
                    'items' => [
                        ['primary' => 'Net-metering for Singh Residence', 'secondary' => 'Awaiting DISCOM confirmation', 'badge' => 'Due today'],
                        ['primary' => 'Vendor onboarding - ElectraParts', 'secondary' => 'Compliance docs ready for sign-off', 'badge' => '2 docs'],
                        ['primary' => 'CapEx release - Solar Farm Zone 4', 'secondary' => 'Finance recommended release of ₹18L', 'badge' => 'Review'],
                    ],
                ],
                [
                    'title' => 'Team focus',
                    'icon' => 'fa-people-group',
                    'items' => [
                        ['primary' => 'Ops stand-up with installation leads', 'secondary' => 'Tomorrow • 10:00 AM', 'badge' => 'Meeting'],
                        ['primary' => 'HR onboarding kit refresh', 'secondary' => 'Draft shared by Priya', 'badge' => 'Update'],
                    ],
                ],
            ],
            'quick_actions' => [
                ['id' => 'invite-employee', 'label' => 'Invite employee', 'icon' => 'fa-user-plus', 'description' => 'Send an onboarding email to a new hire', 'message' => 'Invite sent to Priya at operations@example.com.', 'tone' => 'success'],
                ['id' => 'approve-budget', 'label' => 'Approve CAPEX', 'icon' => 'fa-sack-dollar', 'description' => 'Release ₹8L for inverter procurement', 'message' => 'Budget approved and finance notified.', 'tone' => 'success', 'confirm' => 'Approve this capital expense now?'],
                ['id' => 'pause-project', 'label' => 'Pause project', 'icon' => 'fa-pause-circle', 'description' => 'Place Zone 3 project on hold', 'message' => 'Action cancelled.', 'tone' => 'error', 'confirm' => 'Pause the Zone 3 project? Teams will be notified.'],
            ],
            'critical_actions' => [
                ['id' => 'reset-portal', 'label' => 'Reset portal cache', 'icon' => 'fa-bolt', 'message' => 'Portal cache cleared and services restarted.', 'confirm' => 'Reset cached portal data now? This may temporarily log users out.', 'tone' => 'warning'],
            ],
            'notifications' => [
                ['icon' => 'fa-briefcase', 'message' => 'Ravi assigned a new site survey to you', 'time' => '2h ago', 'tone' => 'info'],
                ['icon' => 'fa-triangle-exclamation', 'message' => 'Approval pending: DISCOM documentation for Bokaro cluster', 'time' => '4h ago', 'tone' => 'warning'],
                ['icon' => 'fa-circle-check', 'message' => 'Collections for April closed at 96%', 'time' => 'Yesterday', 'tone' => 'success'],
            ],
            'reminders' => [
                ['title' => 'Monthly board review', 'time' => 'Friday • 5:00 PM'],
                ['title' => 'Regulatory compliance snapshot', 'time' => 'Upload before 1 May'],
            ],
            'profile_fields' => [
                ['label' => 'Full name', 'name' => 'name', 'value' => $name],
                ['label' => 'Email', 'name' => 'email', 'value' => $email, 'readonly' => true],
                ['label' => 'Phone', 'name' => 'phone', 'value' => '+91 70702 78178'],
                ['label' => 'Location', 'name' => 'location', 'value' => 'Ranchi, Jharkhand'],
            ],
            'security' => [
                'password_last_changed' => '45 days ago',
                'two_factor' => [
                    'enabled' => true,
                    'methods' => ['Authenticator App', 'Backup codes'],
                    'last_enabled' => 'Enabled 2 weeks ago',
                ],
            ],
            'search' => $search,
        ],
        'employee' => [
            'role' => 'employee',
            'role_label' => 'Employee',
            'headline' => 'Stay on track, ' . $name,
            'subheadline' => 'Review assigned jobs, customer updates, and collaboration spaces.',
            'modules' => [
                ['id' => 'overview', 'label' => 'My day', 'icon' => 'fa-list-check', 'target' => 'overview'],
                ['id' => 'assignments', 'label' => 'Assignments', 'icon' => 'fa-clipboard-list', 'target' => 'assignments'],
                ['id' => 'customers', 'label' => 'Customer updates', 'icon' => 'fa-user-group', 'target' => 'quick-actions'],
                ['id' => 'profile', 'label' => 'Profile & security', 'icon' => 'fa-user-shield', 'target' => 'profile'],
            ],
            'cards' => [
                ['title' => 'Tasks due', 'value' => '5', 'meta' => '2 overdue', 'icon' => 'fa-flag', 'tone' => 'warning'],
                ['title' => 'Site visits', 'value' => '3', 'meta' => 'This week', 'icon' => 'fa-map-location-dot', 'tone' => 'neutral'],
                ['title' => 'Customer CSAT', 'value' => '4.6', 'meta' => 'Average rating', 'icon' => 'fa-star', 'tone' => 'positive'],
            ],
            'lists' => [
                [
                    'title' => 'Next actions',
                    'icon' => 'fa-person-running',
                    'items' => [
                        ['primary' => 'Confirm scaffolding availability', 'secondary' => 'Project: Sunrise Hospital', 'badge' => 'Today'],
                        ['primary' => 'Share progress photos', 'secondary' => 'Project: Singh Residence', 'badge' => 'Upload'],
                        ['primary' => 'Update CRM notes', 'secondary' => 'Lead: AgroFresh warehouse', 'badge' => 'Reminder'],
                    ],
                ],
                [
                    'title' => 'Team rooms',
                    'icon' => 'fa-comments',
                    'items' => [
                        ['primary' => 'Installation playbook', 'secondary' => 'Latest SOP revisions shared by Ajay', 'badge' => 'New'],
                        ['primary' => 'Quality assurance desk', 'secondary' => '2 files updated this morning', 'badge' => 'Files'],
                    ],
                ],
            ],
            'quick_actions' => [
                ['id' => 'log-visit', 'label' => 'Log site visit', 'icon' => 'fa-pen-to-square', 'description' => 'Capture notes and media from the field', 'message' => 'Visit logged with today\'s coordinates.', 'tone' => 'success'],
                ['id' => 'request-support', 'label' => 'Request support', 'icon' => 'fa-headset', 'description' => 'Escalate to technical helpdesk', 'message' => 'Support ticket #4822 created.', 'tone' => 'success'],
                ['id' => 'flag-risk', 'label' => 'Flag risk', 'icon' => 'fa-triangle-exclamation', 'description' => 'Raise quality or safety concerns', 'message' => 'Risk alert noted and shared with supervisors.', 'tone' => 'warning'],
            ],
            'critical_actions' => [
                ['id' => 'complete-shift', 'label' => 'Complete shift handover', 'icon' => 'fa-tasks', 'message' => 'Shift summary submitted to operations.', 'confirm' => 'Submit your shift handover now?', 'tone' => 'success'],
            ],
            'notifications' => [
                ['icon' => 'fa-clipboard-check', 'message' => 'Checklist approved for Singh Residence', 'time' => '1h ago', 'tone' => 'success'],
                ['icon' => 'fa-person-digging', 'message' => 'Installation window advanced to Thursday', 'time' => '3h ago', 'tone' => 'info'],
                ['icon' => 'fa-bolt', 'message' => 'Weather alert for Zone 4 tomorrow', 'time' => '5h ago', 'tone' => 'warning'],
            ],
            'reminders' => [
                ['title' => 'Submit material usage log', 'time' => 'Today • 6:00 PM'],
                ['title' => 'Team huddle', 'time' => 'Daily • 9:00 AM'],
            ],
            'profile_fields' => [
                ['label' => 'Full name', 'name' => 'name', 'value' => $name],
                ['label' => 'Email', 'name' => 'email', 'value' => $email, 'readonly' => true],
                ['label' => 'Phone', 'name' => 'phone', 'value' => '+91 90000 12345'],
                ['label' => 'Department', 'name' => 'department', 'value' => 'Project delivery'],
            ],
            'security' => [
                'password_last_changed' => '30 days ago',
                'two_factor' => [
                    'enabled' => false,
                    'methods' => ['Authenticator App', 'SMS OTP'],
                    'last_enabled' => 'Not enabled',
                ],
            ],
            'search' => $search,
        ],
        'installer' => [
            'role' => 'installer',
            'role_label' => 'Installer',
            'headline' => 'Ready for the next install, ' . $name,
            'subheadline' => 'Review assignments, punch lists, and equipment logistics.',
            'modules' => [
                ['id' => 'overview', 'label' => 'Today\'s plan', 'icon' => 'fa-sun', 'target' => 'overview'],
                ['id' => 'assignments', 'label' => 'Install queue', 'icon' => 'fa-screwdriver-wrench', 'target' => 'assignments'],
                ['id' => 'inventory', 'label' => 'Crew quick actions', 'icon' => 'fa-boxes-stacked', 'target' => 'quick-actions'],
                ['id' => 'profile', 'label' => 'Profile & security', 'icon' => 'fa-user-shield', 'target' => 'profile'],
            ],
            'cards' => [
                ['title' => 'Installs this week', 'value' => '6', 'meta' => '2 completed', 'icon' => 'fa-solar-panel', 'tone' => 'positive'],
                ['title' => 'Punch list items', 'value' => '9', 'meta' => 'Across 4 projects', 'icon' => 'fa-list-ol', 'tone' => 'warning'],
                ['title' => 'Travel distance', 'value' => '186 km', 'meta' => 'Scheduled routes', 'icon' => 'fa-route', 'tone' => 'neutral'],
            ],
            'lists' => [
                [
                    'title' => 'Install queue',
                    'icon' => 'fa-screwdriver-wrench',
                    'items' => [
                        ['primary' => 'Sunrise Hospital', 'secondary' => 'Stringing & inverter setup', 'badge' => 'Today 11:30'],
                        ['primary' => 'Govt. school rooftop', 'secondary' => 'Mounting structure alignment', 'badge' => 'Tomorrow'],
                        ['primary' => 'AgroFresh warehouse', 'secondary' => 'Pre-commissioning checks', 'badge' => 'Fri'],
                    ],
                ],
                [
                    'title' => 'Parts on hold',
                    'icon' => 'fa-triangle-exclamation',
                    'items' => [
                        ['primary' => 'ABB inverter 15kW', 'secondary' => 'Awaiting delivery confirmation', 'badge' => 'Logistics'],
                        ['primary' => 'DC isolators batch #24', 'secondary' => 'Quality check flagged', 'badge' => 'QA'],
                    ],
                ],
            ],
            'quick_actions' => [
                ['id' => 'start-checklist', 'label' => 'Start checklist', 'icon' => 'fa-clipboard-check', 'description' => 'Open safety and QA checklist', 'message' => 'Checklist started for Sunrise Hospital.', 'tone' => 'success'],
                ['id' => 'mark-complete', 'label' => 'Mark stage complete', 'icon' => 'fa-check', 'description' => 'Notify project managers instantly', 'message' => 'Stage completion sent to project managers.', 'tone' => 'success'],
                ['id' => 'report-issue', 'label' => 'Report site issue', 'icon' => 'fa-person-falling-burst', 'description' => 'Escalate hazards with evidence', 'message' => 'Issue escalated with priority status.', 'tone' => 'warning'],
            ],
            'critical_actions' => [
                ['id' => 'request-standby', 'label' => 'Request crew standby', 'icon' => 'fa-people-carry-box', 'message' => 'Standby crew alerted for tomorrow\'s slot.', 'confirm' => 'Alert standby crew for immediate deployment?', 'tone' => 'warning'],
            ],
            'notifications' => [
                ['icon' => 'fa-cloud-sun-rain', 'message' => 'Weather alert: light showers expected at 4 PM', 'time' => '45m ago', 'tone' => 'warning'],
                ['icon' => 'fa-truck', 'message' => 'Logistics: Panels for AgroFresh dispatched', 'time' => '2h ago', 'tone' => 'info'],
                ['icon' => 'fa-comment', 'message' => 'QA feedback added on inverter install', 'time' => '6h ago', 'tone' => 'info'],
            ],
            'reminders' => [
                ['title' => 'Upload install photos', 'time' => 'Everyday • 7:00 PM'],
                ['title' => 'Safety training refresher', 'time' => 'Due next Monday'],
            ],
            'profile_fields' => [
                ['label' => 'Full name', 'name' => 'name', 'value' => $name],
                ['label' => 'Email', 'name' => 'email', 'value' => $email, 'readonly' => true],
                ['label' => 'Primary phone', 'name' => 'phone', 'value' => '+91 98765 43210'],
                ['label' => 'Preferred hub', 'name' => 'hub', 'value' => 'Ranchi central warehouse'],
            ],
            'security' => [
                'password_last_changed' => '60 days ago',
                'two_factor' => [
                    'enabled' => false,
                    'methods' => ['Authenticator App', 'SMS OTP'],
                    'last_enabled' => 'Not enabled',
                ],
            ],
            'search' => $search,
        ],
        'referrer' => [
            'role' => 'referrer',
            'role_label' => 'Channel Partner',
            'headline' => 'Grow the pipeline, ' . $name,
            'subheadline' => 'Track lead status, commissions, and marketing collateral.',
            'modules' => [
                ['id' => 'overview', 'label' => 'Pipeline view', 'icon' => 'fa-diagram-project', 'target' => 'overview'],
                ['id' => 'leads', 'label' => 'Lead tracker', 'icon' => 'fa-user-tag', 'target' => 'leads'],
                ['id' => 'rewards', 'label' => 'Rewards & payouts', 'icon' => 'fa-gift', 'target' => 'quick-actions'],
                ['id' => 'profile', 'label' => 'Profile & security', 'icon' => 'fa-user-shield', 'target' => 'profile'],
            ],
            'cards' => [
                ['title' => 'Qualified leads', 'value' => '14', 'meta' => '3 added this week', 'icon' => 'fa-user-plus', 'tone' => 'positive'],
                ['title' => 'Conversion rate', 'value' => '38%', 'meta' => '+5% vs last month', 'icon' => 'fa-chart-line', 'tone' => 'positive'],
                ['title' => 'Pending payouts', 'value' => '₹1.2L', 'meta' => 'Clearing on 30 Apr', 'icon' => 'fa-wallet', 'tone' => 'neutral'],
            ],
            'lists' => [
                [
                    'title' => 'Hot leads',
                    'icon' => 'fa-fire',
                    'items' => [
                        ['primary' => 'Meera Developers township', 'secondary' => 'Decision call tomorrow 4 PM', 'badge' => 'High'],
                        ['primary' => 'AgroFresh warehouse', 'secondary' => 'Awaiting technical proposal', 'badge' => 'Follow-up'],
                    ],
                ],
                [
                    'title' => 'Marketing assets',
                    'icon' => 'fa-folder-open',
                    'items' => [
                        ['primary' => 'Commercial pitch deck', 'secondary' => 'Updated yesterday', 'badge' => 'Slides'],
                        ['primary' => 'PM Surya Ghar brochure', 'secondary' => 'Hindi + English versions', 'badge' => 'PDF'],
                    ],
                ],
            ],
            'quick_actions' => [
                ['id' => 'add-lead', 'label' => 'Add new lead', 'icon' => 'fa-user-plus', 'description' => 'Capture opportunity details quickly', 'message' => 'Lead captured and routed to sales desk.', 'tone' => 'success'],
                ['id' => 'share-collateral', 'label' => 'Share collateral', 'icon' => 'fa-share-from-square', 'description' => 'Send latest brochures to a prospect', 'message' => 'Collateral shared via email.', 'tone' => 'success'],
                ['id' => 'raise-query', 'label' => 'Raise payout query', 'icon' => 'fa-money-check-dollar', 'description' => 'Connect with finance for commissions', 'message' => 'Finance ticket opened with priority.', 'tone' => 'warning'],
            ],
            'critical_actions' => [
                ['id' => 'update-bank', 'label' => 'Update bank proofs', 'icon' => 'fa-building-columns', 'message' => 'Bank proofs uploaded for verification.', 'confirm' => 'Upload refreshed bank proofs now?', 'tone' => 'success'],
            ],
            'notifications' => [
                ['icon' => 'fa-bell', 'message' => 'Commission statement for March ready', 'time' => '30m ago', 'tone' => 'success'],
                ['icon' => 'fa-users', 'message' => 'Sales assigned follow-up on AgroFresh', 'time' => '2h ago', 'tone' => 'info'],
                ['icon' => 'fa-circle-info', 'message' => 'New marketing video added to library', 'time' => 'Yesterday', 'tone' => 'info'],
            ],
            'reminders' => [
                ['title' => 'Submit monthly forecast', 'time' => 'Due in 2 days'],
                ['title' => 'Community webinar', 'time' => 'Friday • 7:30 PM'],
            ],
            'profile_fields' => [
                ['label' => 'Full name', 'name' => 'name', 'value' => $name],
                ['label' => 'Email', 'name' => 'email', 'value' => $email, 'readonly' => true],
                ['label' => 'Phone', 'name' => 'phone', 'value' => '+91 99887 65432'],
                ['label' => 'Region', 'name' => 'region', 'value' => 'Jharkhand & Bihar'],
            ],
            'security' => [
                'password_last_changed' => '52 days ago',
                'two_factor' => [
                    'enabled' => true,
                    'methods' => ['SMS OTP'],
                    'last_enabled' => 'Enabled 3 months ago',
                ],
            ],
            'search' => $search,
        ],
        'customer' => [
            'role' => 'customer',
            'role_label' => 'Customer',
            'headline' => 'Welcome back, ' . $name,
            'subheadline' => 'Track project progress, documents, and support in one place.',
            'modules' => [
                ['id' => 'overview', 'label' => 'Project summary', 'icon' => 'fa-gauge-high', 'target' => 'overview'],
                ['id' => 'documents', 'label' => 'Project updates', 'icon' => 'fa-file-lines', 'target' => 'documents'],
                ['id' => 'support', 'label' => 'Support & actions', 'icon' => 'fa-life-ring', 'target' => 'quick-actions'],
                ['id' => 'profile', 'label' => 'Profile & security', 'icon' => 'fa-user-shield', 'target' => 'profile'],
            ],
            'cards' => [
                ['title' => 'Project stage', 'value' => 'Installation', 'meta' => 'Expected completion 2 May', 'icon' => 'fa-layer-group', 'tone' => 'neutral'],
                ['title' => 'Energy generated', 'value' => '412 kWh', 'meta' => 'Last 30 days', 'icon' => 'fa-bolt', 'tone' => 'positive'],
                ['title' => 'Support tickets', 'value' => '1 open', 'meta' => 'Average response 2h', 'icon' => 'fa-headset', 'tone' => 'warning'],
            ],
            'lists' => [
                [
                    'title' => 'Upcoming milestones',
                    'icon' => 'fa-road',
                    'items' => [
                        ['primary' => 'Net-metering inspection', 'secondary' => 'DISCOM visit on 27 Apr', 'badge' => 'Scheduled'],
                        ['primary' => 'Performance demo', 'secondary' => 'Engineer walkthrough after go-live', 'badge' => 'Planned'],
                    ],
                ],
                [
                    'title' => 'Recent updates',
                    'icon' => 'fa-bullhorn',
                    'items' => [
                        ['primary' => 'Structure installation completed', 'secondary' => 'Photos shared by field team', 'badge' => 'Gallery'],
                        ['primary' => 'Warranty documents uploaded', 'secondary' => 'Access from Documents module', 'badge' => 'Docs'],
                    ],
                ],
            ],
            'quick_actions' => [
                ['id' => 'book-call', 'label' => 'Book review call', 'icon' => 'fa-calendar-check', 'description' => 'Schedule a walkthrough with your manager', 'message' => 'Your review call request has been sent.', 'tone' => 'success'],
                ['id' => 'download-docs', 'label' => 'Download documents', 'icon' => 'fa-download', 'description' => 'Get latest invoices and approvals', 'message' => 'Download link emailed to you.', 'tone' => 'success'],
                ['id' => 'raise-ticket', 'label' => 'Raise support ticket', 'icon' => 'fa-ticket', 'description' => 'Let us know if you need assistance', 'message' => 'Support ticket #4831 created.', 'tone' => 'warning'],
            ],
            'critical_actions' => [
                ['id' => 'request-pause', 'label' => 'Request project pause', 'icon' => 'fa-stopwatch', 'message' => 'Project pause request submitted to project manager.', 'confirm' => 'Pause project progress? Timelines will be adjusted.', 'tone' => 'warning'],
            ],
            'notifications' => [
                ['icon' => 'fa-circle-check', 'message' => 'Panel delivery confirmed for tomorrow', 'time' => '2h ago', 'tone' => 'success'],
                ['icon' => 'fa-envelope-open-text', 'message' => 'Invoice #INV-204 shared', 'time' => 'Yesterday', 'tone' => 'info'],
                ['icon' => 'fa-person-chalkboard', 'message' => 'Energy coaching session scheduled', 'time' => 'Mon', 'tone' => 'info'],
            ],
            'reminders' => [
                ['title' => 'Upload KYC documents', 'time' => 'Pending • tap to upload'],
                ['title' => 'Review maintenance plan', 'time' => 'Due next week'],
            ],
            'profile_fields' => [
                ['label' => 'Primary contact', 'name' => 'name', 'value' => $name],
                ['label' => 'Email', 'name' => 'email', 'value' => $email, 'readonly' => true],
                ['label' => 'Phone', 'name' => 'phone', 'value' => '+91 91234 56789'],
                ['label' => 'Preferred contact time', 'name' => 'preferred_contact', 'value' => 'Weekdays • 4-6 PM'],
            ],
            'security' => [
                'password_last_changed' => '15 days ago',
                'two_factor' => [
                    'enabled' => true,
                    'methods' => ['Authenticator App'],
                    'last_enabled' => 'Enabled 1 month ago',
                ],
            ],
            'search' => $search,
        ],
    ];

    return $base;
}

function portal_dashboard_config($role, array $user)
{
    $name = $user['name'] ?? ucfirst($role);
    $email = $user['email'] ?? '';
    $definitions = portal_dashboard_definitions($name, $email);
    $config = $definitions[$role] ?? $definitions['employee'];
    $config['user'] = [
        'name' => $name,
        'email' => $email,
        'role' => $role,
        'login_at' => $user['login_at'] ?? time(),
    ];

    $config['secure_links'] = [];
    if ($role === 'employee' && function_exists('portal_protected_modules') && function_exists('portal_user_has_capability')) {
        $links = [];
        foreach (portal_protected_modules() as $module) {
            if (portal_user_has_capability($module['capability'], $user)) {
                $links[] = $module;
            }
        }
        $config['secure_links'] = $links;
    }

    return $config;
}

function portal_render_dashboard(array $config)
{
    $searchJson = htmlspecialchars(json_encode($config['search'] ?? [], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
    $user = $config['user'];
    $loginAgo = portal_relative_time($user['login_at'] ?? time());
    $secureLinks = $config['secure_links'] ?? [];
    ?>
    <section class="dashboard" data-dashboard data-role="<?php echo htmlspecialchars($config['role']); ?>" data-search="<?php echo $searchJson; ?>">
      <div class="container dashboard-shell">
        <header class="dashboard-header">
          <div class="dashboard-heading">
            <span class="badge badge-muted"><?php echo htmlspecialchars($config['role_label']); ?></span>
            <h1><?php echo htmlspecialchars($config['headline']); ?></h1>
            <p class="dashboard-subheading"><?php echo htmlspecialchars($config['subheadline']); ?></p>
            <p class="dashboard-meta"><i class="fa-regular fa-clock"></i> Signed in <?php echo htmlspecialchars($loginAgo); ?></p>
          </div>
          <div class="dashboard-search" data-dashboard-search>
            <label class="dashboard-search-field">
              <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
              <input type="search" placeholder="Search customers, tickets, documents, leads" aria-label="Search portal" data-dashboard-search-input />
            </label>
            <div class="dashboard-search-results" data-dashboard-search-results hidden>
              <p class="dashboard-search-empty">Type at least two characters to search.</p>
            </div>
          </div>
        </header>

        <div class="dashboard-body">
          <nav class="dashboard-nav" aria-label="Role navigation">
            <h2 class="dashboard-nav-title">Navigation</h2>
            <ul>
              <?php foreach ($config['modules'] as $module): ?>
                <?php $target = $module['target'] ?? $module['id']; ?>
                <li>
                  <a href="#<?php echo htmlspecialchars($target); ?>" class="dashboard-nav-link" data-dashboard-nav-link>
                    <i class="fa-solid <?php echo htmlspecialchars($module['icon']); ?>" aria-hidden="true"></i>
                    <span><?php echo htmlspecialchars($module['label']); ?></span>
                  </a>
                </li>
              <?php endforeach; ?>
              <?php if (!empty($secureLinks)): ?>
                <li class="dashboard-nav-heading" aria-hidden="true">Operations apps</li>
                <?php foreach ($secureLinks as $link): ?>
                  <li>
                    <a href="<?php echo htmlspecialchars(portal_url('users/admin/' . $link['href'])); ?>" class="dashboard-nav-link dashboard-nav-link--external">
                      <span class="dashboard-nav-link__meta">
                        <i class="fa-solid <?php echo htmlspecialchars($link['icon']); ?>" aria-hidden="true"></i>
                        <span><?php echo htmlspecialchars($link['label']); ?></span>
                      </span>
                      <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                    </a>
                  </li>
                <?php endforeach; ?>
              <?php endif; ?>
              <li class="dashboard-nav-footer">
                <a href="<?php echo htmlspecialchars(portal_url('logout.php')); ?>" class="dashboard-nav-link dashboard-nav-link--logout">
                  <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
                  <span>Logout</span>
                </a>
              </li>
            </ul>
          </nav>

          <div class="dashboard-main">
            <section id="overview" class="dashboard-section" data-dashboard-section>
              <h2>Key metrics</h2>
              <div class="dashboard-cards">
                <?php foreach ($config['cards'] as $card): ?>
                  <article class="dashboard-card dashboard-card--<?php echo htmlspecialchars($card['tone'] ?? 'neutral'); ?>">
                    <div class="dashboard-card-icon" aria-hidden="true">
                      <i class="fa-solid <?php echo htmlspecialchars($card['icon']); ?>"></i>
                    </div>
                    <div class="dashboard-card-body">
                      <p class="dashboard-card-title"><?php echo htmlspecialchars($card['title']); ?></p>
                      <p class="dashboard-card-value"><?php echo htmlspecialchars($card['value']); ?></p>
                      <p class="dashboard-card-meta"><?php echo htmlspecialchars($card['meta']); ?></p>
                    </div>
                  </article>
                <?php endforeach; ?>
              </div>
            </section>

            <?php if (!empty($config['lists'])): ?>
              <?php $listModule = $config['modules'][1] ?? ['id' => 'workflow', 'label' => 'Workstreams']; ?>
              <?php $listTarget = $listModule['target'] ?? $listModule['id'] ?? 'workflow'; ?>
              <section id="<?php echo htmlspecialchars($listTarget); ?>" class="dashboard-section" data-dashboard-section>
                <div class="dashboard-section-header">
                  <h2><?php echo htmlspecialchars($listModule['label'] ?? 'Workstreams'); ?></h2>
                  <p class="dashboard-section-sub">Stay aligned with your current focus areas.</p>
                </div>
                <div class="dashboard-lists">
                  <?php foreach ($config['lists'] as $list): ?>
                    <article class="dashboard-list">
                      <header>
                        <i class="fa-solid <?php echo htmlspecialchars($list['icon']); ?>" aria-hidden="true"></i>
                        <h3><?php echo htmlspecialchars($list['title']); ?></h3>
                      </header>
                      <ul>
                        <?php foreach ($list['items'] as $item): ?>
                          <li>
                            <p class="primary"><?php echo htmlspecialchars($item['primary']); ?></p>
                            <p class="secondary"><?php echo htmlspecialchars($item['secondary']); ?></p>
                            <span class="badge badge-soft"><?php echo htmlspecialchars($item['badge']); ?></span>
                          </li>
                        <?php endforeach; ?>
                      </ul>
                    </article>
                  <?php endforeach; ?>
                </div>
              </section>
            <?php endif; ?>

            <section id="quick-actions" class="dashboard-section" data-dashboard-section>
              <div class="dashboard-section-header">
                <h2>Quick actions</h2>
                <p class="dashboard-section-sub">Launch high-impact workflows tailored to your role.</p>
              </div>
              <div class="dashboard-actions">
                <?php foreach ($config['quick_actions'] as $action): ?>
                  <button type="button"
                    class="dashboard-action"
                    data-quick-action="<?php echo htmlspecialchars($action['id']); ?>"
                    data-action-message="<?php echo htmlspecialchars($action['message']); ?>"
                    data-action-type="<?php echo htmlspecialchars($action['tone'] ?? 'success'); ?>"
                    <?php if (!empty($action['confirm'])): ?>data-action-confirm="<?php echo htmlspecialchars($action['confirm']); ?>"<?php endif; ?>>
                    <div class="dashboard-action-icon"><i class="fa-solid <?php echo htmlspecialchars($action['icon']); ?>" aria-hidden="true"></i></div>
                    <div class="dashboard-action-body">
                      <span class="label"><?php echo htmlspecialchars($action['label']); ?></span>
                      <span class="description"><?php echo htmlspecialchars($action['description']); ?></span>
                    </div>
                    <i class="fa-solid fa-chevron-right dashboard-action-caret" aria-hidden="true"></i>
                  </button>
                <?php endforeach; ?>
              </div>
              <?php if (!empty($config['critical_actions'])): ?>
                <div class="dashboard-critical">
                  <h3>Critical actions</h3>
                  <div class="dashboard-critical-actions">
                    <?php foreach ($config['critical_actions'] as $critical): ?>
                      <button type="button"
                        class="dashboard-critical-action"
                        data-quick-action="<?php echo htmlspecialchars($critical['id']); ?>"
                        data-action-message="<?php echo htmlspecialchars($critical['message']); ?>"
                        data-action-type="<?php echo htmlspecialchars($critical['tone'] ?? 'warning'); ?>"
                        <?php if (!empty($critical['confirm'])): ?>data-action-confirm="<?php echo htmlspecialchars($critical['confirm']); ?>"<?php endif; ?>>
                        <i class="fa-solid <?php echo htmlspecialchars($critical['icon']); ?>" aria-hidden="true"></i>
                        <span><?php echo htmlspecialchars($critical['label']); ?></span>
                      </button>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endif; ?>
            </section>

            <section id="profile" class="dashboard-section" data-dashboard-section>
              <div class="dashboard-section-header">
                <h2>Profile &amp; security</h2>
                <p class="dashboard-section-sub">Keep your contact preferences and account controls up to date.</p>
              </div>
              <div class="dashboard-profile-grid">
                <form class="dashboard-form" data-profile-form data-success-message="Profile updated successfully.">
                  <h3>Contact information</h3>
                  <div class="dashboard-form-grid">
                    <?php foreach ($config['profile_fields'] as $field): ?>
                      <label>
                        <span><?php echo htmlspecialchars($field['label']); ?></span>
                        <input type="text" name="<?php echo htmlspecialchars($field['name']); ?>" value="<?php echo htmlspecialchars($field['value']); ?>" <?php echo !empty($field['readonly']) ? 'readonly' : ''; ?> />
                      </label>
                    <?php endforeach; ?>
                  </div>
                  <button type="submit" class="btn btn-primary">Save profile</button>
                </form>

                <form class="dashboard-form" data-profile-form data-success-message="Password updated.">
                  <h3>Change password</h3>
                  <div class="dashboard-form-grid">
                    <label>
                      <span>Current password</span>
                      <input type="password" name="current_password" required />
                    </label>
                    <label>
                      <span>New password</span>
                      <input type="password" name="new_password" required minlength="8" />
                    </label>
                    <label>
                      <span>Confirm password</span>
                      <input type="password" name="confirm_password" required minlength="8" />
                    </label>
                  </div>
                  <p class="dashboard-form-note"><i class="fa-solid fa-shield"></i> Last updated <?php echo htmlspecialchars($config['security']['password_last_changed'] ?? 'recently'); ?>.</p>
                  <button type="submit" class="btn btn-secondary">Update password</button>
                </form>

                <div class="dashboard-form">
                  <h3>Two-factor authentication</h3>
                  <p class="dashboard-form-note">Secure your account with an additional verification step when signing in.</p>
                  <label class="dashboard-toggle">
                    <input type="checkbox" data-two-factor-toggle <?php echo !empty($config['security']['two_factor']['enabled']) ? 'checked' : ''; ?> />
                    <span class="dashboard-toggle-label">Enable 2FA</span>
                  </label>
                  <ul class="dashboard-twofactor-methods">
                    <?php foreach ($config['security']['two_factor']['methods'] as $method): ?>
                      <li><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> <?php echo htmlspecialchars($method); ?></li>
                    <?php endforeach; ?>
                  </ul>
                  <p class="dashboard-form-note">Status: <?php echo htmlspecialchars($config['security']['two_factor']['last_enabled']); ?></p>
                </div>
              </div>
            </section>
          </div>

          <aside class="dashboard-aside">
            <section class="dashboard-panel">
              <h2>Notifications</h2>
              <ul class="dashboard-notifications">
                <?php foreach ($config['notifications'] as $note): ?>
                  <li class="dashboard-notification dashboard-notification--<?php echo htmlspecialchars($note['tone']); ?>">
                    <i class="fa-solid <?php echo htmlspecialchars($note['icon']); ?>" aria-hidden="true"></i>
                    <div>
                      <p><?php echo htmlspecialchars($note['message']); ?></p>
                      <span><?php echo htmlspecialchars($note['time']); ?></span>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
            </section>

            <section class="dashboard-panel">
              <h2>Reminders</h2>
              <ul class="dashboard-reminders">
                <?php foreach ($config['reminders'] as $reminder): ?>
                  <li>
                    <p><?php echo htmlspecialchars($reminder['title']); ?></p>
                    <span><?php echo htmlspecialchars($reminder['time']); ?></span>
                  </li>
                <?php endforeach; ?>
              </ul>
            </section>

            <section class="dashboard-panel dashboard-panel--muted">
              <h2>Need help?</h2>
              <p>Search the knowledge base, or contact support for a priority callback.</p>
              <a class="btn btn-tertiary" href="<?php echo htmlspecialchars(portal_url('contact.html')); ?>">Contact support</a>
            </section>
          </aside>
        </div>
      </div>
    </section>
    <?php
}
