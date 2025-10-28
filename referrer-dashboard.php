<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bootstrap.php';

require_role('referrer');

$user = current_user();
$sessionUserId = (int) ($user['id'] ?? 0);
$storedAccount = file_portal_find_user($sessionUserId) ?: [];
$referrerAccount = $storedAccount + [
    'id' => $sessionUserId,
    'full_name' => $user['full_name'] ?? '',
    'email' => $user['email'] ?? '',
];
$csrfToken = $_SESSION['csrf_token'] ?? '';

$profileError = '';
try {
    $referrerProfile = file_referrer_ensure_profile($referrerAccount);
} catch (Throwable $exception) {
    $profileError = $exception->getMessage();
    $referrerProfile = null;
}

$formData = [
    'name' => '',
    'phone' => '',
    'email' => '',
    'site_location' => '',
    'notes' => '',
];
$formError = '';
$formSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'name' => trim((string) ($_POST['name'] ?? '')),
        'phone' => trim((string) ($_POST['phone'] ?? '')),
        'email' => trim((string) ($_POST['email'] ?? '')),
        'site_location' => trim((string) ($_POST['site_location'] ?? '')),
        'notes' => trim((string) ($_POST['notes'] ?? '')),
    ];

    $token = $_POST['csrf_token'] ?? null;
    if (!verify_csrf_token(is_string($token) ? $token : null)) {
        $formError = 'Session expired. Please submit the lead again.';
    } elseif ($referrerProfile === null) {
        $formError = $profileError !== ''
            ? $profileError
            : 'Lead submission is temporarily unavailable. Please contact the administrator.';
    } else {
        try {
            $lead = file_referrer_submit_lead($_POST, (int) $referrerProfile['id'], $sessionUserId);
            $leadName = trim((string) ($lead['name'] ?? 'New lead'));
            $formSuccess = sprintf('Lead "%s" submitted successfully.', $leadName !== '' ? $leadName : 'New lead');
            $formData = [
                'name' => '',
                'phone' => '',
                'email' => '',
                'site_location' => '',
                'notes' => '',
            ];
        } catch (Throwable $exception) {
            $formError = $exception->getMessage();
        }
    }
}

$leads = $referrerProfile ? file_referrer_portal_leads((int) $referrerProfile['id']) : [];
$statusCounts = [
    'approved' => 0,
    'converted' => 0,
    'rejected' => 0,
];
foreach ($leads as $leadRow) {
    $category = $leadRow['category'] ?? 'approved';
    if (isset($statusCounts[$category])) {
        $statusCounts[$category]++;
    }
}

