<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bootstrap.php';

require_admin();
$user = current_user();
$db = get_db();
$adminCsrfToken = $_SESSION['csrf_token'] ?? '';

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

$module = strtolower(trim((string) ($_GET['module'] ?? 'employees')));

$modules = [
    'employees' => [
        'title' => 'Employees',
        'description' => 'Manage staff access and activity for the internal portals.',
        'defaultFilter' => 'active',
        'filters' => [
            'active' => 'Active',
            'inactive' => 'Inactive',
            'pending' => 'Pending',
            'all' => 'All employees',
        ],
        'columns' => ['Name', 'Email', 'Status', 'Created', 'Last login'],
        'fetch' => static function (PDO $db, string $filter): array {
            return admin_list_employees($db, $filter);
        },
        'transform' => static function (array $row): array {
            return [
                $row['full_name'] ?? '',
                $row['email'] ?? '',
                ucfirst((string) ($row['status'] ?? '')),
                format_admin_datetime($row['created_at'] ?? ''),
                format_admin_datetime($row['last_login_at'] ?? ''),
            ];
        },
    ],
    'leads' => [
        'title' => 'Leads',
        'description' => 'Follow up on enquiries and site visits captured by the sales desk.',
        'defaultFilter' => 'new',
        'filters' => [
            'new' => 'New',
            'visited' => 'Visited',
            'quotation' => 'Quotation',
            'converted' => 'Converted',
            'lost' => 'Lost',
            'all' => 'All leads',
        ],
        'columns' => ['Lead', 'Status', 'Source', 'Assigned to', 'Updated'],
        'fetch' => static function (PDO $db, string $filter): array {
            return admin_list_leads($db, $filter);
        },
        'transform' => static function (array $row): array {
            return [
                $row['name'] ?? '',
                lead_status_label($row['status'] ?? ''),
                $row['source'] ? ucfirst((string) $row['source']) : '—',
                ($row['assigned_name'] ?? '') !== '' ? $row['assigned_name'] : ($row['assigned_to'] ? ('User #' . $row['assigned_to']) : 'Unassigned'),
                format_admin_datetime($row['updated_at'] ?: ($row['created_at'] ?? '')),
            ];
        },
    ],
    'installations' => [
        'title' => 'Installations',
        'description' => 'Monitor ongoing projects and confirm commissioning milestones.',
        'defaultFilter' => 'ongoing',
        'filters' => [
            'ongoing' => 'Ongoing (Structure–Meter)',
            'structure' => 'Structure',
            'wiring' => 'Wiring',
            'testing' => 'Testing',
            'meter' => 'Meter',
            'pending_commissioned' => 'Pending Commissioning',
            'commissioned' => 'Commissioned',
            'on_hold' => 'On hold',
            'cancelled' => 'Cancelled',
            'all' => 'All jobs',
        ],
        'columns' => ['Project', 'Stage', 'AMC', 'Scheduled', 'Handover', 'Updated'],
        'fetch' => static function (PDO $db, string $filter): array {
            return file_admin_list_installations($filter);
        },
        'transform' => static function (array $row): array {
            $name = $row['project'] ?: ($row['customer'] ?? '');
            $stageLabel = $row['requestedStage'] === 'commissioned'
                ? 'Commissioning Pending'
                : ($row['stageLabel'] ?? installation_stage_label($row['stage'] ?? ''));
            return [
                $name,
                $stageLabel,
                !empty($row['amcCommitted']) ? 'Yes' : 'No',
                format_admin_date($row['scheduled'] ?? ''),
                format_admin_date($row['handover'] ?? ''),
                format_admin_datetime($row['updated'] ?: ($row['created'] ?? '')),
            ];
        },
    ],
    'subsidy' => [
        'title' => 'Subsidy',
        'description' => 'Review the pipeline for PM Surya Ghar and other subsidy applications.',
        'defaultFilter' => 'pending',
        'filters' => [
            'pending' => 'Pending (Not Disbursed)',
            'applied' => 'Applied',
            'under_review' => 'Under Review',
            'approved' => 'Approved',
            'disbursed' => 'Disbursed',
            'all' => 'All applications',
        ],
        'columns' => ['Application', 'Status', 'Amount', 'Submitted', 'Updated'],
        'fetch' => static function (PDO $db, string $filter): array {
            return admin_list_subsidy($db, $filter);
        },
        'transform' => static function (array $row): array {
            $label = $row['application_number'] ?: ($row['customer_name'] ?? '');
            $amount = isset($row['amount']) ? number_format((float) $row['amount']) : '—';
            return [
                $label,
                subsidy_status_label($row['status'] ?? ''),
                $amount,
                format_admin_date($row['submitted_on'] ?? ''),
                format_admin_datetime($row['updated_at'] ?: ($row['created_at'] ?? '')),
            ];
        },
    ],
];

