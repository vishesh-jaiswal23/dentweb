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

$counts = admin_overview_counts($db);
$highlights = admin_today_highlights($db, 20);
$reminderDueCounts = reminder_due_counts($db);

ai_daily_notes_generate_if_due();
$aiDashboardNotes = ai_daily_notes_recent(2);

$todayIst = new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata'));
$todayDate = $todayIst->format('Y-m-d');
$yesterdayDate = $todayIst->modify('-1 day')->format('Y-m-d');

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

$reminderPath = $pathFor('admin-reminders.php');
$dueTodayLink = $reminderPath . '?status=active&from=' . urlencode($todayDate) . '&to=' . urlencode($todayDate) . '#reminder-list';
$overdueLink = $reminderPath . '?status=active&to=' . urlencode($yesterdayDate) . '#reminder-list';

$cardConfigs = [
    [
        'label' => 'Active Employees',
        'value' => $counts['employees'],
        'icon' => 'fa-user-check',
        'description' => 'Staff with Dentweb access who are currently enabled.',
        'link' => $pathFor('admin-records.php') . '?module=employees&filter=active',
    ],
    [
        'label' => 'New Leads',
        'value' => $counts['leads'],
        'icon' => 'fa-user-plus',
        'description' => 'Enquiries that still require qualification or hand-off.',
        'link' => $pathFor('admin-leads.php'),
    ],
    [
        'label' => 'Active Referrers',
        'value' => $counts['referrers'],
        'icon' => 'fa-handshake-angle',
        'description' => 'Channel partners enabled to submit and track leads.',
        'link' => $pathFor('admin-referrers.php'),
    ],
    [
        'label' => 'Ongoing Installations',
        'value' => $counts['installations'],
        'icon' => 'fa-solar-panel',
        'description' => 'Projects that are live on-site and awaiting closure.',
        'link' => $pathFor('admin-records.php') . '?module=installations&filter=ongoing',
    ],
    [
        'label' => 'Open Complaints',
        'value' => $counts['complaints'],
        'icon' => 'fa-headset',
        'description' => 'Active complaints pending field work or admin approval.',
        'link' => $pathFor('admin-complaints.php') . '?filter=open',
    ],
    [
        'label' => 'Subsidy Pending',
        'value' => $counts['subsidy'],
        'icon' => 'fa-indian-rupee-sign',
        'description' => 'Applications awaiting approval or disbursal.',
        'link' => $pathFor('admin-subsidy-tracker.php') . '?stage=pending',
    ],
    [
        'label' => 'Active Reminders',
        'value' => $counts['reminders'],
        'icon' => 'fa-bell',
        'description' => 'Follow-ups awaiting admin attention.',
        'link' => $pathFor('admin-reminders.php'),
    ],
];

$moduleMeta = [
    'employees' => ['label' => 'Employees', 'icon' => 'fa-user-check'],
    'leads' => ['label' => 'Leads', 'icon' => 'fa-user-plus'],
    'installations' => ['label' => 'Installations', 'icon' => 'fa-solar-panel'],
    'complaints' => ['label' => 'Complaints', 'icon' => 'fa-headset'],
    'subsidy' => ['label' => 'Subsidy', 'icon' => 'fa-indian-rupee-sign'],
    'reminders' => ['label' => 'Reminders', 'icon' => 'fa-bell'],
];

