<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bootstrap.php';

require_admin();

$admin = current_user();
$csrfToken = $_SESSION['csrf_token'] ?? '';
$customerStore = customer_record_store();

$allowedIntegralRoles = ['admin', 'employee', 'installer', 'referrer'];
$customerStates = ['all', CustomerRecordStore::STATE_LEAD, CustomerRecordStore::STATE_ONGOING, CustomerRecordStore::STATE_INSTALLED];

$flashData = consume_flash();
$flashMessage = '';
$flashTone = 'info';
$flashIcon = 'fa-circle-info';
$flashIcons = [
    'success' => 'fa-circle-check',
    'warning' => 'fa-triangle-exclamation',
    'error' => 'fa-circle-exclamation',
    'info' => 'fa-circle-info',
];

if (is_array($flashData)) {
    if (isset($flashData['message']) && is_string($flashData['message'])) {
        $flashMessage = trim($flashData['message']);
    }
    if (isset($flashData['type']) && is_string($flashData['type'])) {
        $candidateTone = strtolower(trim($flashData['type']));
        if (isset($flashIcons[$candidateTone])) {
            $flashTone = $candidateTone;
            $flashIcon = $flashIcons[$candidateTone];
        }
    }
}

$activeTab = isset($_GET['tab']) ? strtolower(trim((string) $_GET['tab'])) : 'integral';
if (!in_array($activeTab, ['integral', 'customers'], true)) {
    $activeTab = 'integral';
}

$integralSearch = trim((string) ($_GET['integral_search'] ?? ''));
$integralPage = max(1, (int) ($_GET['integral_page'] ?? 1));
$editUserId = (int) ($_GET['edit_user'] ?? 0);