if (!isset($modules[$module])) {
    $module = 'employees';
}

$config = $modules[$module];
$filter = strtolower(trim((string) ($_GET['filter'] ?? $config['defaultFilter'])));
if (!isset($config['filters'][$filter])) {
    $filter = $config['defaultFilter'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        set_flash('error', 'Your session expired. Please try again.');
        header('Location: admin-records.php?module=' . urlencode($module) . '&filter=' . urlencode($filter));
        exit;
    }

    try {
        $installationId = isset($_POST['installation_id']) ? (int) $_POST['installation_id'] : 0;
        if ($installationId <= 0) {
            throw new RuntimeException('Installation reference missing.');
        }

        $actorId = (int) ($user['id'] ?? 0);
        switch ($action) {
            case 'update_stage':
                $targetStage = (string) ($_POST['target_stage'] ?? '');
                $remarks = trim((string) ($_POST['remarks'] ?? ''));
                $photo = trim((string) ($_POST['photo_label'] ?? ''));
                file_installation_update_stage($installationId, $targetStage, $actorId, 'admin', $remarks, $photo);
                set_flash('success', 'Installation stage updated.');
                break;
            case 'approve_commissioning':
                $remarks = trim((string) ($_POST['remarks'] ?? ''));
                file_installation_approve_commissioning($installationId, $actorId, $remarks);
                set_flash('success', 'Commissioning approved.');
                break;
            case 'toggle_amc':
                $targetAmc = isset($_POST['target_amc']) && $_POST['target_amc'] === '1';
                file_installation_toggle_amc($installationId, $targetAmc, $actorId);
                set_flash('success', $targetAmc ? 'AMC commitment captured.' : 'AMC commitment removed.');
                break;
            default:
                throw new RuntimeException('Unsupported action.');
        }
    } catch (Throwable $exception) {
        set_flash('error', $exception->getMessage());
    }

    header('Location: admin-records.php?module=' . urlencode($module) . '&filter=' . urlencode($filter));
    exit;
}

$rows = ($config['fetch'])($db, $filter);
$records = array_map($config['transform'], $rows);
$installationCards = [];
if ($module === 'installations') {
    $installationCards = $rows;
}

function format_admin_datetime(?string $value): string
{
    if (!$value) {
        return '—';
    }
    try {
        $dt = new DateTimeImmutable($value);
    } catch (Throwable $exception) {
        return $value;
    }
    return $dt->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('d M Y · h:i A');
}

function format_admin_date(?string $value): string
{
    if (!$value) {
        return '—';
    }
    try {
        $dt = new DateTimeImmutable($value);
    } catch (Throwable $exception) {
        return $value;
    }
    return $dt->format('d M Y');
}

