<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bootstrap.php';

require_role('installer');

$user = current_user();
$installerId = (int) ($user['id'] ?? 0);
$portalCsrfToken = $_SESSION['csrf_token'] ?? '';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token(is_string($token) ? $token : null)) {
        set_flash('error', 'Session expired. Please submit again.');
        header('Location: installer-dashboard.php');
        exit;
    }

    try {
        switch ($action) {
            case 'update_installation_stage':
                $installationId = (int) ($_POST['installation_id'] ?? 0);
                $targetStage = (string) ($_POST['target_stage'] ?? '');
                $remarks = trim((string) ($_POST['remarks'] ?? ''));
                $photo = trim((string) ($_POST['photo_label'] ?? ''));
                file_installation_update_stage($installationId, $targetStage, $installerId, 'installer', $remarks, $photo);
                set_flash('success', 'Installation update saved.');
                break;
            default:
                set_flash('error', 'Unsupported action.');
                break;
        }
    } catch (Throwable $exception) {
        set_flash('error', $exception->getMessage());
    }

    header('Location: installer-dashboard.php');
    exit;
}

$installations = file_installation_list_for_role('installer', $installerId);

$installerName = trim((string) ($user['full_name'] ?? 'Installer'));
if ($installerName === '') {
    $installerName = 'Installer';
}

function installer_format_datetime(?string $value): string
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

function installer_format_date(?string $value): string
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

$logoutUrl = 'logout.php';

?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Installer Workspace | Dakshayani Enterprises</title>
  <meta name="description" content="Live installation tracker for Dakshayani installers." />
  <link rel="icon" href="images/favicon.ico" />
  <link rel="stylesheet" href="style.css" />
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
<body class="installer-dashboard" data-theme="light">
  <main class="installer-shell">
    <header class="installer-header">
      <div class="installer-identity">
        <i class="fa-solid fa-helmet-safety" aria-hidden="true"></i>
        <div>
          <p class="installer-subtitle">Installer workspace</p>
          <h1 class="installer-title">Welcome back, <?= htmlspecialchars($installerName, ENT_QUOTES) ?></h1>
        </div>
      </div>
      <a href="<?= htmlspecialchars($logoutUrl, ENT_QUOTES) ?>" class="btn btn-ghost">
        <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
        Log out
      </a>
    </header>

    <?php if ($flashMessage !== ''): ?>
    <div class="admin-alert admin-alert--<?= htmlspecialchars($flashTone, ENT_QUOTES) ?>" role="status">
      <i class="fa-solid <?= htmlspecialchars($flashIcon, ENT_QUOTES) ?>" aria-hidden="true"></i>
      <span><?= htmlspecialchars($flashMessage, ENT_QUOTES) ?></span>
    </div>
    <?php endif; ?>

    <section class="installer-installations" aria-label="Assigned installations">
      <h2>Assigned installations</h2>
      <p class="installer-installations__meta">Update Structure, Wiring, or Meter progress and attach site photos or remarks for Admin review.</p>
      <?php if (empty($installations)): ?>
      <p class="empty-state">No installations assigned yet. Check back after Admin schedules your next project.</p>
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
            <li><i class="fa-solid fa-calendar-days" aria-hidden="true"></i> Scheduled: <?= htmlspecialchars(installer_format_date($installation['scheduled'] ?? ''), ENT_QUOTES) ?></li>
            <li><i class="fa-solid fa-flag-checkered" aria-hidden="true"></i> Target handover: <?= htmlspecialchars(installer_format_date($installation['handover'] ?? ''), ENT_QUOTES) ?></li>
            <li><i class="fa-solid fa-bolt" aria-hidden="true"></i> Capacity: <?= htmlspecialchars($installation['capacity'] ? number_format($installation['capacity'], 1) . ' kW' : '—', ENT_QUOTES) ?></li>
          </ul>
          <ol class="installation-card__progress">
            <?php foreach ($installation['progress'] as $step): ?>
            <li class="installation-card__progress-item installation-card__progress-item--<?= htmlspecialchars($step['state'], ENT_QUOTES) ?>">
              <?= htmlspecialchars($step['label'], ENT_QUOTES) ?>
            </li>
            <?php endforeach; ?>
          </ol>
          <form class="installation-card__form" method="post">
            <input type="hidden" name="action" value="update_installation_stage" />
            <input type="hidden" name="installation_id" value="<?= (int) $installation['id'] ?>" />
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($portalCsrfToken, ENT_QUOTES) ?>" />
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
          <?php if (!empty($installation['entries'])): ?>
          <div class="installation-card__timeline">
            <h4>Recent notes</h4>
            <ol>
              <?php foreach (array_slice(array_reverse($installation['entries']), 0, 4) as $entry): ?>
              <li>
                <div>
                  <strong><?= htmlspecialchars($entry['stageLabel'], ENT_QUOTES) ?></strong>
                  <span><?= htmlspecialchars($entry['actorName'], ENT_QUOTES) ?> · <?= htmlspecialchars(installer_format_datetime($entry['timestamp']), ENT_QUOTES) ?></span>
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
    </section>
  </main>
</body>
</html>
