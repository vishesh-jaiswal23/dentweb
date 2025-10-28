<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bootstrap.php';

require_admin();
$admin = current_user();
$db = get_db();
$csrfToken = $_SESSION['csrf_token'] ?? '';

$template = isset($_GET['template']) && is_string($_GET['template']) ? strtolower(trim($_GET['template'])) : '';
if ($template !== '') {
    $columns = [];
    $filename = 'records-template.csv';
    if (in_array($template, ['lead', 'leads', 'non-customer', 'noncustomer'], true)) {
        $columns = admin_lead_csv_columns();
        $filename = 'lead-upload-template.csv';
    } elseif (in_array($template, ['customer', 'customers'], true)) {
        $columns = admin_customer_csv_columns();
        $filename = 'customer-upload-template.csv';
    }
    if ($columns !== []) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $stream = fopen('php://output', 'wb');
        if ($stream !== false) {
            fputcsv($stream, $columns);
            fclose($stream);
        }
        exit;
    }
}

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

$importSummary = $_SESSION['import_summary'] ?? null;
unset($_SESSION['import_summary']);
if (!is_array($importSummary)) {
    $importSummary = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token(is_string($token) ? $token : null)) {
        set_flash('error', 'Your session expired. Please try again.');
        header('Location: admin-record-import.php');
        exit;
    }

    $recordType = isset($_POST['record_type']) && is_string($_POST['record_type']) ? strtolower(trim($_POST['record_type'])) : '';
    if (!in_array($recordType, ['lead', 'leads', 'customer', 'customers'], true)) {
        set_flash('error', 'Select whether you are importing leads or commissioned customers.');
        header('Location: admin-record-import.php');
        exit;
    }

    if (!isset($_FILES['csv_file']) || !is_array($_FILES['csv_file'])) {
        set_flash('error', 'Upload a CSV file to continue.');
        header('Location: admin-record-import.php');
        exit;
    }

    $file = $_FILES['csv_file'];
    $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadError === UPLOAD_ERR_NO_FILE) {
        set_flash('error', 'Select a CSV file to upload.');
        header('Location: admin-record-import.php');
        exit;
    }
    if ($uploadError !== UPLOAD_ERR_OK) {
        set_flash('error', 'The CSV upload failed. Please try again.');
        header('Location: admin-record-import.php');
        exit;
    }

    $tmpPath = (string) ($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        set_flash('error', 'Invalid CSV upload payload.');
        header('Location: admin-record-import.php');
        exit;
    }

    try {
        $summary = admin_import_contacts($db, $recordType, $tmpPath, (int) ($admin['id'] ?? 0));
        $_SESSION['import_summary'] = $summary;

        $customerCount = (int) ($summary['customers_imported'] ?? 0);
        $leadCount = (int) ($summary['leads_imported'] ?? 0);
        $promotedCount = (int) ($summary['customers_promoted'] ?? 0);
        $parts = [];
        $parts[] = sprintf('%d customer%s', $customerCount, $customerCount === 1 ? '' : 's');
        $parts[] = sprintf('%d lead%s', $leadCount, $leadCount === 1 ? '' : 's');
        if ($promotedCount > 0) {
            $parts[] = sprintf('%d promotion%s from lead to customer', $promotedCount, $promotedCount === 1 ? '' : 's');
        }
        $message = 'Import complete: ' . implode(', ', $parts) . '.';
        $newAccounts = isset($summary['accounts_created']) && is_array($summary['accounts_created']) ? count($summary['accounts_created']) : 0;
        if ($newAccounts > 0) {
            $message .= sprintf(' %d new customer portal account%s created.', $newAccounts, $newAccounts === 1 ? '' : 's');
        }
        set_flash('success', $message);
    } catch (Throwable $exception) {
        set_flash('error', $exception->getMessage());
    }

    header('Location: admin-record-import.php');
    exit;
}