?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($config['title'], ENT_QUOTES) ?> | Admin Lists</title>
  <meta name="description" content="Filtered admin records for <?= htmlspecialchars($config['title'], ENT_QUOTES) ?>." />
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
<body class="admin-records" data-theme="light">
  <main class="admin-records__shell">
    <?php if ($flashMessage !== ''): ?>
    <div class="admin-alert admin-alert--<?= htmlspecialchars($flashTone, ENT_QUOTES) ?>" role="status" aria-live="polite">
      <i class="fa-solid <?= htmlspecialchars($flashIcon, ENT_QUOTES) ?>" aria-hidden="true"></i>
      <span><?= htmlspecialchars($flashMessage, ENT_QUOTES) ?></span>
    </div>
    <?php endif; ?>

    <header class="admin-records__header">
      <div class="admin-records__title-group">
        <a href="admin-dashboard.php" class="admin-records__back"><i class="fa-solid fa-arrow-left"></i> Overview</a>
        <h1><?= htmlspecialchars($config['title'], ENT_QUOTES) ?></h1>
        <p><?= htmlspecialchars($config['description'], ENT_QUOTES) ?></p>
      </div>
      <div class="admin-records__meta">
        <span>Signed in as <?= htmlspecialchars($user['full_name'] ?? 'Administrator', ENT_QUOTES) ?></span>
        <a href="logout.php" class="btn btn-ghost">Log out</a>
      </div>
    </header>

    <form class="admin-records__filter" method="get">
      <input type="hidden" name="module" value="<?= htmlspecialchars($module, ENT_QUOTES) ?>" />
      <label>
        Filter
        <select name="filter" onchange="this.form.submit()">
          <?php foreach ($config['filters'] as $value => $label): ?>
          <option value="<?= htmlspecialchars($value, ENT_QUOTES) ?>" <?= $value === $filter ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </form>

    <?php if ($module === 'installations'): ?>
    <section class="admin-installations" aria-label="Installation stage tracker">
      <?php if (count($installationCards) === 0): ?>
      <p class="admin-records__empty">No installations match the selected view.</p>
      <?php else: ?>
      <div class="admin-installations__grid">
        <?php foreach ($installationCards as $installation): ?>
        <article class="installation-admin-card dashboard-panel">
          <header class="installation-card__header">
            <div>
              <h2><?= htmlspecialchars($installation['project'] ?: $installation['customer'], ENT_QUOTES) ?></h2>
              <p class="installation-card__customer">Customer: <?= htmlspecialchars($installation['customer'], ENT_QUOTES) ?></p>
            </div>
            <?php $stageLabel = $installation['requestedStage'] === 'commissioned' ? 'Commissioning Pending' : $installation['stageLabel']; ?>
            <span class="installation-card__stage installation-card__stage--<?= htmlspecialchars($installation['stageTone'], ENT_QUOTES) ?>">
              <?= htmlspecialchars($stageLabel, ENT_QUOTES) ?>
            </span>
          </header>

          <div class="installation-card__details">
            <ul class="installation-card__meta">
              <li><i class="fa-solid fa-calendar-days" aria-hidden="true"></i> Scheduled: <?= htmlspecialchars(format_admin_date($installation['scheduled'] ?? ''), ENT_QUOTES) ?></li>
              <li><i class="fa-solid fa-flag-checkered" aria-hidden="true"></i> Target handover: <?= htmlspecialchars(format_admin_date($installation['handover'] ?? ''), ENT_QUOTES) ?></li>
              <li><i class="fa-solid fa-user-tie" aria-hidden="true"></i> Assigned employee: <?= htmlspecialchars($installation['employeeName'] ?: '—', ENT_QUOTES) ?></li>
              <li><i class="fa-solid fa-toolbox" aria-hidden="true"></i> Installer: <?= htmlspecialchars($installation['installerName'] ?: '—', ENT_QUOTES) ?></li>
            </ul>
            <form class="installation-card__amc" method="post">
              <input type="hidden" name="action" value="toggle_amc" />
              <input type="hidden" name="installation_id" value="<?= (int) $installation['id'] ?>" />
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($adminCsrfToken, ENT_QUOTES) ?>" />
              <input type="hidden" name="target_amc" value="<?= $installation['amcCommitted'] ? '0' : '1' ?>" />
              <label>
                <input type="checkbox" <?= $installation['amcCommitted'] ? 'checked' : '' ?> onchange="this.form.submit()" />
                AMC committed
              </label>
            </form>
          </div>

          <ol class="installation-card__progress">
            <?php foreach ($installation['progress'] as $step): ?>
            <li class="installation-card__progress-item installation-card__progress-item--<?= htmlspecialchars($step['state'], ENT_QUOTES) ?>">
              <?= htmlspecialchars($step['label'], ENT_QUOTES) ?>
            </li>
            <?php endforeach; ?>
          </ol>

          <div class="installation-card__actions">
            <form class="installation-card__form" method="post">
              <input type="hidden" name="action" value="update_stage" />
              <input type="hidden" name="installation_id" value="<?= (int) $installation['id'] ?>" />
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($adminCsrfToken, ENT_QUOTES) ?>" />
              <div class="installation-card__form-row">
                <label>
                  Stage
                  <select name="target_stage">
                    <?php foreach ($installation['stageOptions'] as $option): ?>
                    <option value="<?= htmlspecialchars($option['value'], ENT_QUOTES) ?>" <?= !empty($option['disabled']) ? 'disabled' : '' ?> <?= $option['value'] === $installation['stage'] ? 'selected' : '' ?>><?= htmlspecialchars($option['label'], ENT_QUOTES) ?></option>
                    <?php endforeach; ?>
                  </select>
                </label>
                <label>
                  Remarks
                  <input type="text" name="remarks" placeholder="Optional note" />
                </label>
                <label>
                  Photo reference
                  <input type="text" name="photo_label" placeholder="Filename or link" />
                </label>
              </div>
              <button type="submit" class="btn btn-secondary btn-sm">Save update</button>
            </form>

            <?php if ($installation['requestedStage'] === 'commissioned'): ?>
            <form class="installation-card__form" method="post">
              <input type="hidden" name="action" value="approve_commissioning" />
              <input type="hidden" name="installation_id" value="<?= (int) $installation['id'] ?>" />
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($adminCsrfToken, ENT_QUOTES) ?>" />
              <label>
                Approval remarks
                <input type="text" name="remarks" placeholder="Optional" />
              </label>
              <button type="submit" class="btn btn-success btn-sm">Approve commissioning</button>
            </form>
            <?php endif; ?>
          </div>

          <?php if (!empty($installation['entries'])): ?>
          <section class="installation-card__timeline" aria-label="Stage notes">
            <h3>Stage notes</h3>
            <ol>
              <?php foreach (array_reverse($installation['entries']) as $entry): ?>
              <li>
                <div>
                  <strong><?= htmlspecialchars($entry['stageLabel'], ENT_QUOTES) ?></strong>
                  <span><?= htmlspecialchars($entry['actorName'], ENT_QUOTES) ?> · <?= htmlspecialchars(format_admin_datetime($entry['timestamp']), ENT_QUOTES) ?></span>
                </div>
                <?php if ($entry['remarks'] !== ''): ?>
                <p><?= htmlspecialchars($entry['remarks'], ENT_QUOTES) ?></p>
                <?php endif; ?>
                <?php if ($entry['photo'] !== ''): ?>
                <p class="installation-card__photo">Photo: <?= htmlspecialchars($entry['photo'], ENT_QUOTES) ?></p>
                <?php endif; ?>
                <?php if ($entry['type'] === 'request'): ?>
                <span class="installation-card__badge">Commissioning approval requested.</span>
                <?php elseif ($entry['type'] === 'amc'): ?>
                <span class="installation-card__badge">AMC log updated.</span>
                <?php endif; ?>
              </li>
              <?php endforeach; ?>
            </ol>
          </section>
          <?php endif; ?>
        </article>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <section class="admin-records__table" aria-live="polite">
      <?php if (count($records) === 0): ?>
      <p class="admin-records__empty">No records match the selected filter.</p>
      <?php else: ?>
      <div class="admin-table-wrapper">
        <table class="admin-table">
          <thead>
            <tr>
              <?php foreach ($config['columns'] as $column): ?>
              <th scope="col"><?= htmlspecialchars($column, ENT_QUOTES) ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($records as $record): ?>
            <tr>
              <?php foreach ($record as $cell): ?>
              <td><?= htmlspecialchars((string) $cell, ENT_QUOTES) ?></td>
              <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </section>
  </main>

  <script src="admin-dashboard.js" defer></script>
</body>
</html>