$customerState = isset($_GET['state']) ? strtolower(trim((string) $_GET['state'])) : 'all';
if (!in_array($customerState, $customerStates, true)) {
    $customerState = 'all';
}
$customerSearch = trim((string) ($_GET['customer_search'] ?? ''));
$customerPage = max(1, (int) ($_GET['customer_page'] ?? 1));
$editCustomerId = (int) ($_GET['edit_customer'] ?? 0);
$transitionAction = strtolower(trim((string) ($_GET['transition'] ?? '')));
$transitionCustomerId = (int) ($_GET['customer'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedTab = isset($_POST['tab']) ? strtolower(trim((string) $_POST['tab'])) : $activeTab;
    if (in_array($postedTab, ['integral', 'customers'], true)) {
        $activeTab = $postedTab;
    }

    if ($activeTab === 'integral') {
        $integralSearch = trim((string) ($_POST['integral_search'] ?? $integralSearch));
        $integralPage = max(1, (int) ($_POST['integral_page'] ?? $integralPage));
    } else {
        $candidateState = isset($_POST['state']) ? strtolower(trim((string) $_POST['state'])) : $customerState;
        if (in_array($candidateState, $customerStates, true)) {
            $customerState = $candidateState;
        }
        $customerSearch = trim((string) ($_POST['customer_search'] ?? $customerSearch));
        $customerPage = max(1, (int) ($_POST['customer_page'] ?? $customerPage));
    }

    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token(is_string($token) ? $token : null)) {
        set_flash('error', 'Your session expired. Please try again.');
        $redirectParams = build_admin_users_redirect_params($activeTab, $integralSearch, $integralPage, $customerState, $customerSearch, $customerPage);
        redirect_admin_users($redirectParams);
    }

    $action = (string) ($_POST['action'] ?? '');
    $actorId = (int) ($admin['id'] ?? 0);

    try {
        switch ($action) {
            case 'create_integral_user':
                admin_create_user(null, $_POST, $actorId);
                set_flash('success', 'Integral user created successfully.');
                break;
            case 'update_integral_user':
                $userId = (int) ($_POST['user_id'] ?? 0);
                if ($userId <= 0) {
                    throw new RuntimeException('Select a valid user to update.');
                }
                admin_update_integral_user(null, $userId, $_POST, $actorId);
                set_flash('success', 'Account details updated.');
                $editUserId = 0;
                break;
            case 'set_integral_user_status':
                $userId = (int) ($_POST['user_id'] ?? 0);
                $targetStatus = (string) ($_POST['target_status'] ?? '');
                if ($userId <= 0) {
                    throw new RuntimeException('Select a valid user to update.');
                }
                admin_update_user_status(null, $userId, $targetStatus, $actorId);
                set_flash('success', 'Account status updated.');
                break;
            case 'reset_integral_user_password':
                $userId = (int) ($_POST['user_id'] ?? 0);
                $newPassword = (string) ($_POST['new_password'] ?? '');
                if ($userId <= 0) {
                    throw new RuntimeException('Select a valid user to update.');
                }
                admin_reset_user_password(null, $userId, $newPassword, $actorId);
                set_flash('success', 'Password reset successfully.');
                break;
            case 'create_customer_lead':
                $customerStore->createLead([
                    'full_name' => $_POST['full_name'] ?? '',
                    'phone' => $_POST['phone'] ?? '',
                    'district' => $_POST['district'] ?? '',
                    'lead_source' => $_POST['lead_source'] ?? '',
                    'notes' => $_POST['notes'] ?? '',
                ]);
                set_flash('success', 'Lead created successfully.');
                break;
            case 'update_customer':
                $customerId = (int) ($_POST['customer_id'] ?? 0);
                if ($customerId <= 0) {
                    throw new RuntimeException('Select a valid customer to update.');
                }
                $customerStore->updateCustomer($customerId, [
                    'full_name' => $_POST['full_name'] ?? '',
                    'email' => $_POST['email'] ?? '',
                    'phone' => $_POST['phone'] ?? '',
                    'address_line' => $_POST['address_line'] ?? '',
                    'district' => $_POST['district'] ?? '',
                    'pin_code' => $_POST['pin_code'] ?? '',
                    'discom' => $_POST['discom'] ?? '',
                    'sanctioned_load' => $_POST['sanctioned_load'] ?? '',
                    'lead_source' => $_POST['lead_source'] ?? '',
                    'notes' => $_POST['notes'] ?? '',
                    'assigned_employee_id' => $_POST['assigned_employee_id'] ?? '',
                    'assigned_installer_id' => $_POST['assigned_installer_id'] ?? '',
                    'system_type' => $_POST['system_type'] ?? '',
                    'system_kwp' => $_POST['system_kwp'] ?? '',
                    'quote_number' => $_POST['quote_number'] ?? '',
                    'quote_date' => $_POST['quote_date'] ?? '',
                    'installation_status' => $_POST['installation_status'] ?? '',
                    'subsidy_application_id' => $_POST['subsidy_application_id'] ?? '',
                    'handover_date' => $_POST['handover_date'] ?? '',
                    'warranty_until' => $_POST['warranty_until'] ?? '',
                ]);
                set_flash('success', 'Customer details updated.');
                $editCustomerId = 0;
                break;
            case 'customer_move_to_ongoing':
                $customerId = (int) ($_POST['customer_id'] ?? 0);
                if ($customerId <= 0) {
                    throw new RuntimeException('Select a valid customer.');
                }
                $customerStore->changeState($customerId, CustomerRecordStore::STATE_ONGOING, [
                    'assigned_employee_id' => $_POST['assigned_employee_id'] ?? '',
                    'assigned_installer_id' => $_POST['assigned_installer_id'] ?? '',
                    'system_type' => $_POST['system_type'] ?? '',
                    'system_kwp' => $_POST['system_kwp'] ?? '',
                    'quote_number' => $_POST['quote_number'] ?? '',
                    'quote_date' => $_POST['quote_date'] ?? '',
                    'installation_status' => $_POST['installation_status'] ?? '',
                    'notes' => $_POST['notes'] ?? '',
                ]);
                set_flash('success', 'Lead moved to ongoing projects.');
                $transitionAction = '';
                $transitionCustomerId = 0;
                break;
            case 'customer_mark_installed':
                $customerId = (int) ($_POST['customer_id'] ?? 0);
                if ($customerId <= 0) {
                    throw new RuntimeException('Select a valid customer.');
                }
                $customerStore->changeState($customerId, CustomerRecordStore::STATE_INSTALLED, [
                    'handover_date' => $_POST['handover_date'] ?? '',
                    'warranty_until' => $_POST['warranty_until'] ?? '',
                    'installation_status' => $_POST['installation_status'] ?? '',
                ]);
                set_flash('success', 'Project marked as installed.');
                $transitionAction = '';
                $transitionCustomerId = 0;
                break;
            case 'customer_import_csv':
                $upload = $_FILES['customers_csv'] ?? null;
                if (!is_array($upload) || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    throw new RuntimeException('Upload a CSV file containing lead records.');
                }
                $contents = file_get_contents((string) ($upload['tmp_name'] ?? ''));
                if ($contents === false || trim($contents) === '') {
                    throw new RuntimeException('The uploaded CSV file was empty.');
                }
                $summary = $customerStore->importLeadCsv($contents);
                $parts = [sprintf('%d processed', (int) ($summary['processed'] ?? 0))];
                if (!empty($summary['created'])) {
                    $parts[] = sprintf('%d created', (int) $summary['created']);
                }
                if (!empty($summary['updated'])) {
                    $parts[] = sprintf('%d updated', (int) $summary['updated']);
                }
                if (!empty($summary['skipped'])) {
                    $parts[] = sprintf('%d skipped', (int) $summary['skipped']);
                }
                set_flash('success', 'CSV import completed: ' . implode(', ', $parts) . '.');
                break;
            default:
                throw new RuntimeException('Unsupported action.');
        }
    } catch (Throwable $exception) {
        set_flash('error', $exception->getMessage());
    }

    $redirectParams = build_admin_users_redirect_params($activeTab, $integralSearch, $integralPage, $customerState, $customerSearch, $customerPage);
    redirect_admin_users($redirectParams);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $activeTab === 'customers' && isset($_GET['export']) && strtolower((string) $_GET['export']) === 'csv') {
    $exportState = $customerState === 'all' ? null : $customerState;
    try {
        $csv = $customerStore->exportCsv($exportState);
    } catch (Throwable $exception) {
        set_flash('error', $exception->getMessage());
        $redirectParams = build_admin_users_redirect_params('customers', $integralSearch, $integralPage, $customerState, $customerSearch, $customerPage);
        redirect_admin_users($redirectParams);
    }

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="customers-' . ($exportState ?? 'all') . '-' . date('Ymd-His') . '.csv"');
    echo $csv;
    exit;
}

$allAccounts = admin_list_accounts(null, ['status' => 'all']);
$integralAccounts = array_values(array_filter($allAccounts, static function (array $account) use ($allowedIntegralRoles): bool {
    $role = strtolower((string) ($account['role'] ?? ''));
    return in_array($role, $allowedIntegralRoles, true);
}));

$integralStatusCounts = ['active' => 0, 'inactive' => 0, 'total' => 0];
foreach ($integralAccounts as $account) {
    $status = strtolower((string) ($account['status'] ?? 'inactive'));
    if (isset($integralStatusCounts[$status])) {
        $integralStatusCounts[$status]++;
    }
}
$integralStatusCounts['total'] = count($integralAccounts);

if ($integralSearch !== '') {
    $needle = strtolower($integralSearch);
    $integralAccounts = array_values(array_filter($integralAccounts, static function (array $account) use ($needle): bool {
        $haystack = strtolower(implode(' ', [
            (string) ($account['full_name'] ?? ''),
            (string) ($account['email'] ?? ''),
            (string) ($account['phone'] ?? ''),
        ]));
        return strpos($haystack, $needle) !== false;
    }));
}