$indiaTz = new DateTimeZone('Asia/Kolkata');
$highlightItems = array_map(static function (array $item) use ($moduleMeta, $indiaTz): array {
    $module = $moduleMeta[$item['module']] ?? ['label' => ucfirst($item['module']), 'icon' => 'fa-circle-info'];
    try {
        $timestamp = new DateTimeImmutable($item['timestamp']);
    } catch (Throwable $exception) {
        $timestamp = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
    $localTime = $timestamp->setTimezone($indiaTz);

    return [
        'moduleKey' => $item['module'],
        'moduleLabel' => $module['label'],
        'icon' => $module['icon'],
        'summary' => $item['summary'],
        'timeDisplay' => $localTime->format('d M · h:i A'),
        'isoTime' => $localTime->format(DateTimeInterface::ATOM),
    ];
}, $highlights);

?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin Overview | Dakshayani Enterprises</title>
  <meta name="description" content="At-a-glance admin overview with live counts and recent activity across Dentweb operations." />
  <link rel="icon" href="<?= htmlspecialchars($pathFor('images/favicon.ico'), ENT_QUOTES) ?>" />
  <link rel="stylesheet" href="<?= htmlspecialchars($pathFor('style.css'), ENT_QUOTES) ?>" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap"
    rel="stylesheet"
  />
  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    crossorigin="anonymous"
    referrerpolicy="no-referrer"
  />
</head>
<body class="admin-overview" data-theme="light">
  <main class="admin-overview__shell">
    <?php if ($flashMessage !== ''): ?>
    <div class="admin-alert admin-alert--<?= htmlspecialchars($flashTone, ENT_QUOTES) ?>" role="status" aria-live="polite">
      <i class="fa-solid <?= htmlspecialchars($flashIcon, ENT_QUOTES) ?>" aria-hidden="true"></i>
      <span><?= htmlspecialchars($flashMessage, ENT_QUOTES) ?></span>
    </div>
    <?php endif; ?>

    <header class="admin-overview__header">
      <div class="admin-overview__identity">
        <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
        <div>
          <p class="admin-overview__subtitle">Welcome back</p>
          <h1 class="admin-overview__title">Admin Overview</h1>
          <p class="admin-overview__user">Signed in as <strong><?= htmlspecialchars($user['full_name'] ?? 'Administrator', ENT_QUOTES) ?></strong></p>
        </div>
      </div>
      <div class="admin-overview__actions">
        <a href="<?= htmlspecialchars($pathFor('admin-users.php'), ENT_QUOTES) ?>" class="btn btn-ghost">
          <i class="fa-solid fa-users-gear" aria-hidden="true"></i>
          Users
        </a>
        <a href="<?= htmlspecialchars($pathFor('admin-requests.php'), ENT_QUOTES) ?>" class="btn btn-ghost">
          <i class="fa-solid fa-inbox" aria-hidden="true"></i>
          Requests
        </a>
        <a href="<?= htmlspecialchars($pathFor('admin-record-import.php'), ENT_QUOTES) ?>" class="btn btn-ghost">
          <i class="fa-solid fa-file-arrow-up" aria-hidden="true"></i>
          Bulk import
        </a>
        <a href="<?= htmlspecialchars($pathFor('admin-ai-studio.php'), ENT_QUOTES) ?>" class="btn btn-ghost">
          <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
          AI Studio
        </a>
        <button type="button" class="btn btn-ghost" data-theme-toggle>
          <i class="fa-solid fa-circle-half-stroke" aria-hidden="true"></i>
          Theme
        </button>
        <a href="<?= htmlspecialchars($pathFor('logout.php'), ENT_QUOTES) ?>" class="btn btn-primary">
          <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
          Log out
        </a>
      </div>
    </header>

    <section class="admin-overview__reminder-glance" aria-label="Reminder due states">
      <h2 class="admin-overview__reminder-title">Reminder deadlines</h2>
      <div class="admin-overview__reminder-chips">
        <a class="reminder-chip" href="<?= htmlspecialchars($dueTodayLink, ENT_QUOTES) ?>">
          <span class="reminder-chip__label">Due today</span>
          <span class="reminder-chip__count"><?= number_format((int) ($reminderDueCounts['due_today'] ?? 0)) ?></span>
        </a>
        <a class="reminder-chip reminder-chip--danger" href="<?= htmlspecialchars($overdueLink, ENT_QUOTES) ?>">
          <span class="reminder-chip__label">Overdue</span>
          <span class="reminder-chip__count"><?= number_format((int) ($reminderDueCounts['overdue'] ?? 0)) ?></span>
        </a>
      </div>
    </section>

    <?php if (!empty($aiDashboardNotes)): ?>
    <section class="admin-overview__ai-digest" aria-labelledby="ai-digest-title">
      <div class="admin-overview__ai-digest-header">
        <div>
          <h2 id="ai-digest-title">AI Daily Notes</h2>
          <p>Latest automation snapshots captured at 8 PM and 9 PM IST.</p>
        </div>
      </div>
      <div class="admin-overview__ai-digest-grid">
        <?php foreach ($aiDashboardNotes as $note): ?>
        <article class="ai-note-card">
          <header>
            <p class="ai-note-card__type"><?= htmlspecialchars($note['label'], ENT_QUOTES) ?></p>
            <p class="ai-note-card__timestamp"><?= htmlspecialchars($note['display_label'], ENT_QUOTES) ?></p>
          </header>
          <p class="ai-note-card__content"><?= htmlspecialchars($note['content'], ENT_QUOTES) ?></p>
        </article>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <section class="admin-overview__cards" aria-label="Operational summaries">
      <?php foreach ($cardConfigs as $card): ?>
      <a class="overview-card" href="<?= htmlspecialchars($card['link'], ENT_QUOTES) ?>">
        <div class="overview-card__icon" aria-hidden="true"><i class="fa-solid <?= htmlspecialchars($card['icon'], ENT_QUOTES) ?>"></i></div>
        <div class="overview-card__body">
          <p class="overview-card__label"><?= htmlspecialchars($card['label'], ENT_QUOTES) ?></p>
          <p class="overview-card__value"><?= number_format((int) $card['value']) ?></p>
          <p class="overview-card__meta"><?= htmlspecialchars($card['description'], ENT_QUOTES) ?></p>
        </div>
        <span class="overview-card__cta" aria-hidden="true">View list <i class="fa-solid fa-arrow-right"></i></span>
      </a>
      <?php endforeach; ?>
    </section>

    <section class="admin-overview__highlights" aria-labelledby="highlights-title">
      <div class="admin-overview__highlights-header">
        <h2 id="highlights-title">Today's Highlights</h2>
        <p class="admin-overview__highlights-sub">Recent changes across leads, installations, complaints, subsidy, and reminders.</p>
      </div>
      <?php if (count($highlightItems) === 0): ?>
      <p class="admin-overview__empty">No activity recorded yet today. Updates from leads, installations, complaints, subsidy, and reminders will appear here.</p>
      <?php else: ?>
      <ol class="highlight-list">
        <?php foreach ($highlightItems as $item): ?>
        <li class="highlight-list__item highlight-list__item--<?= htmlspecialchars($item['moduleKey'], ENT_QUOTES) ?>">
          <div class="highlight-list__icon" aria-hidden="true"><i class="fa-solid <?= htmlspecialchars($item['icon'], ENT_QUOTES) ?>"></i></div>
          <div class="highlight-list__content">
            <p class="highlight-list__module"><?= htmlspecialchars($item['moduleLabel'], ENT_QUOTES) ?></p>
            <p class="highlight-list__summary"><?= htmlspecialchars($item['summary'], ENT_QUOTES) ?></p>
          </div>
          <time class="highlight-list__time" datetime="<?= htmlspecialchars($item['isoTime'], ENT_QUOTES) ?>" data-highlight-time><?= htmlspecialchars($item['timeDisplay'], ENT_QUOTES) ?></time>
        </li>
        <?php endforeach; ?>
      </ol>
      <?php endif; ?>
    </section>
  </main>

  <script src="<?= htmlspecialchars($pathFor('admin-dashboard.js'), ENT_QUOTES) ?>" defer></script>
</body>
</html>
