<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bootstrap.php';

require_role('customer');

$user = current_user();
$db = get_db();
$sessionUserId = (int) ($user['id'] ?? 0);
$accountRecord = portal_find_user($db, $sessionUserId) ?: [
    'id' => $sessionUserId,
    'full_name' => $user['full_name'] ?? '',
    'email' => $user['email'] ?? '',
    'permissions_note' => '',
];
$accountRecord += [
    'full_name' => $user['full_name'] ?? ($accountRecord['full_name'] ?? ''),
    'email' => $user['email'] ?? ($accountRecord['email'] ?? ''),
];
$csrfToken = $_SESSION['csrf_token'] ?? '';

$installations = customer_portal_installations($db, $accountRecord);
$subsidySummary = customer_portal_subsidy($db, $installations);

$projectSuggestions = [];
foreach ($installations as $installation) {
    $label = $installation['project'] ?: $installation['customer'];
    if ($label !== '') {
        $projectSuggestions[] = $label;
    }
}
$projectSuggestions = array_values(array_unique($projectSuggestions));

$complaintData = [
    'reference' => $projectSuggestions[0] ?? '',
    'title' => '',
    'description' => '',
    'contact' => trim((string) ($accountRecord['email'] ?? '')),
];
$complaintError = '';
$complaintSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $complaintData = [
        'reference' => trim((string) ($_POST['reference'] ?? '')),
        'title' => trim((string) ($_POST['title'] ?? '')),
        'description' => trim((string) ($_POST['description'] ?? '')),
        'contact' => trim((string) ($_POST['contact'] ?? '')),
    ];

    $token = $_POST['csrf_token'] ?? null;
    if (!verify_csrf_token(is_string($token) ? $token : null)) {
        $complaintError = 'Session expired. Please submit the form again.';
    } else {
        try {
            $payload = [
                'reference' => strtoupper($complaintData['reference']),
                'title' => $complaintData['title'],
                'description' => $complaintData['description'],
                'customerName' => trim((string) ($accountRecord['full_name'] ?? $user['full_name'] ?? 'Customer')),
                'customerContact' => $complaintData['contact'],
                'origin' => 'customer',
                'priority' => 'medium',
            ];
            $complaint = portal_save_complaint($db, $payload, $sessionUserId);
            $reference = (string) ($complaint['reference'] ?? ($payload['reference'] ?: '')); 
            $complaintSuccess = $reference !== ''
                ? sprintf('Complaint %s submitted. Our service team will be in touch shortly.', $reference)
                : 'Complaint submitted. Our service team will be in touch shortly.';
            $complaintData['title'] = '';
            $complaintData['description'] = '';
        } catch (Throwable $exception) {
            $complaintError = $exception->getMessage();
        }
    }
}