$referrerName = trim((string) ($referrerProfile['name'] ?? ($user['full_name'] ?? 'Referrer')));
if ($referrerName === '') {
    $referrerName = 'Referrer';
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

function referrer_format_datetime(?string $value): string
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

function referrer_format_contact(array $lead): string
{
    $parts = [];
    $phone = trim((string) ($lead['phone'] ?? ''));
    $email = trim((string) ($lead['email'] ?? ''));
    if ($phone !== '') {
        $parts[] = $phone;
    }
    if ($email !== '') {
        $parts[] = $email;
    }

    return $parts ? implode(' · ', $parts) : '—';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Referrer Workspace | Dakshayani Enterprises</title>
  <meta name="description" content="Submit new leads and follow up on their status with the Dakshayani sales desk." />
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
          <i class="fa-solid fa-handshake-angle" aria-hidden="true"></i>
          <div>
            <small>Signed in as</small>
            <strong><?= htmlspecialchars($referrerName, ENT_QUOTES) ?> · Referrer</strong>
          </div>
        </div>
        <div class="dashboard-auth-actions">
          <a href="<?= htmlspecialchars($logoutUrl, ENT_QUOTES) ?>" class="btn btn-ghost">
            <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
            Log out
          </a>
        </div>
      </div>

      <?php if ($profileError !== '' && $referrerProfile === null): ?>
      <div class="admin-alert admin-alert--warning" role="status" aria-live="polite">
        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
        <span><?= htmlspecialchars($profileError, ENT_QUOTES) ?></span>
      </div>
      <?php endif; ?>

      <?php if ($formError !== ''): ?>
      <div class="admin-alert admin-alert--error" role="status" aria-live="polite">
        <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
        <span><?= htmlspecialchars($formError, ENT_QUOTES) ?></span>
      </div>
      <?php endif; ?>

      <?php if ($formSuccess !== ''): ?>
      <div class="admin-alert admin-alert--success" role="status" aria-live="polite">
        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
        <span><?= htmlspecialchars($formSuccess, ENT_QUOTES) ?></span>
      </div>
      <?php endif; ?>

      <section class="dashboard-panel" aria-labelledby="lead-overview">
        <div>
          <h2 id="lead-overview">Lead pipeline</h2>
          <p>Track the journey of every opportunity you share with Dakshayani Enterprises.</p>
        </div>
        <div class="dashboard-cards dashboard-cards--grid">
          <article class="dashboard-card dashboard-card--neutral">
            <div class="dashboard-card-icon">
              <i class="fa-solid fa-user-check" aria-hidden="true"></i>
            </div>
            <div>
              <h3 class="dashboard-card-title">Approved</h3>
              <p class="dashboard-card-value"><?= (int) $statusCounts['approved'] ?></p>
              <p class="dashboard-card-meta">Accepted and under review</p>
            </div>
          </article>
          <article class="dashboard-card dashboard-card--positive">
            <div class="dashboard-card-icon">
              <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
            </div>
            <div>
              <h3 class="dashboard-card-title">Converted</h3>
              <p class="dashboard-card-value"><?= (int) $statusCounts['converted'] ?></p>
              <p class="dashboard-card-meta">Projects that became customers</p>
            </div>
          </article>
          <article class="dashboard-card dashboard-card--warning">
            <div class="dashboard-card-icon">
              <i class="fa-solid fa-circle-xmark" aria-hidden="true"></i>
            </div>
            <div>
              <h3 class="dashboard-card-title">Rejected</h3>
              <p class="dashboard-card-value"><?= (int) $statusCounts['rejected'] ?></p>
              <p class="dashboard-card-meta">Not moving forward</p>
            </div>
          </article>
        </div>
      </section>

      <section class="dashboard-panel" aria-labelledby="lead-create">
        <div>
          <h2 id="lead-create">Add new lead</h2>
          <p>Share basic contact information so the sales desk can qualify the opportunity.</p>
        </div>
        <?php if ($referrerProfile !== null): ?>
        <form method="post" class="dashboard-form">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" />
          <div class="dashboard-form-grid">
            <label>
              Customer name
              <input type="text" name="name" required value="<?= htmlspecialchars($formData['name'], ENT_QUOTES) ?>" placeholder="Primary decision-maker" />
            </label>
            <label>
              Phone
              <input type="tel" name="phone" pattern="[0-9+\-\s]{6,}" value="<?= htmlspecialchars($formData['phone'], ENT_QUOTES) ?>" placeholder="Contact number" />
            </label>
            <label>
              Email
              <input type="email" name="email" value="<?= htmlspecialchars($formData['email'], ENT_QUOTES) ?>" placeholder="customer@example.com" />
            </label>
            <label>
              Site location
              <input type="text" name="site_location" value="<?= htmlspecialchars($formData['site_location'], ENT_QUOTES) ?>" placeholder="City or neighbourhood" />
            </label>
          </div>
          <label>
            Notes
            <textarea name="notes" rows="3" placeholder="Roof type, load profile, or any context."><?= htmlspecialchars($formData['notes'], ENT_QUOTES) ?></textarea>
          </label>
          <button type="submit" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
            Submit lead
          </button>
        </form>
        <?php else: ?>
        <p>Lead submission is currently disabled because your referrer profile is not linked. Please contact the administrator to restore access.</p>
        <?php endif; ?>
      </section>

      <section class="dashboard-panel" aria-labelledby="lead-statuses">
        <div>
          <h2 id="lead-statuses">Submitted leads</h2>
          <p>Statuses update automatically as the Dakshayani team works on each opportunity.</p>
        </div>
        <?php if (empty($leads)): ?>
        <p>You have not submitted any leads yet. Every opportunity you log will appear here with its latest status.</p>
        <?php else: ?>
        <div class="dashboard-table-wrapper">
          <table class="dashboard-table">
            <thead>
              <tr>
                <th scope="col">Lead</th>
                <th scope="col">Contact</th>
                <th scope="col">Portal status</th>
                <th scope="col">Sales stage</th>
                <th scope="col">Last updated</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($leads as $lead): ?>
              <?php
                $categoryClass = match ($lead['category']) {
                    'converted' => 'dashboard-status--approved',
                    'rejected' => 'dashboard-status--rejected',
                    default => 'dashboard-status--pending',
                };
                $pipelineLabel = (string) ($lead['statusLabel'] ?? '');
              ?>
              <tr>
                <th scope="row">
                  <div>
                    <strong><?= htmlspecialchars($lead['name'], ENT_QUOTES) ?></strong>
                    <div class="dashboard-card-meta">Submitted <?= htmlspecialchars(referrer_format_datetime($lead['createdAt'] ?? ''), ENT_QUOTES) ?></div>
                  </div>
                </th>
                <td><?= htmlspecialchars(referrer_format_contact($lead), ENT_QUOTES) ?></td>
                <td>
                  <span class="dashboard-status <?= htmlspecialchars($categoryClass, ENT_QUOTES) ?>">
                    <?= htmlspecialchars($lead['categoryLabel'], ENT_QUOTES) ?>
                  </span>
                </td>
                <td>
                  <?php if ($pipelineLabel !== ''): ?>
                  <span class="badge badge-soft"><?= htmlspecialchars($pipelineLabel, ENT_QUOTES) ?></span>
                  <?php else: ?>
                  —
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars(referrer_format_datetime($lead['updatedAt'] ?: $lead['createdAt'] ?? ''), ENT_QUOTES) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </section>
    </div>
  </main>
</body>
</html>
