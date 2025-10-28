<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bootstrap.php';

require_admin();
$admin = current_user();
$db = get_db();
$recordStore = null;
try {
    $recordStore = customer_record_store();
} catch (Throwable $recordStoreError) {
    error_log('Unable to initialise customer record storage: ' . $recordStoreError->getMessage());
}

$flashData = consume_flash();
$flashMessage = '';
$flashTone = 'info';
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
$leadsPath = $pathFor('admin-leads.php');
if (is_array($flashData)) {
    if (isset($flashData['message']) && is_string($flashData['message'])) {
        $flashMessage = trim($flashData['message']);
    }
    if (isset($flashData['type']) && is_string($flashData['type'])) {
        $flashTone = strtolower($flashData['type']);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $token = $_POST['csrf_token'] ?? null;
    if (!verify_csrf_token(is_string($token) ? $token : null)) {
        set_flash('error', 'Your session expired. Please try again.');
        header('Location: ' . $leadsPath);
        exit;
    }

    $adminId = (int) ($admin['id'] ?? 0);

    try {
        switch ($action) {
            case 'create':
                admin_create_lead($db, $_POST, $adminId);
                set_flash('success', 'Lead created successfully.');
                break;
            case 'records-import':
                if (!$recordStore instanceof CustomerRecordStore) {
                    throw new RuntimeException('Customer record storage is not available.');
                }
                $recordType = isset($_POST['record_type']) && is_string($_POST['record_type'])
                    ? strtolower(trim($_POST['record_type']))
                    : 'leads';
                $recordType = in_array($recordType, ['customers', 'customer'], true) ? 'customers' : 'leads';

                $upload = $_FILES['records_csv'] ?? null;
                if (!is_array($upload) || !isset($upload['error']) || (int) $upload['error'] !== UPLOAD_ERR_OK) {
                    throw new RuntimeException('Upload a CSV file to import lead or customer records.');
                }

                $contents = file_get_contents((string) $upload['tmp_name']);
                if ($contents === false || trim($contents) === '') {
                    throw new RuntimeException('The uploaded CSV file was empty.');
                }

                $summary = $recordStore->importCsv($recordType, $contents);
                $details = [];
                $details[] = sprintf('%d processed', (int) ($summary['processed'] ?? 0));
                if (!empty($summary['created'])) {
                    $details[] = sprintf('%d new', (int) $summary['created']);
                }
                if (!empty($summary['updated'])) {
                    $details[] = sprintf('%d updated', (int) $summary['updated']);
                }
                if (!empty($summary['customers'])) {
                    $details[] = sprintf('%d customers', (int) $summary['customers']);
                }
                if (!empty($summary['leads'])) {
                    $details[] = sprintf('%d leads', (int) $summary['leads']);
                }

                set_flash('success', 'CSV import completed: ' . implode(', ', $details) . '.');
                break;
            case 'records-update-status':
                if (!$recordStore instanceof CustomerRecordStore) {
                    throw new RuntimeException('Customer record storage is not available.');
                }
                $recordId = isset($_POST['record_id']) ? (int) $_POST['record_id'] : 0;
                if ($recordId <= 0) {
                    throw new RuntimeException('Select a valid record to update.');
                }
                $statusInput = isset($_POST['installation_status']) && is_string($_POST['installation_status'])
                    ? $_POST['installation_status']
                    : '';
                $updatedRecord = $recordStore->updateInstallationStatus($recordId, $statusInput);
                $statusMessage = strtolower((string) ($updatedRecord['record_type'] ?? 'lead')) === 'customer'
                    ? 'Record marked as commissioned and moved to customers.'
                    : 'Record status updated.';
                set_flash('success', $statusMessage);
                break;
            case 'assign':
                $leadId = (int) ($_POST['lead_id'] ?? 0);
                $employeeId = isset($_POST['assigned_to']) && $_POST['assigned_to'] !== '' ? (int) $_POST['assigned_to'] : null;
                admin_assign_lead($db, $leadId, $employeeId, $adminId);
                set_flash('success', 'Lead assignment updated.');
                break;
            case 'update-stage':
                $leadId = (int) ($_POST['lead_id'] ?? 0);
                $stage = (string) ($_POST['stage'] ?? '');
                $note = trim((string) ($_POST['note'] ?? ''));
                if ($stage === 'lost') {
                    admin_mark_lead_lost($db, $leadId, $adminId, $note);
                } else {
                    admin_update_lead_stage($db, $leadId, $stage, $adminId, $note);
                }
                set_flash('success', 'Lead stage updated.');
                break;
            case 'approve-proposal':
                $proposalId = (int) ($_POST['proposal_id'] ?? 0);
                admin_approve_lead_proposal($db, $proposalId, $adminId);
                set_flash('success', 'Proposal approved and lead converted.');
                break;
            case 'reject-proposal':
                $proposalId = (int) ($_POST['proposal_id'] ?? 0);
                $note = trim((string) ($_POST['review_note'] ?? ''));
                admin_reject_lead_proposal($db, $proposalId, $adminId, $note);
                set_flash('info', 'Proposal rejected.');
                break;
            case 'assign-referrer':
                $leadId = (int) ($_POST['lead_id'] ?? 0);
                $referrerId = isset($_POST['referrer_id']) && $_POST['referrer_id'] !== '' ? (int) $_POST['referrer_id'] : null;
                admin_assign_referrer($db, $leadId, $referrerId, $adminId);
                set_flash('success', $referrerId ? 'Referrer updated for the lead.' : 'Referrer removed from the lead.');
                break;
            case 'delete-lead':
                $leadId = (int) ($_POST['lead_id'] ?? 0);
                if ($leadId <= 0) {
                    throw new RuntimeException('Select a valid lead to delete.');
                }
                admin_delete_lead($db, $leadId, $adminId);
                set_flash('success', 'Lead deleted permanently.');
                break;
            default:
                throw new RuntimeException('Unsupported action.');
        }
    } catch (Throwable $exception) {
        set_flash('error', $exception->getMessage());
    }

    header('Location: ' . $leadsPath);
    exit;
}

function admin_format_datetime(?string $value): string
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

$employees = admin_active_employees($db);
$referrers = admin_active_referrers($db);
$referrerLookup = [];
foreach ($referrers as $referrerOption) {
    $referrerLookup[(int) $referrerOption['id']] = $referrerOption['name'];
}

$leads = [];
try {
    $leads = admin_fetch_lead_overview($db);
} catch (Throwable $leadLoadError) {
    error_log('Unable to load admin lead overview: ' . $leadLoadError->getMessage());
    if ($flashMessage === '') {
        $flashTone = 'error';
        $flashIcon = $flashIcons[$flashTone];
        $flashMessage = 'Lead data is temporarily unavailable. Please try again later.';
    }
}
$recordLeads = [];
$recordCustomers = [];
try {
    $recordLeads = customer_records_leads();
    $recordCustomers = customer_records_customers();
} catch (Throwable $recordLoadError) {
    error_log('Unable to list customer record data: ' . $recordLoadError->getMessage());
}
$installationStatusOptions = [
    'pending' => 'Pending',
    'in_progress' => 'In Progress',
    'commissioned' => 'Commissioned',
    'on_hold' => 'On Hold',
    'cancelled' => 'Cancelled',
];
$csrfToken = $_SESSION['csrf_token'] ?? '';
$stageOptions = [
    'new' => 'New',
    'visited' => 'Visited',
    'quotation' => 'Quotation',
    'lost' => 'Lost',
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Leads & Site Visits | Admin</title>
  <meta name="description" content="Manage lead assignments, site visits, and proposal approvals." />
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
<body class="admin-leads" data-theme="light">
  <main class="admin-leads__shell">
    <header class="admin-leads__header">
      <div>
        <p class="admin-leads__subtitle">Admin workspace</p>
        <h1 class="admin-leads__title">Leads &amp; Site Visits</h1>
        <p class="admin-leads__meta">Signed in as <strong><?= htmlspecialchars($admin['full_name'] ?? 'Administrator', ENT_QUOTES) ?></strong></p>
      </div>
      <div class="admin-leads__actions">
        <a href="<?= htmlspecialchars($pathFor('admin-dashboard.php'), ENT_QUOTES) ?>" class="btn btn-ghost"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to overview</a>
        <a href="<?= htmlspecialchars($pathFor('logout.php'), ENT_QUOTES) ?>" class="btn btn-primary"><i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i> Log out</a>
      </div>
    </header>

    <?php if ($flashMessage !== ''): ?>
    <div class="admin-alert admin-alert--<?= htmlspecialchars($flashTone, ENT_QUOTES) ?>" role="status" aria-live="polite">
      <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
      <span><?= htmlspecialchars($flashMessage, ENT_QUOTES) ?></span>
    </div>
    <?php endif; ?>

    <section class="admin-panel" aria-labelledby="records-panel">
      <div class="admin-panel__header">
        <div>
          <h2 id="records-panel">Customer &amp; lead records</h2>
          <p>Upload CSV files to maintain communication details and commissioned customers without relying on the SQL database.</p>
        </div>
        <span class="admin-panel__count"><?= count($recordLeads) ?> leads · <?= count($recordCustomers) ?> customers</span>
      </div>
      <form
        method="post"
        enctype="multipart/form-data"
        action="<?= htmlspecialchars($leadsPath, ENT_QUOTES) ?>"
        class="admin-form"
      >
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" />
        <input type="hidden" name="action" value="records-import" />
        <label>
          Record type
          <select name="record_type">
            <option value="leads">Leads / Non-Customers</option>
            <option value="customers">Commissioned Customers</option>
          </select>
        </label>
        <label>
          CSV file
          <input type="file" name="records_csv" accept=".csv,text/csv" required />
        </label>
        <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-file-import" aria-hidden="true"></i> Import</button>
        <p class="text-xs">
          Download templates:
          <a href="<?= htmlspecialchars($pathFor('customer-records-template.php?type=leads'), ENT_QUOTES) ?>">Leads CSV</a>
          ·
          <a href="<?= htmlspecialchars($pathFor('customer-records-template.php?type=customers'), ENT_QUOTES) ?>">Customers CSV</a>
        </p>
      </form>

      <div class="admin-table-wrapper" aria-live="polite">
        <table class="admin-table">
          <caption class="sr-only">Leads &amp; Non-Customer records uploaded via CSV</caption>
          <thead>
            <tr>
              <th scope="col">Name</th>
              <th scope="col">Mobile</th>
              <th scope="col">District</th>
              <th scope="col">Lead status</th>
              <th scope="col">Installation</th>
              <th scope="col">Updated</th>
              <th scope="col" class="admin-table__actions">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($recordLeads)): ?>
            <tr>
              <td colspan="7">
                <p class="admin-empty">No CSV-based leads recorded yet. Import a leads CSV to populate this list.</p>
              </td>
            </tr>
            <?php else: ?>
            <?php foreach ($recordLeads as $record): ?>
            <tr>
              <td data-label="Name"><?= htmlspecialchars($record['full_name'] ?? '', ENT_QUOTES) ?></td>
              <td data-label="Mobile"><?= htmlspecialchars($record['mobile_number'] ?? '', ENT_QUOTES) ?></td>
              <td data-label="District"><?= htmlspecialchars($record['district'] ?? '', ENT_QUOTES) ?></td>
              <td data-label="Lead status"><?= htmlspecialchars(ucwords(str_replace('_', ' ', (string) ($record['lead_status'] ?? ''))), ENT_QUOTES) ?></td>
              <td data-label="Installation"><?= htmlspecialchars(ucwords(str_replace('_', ' ', (string) ($record['installation_status'] ?? 'pending'))), ENT_QUOTES) ?></td>
              <td data-label="Updated"><?= htmlspecialchars(admin_format_datetime($record['updated_at'] ?? ''), ENT_QUOTES) ?></td>
              <td class="admin-table__actions" data-label="Actions">
                <form method="post" class="admin-inline-form" action="<?= htmlspecialchars($leadsPath, ENT_QUOTES) ?>">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" />
                  <input type="hidden" name="action" value="records-update-status" />
                  <input type="hidden" name="record_id" value="<?= (int) ($record['id'] ?? 0) ?>" />
                  <label class="sr-only" for="record-status-<?= (int) ($record['id'] ?? 0) ?>">Installation status</label>
                  <select name="installation_status" id="record-status-<?= (int) ($record['id'] ?? 0) ?>">
                    <?php foreach ($installationStatusOptions as $statusValue => $statusLabel): ?>
                    <option value="<?= htmlspecialchars($statusValue, ENT_QUOTES) ?>"<?= strtolower((string) ($record['installation_status'] ?? '')) === $statusValue ? ' selected' : '' ?>>
                      <?= htmlspecialchars($statusLabel, ENT_QUOTES) ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                  <button type="submit" class="btn btn-ghost btn-xs">Update</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="admin-table-wrapper" aria-live="polite">
        <table class="admin-table">
          <caption class="sr-only">Commissioned customer records uploaded via CSV</caption>
          <thead>
            <tr>
              <th scope="col">Name</th>
              <th scope="col">Mobile</th>
              <th scope="col">Installation</th>
              <th scope="col">Lead status</th>
              <th scope="col">Handover</th>
              <th scope="col">Complaints</th>
              <th scope="col">Updated</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($recordCustomers)): ?>
            <tr>
              <td colspan="7">
                <p class="admin-empty">No commissioned customers have been uploaded yet.</p>
              </td>
            </tr>
            <?php else: ?>
            <?php foreach ($recordCustomers as $record): ?>
            <tr>
              <td data-label="Name"><?= htmlspecialchars($record['full_name'] ?? '', ENT_QUOTES) ?></td>
              <td data-label="Mobile"><?= htmlspecialchars($record['mobile_number'] ?? '', ENT_QUOTES) ?></td>
              <td data-label="Installation"><?= htmlspecialchars(ucwords(str_replace('_', ' ', (string) ($record['installation_status'] ?? ''))), ENT_QUOTES) ?></td>
              <td data-label="Lead status"><?= htmlspecialchars(ucwords(str_replace('_', ' ', (string) ($record['lead_status'] ?? ''))), ENT_QUOTES) ?></td>
              <td data-label="Handover"><?= htmlspecialchars(admin_format_datetime($record['handover_date'] ?? ''), ENT_QUOTES) ?></td>
              <td data-label="Complaints"><?= htmlspecialchars($record['complaint_status'] ?? '—', ENT_QUOTES) ?></td>
              <td data-label="Updated"><?= htmlspecialchars(admin_format_datetime($record['updated_at'] ?? ''), ENT_QUOTES) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <section class="admin-panel" aria-labelledby="lead-create">
      <div class="admin-panel__header">
        <div>
          <h2 id="lead-create">Create lead</h2>
          <p>Add a new enquiry and assign it to an employee for follow-up.</p>
        </div>
      </div>
      <form method="post" action="<?= htmlspecialchars($leadsPath, ENT_QUOTES) ?>" class="admin-form">
        <input type="hidden" name="action" value="create" />
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" />
        <div class="admin-form__grid">
          <label>
            Lead name
            <input type="text" name="name" required placeholder="Customer or organisation" />
          </label>
          <label>
            Phone
            <input type="tel" name="phone" placeholder="10-digit contact" pattern="[0-9]{10}" />
          </label>
          <label>
            Email
            <input type="email" name="email" placeholder="email@example.com" />
          </label>
          <label>
            Source
            <input type="text" name="source" placeholder="Web, referral, walk-in…" />
          </label>
          <label>
            Site location
            <input type="text" name="site_location" placeholder="City / neighbourhood" />
          </label>
          <label>
            Assign to
            <select name="assigned_to">
              <option value="">Unassigned</option>
              <?php foreach ($employees as $employee): ?>
              <option value="<?= (int) $employee['id'] ?>"><?= htmlspecialchars($employee['name'], ENT_QUOTES) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>
            Referrer (optional)
            <select name="referrer_id">
              <option value="">No referrer</option>
              <?php foreach ($referrers as $referrer): ?>
              <option value="<?= (int) $referrer['id'] ?>"><?= htmlspecialchars($referrer['name'], ENT_QUOTES) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
        <label class="admin-form__full">
          Site details / notes
          <textarea name="site_details" rows="3" placeholder="Access instructions, load profile, or context to help the employee."></textarea>
        </label>
        <button type="submit" class="btn btn-primary btn-sm">Create lead</button>
      </form>
    </section>

    <section class="admin-panel" aria-labelledby="lead-pipeline">
      <div class="admin-panel__header">
        <div>
          <h2 id="lead-pipeline">Active pipeline</h2>
          <p>Track progress from first contact through quotation, approvals, and conversion.</p>
        </div>
        <span class="admin-panel__count"><?= count($leads) ?> leads</span>
      </div>
      <?php if (empty($leads)): ?>
      <p class="admin-empty">No leads available yet. Create a lead above to get started.</p>
      <?php else: ?>
      <div class="admin-lead-grid">
        <?php foreach ($leads as $lead): ?>
        <article class="admin-lead-card" data-lead-id="<?= (int) $lead['id'] ?>">
          <header class="admin-lead-card__header">
            <div>
              <h3><?= htmlspecialchars($lead['name'], ENT_QUOTES) ?></h3>
              <p class="admin-lead-card__meta">
                Stage: <span class="admin-badge admin-badge--<?= htmlspecialchars($lead['status'], ENT_QUOTES) ?>"><?= htmlspecialchars($lead['statusLabel'], ENT_QUOTES) ?></span>
              </p>
            </div>
            <ul class="admin-lead-card__info">
              <?php if ($lead['phone']): ?>
              <li><i class="fa-solid fa-phone" aria-hidden="true"></i> <?= htmlspecialchars($lead['phone'], ENT_QUOTES) ?></li>
              <?php endif; ?>
              <?php if ($lead['email']): ?>
              <li><i class="fa-solid fa-envelope" aria-hidden="true"></i> <?= htmlspecialchars($lead['email'], ENT_QUOTES) ?></li>
              <?php endif; ?>
              <?php if ($lead['siteLocation']): ?>
              <li><i class="fa-solid fa-location-dot" aria-hidden="true"></i> <?= htmlspecialchars($lead['siteLocation'], ENT_QUOTES) ?></li>
              <?php endif; ?>
              <?php if ($lead['referrerName']): ?>
              <li><i class="fa-solid fa-handshake-angle" aria-hidden="true"></i> <?= htmlspecialchars($lead['referrerName'], ENT_QUOTES) ?></li>
              <?php endif; ?>
            </ul>
          </header>

          <?php if ($lead['notes']): ?>
          <p class="admin-lead-card__notes"><strong>Notes:</strong> <?= nl2br(htmlspecialchars($lead['notes'], ENT_QUOTES)) ?></p>
          <?php endif; ?>

          <div class="admin-lead-card__actions">
            <form method="post" action="<?= htmlspecialchars($leadsPath, ENT_QUOTES) ?>" class="admin-inline-form">
              <input type="hidden" name="action" value="assign" />
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" />
              <input type="hidden" name="lead_id" value="<?= (int) $lead['id'] ?>" />
              <label>
                Assigned employee
                <select name="assigned_to">
                  <option value="">Unassigned</option>
                  <?php foreach ($employees as $employee): ?>
                  <option value="<?= (int) $employee['id'] ?>" <?= $lead['assignedId'] === (int) $employee['id'] ? 'selected' : '' ?>><?= htmlspecialchars($employee['name'], ENT_QUOTES) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <button type="submit" class="btn btn-secondary btn-xs">Save</button>
            </form>

            <form method="post" action="<?= htmlspecialchars($leadsPath, ENT_QUOTES) ?>" class="admin-inline-form">
              <input type="hidden" name="action" value="assign-referrer" />
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" />
              <input type="hidden" name="lead_id" value="<?= (int) $lead['id'] ?>" />
              <label>
                Referrer
                <select name="referrer_id">
                  <option value="">No referrer</option>
                  <?php if ($lead['referrerId'] && !isset($referrerLookup[$lead['referrerId']])): ?>
                  <option value="<?= (int) $lead['referrerId'] ?>" selected><?= htmlspecialchars($lead['referrerName'], ENT_QUOTES) ?> (inactive)</option>
                  <?php endif; ?>
                  <?php foreach ($referrers as $referrer): ?>
                  <option value="<?= (int) $referrer['id'] ?>" <?= $lead['referrerId'] === (int) $referrer['id'] ? 'selected' : '' ?>><?= htmlspecialchars($referrer['name'], ENT_QUOTES) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <button type="submit" class="btn btn-secondary btn-xs">Update</button>
            </form>

            <?php if (!in_array($lead['status'], ['converted', 'lost'], true)): ?>
            <form method="post" action="<?= htmlspecialchars($leadsPath, ENT_QUOTES) ?>" class="admin-inline-form">
              <input type="hidden" name="action" value="update-stage" />
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" />
              <input type="hidden" name="lead_id" value="<?= (int) $lead['id'] ?>" />
              <label>
                Stage
                <select name="stage">
                  <?php foreach ($stageOptions as $key => $label): ?>
                  <?php $disabled = lead_stage_index($key) < lead_stage_index($lead['status']); ?>
                  <option value="<?= htmlspecialchars($key, ENT_QUOTES) ?>" <?= $key === $lead['status'] ? 'selected' : '' ?> <?= $disabled ? 'disabled' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>
                Notes (optional)
                <input type="text" name="note" placeholder="Reason or outcome" />
              </label>
              <button type="submit" class="btn btn-secondary btn-xs">Update</button>
            </form>
            <?php endif; ?>
            <form method="post" action="<?= htmlspecialchars($leadsPath, ENT_QUOTES) ?>" class="admin-inline-form" onsubmit="return confirm('Delete this lead and all related records?');">
              <input type="hidden" name="action" value="delete-lead" />
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" />
              <input type="hidden" name="lead_id" value="<?= (int) $lead['id'] ?>" />
              <button type="submit" class="btn btn-danger btn-xs">Delete lead</button>
            </form>
          </div>

          <div class="admin-lead-card__timeline">
            <div>
              <h4>Recent visits</h4>
              <?php if (empty($lead['visits'])): ?>
              <p class="admin-muted">No visits logged yet.</p>
              <?php else: ?>
              <ul class="admin-visit-list">
                <?php foreach (array_slice($lead['visits'], 0, 3) as $visit): ?>
                <li>
                  <time datetime="<?= htmlspecialchars($visit['createdAt'], ENT_QUOTES) ?>"><?= admin_format_datetime($visit['createdAt']) ?></time>
                  <p><?= nl2br(htmlspecialchars($visit['note'], ENT_QUOTES)) ?></p>
                  <?php if (!empty($visit['photoDataUrl'])): ?>
                  <a href="<?= htmlspecialchars($visit['photoDataUrl'], ENT_QUOTES) ?>" target="_blank" rel="noopener" class="admin-link">View photo</a>
                  <?php endif; ?>
                  <?php if ($visit['employeeName']): ?>
                  <span class="admin-muted">Logged by <?= htmlspecialchars($visit['employeeName'], ENT_QUOTES) ?></span>
                  <?php endif; ?>
                </li>
                <?php endforeach; ?>
              </ul>
              <?php endif; ?>
            </div>
            <div>
              <h4>Proposals</h4>
              <?php if (empty($lead['proposals'])): ?>
              <p class="admin-muted">No proposals submitted yet.</p>
              <?php else: ?>
              <ul class="admin-proposal-list">
                <?php foreach ($lead['proposals'] as $proposal): ?>
                <li class="admin-proposal admin-proposal--<?= htmlspecialchars($proposal['status'], ENT_QUOTES) ?>">
                  <div>
                    <strong><?= htmlspecialchars(ucfirst($proposal['status']), ENT_QUOTES) ?></strong>
                    <span><?= admin_format_datetime($proposal['createdAt']) ?></span>
                    <p><?= nl2br(htmlspecialchars($proposal['summary'], ENT_QUOTES)) ?></p>
                    <?php if ($proposal['estimate'] !== null): ?>
                    <span class="admin-muted">Estimate: ₹<?= number_format((float) $proposal['estimate']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($proposal['documentUrl'])): ?>
                    <a href="<?= htmlspecialchars($proposal['documentUrl'], ENT_QUOTES) ?>" target="_blank" rel="noopener" class="admin-link">Download <?= htmlspecialchars($proposal['documentName'] ?: 'attachment', ENT_QUOTES) ?></a>
                    <?php endif; ?>
                    <?php if ($proposal['reviewNote']): ?>
                    <span class="admin-muted">Review: <?= htmlspecialchars($proposal['reviewNote'], ENT_QUOTES) ?></span>
                    <?php endif; ?>
                  </div>
                  <?php if ($proposal['status'] === 'pending'): ?>
                  <div class="admin-proposal__actions">
                    <form method="post" action="<?= htmlspecialchars($leadsPath, ENT_QUOTES) ?>">
                      <input type="hidden" name="action" value="approve-proposal" />
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" />
                      <input type="hidden" name="proposal_id" value="<?= (int) $proposal['id'] ?>" />
                      <button type="submit" class="btn btn-success btn-xs">Approve</button>
                    </form>
                    <form method="post" action="<?= htmlspecialchars($leadsPath, ENT_QUOTES) ?>">
                      <input type="hidden" name="action" value="reject-proposal" />
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>" />
                      <input type="hidden" name="proposal_id" value="<?= (int) $proposal['id'] ?>" />
                      <input type="text" name="review_note" placeholder="Reason" />
                      <button type="submit" class="btn btn-danger btn-xs">Reject</button>
                    </form>
                  </div>
                  <?php endif; ?>
                </li>
                <?php endforeach; ?>
              </ul>
              <?php endif; ?>
            </div>
          </div>

          <footer class="admin-lead-card__footer">
            <span>Created <?= admin_format_datetime($lead['createdAt']) ?></span>
            <span>Updated <?= admin_format_datetime($lead['updatedAt']) ?></span>
          </footer>
        </article>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>