usort($integralAccounts, static function (array $left, array $right): int {
    return strcmp(strtolower((string) ($left['full_name'] ?? '')), strtolower((string) ($right['full_name'] ?? '')));
});

$integralPerPage = 15;
$integralTotal = count($integralAccounts);
$integralPages = (int) max(1, ceil($integralTotal / $integralPerPage));
$integralPage = min($integralPage, $integralPages);
$integralItems = array_slice($integralAccounts, ($integralPage - 1) * $integralPerPage, $integralPerPage);

$integralEditUser = null;
if ($editUserId > 0) {
    foreach ($integralAccounts as $account) {
        if ((int) ($account['id'] ?? 0) === $editUserId) {
            $integralEditUser = $account;
            break;
        }
    }
}

$userDirectory = [];
$employeeOptions = [];
$installerOptions = [];
try {
    $userDirectory = user_store()->listAll();
} catch (Throwable $exception) {
    $userDirectory = [];
}

foreach ($userDirectory as $userRecord) {
    $id = (int) ($userRecord['id'] ?? 0);
    if ($id <= 0) {
        continue;
    }
    $role = strtolower((string) ($userRecord['role'] ?? ''));
    $status = strtolower((string) ($userRecord['status'] ?? 'inactive'));
    $name = trim((string) ($userRecord['full_name'] ?? ''));
    if ($name === '') {
        $name = 'User #' . $id;
    }
    if ($role === 'employee' && $status === 'active') {
        $employeeOptions[$id] = $name;
    }
    if ($role === 'installer' && $status === 'active') {
        $installerOptions[$id] = $name;
    }
}

$customerList = $customerStore->list([
    'state' => $customerState,
    'search' => $customerSearch,
    'page' => $customerPage,
    'per_page' => 15,
]);
$customerItems = $customerList['items'];
$customerPages = (int) max(1, $customerList['pages']);
$customerPage = min($customerPage, $customerPages);

$customerUniverse = $customerStore->list([
    'state' => 'all',
    'per_page' => 5000,
]);
$customerStateCounts = [
    'lead' => 0,
    'ongoing' => 0,
    'installed' => 0,
    'total' => 0,
];
foreach ($customerUniverse['items'] as $record) {
    $state = strtolower((string) ($record['state'] ?? CustomerRecordStore::STATE_LEAD));
    if (isset($customerStateCounts[$state])) {
        $customerStateCounts[$state]++;
    }
}
$customerStateCounts['total'] = count($customerUniverse['items']);

$customerEditRecord = null;
if ($editCustomerId > 0) {
    $customerEditRecord = $customerStore->find($editCustomerId);
    if ($customerEditRecord === null) {
        $editCustomerId = 0;
    }
}

$transitionCustomerRecord = null;
if ($transitionCustomerId > 0 && in_array($transitionAction, ['ongoing', 'installed'], true)) {
    $transitionCustomerRecord = $customerStore->find($transitionCustomerId);
    if ($transitionCustomerRecord === null) {
        $transitionCustomerId = 0;
        $transitionAction = '';
    }
}

function admin_users_format_datetime(?string $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }
    return date('d M Y · H:i', $timestamp);
}

function build_admin_users_redirect_params(string $tab, string $integralSearch, int $integralPage, string $customerState, string $customerSearch, int $customerPage): array
{
    $params = ['tab' => $tab];
    if ($tab === 'integral') {
        if ($integralSearch !== '') {
            $params['integral_search'] = $integralSearch;
        }
        if ($integralPage > 1) {
            $params['integral_page'] = $integralPage;
        }
    } else {
        if ($customerState !== 'all') {
            $params['state'] = $customerState;
        }
        if ($customerSearch !== '') {
            $params['customer_search'] = $customerSearch;
        }
        if ($customerPage > 1) {
            $params['customer_page'] = $customerPage;
        }
    }

    return $params;
}

function redirect_admin_users(array $params): void
{
    $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    header('Location: admin-users.php' . ($query !== '' ? ('?' . $query) : ''));
    exit;
}

function admin_users_state_badge_class(string $state): string
{
    $state = strtolower($state);
    return 'state-badge state-badge--' . ($state === '' ? 'lead' : $state);
}