$customerName = trim((string) ($accountRecord['full_name'] ?? 'Customer'));
if ($customerName === '') {
    $customerName = 'Customer';
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

function customer_format_date(?string $value): string
{
    if (!$value) {
        return '—';
    }
    try {
        $dt = new DateTimeImmutable($value);
        return $dt->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('d M Y');
    } catch (Throwable $exception) {
        return $value;
    }
}

function customer_format_datetime(?string $value): string
{
    if (!$value) {
        return '—';
    }
    try {
        $dt = new DateTimeImmutable($value);
        return $dt->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('d M Y · h:i A');
    } catch (Throwable $exception) {
        return $value;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Customer Workspace | Dakshayani Enterprises</title>
  <meta name="description" content="Track your solar project progress, subsidy stage, and raise service requests securely." />
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
<body>
  <main class="dashboard">
    <div class="container dashboard-shell">
      <div class="dashboard-auth-bar" role="banner">
        <div class="dashboard-auth-user">
          <i class="fa-solid fa-house-signal" aria-hidden="true"></i>
          <div>
            <small>Signed in as</small>
            <strong><?= htmlspecialchars($customerName, ENT_QUOTES) ?> · Customer</strong>
          </div>
        </div>
        <div class="dashboard-auth-actions">
          <a href="<?= htmlspecialchars($logoutUrl, ENT_QUOTES) ?>" class="btn btn-ghost">
            <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
            Log out
          </a>
        </div>
      </div>

      <?php if ($complaintError !== ''): ?>
      <div class="admin-alert admin-alert--error" role="status" aria-live="polite">
        <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
        <span><?= htmlspecialchars($complaintError, ENT_QUOTES) ?></span>
      </div>
      <?php endif; ?>

      <?php if ($complaintSuccess !== ''): ?>
      <div class="admin-alert admin-alert--success" role="status" aria-live="polite">
        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
        <span><?= htmlspecialchars($complaintSuccess, ENT_QUOTES) ?></span>
      </div>
      <?php endif; ?>

      <section class="dashboard-panel" aria-labelledby="project-details">
        <div>
          <h2 id="project-details">Project details</h2>
          <p>Review installation progress and the latest updates shared by the Dakshayani field team.</p>
        </div>
        <?php if (empty($installations)): ?>
        <p>No projects are currently linked to your login. Reach out to your relationship manager if you believe this is a mistake.</p>
        <?php else: ?>
        <div class="customer-projects">
          <?php foreach ($installations as $installation): ?>
          <?php
            $projectLabel = $installation['project'] ?: $installation['customer'] ?: 'Installation #' . (int) $installation['id'];
            $subsidy = $subsidySummary[(int) $installation['id']] ?? null;
            $subsidyStage = $subsidy['stageLabel'] ?? 'Pending update';
            $subsidyDate = $subsidy['stageDate'] ?? '';
            $subsidyClass = match (strtolower((string) ($subsidy['stage'] ?? ''))) {
                'approved', 'disbursed' => 'dashboard-status--approved',
                'under_review' => 'dashboard-status--pending',
                default => 'dashboard-status--pending',
            };
          ?>
          <article class="dashboard-panel dashboard-panel--muted customer-project">
            <header class="customer-project__header">
              <div>
                <h3><?= htmlspecialchars($projectLabel, ENT_QUOTES) ?></h3>
                <p class="customer-project__meta">Last updated <?= htmlspecialchars(customer_format_datetime($installation['updated'] ?? ''), ENT_QUOTES) ?></p>
              </div>
              <span class="badge badge-soft"><?= htmlspecialchars($installation['stageLabel'], ENT_QUOTES) ?></span>
            </header>
            <ul class="customer-project__details">
              <li><i class="fa-solid fa-bolt" aria-hidden="true"></i> Capacity: <?= htmlspecialchars($installation['capacity'] ? number_format((float) $installation['capacity'], 1) . ' kW' : '—', ENT_QUOTES) ?></li>
              <li><i class="fa-solid fa-calendar-days" aria-hidden="true"></i> Scheduled: <?= htmlspecialchars(customer_format_date($installation['scheduled'] ?? ''), ENT_QUOTES) ?></li>
              <li><i class="fa-solid fa-flag-checkered" aria-hidden="true"></i> Target handover: <?= htmlspecialchars(customer_format_date($installation['handover'] ?? ''), ENT_QUOTES) ?></li>
            </ul>
            <ol class="installation-card__progress">
              <?php foreach ($installation['progress'] as $step): ?>
              <li class="installation-card__progress-item installation-card__progress-item--<?= htmlspecialchars($step['state'], ENT_QUOTES) ?>">
                <?= htmlspecialchars($step['label'], ENT_QUOTES) ?>
              </li>
              <?php endforeach; ?>
            </ol>
            <div class="customer-project__subsidy">
              <span>Subsidy stage:</span>
              <span class="dashboard-status <?= htmlspecialchars($subsidyClass, ENT_QUOTES) ?>"><?= htmlspecialchars($subsidyStage, ENT_QUOTES) ?></span>
              <?php if ($subsidyDate !== ''): ?>
              <span class="customer-project__subsidy-date">(updated <?= htmlspecialchars(customer_format_date($subsidyDate), ENT_QUOTES) ?>)</span>
              <?php endif; ?>
            </div>
          </article>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </section>

      <section class="dashboard-panel" aria-labelledby="service-form">
        <div>
          <h2 id="service-form">Raise a service request</h2>
          <p>Log issues or support needs directly with the Dakshayani service desk. Your request lands with Admin instantly.</p>
        </div>
        <form method="post" class="dashboard-form">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" />
          <?php if (!empty($projectSuggestions)): ?>
          <datalist id="customer-project-options">
            <?php foreach ($projectSuggestions as $option): ?>
            <option value="<?= htmlspecialchars($option, ENT_QUOTES) ?>"></option>
            <?php endforeach; ?>
          </datalist>
          <?php endif; ?>
          <div class="dashboard-form-grid">
            <label>
              Project reference
              <input type="text" name="reference" list="customer-project-options" value="<?= htmlspecialchars($complaintData['reference'], ENT_QUOTES) ?>" placeholder="e.g. PRJ-102 or customer name" />
            </label>
            <label>
              Contact details
              <input type="text" name="contact" value="<?= htmlspecialchars($complaintData['contact'], ENT_QUOTES) ?>" placeholder="Phone or preferred email" />
            </label>
          </div>
          <label>
            Issue title
            <input type="text" name="title" required value="<?= htmlspecialchars($complaintData['title'], ENT_QUOTES) ?>" placeholder="Brief summary" />
          </label>
          <label>
            Describe the issue
            <textarea name="description" rows="4" required placeholder="Share what happened, times, or any photos shared separately."><?= htmlspecialchars($complaintData['description'], ENT_QUOTES) ?></textarea>
          </label>
          <button type="submit" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-circle-plus" aria-hidden="true"></i>
            Submit request
          </button>
        </form>
      </section>
    </div>
  </main>
</body>
</html>