function admin_import_summary_value(array $summary, string $key): int
{
    return isset($summary[$key]) ? (int) $summary[$key] : 0;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Bulk Record Import | Admin</title>
  <meta name="description" content="Upload lead and customer CSV records with automatic role assignment for the admin portal." />
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
        <h1>Bulk Record Import</h1>
        <p>Upload CSV files to add leads or activate commissioned customer accounts with complaint access.</p>
      </div>
      <div class="admin-records__meta">
        <span>Signed in as <?= htmlspecialchars($admin['full_name'] ?? 'Administrator', ENT_QUOTES) ?></span>
        <a href="logout.php" class="btn btn-ghost">Log out</a>
      </div>
    </header>

    <section class="admin-panel" aria-labelledby="import-instructions">
      <div class="admin-panel__header">
        <div>
          <h2 id="import-instructions">Import rules</h2>
          <p>Records are assigned automatically based on installation status.</p>
        </div>
      </div>
      <ul class="admin-list">
        <li>Customers are created only when <strong>Installation Status</strong> is <em>commissioned</em> <span aria-hidden="true">(and Lead Status is Converted)</span>.</li>
        <li>Non-commissioned entries remain under Leads &amp; Site Visits and cannot log in or raise complaints.</li>
        <li>Commissioned customers are added to Admin → User Management → Customers and receive portal access.</li>
        <li>If a lead tries to log in before commissioning, the portal shows “You are not a registered customer yet.” automatically.</li>
      </ul>
      <p>Need a template? Download <a href="admin-record-import.php?template=lead">lead CSV</a> or <a href="admin-record-import.php?template=customer">customer CSV</a>.</p>
    </section>

    <section class="admin-panel" aria-labelledby="import-form">
      <div class="admin-panel__header">
        <div>
          <h2 id="import-form">Upload CSV</h2>
          <p>Select the record type and upload the corresponding template.</p>
        </div>
      </div>
      <form method="post" enctype="multipart/form-data" class="admin-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" />
        <div class="admin-form__grid">
          <label>
            Record type
            <select name="record_type" required>
              <option value="customers">Commissioned customers</option>
              <option value="leads">Leads / Non-customers</option>
            </select>
          </label>
          <label>
            CSV file
            <input type="file" name="csv_file" accept=".csv" required />
          </label>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-file-arrow-up"></i> Import records</button>
      </form>
    </section>

    <?php if (is_array($importSummary)): ?>
    <section class="admin-panel" aria-labelledby="import-summary">
      <div class="admin-panel__header">
        <div>
          <h2 id="import-summary">Last import summary</h2>
          <p>Review the counts and any accounts created during the most recent upload.</p>
        </div>
      </div>
      <div class="admin-summary">
        <ul class="admin-summary__grid">
          <li><strong><?= admin_import_summary_value($importSummary, 'customers_imported') ?></strong><span>Customers processed</span></li>
          <li><strong><?= admin_import_summary_value($importSummary, 'leads_imported') ?></strong><span>Leads processed</span></li>
          <li><strong><?= admin_import_summary_value($importSummary, 'customers_promoted') ?></strong><span>Promoted from lead</span></li>
          <li><strong><?= count($importSummary['accounts_created'] ?? []) ?></strong><span>New portal accounts</span></li>
        </ul>
        <?php $skipped = $importSummary['skipped'] ?? []; ?>
        <?php if (!empty($skipped)): ?>
        <details class="admin-details">
          <summary><i class="fa-solid fa-circle-exclamation"></i> Rows skipped (<?= count($skipped) ?>)</summary>
          <ul class="admin-list">
            <?php foreach ($skipped as $item): ?>
            <?php if (!is_array($item)) { continue; } ?>
            <li>Row <?= htmlspecialchars((string) ($item['row'] ?? '?'), ENT_QUOTES) ?>: <?= htmlspecialchars((string) ($item['reason'] ?? 'Unknown reason'), ENT_QUOTES) ?></li>
            <?php endforeach; ?>
          </ul>
        </details>
        <?php endif; ?>

        <?php $accounts = $importSummary['accounts_created'] ?? []; ?>
        <?php if (!empty($accounts)): ?>
        <div class="admin-table__wrapper">
          <table class="admin-table">
            <thead>
              <tr>
                <th scope="col">Customer</th>
                <th scope="col">Mobile</th>
                <th scope="col">Temporary password</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($accounts as $account): ?>
              <?php if (!is_array($account)) { continue; } ?>
              <tr>
                <td><?= htmlspecialchars((string) ($account['name'] ?? ''), ENT_QUOTES) ?></td>
                <td><?= htmlspecialchars((string) ($account['mobile'] ?? ''), ENT_QUOTES) ?></td>
                <td><?= htmlspecialchars((string) ($account['password'] ?? ''), ENT_QUOTES) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p class="admin-muted">Share these temporary passwords securely and ask customers to update them after first login.</p>
        <?php endif; ?>
      </div>
    </section>
    <?php endif; ?>
  </main>
</body>
</html>