function admin_users_safe(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function admin_users_display_assignment(?int $id, array $directory): string
{
    if ($id === null || $id <= 0) {
        return '—';
    }
    return admin_users_safe($directory[$id] ?? ('ID #' . $id));
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>User Management | Dakshayani Enterprises</title>
  <meta name="description" content="Administer Dentweb user accounts and customer lifecycles." />
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
    <div class="admin-alert admin-alert--<?= admin_users_safe($flashTone) ?>" role="status" aria-live="polite">
      <i class="fa-solid <?= admin_users_safe($flashIcon) ?>" aria-hidden="true"></i>
      <span><?= admin_users_safe($flashMessage) ?></span>
    </div>
    <?php endif; ?>

    <header class="admin-records__header">
      <div>
        <h1>User Management</h1>
        <p class="admin-muted">Integral accounts and customer progress live here. Quickly create, update, and transition records without leaving the dashboard.</p>
      </div>
      <div class="admin-records__meta">
        <a class="admin-link" href="admin-dashboard.php"><i class="fa-solid fa-gauge-high"></i> Back to overview</a>
      </div>
    </header>

    <nav class="admin-users__tabs" aria-label="User management sections">
      <a class="admin-users__tab<?= $activeTab === 'integral' ? ' is-active' : '' ?>" href="admin-users.php?<?= http_build_query(build_admin_users_redirect_params('integral', $integralSearch, $integralPage, $customerState, $customerSearch, $customerPage), '', '&', PHP_QUERY_RFC3986) ?>">
        <i class="fa-solid fa-user-gear" aria-hidden="true"></i>
        <span>Integral Users</span>
        <span class="admin-users__tab-count"><?= $integralStatusCounts['total'] ?></span>
      </a>
      <a class="admin-users__tab<?= $activeTab === 'customers' ? ' is-active' : '' ?>" href="admin-users.php?<?= http_build_query(build_admin_users_redirect_params('customers', $integralSearch, $integralPage, $customerState, $customerSearch, $customerPage), '', '&', PHP_QUERY_RFC3986) ?>">
        <i class="fa-solid fa-users" aria-hidden="true"></i>
        <span>Customers</span>
        <span class="admin-users__tab-count"><?= $customerStateCounts['total'] ?></span>
      </a>
    </nav>

    <?php if ($activeTab === 'integral'): ?>
    <section class="admin-section">
      <header class="admin-section__header">
        <div>
          <h2>Integral users</h2>
          <p class="admin-muted">Admins, employees, referrers, and installers share the same management workflow.</p>
        </div>
        <form method="get" class="admin-inline-form" aria-label="Search integral users">
          <input type="hidden" name="tab" value="integral" />
          <label class="sr-only" for="integral-search">Search integral users</label>
          <input id="integral-search" type="search" name="integral_search" value="<?= admin_users_safe($integralSearch) ?>" placeholder="Search by name, email, or phone" />
          <button type="submit" class="btn btn-secondary"><i class="fa-solid fa-search"></i> Search</button>
        </form>
      </header>

      <section class="admin-overview__cards admin-overview__cards--compact">
        <article class="admin-overview__card">
          <h3>Total</h3>
          <p><?= $integralStatusCounts['total'] ?></p>
        </article>
        <article class="admin-overview__card">
          <h3>Active</h3>
          <p><?= $integralStatusCounts['active'] ?></p>
        </article>
        <article class="admin-overview__card">
          <h3>Inactive</h3>
          <p><?= $integralStatusCounts['inactive'] ?></p>
        </article>
      </section>

      <section class="admin-section__body">
        <h3>Create integral user</h3>
        <form method="post" class="admin-form admin-form--stacked">
          <input type="hidden" name="action" value="create_integral_user" />
          <input type="hidden" name="csrf_token" value="<?= admin_users_safe($csrfToken) ?>" />
          <input type="hidden" name="tab" value="integral" />
          <div class="admin-form__grid">
            <label>
              <span>Full name</span>
              <input type="text" name="full_name" required />
            </label>
            <label>
              <span>Email</span>
              <input type="email" name="email" placeholder="team@example.com" />
            </label>
          </div>
          <div class="admin-form__grid">
            <label>
              <span>Phone</span>
              <input type="tel" name="mobile" inputmode="numeric" pattern="[0-9]{10,}" minlength="10" placeholder="10-digit phone" required />
            </label>
            <label>
              <span>Username</span>
              <input type="text" name="username" pattern="[a-z0-9._-]{3,}" placeholder="login username" required />
            </label>
          </div>
          <div class="admin-form__grid">
            <label>
              <span>Password</span>
              <input type="password" name="password" minlength="8" required />
            </label>
            <label>
              <span>Role</span>
              <select name="role" required>
                <?php foreach ($allowedIntegralRoles as $role): ?>
                <option value="<?= admin_users_safe($role) ?>"><?= ucfirst($role) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label>
              <span>Status</span>
              <select name="status">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </label>
          </div>
          <label>
            <span>Permissions note (optional)</span>
            <textarea name="permissions_note" rows="2" placeholder="Scope, region, or notes"></textarea>
          </label>
          <button type="submit" class="btn btn-primary">Create user</button>
        </form>
      </section>

      <?php if ($integralEditUser !== null): ?>
      <section class="admin-section__body" id="integral-edit">
        <h3>Edit integral user</h3>
        <form method="post" class="admin-form admin-form--stacked">
          <input type="hidden" name="action" value="update_integral_user" />
          <input type="hidden" name="csrf_token" value="<?= admin_users_safe($csrfToken) ?>" />
          <input type="hidden" name="tab" value="integral" />
          <input type="hidden" name="user_id" value="<?= (int) ($integralEditUser['id'] ?? 0) ?>" />
          <input type="hidden" name="integral_search" value="<?= admin_users_safe($integralSearch) ?>" />
          <input type="hidden" name="integral_page" value="<?= $integralPage ?>" />
          <div class="admin-form__grid">
            <label>
              <span>Full name</span>
              <input type="text" name="full_name" value="<?= admin_users_safe((string) ($integralEditUser['full_name'] ?? '')) ?>" required />
            </label>
            <label>
              <span>Email</span>
              <input type="email" name="email" value="<?= admin_users_safe((string) ($integralEditUser['email'] ?? '')) ?>" />
            </label>
          </div>
          <div class="admin-form__grid">
            <label>
              <span>Phone</span>
              <input type="tel" name="phone" inputmode="numeric" pattern="[0-9]{10,}" value="<?= admin_users_safe((string) ($integralEditUser['phone'] ?? '')) ?>" required />
            </label>
            <label>
              <span>Role</span>
              <select name="role">
                <?php foreach ($allowedIntegralRoles as $role): ?>
                <option value="<?= admin_users_safe($role) ?>"<?= strtolower((string) ($integralEditUser['role'] ?? '')) === $role ? ' selected' : '' ?>><?= ucfirst($role) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>
          <button type="submit" class="btn btn-primary">Save changes</button>
        </form>
      </section>
      <?php endif; ?>

      <div class="admin-table-wrapper">
        <table class="admin-table">
          <thead>
            <tr>
              <th scope="col">Name</th>
              <th scope="col">Role</th>
              <th scope="col">Phone</th>
              <th scope="col">Email</th>
              <th scope="col">Status</th>
              <th scope="col" class="text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($integralItems)): ?>
            <tr>
              <td colspan="6" class="admin-records__empty">No integral users match the current filter.</td>
            </tr>
            <?php else: ?>
            <?php foreach ($integralItems as $account): ?>
            <?php $status = strtolower((string) ($account['status'] ?? 'inactive')); ?>
            <tr>
              <td>
                <strong><?= admin_users_safe((string) ($account['full_name'] ?? 'User #' . ($account['id'] ?? ''))) ?></strong>
                <div class="admin-muted small">Last updated <?= admin_users_format_datetime($account['updated_at'] ?? '') ?></div>
              </td>
              <td><?= ucfirst(strtolower((string) ($account['role'] ?? ''))) ?></td>
              <td><?= admin_users_safe((string) ($account['phone'] ?? '—')) ?></td>
              <td><?= admin_users_safe((string) ($account['email'] ?? '—')) ?></td>
              <td>
                <span class="status-pill status-pill--<?= $status ?>"><?= ucfirst($status) ?></span>
              </td>
              <td class="admin-actions">
                <a class="admin-link" href="admin-users.php?<?= http_build_query(array_merge(build_admin_users_redirect_params('integral', $integralSearch, $integralPage, $customerState, $customerSearch, $customerPage), ['edit_user' => (int) ($account['id'] ?? 0)]), '', '&', PHP_QUERY_RFC3986) ?>">Edit</a>
                <form method="post" class="admin-inline-form">
                  <input type="hidden" name="action" value="set_integral_user_status" />
                  <input type="hidden" name="csrf_token" value="<?= admin_users_safe($csrfToken) ?>" />
                  <input type="hidden" name="tab" value="integral" />
                  <input type="hidden" name="user_id" value="<?= (int) ($account['id'] ?? 0) ?>" />
                  <input type="hidden" name="integral_search" value="<?= admin_users_safe($integralSearch) ?>" />
                  <input type="hidden" name="integral_page" value="<?= $integralPage ?>" />
                  <input type="hidden" name="target_status" value="<?= $status === 'active' ? 'inactive' : 'active' ?>" />
                  <button type="submit" class="btn btn-secondary"><?= $status === 'active' ? 'Deactivate' : 'Activate' ?></button>
                </form>
                <details>
                  <summary class="admin-link">Reset password</summary>
                  <form method="post" class="admin-form admin-form--inline">
                    <input type="hidden" name="action" value="reset_integral_user_password" />
                    <input type="hidden" name="csrf_token" value="<?= admin_users_safe($csrfToken) ?>" />
                    <input type="hidden" name="tab" value="integral" />
                    <input type="hidden" name="user_id" value="<?= (int) ($account['id'] ?? 0) ?>" />
                    <input type="hidden" name="integral_search" value="<?= admin_users_safe($integralSearch) ?>" />
                    <input type="hidden" name="integral_page" value="<?= $integralPage ?>" />
                    <label>
                      <span class="sr-only">New password</span>
                      <input type="password" name="new_password" minlength="8" placeholder="New password" required />
                    </label>
                    <button type="submit" class="btn btn-secondary">Save</button>
                  </form>
                </details>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <?php if ($integralPages > 1): ?>
      <nav class="admin-pagination" aria-label="Integral users pages">
        <?php for ($page = 1; $page <= $integralPages; $page++): ?>
        <a class="admin-pagination__link<?= $page === $integralPage ? ' is-active' : '' ?>" href="admin-users.php?<?= http_build_query(array_merge(build_admin_users_redirect_params('integral', $integralSearch, $page, $customerState, $customerSearch, $customerPage)), '', '&', PHP_QUERY_RFC3986) ?>"><?= $page ?></a>
        <?php endfor; ?>
      </nav>
      <?php endif; ?>
    </section>
    <?php else: ?>
    <section class="admin-section">
      <header class="admin-section__header">
        <div>
          <h2>Customers</h2>
          <p class="admin-muted">Track every prospect from first contact to handover.</p>
        </div>
        <form method="get" class="admin-inline-form" aria-label="Search customers">
          <input type="hidden" name="tab" value="customers" />
          <?php if ($customerState !== 'all'): ?>
          <input type="hidden" name="state" value="<?= admin_users_safe($customerState) ?>" />
          <?php endif; ?>
          <label class="sr-only" for="customer-search">Search customers</label>
          <input id="customer-search" type="search" name="customer_search" value="<?= admin_users_safe($customerSearch) ?>" placeholder="Search by name, phone, or quote" />
          <button type="submit" class="btn btn-secondary"><i class="fa-solid fa-search"></i> Search</button>
        </form>
      </header>

      <nav class="admin-users__tabs admin-users__tabs--status" aria-label="Customer state filter">
        <?php foreach ([
            'all' => ['label' => 'All', 'count' => $customerStateCounts['total']],
            CustomerRecordStore::STATE_LEAD => ['label' => 'Lead', 'count' => $customerStateCounts['lead']],
            CustomerRecordStore::STATE_ONGOING => ['label' => 'Ongoing', 'count' => $customerStateCounts['ongoing']],
            CustomerRecordStore::STATE_INSTALLED => ['label' => 'Installed', 'count' => $customerStateCounts['installed']],
        ] as $stateKey => $meta): ?>
        <a
          class="admin-users__tab admin-users__tab--status<?= $customerState === $stateKey ? ' is-active' : '' ?>"
          href="admin-users.php?<?= http_build_query(array_merge(build_admin_users_redirect_params('customers', $integralSearch, $integralPage, $stateKey, $customerSearch, $customerPage)), '', '&', PHP_QUERY_RFC3986) ?>"
        >
          <span><?= admin_users_safe($meta['label']) ?></span>
          <span class="admin-users__tab-count"><?= (int) $meta['count'] ?></span>
        </a>
        <?php endforeach; ?>
      </nav>

      <section class="admin-section__body">
        <h3>Create lead</h3>
        <form method="post" class="admin-form admin-form--stacked">
          <input type="hidden" name="action" value="create_customer_lead" />
          <input type="hidden" name="csrf_token" value="<?= admin_users_safe($csrfToken) ?>" />
          <input type="hidden" name="tab" value="customers" />
          <input type="hidden" name="state" value="<?= admin_users_safe($customerState) ?>" />
          <input type="hidden" name="customer_search" value="<?= admin_users_safe($customerSearch) ?>" />
          <input type="hidden" name="customer_page" value="<?= $customerPage ?>" />
          <div class="admin-form__grid">
            <label>
              <span>Full name</span>
              <input type="text" name="full_name" required />
            </label>
            <label>
              <span>Phone</span>
              <input type="tel" name="phone" inputmode="numeric" pattern="[0-9]{10,}" minlength="10" placeholder="10-digit phone" required />
            </label>
            <label>
              <span>District</span>
              <input type="text" name="district" required />
            </label>
          </div>
          <div class="admin-form__grid">
            <label>
              <span>Lead source</span>
              <input type="text" name="lead_source" placeholder="Campaign, referral, etc." />
            </label>
            <label>
              <span>Notes</span>
              <textarea name="notes" rows="2" placeholder="Discovery notes"></textarea>
            </label>
          </div>
          <button type="submit" class="btn btn-primary">Create lead</button>
        </form>
      </section>

      <section class="admin-section__body">
        <h3>CSV tools</h3>
        <div class="admin-csv-tools">
          <form method="post" enctype="multipart/form-data" class="admin-inline-form">
            <input type="hidden" name="action" value="customer_import_csv" />
            <input type="hidden" name="csrf_token" value="<?= admin_users_safe($csrfToken) ?>" />
            <input type="hidden" name="tab" value="customers" />
            <input type="hidden" name="state" value="<?= admin_users_safe($customerState) ?>" />
            <label class="file-input">
              <span>Import leads CSV</span>
              <input type="file" name="customers_csv" accept=".csv" required />
            </label>
            <button type="submit" class="btn btn-secondary">Upload</button>
          </form>
          <a class="btn btn-secondary" href="admin-users.php?<?= http_build_query(array_merge(build_admin_users_redirect_params('customers', $integralSearch, $integralPage, $customerState, $customerSearch, $customerPage), ['export' => 'csv']), '', '&', PHP_QUERY_RFC3986) ?>">
            <i class="fa-solid fa-file-arrow-down"></i> Export current view
          </a>
        </div>
      </section>

      <?php if ($customerEditRecord !== null): ?>
      <section class="admin-section__body" id="customer-edit">
        <h3>Edit customer</h3>
        <form method="post" class="admin-form admin-form--stacked">
          <input type="hidden" name="action" value="update_customer" />
          <input type="hidden" name="csrf_token" value="<?= admin_users_safe($csrfToken) ?>" />
          <input type="hidden" name="tab" value="customers" />
          <input type="hidden" name="state" value="<?= admin_users_safe($customerState) ?>" />
          <input type="hidden" name="customer_search" value="<?= admin_users_safe($customerSearch) ?>" />
          <input type="hidden" name="customer_page" value="<?= $customerPage ?>" />
          <input type="hidden" name="customer_id" value="<?= (int) ($customerEditRecord['id'] ?? 0) ?>" />
          <div class="admin-form__grid">
            <label>
              <span>Full name</span>
              <input type="text" name="full_name" value="<?= admin_users_safe((string) ($customerEditRecord['full_name'] ?? '')) ?>" required />
            </label>
            <label>
              <span>Email</span>
              <input type="email" name="email" value="<?= admin_users_safe((string) ($customerEditRecord['email'] ?? '')) ?>" />
            </label>
            <label>
              <span>Phone</span>
              <input type="tel" name="phone" inputmode="numeric" pattern="[0-9]{10,}" value="<?= admin_users_safe((string) ($customerEditRecord['phone'] ?? '')) ?>" required />
            </label>
          </div>
          <div class="admin-form__grid">
            <label>
              <span>Address</span>
              <input type="text" name="address_line" value="<?= admin_users_safe((string) ($customerEditRecord['address_line'] ?? '')) ?>" />
            </label>
            <label>
              <span>District</span>
              <input type="text" name="district" value="<?= admin_users_safe((string) ($customerEditRecord['district'] ?? '')) ?>" />
            </label>
            <label>
              <span>PIN code</span>
              <input type="text" name="pin_code" value="<?= admin_users_safe((string) ($customerEditRecord['pin_code'] ?? '')) ?>" />
            </label>
          </div>
          <div class="admin-form__grid">
            <label>
              <span>DISCOM</span>
              <input type="text" name="discom" value="<?= admin_users_safe((string) ($customerEditRecord['discom'] ?? '')) ?>" />
            </label>
            <label>
              <span>Sanctioned load</span>
              <input type="text" name="sanctioned_load" value="<?= admin_users_safe((string) ($customerEditRecord['sanctioned_load'] ?? '')) ?>" />
            </label>
            <label>
              <span>Lead source</span>
              <input type="text" name="lead_source" value="<?= admin_users_safe((string) ($customerEditRecord['lead_source'] ?? '')) ?>" />
            </label>
          </div>
          <div class="admin-form__grid">
            <label>
              <span>Assigned employee</span>
              <select name="assigned_employee_id">
                <option value="">Unassigned</option>
                <?php foreach ($employeeOptions as $id => $name): ?>
                <option value="<?= (int) $id ?>"<?= (int) ($customerEditRecord['assigned_employee_id'] ?? 0) === (int) $id ? ' selected' : '' ?>><?= admin_users_safe($name) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label>
              <span>Assigned installer</span>
              <select name="assigned_installer_id">
                <option value="">Unassigned</option>
                <?php foreach ($installerOptions as $id => $name): ?>
                <option value="<?= (int) $id ?>"<?= (int) ($customerEditRecord['assigned_installer_id'] ?? 0) === (int) $id ? ' selected' : '' ?>><?= admin_users_safe($name) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label>
              <span>System type</span>
              <input type="text" name="system_type" value="<?= admin_users_safe((string) ($customerEditRecord['system_type'] ?? '')) ?>" />
            </label>
          </div>
          <div class="admin-form__grid">
            <label>
              <span>System kWp</span>
              <input type="text" name="system_kwp" value="<?= admin_users_safe((string) ($customerEditRecord['system_kwp'] ?? '')) ?>" />
            </label>
            <label>
              <span>Quote number</span>
              <input type="text" name="quote_number" value="<?= admin_users_safe((string) ($customerEditRecord['quote_number'] ?? '')) ?>" />
            </label>
            <label>
              <span>Quote date</span>
              <input type="text" name="quote_date" value="<?= admin_users_safe((string) ($customerEditRecord['quote_date'] ?? '')) ?>" placeholder="YYYY-MM-DD" />
            </label>
          </div>
          <div class="admin-form__grid">
            <label>
              <span>Installation status</span>
              <input type="text" name="installation_status" value="<?= admin_users_safe((string) ($customerEditRecord['installation_status'] ?? '')) ?>" />
            </label>
            <label>
              <span>Subsidy application ID</span>
              <input type="text" name="subsidy_application_id" value="<?= admin_users_safe((string) ($customerEditRecord['subsidy_application_id'] ?? '')) ?>" />
            </label>
            <label>
              <span>Handover date</span>
              <input type="text" name="handover_date" value="<?= admin_users_safe((string) ($customerEditRecord['handover_date'] ?? '')) ?>" placeholder="YYYY-MM-DD" />
            </label>
          </div>
          <div class="admin-form__grid">
            <label>
              <span>Warranty until</span>
              <input type="text" name="warranty_until" value="<?= admin_users_safe((string) ($customerEditRecord['warranty_until'] ?? '')) ?>" placeholder="YYYY-MM-DD" />
            </label>
            <label>
              <span>Notes</span>
              <textarea name="notes" rows="2" placeholder="Internal notes"><?= admin_users_safe((string) ($customerEditRecord['notes'] ?? '')) ?></textarea>
            </label>
          </div>
          <button type="submit" class="btn btn-primary">Save changes</button>
        </form>
      </section>
      <?php endif; ?>

      <?php if ($transitionCustomerRecord !== null && $transitionAction === 'ongoing'): ?>
      <section class="admin-section__body" id="customer-transition-ongoing">
        <h3>Move to ongoing</h3>
        <p class="admin-hint">Assign an employee and define the proposed system to move this lead forward.</p>
        <form method="post" class="admin-form admin-form--stacked">
          <input type="hidden" name="action" value="customer_move_to_ongoing" />
          <input type="hidden" name="csrf_token" value="<?= admin_users_safe($csrfToken) ?>" />
          <input type="hidden" name="tab" value="customers" />
          <input type="hidden" name="state" value="<?= admin_users_safe($customerState) ?>" />
          <input type="hidden" name="customer_search" value="<?= admin_users_safe($customerSearch) ?>" />
          <input type="hidden" name="customer_page" value="<?= $customerPage ?>" />
          <input type="hidden" name="customer_id" value="<?= (int) ($transitionCustomerRecord['id'] ?? 0) ?>" />
          <div class="admin-form__grid">
            <label>
              <span>Assigned employee</span>
              <select name="assigned_employee_id" required>
                <option value="">Select employee</option>
                <?php foreach ($employeeOptions as $id => $name): ?>
                <option value="<?= (int) $id ?>"<?= (int) ($transitionCustomerRecord['assigned_employee_id'] ?? 0) === (int) $id ? ' selected' : '' ?>><?= admin_users_safe($name) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label>
              <span>Assigned installer</span>
              <select name="assigned_installer_id">
                <option value="">Optional</option>
                <?php foreach ($installerOptions as $id => $name): ?>
                <option value="<?= (int) $id ?>"<?= (int) ($transitionCustomerRecord['assigned_installer_id'] ?? 0) === (int) $id ? ' selected' : '' ?>><?= admin_users_safe($name) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>
          <div class="admin-form__grid">
            <label>
              <span>System type</span>
              <input type="text" name="system_type" value="<?= admin_users_safe((string) ($transitionCustomerRecord['system_type'] ?? '')) ?>" required />
            </label>
            <label>
              <span>System kWp</span>
              <input type="text" name="system_kwp" value="<?= admin_users_safe((string) ($transitionCustomerRecord['system_kwp'] ?? '')) ?>" required />
            </label>
            <label>
              <span>Installation status</span>
              <input type="text" name="installation_status" value="<?= admin_users_safe((string) ($transitionCustomerRecord['installation_status'] ?? 'structure')) ?>" />
            </label>
          </div>
          <div class="admin-form__grid">
            <label>
              <span>Quote number</span>
              <input type="text" name="quote_number" value="<?= admin_users_safe((string) ($transitionCustomerRecord['quote_number'] ?? '')) ?>" />
            </label>
            <label>
              <span>Quote date</span>
              <input type="text" name="quote_date" value="<?= admin_users_safe((string) ($transitionCustomerRecord['quote_date'] ?? '')) ?>" placeholder="YYYY-MM-DD" />
            </label>
            <label>
              <span>Notes</span>
              <textarea name="notes" rows="2" placeholder="Internal notes"><?= admin_users_safe((string) ($transitionCustomerRecord['notes'] ?? '')) ?></textarea>
            </label>
          </div>
          <p class="admin-hint">Moving to ongoing locks in the employee assignment and unlocks project tracking.</p>
          <button type="submit" class="btn btn-primary">Move to ongoing</button>
        </form>
      </section>
      <?php endif; ?>

      <?php if ($transitionCustomerRecord !== null && $transitionAction === 'installed'): ?>
      <section class="admin-section__body" id="customer-transition-installed">
        <h3>Mark as installed</h3>
        <p class="admin-hint">Provide the handover date to close the project. Complaints will open automatically for installed customers.</p>
        <form method="post" class="admin-form admin-form--stacked">
          <input type="hidden" name="action" value="customer_mark_installed" />
          <input type="hidden" name="csrf_token" value="<?= admin_users_safe($csrfToken) ?>" />
          <input type="hidden" name="tab" value="customers" />
          <input type="hidden" name="state" value="<?= admin_users_safe($customerState) ?>" />
          <input type="hidden" name="customer_search" value="<?= admin_users_safe($customerSearch) ?>" />
          <input type="hidden" name="customer_page" value="<?= $customerPage ?>" />
          <input type="hidden" name="customer_id" value="<?= (int) ($transitionCustomerRecord['id'] ?? 0) ?>" />
          <div class="admin-form__grid">
            <label>
              <span>Handover date</span>
              <input type="text" name="handover_date" value="<?= admin_users_safe((string) ($transitionCustomerRecord['handover_date'] ?? '')) ?>" placeholder="YYYY-MM-DD" required />
            </label>
            <label>
              <span>Warranty until</span>
              <input type="text" name="warranty_until" value="<?= admin_users_safe((string) ($transitionCustomerRecord['warranty_until'] ?? '')) ?>" placeholder="YYYY-MM-DD" />
            </label>
            <label>
              <span>Installation status</span>
              <input type="text" name="installation_status" value="<?= admin_users_safe((string) ($transitionCustomerRecord['installation_status'] ?? 'installed')) ?>" />
            </label>
          </div>
          <button type="submit" class="btn btn-primary">Mark installed</button>
        </form>
      </section>
      <?php endif; ?>

      <div class="admin-table-wrapper">
        <table class="admin-table">
          <thead>
            <tr>
              <th scope="col">Name</th>
              <th scope="col">Phone</th>
              <th scope="col">District</th>
              <th scope="col">State</th>
              <th scope="col">Assigned Employee</th>
              <th scope="col">Assigned Installer</th>
              <th scope="col">kWp</th>
              <th scope="col">Updated</th>
              <th scope="col" class="text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($customerItems)): ?>
            <tr>
              <td colspan="9" class="admin-records__empty">No customers match the current filter.</td>
            </tr>
            <?php else: ?>
            <?php foreach ($customerItems as $record): ?>
            <tr>
              <td>
                <strong><?= admin_users_safe((string) ($record['full_name'] ?? 'Lead #' . ($record['id'] ?? ''))) ?></strong>
                <div class="admin-muted small">Quote <?= admin_users_safe((string) ($record['quote_number'] ?? '—')) ?></div>
              </td>
              <td><?= admin_users_safe((string) ($record['phone'] ?? '—')) ?></td>
              <td><?= admin_users_safe((string) ($record['district'] ?? '—')) ?></td>
              <td><span class="<?= admin_users_state_badge_class((string) ($record['state'] ?? 'lead')) ?>"><?= ucfirst((string) ($record['state'] ?? 'lead')) ?></span></td>
              <td><?= admin_users_display_assignment(isset($record['assigned_employee_id']) ? (int) $record['assigned_employee_id'] : null, $employeeOptions) ?></td>
              <td><?= admin_users_display_assignment(isset($record['assigned_installer_id']) ? (int) $record['assigned_installer_id'] : null, $installerOptions) ?></td>
              <td><?= isset($record['system_kwp']) && $record['system_kwp'] !== null ? admin_users_safe((string) $record['system_kwp']) : '—' ?></td>
              <td><?= admin_users_format_datetime($record['updated_at'] ?? '') ?></td>
              <td class="admin-actions">
                <a class="admin-link" href="admin-users.php?<?= http_build_query(array_merge(build_admin_users_redirect_params('customers', $integralSearch, $integralPage, $customerState, $customerSearch, $customerPage), ['edit_customer' => (int) ($record['id'] ?? 0)]), '', '&', PHP_QUERY_RFC3986) ?>">Edit</a>
                <?php if (strtolower((string) ($record['state'] ?? '')) === CustomerRecordStore::STATE_LEAD): ?>
                <a class="admin-link" href="admin-users.php?<?= http_build_query(array_merge(build_admin_users_redirect_params('customers', $integralSearch, $integralPage, $customerState, $customerSearch, $customerPage), ['transition' => 'ongoing', 'customer' => (int) ($record['id'] ?? 0)]), '', '&', PHP_QUERY_RFC3986) ?>">Move to ongoing</a>
                <?php elseif (strtolower((string) ($record['state'] ?? '')) === CustomerRecordStore::STATE_ONGOING): ?>
                <a class="admin-link" href="admin-users.php?<?= http_build_query(array_merge(build_admin_users_redirect_params('customers', $integralSearch, $integralPage, $customerState, $customerSearch, $customerPage), ['transition' => 'installed', 'customer' => (int) ($record['id'] ?? 0)]), '', '&', PHP_QUERY_RFC3986) ?>">Mark installed</a>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <?php if ($customerPages > 1): ?>
      <nav class="admin-pagination" aria-label="Customer pages">
        <?php for ($page = 1; $page <= $customerPages; $page++): ?>
        <a class="admin-pagination__link<?= $page === $customerPage ? ' is-active' : '' ?>" href="admin-users.php?<?= http_build_query(array_merge(build_admin_users_redirect_params('customers', $integralSearch, $integralPage, $customerState, $customerSearch, $page)), '', '&', PHP_QUERY_RFC3986) ?>"><?= $page ?></a>
        <?php endfor; ?>
      </nav>
      <?php endif; ?>
    </section>
    <?php endif; ?>
  </main>
</body>
</html>
